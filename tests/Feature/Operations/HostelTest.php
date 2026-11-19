<?php

use App\Enums\Gender;
use App\Enums\HostelType;
use App\Models\Hostel;
use App\Models\HostelAllocation;
use App\Models\HostelRoom;
use App\Models\Invoice;
use App\Models\Student;

/**
 * @return array<string, mixed>
 */
function hostelSchool(): array
{
    $setup = academicSchool();
    $warden = userWithPermissions(['hostels.view', 'hostels.create', 'hostels.edit', 'hostels.delete', 'invoices.create'], $setup['school']);
    $hostel = Hostel::query()->create(['school_id' => $setup['school']->id, 'name' => 'Iqbal House', 'type' => HostelType::Boys, 'monthly_fee' => 12000, 'is_active' => true]);
    $room = HostelRoom::query()->create(['school_id' => $setup['school']->id, 'hostel_id' => $hostel->id, 'name' => '101', 'capacity' => 1]);
    $boy = Student::factory()->create(['school_id' => $setup['school']->id, 'gender' => Gender::Male, 'admission_no' => 'B1']);
    $girl = Student::factory()->create(['school_id' => $setup['school']->id, 'gender' => Gender::Female, 'admission_no' => 'G1']);

    return [...$setup, 'warden' => $warden, 'hostel' => $hostel, 'room' => $room, 'boy' => $boy, 'girl' => $girl];
}

test('students are allocated within room capacity and matching hostel type', function () {
    $setup = hostelSchool();
    $allocate = fn (string $admissionNo) => $this->actingAs($setup['warden'])->post(route('operations.hostel-rooms.allocate', $setup['room']), ['admission_no' => $admissionNo, 'bed_label' => 'A']);

    $allocate('G1')->assertSessionHasErrors('student_id');
    $allocate('B1')->assertSessionHasNoErrors();

    $another = Student::factory()->create(['school_id' => $setup['school']->id, 'gender' => Gender::Male, 'admission_no' => 'B2']);
    $allocate('B2')->assertSessionHasErrors('hostel_room_id');

    expect(HostelAllocation::query()->whereNull('ends_on')->count())->toBe(1);
});

test('a student has one bed at a time and moving closes the old stay', function () {
    $setup = hostelSchool();
    $second = HostelRoom::query()->create(['school_id' => $setup['school']->id, 'hostel_id' => $setup['hostel']->id, 'name' => '102', 'capacity' => 2]);

    $this->actingAs($setup['warden'])->post(route('operations.hostel-rooms.allocate', $setup['room']), ['admission_no' => 'B1']);
    $this->actingAs($setup['warden'])->post(route('operations.hostel-rooms.allocate', $second), ['admission_no' => 'B1'])->assertSessionHasErrors('student_id');

    $stay = HostelAllocation::query()->sole();
    $this->actingAs($setup['warden'])->put(route('operations.hostel-residents.move', $stay), ['hostel_room_id' => $second->id])->assertSessionHasNoErrors();

    expect($stay->refresh()->ends_on)->not->toBeNull()
        ->and(HostelAllocation::query()->whereNull('ends_on')->sole()->hostel_room_id)->toBe($second->id);

    $this->actingAs($setup['warden'])->delete(route('operations.hostel-residents.vacate', HostelAllocation::query()->whereNull('ends_on')->sole()))->assertRedirect();
    expect(HostelAllocation::query()->whereNull('ends_on')->count())->toBe(0);
});

test('hostel fees are billed once per month to current residents', function () {
    $setup = hostelSchool();
    $this->actingAs($setup['warden'])->post(route('operations.hostel-rooms.allocate', $setup['room']), ['admission_no' => 'B1']);

    $this->actingAs($setup['warden'])->post(route('operations.hostels.bill', $setup['hostel']), ['month' => '2026-11'])->assertSessionHasNoErrors();
    $this->actingAs($setup['warden'])->post(route('operations.hostels.bill', $setup['hostel']), ['month' => '2026-11'])->assertSessionHasNoErrors();

    $invoice = Invoice::query()->sole();
    expect($invoice->student_id)->toBe($setup['boy']->id)
        ->and($invoice->total)->toBe(12000)
        ->and($invoice->title)->toContain('November 2026');
});

test('hostels with residents cannot be deleted and viewers cannot change anything', function () {
    $setup = hostelSchool();
    $this->actingAs($setup['warden'])->post(route('operations.hostel-rooms.allocate', $setup['room']), ['admission_no' => 'B1']);

    $this->actingAs($setup['warden'])->delete(route('operations.hostels.destroy', $setup['hostel']))->assertSessionHasErrors('hostel');

    $viewer = userWithPermissions(['hostels.view'], $setup['school']);
    $this->actingAs($viewer)->get(route('operations.hostels.show', $setup['hostel']))->assertOk();
    $this->actingAs($viewer)->post(route('operations.hostel-rooms.allocate', $setup['room']), ['admission_no' => 'B1'])->assertForbidden();
    $this->actingAs(userWithPermissions(['students.view'], $setup['school']))->get(route('operations.hostels.index'))->assertForbidden();
});
