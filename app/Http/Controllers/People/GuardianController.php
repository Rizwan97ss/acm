<?php

namespace App\Http\Controllers\People;

use App\Http\Controllers\Controller;
use App\Http\Requests\People\GuardianRequest;
use App\Http\Resources\GuardianResource;
use App\Models\Guardian;
use App\Services\Tenancy\CurrentSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class GuardianController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Guardian::class);

        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100']]);

        $guardians = Guardian::query()
            ->withCount('students')
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $this->applySearch($query, $search))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('people/guardians/index', [
            'guardians' => GuardianResource::collection($guardians),
            'filters' => $filters,
            'can' => ['create' => $request->user()->can('create', Guardian::class)],
        ]);
    }

    /**
     * JSON lookup used when linking a guardian to a student.
     */
    public function search(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Guardian::class);

        $search = (string) $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']])['q'];

        $guardians = Guardian::query()
            ->tap(fn (Builder $query) => $this->applySearch($query, $search))
            ->orderBy('last_name')
            ->limit(10)
            ->get();

        return response()->json(GuardianResource::collection($guardians)->resolve($request));
    }

    public function create(): Response
    {
        Gate::authorize('create', Guardian::class);

        return Inertia::render('people/guardians/create');
    }

    public function store(GuardianRequest $request, CurrentSchool $currentSchool): RedirectResponse
    {
        Guardian::query()->create([...$request->validated(), 'school_id' => $currentSchool->id()]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Guardian created.')]);

        return to_route('people.guardians.index');
    }

    public function edit(Request $request, Guardian $guardian): Response
    {
        Gate::authorize('update', $guardian);

        return Inertia::render('people/guardians/edit', [
            'guardian' => (new GuardianResource($guardian->load('students')))->resolve($request),
        ]);
    }

    public function update(GuardianRequest $request, Guardian $guardian): RedirectResponse
    {
        $guardian->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Guardian updated.')]);

        return to_route('people.guardians.index');
    }

    public function destroy(Guardian $guardian): RedirectResponse
    {
        Gate::authorize('delete', $guardian);

        if ($guardian->students()->exists()) {
            throw ValidationException::withMessages(['guardian' => __('Unlink this guardian from their students first.')]);
        }

        $guardian->user?->update(['is_active' => false]);
        $guardian->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Guardian deleted.')]);

        return to_route('people.guardians.index');
    }

    /**
     * Partial match on name or email; exact match on phone via its blind index.
     *
     * @param  Builder<Guardian>  $query
     */
    private function applySearch(Builder $query, string $search): void
    {
        $escaped = addcslashes($search, '%_\\');
        $phoneIndex = (new Guardian)->blindIndexFor('phone', $search);

        $query->where(function (Builder $query) use ($escaped, $phoneIndex): void {
            $query->where('first_name', 'like', "%{$escaped}%")
                ->orWhere('last_name', 'like', "%{$escaped}%")
                ->orWhere('email', 'like', "%{$escaped}%");

            if ($phoneIndex !== null) {
                $query->orWhere('phone_index', $phoneIndex);
            }
        });
    }
}
