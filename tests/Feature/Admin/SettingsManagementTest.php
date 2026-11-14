<?php

use App\Enums\SettingScope;
use App\Models\School;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Inertia\Testing\AssertableInertia as Assert;

const SETTINGS_PERMISSIONS = ['settings.view', 'settings.edit'];

test('settings resolve from user, then school, then global, then default', function () {
    $settings = app(SettingsService::class);
    $school = School::factory()->create();

    expect($settings->get('localization.timezone', $school->id, 7))->toBe('UTC');

    $settings->put(SettingScope::Global, 0, ['localization.timezone' => 'Europe/London']);
    expect($settings->get('localization.timezone', $school->id, 7))->toBe('Europe/London');

    $settings->put(SettingScope::School, $school->id, ['localization.timezone' => 'Asia/Karachi']);
    expect($settings->get('localization.timezone', $school->id, 7))->toBe('Asia/Karachi');

    $settings->put(SettingScope::User, 7, ['localization.timezone' => 'America/New_York']);
    expect($settings->get('localization.timezone', $school->id, 7))->toBe('America/New_York')
        ->and($settings->get('localization.timezone', $school->id, 8))->toBe('Asia/Karachi');
});

test('a school admin overrides branding for their school only', function () {
    $school = School::factory()->create();
    $admin = userWithPermissions(SETTINGS_PERMISSIONS, $school);

    $response = $this->actingAs($admin)->put(route('admin.settings.update', 'branding'), [
        'values' => ['app_name' => 'Greenfield', 'primary_color' => '#16a34a', 'default_theme' => 'dark'],
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect(route('admin.settings.edit', 'branding'));
    $settings = app(SettingsService::class);
    expect($settings->get('branding.primary_color', $school->id))->toBe('#16a34a')
        ->and($settings->get('branding.primary_color'))->toBe('#4f46e5');
    $this->assertDatabaseHas('audit_logs', ['event' => 'settings.school.updated', 'user_id' => $admin->id]);
});

test('a null value removes a school override', function () {
    $school = School::factory()->create();
    $admin = userWithPermissions(SETTINGS_PERMISSIONS, $school);
    app(SettingsService::class)->put(SettingScope::School, $school->id, ['branding.primary_color' => '#16a34a']);

    $this->actingAs($admin)->put(route('admin.settings.update', 'branding'), [
        'values' => ['app_name' => null, 'primary_color' => null, 'default_theme' => null],
    ])->assertSessionHasNoErrors();

    expect(app(SettingsService::class)->get('branding.primary_color', $school->id))->toBe('#4f46e5');
});

test('setting values are validated against the registry', function () {
    $admin = userWithPermissions(SETTINGS_PERMISSIONS);

    $response = $this->actingAs($admin)->put(route('admin.settings.update', 'branding'), [
        'values' => ['app_name' => 'X', 'primary_color' => 'red;}body{display:none', 'default_theme' => 'neon'],
    ]);

    $response->assertSessionHasErrors(['values.primary_color', 'values.default_theme']);
    expect(app(SettingsService::class)->get('branding.primary_color'))->toBe('#4f46e5');
});

test('platform-only security settings are not available inside a school', function () {
    $admin = userWithPermissions(SETTINGS_PERMISSIONS, School::factory()->create());

    $this->actingAs($admin)->get(route('admin.settings.edit', 'security'))->assertNotFound();
    $this->actingAs($admin)->put(route('admin.settings.update', 'security'), [
        'values' => ['password_min_length' => 8],
    ])->assertNotFound();
});

test('viewing settings requires settings.view and saving requires settings.edit', function () {
    $viewer = userWithPermissions(['settings.view']);

    $this->actingAs(userWithPermissions(['users.view']))->get(route('admin.settings.edit', 'branding'))->assertForbidden();
    $this->actingAs($viewer)->get(route('admin.settings.edit', 'branding'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('admin/settings/edit')->where('canEdit', false));
    $this->actingAs($viewer)->put(route('admin.settings.update', 'branding'), [
        'values' => ['primary_color' => '#000000'],
    ])->assertForbidden();
});

test('the global password policy setting strengthens password validation', function () {
    app(SettingsService::class)->put(SettingScope::Global, 0, [
        'security.password_min_length' => 14,
        'security.password_require_numbers' => true,
    ]);

    expect(Validator::make(['password' => 'longpasswordonly'], ['password' => Password::default()])->fails())->toBeTrue()
        ->and(Validator::make(['password' => 'longpassword1234'], ['password' => Password::default()])->passes())->toBeTrue();
});

test('users save personal preferences that override school settings', function () {
    $user = userWithPermissions([]);

    $this->actingAs($user)->put(route('preferences.update', 'localization'), [
        'values' => ['timezone' => 'Asia/Tokyo', 'date_format' => 'd/m/Y', 'time_format' => null],
    ])->assertSessionHasNoErrors()->assertRedirect(route('preferences.edit'));

    expect(app(SettingsService::class)->get('localization.timezone', null, $user->id))->toBe('Asia/Tokyo');
});

test('users cannot set school-only settings as personal preferences', function () {
    $user = userWithPermissions([]);

    $this->actingAs($user)->put(route('preferences.update', 'branding'), [
        'values' => ['primary_color' => '#000000'],
    ])->assertNotFound();
});

test('invalid preference values are rejected', function () {
    $user = userWithPermissions([]);

    $this->actingAs($user)->put(route('preferences.update', 'localization'), [
        'values' => ['timezone' => 'Mars/Olympus'],
    ])->assertSessionHasErrors('values.timezone');
});
