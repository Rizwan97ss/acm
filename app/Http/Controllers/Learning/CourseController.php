<?php

namespace App\Http\Controllers\Learning;

use App\Enums\PublishStatus;
use App\Http\Requests\Learning\CourseRequest;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LessonMaterial;
use App\Models\LessonProgress;
use App\Models\User;
use App\Services\Learning\LearningFiles;
use App\Services\Learning\LearningNotifier;
use App\Services\Learning\LearningScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Teachers build courses (chapters → lessons → materials) for the classes and subjects
 * they teach; supervisors (courses.approve) see every course in the school.
 */
class CourseController extends LearningController
{
    public function __construct(
        LearningScope $scope,
        private LearningFiles $files,
        private LearningNotifier $notifier,
    ) {
        parent::__construct($scope);
    }

    public function index(Request $request): Response
    {
        Gate::authorize('courses.create');

        $user = $request->user();
        $year = $this->currentYear();
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'school_class_id' => ['nullable', 'integer'],
            'subject_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'in:draft,published'],
        ]);

        $courses = $this->manageable($user, $year->id)
            ->with(['schoolClass:id,name', 'section:id,name', 'subject:id,name,color', 'author:id,name'])
            ->withCount(['chapters', 'lessons', 'lessons as published_lessons_count' => fn (Builder $query) => $query->where('status', PublishStatus::Published)])
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where('title', 'like', '%'.addcslashes($search, '%_\\').'%'))
            ->when($filters['school_class_id'] ?? null, fn (Builder $query, int $id) => $query->where('school_class_id', $id))
            ->when($filters['subject_id'] ?? null, fn (Builder $query, int $id) => $query->where('subject_id', $id))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->latest('updated_at')
            ->paginate(18)
            ->withQueryString();

        return Inertia::render('learning/courses/index', [
            'courses' => [
                'data' => $courses->getCollection()->map(fn (Course $course): array => [
                    ...$this->present($course),
                    'chapters_count' => (int) $course->getAttribute('chapters_count'),
                    'lessons_count' => (int) $course->getAttribute('lessons_count'),
                    'published_lessons_count' => (int) $course->getAttribute('published_lessons_count'),
                ])->values(),
                'meta' => $this->meta($courses),
            ],
            'filters' => $filters,
            'options' => $this->scope->teachingOptions($user, 'courses', $year),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('courses.create');

        return Inertia::render('learning/courses/form', [
            'course' => null,
            'options' => $this->scope->teachingOptions($request->user(), 'courses', $this->currentYear()),
        ]);
    }

    public function store(CourseRequest $request): RedirectResponse
    {
        $year = $this->currentYear();
        $this->authorizeAudience($request, $year->id);

        $course = DB::transaction(function () use ($request, $year): Course {
            $course = Course::query()->create([
                ...$request->safe()->except('student_ids'),
                'academic_year_id' => $year->id,
                'created_by' => $request->user()->id,
            ]);
            $course->students()->sync($this->chosenStudents($request, $year->id));
            $course->chapters()->create(['school_id' => $course->school_id, 'title' => __('Chapter 1'), 'position' => 1]);

            return $course;
        });

        Log::info('Course created.', ['course_id' => $course->id, 'user_id' => $request->user()->id]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Course created. Add chapters and lessons below.')]);

        return to_route('learning.courses.show', $course);
    }

    public function show(Request $request, Course $course): Response
    {
        $this->authorizeManage($request->user(), $course);
        $course->load(['chapters.lessons' => fn ($query) => $query->withCount('materials'), 'subject:id,name,color', 'students:id']);

        $audience = $this->audienceSize($course);
        $completed = LessonProgress::query()
            ->whereIn('lesson_id', $course->lessons()->select('id'))
            ->whereNotNull('completed_at')
            ->selectRaw('lesson_id, count(*) as total')
            ->groupBy('lesson_id')
            ->pluck('total', 'lesson_id');

        return Inertia::render('learning/courses/show', [
            'course' => [
                ...$this->present($course),
                'student_ids' => $course->students->pluck('id'),
                'audience_size' => $audience,
            ],
            'chapters' => $course->chapters->map(fn ($chapter): array => [
                'id' => $chapter->id,
                'title' => $chapter->title,
                'description' => $chapter->description,
                'lessons' => $chapter->lessons->map(fn ($lesson): array => [
                    'id' => $lesson->id,
                    'title' => $lesson->title,
                    'status' => $lesson->status->value,
                    'publish_at' => $lesson->publish_at?->toIso8601String(),
                    'estimated_minutes' => $lesson->estimated_minutes,
                    'materials_count' => (int) $lesson->getAttribute('materials_count'),
                    'completed_count' => (int) ($completed[$lesson->id] ?? 0),
                ])->values(),
            ])->values(),
        ]);
    }

    public function edit(Request $request, Course $course): Response
    {
        $this->authorizeManage($request->user(), $course);

        return Inertia::render('learning/courses/form', [
            'course' => [...$this->present($course), 'student_ids' => $course->students()->pluck('students.id')],
            'options' => $this->scope->teachingOptions($request->user(), 'courses', $this->currentYear()),
        ]);
    }

    public function update(CourseRequest $request, Course $course): RedirectResponse
    {
        $this->authorizeManage($request->user(), $course);
        $this->authorizeAudience($request, $course->academic_year_id);
        $wasPublished = $course->status === PublishStatus::Published;

        DB::transaction(function () use ($request, $course): void {
            $course->update($request->safe()->except('student_ids'));
            $course->students()->sync($this->chosenStudents($request, $course->academic_year_id));
        });

        if (! $wasPublished && $course->refresh()->status === PublishStatus::Published) {
            $this->notifier->coursePublished($course);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Course saved.')]);

        return to_route('learning.courses.show', $course);
    }

    public function destroy(Request $request, Course $course): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('courses.delete') && $this->scope->canManage($request->user(), 'courses', $course), 403);

        $materials = LessonMaterial::query()->whereIn('lesson_id', $course->lessons()->select('id'))->get(['disk', 'path']);
        $course->delete();
        $materials->each(fn (LessonMaterial $material) => $this->files->delete($material->disk, $material->path));

        Log::info('Course deleted.', ['course_id' => $course->id, 'files' => $materials->count()]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Course deleted.')]);

        return to_route('learning.courses.index');
    }

    /**
     * Courses the user may manage this year.
     *
     * @return Builder<Course>
     */
    private function manageable(User $user, int $yearId): Builder
    {
        $query = Course::query()->where('academic_year_id', $yearId);

        if ($this->scope->supervises($user, 'courses')) {
            return $query;
        }

        $year = $this->currentYear();
        $pairs = collect($this->scope->teachingOptions($user, 'courses', $year))
            ->flatMap(fn (array $class) => collect($class['subjects'])->map(fn (array $subject): array => [$class['id'], $subject['id']]));

        return $query->where(function (Builder $query) use ($user, $pairs): void {
            $query->where('created_by', $user->id);

            foreach ($pairs as [$classId, $subjectId]) {
                $query->orWhere(fn (Builder $query) => $query->where('school_class_id', $classId)->where('subject_id', $subjectId));
            }
        });
    }

    private function authorizeManage(User $user, Course $course): void
    {
        abort_unless($this->scope->canManage($user, 'courses', $course), 403);
    }

    private function authorizeAudience(CourseRequest $request, int $yearId): void
    {
        $year = $this->currentYear();
        abort_unless($year->id === $yearId || $this->scope->supervises($request->user(), 'courses'), 403);

        $allowed = $this->scope->canTeach(
            $request->user(),
            'courses',
            $request->integer('school_class_id'),
            $request->filled('section_id') ? $request->integer('section_id') : null,
            $request->integer('subject_id'),
            $year,
        );

        if (! $allowed) {
            throw ValidationException::withMessages(['subject_id' => __('You can only create courses for classes and subjects you teach.')]);
        }
    }

    /**
     * Chosen students must be enrolled in the course's class (and section, when set).
     *
     * @return list<int>
     */
    private function chosenStudents(CourseRequest $request, int $yearId): array
    {
        $ids = array_values(array_map('intval', (array) $request->validated('student_ids', [])));

        if ($ids === []) {
            return [];
        }

        $eligible = collect($this->scope->rosterFor($yearId, $request->integer('school_class_id'), $request->filled('section_id') ? $request->integer('section_id') : null))->pluck('id')->all();

        if (array_diff($ids, $eligible) !== []) {
            throw ValidationException::withMessages(['student_ids' => __('Some chosen students are not in this class or section.')]);
        }

        return $ids;
    }

    private function audienceSize(Course $course): int
    {
        if ($course->students->isNotEmpty()) {
            return $course->students->count();
        }

        return Enrollment::query()
            ->where('academic_year_id', $course->academic_year_id)
            ->whereHas('section', fn (Builder $query) => $query->where('school_class_id', $course->school_class_id))
            ->when($course->section_id !== null, fn (Builder $query) => $query->where('section_id', $course->section_id))
            ->count();
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Course $course): array
    {
        $course->loadMissing(['subject:id,name,color', 'author:id,name']);

        return [
            'id' => $course->id,
            'title' => $course->title,
            'description' => $course->description,
            'status' => $course->status->value,
            'publish_at' => $course->publish_at?->toIso8601String(),
            'school_class_id' => $course->school_class_id,
            'section_id' => $course->section_id,
            'subject_id' => $course->subject_id,
            'subject' => ['name' => $course->subject->name, 'color' => $course->subject->color],
            'audience' => $this->audienceLabel($course),
            'author' => $course->author?->name,
            'updated_at' => $course->updated_at?->toIso8601String(),
        ];
    }
}
