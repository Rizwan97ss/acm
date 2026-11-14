<?php

use App\Enums\AdmissionStatus;
use App\Jobs\DeliverWebhook;
use App\Models\AdmissionApplication;
use App\Models\Guardian;
use App\Models\Section;
use App\Models\Student;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\People\AdmissionService;
use Illuminate\Support\Facades\Queue;

/**
 * @return array<string, mixed>
 */
function applicationPayload(array $setup, array $overrides = []): array
{
    return [
        'academic_year_id' => $setup['year']->id,
        'school_class_id' => $setup['section']->school_class_id,
        'first_name' => 'Zara',
        'last_name' => 'Ahmed',
        'gender' => 'female',
        'date_of_birth' => '2016-02-01',
        'guardian_name' => 'Nadia Ahmed',
        'guardian_relationship' => 'mother',
        'guardian_phone' => '+1 555 444 5555',
        'guardian_email' => 'nadia@example.com',
        ...$overrides,
    ];
}

test('reception records applications but cannot decide them', function () {
    $setup = academicSchool();
    $receptionist = userWithPermissions(['admissions.view', 'admissions.create', 'admissions.edit'], $setup['school']);

    $this->actingAs($receptionist)->post(route('people.admissions.store'), applicationPayload($setup))->assertSessionHasNoErrors();

    $application = AdmissionApplication::query()->withoutGlobalScopes()->sole();
    expect($application->application_no)->toStartWith('APP-')
        ->and($application->status)->toBe(AdmissionStatus::Pending);
    $this->actingAs($receptionist)->post(route('people.admissions.decision', $application), ['decision' => 'approved'])->assertForbidden();
    expect($application->fresh()->status)->toBe(AdmissionStatus::Pending);
});

test('an approved application is enrolled as a student with guardian and enrollment', function () {
    $setup = academicSchool();
    $principal = userWithPermissions(['admissions.view', 'admissions.approve', 'students.create'], $setup['school']);
    $application = AdmissionApplication::factory()->create([
        'school_id' => $setup['school']->id, 'academic_year_id' => $setup['year']->id, 'school_class_id' => $setup['section']->school_class_id,
        'guardian_phone' => '+1 555 444 5555', 'guardian_name' => 'Nadia Ahmed',
    ]);

    $this->actingAs($principal)->post(route('people.admissions.decision', $application), ['decision' => 'approved', 'notes' => 'Interview passed'])
        ->assertSessionHasNoErrors();
    $response = $this->actingAs($principal)->post(route('people.admissions.enroll', $application), ['section_id' => $setup['section']->id]);

    $student = Student::query()->withoutGlobalScopes()->sole();
    $response->assertRedirect(route('people.students.show', $student));
    expect($application->fresh()->status)->toBe(AdmissionStatus::Enrolled)
        ->and($application->fresh()->student_id)->toBe($student->id)
        ->and($application->fresh()->reviewed_by)->toBe($principal->id);
    $this->assertDatabaseHas('enrollments', ['student_id' => $student->id, 'section_id' => $setup['section']->id, 'academic_year_id' => $setup['year']->id]);
    $guardian = $student->guardians()->sole();
    expect($guardian->first_name)->toBe('Nadia')->and((bool) $guardian->pivot->is_primary)->toBeTrue();
});

test('enrolling an application announces the new student to webhooks', function () {
    $setup = academicSchool();
    Queue::fake();
    WebhookEndpoint::query()->create(['school_id' => $setup['school']->id, 'url' => 'https://93.184.216.34/hook', 'secret' => 'whsec_test', 'events' => ['student.created']]);
    $application = AdmissionApplication::factory()->create([
        'school_id' => $setup['school']->id, 'academic_year_id' => $setup['year']->id, 'school_class_id' => $setup['section']->school_class_id,
        'status' => AdmissionStatus::Approved,
    ]);

    $student = app(AdmissionService::class)->enroll($application, $setup['section'], null);

    $delivery = WebhookDelivery::query()->withoutGlobalScopes()->sole();
    expect($delivery->event)->toBe('student.created')
        ->and($delivery->payload['data']['student_id'])->toBe($student->id);
    Queue::assertPushed(DeliverWebhook::class, 1);
});

test('siblings share the existing guardian matched by phone', function () {
    $setup = academicSchool();
    $principal = userWithPermissions(['admissions.view', 'admissions.approve', 'students.create'], $setup['school']);
    $existing = Guardian::factory()->create(['school_id' => $setup['school']->id, 'phone' => '+1 (555) 444-5555']);
    $application = AdmissionApplication::factory()->approved()->create([
        'school_id' => $setup['school']->id, 'academic_year_id' => $setup['year']->id, 'school_class_id' => $setup['section']->school_class_id,
        'guardian_phone' => '+1 555 444 5555', 'guardian_email' => null,
    ]);

    $this->actingAs($principal)->post(route('people.admissions.enroll', $application), ['section_id' => $setup['section']->id])->assertSessionHasNoErrors();

    expect(Guardian::query()->withoutGlobalScopes()->count())->toBe(1)
        ->and(Student::query()->withoutGlobalScopes()->sole()->guardians()->sole()->id)->toBe($existing->id);
});

test('applications can only be enrolled once approved, into a section of the applied class', function () {
    $setup = academicSchool();
    $principal = userWithPermissions(['admissions.view', 'admissions.approve', 'students.create'], $setup['school']);
    $attributes = ['school_id' => $setup['school']->id, 'academic_year_id' => $setup['year']->id, 'school_class_id' => $setup['section']->school_class_id];
    $pending = AdmissionApplication::factory()->create($attributes);
    $approved = AdmissionApplication::factory()->approved()->create($attributes);
    $otherClassSection = Section::factory()->create(['school_id' => $setup['school']->id]);

    $this->actingAs($principal)->post(route('people.admissions.enroll', $pending), ['section_id' => $setup['section']->id])->assertForbidden();
    $this->actingAs($principal)->post(route('people.admissions.enroll', $approved), ['section_id' => $otherClassSection->id])->assertSessionHasErrors('section_id');

    expect(Student::query()->withoutGlobalScopes()->count())->toBe(0);
});

test('decided applications cannot be decided again', function () {
    $setup = academicSchool();
    $principal = userWithPermissions(['admissions.view', 'admissions.approve'], $setup['school']);
    $application = AdmissionApplication::factory()->create([
        'school_id' => $setup['school']->id, 'academic_year_id' => $setup['year']->id, 'school_class_id' => $setup['section']->school_class_id,
        'status' => AdmissionStatus::Rejected,
    ]);

    $this->actingAs($principal)->post(route('people.admissions.decision', $application), ['decision' => 'approved'])->assertForbidden();

    expect($application->fresh()->status)->toBe(AdmissionStatus::Rejected);
});
