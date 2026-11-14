<?php

namespace Database\Seeders;

use App\Enums\Gender;
use App\Enums\GuardianRelationship;
use App\Models\Employee;
use App\Models\Guardian;
use App\Models\Role;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\Rbac\PermissionResolver;
use Illuminate\Database\Seeder;

/**
 * Demo data for local development. Every account uses the password "password".
 */
class DemoSeeder extends Seeder
{
    public function run(PermissionResolver $resolver): void
    {
        $main = School::query()->firstOrCreate(['code' => 'GHS'], [
            'name' => 'Greenfield High School',
            'email' => 'office@greenfield.test',
            'phone' => '+1 555 0100',
            'address' => '12 Elm Street, Springfield',
        ]);

        $branch = School::query()->firstOrCreate(['code' => 'GHS-N'], [
            'name' => 'Greenfield North Campus',
            'parent_id' => $main->id,
            'email' => 'north@greenfield.test',
        ]);

        $roles = Role::query()->whereNull('school_id')->pluck('id', 'slug');

        $accounts = [
            ['admin@example.com', 'Platform Admin', null, 'super-admin'],
            ['school.admin@example.com', 'Sam School-Admin', $main, 'school-admin'],
            ['principal@example.com', 'Priya Principal', $main, 'principal'],
            ['teacher@example.com', 'Tom Teacher', $main, 'teacher'],
            ['accountant@example.com', 'Alex Accountant', $main, 'accountant'],
            ['hr@example.com', 'Hana HR', $main, 'hr'],
            ['librarian@example.com', 'Lee Librarian', $main, 'librarian'],
            ['reception@example.com', 'Rita Reception', $main, 'receptionist'],
            ['student@example.com', 'Stu Student', $main, 'student'],
            ['parent@example.com', 'Pat Parent', $main, 'parent'],
            ['north.admin@example.com', 'Nora North-Admin', $branch, 'school-admin'],
        ];

        foreach ($accounts as [$email, $name, $school, $role]) {
            $user = User::query()->firstOrCreate(['email' => $email], [
                'name' => $name,
                'password' => 'password',
                'school_id' => $school?->id,
            ]);

            $user->forceFill(['email_verified_at' => now()])->save();
            $user->roles()->syncWithoutDetaching([$roles[$role] => ['created_at' => now()]]);
        }

        $this->seedPeople($main);

        $resolver->flush();
    }

    /**
     * Link the demo teacher, student and parent accounts to people records.
     */
    private function seedPeople(School $school): void
    {
        $account = fn (string $email): int => (int) User::query()->where('email', $email)->value('id');

        Employee::query()->withoutGlobalScopes()->firstOrCreate(['school_id' => $school->id, 'employee_no' => 'GHS-E0001'], [
            'user_id' => $account('teacher@example.com'),
            'first_name' => 'Tom',
            'last_name' => 'Teacher',
            'email' => 'teacher@example.com',
            'is_teaching' => true,
            'designation' => 'Mathematics teacher',
            'joined_on' => '2024-08-15',
        ]);

        $student = Student::query()->withoutGlobalScopes()->firstOrCreate(['school_id' => $school->id, 'admission_no' => 'GHS250001'], [
            'user_id' => $account('student@example.com'),
            'first_name' => 'Stu',
            'last_name' => 'Student',
            'gender' => Gender::Male,
            'date_of_birth' => '2014-05-20',
            'email' => 'student@example.com',
            'admission_date' => '2025-09-01',
        ]);

        $guardian = Guardian::query()->withoutGlobalScopes()->firstOrCreate(['school_id' => $school->id, 'email' => 'parent@example.com'], [
            'user_id' => $account('parent@example.com'),
            'first_name' => 'Pat',
            'last_name' => 'Parent',
            'phone' => '+1 555 0199',
        ]);

        $student->guardians()->syncWithoutDetaching([
            $guardian->id => ['relationship' => GuardianRelationship::Mother->value, 'is_primary' => true, 'is_emergency_contact' => true],
        ]);
    }
}
