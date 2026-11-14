<?php

use App\Models\AttendanceRecord;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\Student;
use App\Notifications\StudentAbsentNotification;
use App\Services\Academics\AttendanceService;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

function attendancePayload(array $setup, string $status = 'absent', ?string $date = null): array
{
    return [
        'section_id' => $setup['section']->id,
        'date' => $date ?? now()->toDateString(),
        'entries' => [['student_id' => $setup['student']->id, 'status' => $status, 'remarks' => null]],
    ];
}

test('a class teacher records attendance and guardians are told about absences once', function () {
    Notification::fake();
    $setup = teachingSchool();

    $this->actingAs($setup['classTeacher'])->post(route('academics.attendance.store'), attendancePayload($setup))->assertSessionHasNoErrors();
    $this->actingAs($setup['classTeacher'])->post(route('academics.attendance.store'), attendancePayload($setup))->assertSessionHasNoErrors();

    $record = AttendanceRecord::query()->withoutGlobalScopes()->sole();
    expect($record->status->value)->toBe('absent')
        ->and($record->marked_by)->toBe($setup['classTeacher']->id);
    Notification::assertSentToTimes($setup['guardian'], StudentAbsentNotification::class, 1);
});

test('the roll call shows the section roster with recorded statuses', function () {
    $setup = teachingSchool();
    $this->actingAs($setup['classTeacher'])->post(route('academics.attendance.store'), attendancePayload($setup, 'late'));

    $this->actingAs($setup['classTeacher'])->get(route('academics.attendance.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('academics/attendance/index')
        ->has('sections', 1)
        ->where('rows.0.status', 'late')
        ->where('canRecord', true)
    );
});

test('teachers cannot take attendance for sections they do not teach', function () {
    $setup = teachingSchool();
    $other = Section::factory()->create(['school_class_id' => $setup['section']->school_class_id, 'name' => 'B']);
    $otherStudent = Student::factory()->create(['school_id' => $setup['school']->id]);
    Enrollment::factory()->create(['student_id' => $otherStudent->id, 'section_id' => $other->id, 'academic_year_id' => $setup['year']->id]);

    $this->actingAs($setup['classTeacher'])->post(route('academics.attendance.store'), [
        'section_id' => $other->id,
        'date' => now()->toDateString(),
        'entries' => [['student_id' => $otherStudent->id, 'status' => 'present']],
    ])->assertForbidden();

    expect(AttendanceRecord::query()->withoutGlobalScopes()->count())->toBe(0);
});

test('only supervisors can correct attendance older than a week, and nobody can record the future', function () {
    $setup = teachingSchool();
    $supervisor = userWithPermissions(['attendance.view', 'attendance.create', 'attendance.approve'], $setup['school']);
    $old = now()->subDays(10)->toDateString();

    $this->actingAs($setup['classTeacher'])->post(route('academics.attendance.store'), attendancePayload($setup, 'present', $old))->assertSessionHasErrors('date');
    $this->actingAs($setup['classTeacher'])->post(route('academics.attendance.store'), attendancePayload($setup, 'present', now()->addDay()->toDateString()))->assertSessionHasErrors('date');
    $this->actingAs($supervisor)->post(route('academics.attendance.store'), attendancePayload($setup, 'present', $old))->assertSessionHasNoErrors();

    expect(AttendanceRecord::query()->withoutGlobalScopes()->count())->toBe(1);
});

test('students who are not enrolled in the section are rejected', function () {
    $setup = teachingSchool();
    $stranger = Student::factory()->create(['school_id' => $setup['school']->id]);

    $this->actingAs($setup['classTeacher'])->post(route('academics.attendance.store'), [
        'section_id' => $setup['section']->id,
        'date' => now()->toDateString(),
        'entries' => [['student_id' => $stranger->id, 'status' => 'present']],
    ])->assertSessionHasErrors('entries');
});

test('attendance percentage counts late as present and ignores excused days', function () {
    $setup = teachingSchool();
    foreach (['present', 'present', 'late', 'absent', 'excused'] as $offset => $status) {
        AttendanceRecord::query()->create([
            'school_id' => $setup['school']->id, 'academic_year_id' => $setup['year']->id, 'section_id' => $setup['section']->id,
            'student_id' => $setup['student']->id, 'date' => now()->subDays($offset)->toDateString(), 'status' => $status,
        ]);
    }

    $summary = app(AttendanceService::class)->summary($setup['student'], $setup['year']);

    expect($summary)->toMatchArray(['present' => 2, 'late' => 1, 'absent' => 1, 'excused' => 1, 'total' => 5, 'percentage' => 75.0]);
});

test('the monthly register shows each day\'s status', function () {
    $setup = teachingSchool();
    $this->actingAs($setup['classTeacher'])->post(route('academics.attendance.store'), attendancePayload($setup, 'present'));

    $this->actingAs($setup['classTeacher'])->get(route('academics.attendance.register', ['month' => now()->format('Y-m')]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('students.0.days.'.now()->toDateString(), 'present')->where('students.0.percentage', 100));
});
