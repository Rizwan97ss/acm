<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SchoolRequest;
use App\Http\Resources\SchoolResource;
use App\Models\School;
use App\Services\Tenancy\Tenancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class SchoolController extends Controller
{
    public function index(Request $request, Tenancy $tenancy): Response|RedirectResponse
    {
        Gate::authorize('viewAny', School::class);

        if ($tenancy->isSingle()) {
            return to_route('admin.schools.edit', $tenancy->institute());
        }

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);

        $actor = $request->user();

        $schools = School::query()
            ->with('parent:id,name')
            ->withCount(['users', 'branches'])
            ->when(! $actor->isPlatformUser(), fn (Builder $query) => $query->where(
                fn (Builder $query) => $query->whereKey($actor->school_id)->orWhere('parent_id', $actor->school_id),
            ))
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $escaped = addcslashes($search, '%_\\');
                $query->where(fn (Builder $query) => $query->where('name', 'like', "%{$escaped}%")->orWhere('code', 'like', "%{$escaped}%"));
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('is_active', $status === 'active'))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/schools/index', [
            'schools' => SchoolResource::collection($schools),
            'filters' => $filters,
            'can' => ['create' => $actor->can('create', School::class)],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', School::class);

        return Inertia::render('admin/schools/create', [
            'parents' => $this->parentOptions(null),
        ]);
    }

    public function store(SchoolRequest $request): RedirectResponse
    {
        $school = new School(Arr::except($request->validated(), ['logo', 'remove_logo']));

        if ($request->hasFile('logo')) {
            $school->logo_path = $this->storeLogo($request);
        }

        $school->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('School created.')]);

        return to_route('admin.schools.index');
    }

    public function edit(Request $request, School $school): Response
    {
        Gate::authorize('update', $school);

        return Inertia::render('admin/schools/edit', [
            'school' => (new SchoolResource($school))->resolve($request),
            'parents' => $request->user()->isPlatformUser() ? $this->parentOptions($school) : [],
        ]);
    }

    public function update(SchoolRequest $request, School $school): RedirectResponse
    {
        $school->fill(Arr::except($request->validated(), ['logo', 'remove_logo']));
        $previousLogo = $school->logo_path;

        if ($request->hasFile('logo')) {
            $school->logo_path = $this->storeLogo($request);
        } elseif ($request->boolean('remove_logo')) {
            $school->logo_path = null;
        }

        $school->save();

        if ($previousLogo !== null && $previousLogo !== $school->logo_path) {
            Storage::disk('public')->delete($previousLogo);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('School updated.')]);

        return to_route('admin.schools.index');
    }

    public function destroy(School $school): RedirectResponse
    {
        Gate::authorize('delete', $school);

        if ($school->users()->exists() || $school->branches()->exists()) {
            throw ValidationException::withMessages([
                'school' => __('This school still has users or branches. Deactivate it instead.'),
            ]);
        }

        DB::transaction(fn () => $school->delete());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('School deleted.')]);

        return to_route('admin.schools.index');
    }

    private function storeLogo(SchoolRequest $request): string
    {
        $path = $request->file('logo')?->store('schools/logos', 'public');

        if (! is_string($path)) {
            throw new RuntimeException('The logo could not be stored.');
        }

        return $path;
    }

    /**
     * Only top-level schools can be parents; branches are one level deep.
     *
     * @return Collection<int, School>
     */
    private function parentOptions(?School $school)
    {
        return School::query()
            ->whereNull('parent_id')
            ->when($school, fn (Builder $query, School $school) => $query->whereKeyNot($school->id))
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
