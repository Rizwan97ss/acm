<?php

use App\Enums\SettingScope;
use App\Http\Middleware\LogActivity;
use App\Models\User;
use App\Services\Settings\SettingsService;
use Inertia\Testing\AssertableInertia as Assert;

test('signed-in pages receive the ten minute idle timeout by default', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.idleTimeout', 10));
});

test('the timeout follows the security setting', function () {
    app(SettingsService::class)->put(SettingScope::Global, 0, ['security.session_idle_timeout' => 25]);

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.idleTimeout', 25));
});

test('the keep-alive keeps an active session signed in past the timeout', function () {
    $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertOk();

    foreach (range(1, 3) as $step) {
        $this->travel(6)->minutes();
        $this->get(route('session.heartbeat'))->assertNoContent();
    }

    $this->get(route('dashboard'))->assertOk();
    $this->assertAuthenticated();
});

test('after ten idle minutes the next request signs the user out with an explanation', function () {
    $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertOk();
    $this->travel(11)->minutes();

    $this->get(route('session.heartbeat'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', __('You were signed out after a period of inactivity.'));

    $this->assertGuest();
});

test('guests cannot use the keep-alive', function () {
    $this->get(route('session.heartbeat'))->assertRedirect(route('login'));
});

test('keep-alive requests are not written to the activity log', function () {
    $path = captureLogChannel(LogActivity::CHANNEL);

    $this->actingAs(User::factory()->create())->get(route('session.heartbeat'))->assertNoContent();

    expect(file_exists($path) ? file_get_contents($path) : '')->not->toContain('session.heartbeat');
});
