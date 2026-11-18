<?php

namespace App\Console\Commands;

use App\Enums\LiveClassStatus;
use App\Enums\PublishStatus;
use App\Models\Lesson;
use App\Models\LiveClass;
use App\Models\Quiz;
use App\Models\School;
use App\Services\Learning\LearningNotifier;
use App\Services\Settings\SettingsService;
use App\Services\Tenancy\CurrentSchool;
use Illuminate\Console\Command;

/**
 * Runs every few minutes (see routes/console.php): announces lessons and quizzes whose
 * scheduled publish time has arrived and sends live class reminders. Each school is
 * processed in its own tenant context so settings and data never mix.
 */
class DispatchLearningUpdates extends Command
{
    protected $signature = 'lms:dispatch';

    protected $description = 'Announce scheduled lessons and quizzes and send live class reminders';

    public function handle(CurrentSchool $currentSchool, LearningNotifier $notifier, SettingsService $settings): int
    {
        $lessons = 0;
        $quizzes = 0;
        $reminders = 0;

        foreach (School::query()->where('is_active', true)->get() as $school) {
            $currentSchool->set($school);

            if (! $settings->current('lms.enabled')) {
                continue;
            }

            Lesson::query()
                ->where('status', PublishStatus::Published)
                ->whereNull('notified_at')
                ->where('publish_at', '<=', now())
                ->with('course')
                ->each(function (Lesson $lesson) use ($notifier, &$lessons): void {
                    $lessons += $notifier->lessonPublished($lesson) > 0 ? 1 : 0;
                });

            Quiz::query()
                ->where('status', PublishStatus::Published)
                ->whereNull('notified_at')
                ->where(fn ($query) => $query->whereNull('opens_at')->orWhere('opens_at', '<=', now()))
                ->each(function (Quiz $quiz) use ($notifier, &$quizzes): void {
                    $quizzes += $notifier->quizPublished($quiz) > 0 ? 1 : 0;
                });

            LiveClass::query()
                ->where('status', LiveClassStatus::Scheduled)
                ->whereNull('reminded_at')
                ->whereBetween('starts_at', [now(), now()->addDay()])
                ->each(function (LiveClass $liveClass) use ($notifier, &$reminders): void {
                    $reminders += $notifier->liveClassReminder($liveClass) ? 1 : 0;
                });
        }

        $currentSchool->set(null);

        $this->info("Announced {$lessons} lessons and {$quizzes} quizzes; sent {$reminders} live class reminders.");

        return self::SUCCESS;
    }
}
