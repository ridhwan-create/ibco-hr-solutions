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
                'payroll.view',
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
                'payroll.view',
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
            ],
            self::Viewer => [
                'dashboard.view',
                'employees.view',
                'positions.view',
                'attendance.view',
                'leave.view',
                'overtime.view',
            ],
            self::Employee => [
                'dashboard.view',
                'employee.profile.view',
                'employee.profile.update',
                'leave.self',
                'leave.apply',
                'attendance.clock',
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
            self::HrAdmin => 'Akses semua modul HR termasuk payroll dan laporan.',
            self::Supervisor => 'Semak permohonan cuti pekerja bagi jabatan yang ditetapkan.',
            self::Viewer => 'Akses paparan operasi tanpa payroll, laporan dan pengurusan pengguna.',
            self::Employee => 'Akses profil sendiri, permohonan cuti dan rakaman kehadiran.',
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
