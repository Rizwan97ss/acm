import { Link, router, useForm } from '@inertiajs/react';
import { BookOpen, Library, Pencil, FileUp, Plus } from 'lucide-react';
import type { FormEvent } from 'react';
import { useEffect, useState } from 'react';
import ConfirmDelete from '@/components/confirm-delete';
import Pagination from '@/components/data/pagination';
import SearchInput from '@/components/data/search-input';
import FormField from '@/components/form-field';
import PageHeader from '@/components/page-header';
import StatCard from '@/components/stat-card';
import { TonePill } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { destroy, index, store, update } from '@/routes/library/books';
import { index as loans } from '@/routes/library/loans';
import { show as importPage } from '@/routes/imports';
import type { PaginatedList } from '@/types/communication';

type Book = {
    id: number;
    title: string;
    author: string | null;
    isbn: string | null;
    publisher: string | null;
    category: string | null;
    shelf: string | null;
    copies_total: number;
    copies_available: number;
};

type Props = {
    books: PaginatedList<Book>;
    filters: { search?: string; category?: string; available: boolean };
    categories: string[];
    stats: { titles: number; copies: number; onLoan: number };
    can: { create: boolean; edit: boolean; delete: boolean };
};

function BookDialog({
    book,
    categories,
    onClose,
}: {
    book: Book | null;
    categories: string[];
    onClose: () => void;
}) {
    const form = useForm({
        title: book?.title ?? '',
        author: book?.author ?? '',
        isbn: book?.isbn ?? '',
        publisher: book?.publisher ?? '',
        category: book?.category ?? '',
        shelf: book?.shelf ?? '',
        copies_total: book?.copies_total ?? 1,
    });

    const submit = (e: FormEvent): void => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: onClose };

        if (book) {
            form.put(update.url(book.id), options);
        } else {
            form.post(store.url(), options);
        }
    };

    const text = (
        key: 'title' | 'author' | 'isbn' | 'publisher' | 'category' | 'shelf',
        label: string,
        options: { required?: boolean; wide?: boolean; list?: string } = {},
    ) => (
        <FormField
            id={`book-${key}`}
            label={label}
            error={form.errors[key]}
            required={options.required}
            className={options.wide ? 'sm:col-span-2' : undefined}
        >
            <Input
                id={`book-${key}`}
                value={form.data[key]}
                onChange={(e) => form.setData(key, e.target.value)}
                required={options.required}
                list={options.list}
            />
        </FormField>
    );

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="sm:max-w-xl">
                <DialogTitle>{book ? 'Edit book' : 'Add book'}</DialogTitle>
                <DialogDescription>
                    Copies on loan are kept track of automatically.
                </DialogDescription>
                <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
                    {text('title', 'Title', { required: true, wide: true })}
                    {text('author', 'Author')}
                    {text('isbn', 'ISBN')}
                    {text('publisher', 'Publisher')}
                    {text('category', 'Category', { list: 'book-categories' })}
                    <datalist id="book-categories">
                        {categories.map((category) => (
                            <option key={category} value={category} />
                        ))}
                    </datalist>
                    {text('shelf', 'Shelf')}
                    <FormField
                        id="book-copies"
                        label="Copies owned"
                        error={form.errors.copies_total}
                        required
                    >
                        <Input
                            id="book-copies"
                            type="number"
                            min={0}
                            max={1000}
                            value={form.data.copies_total}
                            onChange={(e) =>
                                form.setData(
                                    'copies_total',
                                    Number(e.target.value),
                                )
                            }
                            required
                        />
                    </FormField>
                    <div className="flex justify-end sm:col-span-2">
                        <Button type="submit" disabled={form.processing}>
                            Save book
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function Books({
    books,
    filters,
    categories,
    stats,
    can,
}: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [editing, setEditing] = useState<Book | null | 'new'>(null);
    const visit = (changes: Record<string, string | boolean | undefined>) =>
        router.get(
            index.url(),
            { ...filters, search, ...changes },
            { preserveState: true, replace: true },
        );

    useEffect(() => {
        if (search === (filters.search ?? '')) {
            return;
        }

        const timeout = setTimeout(
            () =>
                router.get(
                    index.url(),
                    { ...filters, search },
                    { preserveState: true, replace: true },
                ),
            300,
        );

        return () => clearTimeout(timeout);
    }, [search, filters]);

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title="Library catalogue"
                description="Find a book and see whether a copy is on the shelf."
                actions={
                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <Link href={loans()}>
                                <BookOpen aria-hidden /> Loans
                            </Link>
                        </Button>
                        {can.create && (
                            <Button variant="outline" asChild>
                                <Link href={importPage('library-books')}>
                                    <FileUp aria-hidden /> Import
                                </Link>
                            </Button>
                        )}
                        {can.create && (
                            <Button onClick={() => setEditing('new')}>
                                <Plus aria-hidden /> Add book
                            </Button>
                        )}
                    </div>
                }
            />
            <section aria-label="Summary" className="grid gap-4 sm:grid-cols-3">
                <StatCard label="Titles" value={stats.titles} tone="violet" />
                <StatCard label="Copies" value={stats.copies} tone="sky" />
                <StatCard label="On loan" value={stats.onLoan} tone="orange" />
            </section>
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                <SearchInput
                    value={search}
                    onChange={setSearch}
                    placeholder="Title, author or ISBN…"
                />
                <NativeSelect
                    aria-label="Category"
                    value={filters.category ?? ''}
                    onChange={(e) =>
                        visit({ category: e.target.value || undefined })
                    }
                    className="sm:w-48"
                >
                    <option value="">All categories</option>
                    {categories.map((category) => (
                        <option key={category} value={category}>
                            {category}
                        </option>
                    ))}
                </NativeSelect>
                <div className="flex items-center gap-2">
                    <Checkbox
                        id="only-available"
                        checked={filters.available}
                        onCheckedChange={(checked) =>
                            visit({ available: checked === true || undefined })
                        }
                    />
                    <Label htmlFor="only-available">On the shelf only</Label>
                </div>
            </div>
            <Card className="overflow-x-auto p-0">
                {books.data.length === 0 ? (
                    <div className="flex flex-col items-center gap-2 p-10 text-center text-sm text-muted-foreground">
                        <Library className="size-8 text-primary" aria-hidden />
                        No books found.
                    </div>
                ) : (
                    <table className="w-full text-sm">
                        <caption className="sr-only">Books</caption>
                        <thead className="border-b text-left text-xs text-muted-foreground uppercase">
                            <tr>
                                <th
                                    scope="col"
                                    className="px-4 py-3 font-medium"
                                >
                                    Title
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-3 font-medium"
                                >
                                    Category
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-3 font-medium"
                                >
                                    Shelf
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-3 font-medium"
                                >
                                    Availability
                                </th>
                                {(can.edit || can.delete) && (
                                    <th scope="col" className="px-4 py-3">
                                        <span className="sr-only">Actions</span>
                                    </th>
                                )}
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {books.data.map((book) => (
                                <tr key={book.id}>
                                    <td className="px-4 py-3">
                                        <p className="font-medium">
                                            {book.title}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {[book.author, book.isbn]
                                                .filter(Boolean)
                                                .join(' · ')}
                                        </p>
                                    </td>
                                    <td className="px-4 py-3">
                                        {book.category ?? '—'}
                                    </td>
                                    <td className="px-4 py-3">
                                        {book.shelf ?? '—'}
                                    </td>
                                    <td className="px-4 py-3">
                                        <TonePill
                                            tone={
                                                book.copies_available > 0
                                                    ? 'success'
                                                    : 'danger'
                                            }
                                        >
                                            {book.copies_available} of{' '}
                                            {book.copies_total} available
                                        </TonePill>
                                    </td>
                                    {(can.edit || can.delete) && (
                                        <td className="px-4 py-3">
                                            <div className="flex justify-end gap-1">
                                                {can.edit && (
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label={`Edit ${book.title}`}
                                                        onClick={() =>
                                                            setEditing(book)
                                                        }
                                                    >
                                                        <Pencil aria-hidden />
                                                    </Button>
                                                )}
                                                {can.delete && (
                                                    <ConfirmDelete
                                                        action={destroy(
                                                            book.id,
                                                        )}
                                                        title="Delete book?"
                                                        description={`“${book.title}” will be removed from the catalogue.`}
                                                    />
                                                )}
                                            </div>
                                        </td>
                                    )}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </Card>
            <Pagination meta={books.meta} />
            {editing !== null && (
                <BookDialog
                    book={editing === 'new' ? null : editing}
                    categories={categories}
                    onClose={() => setEditing(null)}
                />
            )}
        </div>
    );
}

Books.layout = {
    breadcrumbs: [{ title: 'Library', href: index() }],
};
