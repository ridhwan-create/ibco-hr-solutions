<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case HrAdmin = 'hr_admin';
    case Supervisor = 'supervisor';
    case Viewer = 'viewer';
    case Employee = 'employee';

    /**
     * @return array<int, string>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::SuperAdmin => [
                'dashboard.view',
                'employees.view',
                'employees.manage',
                'positions.view',
                'positions.manage',
                'attendance.view',
                'attendance.manage',
                'attendance.settings',
                'leave.view',
                'leave.manage',
                'leave.supervise',
                'leave.settings',
                'overtime.view',
                'overtime.manage',
                'overtime.supervise',
                'overtime.settings',
                'roster.view',
                'roster.manage',
                'roster.publish',
                'roster.supervise',
                'roster.settings',
                'performance.view',
                'performance.manage',
                'performance.supervise',
                'performance.moderate',
                'performance.finalize',
                'performance.settings',
                'claims.view',
                'claims.manage',
                'claims.supervise',
                'claims.settings',
                'payroll.view',
                'payroll.manage',
                'payroll.approve',
                'payroll.settings',
                'reports.view',
                'audit.view',
                'users.manage',
            ],
            self::HrAdmin => [
                'dashboard.view',
                'employees.view',
                'employees.manage',
                'positions.view',
                'positions.manage',
                'attendance.view',
                'attendance.manage',
                'leave.view',
                'leave.manage',
                'leave.settings',
                'overtime.view',
                'overtime.manage',
                'overtime.settings',
                'roster.view',
                'roster.manage',
                'roster.publish',
                'roster.settings',
                'performance.view',
                'performance.manage',
                'performance.moderate',
                'performance.finalize',
                'performance.settings',
                'claims.view',
                'claims.manage',
                'claims.settings',
                'payroll.view',
                'payroll.manage',
                'payroll.settings',
                'reports.view',
                'audit.view',
            ],
            self::Supervisor => [
                'dashboard.view',
                'employees.view',
                'positions.view',
                'attendance.view',
                'leave.view',
                'leave.supervise',
                'overtime.view',
                'overtime.supervise',
                'roster.view',
                'roster.supervise',
                'performance.view',
                'performance.supervise',
                'claims.view',
                'claims.supervise',
            ],
            self::Viewer => [
                'dashboard.view',
                'employees.view',
                'positions.view',
                'attendance.view',
                'leave.view',
                'overtime.view',
                'roster.view',
            ],
            self::Employee => [
                'dashboard.view',
                'employee.profile.view',
                'employee.profile.update',
                'leave.self',
                'leave.apply',
                'attendance.clock',
                'overtime.self',
                'overtime.apply',
                'roster.self',
                'roster.swap',
                'performance.self',
                'claims.self',
                'claims.apply',
                'payslip.self',
            ],
        };
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::HrAdmin => 'HR Admin',
            self::Supervisor => 'Penyelia / Ketua Jabatan',
            self::Viewer => 'Viewer / Manager',
            self::Employee => 'Employee',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Akses penuh termasuk pengurusan pengguna dan role.',
            self::HrAdmin => 'Akses semua modul HR termasuk roster, prestasi, tuntutan, payroll dan laporan.',
            self::Supervisor => 'Semak cuti, OT, tuntutan, pertukaran syif dan prestasi jabatan.',
            self::Viewer => 'Akses paparan operasi dan roster tanpa payroll, laporan atau pengurusan pengguna.',
            self::Employee => 'Akses profil, roster, prestasi, cuti, OT, tuntutan, kehadiran dan slip gaji sendiri.',
        };
    }

    public function priority(): int
    {
        return match ($this) {
            self::SuperAdmin => 400,
            self::HrAdmin => 300,
            self::Supervisor => 250,
            self::Viewer => 200,
            self::Employee => 100,
        };
    }
}
