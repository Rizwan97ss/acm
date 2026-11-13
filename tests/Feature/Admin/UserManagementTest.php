<?php

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\School;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

const USER_ADMIN_PERMISSIONS = ['users.view', 'users.create', 'users.edit', 'users.delete', 'students.view'];

/**
 * @return array<string, mixed>
 */
function userPayload(array $overrides = []): array
{
    return [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'phone' => '+1 (555) 123-4567',
        'password' => 'secret-password',
        'password_confirmation' => 'secret-password',
        'is_active' => true,
        'roles' => [],
        ...$overrides,
    ];
}

test('users without the users.view permission cannot list users', function () {
    $user = userWithPermissions(['students.view']);

    $this->actingAs($user)->get(route('admin.users.index'))->assertForbidden();
});

test('school users only see users of their own school', function () {
    $school = School::factory()->create();
    $admin = userWithPermissions(USER_ADMIN_PERMISSIONS, $school);
    $colleague = User::factory()->forSchool($school)->create();
    User::factory()->forSchool(School::factory()->create())->create();

    $response = $this->actingAs($admin)->get(route('admin.users.index'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('admin/users/index')
        ->where('users.data', fn ($users) => collect($users)->pluck('id')->sort()->values()->all() === collect([$admin->id, $colleague->id])->sort()->values()->all())
    );
});

test('an admin creates a user in their own school with an encrypted phone number', function () {
    $school = School::factory()->create();
    $admin = userWithPermissions(USER_ADMIN_PERMISSIONS, $school);
    $role = Role::factory()->forSchool($school)->withPermissions(['students.view'])->create();

    $response = $this->actingAs($admin)->post(route('admin.users.store'), userPayload(['roles' => [$role->id]]));

    $response->assertSessionHasNoErrors()->assertRedirect(route('admin.users.index'));
    $user = User::query()->where('email', 'jane@example.com')->firstOrFail();
    expect($user->school_id)->toBe($school->id)
        ->and($user->phone)->toBe('+1 (555) 123-4567')
        ->and($user->roles->pluck('id')->all())->toBe([$role->id]);
    expect(DB::table('users')->where('id', $user->id)->value('phone'))->not->toContain('555');
});

test('the audit trail masks encrypted and hidden attributes', function () {
    $admin = userWithPermissions(USER_ADMIN_PERMISSIONS);

    $this->actingAs($admin)->post(route('admin.users.store'), userPayload());

    $created = User::query()->where('email', 'jane@example.com')->sole();
    $log = AuditLog::query()->where('event', 'user.created')->where('auditable_id', $created->id)->sole();
    expect($log->user_id)->toBe($admin->id)
        ->and($log->new_values['phone'])->toBe('[encrypted]')
        ->and($log->new_values['password'])->toBe('[redacted]')
        ->and($log->new_values['email'])->toBe('jane@example.com');
});

test('users can be found by exact phone number through the blind index', function () {
    $admin = userWithPermissions(USER_ADMIN_PERMISSIONS);
    $match = User::factory()->create(['phone' => '+1 555 987 6543']);
    User::factory()->create(['phone' => '+1 555 000 0000']);

    $response = $this->actingAs($admin)->get(route('admin.users.index', ['search' => '15559876543']));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('users.data', 1)
        ->where('users.data.0.id', $match->id)
    );
});

test('phone numbers are masked for viewers who cannot edit the user', function () {
    $viewer = userWithPermissions(['users.view']);
    User::factory()->create(['phone' => '+1 555 987 6543', 'name' => 'Aaron First']);

    $response = $this->actingAs($viewer)->get(route('admin.users.index', ['sort' => 'name']));

    $response->assertInertia(fn (Assert $page) => $page->where('users.data.0.phone', '•••• 43'));
});

