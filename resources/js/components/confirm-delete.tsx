import { router } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import type { RouteDefinition } from '@/wayfinder';

type ConfirmDeleteProps = {
    action: RouteDefinition<'delete'>;
    title: string;
    description: string;
    label?: string;
};

/**
 * A delete button guarded by a confirmation dialog. Errors from the server
 * (e.g. "role still assigned") arrive as a flash toast or validation error.
 */
export default function ConfirmDelete({
    action,
    title,
    description,
    label = 'Delete',
}: ConfirmDeleteProps) {
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);

    const confirm = (): void => {
        router.visit(action, {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onError: (errors) => {
                const message = Object.values(errors)[0];

                if (message) {
                    toast.error(message);
                }
            },
            onFinish: () => {
                setProcessing(false);
                setOpen(false);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant="ghost"
                    size="sm"
                    className="text-destructive hover:text-destructive"
                >
                    <Trash2 aria-hidden />
                    <span className="sr-only sm:not-sr-only">{label}</span>
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>{title}</DialogTitle>
                <DialogDescription>{description}</DialogDescription>
                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="secondary">Cancel</Button>
                    </DialogClose>
                    <Button
                        variant="destructive"
                        disabled={processing}
                        onClick={confirm}
                    >
                        {label}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
