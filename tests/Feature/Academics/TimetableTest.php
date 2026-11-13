<?php

use App\Models\AcademicYear;
use App\Models\Period;
use App\Models\Permission;
use App\Models\Section;
use App\Models\Subject;
use App\Models\TimetableEntry;
use App\Models\User;
use App\Services\Rbac\PermissionResolver;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @param  array{year: AcademicYear, section: Section, subject: Subject, period: Period, teacher: User}  $setup
 * @return array<string, mixed>
 */
function lessonPayload(array $setup, array $overrides = []): array
{
    return [
        'academic_year_id' => $setup['year']->id,
        'section_id' => $setup['section']->id,
        'day_of_week' => 1,
        'period_id' => $setup['period']->id,
        'subject_id' => $setup['subject']->id,
        'teacher_id' => $setup['teacher']->id,
        'room' => null,
        ...$overrides,
    ];
}

function secondSection(array $setup): Section
{
    return Section::factory()->create(['school_class_id' => $setup['section']->school_class_id, 'name' => 'B']);
}

test('a lesson is placed in a free slot', function () {
    $setup = academicSchool();

    $response = $this->actingAs($setup['admin'])->post(route('academics.timetable.entries.store'), lessonPayload($setup));

    $response->assertSessionHasNoErrors()->assertRedirect(route('academics.timetable.index', [
        'academic_year_id' => $setup['year']->id,
        'section_id' => $setup['section']->id,
    ]));
    $this->assertDatabaseHas('timetable_entries', [
        'school_id' => $setup['school']->id,
        'section_id' => $setup['section']->id,
        'teacher_id' => $setup['teacher']->id,
        'day_of_week' => 1,
    ]);
});

test('a section cannot have two lessons in one slot', function () {
    $setup = academicSchool();
    $this->actingAs($setup['admin'])->post(route('academics.timetable.entries.store'), lessonPayload($setup, ['teacher_id' => null]));

    $this->actingAs($setup['admin'])
        ->post(route('academics.timetable.entries.store'), lessonPayload($setup, ['teacher_id' => null]))
        ->assertSessionHasErrors('period_id');

    expect(TimetableEntry::query()->withoutGlobalScopes()->count())->toBe(1);
});

test('a teacher cannot teach two sections at the same time', function () {
    $setup = academicSchool();
    $other = secondSection($setup);
    $this->actingAs($setup['admin'])->post(route('academics.timetable.entries.store'), lessonPayload($setup));

    $this->actingAs($setup['admin'])
        ->post(route('academics.timetable.entries.store'), lessonPayload($setup, ['section_id' => $other->id]))
        ->assertSessionHasErrors('teacher_id');

    $this->assertDatabaseMissing('timetable_entries', ['section_id' => $other->id]);
});

test('a room cannot host two lessons at the same time', function () {
    $setup = academicSchool();
    $other = secondSection($setup);
    $this->actingAs($setup['admin'])->post(route('academics.timetable.entries.store'), lessonPayload($setup, ['teacher_id' => null, 'room' => 'Lab 1']));

    $this->actingAs($setup['admin'])
        ->post(route('academics.timetable.entries.store'), lessonPayload($setup, ['section_id' => $other->id, 'teacher_id' => null, 'room' => 'Lab 1']))
        ->assertSessionHasErrors('room');

    $this->assertDatabaseMissing('timetable_entries', ['section_id' => $other->id]);
});

test('editing a lesson in place does not clash with itself', function () {
    $setup = academicSchool();
    $entry = TimetableEntry::factory()->create([
        'section_id' => $setup['section']->id, 'academic_year_id' => $setup['year']->id,
        'period_id' => $setup['period']->id, 'subject_id' => $setup['subject']->id, 'teacher_id' => $setup['teacher']->id,
    ]);

    $this->actingAs($setup['admin'])
        ->put(route('academics.timetable.entries.update', $entry), lessonPayload($setup, ['room' => 'Lab 2']))
        ->assertSessionHasNoErrors();

    expect($entry->fresh()->room)->toBe('Lab 2');
});

test('invalid lessons are rejected', function (Closure $overrides, string $errorKey) {
    $setup = academicSchool();

    $this->actingAs($setup['admin'])
        ->post(route('academics.timetable.entries.store'), lessonPayload($setup, $overrides($setup)))
        ->assertSessionHasErrors($errorKey);

    expect(TimetableEntry::query()->withoutGlobalScopes()->count())->toBe(0);
})->with([
    'subject not taught in the class' => [fn (array $setup) => ['subject_id' => Subject::factory()->create(['school_id' => $setup['school']->id])->id], 'subject_id'],
    'lesson during a break' => [fn (array $setup) => ['period_id' => Period::factory()->break()->create(['school_id' => $setup['school']->id])->id], 'period_id'],
    'not a school day' => [fn (array $setup) => ['day_of_week' => 6], 'day_of_week'],
    'teacher who is not a teacher' => [fn (array $setup) => ['teacher_id' => User::factory()->forSchool($setup['school'])->create()->id], 'teacher_id'],
    'section of another school' => [fn (array $setup) => ['section_id' => Section::factory()->create()->id], 'section_id'],
]);

