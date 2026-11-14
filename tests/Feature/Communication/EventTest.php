<?php

use App\Enums\Audience;
use App\Models\SchoolEvent;
use Inertia\Testing\AssertableInertia as Assert;

test('managers add events and all-day events cover whole days', function () {
    $setup = communitySchool();

    $this->actingAs($setup['manager'])->post(route('communication.events.store'), [
        'title' => 'Mid-term break', 'all_day' => true, 'starts_at' => '2026-12-21 10:00', 'ends_at' => '2026-12-23 09:00', 'audience' => 'everyone',
    ])->assertRedirect(route('communication.events.index', ['month' => '2026-12']));

    $event = SchoolEvent::query()->sole();
    expect($event->starts_at->format('Y-m-d H:i:s'))->toBe('2026-12-21 00:00:00')
        ->and($event->ends_at->format('Y-m-d H:i:s'))->toBe('2026-12-23 23:59:59');
});

test('the end must not be before the start', function () {
    $setup = communitySchool();

    $this->actingAs($setup['manager'])->post(route('communication.events.store'), [
        'title' => 'Backwards', 'starts_at' => '2026-12-21 10:00', 'ends_at' => '2026-12-21 09:00', 'audience' => 'everyone',
    ])->assertSessionHasErrors('ends_at');
});

test('the month view shows overlapping events addressed to the viewer', function () {
    $setup = communitySchool();
    $school = $setup['school'];

    SchoolEvent::factory()->create(['school_id' => $school->id, 'title' => 'Spans into December', 'starts_at' => '2026-11-30 09:00', 'ends_at' => '2026-12-02 15:00']);
    SchoolEvent::factory()->create(['school_id' => $school->id, 'title' => 'Staff meeting', 'starts_at' => '2026-12-10 15:00', 'ends_at' => '2026-12-10 16:00', 'audience' => Audience::Staff]);
    SchoolEvent::factory()->create(['school_id' => $school->id, 'title' => 'January', 'starts_at' => '2027-01-05 09:00', 'ends_at' => '2027-01-05 10:00']);

    $this->actingAs($setup['guardianUser'])->get(route('communication.events.index', ['month' => '2026-12']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('month', '2026-12')
            ->has('events', 1)
            ->where('events.0.title', 'Spans into December')
            ->where('can.create', false));
});

test('readers cannot change events', function () {
    $setup = communitySchool();
    $event = SchoolEvent::factory()->create(['school_id' => $setup['school']->id]);

    $this->actingAs($setup['guardianUser'])->put(route('communication.events.update', $event), ['title' => 'x', 'starts_at' => '2026-12-01', 'ends_at' => '2026-12-01', 'audience' => 'everyone'])->assertForbidden();
    $this->actingAs($setup['guardianUser'])->delete(route('communication.events.destroy', $event))->assertForbidden();
});
