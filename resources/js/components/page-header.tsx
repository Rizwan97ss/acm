import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';

type PageHeaderProps = {
    title: string;
    description?: string;
    actions?: ReactNode;
};

export default function PageHeader({
    title,
    description,
    actions,
}: PageHeaderProps) {
    return (
        <>
            <Head title={title} />
            <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div className="space-y-1.5">
                    <h1 className="text-2xl font-bold tracking-tight">
                        {title}
                    </h1>
                    <div
                        className="h-1 w-14 rounded-full bg-gradient-to-r from-indigo-500 via-fuchsia-500 to-orange-400"
                        aria-hidden
                    />
                    {description && (
                        <p className="pt-1 text-sm text-muted-foreground">
                            {description}
                        </p>
                    )}
                </div>
                {actions && (
                    <div className="flex shrink-0 flex-wrap gap-2">
                        {actions}
                    </div>
                )}
            </div>
        </>
    );
}
