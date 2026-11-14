import { usePage } from '@inertiajs/react';

import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    const { name, branding, tenant } = usePage().props;
    const displayName = branding?.name ?? name;

    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center overflow-hidden rounded-lg bg-gradient-to-br from-fuchsia-500 via-violet-500 to-indigo-500 text-white shadow-md shadow-fuchsia-900/30">
                {branding?.logoUrl ? (
                    <img
                        src={branding.logoUrl}
                        alt=""
                        className="size-full bg-white object-cover"
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
                    <span className="truncate text-xs text-sidebar-foreground/70">
                        {tenant.current.name}
                    </span>
                )}
            </div>
        </>
    );
}
