<?php

namespace App\Http\Controllers\Learning;

use App\Enums\MaterialKind;
use App\Enums\QuizAttemptStatus;
use App\Models\AcademicYear;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonBookmark;
use App\Models\LessonMaterial;
use App\Models\LessonProgress;
use App\Models\LiveClass;
use App\Models\QuizAttempt;
use App\Models\Student;
use App\Models\User;
use App\Services\Learning\LearningFiles;
use App\Services\Learning\LearningProgress;
use App\Services\Learning\LearningScope;
use App\Services\Settings\SettingsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The student learning portal. Parents see their child's learning read-only.
 */
class LearnController extends LearningController
{
    public function __construct(
        LearningScope $scope,
        private LearningProgress $progress,
        private LearningFiles $files,
        private SettingsService $settings,
    ) {
        parent::__construct($scope);
    }

    public function index(Request $request): Response
    {
        Gate::authorize('courses.view');

        $user = $request->user();
        $year = AcademicYear::query()->current()->first();
        $learner = $this->scope->learner($user, $request->integer('student') ?: null);

        if ($learner === null || $year === null) {
            return Inertia::render('learn/index', ['learner' => null, 'children' => $this->children($user)]);
        }

        $courses = $this->scope->coursesFor($learner, $year)->with('subject:id,name,color')->orderBy('title')->get();
        $lessonIds = array_values(array_map('intval', $this->scope->lessonsIn($this->scope->coursesFor($learner, $year))->pluck('id')->all()));
        $progress = $this->progress->byCourse($learner, $lessonIds);

        $recent = LessonProgress::query()
            ->with('lesson:id,title,course_id', 'lesson.course:id,title,subject_id', 'lesson.course.subject:id,name,color')
            ->where('student_id', $learner->id)
            ->whereIn('lesson_id', $lessonIds)
            ->latest('last_viewed_at')
            ->take(4)
            ->get();

        $bookmarks = LessonBookmark::query()
            ->with('lesson:id,title,course_id', 'lesson.course:id,title')
            ->where('student_id', $learner->id)
            ->whereIn('lesson_id', $lessonIds)
            ->latest()
            ->take(8)
            ->get();

        $joinEarly = (int) $this->settings->current('lms.join_early_minutes');
        $liveClasses = $this->scope->liveClassesFor($learner, $year)
            ->with('subject:id,name,color')
            ->where('starts_at', '>=', now()->subHours(4))
            ->orderBy('starts_at')
            ->take(6)
            ->get()
            ->filter(fn (LiveClass $liveClass): bool => $liveClass->endsAt()->isFuture())
            ->values();

        $attempts = QuizAttempt::query()->where('student_id', $learner->id)->get()->groupBy('quiz_id');
        $quizzes = $this->scope->quizzesFor($learner, $year)
            ->with('subject:id,name,color')
            ->where(fn (Builder $query) => $query->whereNull('closes_at')->orWhere('closes_at', '>', now()->subDays(14)))
            ->orderByRaw('closes_at is null, closes_at')
            ->take(8)
            ->get();

        $dueWork = Assignment::query()
            ->with('subject:id,name,color')
            ->where('academic_year_id', $year->id)
            ->where('section_id', $this->scope->enrollmentFor($learner, $year)?->section_id)
            ->where(fn (Builder $query) => $query->whereDoesntHave('students')->orWhereHas('students', fn (Builder $students) => $students->whereKey($learner->id)))
            ->whereDate('due_on', '>=', now()->toDateString())
            ->whereNotIn('id', AssignmentSubmission::query()->where('student_id', $learner->id)->select('assignment_id'))
            ->orderBy('due_on')
            ->take(5)
            ->get();

        return Inertia::render('learn/index', [
            'learner' => ['id' => $learner->id, 'name' => $learner->full_name, 'is_self' => $user->student?->id === $learner->id],
            'children' => $this->children($user),
            'courses' => $courses->map(fn (Course $course): array => [
                'id' => $course->id,
                'title' => $course->title,
                'description' => $course->description,
                'subject' => ['name' => $course->subject->name, 'color' => $course->subject->color],
                'progress' => $progress[$course->id] ?? ['completed' => 0, 'total' => 0, 'percent' => 0],
            ])->values(),
            'recent' => $recent->map(fn (LessonProgress $row): array => [
                'lesson_id' => $row->lesson_id,
                'title' => $row->lesson->title,
                'course' => $row->lesson->course->title,
                'subject_color' => $row->lesson->course->subject->color,
                'completed' => $row->completed_at !== null,
                'last_viewed_at' => $row->last_viewed_at->toIso8601String(),
            ])->values(),
            'bookmarks' => $bookmarks->map(fn (LessonBookmark $bookmark): array => [
                'lesson_id' => $bookmark->lesson_id,
                'title' => $bookmark->lesson->title,
                'course' => $bookmark->lesson->course->title,
            ])->values(),
            'liveClasses' => $liveClasses->map(fn (LiveClass $liveClass): array => [
                'id' => $liveClass->id,
                'title' => $liveClass->title,
                'subject' => ['name' => $liveClass->subject->name, 'color' => $liveClass->subject->color],
                'provider' => $liveClass->provider->label(),
                'starts_at' => $liveClass->starts_at->toIso8601String(),
                'ends_at' => $liveClass->endsAt()->toIso8601String(),
                'joinable_from' => $liveClass->starts_at->subMinutes($joinEarly)->toIso8601String(),
            ])->values(),
            'quizzes' => $quizzes->map(fn ($quiz): array => [
                'id' => $quiz->id,
                'title' => $quiz->title,
                'type' => $quiz->type->label(),
                'subject' => ['name' => $quiz->subject->name, 'color' => $quiz->subject->color],
                'closes_at' => $quiz->closes_at?->toIso8601String(),
                'is_open' => $quiz->isOpen(),
                'attempts_used' => ($attempts[$quiz->id] ?? collect())->count(),
                'max_attempts' => $quiz->max_attempts,
                'best_score' => ($attempts[$quiz->id] ?? collect())->where('status', QuizAttemptStatus::Graded)->max(fn (QuizAttempt $attempt) => (float) $attempt->score),
            ])->values(),
            'dueWork' => $dueWork->map(fn (Assignment $assignment): array => [
                'id' => $assignment->id,
                'title' => $assignment->title,
                'subject' => $assignment->subject->name,
                'due_on' => $assignment->due_on->toDateString(),
            ])->values(),
        ]);
    }

