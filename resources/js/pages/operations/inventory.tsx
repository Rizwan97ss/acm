import { router, useForm } from '@inertiajs/react';
import { ArrowDownUp, Boxes, Pencil, Plus } from 'lucide-react';
import type { FormEvent } from 'react';
import { useEffect, useState } from 'react';
import ConfirmDelete from '@/components/confirm-delete';
import Pagination from '@/components/data/pagination';
import SearchInput from '@/components/data/search-input';
import FormField from '@/components/form-field';
import PageHeader from '@/components/page-header';
import { TonePill } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import { useFormat } from '@/hooks/use-format';
import { cn } from '@/lib/utils';
import {
    destroy,
    index,
    move,
    store,
    update,
} from '@/routes/operations/inventory';
import type { PaginatedList } from '@/types/communication';
import type { EnumOption } from '@/types/people';

type Item = {
    id: number;
    name: string;
    sku: string | null;
    category: string | null;
    unit: string;
    location: string | null;
    quantity: number;
    reorder_level: number;
    low: boolean;
};

type Movement = {
    id: number;
    item: string;
    unit: string;
    type: string;
    type_label: string;
    quantity: number;
    balance_after: number;
    note: string | null;
    user: string | null;
    at: string | null;
};

type Props = {
    items: PaginatedList<Item>;
    filters: { search?: string; low: boolean };
    lowCount: number;
    movements: Movement[];
    movementTypes: EnumOption[];
    can: { create: boolean; edit: boolean; delete: boolean };
};

