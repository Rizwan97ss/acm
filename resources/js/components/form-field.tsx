import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

type FormFieldProps = {
    id: string;
    label: string;
    error?: string;
    description?: ReactNode;
    required?: boolean;
    className?: string;
    children: ReactNode;
};

/**
 * Label, control, help text and validation error laid out consistently.
 */
export default function FormField({
    id,
    label,
    error,
    description,
    required,
    className,
    children,
}: FormFieldProps) {
    return (
        <div className={cn('grid gap-2', className)}>
            <Label htmlFor={id}>
                {label}
                {required && (
                    <span className="text-destructive" aria-hidden>
                        *
                    </span>
                )}
            </Label>
            {children}
            {description && (
                <p
                    id={`${id}-description`}
                    className="text-xs text-muted-foreground"
                >
                    {description}
                </p>
            )}
            <InputError message={error} />
        </div>
    );
}
