<?php

use App\Models\School;
use App\Models\User;
use App\Services\Settings\SettingsService;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => config(['tenancy.mode' => 'single', 'tenancy.default_institute' => ['name' => 'Sunrise Academy', 'code' => 'sun']]));

test('the institute is created on first use and every user works in it', function () {
    $admin = superAdmin();

    $this->actingAs($admin)->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('tenant.mode', 'single')
        ->where('tenant.canSwitch', false)
        ->where('tenant.current.name', 'Sunrise Academy')
    );

    expect(School::query()->sole()->code)->toBe('SUN');
});

test('academic and people modules open without choosing a school', function () {
    $this->actingAs(superAdmin())->get(route('academics.academic-years.index'))->assertOk();
});

test('schools cannot be created or switched and the list opens the institute profile', function () {
    $admin = superAdmin();
    $institute = School::factory()->create();

    $this->actingAs($admin)->get(route('admin.schools.index'))->assertRedirect(route('admin.schools.edit', $institute));
    $this->actingAs($admin)->post(route('admin.schools.store'), ['name' => 'Second', 'code' => 'TWO', 'is_active' => true])->assertForbidden();
    $this->actingAs($admin)->post(route('admin.schools.switch', $institute))->assertForbidden();

    expect(School::query()->count())->toBe(1);
});

test('new users are placed in the institute', function () {
    $institute = School::factory()->create();

    $this->actingAs(superAdmin())->post(route('admin.users.store'), [
        'name' => 'New Staff', 'email' => 'staff@example.com', 'password' => 'secret-password',
        'password_confirmation' => 'secret-password', 'is_active' => true, 'roles' => [],
    ])->assertSessionHasNoErrors();

    expect(User::query()->where('email', 'staff@example.com')->sole()->school_id)->toBe($institute->id);
});

test('settings, including security, are edited institute-wide', function () {
    School::factory()->create();
    $admin = superAdmin();

    $this->actingAs($admin)->get(route('admin.settings.edit', 'security'))->assertOk();
    $this->actingAs($admin)->put(route('admin.settings.update', 'branding'), [
        'values' => ['app_name' => 'Sunrise', 'primary_color' => '#db2777', 'default_theme' => 'light'],
    ])->assertSessionHasNoErrors();

    expect(app(SettingsService::class)->get('branding.primary_color'))->toBe('#db2777');
});
