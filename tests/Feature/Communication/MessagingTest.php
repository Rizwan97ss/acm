<?php

use App\Models\Conversation;
use App\Models\Message;
use App\Notifications\NewMessageReceived;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

test('a guardian can message staff, and the reply notifies the guardian', function () {
    Notification::fake();
    $setup = communitySchool();

    $this->actingAs($setup['guardianUser'])->post(route('communication.messages.store'), [
        'recipient_ids' => [$setup['manager']->id], 'subject' => 'Absence next week', 'body' => 'Ali has a hospital appointment.',
    ])->assertRedirect();

    $conversation = Conversation::query()->sole();
    Notification::assertSentTo($setup['manager'], NewMessageReceived::class);

    $this->actingAs($setup['manager'])->post(route('communication.messages.reply', $conversation), ['body' => 'Thanks, noted.'])->assertRedirect();
    Notification::assertSentTo($setup['guardianUser'], NewMessageReceived::class);

    $this->actingAs($setup['guardianUser'])->get(route('communication.messages.show', $conversation))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('selected.subject', 'Absence next week')
            ->has('selected.messages', 2)
            ->where('selected.messages.1.body', 'Thanks, noted.')
            ->where('selected.messages.1.mine', false));
});

test('message bodies are encrypted at rest', function () {
    $setup = communitySchool();

    $this->actingAs($setup['guardianUser'])->post(route('communication.messages.store'), [
        'recipient_ids' => [$setup['manager']->id], 'subject' => 'Private', 'body' => 'Sensitive medical detail',
    ]);

    expect(DB::table('messages')->value('body'))->not->toContain('Sensitive')
        ->and(Message::query()->sole()->body)->toBe('Sensitive medical detail');
});

test('guardians cannot message other guardians or students', function () {
    $setup = communitySchool();

    foreach ([$setup['otherGuardianUser'], $setup['studentUser']] as $recipient) {
        $this->actingAs($setup['guardianUser'])->post(route('communication.messages.store'), [
            'recipient_ids' => [$recipient->id], 'subject' => 'Hi', 'body' => 'Hello',
        ])->assertSessionHasErrors('recipient_ids');
    }

    expect(Conversation::query()->count())->toBe(0);
});

test('students can read their inbox but cannot write', function () {
    $setup = communitySchool();

    $this->actingAs($setup['studentUser'])->get(route('communication.messages.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('can.create', false)->where('contacts', []));
    $this->actingAs($setup['studentUser'])->post(route('communication.messages.store'), [
        'recipient_ids' => [$setup['manager']->id], 'subject' => 'Hi', 'body' => 'Hello',
    ])->assertForbidden();
});

test('non-participants cannot see or reply to a conversation', function () {
    $setup = communitySchool();
    $this->actingAs($setup['guardianUser'])->post(route('communication.messages.store'), [
        'recipient_ids' => [$setup['manager']->id], 'subject' => 'Private', 'body' => 'Hello',
    ]);
    $conversation = Conversation::query()->sole();

    $this->actingAs($setup['otherGuardianUser'])->get(route('communication.messages.show', $conversation))->assertNotFound();
    $this->actingAs($setup['otherGuardianUser'])->post(route('communication.messages.reply', $conversation), ['body' => 'Sneaky'])->assertNotFound();
    $this->actingAs($setup['otherGuardianUser'])->get(route('communication.messages.index'))
        ->assertInertia(fn (Assert $page) => $page->has('conversations', 0));
});

test('leaving a conversation removes it from the inbox only for that person', function () {
    $setup = communitySchool();
    $this->actingAs($setup['guardianUser'])->post(route('communication.messages.store'), [
        'recipient_ids' => [$setup['manager']->id], 'subject' => 'Thread', 'body' => 'Hello',
    ]);
    $conversation = Conversation::query()->sole();

    $this->actingAs($setup['guardianUser'])->delete(route('communication.messages.destroy', $conversation))->assertRedirect();

    $this->actingAs($setup['guardianUser'])->get(route('communication.messages.show', $conversation))->assertNotFound();
    $this->actingAs($setup['manager'])->get(route('communication.messages.show', $conversation))->assertOk();
});
