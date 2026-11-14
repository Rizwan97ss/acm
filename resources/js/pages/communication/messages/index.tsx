import { Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Inbox, LogOut, PenSquare, Send } from 'lucide-react';
import type { FormEvent } from 'react';
import { useEffect, useRef, useState } from 'react';
import FormField from '@/components/form-field';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useFormat } from '@/hooks/use-format';
import { cn } from '@/lib/utils';
import {
    destroy,
    index,
    reply,
    show,
    store,
} from '@/routes/communication/messages';
import type { Option } from '@/types';
import type {
    ConversationDetail,
    ConversationSummary,
} from '@/types/communication';

type Props = {
    conversations: ConversationSummary[];
    selected: ConversationDetail | null;
    contacts: Option[];
    can: { create: boolean };
};

function ComposeDialog({
    contacts,
    onClose,
}: {
    contacts: Option[];
    onClose: () => void;
}) {
    const [filter, setFilter] = useState('');
    const form = useForm({
        recipient_ids: [] as number[],
        subject: '',
        body: '',
    });
    const visible = contacts.filter((contact) =>
        contact.name.toLowerCase().includes(filter.toLowerCase()),
    );
    const toggle = (id: number): void =>
        form.setData(
            'recipient_ids',
            form.data.recipient_ids.includes(id)
                ? form.data.recipient_ids.filter((value) => value !== id)
                : [...form.data.recipient_ids, id],
        );

    const submit = (e: FormEvent): void => {
        e.preventDefault();
        form.post(store.url(), { onSuccess: onClose });
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="sm:max-w-xl">
                <DialogTitle>New message</DialogTitle>
                <DialogDescription>
                    Messages are private to the people in the conversation.
                </DialogDescription>
                <form onSubmit={submit} className="grid gap-4">
                    <FormField
                        id="message-to"
                        label={`To (${form.data.recipient_ids.length} selected)`}
                        error={form.errors.recipient_ids}
                        required
                    >
                        <Input
                            id="message-to"
                            type="search"
                            placeholder="Find a person…"
                            value={filter}
                            onChange={(e) => setFilter(e.target.value)}
                        />
                        <ul
                            className="max-h-40 overflow-y-auto rounded-md border p-1"
                            aria-label="People you can message"
                        >
                            {visible.map((contact) => {
                                const checked =
                                    form.data.recipient_ids.includes(
                                        contact.id,
                                    );

                                return (
                                    <li key={contact.id}>
                                        <label className="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 text-sm hover:bg-accent">
                                            <input
                                                type="checkbox"
                                                className="size-4 accent-primary"
                                                checked={checked}
                                                onChange={() =>
                                                    toggle(contact.id)
                                                }
                                            />
                                            {contact.name}
                                        </label>
                                    </li>
                                );
                            })}
                            {visible.length === 0 && (
                                <li className="px-2 py-1.5 text-sm text-muted-foreground">
                                    No matches.
                                </li>
                            )}
                        </ul>
                    </FormField>
                    <FormField
                        id="message-subject"
                        label="Subject"
                        error={form.errors.subject}
                        required
                    >
                        <Input
                            id="message-subject"
                            value={form.data.subject}
                            onChange={(e) =>
                                form.setData('subject', e.target.value)
                            }
                            maxLength={150}
                            required
                        />
                    </FormField>
                    <FormField
                        id="message-body"
                        label="Message"
                        error={form.errors.body}
                        required
                    >
                        <Textarea
                            id="message-body"
                            value={form.data.body}
                            onChange={(e) =>
                                form.setData('body', e.target.value)
                            }
                            rows={5}
                            maxLength={5000}
                            required
                        />
                    </FormField>
                    <div className="flex justify-end">
                        <Button
                            type="submit"
                            disabled={
                                form.processing ||
                                form.data.recipient_ids.length === 0
                            }
                        >
                            <Send aria-hidden /> Send
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function Thread({
    conversation,
    canReply,
}: {
    conversation: ConversationDetail;
    canReply: boolean;
}) {
    const format = useFormat();
    const bottom = useRef<HTMLDivElement>(null);
    const form = useForm({ body: '' });

    useEffect(() => {
        bottom.current?.scrollIntoView({ block: 'end' });
    }, [conversation.messages.length]);

    const submit = (e: FormEvent): void => {
        e.preventDefault();
        form.post(reply.url(conversation.id), {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <div className="flex h-full min-h-[28rem] flex-col">
            <div className="flex items-start justify-between gap-2 border-b p-4">
                <div className="min-w-0">
                    <Button
                        variant="ghost"
                        size="sm"
                        className="-ml-2 md:hidden"
                        asChild
                    >
                        <Link href={index()}>
                            <ArrowLeft aria-hidden /> Inbox
                        </Link>
                    </Button>
                    <h2 className="truncate font-semibold">
                        {conversation.subject}
                    </h2>
                    <p className="truncate text-xs text-muted-foreground">
                        {conversation.participants
                            .map((participant) => participant.name)
                            .join(', ')}
                    </p>
                </div>
                <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => router.delete(destroy.url(conversation.id))}
                >
                    <LogOut aria-hidden /> Leave
                </Button>
            </div>
            <ol
                className="flex-1 space-y-3 overflow-y-auto p-4"
                aria-label="Messages"
            >
                {conversation.messages.map((message) => (
                    <li
                        key={message.id}
                        className={cn(
                            'flex flex-col gap-1',
                            message.mine ? 'items-end' : 'items-start',
                        )}
                    >
                        <div
                            className={cn(
                                'max-w-[85%] rounded-2xl px-3.5 py-2 text-sm whitespace-pre-line',
                                message.mine
                                    ? 'rounded-br-sm bg-gradient-to-br from-violet-600 to-indigo-600 text-white'
                                    : 'rounded-bl-sm bg-muted',
                            )}
                        >
                            {message.body}
                        </div>
                        <span className="text-[11px] text-muted-foreground">
                            {message.mine ? 'You' : message.sender} ·{' '}
                            {format.dateTime(message.sent_at)}
                        </span>
                    </li>
                ))}
                <div ref={bottom} />
            </ol>
            {canReply ? (
                <form onSubmit={submit} className="flex gap-2 border-t p-3">
                    <label htmlFor="reply-body" className="sr-only">
                        Reply
                    </label>
                    <Textarea
                        id="reply-body"
                        value={form.data.body}
                        onChange={(e) => form.setData('body', e.target.value)}
                        rows={2}
                        maxLength={5000}
                        placeholder="Write a reply…"
                        className="min-h-0 flex-1 resize-none"
                        required
                    />
                    <Button
                        type="submit"
                        disabled={form.processing || !form.data.body.trim()}
                        aria-label="Send reply"
                    >
                        <Send aria-hidden />
                    </Button>
                </form>
            ) : (
                <p className="border-t p-3 text-center text-xs text-muted-foreground">
                    You can read messages but not reply.
                </p>
            )}
        </div>
    );
}

export default function Messages({
    conversations,
    selected,
    contacts,
    can,
}: Props) {
    const format = useFormat();
    const [composing, setComposing] = useState(false);

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title="Messages"
                description="Private conversations between staff and families."
                actions={
                    can.create ? (
                        <Button onClick={() => setComposing(true)}>
                            <PenSquare aria-hidden /> New message
                        </Button>
                    ) : null
                }
            />
            <Card className="grid gap-0 overflow-hidden p-0 md:grid-cols-[18rem_1fr]">
                <nav
                    aria-label="Conversations"
                    className={cn(
                        'border-b md:border-r md:border-b-0',
                        selected && 'hidden md:block',
                    )}
                >
                    {conversations.length === 0 ? (
                        <div className="flex flex-col items-center gap-2 p-8 text-center text-sm text-muted-foreground">
                            <Inbox
                                className="size-8 text-primary"
                                aria-hidden
                            />
                            No conversations yet.
                        </div>
                    ) : (
                        <ul className="max-h-[36rem] divide-y overflow-y-auto">
                            {conversations.map((conversation) => (
                                <li key={conversation.id}>
                                    <Link
                                        href={show(conversation.id)}
                                        className={cn(
                                            'block space-y-0.5 px-4 py-3 hover:bg-accent',
                                            selected?.id === conversation.id &&
                                                'bg-accent',
                                        )}
                                        aria-current={
                                            selected?.id === conversation.id
                                                ? 'page'
                                                : undefined
                                        }
                                    >
                                        <div className="flex items-center justify-between gap-2">
                                            <span
                                                className={cn(
                                                    'truncate text-sm',
                                                    conversation.unread &&
                                                        'font-semibold',
                                                )}
                                            >
                                                {conversation.participants.join(
                                                    ', ',
                                                ) || 'Only you'}
                                            </span>
                                            {conversation.unread && (
                                                <span
                                                    className="size-2 shrink-0 rounded-full bg-rose-500"
                                                    aria-label="Unread"
                                                />
                                            )}
                                        </div>
                                        <p className="truncate text-sm">
                                            {conversation.subject}
                                        </p>
                                        <p className="truncate text-xs text-muted-foreground">
                                            {format.dateTime(
                                                conversation.last_message_at,
                                            )}{' '}
                                            · {conversation.preview}
                                        </p>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </nav>
                <section
                    aria-label="Conversation"
                    className={cn(!selected && 'hidden md:block')}
                >
                    {selected ? (
                        <Thread
                            key={selected.id}
                            conversation={selected}
                            canReply={can.create}
                        />
                    ) : (
                        <div className="flex h-full min-h-[20rem] items-center justify-center p-8 text-sm text-muted-foreground">
                            Choose a conversation to read it.
                        </div>
                    )}
                </section>
            </Card>
            {composing && (
                <ComposeDialog
                    contacts={contacts}
                    onClose={() => setComposing(false)}
                />
            )}
        </div>
    );
}

Messages.layout = {
    breadcrumbs: [{ title: 'Messages', href: index() }],
};
