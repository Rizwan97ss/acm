import { usePage } from '@inertiajs/react';

import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    const { name, branding, tenant } = usePage().props;
    const displayName = branding?.name ?? name;

    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center overflow-hidden rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
                {branding?.logoUrl ? (
                    <img
                        src={branding.logoUrl}
                        alt=""
                        className="size-full object-cover"
                    />
                ) : (
                    <AppLogoIcon className="size-5 fill-current" />
                )}
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="truncate leading-tight font-semibold">
                    {displayName}
                </span>
                {tenant?.current && displayName !== tenant.current.name && (
                    <span className="truncate text-xs text-muted-foreground">
                        {tenant.current.name}
                    </span>
                )}
            </div>
        </>
    );
}
