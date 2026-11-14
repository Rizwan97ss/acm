import { Checkbox } from '@/components/ui/checkbox';
import { cn } from '@/lib/utils';
import type { PermissionModule } from '@/types';

type PermissionMatrixProps = {
    modules: PermissionModule[];
    selected: string[];
    /** Permissions coming from the parent role: shown as granted and locked. */
    inherited: string[];
    /** Permissions the current user may hand out. */
    grantable: string[];
    onChange: (permissions: string[]) => void;
    disabled?: boolean;
};

const ACTIONS = ['view', 'create', 'edit', 'delete', 'approve', 'export'];

/**
 * Module × action grid. Whole rows and columns toggle together; permissions the
 * user cannot grant are disabled (the server enforces the same rule).
 */
export default function PermissionMatrix({
    modules,
    selected,
    inherited,
    grantable,
    onChange,
    disabled = false,
}: PermissionMatrixProps) {
    const selectedSet = new Set(selected);
    const inheritedSet = new Set(inherited);
    const grantableSet = new Set(grantable);
    const groups = [...new Set(modules.map((module) => module.group))];

    const isEditable = (name: string): boolean =>
        !disabled && grantableSet.has(name) && !inheritedSet.has(name);

    const setMany = (names: string[], checked: boolean): void => {
        const editable = names.filter(isEditable);
        const next = new Set(selectedSet);
        editable.forEach((name) =>
            checked ? next.add(name) : next.delete(name),
        );
        onChange([...next].sort());
    };

    const rowNames = (module: PermissionModule): string[] =>
        module.actions.map((action) => `${module.key}.${action}`);
    const columnNames = (action: string): string[] =>
        modules
            .filter((module) => module.actions.includes(action))
            .map((module) => `${module.key}.${action}`);
    const allChecked = (names: string[]): boolean =>
        names.length > 0 &&
        names.every((name) => selectedSet.has(name) || inheritedSet.has(name));

    return (
        <div className="overflow-x-auto rounded-xl border shadow-sm">
            <table className="w-full min-w-[640px] text-sm">
                <caption className="sr-only">
                    Permissions by module and action
                </caption>
                <thead className="sticky top-0 bg-muted/60 text-xs text-muted-foreground uppercase backdrop-blur">
                    <tr>
                        <th
                            scope="col"
                            className="px-4 py-3 text-left font-medium"
                        >
                            Module
                        </th>
                        {ACTIONS.map((action) => (
                            <th
                                key={action}
                                scope="col"
                                className="px-2 py-3 text-center font-medium"
                            >
                                <button
                                    type="button"
                                    disabled={disabled}
                                    onClick={() =>
                                        setMany(
                                            columnNames(action),
                                            !allChecked(columnNames(action)),
                                        )
                                    }
                                    className="uppercase hover:text-foreground disabled:cursor-default"
                                    aria-label={`Toggle ${action} for every module`}
                                >
                                    {action}
                                </button>
                            </th>
                        ))}
                    </tr>
                </thead>
                {groups.map((group) => (
                    <tbody key={group} className="divide-y border-t">
                        <tr className="bg-muted/20">
                            <th
                                colSpan={ACTIONS.length + 1}
                                scope="colgroup"
                                className="px-4 py-2 text-left text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                            >
                                {group}
                            </th>
                        </tr>
                        {modules
                            .filter((module) => module.group === group)
                            .map((module) => (
                                <tr
                                    key={module.key}
                                    className="hover:bg-muted/30"
                                >
                                    <th
                                        scope="row"
                                        className="px-4 py-2 text-left font-normal"
                                    >
                                        <button
                                            type="button"
                                            disabled={disabled}
                                            onClick={() =>
                                                setMany(
                                                    rowNames(module),
                                                    !allChecked(
                                                        rowNames(module),
                                                    ),
                                                )
                                            }
                                            className="text-left hover:underline disabled:cursor-default disabled:no-underline"
                                            aria-label={`Toggle every ${module.label} permission`}
                                        >
                                            {module.label}
                                        </button>
                                    </th>
                                    {ACTIONS.map((action) => {
                                        const name = `${module.key}.${action}`;

                                        if (!module.actions.includes(action)) {
                                            return (
                                                <td
                                                    key={action}
                                                    className="px-2 py-2 text-center text-muted-foreground/40"
                                                    aria-hidden
                                                >
                                                    ·
                                                </td>
                                            );
                                        }

                                        const isInherited =
                                            inheritedSet.has(name);

                                        return (
                                            <td
                                                key={action}
                                                className="px-2 py-2 text-center"
                                            >
                                                <Checkbox
                                                    checked={
                                                        isInherited ||
                                                        selectedSet.has(name)
                                                    }
                                                    disabled={!isEditable(name)}
                                                    onCheckedChange={(
                                                        checked,
                                                    ) =>
                                                        setMany(
                                                            [name],
                                                            checked === true,
                                                        )
                                                    }
                                                    aria-label={`${module.label}: ${action}${isInherited ? ' (inherited)' : ''}`}
                                                    className={cn(
                                                        isInherited &&
                                                            'data-[state=checked]:opacity-60',
                                                    )}
                                                />
                                            </td>
                                        );
                                    })}
                                </tr>
                            ))}
                    </tbody>
                ))}
            </table>
        </div>
    );
}
