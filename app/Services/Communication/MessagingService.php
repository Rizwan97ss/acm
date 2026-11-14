<?php

namespace App\Services\Communication;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Notifications\NewMessageReceived;
use App\Services\People\PortalScope;
use App\Services\Tenancy\CurrentSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Private messaging. Who may write to whom:
 *  - staff may write to staff and to guardians;
 *  - guardians may write to staff;
 *  - students cannot start or join conversations (safeguarding).
 */
class MessagingService
{
    public function __construct(
        private PortalScope $portalScope,
        private CurrentSchool $currentSchool,
    ) {}

    /**
     * Active users the sender may start a conversation with.
     *
     * @return Builder<User>
     */
    public function contactsFor(User $sender): Builder
    {
        $isStaff = ! $this->portalScope->isPortalUser($sender);

        return User::query()
            ->where('school_id', $this->currentSchool->id())
            ->where('is_active', true)
            ->whereKeyNot($sender->id)
            ->where(function (Builder $query) use ($isStaff): void {
                $query->whereHas('employee')
                    ->orWhere(fn (Builder $query) => $query->whereDoesntHave('student')->whereDoesntHave('guardian'));

                if ($isStaff) {
                    $query->orWhere(fn (Builder $query) => $query->whereHas('guardian')->whereDoesntHave('student'));
                }
            });
    }

    /**
     * @param  list<int>  $recipientIds
     */
    public function start(User $sender, array $recipientIds, string $subject, string $body): Conversation
    {
        return DB::transaction(function () use ($sender, $recipientIds, $subject, $body): Conversation {
            $conversation = Conversation::query()->create([
                'school_id' => $this->currentSchool->id(),
                'subject' => $subject,
                'created_by' => $sender->id,
            ]);

            $conversation->participants()->attach([$sender->id, ...$recipientIds]);
            $this->post($conversation, $sender, $body);

            return $conversation;
        });
    }

    public function post(Conversation $conversation, User $sender, string $body): Message
    {
        $message = $conversation->messages()->create(['user_id' => $sender->id, 'body' => $body]);
        $conversation->forceFill(['last_message_at' => $message->created_at])->save();
        $conversation->participants()->updateExistingPivot($sender->id, ['last_read_at' => now()]);

        $others = $conversation->participants()->whereKeyNot($sender->id)->get();
        Notification::send($others, new NewMessageReceived($conversation, $sender));

        return $message;
    }

    public function markRead(Conversation $conversation, User $user): void
    {
        $conversation->participants()->updateExistingPivot($user->id, ['last_read_at' => now()]);
    }

    /**
     * Conversations with messages the user has not read yet.
     */
    public function unreadCount(User $user): int
    {
        return Conversation::query()
            ->whereHas('participants', fn (Builder $query) => $query
                ->whereKey($user->id)
                ->where(fn (Builder $query) => $query->whereNull('conversation_participants.last_read_at')->orWhereColumn('conversation_participants.last_read_at', '<', 'conversations.last_message_at')))
            ->count();
    }
}
