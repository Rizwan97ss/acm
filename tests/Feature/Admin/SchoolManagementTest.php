<?php

use App\Models\School;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

const SCHOOL_ADMIN_PERMISSIONS = ['schools.view', 'schools.create', 'schools.edit', 'schools.delete', 'users.view'];

test('platform admins create schools with a logo', function () {
    Storage::fake('public');
    $admin = userWithPermissions(SCHOOL_ADMIN_PERMISSIONS);

    $response = $this->actingAs($admin)->post(route('admin.schools.store'), [
        'name' => 'Riverside Academy',
        'code' => 'rsa',
        'is_active' => true,
        'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect(route('admin.schools.index'));
    $school = School::query()->where('code', 'RSA')->sole();
    Storage::disk('public')->assertExists($school->logo_path);
});

test('svg logos are rejected', function () {
    Storage::fake('public');
    $admin = userWithPermissions(SCHOOL_ADMIN_PERMISSIONS);

    $response = $this->actingAs($admin)->post(route('admin.schools.store'), [
        'name' => 'Riverside Academy',
        'code' => 'RSA',
        'is_active' => true,
        'logo' => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml'),
    ]);

    $response->assertSessionHasErrors('logo');
    $this->assertDatabaseMissing('schools', ['code' => 'RSA']);
});

test('school-bound users cannot create schools', function () {
    $admin = userWithPermissions(SCHOOL_ADMIN_PERMISSIONS, School::factory()->create());

    $this->actingAs($admin)->post(route('admin.schools.store'), [
        'name' => 'Rogue', 'code' => 'ROGUE', 'is_active' => true,
    ])->assertForbidden();

    $this->assertDatabaseMissing('schools', ['code' => 'ROGUE']);
});

test('school-bound users only see their own school and its branches', function () {
    $school = School::factory()->create();
    $branch = School::factory()->branchOf($school)->create();
    School::factory()->create();
    $admin = userWithPermissions(SCHOOL_ADMIN_PERMISSIONS, $school);

    $response = $this->actingAs($admin)->get(route('admin.schools.index'));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('schools.data', fn ($schools) => collect($schools)->pluck('id')->sort()->values()->all() === [$school->id, $branch->id])
    );
});

test('school-bound users cannot edit other schools', function () {
    $admin = userWithPermissions(SCHOOL_ADMIN_PERMISSIONS, School::factory()->create());
    $other = School::factory()->create();

    $this->actingAs($admin)->get(route('admin.schools.edit', $other))->assertForbidden();
});

test('schools with users cannot be deleted', function () {
    $admin = userWithPermissions(SCHOOL_ADMIN_PERMISSIONS);
    $school = School::factory()->create();
    User::factory()->forSchool($school)->create();

    $this->actingAs($admin)->delete(route('admin.schools.destroy', $school))->assertSessionHasErrors('school');

    $this->assertNotSoftDeleted($school);
});

test('platform users switch into a school and see only its data', function () {
    $admin = userWithPermissions(SCHOOL_ADMIN_PERMISSIONS);
    $school = School::factory()->create();
    $member = User::factory()->forSchool($school)->create();
    User::factory()->forSchool(School::factory()->create())->create();

    $this->actingAs($admin)->post(route('admin.schools.switch', $school))->assertRedirect(route('dashboard'));

    $this->get(route('admin.users.index'))->assertInertia(fn (Assert $page) => $page
        ->where('tenant.current.id', $school->id)
        ->has('users.data', 1)
        ->where('users.data.0.id', $member->id)
    );
});

test('school-bound users cannot switch schools', function () {
    $admin = userWithPermissions(SCHOOL_ADMIN_PERMISSIONS, School::factory()->create());
    $other = School::factory()->create();

    $this->actingAs($admin)->post(route('admin.schools.switch', $other))->assertForbidden();
});
