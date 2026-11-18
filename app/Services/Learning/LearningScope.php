<?php

namespace App\Services\Learning;

use App\Enums\LiveClassStatus;
use App\Enums\PublishStatus;
use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LiveClass;
use App\Models\Quiz;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TimetableEntry;
use App\Models\User;
use App\Services\Academics\TeachingScope;
use App\Services\People\PortalScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Who may build learning content for which class, section and subject, and which
 * content, live classes and quizzes each student may see.
 *
 * Staff holding "{module}.approve" oversee the whole school. Teachers work with the
 * subjects they teach on the timetable, or every subject of sections they are class
 * teacher of. Content aimed at a whole class (no section) is visible in all its sections;
 * content with chosen students is visible only to them.
 */
class LearningScope
{
    public function __construct(
        private TeachingScope $teaching,
        private PortalScope $portal,
    ) {}

    public function supervises(User $user, string $module): bool
    {
        return $this->teaching->supervises($user, $module);
    }

    /**
     * May the user create content for this class (optionally one section) and subject?
     */
    public function canTeach(User $user, string $module, int $classId, ?int $sectionId, int $subjectId, AcademicYear $year): bool
    {
        if ($this->supervises($user, $module)) {
            return true;
        }

        $sections = Section::query()
            ->where('school_class_id', $classId)
            ->when($sectionId !== null, fn (Builder $query) => $query->whereKey($sectionId))
            ->get();

        foreach ($sections as $section) {
            if ($this->teaching->canTeachSubject($user, $section, $subjectId, $year, $module)) {
                return true;
            }
        }

        return false;
    }

    /**
     * May the user edit or delete an existing course, live class or quiz? Its author may,
     * as may anyone who teaches that subject there, and supervisors.
     */
    public function canManage(User $user, string $module, Course|LiveClass|Quiz $record): bool
    {
        if (! $user->hasPermission("{$module}.edit") || ! $this->sameSchool($user, $record)) {
            return false;
        }

        if ($record->created_by === $user->id || $this->supervises($user, $module)) {
            return true;
        }

        $year = AcademicYear::query()->find($record->academic_year_id);

        return $year !== null && $this->canTeach($user, $module, $record->school_class_id, $record->section_id, $record->subject_id, $year);
    }

    /**
     * Classes, sections and subjects the user may target, for the content forms.
     *
     * @return list<array{id: int, name: string, sections: list<array{id: int, name: string}>, subjects: list<array{id: int, name: string}>}>
     */
    public function teachingOptions(User $user, string $module, AcademicYear $year): array
    {
        $classes = SchoolClass::query()->with(['sections:id,school_class_id,name', 'subjects:id,name'])->orderBy('level')->get();

        if ($this->supervises($user, $module)) {
            return array_values($classes->map(fn (SchoolClass $class): array => $this->classOption($class, $class->sections, $class->subjects))->all());
        }

        $classTeacherOf = Section::query()->where('class_teacher_id', $user->id)->pluck('id')->all();
        $timetable = TimetableEntry::query()
            ->where('academic_year_id', $year->id)
            ->where('teacher_id', $user->id)
            ->get(['section_id', 'subject_id']);

        $options = [];

        foreach ($classes as $class) {
            $sections = $class->sections->filter(fn (Section $section): bool => in_array($section->id, $classTeacherOf, true)
                || $timetable->contains('section_id', $section->id));

            if ($sections->isEmpty()) {
                continue;
            }

            $wholeClassSubjects = $sections->contains(fn (Section $section): bool => in_array($section->id, $classTeacherOf, true));
            $subjectIds = $timetable->whereIn('section_id', $sections->pluck('id'))->pluck('subject_id')->all();
            $subjects = $wholeClassSubjects ? $class->subjects : $class->subjects->whereIn('id', $subjectIds);

            $options[] = $this->classOption($class, $sections, $subjects);
        }

        return $options;
    }

    /**
     * The student a request is about: the signed-in student, or (for parents) the chosen
     * child, defaulting to the first. Null for staff and for parents without children.
     */
    public function learner(User $user, ?int $requestedStudentId = null): ?Student
    {
        if (! $this->portal->isPortalUser($user)) {
            return null;
        }

        $ids = $this->portal->studentIds($user);

        if ($ids === []) {
            return null;
        }

        $id = $requestedStudentId !== null && in_array($requestedStudentId, $ids, true) ? $requestedStudentId : $ids[0];

        return Student::query()->find($id);
    }

    /**
     * The section the student is enrolled in this year.
     */
    public function enrollmentFor(Student $student, AcademicYear $year): ?Enrollment
    {
        return Enrollment::query()
            ->with('section:id,school_class_id,name')
            ->where('student_id', $student->id)
            ->where('academic_year_id', $year->id)
            ->first();
    }

