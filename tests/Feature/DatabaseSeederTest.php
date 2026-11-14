<?php

use App\Models\Guardian;
use App\Models\Student;
use App\Models\User;

test('the demo seeder links demo accounts to their people records and can run twice', function () {
    $this->seed();
    $this->seed();

    $student = Student::query()->withoutGlobalScopes()->where('admission_no', 'GHS250001')->sole();
    $parent = User::query()->where('email', 'parent@example.com')->sole();

    expect($student->user->email)->toBe('student@example.com')
        ->and(Guardian::query()->withoutGlobalScopes()->where('user_id', $parent->id)->sole()->students()->pluck('students.id')->all())->toBe([$student->id])
        ->and(User::query()->where('email', 'teacher@example.com')->sole()->employee()->withoutGlobalScopes()->exists())->toBeTrue()
        ->and(User::query()->where('email', 'admin@example.com')->sole()->isSuperAdmin())->toBeTrue();
});