    public function course(Request $request, Course $course): Response
    {
        Gate::authorize('courses.view');
        [$learner, $year] = $this->learnerAndYear($request);

        abort_unless($this->scope->coursesFor($learner, $year)->whereKey($course->id)->exists(), 404);

        $lessons = $this->scope->lessonsIn(Course::query()->whereKey($course->id))->get()->groupBy('course_chapter_id');
        $done = LessonProgress::query()->where('student_id', $learner->id)->whereNotNull('completed_at')->pluck('lesson_id')->all();
        $course->load(['chapters', 'subject:id,name,color', 'author:id,name']);

        $visibleIds = $lessons->flatten()->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
        $progress = $this->progress->byCourse($learner, array_values($visibleIds))[$course->id] ?? ['completed' => 0, 'total' => 0, 'percent' => 0];

        return Inertia::render('learn/course', [
            'learner' => ['id' => $learner->id, 'name' => $learner->full_name, 'is_self' => $request->user()->student?->id === $learner->id],
            'course' => [
                'id' => $course->id,
                'title' => $course->title,
                'description' => $course->description,
                'subject' => ['name' => $course->subject->name, 'color' => $course->subject->color],
                'teacher' => $course->author?->name,
                'progress' => $progress,
            ],
            'chapters' => $course->chapters
                ->filter(fn ($chapter) => $lessons->has($chapter->id))
                ->map(fn ($chapter): array => [
                    'id' => $chapter->id,
                    'title' => $chapter->title,
                    'description' => $chapter->description,
                    'lessons' => $lessons[$chapter->id]->sortBy('position')->map(fn (Lesson $lesson): array => [
                        'id' => $lesson->id,
                        'title' => $lesson->title,
                        'summary' => $lesson->summary,
                        'estimated_minutes' => $lesson->estimated_minutes,
                        'completed' => in_array($lesson->id, $done, true),
                    ])->values(),
                ])->values(),
        ]);
    }

