<?php

namespace App\Services\Academics;

use App\Enums\EmployeeStatus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Teaching staff of a school: active users linked to an active teaching staff record,
 * or holding the system "teacher" role (or a role inheriting from it).
 */
class TeacherDirectory
{
    private const string TEACHER_ROLE = 'teacher';

    /**
     * @return Builder<User>
     */
    public function query(int $schoolId): Builder
    {
        return User::query()
            ->where('school_id', $schoolId)
            ->where('is_active', true)
            ->where(fn (Builder $query) => $query
                ->whereHas('roles', fn (Builder $roles) => $roles->whereIn('roles.id', $this->teacherRoleIds()))
                ->orWhereHas('employee', fn (Builder $employee) => $employee
                    ->withoutGlobalScopes()
                    ->whereNull('deleted_at')
                    ->where('is_teaching', true)
                    ->where('status', EmployeeStatus::Active)));
    }

    /**
     * @return Collection<int, User>
     */
    public function forSchool(int $schoolId): Collection
    {
        return $this->query($schoolId)->orderBy('name')->get(['id', 'name']);
    }

    /**
     * The teacher system role plus every role descending from it.
     *
     * @return list<int>
     */
    private function teacherRoleIds(): array
    {
        $ids = Role::query()->whereNull('school_id')->where('slug', self::TEACHER_ROLE)->pluck('id')->all();
        $frontier = $ids;

        while ($frontier !== []) {
            $children = Role::query()->whereIn('parent_id', $frontier)->whereNotIn('id', $ids)->pluck('id')->all();
            $ids = [...$ids, ...$children];
            $frontier = $children;
        }

        return array_values(array_map(intval(...), $ids));
    }
}
