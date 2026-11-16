<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\School;
use App\Models\User;
use App\Services\Tenancy\CurrentSchool;
use App\Services\Tenancy\Tenancy;
use App\Services\Users\UserService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    private const array SORTABLE = ['name', 'email', 'last_login_at', 'created_at'];

    public function __construct(
        private CurrentSchool $currentSchool,
        private UserService $users,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', 'integer'],
            'status' => ['nullable', 'in:active,inactive'],
            'sort' => ['nullable', 'in:'.implode(',', self::SORTABLE)],
            'direction' => ['nullable', 'in:asc,desc'],
        ]);

        $users = User::query()
            ->with(['roles:id,name', 'school:id,name'])
            ->when($this->currentSchool->id(), fn (Builder $query, int $schoolId) => $query->where('school_id', $schoolId))
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $this->applySearch($query, $search))
            ->when($filters['role'] ?? null, fn (Builder $query, int $roleId) => $query->whereHas('roles', fn (Builder $roles) => $roles->whereKey($roleId)))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('is_active', $status === 'active'))
            ->orderBy($filters['sort'] ?? 'name', $filters['direction'] ?? 'asc')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/users/index', [
            'users' => UserResource::collection($users),
            'filters' => $filters,
            'roles' => Role::query()->availableTo($this->currentSchool->id())->orderBy('name')->get(['id', 'name']),
            'can' => ['create' => $request->user()->can('create', User::class)],
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', User::class);

        return Inertia::render('admin/users/create', $this->formOptions($request));
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $this->users->create($request->user(), $request->payload());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User created.')]);

        return to_route('admin.users.index');
    }

    public function edit(Request $request, User $user): Response
    {
        Gate::authorize('update', $user);

        $user->load('roles:id');

        return Inertia::render('admin/users/edit', [
            ...$this->formOptions($request),
            'user' => [
                ...(new UserResource($user))->resolve($request),
                'school_id' => $user->school_id,
                'role_ids' => $user->roles->pluck('id'),
            ],
            'canResetTwoFactor' => $request->user()->can('resetTwoFactor', $user),
        ]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $this->users->update($request->user(), $user, $request->payload());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User updated.')]);

        return to_route('admin.users.index');
    }

    public function destroy(User $user): RedirectResponse
    {
        Gate::authorize('delete', $user);

        $this->users->delete($user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User deleted.')]);

        return to_route('admin.users.index');
    }

    /**
     * Match name or email partially, or the encrypted phone exactly via its blind index.
     *
     * @param  Builder<User>  $query
     */
    private function applySearch(Builder $query, string $search): void
    {
        $phoneIndex = (new User)->blindIndexFor('phone', $search);
        $escaped = addcslashes($search, '%_\\');

        $query->where(function (Builder $query) use ($escaped, $phoneIndex): void {
            $query->where('name', 'like', "%{$escaped}%")
                ->orWhere('email', 'like', "%{$escaped}%");

            if ($phoneIndex !== null) {
                $query->orWhere('phone_index', $phoneIndex);
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(Request $request): array
    {
        $actor = $request->user();
        $schoolId = $actor->isPlatformUser() ? null : $actor->school_id;

        $roles = Role::query()
            ->when(! $actor->isPlatformUser(), fn (Builder $query) => $query->availableTo($schoolId))
            ->when(! $actor->isSuperAdmin(), fn (Builder $query) => $query->where('slug', '!=', config('rbac.super_admin_role')))
            ->orderByRaw('school_id is not null')
            ->orderBy('name')
            ->get(['id', 'name', 'school_id', 'description']);

        return [
            'roles' => $roles,
            'schools' => $actor->isPlatformUser() && app(Tenancy::class)->isMulti()
                ? School::query()->orderBy('name')->get(['id', 'name'])
                : [],
            'defaultSchoolId' => $this->currentSchool->id(),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ];
    }
}
