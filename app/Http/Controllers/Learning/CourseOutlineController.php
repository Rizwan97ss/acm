<?php

namespace App\Http\Controllers\Learning;

use App\Enums\MaterialKind;
use App\Enums\PublishStatus;
use App\Http\Requests\Learning\LessonMaterialRequest;
use App\Http\Requests\Learning\LessonRequest;
use App\Models\Course;
use App\Models\CourseChapter;
use App\Models\Lesson;
use App\Models\LessonMaterial;
use App\Models\User;
use App\Services\Learning\LearningFiles;
use App\Services\Learning\LearningNotifier;
use App\Services\Learning\LearningScope;
use App\Services\Settings\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Building a course: chapters, lessons and the materials inside each lesson.
 */
class CourseOutlineController extends LearningController
{
    public function __construct(
        LearningScope $scope,
        private LearningFiles $files,
        private LearningNotifier $notifier,
        private SettingsService $settings,
    ) {
        parent::__construct($scope);
    }

    /* ---------------------------------------------------------------- chapters */

    public function storeChapter(Request $request, Course $course): RedirectResponse
    {
        $this->authorizeCourse($request->user(), $course);
        $validated = $request->validate(['title' => ['required', 'string', 'max:150'], 'description' => ['nullable', 'string', 'max:2000']]);

        $course->chapters()->create([...$validated, 'school_id' => $course->school_id, 'position' => (int) $course->chapters()->max('position') + 1]);

        return back();
    }

    public function updateChapter(Request $request, CourseChapter $chapter): RedirectResponse
    {
        $this->authorizeCourse($request->user(), $chapter->course);
        $chapter->update($request->validate(['title' => ['required', 'string', 'max:150'], 'description' => ['nullable', 'string', 'max:2000']]));

        return back();
    }

    public function destroyChapter(Request $request, CourseChapter $chapter): RedirectResponse
    {
        $this->authorizeCourse($request->user(), $chapter->course);

        if ($chapter->lessons()->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Move or delete the lessons in this chapter first.')]);

            return back();
        }

        $chapter->delete();

        return back();
    }

    public function moveChapter(Request $request, CourseChapter $chapter): RedirectResponse
    {
        $this->authorizeCourse($request->user(), $chapter->course);
        $direction = $request->validate(['direction' => ['required', 'in:up,down']])['direction'];
        $this->move($chapter, CourseChapter::query()->where('course_id', $chapter->course_id), $direction);

        return back();
    }

    /* ---------------------------------------------------------------- lessons */

    public function storeLesson(Request $request, CourseChapter $chapter): RedirectResponse
    {
        $this->authorizeCourse($request->user(), $chapter->course);
        $validated = $request->validate(['title' => ['required', 'string', 'max:150']]);

        $lesson = Lesson::query()->create([
            'school_id' => $chapter->school_id,
            'course_id' => $chapter->course_id,
            'course_chapter_id' => $chapter->id,
            'title' => $validated['title'],
            'status' => PublishStatus::Draft,
            'position' => (int) Lesson::query()->where('course_chapter_id', $chapter->id)->max('position') + 1,
            'created_by' => $request->user()->id,
        ]);

        return to_route('learning.lessons.edit', $lesson);
    }

    public function editLesson(Request $request, Lesson $lesson): Response
    {
        $this->authorizeCourse($request->user(), $lesson->course);
        $lesson->load(['course.subject:id,name', 'chapter:id,title', 'materials']);

        return Inertia::render('learning/lessons/edit', [
            'lesson' => [
                'id' => $lesson->id,
                'title' => $lesson->title,
                'summary' => $lesson->summary,
                'body' => $lesson->body,
                'estimated_minutes' => $lesson->estimated_minutes,
                'status' => $lesson->status->value,
                'publish_at' => $lesson->publish_at?->toIso8601String(),
                'chapter' => $lesson->chapter->title,
                'course' => ['id' => $lesson->course_id, 'title' => $lesson->course->title, 'subject' => $lesson->course->subject->name],
            ],
            'materials' => $lesson->materials->map(fn (LessonMaterial $material): array => $this->presentMaterial($material))->values(),
            'limits' => [
                'max_upload_mb' => (int) $this->settings->current('lms.max_upload_mb'),
                'extensions' => array_keys((array) config('lms.extensions')),
                'used_bytes' => $this->files->usage($lesson->school_id),
                'quota_bytes' => $this->files->quota(),
                'allow_downloads' => (bool) $this->settings->current('lms.allow_downloads'),
            ],
        ]);
    }

