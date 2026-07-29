import type { Auth, Permission, UserRole } from '@/types';

const roleLabels: Record<UserRole, string> = {
    super_admin: 'Super Admin',
    hr_admin: 'HR Admin',
    supervisor: 'Penyelia / Ketua Jabatan',
    viewer: 'Viewer / Manager',
    employee: 'Employee',
};

export function hasPermission(auth: Auth, permission: Permission): boolean {
    return auth.permissions.includes(permission);
}

export function roleLabel(role: UserRole): string {
    return roleLabels[role];
}
