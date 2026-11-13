<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Base policy for records owned by a school: the RBAC module permission plus
 * an explicit tenant check (defence in depth on top of the BelongsToSchool scope).
 */
abstract class SchoolOwnedPolicy
{
    /**
     * The RBAC module, e.g. "subjects" for subjects.view / subjects.edit.
     */
    abstract protected function module(): string;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission("{$this->module()}.view");
    }

    public function view(User $user, Model $record): bool
    {
        return $user->hasPermission("{$this->module()}.view") && $this->sameSchool($user, $record);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission("{$this->module()}.create");
    }

    public function update(User $user, Model $record): bool
    {
        return $user->hasPermission("{$this->module()}.edit") && $this->sameSchool($user, $record);
    }

    public function delete(User $user, Model $record): bool
    {
        return $user->hasPermission("{$this->module()}.delete") && $this->sameSchool($user, $record);
    }

    protected function sameSchool(User $user, Model $record): bool
    {
        return $user->isPlatformUser() || $user->school_id === $record->getAttribute('school_id');
    }
}
