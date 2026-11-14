<?php

namespace App\Http\Controllers\People;

use App\Enums\EmployeeStatus;
use App\Enums\EmploymentType;
use App\Enums\Gender;
use App\Http\Controllers\Controller;
use App\Http\Requests\People\EmployeeRequest;
use App\Http\Resources\EmployeeResource;
use App\Models\Employee;
use App\Models\User;
use App\Services\People\ReferenceNumberGenerator;
use App\Services\Tenancy\CurrentSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Staff directory. Users who only hold teachers.* permissions see and manage teaching staff only.
 */
class EmployeeController extends Controller
{
    public function __construct(private CurrentSchool $currentSchool) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Employee::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'kind' => ['nullable', 'in:teaching,non_teaching'],
            'status' => ['nullable', 'in:'.implode(',', array_column(EmployeeStatus::cases(), 'value'))],
        ]);

        $user = $request->user();
        $teachingOnly = ! $user->hasPermission('employees.view');

        $employees = Employee::query()
            ->with('user:id,name,email')
            ->when($teachingOnly, fn (Builder $query) => $query->where('is_teaching', true))
            ->when($filters['kind'] ?? null, fn (Builder $query, string $kind) => $query->where('is_teaching', $kind === 'teaching'))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $escaped = addcslashes($search, '%_\\');
                $query->where(fn (Builder $query) => $query
                    ->where('first_name', 'like', "%{$escaped}%")
                    ->orWhere('last_name', 'like', "%{$escaped}%")
                    ->orWhere('employee_no', 'like', "%{$escaped}%")
                    ->orWhere('designation', 'like', "%{$escaped}%"));
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('people/staff/index', [
            'employees' => EmployeeResource::collection($employees),
            'filters' => $filters,
            'statuses' => EmployeeStatus::options(),
            'teachingOnly' => $teachingOnly,
            'can' => ['create' => $user->can('create', Employee::class)],
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Employee::class);

        return Inertia::render('people/staff/create', $this->formOptions($request, null));
    }

    public function store(EmployeeRequest $request, ReferenceNumberGenerator $numbers): RedirectResponse
    {
        $schoolId = (int) $this->currentSchool->id();
        $attributes = Arr::except($request->validated(), ['employee_no']);
        $create = fn (string $number): Employee => Employee::query()->create([...$attributes, 'school_id' => $schoolId, 'employee_no' => $number]);

        $employee = $request->filled('employee_no')
            ? $create($request->string('employee_no')->toString())
            : $numbers->withNext('employee', $schoolId, $create);

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name added (:no).', ['name' => $employee->full_name, 'no' => $employee->employee_no])]);

        return to_route('people.staff.index');
    }

    public function edit(Request $request, Employee $staff): Response
    {
        Gate::authorize('update', $staff);

        return Inertia::render('people/staff/edit', [
            ...$this->formOptions($request, $staff),
            'employee' => (new EmployeeResource($staff->load('user')))->resolve($request),
        ]);
    }

    public function update(EmployeeRequest $request, Employee $staff): RedirectResponse
    {
        $staff->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Staff record updated.')]);

        return to_route('people.staff.index');
    }

    public function destroy(Employee $staff): RedirectResponse
    {
        Gate::authorize('delete', $staff);

        $staff->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Staff record archived.')]);

        return to_route('people.staff.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(Request $request, ?Employee $employee): array
    {
        return [
            'genders' => Gender::options(),
            'employmentTypes' => EmploymentType::options(),
            'statuses' => EmployeeStatus::options(),
            'canManageNonTeaching' => $request->user()->hasPermission($employee === null ? 'employees.create' : 'employees.edit'),
            'users' => User::query()
                ->where('school_id', $this->currentSchool->id())
                ->where(fn (Builder $query) => $query
                    ->whereDoesntHave('employee', fn (Builder $employees) => $employees->withoutGlobalScopes())
                    ->when($employee?->user_id, fn (Builder $query, int $userId) => $query->orWhere('id', $userId)))
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
        ];
    }
}
