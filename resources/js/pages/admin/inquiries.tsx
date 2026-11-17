import { router } from '@inertiajs/react';
import { Building2, Mail, MailOpen, Phone, UserRound } from 'lucide-react';
import ConfirmDelete from '@/components/confirm-delete';
import Pagination from '@/components/data/pagination';
import SearchInput from '@/components/data/search-input';
import PageHeader from '@/components/page-header';
import { TonePill } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { NativeSelect } from '@/components/ui/native-select';
import { useFormat } from '@/hooks/use-format';
import { useTableFilters } from '@/hooks/use-table-filters';
import { cn } from '@/lib/utils';
import { destroy, index, update } from '@/routes/admin/inquiries';
import type { PaginatedList } from '@/types/communication';

type Inquiry = {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    organization: string | null;
    role: string | null;
    school_size: string | null;
    interest: string;
    message: string;
    read: boolean;
    created_at: string | null;
};

type Props = {
    inquiries: PaginatedList<Inquiry>;
    filters: { search?: string; status?: string };
    unread: number;
    can: { delete: boolean };
};

export default function Inquiries({ inquiries, filters, unread, can }: Props) {
    const { dateTime } = useFormat();
    const { values, update: updateFilters } = useTableFilters(index.url(), {
        search: filters.search ?? '',
        status: filters.status ?? '',
    });

    const toggleRead = (inquiry: Inquiry): void => {
        router.patch(
            update.url(inquiry.id),
            { read: !inquiry.read },
            { preserveScroll: true },
        );
    };

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title="Website enquiries"
                description={`Messages sent from the landing page contact form. ${unread} unread.`}
            />

            <div className="flex flex-col gap-2 md:flex-row md:items-center">
                <SearchInput
                    value={values.search}
                    onChange={(search) =>
                        updateFilters({ search }, { debounce: true })
                    }
                    placeholder="Name, email or school"
                />
                <NativeSelect
                    aria-label="Filter by status"
                    value={values.status}
                    onChange={(event) =>
                        updateFilters({ status: event.target.value })
                    }
                    className="md:w-44"
                >
                    <option value="">All enquiries</option>
                    <option value="unread">Unread</option>
                    <option value="read">Read</option>
                </NativeSelect>
            </div>

            {inquiries.data.length === 0 ? (
                <p className="rounded-xl border border-dashed p-10 text-center text-sm text-muted-foreground">
                    No enquiries match. New messages from the landing page
                    appear here.
                </p>
            ) : (
                <ul className="grid gap-4">
                    {inquiries.data.map((inquiry) => (
                        <li
                            key={inquiry.id}
                            className={cn(
                                'rounded-2xl border bg-card p-5 shadow-sm',
                                !inquiry.read && 'border-l-4 border-l-gold-500',
                            )}
                        >
                            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div className="min-w-0">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <h2 className="font-semibold">
                                            {inquiry.name}
                                        </h2>
                                        <TonePill tone="info">
                                            {inquiry.interest}
                                        </TonePill>
                                        {!inquiry.read && (
                                            <TonePill tone="warning">
                                                New
                                            </TonePill>
                                        )}
                                    </div>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        {dateTime(inquiry.created_at)}
                                    </p>
                                </div>
                                <div className="flex shrink-0 gap-1">
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => toggleRead(inquiry)}
                                    >
                                        {inquiry.read ? (
                                            <Mail aria-hidden />
                                        ) : (
                                            <MailOpen aria-hidden />
                                        )}
                                        {inquiry.read
                                            ? 'Mark unread'
                                            : 'Mark read'}
                                    </Button>
                                    {can.delete && (
                                        <ConfirmDelete
                                            action={destroy(inquiry.id)}
                                            title="Delete this enquiry?"
                                            description={`The message from ${inquiry.name} will be removed permanently.`}
                                        />
                                    )}
                                </div>
                            </div>

                            <dl className="mt-4 grid gap-2 text-sm sm:grid-cols-2 lg:grid-cols-4">
                                <div className="flex items-center gap-2">
                                    <dt>
                                        <Mail
                                            className="size-4 text-muted-foreground"
                                            aria-label="Email"
                                        />
                                    </dt>
                                    <dd className="truncate">
                                        <a
                                            href={`mailto:${inquiry.email}`}
                                            className="font-medium text-primary hover:underline"
                                        >
                                            {inquiry.email}
                                        </a>
                                    </dd>
                                </div>
                                {inquiry.phone && (
                                    <div className="flex items-center gap-2">
                                        <dt>
                                            <Phone
                                                className="size-4 text-muted-foreground"
                                                aria-label="Phone"
                                            />
                                        </dt>
                                        <dd>
                                            <a
                                                href={`tel:${inquiry.phone.replace(/[^+\d]/g, '')}`}
                                                className="hover:underline"
                                            >
                                                {inquiry.phone}
                                            </a>
                                        </dd>
                                    </div>
                                )}
                                {inquiry.organization && (
                                    <div className="flex items-center gap-2">
                                        <dt>
                                            <Building2
                                                className="size-4 text-muted-foreground"
                                                aria-label="Organisation"
                                            />
                                        </dt>
                                        <dd className="truncate">
                                            {inquiry.organization}
                                            {inquiry.school_size &&
                                                ` · ${inquiry.school_size}`}
                                        </dd>
                                    </div>
                                )}
                                {inquiry.role && (
                                    <div className="flex items-center gap-2">
                                        <dt>
                                            <UserRound
                                                className="size-4 text-muted-foreground"
                                                aria-label="Role"
                                            />
                                        </dt>
                                        <dd>{inquiry.role}</dd>
                                    </div>
                                )}
                            </dl>

                            <p className="mt-4 rounded-xl bg-muted/60 p-4 text-sm leading-relaxed whitespace-pre-line">
                                {inquiry.message}
                            </p>
                        </li>
                    ))}
                </ul>
            )}

            <Pagination meta={inquiries.meta} />
        </div>
    );
}

Inquiries.layout = {
    breadcrumbs: [{ title: 'Website enquiries', href: index() }],
};
