<?php

namespace App\Http\Controllers\Communication;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\Communication\MessagingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Private inbox. Conversations are visible only to their participants; anyone else gets
 * a 404 so the existence of a thread is not revealed.
 */
class MessageController extends Controller
{
    public function __construct(private MessagingService $messaging) {}

    public function index(Request $request): Response
    {
        Gate::authorize('messages.view');

        return Inertia::render('communication/messages/index', $this->inbox($request->user(), null));
    }

    public function show(Request $request, Conversation $conversation): Response
    {
        Gate::authorize('messages.view');
        $user = $request->user();
        abort_unless($conversation->hasParticipant($user), 404);

        $this->messaging->markRead($conversation, $user);

        return Inertia::render('communication/messages/index', $this->inbox($user, $conversation));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('messages.create');
        $user = $request->user();

        $validated = $request->validate([
            'recipient_ids' => ['required', 'array', 'min:1', 'max:20'],
            'recipient_ids.*' => ['integer', 'distinct'],
            'subject' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $recipientIds = array_values(array_map(intval(...), $validated['recipient_ids']));

        if ($this->messaging->contactsFor($user)->whereKey($recipientIds)->count() !== count($recipientIds)) {
            throw ValidationException::withMessages(['recipient_ids' => __('You cannot message one or more of the selected people.')]);
        }

        $conversation = $this->messaging->start($user, $recipientIds, $validated['subject'], $validated['body']);

        return to_route('communication.messages.show', $conversation);
    }

    public function reply(Request $request, Conversation $conversation): RedirectResponse
    {
        Gate::authorize('messages.create');
        $user = $request->user();
        abort_unless($conversation->hasParticipant($user), 404);

        $validated = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        $this->messaging->post($conversation, $user, $validated['body']);

        return to_route('communication.messages.show', $conversation);
    }

    /**
     * Leaves the conversation; the others keep their copy.
     */
    public function destroy(Request $request, Conversation $conversation): RedirectResponse
    {
        Gate::authorize('messages.view');
        $user = $request->user();
        abort_unless($conversation->hasParticipant($user), 404);

        $conversation->participants()->detach($user->id);

        if (! $conversation->participants()->exists()) {
            $conversation->delete();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Conversation removed from your inbox.')]);

        return to_route('communication.messages.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function inbox(User $user, ?Conversation $selected): array
    {
        $conversations = Conversation::query()
            ->whereHas('participants', fn (Builder $query) => $query->whereKey($user->id))
            ->with(['participants:id,name', 'latestMessage.sender:id,name'])
            ->orderByDesc('last_message_at')
            ->limit(50)
            ->get();

        $canCreate = $user->can('messages.create');

        return [
            'conversations' => $conversations->map(function (Conversation $conversation) use ($user): array {
                /** @var User|null $me */
                $me = $conversation->participants->firstWhere('id', $user->id);
                $lastRead = $me?->getRelationValue('pivot')?->getAttribute('last_read_at');

                return [
                    'id' => $conversation->id,
                    'subject' => $conversation->subject,
                    'participants' => $conversation->participants->where('id', '!=', $user->id)->pluck('name')->values(),
                    'last_message_at' => $conversation->last_message_at?->toIso8601String(),
                    'preview' => $conversation->latestMessage ? str($conversation->latestMessage->body)->limit(80)->toString() : null,
                    'unread' => $conversation->last_message_at !== null && ($lastRead === null || $conversation->last_message_at->greaterThan($lastRead)),
                ];
            })->values(),
            'selected' => $selected === null ? null : [
                'id' => $selected->id,
                'subject' => $selected->subject,
                'participants' => $selected->participants()->get(['users.id', 'users.name'])->map(fn (User $participant): array => ['id' => $participant->id, 'name' => $participant->name])->values(),
                'messages' => $selected->messages()
                    ->with('sender:id,name')
                    ->latest('id')
                    ->limit(100)
                    ->get()
                    ->reverse()
                    ->map(fn (Message $message): array => [
                        'id' => $message->id,
                        'body' => $message->body,
                        'sender' => $message->sender->name ?? __('Former user'),
                        'mine' => $message->user_id === $user->id,
                        'sent_at' => $message->created_at?->toIso8601String(),
                    ])->values(),
            ],
            'contacts' => $canCreate
                ? $this->messaging->contactsFor($user)->orderBy('name')->limit(500)->get(['id', 'name'])
                : [],
            'can' => ['create' => $canCreate],
        ];
    }
}
