<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use App\Services\Rbac\PermissionRegistry;
use App\Services\Rbac\PermissionResolver;
use App\Services\Rbac\RoleService;
use App\Services\Tenancy\CurrentSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function __construct(
        private CurrentSchool $currentSchool,
        private RoleService $roles,
        private PermissionRegistry $registry,
        private PermissionResolver $resolver,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Role::class);

        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100']]);

        $roles = Role::query()
            ->with(['school:id,name', 'parent:id,name'])
            ->withCount(['permissions', 'users' => fn (Builder $query) => $query->when(
                $this->currentSchool->id(),
                fn (Builder $query, int $schoolId) => $query->where('users.school_id', $schoolId),
            )])
            ->when($this->currentSchool->id(), fn (Builder $query, int $schoolId) => $query->availableTo($schoolId))
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where('name', 'like', '%'.addcslashes($search, '%_\\').'%'))
            ->orderByRaw('school_id is not null')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/roles/index', [
            'roles' => RoleResource::collection($roles),
            'filters' => $filters,
            'can' => ['create' => $request->user()->can('create', Role::class)],
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Role::class);

        return Inertia::render('admin/roles/create', $this->formOptions($request, null));
    }

    public function store(RoleRequest $request): RedirectResponse
    {
        $actor = $request->user();
        $schoolId = $actor->isPlatformUser() ? $this->currentSchool->id() : $actor->school_id;

        $this->roles->create($actor, $schoolId, $request->payload());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role created.')]);

        return to_route('admin.roles.index');
    }

    public function edit(Request $request, Role $role): Response
    {
        Gate::authorize('update', $role);

        return Inertia::render('admin/roles/edit', [
            ...$this->formOptions($request, $role),
            'role' => [
                ...(new RoleResource($role))->resolve($request),
                'permissions' => $role->permissions()->pluck('name'),
            ],
        ]);
    }

    public function update(RoleRequest $request, Role $role): RedirectResponse
    {
        $this->roles->update($request->user(), $role, $request->payload());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role updated.')]);

        return to_route('admin.roles.index');
    }

    public function destroy(Role $role): RedirectResponse
    {
        Gate::authorize('delete', $role);

        $this->roles->delete($role);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role deleted.')]);

        return to_route('admin.roles.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(Request $request, ?Role $role): array
    {
        $schoolId = $role->school_id ?? $this->currentSchool->id();

        $parents = Role::query()
            ->availableTo($schoolId)
            ->where('slug', '!=', config('rbac.super_admin_role'))
            ->when($role, fn (Builder $query, Role $role) => $query->whereKeyNot($role->id))
            ->orderBy('name')
            ->get(['id', 'name', 'school_id']);

        return [
            'modules' => collect($this->registry->modules())
                ->map(fn (array $module, string $key): array => ['key' => $key, ...$module])
                ->values(),
            'parents' => $parents->map(fn (Role $parent): array => [
                'id' => $parent->id,
                'name' => $parent->name,
                'permissions' => $this->resolver->permissionsForRole($parent),
            ]),
            'grantable' => $this->resolver->permissionsFor($request->user()),
        ];
    }
}
