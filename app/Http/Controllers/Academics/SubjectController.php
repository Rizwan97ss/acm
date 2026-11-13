<?php

namespace App\Http\Controllers\Academics;

use App\Enums\SubjectType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Academics\SubjectRequest;
use App\Http\Resources\SubjectResource;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SubjectController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Subject::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'in:'.implode(',', array_column(SubjectType::cases(), 'value'))],
        ]);

        $subjects = Subject::query()
            ->withCount('classes')
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $escaped = addcslashes($search, '%_\\');
                $query->where(fn (Builder $query) => $query->where('name', 'like', "%{$escaped}%")->orWhere('code', 'like', "%{$escaped}%"));
            })
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('academics/subjects/index', [
            'subjects' => SubjectResource::collection($subjects),
            'filters' => $filters,
            'types' => $this->typeOptions(),
            'can' => ['create' => $request->user()->can('create', Subject::class)],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Subject::class);

        return Inertia::render('academics/subjects/create', ['types' => $this->typeOptions()]);
    }

    public function store(SubjectRequest $request): RedirectResponse
    {
        Subject::query()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Subject created.')]);

        return to_route('academics.subjects.index');
    }

    public function edit(Request $request, Subject $subject): Response
    {
        Gate::authorize('update', $subject);

        return Inertia::render('academics/subjects/edit', [
            'subject' => (new SubjectResource($subject))->resolve($request),
            'types' => $this->typeOptions(),
        ]);
    }

    public function update(SubjectRequest $request, Subject $subject): RedirectResponse
    {
        $subject->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Subject updated.')]);

        return to_route('academics.subjects.index');
    }

    public function destroy(Subject $subject): RedirectResponse
    {
        Gate::authorize('delete', $subject);

        if ($subject->timetableEntries()->exists()) {
            throw ValidationException::withMessages(['subject' => __('This subject is scheduled in a timetable. Deactivate it instead.')]);
        }

        $subject->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Subject deleted.')]);

        return to_route('academics.subjects.index');
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function typeOptions(): array
    {
        return array_map(fn (SubjectType $type): array => ['value' => $type->value, 'label' => $type->label()], SubjectType::cases());
    }
}
