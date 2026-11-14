<?php

namespace App\Services\Communication;

use App\Enums\Audience;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\People\PortalScope;
use App\Services\Tenancy\CurrentSchool;
use Illuminate\Database\Eloquent\Builder;

/**
 * Translates audiences into people: which audiences a viewer belongs to (for reading)
 * and which user accounts an announcement reaches (for notifications).
 */
class AudienceResolver
{
    /**
     * @var array<int, array{audiences: list<Audience>, class_ids: list<int>}>
     */
    private array $profiles = [];

    public function __construct(
        private PortalScope $portalScope,
        private CurrentSchool $currentSchool,
    ) {}

    /**
     * @return array{audiences: list<Audience>, class_ids: list<int>}
     */
    public function viewerProfile(User $user): array
    {
        return $this->profiles[$user->id] ??= $this->buildProfile($user);
    }

    /**
     * Active user accounts in the current school that belong to the audience.
     *
     * @return Builder<User>
     */
    public function recipients(Audience $audience, ?int $classId): Builder
    {
        $schoolId = (int) $this->currentSchool->id();
        $inClass = function (Builder $students) use ($classId): void {
            if ($classId !== null) {
                $students->whereHas('currentEnrollment.section', fn (Builder $section) => $section->where('school_class_id', $classId));
            }
        };

        return User::query()
            ->where('school_id', $schoolId)
            ->where('is_active', true)
            ->where(function (Builder $query) use ($audience, $inClass): void {
                if (in_array($audience, [Audience::Everyone, Audience::Staff], true)) {
                    $query->orWhereHas('employee')
                        ->orWhere(fn (Builder $query) => $query->whereDoesntHave('student')->whereDoesntHave('guardian')->whereHas('roles'));
                }

                if (in_array($audience, [Audience::Everyone, Audience::Students], true)) {
                    $query->orWhereHas('student', $inClass);
                }

                if (in_array($audience, [Audience::Everyone, Audience::Guardians], true)) {
                    $query->orWhereHas('guardian.students', $inClass);
                }
            });
    }

    /**
     * @return array{audiences: list<Audience>, class_ids: list<int>}
     */
    private function buildProfile(User $user): array
    {
        $audiences = [];

        // Staff are employees, or non-portal accounts given a role. A self-registered account
        // with no role is nobody yet and must not see staff-only communication.
        if ($user->employee()->exists() || (! $this->portalScope->isPortalUser($user) && $user->roles()->exists())) {
            $audiences[] = Audience::Staff;
        }

        if ($user->student()->exists()) {
            $audiences[] = Audience::Students;
        }

        if ($user->guardian()->exists()) {
            $audiences[] = Audience::Guardians;
        }

        $studentIds = $this->portalScope->studentIds($user);
        $classIds = $studentIds === [] ? [] : Enrollment::query()
            ->whereIn('student_id', $studentIds)
            ->whereHas('academicYear', fn (Builder $query) => $query->where('is_current', true))
            ->join('sections', 'sections.id', '=', 'enrollments.section_id')
            ->distinct()
            ->pluck('sections.school_class_id')
            ->all();

        return ['audiences' => $audiences, 'class_ids' => array_values(array_map(intval(...), $classIds))];
    }
}
