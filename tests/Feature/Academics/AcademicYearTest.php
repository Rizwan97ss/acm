<?php

use App\Models\AcademicYear;
use App\Models\School;
use App\Models\TimetableEntry;

const YEAR_PERMISSIONS = ['academic_years.view', 'academic_years.create', 'academic_years.edit', 'academic_years.delete'];

/**
 * @return array<string, mixed>
 */
function yearPayload(array $overrides = []): array
{
    return [
        'name' => '2030-2031',
        'starts_on' => '2030-09-01',
        'ends_on' => '2031-06-30',
        'terms' => [
            ['id' => null, 'name' => 'Autumn', 'starts_on' => '2030-09-01', 'ends_on' => '2030-12-20'],
            ['id' => null, 'name' => 'Spring', 'starts_on' => '2031-01-06', 'ends_on' => '2031-06-30'],
        ],
        ...$overrides,
    ];
}

test('the first academic year of a school is created with its terms and becomes current', function () {
    $school = School::factory()->create();
    $admin = userWithPermissions(YEAR_PERMISSIONS, $school);

    $response = $this->actingAs($admin)->post(route('academics.academic-years.store'), yearPayload());

    $response->assertSessionHasNoErrors()->assertRedirect(route('academics.academic-years.index'));
    $year = AcademicYear::query()->withoutGlobalScopes()->sole();
    expect($year->school_id)->toBe($school->id)
        ->and($year->is_current)->toBeTrue()
        ->and($year->terms()->pluck('name')->all())->toBe(['Autumn', 'Spring']);
});

test('academic years of a school cannot overlap', function () {
    $school = School::factory()->create();
    AcademicYear::factory()->create(['school_id' => $school->id, 'name' => 'Existing', 'starts_on' => '2030-01-01', 'ends_on' => '2030-12-31']);

    $response = $this->actingAs(userWithPermissions(YEAR_PERMISSIONS, $school))
        ->post(route('academics.academic-years.store'), yearPayload());

    $response->assertSessionHasErrors('starts_on');
    $this->assertDatabaseMissing('academic_years', ['name' => '2030-2031']);
});

test('another school may use the same dates and name', function () {
    AcademicYear::factory()->create(['name' => '2030-2031', 'starts_on' => '2030-09-01', 'ends_on' => '2031-06-30']);

    $this->actingAs(userWithPermissions(YEAR_PERMISSIONS, School::factory()->create()))
        ->post(route('academics.academic-years.store'), yearPayload())
        ->assertSessionHasNoErrors();
});

test('terms must fall inside the year and must not overlap', function (array $terms, string $errorKey) {
    $response = $this->actingAs(userWithPermissions(YEAR_PERMISSIONS, School::factory()->create()))
        ->post(route('academics.academic-years.store'), yearPayload(['terms' => $terms]));

    $response->assertSessionHasErrors($errorKey);
    $this->assertDatabaseMissing('academic_years', ['name' => '2030-2031']);
})->with([
    'starts before the year' => [[['id' => null, 'name' => 'Early', 'starts_on' => '2030-08-01', 'ends_on' => '2030-10-01']], 'terms.0.starts_on'],
    'ends after the year' => [[['id' => null, 'name' => 'Late', 'starts_on' => '2031-05-01', 'ends_on' => '2031-08-01']], 'terms.0.ends_on'],
    'overlapping terms' => [[
        ['id' => null, 'name' => 'One', 'starts_on' => '2030-09-01', 'ends_on' => '2031-01-31'],
        ['id' => null, 'name' => 'Two', 'starts_on' => '2031-01-15', 'ends_on' => '2031-06-30'],
    ], 'terms'],
]);

test('updating a year replaces its terms', function () {
    $school = School::factory()->create();
    $year = AcademicYear::factory()->create(['school_id' => $school->id, 'starts_on' => '2030-09-01', 'ends_on' => '2031-06-30']);
    $kept = $year->terms()->create(['school_id' => $school->id, 'name' => 'Kept', 'starts_on' => '2030-09-01', 'ends_on' => '2030-12-20']);
    $removed = $year->terms()->create(['school_id' => $school->id, 'name' => 'Removed', 'starts_on' => '2031-01-06', 'ends_on' => '2031-06-30']);

    $this->actingAs(userWithPermissions(YEAR_PERMISSIONS, $school))
        ->put(route('academics.academic-years.update', $year), yearPayload(['terms' => [
            ['id' => $kept->id, 'name' => 'Autumn', 'starts_on' => '2030-09-01', 'ends_on' => '2030-12-20'],
        ]]))
        ->assertSessionHasNoErrors();

    expect($kept->fresh()->name)->toBe('Autumn');
    $this->assertModelMissing($removed);
});

test('making a year current clears the previous current year', function () {
    $school = School::factory()->create();
    $old = AcademicYear::factory()->current()->create(['school_id' => $school->id]);
    $new = AcademicYear::factory()->create(['school_id' => $school->id]);

    $this->actingAs(userWithPermissions(YEAR_PERMISSIONS, $school))
        ->post(route('academics.academic-years.current', $new))
        ->assertRedirect(route('academics.academic-years.index'));

    expect($new->fresh()->is_current)->toBeTrue()
        ->and($old->fresh()->is_current)->toBeFalse();
});

test('the current year and years with a timetable cannot be deleted', function () {
    $setup = academicSchool();
    $scheduled = AcademicYear::factory()->create(['school_id' => $setup['school']->id]);
    TimetableEntry::factory()->create([
        'section_id' => $setup['section']->id, 'academic_year_id' => $scheduled->id,
        'period_id' => $setup['period']->id, 'subject_id' => $setup['subject']->id,
    ]);

    $this->actingAs($setup['admin'])->delete(route('academics.academic-years.destroy', $setup['year']))->assertSessionHasErrors('academic_year');
    $this->actingAs($setup['admin'])->delete(route('academics.academic-years.destroy', $scheduled))->assertSessionHasErrors('academic_year');

    $this->assertModelExists($setup['year']);
    $this->assertModelExists($scheduled);
});

test('years of another school are not reachable', function () {
    $admin = userWithPermissions(YEAR_PERMISSIONS, School::factory()->create());
    $foreign = AcademicYear::factory()->create();

    $this->actingAs($admin)->get(route('academics.academic-years.edit', $foreign))->assertNotFound();
    $this->actingAs($admin)->post(route('academics.academic-years.current', $foreign))->assertNotFound();
});

test('academic years require the academic_years.view permission', function () {
    $this->actingAs(userWithPermissions(['users.view'], School::factory()->create()))
        ->get(route('academics.academic-years.index'))
        ->assertForbidden();
});

test('platform users must choose a school before opening academic modules', function () {
    $this->actingAs(superAdmin())
        ->get(route('academics.academic-years.index'))
        ->assertRedirect(route('admin.schools.index'));
});
