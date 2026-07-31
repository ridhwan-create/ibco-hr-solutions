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
    | 'overtime.manage'
    | 'overtime.supervise'
    | 'overtime.settings'
    | 'overtime.self'
    | 'overtime.apply'
    | 'roster.view'
    | 'roster.manage'
    | 'roster.publish'
    | 'roster.supervise'
    | 'roster.settings'
    | 'roster.self'
    | 'roster.swap'
    | 'performance.view'
    | 'performance.manage'
    | 'performance.supervise'
    | 'performance.moderate'
    | 'performance.finalize'
    | 'performance.settings'
    | 'performance.self'
    | 'claims.view'
    | 'claims.manage'
    | 'claims.supervise'
    | 'claims.settings'
    | 'claims.self'
    | 'claims.apply'
    | 'payroll.view'
    | 'payroll.manage'
    | 'payroll.approve'
    | 'payroll.settings'
    | 'payslip.self'
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
    leave_total: number;
    leave_supervisor: number;
    leave_hr: number;
    overtime_total: number;
    overtime_supervisor: number;
    overtime_hr: number;
    claim_total: number;
    claim_supervisor: number;
    claim_finance: number;
    performance_total: number;
    performance_supervisor: number;
    performance_hr: number;
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
