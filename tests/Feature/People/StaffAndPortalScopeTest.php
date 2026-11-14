<?php

use App\Models\Employee;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\Role;
use App\Models\Section;
use App\Models\Student;
use App\Models\TimetableEntry;
use App\Models\User;
use App\Services\Academics\TeacherDirectory;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array<string, mixed>
 */
function employeePayload(array $overrides = []): array
{
    return [
        'first_name' => 'Omar',
        'last_name' => 'Farooq',
        'is_teaching' => true,
        'designation' => 'Physics teacher',
        'employment_type' => 'full_time',
        'joined_on' => '2025-08-01',
        'status' => 'active',
        'bank_account' => 'PK36SCBL0000001123456702',
        ...$overrides,
    ];
}

test('staff with only teachers permissions can manage teaching staff but not other employees', function () {
    $setup = academicSchool();
    $headOfTeaching = userWithPermissions(['teachers.view', 'teachers.create', 'teachers.edit'], $setup['school']);
    $clerk = Employee::factory()->create(['school_id' => $setup['school']->id, 'is_teaching' => false]);

    $this->actingAs($headOfTeaching)->post(route('people.staff.store'), employeePayload())->assertSessionHasNoErrors();
    $this->actingAs($headOfTeaching)->post(route('people.staff.store'), employeePayload(['is_teaching' => false, 'first_name' => 'Clerk']))
        ->assertSessionHasErrors('is_teaching');
    $this->actingAs($headOfTeaching)->get(route('people.staff.edit', $clerk))->assertForbidden();

    $this->assertDatabaseMissing('employees', ['first_name' => 'Clerk']);
    $this->assertDatabaseHas('employees', ['first_name' => 'Omar', 'is_teaching' => true]);
});

test('teachers.view only lists teaching staff', function () {
    $setup = academicSchool();
    $teacher = Employee::factory()->teaching()->create(['school_id' => $setup['school']->id]);
    Employee::factory()->create(['school_id' => $setup['school']->id, 'is_teaching' => false]);

    $this->actingAs(userWithPermissions(['teachers.view'], $setup['school']))
        ->get(route('people.staff.index'))
        ->assertInertia(fn (Assert $page) => $page->has('employees.data', 1)->where('employees.data.0.id', $teacher->id)->where('teachingOnly', true));
});

test('bank details are encrypted and masked for viewers who cannot edit', function () {
    $setup = academicSchool();
    $hr = userWithPermissions(['employees.view', 'employees.create', 'employees.edit'], $setup['school']);
    $this->actingAs($hr)->post(route('people.staff.store'), employeePayload())->assertSessionHasNoErrors();

    $employee = Employee::query()->withoutGlobalScopes()->sole();
    expect(DB::table('employees')->value('bank_account'))->not->toContain('1123456702');

    $this->actingAs(userWithPermissions(['employees.view'], $setup['school']))
        ->get(route('people.staff.index'))
        ->assertInertia(fn (Assert $page) => $page->where('employees.data.0.bank_account', '•••• 6702'));
    expect($employee->employee_no)->toEndWith('-E0001');
});

test('users linked to active teaching staff records can be scheduled as teachers', function () {
    $setup = academicSchool();
    $account = User::factory()->forSchool($setup['school'])->create();
    Employee::factory()->teaching()->create(['school_id' => $setup['school']->id, 'user_id' => $account->id]);
    $inactive = User::factory()->forSchool($setup['school'])->create();
    Employee::factory()->teaching()->create(['school_id' => $setup['school']->id, 'user_id' => $inactive->id, 'status' => 'resigned']);

    $teacherIds = app(TeacherDirectory::class)->query($setup['school']->id)->pluck('id')->all();

    expect($teacherIds)->toContain($account->id)->not->toContain($inactive->id);
});

test('students only see their own section timetable', function () {
    $setup = academicSchool();
    $this->artisan('rbac:sync');
    $other = Section::factory()->create(['school_class_id' => $setup['section']->school_class_id, 'name' => 'B']);
    $account = User::factory()->forSchool($setup['school'])->withRoles(Role::query()->whereNull('school_id')->where('slug', 'student')->sole())->create();
    $student = Student::factory()->create(['school_id' => $setup['school']->id, 'user_id' => $account->id]);
    Enrollment::factory()->create(['student_id' => $student->id, 'section_id' => $setup['section']->id, 'academic_year_id' => $setup['year']->id]);
    TimetableEntry::factory()->create(['section_id' => $other->id, 'academic_year_id' => $setup['year']->id, 'period_id' => $setup['period']->id, 'subject_id' => $setup['subject']->id]);

    $this->actingAs($account)->get(route('academics.timetable.index', ['section_id' => $other->id, 'view' => 'teacher']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('selected.view', 'section')
            ->where('selected.section_id', $setup['section']->id)
            ->has('sections', 1)
            ->has('teachers', 0)
            ->has('entries', 0)
        );
});

test('guardians see the timetables of each of their children', function () {
    $setup = academicSchool();
    $this->artisan('rbac:sync');
    $other = Section::factory()->create(['school_class_id' => $setup['section']->school_class_id, 'name' => 'B']);
    Section::factory()->create(['school_class_id' => $setup['section']->school_class_id, 'name' => 'C']);
    $account = User::factory()->forSchool($setup['school'])->withRoles(Role::query()->whereNull('school_id')->where('slug', 'parent')->sole())->create();
    $guardian = Guardian::factory()->create(['school_id' => $setup['school']->id, 'user_id' => $account->id]);

    foreach ([$setup['section'], $other] as $section) {
        $child = Student::factory()->create(['school_id' => $setup['school']->id]);
        $child->guardians()->attach($guardian->id, ['relationship' => 'mother']);
        Enrollment::factory()->create(['student_id' => $child->id, 'section_id' => $section->id, 'academic_year_id' => $setup['year']->id]);
    }

    $this->actingAs($account)->get(route('academics.timetable.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('sections', 2)
        ->where('sections', fn ($sections) => collect($sections)->pluck('id')->sort()->values()->all() === collect([$setup['section']->id, $other->id])->sort()->values()->all())
    );
});
