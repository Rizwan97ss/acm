<?php

namespace App\Notifications;

use App\Models\Student;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to a student's emergency-contact guardians when the student is marked absent.
 */
class StudentAbsentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Student $student,
        public CarbonImmutable $date,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__(':name was absent today', ['name' => $this->student->first_name]))
            ->greeting(__('Hello,'))
            ->line(__(':name was marked absent on :date.', [
                'name' => $this->student->full_name,
                'date' => $this->date->toFormattedDateString(),
            ]))
            ->line(__('If you were not expecting this, please contact the school office.'));
    }
}
