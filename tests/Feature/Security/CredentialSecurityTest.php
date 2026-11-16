<?php

use App\Http\Middleware\AddContentSecurityPolicy;
use App\Http\Middleware\ThrottleAuthEndpoints;
use App\Models\AuditLog;
use App\Models\School;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Vite;

/**
 * Stores a browser session row for the user, as the database session driver would.
 */
function storeSessionFor(User $user, string $id): void
{
    DB::table('sessions')->insert([
        'id' => $id,
        'user_id' => $user->id,
        'ip_address' => '10.0.0.1',
        'user_agent' => 'Test',
        'payload' => base64_encode(serialize([])),
        'last_activity' => now()->timestamp,
    ]);
}

beforeEach(function () {
    config(['session.driver' => 'database']);
});

test('changing your password signs out your other sessions and API tokens', function () {
    $user = User::factory()->create();
    $user->createToken('Phone');
    storeSessionFor($user, 'other-device');
    $rememberToken = $user->remember_token;

    $this->actingAs($user)->put(route('user-password.update'), [
        'current_password' => 'password',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertSessionHasNoErrors();

    expect(DB::table('sessions')->where('id', 'other-device')->exists())->toBeFalse()
        ->and($user->tokens()->count())->toBe(0)
        ->and($user->refresh()->remember_token)->not->toBe($rememberToken)
        ->and(AuditLog::query()->where('event', 'auth.password_changed')->where('auditable_id', $user->id)->exists())->toBeTrue();

    $this->assertAuthenticatedAs($user);
});

test('resetting a forgotten password signs out every session and API token', function () {
    Notification::fake();
    $user = User::factory()->create();
    $user->createToken('Phone');
    storeSessionFor($user, 'stolen-session');

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        $this->post(route('password.update'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])->assertSessionHasNoErrors();

        return true;
    });

    expect(DB::table('sessions')->where('user_id', $user->id)->exists())->toBeFalse()
        ->and($user->tokens()->count())->toBe(0);
});

test('an administrator setting a new password or deactivating a user signs them out', function (array $change) {
    $school = School::factory()->create();
    $admin = userWithPermissions(['users.view', 'users.edit'], $school);
    $user = User::factory()->forSchool($school)->create();
    $user->createToken('Phone');
    storeSessionFor($user, 'user-session');

    $this->actingAs($admin)->put(route('admin.users.update', $user), [
        'name' => $user->name,
        'email' => $user->email,
        'is_active' => true,
        'roles' => [],
        ...$change,
    ])->assertSessionHasNoErrors();

    expect(DB::table('sessions')->where('id', 'user-session')->exists())->toBeFalse()
        ->and($user->tokens()->count())->toBe(0);
})->with([
    'new password' => [['password' => 'Another-password-123', 'password_confirmation' => 'Another-password-123']],
    'deactivated' => [['is_active' => false]],
]);

test('an administrator can turn off two-factor sign-in for a user who lost their device', function () {
    $school = School::factory()->create();
    $admin = userWithPermissions(['users.view', 'users.edit'], $school);
    $user = User::factory()->forSchool($school)->withTwoFactor()->create();
    $user->createToken('Phone');

    $this->actingAs($admin)
        ->delete(route('admin.users.two-factor.destroy', $user))
        ->assertRedirect();

    $user->refresh();

    expect($user->two_factor_secret)->toBeNull()
        ->and($user->two_factor_recovery_codes)->toBeNull()
        ->and($user->two_factor_confirmed_at)->toBeNull()
        ->and($user->tokens()->count())->toBe(0)
        ->and(AuditLog::query()->where('event', 'user.two_factor_reset')->where('user_id', $admin->id)->exists())->toBeTrue();
});

test('two-factor reset is refused for yourself, without permission, or across schools', function () {
    $school = School::factory()->create();
    $admin = userWithPermissions(['users.view', 'users.edit'], $school)->forceFill(['two_factor_secret' => encrypt('secret')]);
    $admin->save();
    $viewer = userWithPermissions(['users.view'], $school);
    $sameSchool = User::factory()->forSchool($school)->withTwoFactor()->create();
    $otherSchool = User::factory()->forSchool(School::factory()->create())->withTwoFactor()->create();

    $this->actingAs($admin)->delete(route('admin.users.two-factor.destroy', $admin))->assertForbidden();
    $this->actingAs($viewer)->delete(route('admin.users.two-factor.destroy', $sameSchool))->assertForbidden();
    $this->actingAs($admin)->delete(route('admin.users.two-factor.destroy', $otherSchool))->assertForbidden();

    expect($sameSchool->refresh()->two_factor_secret)->not->toBeNull();
});

test('reset-link requests are throttled per IP across different email addresses', function () {
    Notification::fake();
    $attempts = ThrottleAuthEndpoints::LIMITS['password.email'][0] * ThrottleAuthEndpoints::PER_IP_MULTIPLIER;

    foreach (range(1, $attempts) as $attempt) {
        $this->post(route('password.email'), ['email' => "person{$attempt}@example.com"])
            ->assertSessionDoesntHaveErrors(['email' => __('Too many attempts. Please try again in :seconds seconds.', ['seconds' => 60])]);
    }

    $this->post(route('password.email'), ['email' => 'one-more@example.com'])
        ->assertInvalid(['email' => 'Too many attempts']);
});

test('password confirmation is throttled', function () {
    $user = User::factory()->create();

    foreach (range(1, 5) as $attempt) {
        $this->actingAs($user)->post(route('password.confirm.store'), ['password' => 'wrong']);
    }

    $this->actingAs($user)
        ->post(route('password.confirm.store'), ['password' => 'password'])
        ->assertInvalid(['password' => 'Too many attempts']);

    expect(session('auth.password_confirmed_at'))->toBeNull();
});

test('public registration is turned off', function () {
    $this->get('/register')->assertNotFound();
    $this->post('/register', [
        'name' => 'Intruder', 'email' => 'intruder@example.com', 'password' => 'password', 'password_confirmation' => 'password',
    ])->assertNotFound();

    expect(User::query()->where('email', 'intruder@example.com')->exists())->toBeFalse();
});

test('pages carry a content security policy whose nonce matches the inline script', function () {
    Vite::useHotFile(storage_path('framework/testing/no-hot-file'));

    $response = $this->get(route('login'))->assertOk();
    $policy = (string) $response->headers->get(AddContentSecurityPolicy::HEADER);

    expect($policy)->toContain("object-src 'none'")->toContain("frame-ancestors 'self'");
    expect(preg_match("/script-src 'self' 'nonce-([A-Za-z0-9]+)'/", $policy, $matches))->toBe(1);

    $response->assertSee('<script nonce="'.$matches[1].'">', false);
});

test('the content security policy can be switched off', function () {
    config(['security.csp.enabled' => false]);

    $this->get(route('login'))->assertOk()->assertHeaderMissing(AddContentSecurityPolicy::HEADER);
});

test('an unexpected appearance cookie is never written into the page', function () {
    $this->withUnencryptedCookie('appearance', "dark';alert(1);//")
        ->get(route('login'))
        ->assertOk()
        ->assertDontSee('alert(1)', false);
});