    /**
     * Published courses the student may open.
     *
     * @return Builder<Course>
     */
    public function coursesFor(Student $student, AcademicYear $year): Builder
    {
        $enrollment = $this->enrollmentFor($student, $year);

        $query = Course::query()->where('academic_year_id', $year->id);

        if ($enrollment === null) {
            return $query->whereRaw('1 = 0');
        }

        return $this->forAudience($query, $enrollment, $student)
            ->where('status', PublishStatus::Published)
            ->where(fn (Builder $query) => $query->whereNull('publish_at')->orWhere('publish_at', '<=', now()))
            ->where(fn (Builder $query) => $query
                ->whereDoesntHave('students')
                ->orWhereHas('students', fn (Builder $students) => $students->whereKey($student->id)));
    }

    /**
     * Published lessons of the given courses that have reached their publish time.
     *
     * @param  Builder<Course>  $courses
     * @return Builder<Lesson>
     */
    public function lessonsIn(Builder $courses): Builder
    {
        return Lesson::query()
            ->whereIn('lessons.course_id', (clone $courses)->select('courses.id'))
            ->where('lessons.status', PublishStatus::Published)
            ->where(fn (Builder $query) => $query->whereNull('lessons.publish_at')->orWhere('lessons.publish_at', '<=', now()));
    }

    public function canViewLesson(Student $student, Lesson $lesson, AcademicYear $year): bool
    {
        return $this->lessonsIn($this->coursesFor($student, $year))->where('lessons.id', $lesson->id)->exists();
    }

    /**
     * @return Builder<LiveClass>
     */
    public function liveClassesFor(Student $student, AcademicYear $year): Builder
    {
        $enrollment = $this->enrollmentFor($student, $year);
        $query = LiveClass::query()->where('academic_year_id', $year->id)->where('status', LiveClassStatus::Scheduled);

        return $enrollment === null ? $query->whereRaw('1 = 0') : $this->forAudience($query, $enrollment, $student);
    }

    /**
     * @return Builder<Quiz>
     */
    public function quizzesFor(Student $student, AcademicYear $year): Builder
    {
        $enrollment = $this->enrollmentFor($student, $year);
        $query = Quiz::query()->where('academic_year_id', $year->id)->where('status', PublishStatus::Published);

        return $enrollment === null ? $query->whereRaw('1 = 0') : $this->forAudience($query, $enrollment, $student);
    }

    /**
     * Active portal accounts of the students in an audience, for notifications.
     *
     * @param  list<int>|null  $onlyStudentIds  limit to chosen students
     * @return Collection<int, User>
     */
    public function studentUsers(int $yearId, int $classId, ?int $sectionId, ?array $onlyStudentIds = null): Collection
    {
        $studentIds = Enrollment::query()
            ->where('academic_year_id', $yearId)
            ->whereHas('section', fn (Builder $query) => $query->where('school_class_id', $classId))
            ->when($sectionId !== null, fn (Builder $query) => $query->where('section_id', $sectionId))
            ->when($onlyStudentIds !== null && $onlyStudentIds !== [], fn (Builder $query) => $query->whereIn('student_id', $onlyStudentIds))
            ->pluck('student_id');

        return User::query()
            ->where('is_active', true)
            ->whereHas('student', fn (Builder $query) => $query->whereIn('students.id', $studentIds))
            ->get();
    }

    /**
     * Students the user may choose as a restricted audience in a class (optionally section).
     *
     * @return list<array{id: int, name: string, section_id: int}>
     */
    public function rosterFor(int $yearId, int $classId, ?int $sectionId = null): array
    {
        return array_values(Enrollment::query()
            ->with('student:id,first_name,last_name')
            ->where('academic_year_id', $yearId)
            ->whereHas('section', fn (Builder $query) => $query->where('school_class_id', $classId))
            ->when($sectionId !== null, fn (Builder $query) => $query->where('section_id', $sectionId))
            ->get()
            ->filter(fn (Enrollment $enrollment): bool => $enrollment->student !== null)
            ->map(fn (Enrollment $enrollment): array => [
                'id' => $enrollment->student_id,
                'name' => $enrollment->student->full_name,
                'section_id' => $enrollment->section_id,
            ])
            ->sortBy('name')
            ->values()
            ->all());
    }

    /**
     * @template TModel of Course|LiveClass|Quiz
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function forAudience(Builder $query, Enrollment $enrollment, Student $student): Builder
    {
        return $query
            ->where('school_class_id', $enrollment->section->school_class_id)
            ->where(fn (Builder $query) => $query->whereNull('section_id')->orWhere('section_id', $enrollment->section_id));
    }

    /**
     * @param  Collection<int, Section>  $sections
     * @param  Collection<int, Subject>  $subjects
     * @return array{id: int, name: string, sections: list<array{id: int, name: string}>, subjects: list<array{id: int, name: string}>}
     */
    private function classOption(SchoolClass $class, Collection $sections, Collection $subjects): array
    {
        return [
            'id' => $class->id,
            'name' => $class->name,
            'sections' => array_values($sections->map(fn (Section $section): array => ['id' => $section->id, 'name' => $section->name])->all()),
            'subjects' => array_values($subjects->map(fn ($subject): array => ['id' => $subject->id, 'name' => $subject->name])->sortBy('name')->all()),
        ];
    }

    private function sameSchool(User $user, Model $record): bool
    {
        return $user->isPlatformUser() || $user->school_id === $record->getAttribute('school_id');
    }
}
