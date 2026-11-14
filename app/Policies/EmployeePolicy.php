<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;

/**
 * employees.* covers all staff; teachers.* covers teaching staff only.
 */
class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['employees.view', 'teachers.view']);
    }

    public function view(User $user, Employee $employee): bool
    {
        return $this->allows($user, $employee, 'view');
    }

    public function create(User $user): bool
    {
        return $user->hasAnyPermission(['employees.create', 'teachers.create']);
    }

    public function update(User $user, Employee $employee): bool
    {
        return $this->allows($user, $employee, 'edit');
    }

    public function delete(User $user, Employee $employee): bool
    {
        return $this->allows($user, $employee, 'delete');
    }

    /**
     * Whether the user may act on staff of the given kind.
     */
    public function manageKind(User $user, bool $isTeaching, string $action): bool
    {
        return $user->hasPermission("employees.{$action}")
            || ($isTeaching && $user->hasPermission("teachers.{$action}"));
    }

    private function allows(User $user, Employee $employee, string $action): bool
    {
        $sameSchool = $user->isPlatformUser() || $user->school_id === $employee->school_id;

        return $sameSchool && $this->manageKind($user, $employee->is_teaching, $action);
    }
}
