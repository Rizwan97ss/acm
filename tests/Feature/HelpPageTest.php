<?php

use App\Models\School;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('every signed-in user can open the guide', function () {
    $user = User::factory()->forSchool(School::factory()->create())->create();

    $this->actingAs($user)->get(route('help'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('help')->where('setup', null));
});

test('guests are sent to sign in', function () {
    $this->get(route('help'))->assertRedirect(route('login'));
});

test('administrators get a live setup checklist', function () {
    $setup = academicSchool();
    $admin = userWithPermissions(['settings.edit'], $setup['school']);

    $this->actingAs($admin)->get(route('help'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('setup.academic_year', true)
            ->where('setup.classes', true)
            ->where('setup.subjects', true)
            ->where('setup.periods', true)
            ->where('setup.students', false)
            ->where('setup.invoices', false));
});
