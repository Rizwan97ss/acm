<?php

use App\Models\Role;
use App\Models\School;
use App\Models\User;

const ROLE_ADMIN_PERMISSIONS = ['roles.view', 'roles.create', 'roles.edit', 'roles.delete', 'students.view', 'students.create'];

test('users without roles.view cannot list roles', function () {
    $user = userWithPermissions(['students.view']);

    $this->actingAs($user)->get(route('admin.roles.index'))->assertForbidden();
});

test('a school admin creates a role owned by their school', function () {
    $school = School::factory()->create();
    $admin = userWithPermissions(ROLE_ADMIN_PERMISSIONS, $school);

    $response = $this->actingAs($admin)->post(route('admin.roles.store'), [
        'name' => 'Class Monitor',
        'permissions' => ['students.view'],
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect(route('admin.roles.index'));
    $role = Role::query()->where('slug', 'class-monitor')->sole();
    expect($role->school_id)->toBe($school->id)
        ->and($role->permissions()->pluck('name')->all())->toBe(['students.view']);
    $this->assertDatabaseHas('audit_logs', ['event' => 'role.permissions_synced', 'auditable_id' => $role->id]);
});

test('roles cannot grant permissions the actor does not hold', function () {
    $admin = userWithPermissions(ROLE_ADMIN_PERMISSIONS, School::factory()->create());

    $response = $this->actingAs($admin)->post(route('admin.roles.store'), [
        'name' => 'Sneaky',
        'permissions' => ['students.view', 'payroll.approve'],
    ]);

    $response->assertSessionHasErrors('permissions');
    $this->assertDatabaseMissing('roles', ['slug' => 'sneaky']);
});

test('roles cannot inherit from a parent granting permissions the actor does not hold', function () {
    $school = School::factory()->create();
    $admin = userWithPermissions(ROLE_ADMIN_PERMISSIONS, $school);
    $parent = Role::factory()->forSchool($school)->withPermissions(['payroll.approve'])->create();

    $response = $this->actingAs($admin)->post(route('admin.roles.store'), [
        'name' => 'Sneaky',
        'parent_id' => $parent->id,
        'permissions' => [],
    ]);

    $response->assertSessionHasErrors('permissions');
    $this->assertDatabaseMissing('roles', ['slug' => 'sneaky']);
});

test('unknown permissions are rejected', function () {
    $admin = superAdmin();

    $this->actingAs($admin)->post(route('admin.roles.store'), [
        'name' => 'Odd',
        'permissions' => ['students.teleport'],
    ])->assertSessionHasErrors('permissions.0');
});

test('school admins cannot change platform-wide roles', function () {
    $admin = userWithPermissions(ROLE_ADMIN_PERMISSIONS, School::factory()->create());
    $systemRole = Role::factory()->withPermissions(['students.view'])->create(['is_system' => true]);

    $this->actingAs($admin)->put(route('admin.roles.update', $systemRole), [
        'name' => 'Renamed',
        'permissions' => [],
    ])->assertForbidden();

    expect($systemRole->fresh()->name)->not->toBe('Renamed');
});

test('school admins cannot change roles of another school', function () {
    $admin = userWithPermissions(ROLE_ADMIN_PERMISSIONS, School::factory()->create());
    $foreign = Role::factory()->forSchool(School::factory()->create())->create();

    $this->actingAs($admin)->get(route('admin.roles.edit', $foreign))->assertForbidden();
});

test('a role cannot inherit from its own descendant', function () {
    $admin = superAdmin();
    $parent = Role::factory()->create();
    $child = Role::factory()->create(['parent_id' => $parent->id]);

    $response = $this->actingAs($admin)->put(route('admin.roles.update', $parent), [
        'name' => $parent->name,
        'parent_id' => $child->id,
        'permissions' => [],
    ]);

    $response->assertSessionHasErrors('parent_id');
    expect($parent->fresh()->parent_id)->toBeNull();
});

test('roles assigned to users cannot be deleted', function () {
    $school = School::factory()->create();
    $admin = userWithPermissions(ROLE_ADMIN_PERMISSIONS, $school);
    $role = Role::factory()->forSchool($school)->create();
    User::factory()->forSchool($school)->withRoles($role)->create();

    $this->actingAs($admin)->delete(route('admin.roles.destroy', $role))->assertSessionHasErrors('role');

    $this->assertModelExists($role);
});

test('system roles cannot be deleted', function () {
    $role = Role::factory()->create(['is_system' => true]);

    $this->actingAs(superAdmin())->delete(route('admin.roles.destroy', $role))->assertForbidden();

    $this->assertModelExists($role);
});

test('an unused school role can be deleted', function () {
    $school = School::factory()->create();
    $admin = userWithPermissions(ROLE_ADMIN_PERMISSIONS, $school);
    $role = Role::factory()->forSchool($school)->create();

    $this->actingAs($admin)->delete(route('admin.roles.destroy', $role))->assertRedirect(route('admin.roles.index'));

    $this->assertModelMissing($role);
});
