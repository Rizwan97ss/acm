<?php

namespace App\Services\Academics\Exams;

use App\Enums\EnrollmentStatus;
use App\Enums\PublishStatus;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\OnlineExam;
use App\Models\Student;
use App\Models\User;
use App\Services\Learning\LearningScope;
use App\Services\People\PortalScope;
use Illuminate\Database\Eloquent\Builder;

/**
 * Who may see and manage which online exams.
 *
 * Staff: holders of "online_exams.approve" supervise every exam in the school. Teachers
 * work with the classes and subjects they teach on the timetable; class teachers with
 * every subject of their section. Authors may always manage their own exams.
 *
 * Students: only published exams of the current year for the section they are actively
 * enrolled in (exams for the whole class, their section, or naming them individually).
 */
class OnlineExamScope
{
    public const string MODULE = 'online_exams';

    public function __construct(
        private LearningScope $learning,
        private PortalScope $portal,
    ) {}

    public function supervises(User $user): bool
    {
        return $this->learning->supervises($user, self::MODULE);
    }

    public function canTeach(User $user, int $classId, ?int $sectionId, int $subjectId, AcademicYear $year): bool
    {
        return $this->learning->canTeach($user, self::MODULE, $classId, $sectionId, $subjectId, $year);
    }

    /**
     * Staff with access to the online exams area (not students or parents).
     */
    public function isStaff(User $user): bool
    {
        return $user->hasPermission(self::MODULE.'.view') && ! $this->portal->isPortalUser($user);
    }

    public function canView(User $user, OnlineExam $exam): bool
    {
        if (! $user->hasPermission(self::MODULE.'.view') || $this->portal->isPortalUser($user) || ! $this->sameSchool($user, $exam)) {
            return false;
        }

        return $this->owns($user, $exam);
    }

    public function canManage(User $user, OnlineExam $exam): bool
    {
        return $user->hasPermission(self::MODULE.'.edit') && $this->canView($user, $exam);
    }

    /**
     * Classes, sections and subjects the user may set exams for.
     *
     * @return list<array{id: int, name: string, sections: list<array{id: int, name: string}>, subjects: list<array{id: int, name: string}>}>
     */
    public function teachingOptions(User $user, AcademicYear $year): array
    {
        return $this->learning->teachingOptions($user, self::MODULE, $year);
    }

    /**
     * Limit a staff query to exams the user may see.
     *
     * @param  Builder<OnlineExam>  $query
     * @return Builder<OnlineExam>
     */
    public function forStaff(Builder $query, User $user, AcademicYear $year): Builder
    {
        if ($this->supervises($user)) {
            return $query;
        }

        $pairs = collect($this->teachingOptions($user, $year))
            ->flatMap(fn (array $class) => collect($class['subjects'])->map(fn (array $subject): array => [$class['id'], $subject['id'], array_column($class['sections'], 'id')]));

        return $query->where(function (Builder $query) use ($user, $pairs): void {
            $query->where('created_by', $user->id);

            foreach ($pairs as [$classId, $subjectId, $sectionIds]) {
                $query->orWhere(fn (Builder $query) => $query
                    ->where('school_class_id', $classId)
                    ->where('subject_id', $subjectId)
                    ->where(fn (Builder $query) => $query->whereNull('section_id')->orWhereIn('section_id', $sectionIds)));
            }
        });
    }

    /**
     * The section a student is actively enrolled in this year, if any.
     */
    public function enrollmentFor(Student $student, AcademicYear $year): ?Enrollment
    {
        return Enrollment::query()
            ->with('section:id,school_class_id,name')
            ->where('student_id', $student->id)
            ->where('academic_year_id', $year->id)
            ->where('status', EnrollmentStatus::Active)
            ->first();
    }

    /**
     * Published exams the student may see.
     *
     * @return Builder<OnlineExam>
     */
    public function examsFor(Student $student, AcademicYear $year): Builder
    {
        $enrollment = $this->enrollmentFor($student, $year);
        $query = OnlineExam::query()->where('academic_year_id', $year->id)->where('status', PublishStatus::Published);

        if ($enrollment === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->where('school_class_id', $enrollment->section->school_class_id)
            ->where(fn (Builder $query) => $query->whereNull('section_id')->orWhere('section_id', $enrollment->section_id))
            ->where(fn (Builder $query) => $query
                ->whereDoesntHave('students')
                ->orWhereHas('students', fn (Builder $students) => $students->where('students.id', $student->id)));
    }

    /**
     * The signed-in student (only students sit exams; parents see results).
     */
    public function examinee(User $user): ?Student
    {
        if (! $this->portal->isPortalUser($user) || $user->employee()->exists()) {
            return null;
        }

        return $user->student;
    }

    /**
     * The student a results page is about: the signed-in student, or a parent's chosen child.
     */
    public function learner(User $user, ?int $studentId = null): ?Student
    {
        return $this->learning->learner($user, $studentId);
    }

    /**
     * @return list<int>
     */
    public function childIds(User $user): array
    {
        return $this->portal->isPortalUser($user) ? $this->portal->studentIds($user) : [];
    }

    private function owns(User $user, OnlineExam $exam): bool
    {
        if ($exam->created_by === $user->id || $this->supervises($user)) {
            return true;
        }

        $year = AcademicYear::query()->find($exam->academic_year_id);

        return $year !== null && $this->canTeach($user, $exam->school_class_id, $exam->section_id, $exam->subject_id, $year);
    }

    private function sameSchool(User $user, OnlineExam $exam): bool
    {
        return $user->isPlatformUser() || $user->school_id === $exam->school_id;
    }
}
