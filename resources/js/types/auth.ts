export type UserRole =
    'super_admin' | 'hr_admin' | 'supervisor' | 'viewer' | 'employee';

export type Permission =
    | 'dashboard.view'
    | 'employees.view'
    | 'employees.manage'
    | 'positions.view'
    | 'positions.manage'
    | 'attendance.view'
    | 'attendance.manage'
    | 'attendance.clock'
    | 'attendance.settings'
    | 'employee.profile.view'
    | 'employee.profile.update'
    | 'leave.view'
    | 'leave.manage'
    | 'leave.supervise'
    | 'leave.settings'
    | 'leave.self'
    | 'leave.apply'
    | 'overtime.view'
    | 'payroll.view'
    | 'reports.view'
    | 'audit.view'
    | 'users.manage';

export type User = {
    id: number;
    name: string;
    email: string;
    role: UserRole;
    roles: UserRole[];
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
    permissions: Permission[];
};

export type LeaveApprovalAlerts = {
    enabled: boolean;
    total: number;
    supervisor: number;
    hr: number;
    polling_seconds: number;
};

/* @chisel-passkeys */
export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};
/* @end-chisel-passkeys */

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
