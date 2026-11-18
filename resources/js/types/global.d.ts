import type { Auth } from '@/types/auth';
import type { Branding, Preferences, Tenant } from '@/types/models';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            tenant: Tenant;
            branding: Branding;
            preferences: Preferences;
            sidebarOpen: boolean;
            features: { learning?: boolean };
            [key: string]: unknown;
        };
    }
}
