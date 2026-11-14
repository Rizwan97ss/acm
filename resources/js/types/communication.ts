import type { Paginated } from '@/types';

export type AnnouncementItem = {
    id: number;
    title: string;
    body: string;
    audience: string;
    audience_label: string;
    school_class_id: number | null;
    school_class: string | null;
    is_pinned: boolean;
    expires_on: string | null;
    published_at: string | null;
    author: string | null;
};

export type SchoolEventItem = {
    id: number;
    title: string;
    description: string | null;
    location: string | null;
    starts_at: string;
    ends_at: string;
    all_day: boolean;
    audience: string;
    audience_label: string;
    school_class_id: number | null;
    school_class: string | null;
};

export type ConversationSummary = {
    id: number;
    subject: string;
    participants: string[];
    last_message_at: string | null;
    preview: string | null;
    unread: boolean;
};

export type ConversationDetail = {
    id: number;
    subject: string;
    participants: { id: number; name: string }[];
    messages: {
        id: number;
        body: string;
        sender: string;
        mine: boolean;
        sent_at: string | null;
    }[];
};

export type NotificationItem = {
    id: string;
    kind: string;
    title: string;
    body: string | null;
    read: boolean;
    created_at: string | null;
};

export type PaginatedList<T> = { data: T[]; meta: Paginated<T>['meta'] };