test('admins cannot assign a role granting permissions they do not hold', function () {
    $school = School::factory()->create();
    $admin = userWithPermissions(USER_ADMIN_PERMISSIONS, $school);
    $powerful = Role::factory()->forSchool($school)->withPermissions(['payroll.approve'])->create();

    $response = $this->actingAs($admin)->post(route('admin.users.store'), userPayload(['roles' => [$powerful->id]]));

    $response->assertSessionHasErrors('roles');
    $this->assertDatabaseMissing('users', ['email' => 'jane@example.com']);
});

test('admins cannot assign roles belonging to another school', function () {
    $school = School::factory()->create();
    $admin = userWithPermissions(USER_ADMIN_PERMISSIONS, $school);
    $foreignRole = Role::factory()->forSchool(School::factory()->create())->withPermissions(['students.view'])->create();

    $response = $this->actingAs($admin)->post(route('admin.users.store'), userPayload(['roles' => [$foreignRole->id]]));

    $response->assertSessionHasErrors('roles');
    $this->assertDatabaseMissing('users', ['email' => 'jane@example.com']);
});

test('school users cannot choose the school of a new user', function () {
    $school = School::factory()->create();
    $admin = userWithPermissions(USER_ADMIN_PERMISSIONS, $school);
    $otherSchool = School::factory()->create();

    $response = $this->actingAs($admin)->post(route('admin.users.store'), userPayload(['school_id' => $otherSchool->id]));

    $response->assertSessionHasErrors('school_id');
    $this->assertDatabaseMissing('users', ['email' => 'jane@example.com']);
});

test('admins cannot edit users of another school', function () {
    $admin = userWithPermissions(USER_ADMIN_PERMISSIONS, School::factory()->create());
    $outsider = User::factory()->forSchool(School::factory()->create())->create();

    $this->actingAs($admin)->get(route('admin.users.edit', $outsider))->assertForbidden();
    $this->actingAs($admin)->put(route('admin.users.update', $outsider), userPayload())->assertForbidden();

    expect($outsider->fresh()->email)->not->toBe('jane@example.com');
});

test('admins cannot edit users holding more permissions than themselves', function () {
    $admin = userWithPermissions(USER_ADMIN_PERMISSIONS);
    $target = superAdmin();

    $this->actingAs($admin)->get(route('admin.users.edit', $target))->assertForbidden();
});

test('admins cannot delete their own account from user management', function () {
    $admin = userWithPermissions(USER_ADMIN_PERMISSIONS);

    $this->actingAs($admin)->delete(route('admin.users.destroy', $admin))->assertForbidden();

    $this->assertModelExists($admin);
});

test('an admin updates a user and their roles', function () {
    $school = School::factory()->create();
    $admin = userWithPermissions(USER_ADMIN_PERMISSIONS, $school);
    $user = User::factory()->forSchool($school)->create();
    $role = Role::factory()->forSchool($school)->withPermissions(['students.view'])->create();

    $response = $this->actingAs($admin)->put(route('admin.users.update', $user), userPayload([
        'password' => '',
        'password_confirmation' => '',
        'is_active' => false,
        'roles' => [$role->id],
    ]));

    $response->assertSessionHasNoErrors()->assertRedirect(route('admin.users.index'));
    $user->refresh();
    expect($user->name)->toBe('Jane Doe')
        ->and($user->is_active)->toBeFalse()
        ->and($user->roles->pluck('id')->all())->toBe([$role->id]);
    $this->assertDatabaseHas('audit_logs', ['event' => 'user.roles_synced', 'auditable_id' => $user->id]);
});

test('an admin deletes a user', function () {
    $school = School::factory()->create();
    $admin = userWithPermissions(USER_ADMIN_PERMISSIONS, $school);
    $user = User::factory()->forSchool($school)->create();

    $this->actingAs($admin)->delete(route('admin.users.destroy', $user))->assertRedirect(route('admin.users.index'));

    $this->assertModelMissing($user);
});

test('unknown sort columns are rejected', function () {
    $admin = userWithPermissions(USER_ADMIN_PERMISSIONS);

    $this->actingAs($admin)->get(route('admin.users.index', ['sort' => 'password']))->assertSessionHasErrors('sort');
});
