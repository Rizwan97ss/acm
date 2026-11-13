<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('dashboard widgets are limited to what the user may see', function () {
    $user = userWithPermissions(['students.view']);

    $this->actingAs($user)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->missing('stats')
        ->missing('recentActivity')
    );
});

test('administrators see overview stats and deferred activity widgets', function () {
    $user = userWithPermissions(['users.view', 'audit_logs.view']);

    $this->actingAs($user)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('stats.0.key', 'users')
        ->where('stats.0.value', 1)
        ->missing('recentActivity')
        ->loadDeferredProps('widgets', fn (Assert $reload) => $reload
            ->has('loginActivity', 14)
            ->has('recentActivity')
        )
    );
});
