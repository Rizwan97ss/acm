<?php

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\Period;
use App\Models\Role;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\Rbac\PermissionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Create a user holding exactly the given permissions through a dedicated role.
 *
 * @param  list<string>  $permissions
 */
function userWithPermissions(array $permissions, ?School $school = null): User
{
    $roleFactory = Role::factory()->withPermissions($permissions);
    $userFactory = User::factory();

    if ($school !== null) {
        $roleFactory = $roleFactory->forSchool($school);
        $userFactory = $userFactory->forSchool($school);
    }

    return $userFactory->withRoles($roleFactory->create())->create();
}

/**
 * A school with a current year, one class (section A) taking one subject, two lesson
 * periods, a teacher and an administrator holding every academics permission.
 *
 * @return array{school: School, admin: User, teacher: User, year: AcademicYear, section: Section, subject: Subject, period: Period, nextPeriod: Period}
 */
function academicSchool(): array
{
    $school = School::factory()->create();
    $admin = userWithPermissions(app(PermissionRegistry::class)->expand(
        ['academic_years.*', 'classes.*', 'subjects.*', 'timetables.*'],
    ), $school);

    $teacherRole = Role::query()->firstOrCreate(
        ['school_id' => null, 'slug' => 'teacher'],
        ['name' => 'Teacher', 'is_system' => true],
    );
    $teacher = User::factory()->forSchool($school)->withRoles($teacherRole)->create();

    $year = AcademicYear::factory()->current()->create(['school_id' => $school->id]);
    $class = SchoolClass::factory()->create(['school_id' => $school->id]);
    $section = Section::factory()->create(['school_class_id' => $class->id, 'name' => 'A', 'room' => 'R-1']);
    $subject = Subject::factory()->create(['school_id' => $school->id]);
    $class->subjects()->attach($subject->id, ['periods_per_week' => 5]);

    $period = Period::factory()->create(['school_id' => $school->id, 'position' => 1, 'starts_at' => '08:00', 'ends_at' => '08:45']);
    $nextPeriod = Period::factory()->create(['school_id' => $school->id, 'position' => 2, 'starts_at' => '08:45', 'ends_at' => '09:30']);

    return compact('school', 'admin', 'teacher', 'year', 'section', 'subject', 'period', 'nextPeriod');
}

/**
 * academicSchool() plus a class teacher with teaching permissions and an enrolled
 * student whose guardian is an emergency contact with an email address.
 *
 * @return array<string, mixed>
 */
function teachingSchool(): array
{
    $setup = academicSchool();

    $classTeacher = userWithPermissions([
        'attendance.view', 'attendance.create', 'attendance.edit',
        'exams.view', 'grades.view', 'grades.create', 'grades.edit',
        'assignments.view', 'assignments.create', 'assignments.edit', 'assignments.delete',
    ], $setup['school']);
    $setup['section']->update(['class_teacher_id' => $classTeacher->id]);

    $student = Student::factory()->create(['school_id' => $setup['school']->id, 'first_name' => 'Ali']);
    Enrollment::factory()->create(['student_id' => $student->id, 'section_id' => $setup['section']->id, 'academic_year_id' => $setup['year']->id, 'roll_number' => 1]);

    $guardian = Guardian::factory()->create(['school_id' => $setup['school']->id, 'email' => 'mum@example.com']);
    $student->guardians()->attach($guardian->id, ['relationship' => 'mother', 'is_primary' => true, 'is_emergency_contact' => true]);

    return [...$setup, 'classTeacher' => $classTeacher, 'student' => $student, 'guardian' => $guardian];
}

function superAdmin(): User
{
    $role = Role::query()->firstOrCreate(
        ['school_id' => null, 'slug' => config('rbac.super_admin_role')],
        ['name' => 'Super Admin', 'is_system' => true],
    );

    return User::factory()->withRoles($role)->create();
}

/**
 * teachingSchool() plus portal accounts: the enrolled student and their guardian (class 1),
 * and a guardian whose child is in a second class.
 *
 * @return array<string, mixed>
 */
function communitySchool(): array
{
    $setup = teachingSchool();
    $school = $setup['school'];

    $studentUser = userWithPermissions(['announcements.view', 'events.view', 'messages.view'], $school);
    $setup['student']->update(['user_id' => $studentUser->id]);

    $guardianUser = userWithPermissions(['announcements.view', 'events.view', 'messages.view', 'messages.create'], $school);
    $setup['guardian']->update(['user_id' => $guardianUser->id]);

    $otherClass = SchoolClass::factory()->create(['school_id' => $school->id]);
    $otherSection = Section::factory()->create(['school_class_id' => $otherClass->id, 'name' => 'B']);
    $otherStudent = Student::factory()->create(['school_id' => $school->id]);
    Enrollment::factory()->create(['student_id' => $otherStudent->id, 'section_id' => $otherSection->id, 'academic_year_id' => $setup['year']->id]);
    $otherGuardianUser = userWithPermissions(['announcements.view', 'events.view', 'messages.view', 'messages.create'], $school);
    $otherGuardian = Guardian::factory()->create(['school_id' => $school->id, 'user_id' => $otherGuardianUser->id]);
    $otherStudent->guardians()->attach($otherGuardian->id, ['relationship' => 'father', 'is_primary' => true, 'is_emergency_contact' => true]);

    $manager = userWithPermissions(['announcements.view', 'announcements.create', 'announcements.edit', 'announcements.delete', 'announcements.approve', 'events.view', 'events.create', 'events.edit', 'events.delete', 'messages.view', 'messages.create'], $school);

    return [...$setup, 'studentUser' => $studentUser, 'guardianUser' => $guardianUser, 'otherClass' => $otherClass, 'otherGuardianUser' => $otherGuardianUser, 'manager' => $manager];
}

/**
 * Points a log channel at a temporary file and returns its path.
 */
function captureLogChannel(string $channel): string
{
    $path = storage_path('framework/testing/logs/'.Str::uuid().'.log');
    config(["logging.channels.{$channel}" => ['driver' => 'single', 'path' => $path, 'level' => 'debug']]);
    test()->beforeApplicationDestroyed(fn () => File::delete($path));

    return $path;
}
