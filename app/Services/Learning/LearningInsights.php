<?php

namespace App\Services\Learning;

use App\Enums\PublishStatus;
use App\Enums\QuizAttemptStatus;
use App\Models\AcademicYear;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Services\Academics\TeachingScope;
use App\Services\Analytics\AnalyticsClient;
use App\Services\Tenancy\CurrentSchool;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Learning analytics for teachers and administrators: course completion, quiz results and
 * an engagement score per student computed by the Python analytics service. Only ids and
 * counts are sent to Python; names are attached here afterwards.
 */
class LearningInsights
{
    public function __construct(
        private TeachingScope $teaching,
        private AnalyticsClient $client,
        private CurrentSchool $currentSchool,
    ) {}

    /**
     * Sections the user oversees this year (null = the whole school).
     *
     * @return list<int>|null
     */
    public function sectionIds(User $user, AcademicYear $year): ?array
    {
        return $this->teaching->sectionIds($user, $year, 'courses');
    }

    /**
     * Completion and reach for each published course within the user's sections.
     *
     * @param  list<int>|null  $sectionIds
     * @return list<array{id: int, title: string, subject: string, color: string|null, lessons: int, audience: int, started: int, average_completion: int}>
     */
    public function courses(AcademicYear $year, ?array $sectionIds): array
    {
        $courses = Course::query()
            ->with(['subject:id,name,color', 'students:id'])
            ->where('academic_year_id', $year->id)
            ->where('status', PublishStatus::Published)
            ->when($sectionIds !== null, fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereIn('section_id', $sectionIds)
                ->orWhere(fn (Builder $query) => $query->whereNull('section_id')->whereIn('school_class_id', $this->classIdsFor($sectionIds)))))
            ->get();

        $rows = [];

        foreach ($courses as $course) {
            $lessonIds = $this->visibleLessons($course)->pluck('id');
            $audience = $this->audienceStudentIds($course, $year, $sectionIds);
            $completedByStudent = LessonProgress::query()
                ->whereIn('lesson_id', $lessonIds)
                ->whereIn('student_id', $audience)
                ->whereNotNull('completed_at')
                ->selectRaw('student_id, count(*) as done')
                ->groupBy('student_id')
                ->pluck('done', 'student_id');
            $started = LessonProgress::query()->whereIn('lesson_id', $lessonIds)->whereIn('student_id', $audience)->distinct()->count('student_id');
            $lessons = $lessonIds->count();
            $average = $lessons > 0 && $audience->isNotEmpty()
                ? (int) round($audience->sum(fn (int $id): int => (int) ($completedByStudent[$id] ?? 0)) / ($lessons * $audience->count()) * 100)
                : 0;

            $rows[] = [
                'id' => $course->id,
                'title' => $course->title,
                'subject' => $course->subject->name,
                'color' => $course->subject->color,
                'lessons' => $lessons,
                'audience' => $audience->count(),
                'started' => $started,
                'average_completion' => $average,
            ];
        }

        usort($rows, fn (array $a, array $b): int => $a['average_completion'] <=> $b['average_completion']);

