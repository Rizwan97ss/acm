<?php

use App\Enums\SettingScope;
use App\Models\AuditLog;
use App\Models\School;
use App\Models\User;
use App\Services\Settings\SettingsService;

test('deactivated users cannot log in even with the correct password', function () {
    $user = User::factory()->inactive()->create();

    $response = $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('users deactivated mid-session are signed out', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $user->update(['is_active' => false]);

    $this->get(route('dashboard'))->assertRedirect(route('login'));

    $this->assertGuest();
});

test('users of a deactivated school are signed out', function () {
    $school = School::factory()->inactive()->create();
    $user = User::factory()->forSchool($school)->create();

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('login'));

    $this->assertGuest();
});

test('users of a deleted school are signed out instead of falling back to the global context', function () {
    $school = School::factory()->create();
    $user = userWithPermissions(['audit_logs.view'], $school);
    $school->delete();

    $this->actingAs($user)->get(route('admin.audit-logs.index'))->assertRedirect(route('login'));

    $this->assertGuest();
});

test('idle sessions expire after the configured timeout', function () {
    app(SettingsService::class)->put(SettingScope::Global, 0, ['security.session_idle_timeout' => 15]);
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertOk();
    $this->travel(16)->minutes();

    $this->get(route('dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});

test('users without two-factor authentication are sent to enrol when it is mandatory', function () {
    app(SettingsService::class)->put(SettingScope::Global, 0, ['security.require_two_factor' => true]);

    $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertRedirect(route('security.edit'));
    $this->actingAs(User::factory()->withTwoFactor()->create())->get(route('dashboard'))->assertOk();
});

test('successful logins are audited and recorded on the user', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->last_login_at)->not->toBeNull();
    $this->assertDatabaseHas('audit_logs', ['event' => 'auth.login', 'user_id' => $user->id]);
});

test('failed logins are audited without the attempted password', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password']);

    $log = AuditLog::query()->where('event', 'auth.failed')->sole();
    expect($log->new_values)->toBe(['email' => $user->email])
        ->and(json_encode($log->getAttributes()))->not->toContain('wrong-password');
});

test('audit logs are restricted to the viewer\'s school', function () {
    $school = School::factory()->create();
    $viewer = userWithPermissions(['audit_logs.view'], $school);
    AuditLog::query()->create(['school_id' => $school->id, 'event' => 'own.event']);
    AuditLog::query()->create(['school_id' => School::factory()->create()->id, 'event' => 'foreign.event']);

    $response = $this->actingAs($viewer)->get(route('admin.audit-logs.index'));

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->where('logs.data', fn ($logs) => collect($logs)->pluck('event')->contains('own.event')
            && ! collect($logs)->pluck('event')->contains('foreign.event'))
    );
});

test('audit logs require the audit_logs.view permission', function () {
    $this->actingAs(userWithPermissions(['users.view']))->get(route('admin.audit-logs.index'))->assertForbidden();
});
