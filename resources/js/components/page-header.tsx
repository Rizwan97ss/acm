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
                <div className="space-y-1">
                    <h1 className="text-xl font-semibold tracking-tight">
                        {title}
                    </h1>
                    {description && (
                        <p className="text-sm text-muted-foreground">
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
