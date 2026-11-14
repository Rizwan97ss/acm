<?php

namespace App\Services\Communication;

use App\Models\Announcement;
use App\Models\User;
use App\Notifications\AnnouncementPublished;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class AnnouncementService
{
    public function __construct(
        private AudienceResolver $audiences,
        private AuditLogger $audit,
    ) {}

    /**
     * Publishes the announcement and notifies its audience (except the publisher).
     * Returns how many people were notified.
     */
    public function publish(Announcement $announcement, User $publisher): int
    {
        DB::transaction(function () use ($announcement, $publisher): void {
            $announcement->forceFill(['published_at' => now(), 'published_by' => $publisher->id])->save();
            $this->audit->log('announcement.published', $announcement, [], ['audience' => $announcement->audience->value]);
        });

        $recipients = $this->audiences->recipients($announcement->audience, $announcement->school_class_id)
            ->whereKeyNot($publisher->id)
            ->get();

        Notification::send($recipients, new AnnouncementPublished($announcement));

        return $recipients->count();
    }

    public function unpublish(Announcement $announcement): void
    {
        $announcement->forceFill(['published_at' => null, 'published_by' => null])->save();
        $this->audit->log('announcement.unpublished', $announcement);
    }
}
