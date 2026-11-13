export type Paginated<T> = {
    data: T[];
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        from: number | null;
        last_page: number;
        per_page: number;
        to: number | null;
        total: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
};

export type RowAbilities = {
    update: boolean;
    delete: boolean;
};

export type Option = { id: number; name: string };

export type SchoolSummary = {
    id: number;
    name: string;
    code: string;
    logo_url?: string | null;
};

export type ManagedUser = {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    is_active: boolean;
    two_factor_enabled: boolean;
    last_login_at: string | null;
    created_at: string | null;
    school?: Option | null;
    roles?: Option[];
    can: RowAbilities;
};

export type Role = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    is_system: boolean;
    school_id: number | null;
    parent_id: number | null;
    school?: Option | null;
    parent?: Option | null;
    users_count?: number;
    permissions_count?: number;
    can: RowAbilities;
};

export type School = {
    id: number;
    name: string;
    code: string;
    email: string | null;
    phone: string | null;
    address: string | null;
    parent_id: number | null;
    is_active: boolean;
    logo_url: string | null;
    parent?: Option | null;
    users_count?: number;
    branches_count?: number;
    can: RowAbilities & { switch: boolean };
};

export type AuditLog = {
    id: number;
    event: string;
    user: { id: number; name: string; email: string } | null;
    auditable_type: string | null;
    auditable_id: number | null;
    old_values: Record<string, unknown> | null;
    new_values: Record<string, unknown> | null;
    ip_address: string | null;
    user_agent: string | null;
    created_at: string | null;
};

export type PermissionModule = {
    key: string;
    label: string;
    group: string;
    actions: string[];
};

export type SettingField = {
    name: string;
    label: string;
    type: 'string' | 'text' | 'boolean' | 'integer' | 'select' | 'color';
    description: string | null;
    options: Record<string, string> | null;
    value: string | number | boolean | null;
    inherited: string | number | boolean | null;
    overridden: boolean;
};

export type Tenant = {
    current: SchoolSummary | null;
    canSwitch: boolean;
    schools: SchoolSummary[];
};

export type Branding = {
    name: string;
    primaryColor: string;
    logoUrl: string | null;
};

export type Preferences = {
    timezone?: string;
    dateFormat?: string;
    timeFormat?: string;
    currency?: string;
};
