export type UserRole =
    | 'super_admin'
    | 'hr_manager'
    | 'hr_admin'
    | 'supervisor'
    | 'viewer'
    | 'employee';

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
    | 'leave.approve'
    | 'leave.supervise'
    | 'leave.settings'
    | 'leave.self'
    | 'leave.apply'
    | 'overtime.view'
    | 'overtime.manage'
    | 'overtime.approve'
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
    | 'recruitment.view'
    | 'recruitment.manage'
    | 'recruitment.approve'
    | 'recruitment.interview'
    | 'recruitment.settings'
    | 'onboarding.view'
    | 'onboarding.manage'
    | 'onboarding.approve'
    | 'onboarding.self'
    | 'training.view'
    | 'training.manage'
    | 'training.approve'
    | 'training.supervise'
    | 'training.settings'
    | 'training.self'
    | 'training.apply'
    | 'competency.view'
    | 'competency.assess'
    | 'competency.self'
    | 'documents.view'
    | 'documents.manage'
    | 'documents.approve'
    | 'documents.settings'
    | 'documents.self'
    | 'discipline.view'
    | 'discipline.manage'
    | 'discipline.investigate'
    | 'discipline.approve'
    | 'discipline.settings'
    | 'discipline.self'
    | 'discipline.apply'
    | 'separation.view'
    | 'separation.manage'
    | 'separation.supervise'
    | 'separation.approve'
    | 'separation.clearance'
    | 'separation.settings'
    | 'separation.self'
    | 'separation.apply'
    | 'claims.view'
    | 'claims.manage'
    | 'claims.approve'
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
    recruitment_total: number;
    recruitment_approval: number;
    recruitment_interview: number;
    onboarding_total: number;
    onboarding_registration: number;
    onboarding_overdue: number;
    training_total: number;
    training_supervisor: number;
    training_hr: number;
    document_total: number;
    document_approval: number;
    document_expiring: number;
    discipline_total: number;
    discipline_triage: number;
    discipline_investigation: number;
    discipline_decision: number;
    separation_total: number;
    separation_supervisor: number;
    separation_hr: number;
    separation_clearance: number;
    separation_final_review: number;
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
