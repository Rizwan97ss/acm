<?php

namespace App\Notifications;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * In-app notice of a new message. The message text is deliberately not copied into
 * the (unencrypted) notifications table.
 */
class NewMessageReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Conversation $conversation,
        public User $sender,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array{kind: string, title: string, body: string, url: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'message',
            'title' => "New message from {$this->sender->name}",
            'body' => $this->conversation->subject,
            'url' => route('communication.messages.show', $this->conversation, false),
        ];
    }
}
