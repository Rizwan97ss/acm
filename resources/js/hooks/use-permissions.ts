import { usePage } from '@inertiajs/react';
import { useMemo } from 'react';

export type UsePermissionsReturn = {
    can: (permission: string) => boolean;
    canAny: (permissions: string[]) => boolean;
    isSuperAdmin: boolean;
};

/**
 * Client-side permission checks for showing or hiding UI. The server always
 * re-checks every action, so this is a convenience, never a security boundary.
 */
export function usePermissions(): UsePermissionsReturn {
    const { auth } = usePage().props;
    const granted = useMemo(
        () => new Set(auth.permissions ?? []),
        [auth.permissions],
    );

    const can = (permission: string): boolean =>
        auth.isSuperAdmin || granted.has(permission);

    return {
        can,
        canAny: (permissions) => permissions.some(can),
        isSuperAdmin: auth.isSuperAdmin,
    };
}
