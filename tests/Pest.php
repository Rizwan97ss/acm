<?php

use App\Models\AcademicYear;
use App\Models\Period;
use App\Models\Role;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\User;
use App\Services\Rbac\PermissionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

function superAdmin(): User
{
    $role = Role::query()->firstOrCreate(
        ['school_id' => null, 'slug' => config('rbac.super_admin_role')],
        ['name' => 'Super Admin', 'is_system' => true],
    );

    return User::factory()->withRoles($role)->create();
}
