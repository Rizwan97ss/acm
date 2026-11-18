<?php

namespace App\Services\Learning;

use App\Enums\LiveClassStatus;
use App\Enums\PublishStatus;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LiveClass;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Notifications\LearningUpdate;
use App\Services\Settings\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Tells students about new learning content, upcoming live classes and marked work.
 * Content published for a later time is announced by `lms:dispatch` once that time
 * arrives; each item is announced only once.
 */
class LearningNotifier
{
    public function __construct(
        private LearningScope $scope,
        private SettingsService $settings,
    ) {}

    /**
     * One notice for a newly published course; its lessons already published are then
     * treated as announced so students are not flooded.
     */
    public function coursePublished(Course $course): int
    {
        if (! $this->isLive($course->status, $course->publish_at)) {
            return 0;
        }

        $course->loadMissing('subject:id,name');
        $recipients = $this->audience($course);

        $this->send($recipients, new LearningUpdate(
            'lesson',
            __('New course: :title', ['title' => $course->title]),
            $course->subject->name,
            route('learn.courses.show', $course, false),
        ));

        $course->lessons()->where('status', PublishStatus::Published)->whereNull('notified_at')->update(['notified_at' => now()]);

        return $recipients->count();
    }

    public function lessonPublished(Lesson $lesson): int
    {
        $lesson->loadMissing('course.subject:id,name');
        $course = $lesson->course;

        if ($lesson->notified_at !== null || ! $this->isLive($lesson->status, $lesson->publish_at) || ! $this->isLive($course->status, $course->publish_at)) {
            return 0;
        }

        $recipients = $this->audience($course);

        $this->send($recipients, new LearningUpdate(
            'lesson',
            __('New lesson: :title', ['title' => $lesson->title]),
            __(':subject · :course', ['subject' => $course->subject->name, 'course' => $course->title]),
            route('learn.lessons.show', $lesson, false),
        ));

        $lesson->forceFill(['notified_at' => now()])->saveQuietly();

        return $recipients->count();
    }

    public function quizPublished(Quiz $quiz): int
    {
        if ($quiz->notified_at !== null || $quiz->status !== PublishStatus::Published) {
            return 0;
        }

        $recipients = $this->scope->studentUsers($quiz->academic_year_id, $quiz->school_class_id, $quiz->section_id);

        $this->send($recipients, new LearningUpdate(
            'quiz',
            __('New :type: :title', ['type' => Str::lower($quiz->type->label()), 'title' => $quiz->title]),
            $quiz->closes_at !== null ? __('Closes :date', ['date' => $quiz->closes_at->toDayDateTimeString()]) : __('Open now'),
            route('learn.quizzes.show', $quiz, false),
        ));

        $quiz->forceFill(['notified_at' => now()])->saveQuietly();

        return $recipients->count();
    }

    public function liveClassScheduled(LiveClass $liveClass, bool $changed = false): int
    {
        $recipients = $this->scope->studentUsers($liveClass->academic_year_id, $liveClass->school_class_id, $liveClass->section_id);

        $title = $changed
            ? __('Live class updated: :title', ['title' => $liveClass->title])
            : __('Live class scheduled: :title', ['title' => $liveClass->title]);

        $this->send($recipients, new LearningUpdate(
            'live_class',
            $title,
            $liveClass->starts_at->toDayDateTimeString().' · '.$liveClass->provider->label(),
            route('learn.index', [], false),
        ));

        return $recipients->count();
    }

    public function liveClassCancelled(LiveClass $liveClass): int
    {
        $recipients = $this->scope->studentUsers($liveClass->academic_year_id, $liveClass->school_class_id, $liveClass->section_id);

        $this->send($recipients, new LearningUpdate(
            'live_class',
            __('Live class cancelled: :title', ['title' => $liveClass->title]),
            __('It was planned for :date.', ['date' => $liveClass->starts_at->toDayDateTimeString()]),
            route('learn.index', [], false),
        ));

        return $recipients->count();
    }

    /**
     * Remind students shortly before a live class starts. Returns false when it is not yet
     * time, reminders are off, or the reminder was already sent.
     */
    public function liveClassReminder(LiveClass $liveClass): bool
    {
        $minutes = (int) $this->settings->current('lms.live_class_reminder_minutes');

        if ($minutes <= 0 || $liveClass->reminded_at !== null || $liveClass->status !== LiveClassStatus::Scheduled
            || $liveClass->starts_at->isPast() || $liveClass->starts_at->gt(now()->addMinutes($minutes))) {
            return false;
        }

        $this->send($this->scope->studentUsers($liveClass->academic_year_id, $liveClass->school_class_id, $liveClass->section_id), new LearningUpdate(
            'live_class',
            __('Starting soon: :title', ['title' => $liveClass->title]),
            __('Starts at :time. Join from your learning page.', ['time' => $liveClass->starts_at->format('H:i')]),
            route('learn.index', [], false),
        ));

        $liveClass->forceFill(['reminded_at' => now()])->saveQuietly();

        return true;
    }

    public function quizGraded(QuizAttempt $attempt): void
    {
        $attempt->loadMissing(['quiz', 'student.user']);
        $user = $attempt->student->user;

        if ($user === null || ! $attempt->quiz->show_results) {
            return;
        }

        $user->notify(new LearningUpdate(
            'quiz',
            __('Marked: :title', ['title' => $attempt->quiz->title]),
            __('You scored :score out of :max.', ['score' => (float) $attempt->score, 'max' => (float) $attempt->max_score]),
            route('learn.quizzes.show', $attempt->quiz_id, false),
        ));
    }

    public function assignmentGraded(User $student, string $title, int $assignmentId): void
    {
        $student->notify(new LearningUpdate(
            'assignment',
            __('Feedback on :title', ['title' => $title]),
            __('Your teacher has marked your work.'),
            route('academics.assignments.show', $assignmentId, false),
        ));
    }

    /**
     * @return Collection<int, User>
     */
    private function audience(Course $course): Collection
    {
        $chosen = array_values($course->students()->pluck('students.id')->map(fn (mixed $id): int => (int) $id)->all());

        return $this->scope->studentUsers($course->academic_year_id, $course->school_class_id, $course->section_id, $chosen === [] ? null : $chosen);
    }

    /**
     * @param  Collection<int, User>  $recipients
     */
    private function send(Collection $recipients, LearningUpdate $notification): void
    {
        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, $notification);

        Log::info('Learning notification sent.', ['kind' => $notification->kind, 'recipients' => $recipients->count()]);
    }

    private function isLive(PublishStatus $status, ?CarbonImmutable $publishAt): bool
    {
        return $status === PublishStatus::Published && ($publishAt === null || $publishAt->lte(now()));
    }
}
