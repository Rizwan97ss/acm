<?php

use App\Models\Student;
use App\Models\TransportAssignment;
use App\Models\TransportRoute;
use App\Models\TransportStop;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array<string, mixed>
 */
function transportSchool(): array
{
    $setup = teachingSchool();
    $manager = userWithPermissions(['transport.view', 'transport.create', 'transport.edit', 'transport.delete'], $setup['school']);
    $route = TransportRoute::factory()->create(['school_id' => $setup['school']->id, 'capacity' => 1]);
    $stop = TransportStop::query()->create(['transport_route_id' => $route->id, 'name' => 'Market Square', 'pickup_time' => '07:15']);

    return [...$setup, 'manager' => $manager, 'route' => $route, 'stop' => $stop];
}

test('a route is created with ordered stops', function () {
    $setup = transportSchool();

    $this->actingAs($setup['manager'])->post(route('operations.transport.store'), [
        'name' => 'North loop', 'capacity' => 40, 'is_active' => true,
        'stops' => [['name' => 'Gate A', 'pickup_time' => '07:00'], ['name' => 'Library corner', 'pickup_time' => '07:10']],
    ])->assertSessionHasNoErrors();

    $route = TransportRoute::query()->where('name', 'North loop')->sole();
    expect($route->stops->pluck('name')->all())->toBe(['Gate A', 'Library corner']);
});

test('students are seated up to capacity and only at stops on the route', function () {
    $setup = transportSchool();
    $other = Student::factory()->create(['school_id' => $setup['school']->id]);
    $foreignStop = TransportStop::query()->create(['transport_route_id' => TransportRoute::factory()->create(['school_id' => $setup['school']->id])->id, 'name' => 'Elsewhere']);

    $this->actingAs($setup['manager'])->post(route('operations.transport.assign', $setup['route']), ['admission_no' => $setup['student']->admission_no, 'transport_stop_id' => $foreignStop->id])
        ->assertSessionHasErrors('transport_stop_id');
    $this->actingAs($setup['manager'])->post(route('operations.transport.assign', $setup['route']), ['admission_no' => $setup['student']->admission_no, 'transport_stop_id' => $setup['stop']->id])
        ->assertSessionHasNoErrors();
    $this->actingAs($setup['manager'])->post(route('operations.transport.assign', $setup['route']), ['admission_no' => $other->admission_no])
        ->assertSessionHasErrors('student');

    expect(TransportAssignment::query()->sole()->student_id)->toBe($setup['student']->id);
});

test('capacity cannot drop below the riders and routes with riders cannot be deleted', function () {
    $setup = transportSchool();
    TransportAssignment::query()->create(['school_id' => $setup['school']->id, 'student_id' => $setup['student']->id, 'transport_route_id' => $setup['route']->id]);
    $setup['route']->update(['capacity' => 2]);
    TransportAssignment::query()->create(['school_id' => $setup['school']->id, 'student_id' => Student::factory()->create(['school_id' => $setup['school']->id])->id, 'transport_route_id' => $setup['route']->id]);

    $this->actingAs($setup['manager'])->put(route('operations.transport.update', $setup['route']), ['name' => $setup['route']->name, 'capacity' => 1])
        ->assertSessionHasErrors('capacity');
    $this->actingAs($setup['manager'])->delete(route('operations.transport.destroy', $setup['route']))->assertSessionHasErrors('route');
});

test('guardians see only their child\'s seat', function () {
    $setup = transportSchool();
    $parent = userWithPermissions(['transport.view'], $setup['school']);
    $setup['guardian']->update(['user_id' => $parent->id]);
    TransportAssignment::query()->create(['school_id' => $setup['school']->id, 'student_id' => $setup['student']->id, 'transport_route_id' => $setup['route']->id, 'transport_stop_id' => $setup['stop']->id]);

    $this->actingAs($parent)->get(route('operations.transport.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('routes', [])
            ->has('mine', 1)
            ->where('mine.0.stop.name', 'Market Square')
            ->where('mine.0.stop.pickup_time', '07:15'));

    $this->actingAs($parent)->get(route('operations.transport.show', $setup['route']))->assertForbidden();
});
