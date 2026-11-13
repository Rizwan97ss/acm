import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import FormField from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import type { SettingField } from '@/types';

type Value = string | number | boolean | null;

type SettingsFormProps = {
    fields: SettingField[];
    submitUrl: string;
    /** Below the global scope a field may "inherit" instead of storing its own value. */
    allowInherit: boolean;
    inheritLabel?: string;
    disabled?: boolean;
};

const displayValue = (field: SettingField, value: Value): string => {
    if (value === null || value === '') {
        return 'not set';
    }

    if (field.type === 'boolean') {
        return value ? 'on' : 'off';
    }

    return field.options?.[String(value)] ?? String(value);
};

/**
 * Renders any settings group from the registry. When inheriting, the field is
 * sent as null so the server removes the override.
 */
export default function SettingsForm({
    fields,
    submitUrl,
    allowInherit,
    inheritLabel = 'Use default',
    disabled = false,
}: SettingsFormProps) {
    const form = useForm<{
        values: Record<string, Value>;
        inherit: Record<string, boolean>;
    }>({
        values: Object.fromEntries(
            fields.map((field) => [field.name, field.value]),
        ),
        inherit: Object.fromEntries(
            fields.map((field) => [
                field.name,
                allowInherit && !field.overridden,
            ]),
        ),
    });

    const setValue = (name: string, value: Value): void =>
        form.setData('values', { ...form.data.values, [name]: value });

    const setInherit = (field: SettingField, inherit: boolean): void => {
        form.setData((data) => ({
            values: inherit
                ? { ...data.values, [field.name]: field.inherited }
                : data.values,
            inherit: { ...data.inherit, [field.name]: inherit },
        }));
    };

    const submit = (event: FormEvent): void => {
        event.preventDefault();

        form.transform((data) => ({
            values: Object.fromEntries(
                fields.map((field) => [
                    field.name,
                    data.inherit[field.name] ? null : data.values[field.name],
                ]),
            ),
        }));
        form.put(submitUrl, { preserveScroll: true });
    };

    const errorFor = (name: string): string | undefined =>
        (form.errors as Record<string, string | undefined>)[`values.${name}`];

    return (
        <form onSubmit={submit} className="space-y-6">
            {fields.map((field) => {
                const id = `setting-${field.name}`;
                const inherited = form.data.inherit[field.name];
                const locked = disabled || inherited;
                const value = form.data.values[field.name];

                return (
                    <div
                        key={field.name}
                        className="space-y-2 border-b pb-6 last:border-0 last:pb-0"
                    >
                        {field.type === 'boolean' ? (
                            <div className="flex items-start gap-3">
                                <Checkbox
                                    id={id}
                                    checked={value === true}
                                    disabled={locked}
                                    onCheckedChange={(checked) =>
                                        setValue(field.name, checked === true)
                                    }
                                    className="mt-0.5"
                                />
                                <div className="grid gap-1">
                                    <Label htmlFor={id}>{field.label}</Label>
                                    {field.description && (
                                        <p className="text-xs text-muted-foreground">
                                            {field.description}
                                        </p>
                                    )}
                                    {errorFor(field.name) && (
                                        <p className="text-sm text-destructive">
                                            {errorFor(field.name)}
                                        </p>
                                    )}
                                </div>
                            </div>
                        ) : (
                            <FormField
                                id={id}
                                label={field.label}
                                error={errorFor(field.name)}
                                description={field.description}
                            >
                                {field.type === 'select' ? (
                                    <NativeSelect
                                        id={id}
                                        value={String(value ?? '')}
                                        disabled={locked}
                                        onChange={(e) =>
                                            setValue(field.name, e.target.value)
                                        }
                                    >
                                        {Object.entries(
                                            field.options ?? {},
                                        ).map(([optionValue, label]) => (
                                            <option
                                                key={optionValue}
                                                value={optionValue}
                                            >
                                                {label}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                ) : field.type === 'color' ? (
                                    <div className="flex items-center gap-2">
                                        <input
                                            type="color"
                                            aria-label={`${field.label} picker`}
                                            value={String(value ?? '#000000')}
                                            disabled={locked}
                                            onChange={(e) =>
                                                setValue(
                                                    field.name,
                                                    e.target.value,
                                                )
                                            }
                                            className="h-9 w-12 cursor-pointer rounded-md border bg-transparent p-1 disabled:cursor-not-allowed"
                                        />
                                        <Input
                                            id={id}
                                            value={String(value ?? '')}
                                            disabled={locked}
                                            onChange={(e) =>
                                                setValue(
                                                    field.name,
                                                    e.target.value,
                                                )
                                            }
                                            className="w-32 font-mono"
                                        />
                                    </div>
                                ) : field.type === 'text' ? (
                                    <Textarea
                                        id={id}
                                        value={String(value ?? '')}
                                        disabled={locked}
                                        onChange={(e) =>
                                            setValue(field.name, e.target.value)
                                        }
                                    />
                                ) : (
                                    <Input
                                        id={id}
                                        type={
                                            field.type === 'integer'
                                                ? 'number'
                                                : 'text'
                                        }
                                        value={
                                            value === null ? '' : String(value)
                                        }
                                        disabled={locked}
                                        onChange={(e) =>
                                            setValue(
                                                field.name,
                                                field.type === 'integer'
                                                    ? e.target.value === ''
                                                        ? null
                                                        : Number(e.target.value)
                                                    : e.target.value,
                                            )
                                        }
                                        className="max-w-sm"
                                    />
                                )}
                            </FormField>
                        )}

                        {allowInherit && (
                            <label className="flex items-center gap-2 text-xs text-muted-foreground">
                                <Checkbox
                                    checked={inherited}
                                    disabled={disabled}
                                    onCheckedChange={(checked) =>
                                        setInherit(field, checked === true)
                                    }
                                />
                                {inheritLabel} (
                                {displayValue(field, field.inherited)})
                            </label>
                        )}
                    </div>
                );
            })}

            {!disabled && (
                <Button type="submit" disabled={form.processing}>
                    Save
                </Button>
            )}
        </form>
    );
}
