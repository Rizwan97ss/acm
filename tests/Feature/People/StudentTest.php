<?php

use App\Models\Enrollment;
use App\Models\School;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Services\Rbac\PermissionRegistry;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

function studentAdmin(School $school): User
{
    return userWithPermissions(app(PermissionRegistry::class)->expand(['students.*', 'guardians.*']), $school);
}

/**
 * @return array<string, mixed>
 */
function studentPayload(array $overrides = []): array
{
    return [
        'first_name' => 'Amina',
        'last_name' => 'Khan',
        'gender' => 'female',
        'date_of_birth' => '2015-04-12',
        'admission_date' => '2026-09-01',
        'email' => 'amina@example.com',
        'national_id' => '35202-1234567-8',
        'medical_notes' => 'Peanut allergy',
        ...$overrides,
    ];
}

test('students get sequential admission numbers and can be enrolled on creation', function () {
    $this->travelTo('2026-09-01');
    $setup = academicSchool();
    $setup['school']->update(['code' => 'GHS']);
    $admin = studentAdmin($setup['school']);

    $this->actingAs($admin)->post(route('people.students.store'), studentPayload([
        'enrollment' => ['academic_year_id' => $setup['year']->id, 'section_id' => $setup['section']->id],
    ]))->assertSessionHasNoErrors();
    $this->actingAs($admin)->post(route('people.students.store'), studentPayload(['first_name' => 'Bilal', 'email' => null, 'national_id' => null]))
        ->assertSessionHasNoErrors();

    expect(Student::query()->withoutGlobalScopes()->orderBy('id')->pluck('admission_no')->all())->toBe(['GHS260001', 'GHS260002']);
    $first = Student::query()->withoutGlobalScopes()->where('first_name', 'Amina')->sole();
    $this->assertDatabaseHas('enrollments', ['student_id' => $first->id, 'section_id' => $setup['section']->id, 'roll_number' => 1]);
});

test('national IDs and medical notes are encrypted at rest and IDs are searchable by exact match', function () {
    $school = School::factory()->create();
    $admin = studentAdmin($school);
    $this->actingAs($admin)->post(route('people.students.store'), studentPayload());

    $raw = DB::table('students')->first();
    expect($raw->national_id)->not->toContain('1234567')
        ->and($raw->medical_notes)->not->toContain('Peanut');

    $this->actingAs($admin)->get(route('people.students.index', ['search' => '3520212345678']))
        ->assertInertia(fn (Assert $page) => $page->has('students.data', 1)->where('students.data.0.first_name', 'Amina'));
});

test('a national ID cannot be used twice in a school', function () {
    $school = School::factory()->create();
    Student::factory()->create(['school_id' => $school->id, 'national_id' => '35202-1234567-8']);

    $this->actingAs(studentAdmin($school))
        ->post(route('people.students.store'), studentPayload(['national_id' => '3520212345678']))
        ->assertSessionHasErrors('national_id');

    expect(Student::query()->withoutGlobalScopes()->count())->toBe(1);
});

test('confidential fields are hidden from staff who cannot edit students', function () {
    $school = School::factory()->create();
    $student = Student::factory()->create(['school_id' => $school->id, 'national_id' => 'AB123456', 'medical_notes' => 'Asthma', 'address' => '1 Main St']);
    $viewer = userWithPermissions(['students.view'], $school);

    $this->actingAs($viewer)->get(route('people.students.show', $student))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('student.national_id', '•••• 3456')
        ->where('student.medical_notes', null)
        ->where('student.has_medical_notes', true)
        ->where('student.address', null)
    );
});

test('students of another school are not reachable', function () {
    $admin = studentAdmin(School::factory()->create());
    $foreign = Student::factory()->create();

    $this->actingAs($admin)->get(route('people.students.show', $foreign))->assertNotFound();
});

test('archiving a student deactivates their portal account', function () {
    $school = School::factory()->create();
    $account = User::factory()->forSchool($school)->create();
    $student = Student::factory()->create(['school_id' => $school->id, 'user_id' => $account->id]);

    $this->actingAs(studentAdmin($school))->delete(route('people.students.destroy', $student))->assertRedirect(route('people.students.index'));

    $this->assertSoftDeleted($student);
    expect($account->fresh()->is_active)->toBeFalse();
});

test('enrolling again in the same year transfers the student', function () {
    $setup = academicSchool();
    $other = Section::factory()->create(['school_class_id' => $setup['section']->school_class_id, 'name' => 'B']);
    $student = Student::factory()->create(['school_id' => $setup['school']->id]);
    $admin = studentAdmin($setup['school']);
    $route = route('people.students.enrollments.store', $student);

    $this->actingAs($admin)->post($route, ['academic_year_id' => $setup['year']->id, 'section_id' => $setup['section']->id])->assertSessionHasNoErrors();
    $this->actingAs($admin)->post($route, ['academic_year_id' => $setup['year']->id, 'section_id' => $other->id])->assertSessionHasNoErrors();

    $enrollment = Enrollment::query()->withoutGlobalScopes()->where('student_id', $student->id)->sole();
    expect($enrollment->section_id)->toBe($other->id);
});

test('a full section rejects new enrollments', function () {
    $setup = academicSchool();
    $setup['section']->update(['capacity' => 1]);
    Enrollment::factory()->create(['section_id' => $setup['section']->id, 'academic_year_id' => $setup['year']->id]);
    $student = Student::factory()->create(['school_id' => $setup['school']->id]);

    $this->actingAs(studentAdmin($setup['school']))
        ->post(route('people.students.enrollments.store', $student), ['academic_year_id' => $setup['year']->id, 'section_id' => $setup['section']->id])
        ->assertSessionHasErrors('section_id');

    $this->assertDatabaseMissing('enrollments', ['student_id' => $student->id]);
});

test('roll numbers are unique within a section and year', function () {
    $setup = academicSchool();
    Enrollment::factory()->create(['section_id' => $setup['section']->id, 'academic_year_id' => $setup['year']->id, 'roll_number' => 7]);
    $student = Student::factory()->create(['school_id' => $setup['school']->id]);

    $this->actingAs(studentAdmin($setup['school']))
        ->post(route('people.students.enrollments.store', $student), ['academic_year_id' => $setup['year']->id, 'section_id' => $setup['section']->id, 'roll_number' => 7])
        ->assertSessionHasErrors('roll_number');
});

test('students cannot be enrolled in another school\'s section', function () {
    $setup = academicSchool();
    $student = Student::factory()->create(['school_id' => $setup['school']->id]);
    $foreignSection = Section::factory()->create();

    $this->actingAs(studentAdmin($setup['school']))
        ->post(route('people.students.enrollments.store', $student), ['academic_year_id' => $setup['year']->id, 'section_id' => $foreignSection->id])
        ->assertSessionHasErrors('section_id');
});

test('sections with enrolled students cannot be removed from their class', function () {
    $setup = academicSchool();
    Enrollment::factory()->create(['section_id' => $setup['section']->id, 'academic_year_id' => $setup['year']->id]);
    $class = $setup['section']->schoolClass;

    $this->actingAs($setup['admin'])->put(route('academics.classes.update', $class), [
        'name' => $class->name, 'level' => $class->level, 'sections' => [], 'subjects' => [['id' => $setup['subject']->id, 'periods_per_week' => 5]],
    ])->assertSessionHasErrors('sections');

    $this->assertModelExists($setup['section']);
});