    public function updateLesson(LessonRequest $request, Lesson $lesson): RedirectResponse
    {
        $this->authorizeCourse($request->user(), $lesson->course);
        $lesson->update($request->validated());

        $notified = $this->notifier->lessonPublished($lesson->refresh());

        Inertia::flash('toast', ['type' => 'success', 'message' => $notified > 0
            ? trans_choice('Lesson published. :count student was notified.|Lesson published. :count students were notified.', $notified, ['count' => $notified])
            : __('Lesson saved.')]);

        return back();
    }

    public function destroyLesson(Request $request, Lesson $lesson): RedirectResponse
    {
        $this->authorizeCourse($request->user(), $lesson->course);
        $materials = $lesson->materials()->get(['disk', 'path']);
        $courseId = $lesson->course_id;

        $lesson->delete();
        $materials->each(fn (LessonMaterial $material) => $this->files->delete($material->disk, $material->path));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Lesson deleted.')]);

        return to_route('learning.courses.show', $courseId);
    }

    public function moveLesson(Request $request, Lesson $lesson): RedirectResponse
    {
        $this->authorizeCourse($request->user(), $lesson->course);
        $direction = $request->validate(['direction' => ['required', 'in:up,down']])['direction'];
        $this->move($lesson, Lesson::query()->where('course_chapter_id', $lesson->course_chapter_id), $direction);

        return back();
    }

    /* -------------------------------------------------------------- materials */

    public function storeMaterial(LessonMaterialRequest $request, Lesson $lesson): RedirectResponse
    {
        $this->authorizeCourse($request->user(), $lesson->course);

        $attributes = [
            'school_id' => $lesson->school_id,
            'lesson_id' => $lesson->id,
            'title' => $request->validated('title'),
            'description' => $request->validated('description'),
            'is_downloadable' => $request->boolean('is_downloadable', (bool) $this->settings->current('lms.allow_downloads')),
            'position' => (int) $lesson->materials()->max('position') + 1,
            'uploaded_by' => $request->user()->id,
        ];

        if ($request->hasFile('file')) {
            $stored = $this->files->store($request->file('file'), $lesson->school_id, "lessons/{$lesson->id}");
            $attributes = [...$attributes, ...$stored, 'kind' => $stored['kind']];
        } else {
            $attributes = [...$attributes, 'kind' => MaterialKind::Link, 'url' => $request->validated('url')];
        }

        $material = LessonMaterial::query()->create($attributes);

        Log::info('Lesson material added.', ['material_id' => $material->id, 'kind' => $material->kind->value, 'size' => $material->size]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Material added.')]);

        return back();
    }

    public function updateMaterial(Request $request, LessonMaterial $material): RedirectResponse
    {
        $this->authorizeCourse($request->user(), $material->lesson->course);

        $material->update($request->validate([
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_downloadable' => ['boolean'],
        ]));

        return back();
    }

    public function destroyMaterial(Request $request, LessonMaterial $material): RedirectResponse
    {
        $this->authorizeCourse($request->user(), $material->lesson->course);

        $material->delete();
        $this->files->delete($material->disk, $material->path);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Material removed.')]);

        return back();
    }

    public function moveMaterial(Request $request, LessonMaterial $material): RedirectResponse
    {
        $this->authorizeCourse($request->user(), $material->lesson->course);
        $direction = $request->validate(['direction' => ['required', 'in:up,down']])['direction'];
        $this->move($material, LessonMaterial::query()->where('lesson_id', $material->lesson_id), $direction);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function presentMaterial(LessonMaterial $material): array
    {
        return [
            'id' => $material->id,
            'kind' => $material->kind->value,
            'kind_label' => $material->kind->label(),
            'title' => $material->title,
            'description' => $material->description,
            'url' => $material->kind === MaterialKind::Link ? $material->url : $this->files->materialUrl($material, $material->kind->isPreviewable() ? 'inline' : 'download'),
            'original_name' => $material->original_name,
            'size' => $material->size,
            'is_downloadable' => $material->is_downloadable,
        ];
    }

    private function authorizeCourse(User $user, Course $course): void
    {
        abort_unless($this->scope->canManage($user, 'courses', $course), 403);
    }
}
