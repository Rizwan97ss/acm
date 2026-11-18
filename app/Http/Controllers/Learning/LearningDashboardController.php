<?php

namespace App\Http\Controllers\Learning;

use App\Enums\LiveClassStatus;
use App\Enums\PublishStatus;
use App\Enums\QuizAttemptStatus;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonMaterial;
use App\Models\LessonProgress;
use App\Models\LiveClass;
use App\Models\QuizAttempt;
use App\Services\Learning\LearningFiles;
use App\Services\Learning\LearningInsights;
use App\Services\Learning\LearningScope;
use App\Services\Tenancy\CurrentSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Teacher and administrator overview of online learning: reach, completion, quizzes to
 * mark, upcoming live classes and student engagement (computed in Python).
 */
class LearningDashboardController extends LearningController
{
    public function __construct(
        LearningScope $scope,
        private LearningInsights $insights,
        private LearningFiles $files,
    ) {
        parent::__construct($scope);
    }

    public function __invoke(Request $request): Response
    {
        Gate::authorize('courses.create');

        $user = $request->user();
        $year = $this->currentYear();
        $sectionIds = $this->insights->sectionIds($user, $year);
        $supervisor = $this->scope->supervises($user, 'courses');
        $schoolId = (int) app(CurrentSchool::class)->id();

        $courses = Course::query()->where('academic_year_id', $year->id)
            ->when(! $supervisor, fn (Builder $query) => $query->where('created_by', $user->id));

        return Inertia::render('learning/dashboard', [
            'isSupervisor' => $supervisor,
            'stats' => [
                'courses' => (clone $courses)->count(),
                'published_courses' => (clone $courses)->where('status', PublishStatus::Published)->count(),
                'lessons' => Lesson::query()->whereIn('course_id', (clone $courses)->select('id'))->where('status', PublishStatus::Published)->count(),
                'materials' => LessonMaterial::query()->whereIn('lesson_id', Lesson::query()->whereIn('course_id', (clone $courses)->select('id'))->select('id'))->count(),
                'active_learners' => LessonProgress::query()->where('last_viewed_at', '>=', now()->subDays(7))
                    ->whereIn('lesson_id', Lesson::query()->whereIn('course_id', (clone $courses)->select('id'))->select('id'))
                    ->distinct()->count('student_id'),
                'to_mark' => QuizAttempt::query()->where('status', QuizAttemptStatus::Submitted)
                    ->whereHas('quiz', fn (Builder $query) => $query->where('academic_year_id', $year->id)->when(! $supervisor, fn (Builder $query) => $query->where('created_by', $user->id)))
                    ->count(),
            ],
            'storage' => $supervisor ? ['used' => $this->files->usage($schoolId), 'quota' => $this->files->quota()] : null,
            'liveClasses' => LiveClass::query()
                ->with(['subject:id,name,color', 'schoolClass:id,name', 'section:id,name'])
                ->where('academic_year_id', $year->id)
                ->where('status', LiveClassStatus::Scheduled)
                ->where('starts_at', '>=', now()->subHour())
                ->when(! $supervisor, fn (Builder $query) => $query->where('created_by', $user->id))
                ->orderBy('starts_at')
                ->take(5)
                ->get()
                ->map(fn (LiveClass $liveClass): array => [
                    'id' => $liveClass->id,
                    'title' => $liveClass->title,
                    'subject' => $liveClass->subject->name,
                    'color' => $liveClass->subject->color,
                    'audience' => $this->audienceLabel($liveClass),
                    'starts_at' => $liveClass->starts_at->toIso8601String(),
                    'provider' => $liveClass->provider->label(),
                ])->values(),
            'courseProgress' => Inertia::defer(fn (): array => $this->insights->courses($year, $sectionIds)),
            'quizResults' => Inertia::defer(fn (): array => $this->insights->quizzes($year, $sectionIds)),
            'engagement' => Inertia::defer(fn (): array => $this->insights->engagement($year, $sectionIds), 'analytics'),
        ]);
    }
}