test('users who can only view the timetable cannot change it', function () {
    $setup = academicSchool();
    $viewer = userWithPermissions(['timetables.view'], $setup['school']);

    $this->actingAs($viewer)->post(route('academics.timetable.entries.store'), lessonPayload($setup))->assertForbidden();

    expect(TimetableEntry::query()->withoutGlobalScopes()->count())->toBe(0);
});

test('teachers see their own timetable by default', function () {
    $setup = academicSchool();
    $setup['teacher']->roles()->first()->permissions()->sync(
        Permission::query()->firstOrCreate(['name' => 'timetables.view'], ['module' => 'timetables', 'action' => 'view'])->id,
    );
    app(PermissionResolver::class)->flush();
    TimetableEntry::factory()->create([
        'section_id' => $setup['section']->id, 'academic_year_id' => $setup['year']->id,
        'period_id' => $setup['period']->id, 'subject_id' => $setup['subject']->id, 'teacher_id' => $setup['teacher']->id,
    ]);

    $this->actingAs($setup['teacher'])->get(route('academics.timetable.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('academics/timetable/index')
        ->where('selected.view', 'teacher')
        ->where('selected.teacher_id', $setup['teacher']->id)
        ->has('entries', 1)
        ->where('canEdit', false)
    );
});

test('the bell schedule is saved in order and periods may not overlap', function () {
    $setup = academicSchool();

    $this->actingAs($setup['admin'])->put(route('academics.periods.update'), ['periods' => [
        ['id' => $setup['period']->id, 'name' => 'First', 'starts_at' => '08:00', 'ends_at' => '08:45', 'is_break' => false],
        ['id' => null, 'name' => 'Break', 'starts_at' => '08:45', 'ends_at' => '09:00', 'is_break' => true],
        ['id' => $setup['nextPeriod']->id, 'name' => 'Second', 'starts_at' => '09:00', 'ends_at' => '09:45', 'is_break' => false],
    ]])->assertSessionHasNoErrors();

    expect(Period::query()->withoutGlobalScopes()->where('school_id', $setup['school']->id)->orderBy('position')->pluck('name')->all())
        ->toBe(['First', 'Break', 'Second']);

    $this->actingAs($setup['admin'])->put(route('academics.periods.update'), ['periods' => [
        ['id' => null, 'name' => 'One', 'starts_at' => '08:00', 'ends_at' => '09:00', 'is_break' => false],
        ['id' => null, 'name' => 'Two', 'starts_at' => '08:30', 'ends_at' => '09:30', 'is_break' => false],
    ]])->assertSessionHasErrors('periods.0.ends_at');
});

test('periods used in a timetable cannot be removed from the bell schedule', function () {
    $setup = academicSchool();
    TimetableEntry::factory()->create([
        'section_id' => $setup['section']->id, 'academic_year_id' => $setup['year']->id,
        'period_id' => $setup['period']->id, 'subject_id' => $setup['subject']->id,
    ]);

    $this->actingAs($setup['admin'])->put(route('academics.periods.update'), ['periods' => [
        ['id' => $setup['nextPeriod']->id, 'name' => 'Only', 'starts_at' => '08:45', 'ends_at' => '09:30', 'is_break' => false],
    ]])->assertSessionHasErrors('periods');

    $this->assertModelExists($setup['period']);
});

test('teachers see today\'s lessons on the dashboard', function () {
    $setup = academicSchool();
    $setup['teacher']->roles()->first()->permissions()->sync(
        Permission::query()->firstOrCreate(['name' => 'timetables.view'], ['module' => 'timetables', 'action' => 'view'])->id,
    );
    app(PermissionResolver::class)->flush();
    TimetableEntry::factory()->create([
        'section_id' => $setup['section']->id, 'academic_year_id' => $setup['year']->id, 'day_of_week' => 3,
        'period_id' => $setup['period']->id, 'subject_id' => $setup['subject']->id, 'teacher_id' => $setup['teacher']->id,
    ]);
    $this->travelTo('2030-01-02 07:00:00');

    $this->actingAs($setup['teacher'])->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('todaysLessons', 1)
        ->where('todaysLessons.0.subject', $setup['subject']->name)
        ->where('todaysLessons.0.room', 'R-1')
    );
});