    public function lesson(Request $request, Lesson $lesson): Response
    {
        Gate::authorize('courses.view');
        [$learner, $year] = $this->learnerAndYear($request);

        abort_unless($this->scope->canViewLesson($learner, $lesson, $year), 404);

        $isSelf = $request->user()->student?->id === $learner->id;
        $progress = $isSelf ? $this->progress->recordView($learner, $lesson) : LessonProgress::query()->where('lesson_id', $lesson->id)->where('student_id', $learner->id)->first();
        $lesson->load(['materials', 'course.subject:id,name,color', 'chapter:id,title']);

        $siblings = $this->scope->lessonsIn(Course::query()->whereKey($lesson->course_id))
            ->join('course_chapters', 'course_chapters.id', '=', 'lessons.course_chapter_id')
            ->orderBy('course_chapters.position')->orderBy('lessons.position')
            ->pluck('lessons.id')->map(fn (mixed $id): int => (int) $id)->values();
        $index = $siblings->search($lesson->id);

        return Inertia::render('learn/lesson', [
            'learner' => ['id' => $learner->id, 'name' => $learner->full_name, 'is_self' => $isSelf],
            'lesson' => [
                'id' => $lesson->id,
                'title' => $lesson->title,
                'summary' => $lesson->summary,
                'body' => $lesson->body,
                'estimated_minutes' => $lesson->estimated_minutes,
                'chapter' => $lesson->chapter->title,
                'course' => ['id' => $lesson->course_id, 'title' => $lesson->course->title, 'subject' => $lesson->course->subject->name, 'color' => $lesson->course->subject->color],
                'completed' => $progress?->completed_at !== null,
                'bookmarked' => LessonBookmark::query()->where('lesson_id', $lesson->id)->where('student_id', $learner->id)->exists(),
            ],
            'materials' => $lesson->materials->map(fn (LessonMaterial $material): array => [
                'id' => $material->id,
                'kind' => $material->kind->value,
                'kind_label' => $material->kind->label(),
                'title' => $material->title,
                'description' => $material->description,
                'original_name' => $material->original_name,
                'size' => $material->size,
                'preview_url' => $material->kind === MaterialKind::Link ? null : ($material->kind->isPreviewable() ? $this->files->materialUrl($material, 'inline') : null),
                'download_url' => $material->kind === MaterialKind::Link ? null : ($material->is_downloadable ? $this->files->materialUrl($material, 'download') : null),
                'link_url' => $material->kind === MaterialKind::Link ? $material->url : null,
            ])->values(),
            'previousId' => $index !== false && $index > 0 ? $siblings[$index - 1] : null,
            'nextId' => $index !== false && $index < $siblings->count() - 1 ? $siblings[$index + 1] : null,
        ]);
    }

    public function complete(Request $request, Lesson $lesson): RedirectResponse
    {
        $student = $this->selfLearner($request, $lesson);
        $completed = $request->validate(['completed' => ['required', 'boolean']])['completed'];

        $this->progress->setCompleted($student, $lesson, (bool) $completed);

        return back();
    }

    public function bookmark(Request $request, Lesson $lesson): RedirectResponse
    {
        $student = $this->selfLearner($request, $lesson);
        $this->progress->toggleBookmark($student, $lesson);

        return back();
    }

    /**
     * Opens the meeting for an eligible student close to the start time. The link itself
     * never reaches the page, so it cannot be shared ahead of time from the portal.
     */
    public function join(Request $request, LiveClass $liveClass): RedirectResponse
    {
        Gate::authorize('live_classes.view');
        $student = $request->user()->student;
        $year = AcademicYear::query()->current()->first();

        abort_if($student === null || $year === null || $request->user()->employee()->exists(), 403);
        abort_unless($this->scope->liveClassesFor($student, $year)->whereKey($liveClass->id)->exists(), 404);

        $opensAt = $liveClass->starts_at->subMinutes((int) $this->settings->current('lms.join_early_minutes'));

        if (now()->lt($opensAt) || now()->gt($liveClass->endsAt())) {
            Inertia::flash('toast', ['type' => 'info', 'message' => now()->lt($opensAt)
                ? __('You can join from :time.', ['time' => $opensAt->format('H:i')])
                : __('This live class has ended.')]);

            return back();
        }

        Log::info('Live class joined.', ['live_class_id' => $liveClass->id, 'student_id' => $student->id]);

        return redirect()->away($liveClass->meeting_url);
    }

    /**
     * @return array{0: Student, 1: AcademicYear}
     */
    private function learnerAndYear(Request $request): array
    {
        $learner = $this->scope->learner($request->user(), $request->integer('student') ?: null);
        $year = AcademicYear::query()->current()->first();

        abort_if($learner === null || $year === null, 404);

        return [$learner, $year];
    }

    private function selfLearner(Request $request, Lesson $lesson): Student
    {
        Gate::authorize('courses.view');
        $student = $request->user()->student;
        $year = AcademicYear::query()->current()->first();

        abort_if($student === null || $year === null || $request->user()->employee()->exists(), 403);
        abort_unless($this->scope->canViewLesson($student, $lesson, $year), 404);

        return $student;
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function children(User $user): array
    {
        if ($user->guardian === null) {
            return [];
        }

        return array_values($user->guardian->students()->get()->map(fn (Student $student): array => ['id' => $student->id, 'name' => $student->full_name])->all());
    }
}
