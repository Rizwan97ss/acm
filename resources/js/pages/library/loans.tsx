import { Link, router, useForm } from '@inertiajs/react';
import { BookOpenCheck, Library, Plus, Undo2 } from 'lucide-react';
import type { FormEvent } from 'react';
import { useEffect, useState } from 'react';
import Pagination from '@/components/data/pagination';
import SearchInput from '@/components/data/search-input';
import FormField from '@/components/form-field';
import PageHeader from '@/components/page-header';
import { TonePill } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { useFormat } from '@/hooks/use-format';
import { useMoney } from '@/hooks/use-money';
import { cn } from '@/lib/utils';
import { index as books } from '@/routes/library/books';
import { index, returnMethod, store } from '@/routes/library/loans';
import type { PaginatedList } from '@/types/communication';

type Loan = {
    id: number;
    book: string;
    author: string | null;
    borrower: string;
    borrower_code: string | null;
    borrower_type: 'student' | 'staff';
    issued_on: string;
    due_on: string;
    returned_on: string | null;
    overdue: boolean;
    fine: number;
};

type Props = {
    loans: PaginatedList<Loan>;
    filters: { status: 'open' | 'overdue' | 'returned'; search?: string };
    overdueCount: number;
    books: {
        id: number;
        title: string;
        author: string | null;
        copies_available: number;
    }[];
    can: { issue: boolean; receive: boolean };
};

const TABS: { value: Props['filters']['status']; label: string }[] = [
    { value: 'open', label: 'On loan' },
    { value: 'overdue', label: 'Overdue' },
    { value: 'returned', label: 'Returned' },
];

