<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\School;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

test('a role inherits every permission of its parent role', function () {
    $parent = Role::factory()->withPermissions(['students.view'])->create();
    $child = Role::factory()->withPermissions(['grades.edit'])->create(['parent_id' => $parent->id]);
    $user = User::factory()->withRoles($child)->create();

    expect($user->hasPermission('students.view'))->toBeTrue()
        ->and($user->hasPermission('grades.edit'))->toBeTrue()
        ->and($user->hasPermission('students.delete'))->toBeFalse();
});

test('module permissions are enforced through the gate', function () {
    $user = userWithPermissions(['students.view']);

    expect(Gate::forUser($user)->allows('students.view'))->toBeTrue()
        ->and(Gate::forUser($user)->allows('students.delete'))->toBeFalse();
});

test('super admins hold every permission', function () {
    $user = superAdmin();

    expect(Gate::forUser($user)->allows('payroll.approve'))->toBeTrue();
});

test('cached permissions are refreshed when a role changes', function () {
    $this->artisan('rbac:sync');
    $user = userWithPermissions(['students.view']);
    expect($user->hasPermission('fees.view'))->toBeFalse();

    $role = $user->roles()->first();
    $this->actingAs(superAdmin())->put(route('admin.roles.update', $role), [
        'name' => $role->name,
        'permissions' => ['students.view', 'fees.view'],
    ])->assertSessionHasNoErrors();

    expect(User::find($user->id)->hasPermission('fees.view'))->toBeTrue();
});

test('rbac sync creates permissions and system roles from config', function () {
    $this->artisan('rbac:sync')->assertSuccessful();

    expect(Permission::query()->where('name', 'students.view')->exists())->toBeTrue();

    $teacher = Role::query()->whereNull('school_id')->where('slug', 'teacher')->firstOrFail();
    expect($teacher->is_system)->toBeTrue()
        ->and($teacher->permissions()->pluck('name')->all())->toContain('attendance.create')
        ->not->toContain('fees.view');
});

test('rbac sync keeps administrator changes to existing system roles', function () {
    $this->artisan('rbac:sync');
    $teacher = Role::query()->whereNull('school_id')->where('slug', 'teacher')->firstOrFail();
    $teacher->permissions()->sync(Permission::query()->where('name', 'fees.view')->pluck('id'));

    $this->artisan('rbac:sync')->assertSuccessful();

    expect($teacher->permissions()->pluck('name')->all())->toBe(['fees.view']);
});

test('the first super admin can be granted from the console', function () {
    $user = User::factory()->forSchool(School::factory()->create())->create(['email' => 'owner@example.com']);

    $this->artisan('rbac:grant-super-admin', ['email' => 'OWNER@example.com'])->assertSuccessful();

    expect($user->fresh()->isSuperAdmin())->toBeTrue()
        ->and($user->fresh()->school_id)->toBeNull();
    $this->artisan('rbac:grant-super-admin', ['email' => 'nobody@example.com'])->assertFailed();
});