        return $rows;
    }

    /**
     * @param  list<int>|null  $sectionIds
     * @return list<array{id: int, title: string, subject: string, attempts: int, average: float|null, to_mark: int}>
     */
    public function quizzes(AcademicYear $year, ?array $sectionIds): array
    {
        return array_values(Quiz::query()
            ->with('subject:id,name')
            ->where('academic_year_id', $year->id)
            ->where('status', PublishStatus::Published)
            ->when($sectionIds !== null, fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereIn('section_id', $sectionIds)
                ->orWhere(fn (Builder $query) => $query->whereNull('section_id')->whereIn('school_class_id', $this->classIdsFor($sectionIds)))))
            ->withCount(['attempts', 'attempts as to_mark' => fn (Builder $query) => $query->where('status', QuizAttemptStatus::Submitted)])
            ->latest()
            ->take(12)
            ->get()
            ->map(function (Quiz $quiz): array {
                $graded = QuizAttempt::query()->where('quiz_id', $quiz->id)->where('status', QuizAttemptStatus::Graded)->where('max_score', '>', 0)->get(['score', 'max_score']);

                return [
                    'id' => $quiz->id,
                    'title' => $quiz->title,
                    'subject' => $quiz->subject->name,
                    'attempts' => (int) $quiz->getAttribute('attempts_count'),
                    'average' => $graded->isEmpty() ? null : round($graded->avg(fn (QuizAttempt $attempt): float => (float) $attempt->score / (float) $attempt->max_score * 100), 1),
                    'to_mark' => (int) $quiz->getAttribute('to_mark'),
                ];
            })
            ->all());
    }

    /**
     * Engagement scores from the analytics service, with student names attached.
     *
     * @param  list<int>|null  $sectionIds
     * @return array{available: bool, data: array<string, mixed>|null}
     */
    public function engagement(AcademicYear $year, ?array $sectionIds): array
    {
        $key = sprintf('analytics:%d:learning-engagement:%s:%s', (int) $this->currentSchool->id(), md5(json_encode($sectionIds) ?: ''), now()->format('Y-m-d-H'));
        $data = Cache::get($key);

        if (! is_array($data)) {
            $data = $this->client->compute('/v1/learning/engagement', ['students' => $this->engagementDataset($year, $sectionIds)]);

            if ($data === null) {
                return ['available' => false, 'data' => null];
            }

            Cache::put($key, $data, now()->addMinutes((int) config('services.analytics.cache_minutes', 10)));
        }

        $rows = array_slice(is_array($data['students'] ?? null) ? $data['students'] : [], 0, 30);
        $names = Student::query()->whereIn('id', array_column($rows, 'student_id'))->get(['id', 'first_name', 'last_name'])->keyBy('id');
        $data['students'] = array_values(array_filter(array_map(
            fn (mixed $row): ?array => is_array($row) && ($student = $names->get($row['student_id'] ?? 0)) !== null ? [...$row, 'name' => $student->full_name] : null,
            $rows,
        )));

        return ['available' => true, 'data' => $data];
    }

    /**
     * Per-student counts for the engagement model.
     *
     * @param  list<int>|null  $sectionIds
     * @return list<array<string, int|float|string|null>>
     */
    public function engagementDataset(AcademicYear $year, ?array $sectionIds): array
    {
        $enrollments = Enrollment::query()
            ->with('section:id,name,school_class_id', 'section.schoolClass:id,name')
            ->where('academic_year_id', $year->id)
            ->when($sectionIds !== null, fn (Builder $query) => $query->whereIn('section_id', $sectionIds))
            ->get();

        if ($enrollments->isEmpty()) {
            return [];
        }

        $studentIds = $enrollments->pluck('student_id');
        $courses = Course::query()->with('students:id')->where('academic_year_id', $year->id)->where('status', PublishStatus::Published)
            ->where(fn (Builder $query) => $query->whereNull('publish_at')->orWhere('publish_at', '<=', now()))->get();
        $lessonCounts = Lesson::query()->whereIn('course_id', $courses->pluck('id'))->where('status', PublishStatus::Published)
            ->where(fn (Builder $query) => $query->whereNull('publish_at')->orWhere('publish_at', '<=', now()))
            ->selectRaw('course_id, count(*) as total')->groupBy('course_id')->pluck('total', 'course_id');

        $completed = LessonProgress::query()->whereIn('student_id', $studentIds)->whereNotNull('completed_at')
            ->whereIn('lesson_id', Lesson::query()->whereIn('course_id', $courses->pluck('id'))->select('id'))
            ->selectRaw('student_id, count(*) as done')->groupBy('student_id')->pluck('done', 'student_id');
        $lastLesson = LessonProgress::query()->whereIn('student_id', $studentIds)->selectRaw('student_id, max(last_viewed_at) as last_at')->groupBy('student_id')->pluck('last_at', 'student_id');
        $lastQuiz = QuizAttempt::query()->whereIn('student_id', $studentIds)->selectRaw('student_id, max(started_at) as last_at')->groupBy('student_id')->pluck('last_at', 'student_id');
        $quizAverages = QuizAttempt::query()->whereIn('student_id', $studentIds)->where('status', QuizAttemptStatus::Graded)->where('max_score', '>', 0)
            ->whereIn('quiz_id', Quiz::query()->where('academic_year_id', $year->id)->select('id'))
            ->get(['student_id', 'score', 'max_score'])->groupBy('student_id')
            ->map(fn (Collection $attempts): float => round($attempts->avg(fn (QuizAttempt $attempt): float => (float) $attempt->score / (float) $attempt->max_score * 100), 1));

        $dueBySection = Assignment::query()->with('students:id')->where('academic_year_id', $year->id)->whereDate('due_on', '<=', now()->toDateString())->get()->groupBy('section_id');
        $submitted = AssignmentSubmission::query()->whereIn('student_id', $studentIds)->get(['assignment_id', 'student_id'])->groupBy('student_id');

        $rows = [];

        foreach ($enrollments as $enrollment) {
            $studentId = $enrollment->student_id;
            $classId = $enrollment->section->school_class_id;
            $available = $courses
                ->filter(fn (Course $course): bool => $course->school_class_id === $classId
                    && ($course->section_id === null || $course->section_id === $enrollment->section_id)
                    && ($course->students->isEmpty() || $course->students->contains('id', $studentId)))
                ->sum(fn (Course $course): int => (int) ($lessonCounts[$course->id] ?? 0));

            $due = ($dueBySection[$enrollment->section_id] ?? collect())
                ->filter(fn (Assignment $assignment): bool => $assignment->students->isEmpty() || $assignment->students->contains('id', $studentId));
            $handedIn = ($submitted[$studentId] ?? collect())->pluck('assignment_id')->intersect($due->pluck('id'))->count();

            $last = collect([$lastLesson[$studentId] ?? null, $lastQuiz[$studentId] ?? null])->filter()->max();

            $rows[] = [
                'student_id' => $studentId,
                'section' => "{$enrollment->section->schoolClass->name} – {$enrollment->section->name}",
                'lessons_available' => (int) $available,
                'lessons_completed' => (int) ($completed[$studentId] ?? 0),
                'days_since_active' => $last === null ? null : (int) max(0, now()->startOfDay()->diffInDays(CarbonImmutable::parse((string) $last)->startOfDay(), true)),
                'quiz_average' => $quizAverages[$studentId] ?? null,
                'work_due' => $due->count(),
                'work_submitted' => $handedIn,
            ];
        }

        return $rows;
    }

    /**
     * @return Builder<Lesson>
     */
    private function visibleLessons(Course $course): Builder
    {
        return Lesson::query()->where('course_id', $course->id)->where('status', PublishStatus::Published)
            ->where(fn (Builder $query) => $query->whereNull('publish_at')->orWhere('publish_at', '<=', now()));
    }

    /**
     * @param  list<int>|null  $sectionIds
     * @return Collection<int, int>
     */
    private function audienceStudentIds(Course $course, AcademicYear $year, ?array $sectionIds): Collection
    {
        if ($course->students->isNotEmpty()) {
            return $course->students->pluck('id')->map(fn (mixed $id): int => (int) $id)->values();
        }

        return Enrollment::query()
            ->where('academic_year_id', $year->id)
            ->whereHas('section', fn (Builder $query) => $query->where('school_class_id', $course->school_class_id))
            ->when($course->section_id !== null, fn (Builder $query) => $query->where('section_id', $course->section_id))
            ->when($sectionIds !== null, fn (Builder $query) => $query->whereIn('section_id', $sectionIds))
            ->pluck('student_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values();
    }

    /**
     * @param  list<int>  $sectionIds
     * @return list<int>
     */
    private function classIdsFor(array $sectionIds): array
    {
        return array_values(array_map('intval', Section::query()->whereIn('id', $sectionIds)->distinct()->pluck('school_class_id')->all()));
    }
}
