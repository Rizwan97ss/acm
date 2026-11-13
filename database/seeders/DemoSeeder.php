<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\School;
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

        $resolver->flush();
    }
}