function ItemDialog({
    item,
    onClose,
}: {
    item: Item | null;
    onClose: () => void;
}) {
    const form = useForm({
        name: item?.name ?? '',
        sku: item?.sku ?? '',
        category: item?.category ?? '',
        unit: item?.unit ?? 'pcs',
        location: item?.location ?? '',
        reorder_level: item?.reorder_level ?? 0,
        opening_quantity: 0,
    });

    const submit = (e: FormEvent): void => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: onClose };

        if (item) {
            form.put(update.url(item.id), options);
        } else {
            form.post(store.url(), options);
        }
    };

    const text = (
        key: 'name' | 'sku' | 'category' | 'unit' | 'location',
        label: string,
        required = false,
    ) => (
        <FormField
            id={`item-${key}`}
            label={label}
            error={form.errors[key]}
            required={required}
        >
            <Input
                id={`item-${key}`}
                value={form.data[key]}
                onChange={(e) => form.setData(key, e.target.value)}
                required={required}
            />
        </FormField>
    );

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <DialogTitle>{item ? 'Edit item' : 'New item'}</DialogTitle>
                <DialogDescription>
                    Stock levels change only through recorded movements.
                </DialogDescription>
                <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
                    {text('name', 'Name', true)}
                    {text('sku', 'SKU / code')}
                    {text('category', 'Category')}
                    {text('unit', 'Unit', true)}
                    {text('location', 'Location')}
                    <FormField
                        id="item-reorder"
                        label="Reorder at"
                        error={form.errors.reorder_level}
                        required
                    >
                        <Input
                            id="item-reorder"
                            type="number"
                            min={0}
                            value={form.data.reorder_level}
                            onChange={(e) =>
                                form.setData(
                                    'reorder_level',
                                    Number(e.target.value),
                                )
                            }
                            required
                        />
                    </FormField>
                    {!item && (
                        <FormField
                            id="item-opening"
                            label="Opening stock"
                            error={form.errors.opening_quantity}
                        >
                            <Input
                                id="item-opening"
                                type="number"
                                min={0}
                                value={form.data.opening_quantity}
                                onChange={(e) =>
                                    form.setData(
                                        'opening_quantity',
                                        Number(e.target.value),
                                    )
                                }
                            />
                        </FormField>
                    )}
                    <div className="flex justify-end sm:col-span-2">
                        <Button type="submit" disabled={form.processing}>
                            Save item
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function MoveDialog({
    item,
    types,
    onClose,
}: {
    item: Item;
    types: EnumOption[];
    onClose: () => void;
}) {
    const form = useForm({ type: 'out', quantity: 1, note: '' });

    const submit = (e: FormEvent): void => {
        e.preventDefault();
        form.post(move.url(item.id), {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <DialogTitle>Update stock: {item.name}</DialogTitle>
                <DialogDescription>
                    {item.quantity} {item.unit} in stock now. A stock count sets
                    the quantity you counted.
                </DialogDescription>
                <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
                    <FormField
                        id="move-type"
                        label="Movement"
                        error={form.errors.type}
                        required
                    >
                        <NativeSelect
                            id="move-type"
                            value={form.data.type}
                            onChange={(e) =>
                                form.setData('type', e.target.value)
                            }
                        >
                            {types.map((type) => (
                                <option key={type.value} value={type.value}>
                                    {type.label}
                                </option>
                            ))}
                        </NativeSelect>
                    </FormField>
                    <FormField
                        id="move-quantity"
                        label={
                            form.data.type === 'adjust'
                                ? 'Counted quantity'
                                : 'Quantity'
                        }
                        error={form.errors.quantity}
                        required
                    >
                        <Input
                            id="move-quantity"
                            type="number"
                            min={0}
                            value={form.data.quantity}
                            onChange={(e) =>
                                form.setData('quantity', Number(e.target.value))
                            }
                            required
                        />
                    </FormField>
                    <FormField
                        id="move-note"
                        label="Note"
                        error={form.errors.note}
                        className="sm:col-span-2"
                    >
                        <Input
                            id="move-note"
                            value={form.data.note}
                            onChange={(e) =>
                                form.setData('note', e.target.value)
                            }
                            placeholder="e.g. Issued to Science lab"
                        />
                    </FormField>
                    <div className="flex justify-end sm:col-span-2">
                        <Button type="submit" disabled={form.processing}>
                            Record
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function Inventory({
    items,
    filters,
    lowCount,
    movements,
    movementTypes,
    can,
}: Props) {
    const format = useFormat();
    const [search, setSearch] = useState(filters.search ?? '');
    const [editing, setEditing] = useState<Item | null | 'new'>(null);
    const [moving, setMoving] = useState<Item | null>(null);

    useEffect(() => {
        if (search === (filters.search ?? '')) {
            return;
        }

        const timeout = setTimeout(
            () =>
                router.get(
                    index.url(),
                    {
                        search: search || undefined,
                        low: filters.low || undefined,
                    },
                    { preserveState: true, replace: true },
                ),
            300,
        );

        return () => clearTimeout(timeout);
    }, [search, filters]);

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title="Inventory"
                description="Stationery, equipment and supplies."
                actions={
                    can.create ? (
                        <Button onClick={() => setEditing('new')}>
                            <Plus aria-hidden /> New item
                        </Button>
                    ) : null
                }
            />
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                <SearchInput
                    value={search}
                    onChange={setSearch}
                    placeholder="Name, code or category…"
                />
                <div className="flex items-center gap-2">
                    <Checkbox
                        id="low-only"
                        checked={filters.low}
                        onCheckedChange={(checked) =>
                            router.get(
                                index.url(),
                                {
                                    search: search || undefined,
                                    low: checked === true || undefined,
                                },
                                { preserveState: true, replace: true },
                            )
                        }
                    />
                    <Label htmlFor="low-only">Low stock only</Label>
                    {lowCount > 0 && (
                        <TonePill tone="warning">{lowCount} low</TonePill>
                    )}
                </div>
            </div>
            <div className="grid gap-4 xl:grid-cols-3">
                <div className="space-y-4 xl:col-span-2">
                    <Card className="overflow-x-auto p-0">
                        {items.data.length === 0 ? (
                            <div className="flex flex-col items-center gap-2 p-10 text-center text-sm text-muted-foreground">
                                <Boxes
                                    className="size-8 text-primary"
                                    aria-hidden
                                />
                                No items.
                            </div>
                        ) : (
                            <table className="w-full text-sm">
                                <caption className="sr-only">
                                    Inventory items
                                </caption>
                                <thead className="border-b text-left text-xs text-muted-foreground uppercase">
                                    <tr>
                                        <th
                                            scope="col"
                                            className="px-4 py-3 font-medium"
                                        >
                                            Item
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-4 py-3 font-medium"
                                        >
                                            Location
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-4 py-3 text-right font-medium"
                                        >
                                            In stock
                                        </th>
                                        {(can.edit || can.delete) && (
                                            <th
                                                scope="col"
                                                className="px-4 py-3"
                                            >
                                                <span className="sr-only">
                                                    Actions
                                                </span>
                                            </th>
                                        )}
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {items.data.map((item) => (
                                        <tr key={item.id}>
                                            <td className="px-4 py-3">
                                                <p className="font-medium">
                                                    {item.name}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {[item.sku, item.category]
                                                        .filter(Boolean)
                                                        .join(' · ')}
                                                </p>
                                            </td>
                                            <td className="px-4 py-3">
                                                {item.location ?? '—'}
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <TonePill
                                                    tone={
                                                        item.quantity === 0
                                                            ? 'danger'
                                                            : item.low
                                                              ? 'warning'
                                                              : 'success'
                                                    }
                                                >
                                                    {item.quantity} {item.unit}
                                                </TonePill>
                                            </td>
                                            {(can.edit || can.delete) && (
                                                <td className="px-4 py-3">
                                                    <div className="flex justify-end gap-1">
                                                        {can.edit && (
                                                            <Button
                                                                size="sm"
                                                                variant="outline"
                                                                onClick={() =>
                                                                    setMoving(
                                                                        item,
                                                                    )
                                                                }
                                                            >
                                                                <ArrowDownUp
                                                                    aria-hidden
                                                                />{' '}
                                                                Stock
                                                            </Button>
                                                        )}
                                                        {can.edit && (
                                                            <Button
                                                                size="icon"
                                                                variant="ghost"
                                                                aria-label={`Edit ${item.name}`}
                                                                onClick={() =>
                                                                    setEditing(
                                                                        item,
                                                                    )
                                                                }
                                                            >
                                                                <Pencil
                                                                    aria-hidden
                                                                />
                                                            </Button>
                                                        )}
                                                        {can.delete &&
                                                            item.quantity ===
                                                                0 && (
                                                                <ConfirmDelete
                                                                    action={destroy(
                                                                        item.id,
                                                                    )}
                                                                    title="Delete item?"
                                                                    description={`“${item.name}” and its history will be removed.`}
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
                    <Pagination meta={items.meta} />
                </div>
                <Card>
                    <CardHeader>
                        <CardTitle>Recent movements</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {movements.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Nothing recorded yet.
                            </p>
                        ) : (
                            <ul className="space-y-3 text-sm">
                                {movements.map((movement) => (
                                    <li
                                        key={movement.id}
                                        className="flex items-start justify-between gap-3"
                                    >
                                        <div className="min-w-0">
                                            <p className="truncate font-medium">
                                                {movement.item}
                                            </p>
                                            <p className="truncate text-xs text-muted-foreground">
                                                {movement.type_label} ·{' '}
                                                {format.dateTime(movement.at)}
                                                {movement.user &&
                                                    ` · ${movement.user}`}
                                            </p>
                                            {movement.note && (
                                                <p className="truncate text-xs">
                                                    {movement.note}
                                                </p>
                                            )}
                                        </div>
                                        <span
                                            className={cn(
                                                'shrink-0 font-medium tabular-nums',
                                                movement.quantity > 0
                                                    ? 'text-emerald-700 dark:text-emerald-400'
                                                    : 'text-rose-700 dark:text-rose-400',
                                            )}
                                        >
                                            {movement.quantity > 0 ? '+' : '−'}
                                            {Math.abs(movement.quantity)}{' '}
                                            {movement.unit}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>
            {editing !== null && (
                <ItemDialog
                    item={editing === 'new' ? null : editing}
                    onClose={() => setEditing(null)}
                />
            )}
            {moving !== null && (
                <MoveDialog
                    item={moving}
                    types={movementTypes}
                    onClose={() => setMoving(null)}
                />
            )}
        </div>
    );
}

Inventory.layout = {
    breadcrumbs: [{ title: 'Inventory', href: index() }],
};
