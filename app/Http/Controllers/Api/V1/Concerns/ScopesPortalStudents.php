<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Models\Student;
use App\Models\User;
use App\Services\People\PortalScope;
use Illuminate\Support\Facades\Gate;

/**
 * Students and guardians reach only their own (or their children's) records; staff are
 * checked against the student policy as on the website.
 */
trait ScopesPortalStudents
{
    /**
     * Student ids the user is limited to, or null for staff (unrestricted).
     *
     * @return list<int>|null
     */
    protected function portalStudentIds(User $user): ?array
    {
        $portalScope = app(PortalScope::class);

        return $portalScope->isPortalUser($user) ? $portalScope->studentIds($user) : null;
    }

    /**
     * Portal users get a 404 for students that are not theirs, so ids cannot be probed.
     */
    protected function authorizeStudent(User $user, Student $student): void
    {
        $studentIds = $this->portalStudentIds($user);

        if ($studentIds === null) {
            Gate::forUser($user)->authorize('view', $student);

            return;
        }

        abort_unless(in_array($student->id, $studentIds, true), 404);
    }
}