function IssueDialog({
    books: available,
    onClose,
}: {
    books: Props['books'];
    onClose: () => void;
}) {
    const form = useForm({
        library_book_id: available[0]?.id ?? ('' as number | ''),
        borrower: '',
        due_on: '',
    });

    const submit = (e: FormEvent): void => {
        e.preventDefault();
        form.post(store.url(), { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <DialogTitle>Issue a book</DialogTitle>
                <DialogDescription>
                    Enter the student's admission number or the staff member's
                    employee number.
                </DialogDescription>
                <form onSubmit={submit} className="grid gap-4">
                    <FormField
                        id="loan-book"
                        label="Book"
                        error={form.errors.library_book_id}
                        required
                    >
                        <NativeSelect
                            id="loan-book"
                            value={form.data.library_book_id}
                            onChange={(e) =>
                                form.setData(
                                    'library_book_id',
                                    Number(e.target.value),
                                )
                            }
                            required
                        >
                            {available.map((book) => (
                                <option key={book.id} value={book.id}>
                                    {book.title}
                                    {book.author ? ` — ${book.author}` : ''} (
                                    {book.copies_available} left)
                                </option>
                            ))}
                        </NativeSelect>
                    </FormField>
                    <FormField
                        id="loan-borrower"
                        label="Borrower number"
                        error={form.errors.borrower}
                        required
                    >
                        <Input
                            id="loan-borrower"
                            value={form.data.borrower}
                            onChange={(e) =>
                                form.setData('borrower', e.target.value)
                            }
                            placeholder="e.g. ADM-2026-0001"
                            autoComplete="off"
                            required
                        />
                    </FormField>
                    <FormField
                        id="loan-due"
                        label="Due date"
                        error={form.errors.due_on}
                        description="Leave empty to use the standard loan period."
                    >
                        <Input
                            id="loan-due"
                            type="date"
                            value={form.data.due_on}
                            onChange={(e) =>
                                form.setData('due_on', e.target.value)
                            }
                        />
                    </FormField>
                    <div className="flex justify-end">
                        <Button
                            type="submit"
                            disabled={form.processing || available.length === 0}
                        >
                            Issue
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function Loans({
    loans,
    filters,
    overdueCount,
    books: available,
    can,
}: Props) {
    const format = useFormat();
    const money = useMoney();
    const [search, setSearch] = useState(filters.search ?? '');
    const [issuing, setIssuing] = useState(false);

    useEffect(() => {
        if (search === (filters.search ?? '')) {
            return;
        }

        const timeout = setTimeout(
            () =>
                router.get(
                    index.url(),
                    { status: filters.status, search: search || undefined },
                    { preserveState: true, replace: true },
                ),
            300,
        );

        return () => clearTimeout(timeout);
    }, [search, filters]);

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title={can.issue ? 'Library loans' : 'My library loans'}
                description={
                    can.issue
                        ? 'Issue and receive books at the circulation desk.'
                        : 'Books currently borrowed, and your borrowing history.'
                }
                actions={
                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <Link href={books()}>
                                <Library aria-hidden /> Catalogue
                            </Link>
                        </Button>
                        {can.issue && (
                            <Button onClick={() => setIssuing(true)}>
                                <Plus aria-hidden /> Issue book
                            </Button>
                        )}
                    </div>
                }
            />
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <nav
                    aria-label="Loan status"
                    className="inline-flex rounded-lg bg-muted p-1"
                >
                    {TABS.map((tab) => (
                        <Link
                            key={tab.value}
                            href={index({ query: { status: tab.value } })}
                            preserveScroll
                            className={cn(
                                'flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                                filters.status === tab.value
                                    ? 'bg-background shadow-sm'
                                    : 'text-muted-foreground hover:text-foreground',
                            )}
                            aria-current={
                                filters.status === tab.value
                                    ? 'page'
                                    : undefined
                            }
                        >
                            {tab.label}
                            {tab.value === 'overdue' && overdueCount > 0 && (
                                <span className="rounded-full bg-rose-600 px-1.5 text-[11px] text-white">
                                    {overdueCount}
                                </span>
                            )}
                        </Link>
                    ))}
                </nav>
                {can.issue && (
                    <SearchInput
                        value={search}
                        onChange={setSearch}
                        placeholder="Book, borrower or number…"
                    />
                )}
            </div>
            <Card className="overflow-x-auto p-0">
                {loans.data.length === 0 ? (
                    <div className="flex flex-col items-center gap-2 p-10 text-center text-sm text-muted-foreground">
                        <BookOpenCheck
                            className="size-8 text-primary"
                            aria-hidden
                        />
                        Nothing here.
                    </div>
                ) : (
                    <table className="w-full text-sm">
                        <caption className="sr-only">Loans</caption>
                        <thead className="border-b text-left text-xs text-muted-foreground uppercase">
                            <tr>
                                <th
                                    scope="col"
                                    className="px-4 py-3 font-medium"
                                >
                                    Book
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-3 font-medium"
                                >
                                    Borrower
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-3 font-medium"
                                >
                                    Issued
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-3 font-medium"
                                >
                                    Due
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-3 font-medium"
                                >
                                    Status
                                </th>
                                {can.receive &&
                                    filters.status !== 'returned' && (
                                        <th scope="col" className="px-4 py-3">
                                            <span className="sr-only">
                                                Actions
                                            </span>
                                        </th>
                                    )}
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {loans.data.map((loan) => (
                                <tr key={loan.id}>
                                    <td className="px-4 py-3">
                                        <p className="font-medium">
                                            {loan.book}
                                        </p>
                                        {loan.author && (
                                            <p className="text-xs text-muted-foreground">
                                                {loan.author}
                                            </p>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        <p>{loan.borrower}</p>
                                        <p className="font-mono text-xs text-muted-foreground">
                                            {loan.borrower_code} ·{' '}
                                            {loan.borrower_type}
                                        </p>
                                    </td>
                                    <td className="px-4 py-3">
                                        {format.date(loan.issued_on)}
                                    </td>
                                    <td className="px-4 py-3">
                                        {format.date(loan.due_on)}
                                    </td>
                                    <td className="px-4 py-3">
                                        {loan.returned_on ? (
                                            <TonePill
                                                tone={
                                                    loan.fine > 0
                                                        ? 'warning'
                                                        : 'success'
                                                }
                                            >
                                                Returned{' '}
                                                {format.date(loan.returned_on)}
                                                {loan.fine > 0 &&
                                                    ` · fine ${money.format(loan.fine)}`}
                                            </TonePill>
                                        ) : loan.overdue ? (
                                            <TonePill tone="danger">
                                                Overdue
                                            </TonePill>
                                        ) : (
                                            <TonePill tone="info">
                                                On loan
                                            </TonePill>
                                        )}
                                    </td>
                                    {can.receive &&
                                        filters.status !== 'returned' && (
                                            <td className="px-4 py-3 text-right">
                                                {!loan.returned_on && (
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            router.post(
                                                                returnMethod.url(
                                                                    loan.id,
                                                                ),
                                                                {},
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            )
                                                        }
                                                    >
                                                        <Undo2 aria-hidden />{' '}
                                                        Return
                                                    </Button>
                                                )}
                                            </td>
                                        )}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </Card>
            <Pagination meta={loans.meta} />
            {issuing && (
                <IssueDialog
                    books={available}
                    onClose={() => setIssuing(false)}
                />
            )}
        </div>
    );
}

Loans.layout = {
    breadcrumbs: [{ title: 'Library loans', href: index() }],
};
