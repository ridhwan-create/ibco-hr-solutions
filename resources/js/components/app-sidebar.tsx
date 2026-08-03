import { Link, usePage } from '@inertiajs/react';
import {
    Banknote,
    BriefcaseBusiness,
    CalendarCheck2,
    CalendarClock,
    CalendarDays,
    Clock3,
    ClipboardCheck,
    Database,
    FileCog,
    FileText,
    Files,
    FolderGit2,
    GraduationCap,
    History,
    IdCard,
    LayoutGrid,
    ListChecks,
    LogOut,
    MapPinned,
    MessageSquareWarning,
    Navigation,
    ReceiptText,
    ShieldCheck,
    ShieldAlert,
    SlidersHorizontal,
    Target,
    TrendingUp,
    TimerReset,
    UserPlus,
    UserRoundCheck,
    UsersRound,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { hasPermission } from '@/lib/permissions';
import type { Auth, LeaveApprovalAlerts, NavItem, Permission } from '@/types';
import { dashboard } from '@/routes';

type AuthorizedNavItem = NavItem & {
    permission: Permission | Permission[];
};

const mainNavItems: AuthorizedNavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
        iconClassName:
            'bg-sky-100 text-sky-700 ring-sky-200 dark:bg-sky-950 dark:text-sky-300 dark:ring-sky-800',
        permission: 'dashboard.view',
    },
];

const employeeNavItems: AuthorizedNavItem[] = [
    {
        title: 'Pekerja',
        href: '/pekerja',
        icon: UsersRound,
        iconClassName:
            'bg-blue-100 text-blue-700 ring-blue-200 dark:bg-blue-950 dark:text-blue-300 dark:ring-blue-800',
        permission: 'employees.view',
    },
    {
        title: 'Jawatan',
        href: '/jawatan',
        icon: BriefcaseBusiness,
        iconClassName:
            'bg-indigo-100 text-indigo-700 ring-indigo-200 dark:bg-indigo-950 dark:text-indigo-300 dark:ring-indigo-800',
        permission: 'positions.view',
    },
];

const selfServiceNavItems: AuthorizedNavItem[] = [
    {
        title: 'Profil Saya',
        href: '/profil-saya',
        icon: IdCard,
        iconClassName:
            'bg-cyan-100 text-cyan-700 ring-cyan-200 dark:bg-cyan-950 dark:text-cyan-300 dark:ring-cyan-800',
        permission: 'employee.profile.view',
    },
    {
        title: 'Cuti Saya',
        href: '/cuti-saya',
        icon: CalendarDays,
        iconClassName:
            'bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-950 dark:text-emerald-300 dark:ring-emerald-800',
        permission: 'leave.self',
    },
    {
        title: 'OT Saya',
        href: '/ot-saya',
        icon: TimerReset,
        iconClassName:
            'bg-amber-100 text-amber-700 ring-amber-200 dark:bg-amber-950 dark:text-amber-300 dark:ring-amber-800',
        permission: 'overtime.self',
    },
    {
        title: 'Tuntutan Saya',
        href: '/tuntutan-saya',
        icon: ReceiptText,
        iconClassName:
            'bg-orange-100 text-orange-700 ring-orange-200 dark:bg-orange-950 dark:text-orange-300 dark:ring-orange-800',
        permission: 'claims.self',
    },
    {
        title: 'Jadual Saya',
        href: '/jadual-saya',
        icon: CalendarClock,
        iconClassName:
            'bg-sky-100 text-sky-700 ring-sky-200 dark:bg-sky-950 dark:text-sky-300 dark:ring-sky-800',
        permission: 'roster.self',
    },
    {
        title: 'Rakam Kehadiran',
        href: '/kehadiran/rakam',
        icon: Navigation,
        iconClassName:
            'bg-teal-100 text-teal-700 ring-teal-200 dark:bg-teal-950 dark:text-teal-300 dark:ring-teal-800',
        permission: 'attendance.clock',
    },
    {
        title: 'Slip Gaji Saya',
        href: '/slip-gaji-saya',
        icon: ReceiptText,
        iconClassName:
            'bg-fuchsia-100 text-fuchsia-700 ring-fuchsia-200 dark:bg-fuchsia-950 dark:text-fuchsia-300 dark:ring-fuchsia-800',
        permission: 'payslip.self',
    },
    {
        title: 'Prestasi Saya',
        href: '/prestasi-saya',
        icon: TrendingUp,
        iconClassName:
            'bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-950 dark:text-emerald-300 dark:ring-emerald-800',
        permission: 'performance.self',
    },
    {
        title: 'Onboarding Saya',
        href: '/onboarding-saya',
        icon: ListChecks,
        iconClassName:
            'bg-cyan-100 text-cyan-700 ring-cyan-200 dark:bg-cyan-950 dark:text-cyan-300 dark:ring-cyan-800',
        permission: 'onboarding.self',
    },
    {
        title: 'Latihan Saya',
        href: '/latihan-saya',
        icon: GraduationCap,
        iconClassName:
            'bg-indigo-100 text-indigo-700 ring-indigo-200 dark:bg-indigo-950 dark:text-indigo-300 dark:ring-indigo-800',
        permission: 'training.self',
    },
    {
        title: 'Dokumen Saya',
        href: '/dokumen-saya',
        icon: Files,
        iconClassName:
            'bg-rose-100 text-rose-700 ring-rose-200 dark:bg-rose-950 dark:text-rose-300 dark:ring-rose-800',
        permission: 'documents.self',
    },
    {
        title: 'Aduan Saya',
        href: '/aduan-saya',
        icon: MessageSquareWarning,
        iconClassName:
            'bg-red-100 text-red-700 ring-red-200 dark:bg-red-950 dark:text-red-300 dark:ring-red-800',
        permission: 'discipline.self',
    },
    {
        title: 'Pengakhiran Saya',
        href: '/pengakhiran-saya',
        icon: LogOut,
        iconClassName:
            'bg-orange-100 text-orange-700 ring-orange-200 dark:bg-orange-950 dark:text-orange-300 dark:ring-orange-800',
        permission: 'separation.self',
    },
];

const recruitmentNavItems: AuthorizedNavItem[] = [
    {
        title: 'Pengambilan',
        href: '/pengambilan',
        icon: UserPlus,
        iconClassName:
            'bg-pink-100 text-pink-700 ring-pink-200 dark:bg-pink-950 dark:text-pink-300 dark:ring-pink-800',
        permission: 'recruitment.view',
    },
    {
        title: 'Onboarding',
        href: '/onboarding',
        icon: ListChecks,
        iconClassName:
            'bg-cyan-100 text-cyan-700 ring-cyan-200 dark:bg-cyan-950 dark:text-cyan-300 dark:ring-cyan-800',
        permission: 'onboarding.view',
    },
];

const performanceNavItems: AuthorizedNavItem[] = [
    {
        title: 'Prestasi & KPI',
        href: '/prestasi',
        icon: Target,
        iconClassName:
            'bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-950 dark:text-emerald-300 dark:ring-emerald-800',
        permission: ['performance.view', 'performance.supervise'],
    },
];

const trainingNavItems: AuthorizedNavItem[] = [
    {
        title: 'Latihan & Kompetensi',
        href: '/latihan-kompetensi',
        icon: GraduationCap,
        iconClassName:
            'bg-indigo-100 text-indigo-700 ring-indigo-200 dark:bg-indigo-950 dark:text-indigo-300 dark:ring-indigo-800',
        permission: 'training.view',
    },
];

const documentNavItems: AuthorizedNavItem[] = [
    {
        title: 'Dokumen & Surat HR',
        href: '/dokumen-hr',
        icon: Files,
        iconClassName:
            'bg-rose-100 text-rose-700 ring-rose-200 dark:bg-rose-950 dark:text-rose-300 dark:ring-rose-800',
        permission: 'documents.view',
    },
];

const disciplineNavItems: AuthorizedNavItem[] = [
    {
        title: 'Disiplin & Aduan',
        href: '/disiplin-aduan',
        icon: ShieldAlert,
        iconClassName:
            'bg-red-100 text-red-700 ring-red-200 dark:bg-red-950 dark:text-red-300 dark:ring-red-800',
        permission: 'discipline.view',
    },
];

const separationNavItems: AuthorizedNavItem[] = [
    {
        title: 'Berhenti & Clearance',
        href: '/berhenti-clearance',
        icon: ClipboardCheck,
        iconClassName:
            'bg-orange-100 text-orange-700 ring-orange-200 dark:bg-orange-950 dark:text-orange-300 dark:ring-orange-800',
        permission: 'separation.view',
    },
];

const attendanceNavItems: AuthorizedNavItem[] = [
    {
        title: 'Jadual & Roster',
        href: '/jadual-roster',
        icon: CalendarClock,
        iconClassName:
            'bg-sky-100 text-sky-700 ring-sky-200 dark:bg-sky-950 dark:text-sky-300 dark:ring-sky-800',
        permission: 'roster.view',
    },
    {
        title: 'Kehadiran',
        href: '/kehadiran',
        icon: CalendarCheck2,
        iconClassName:
            'bg-teal-100 text-teal-700 ring-teal-200 dark:bg-teal-950 dark:text-teal-300 dark:ring-teal-800',
        permission: 'attendance.view',
    },
    {
        title: 'Kehadiran Asal',
        href: '/kehadiran-asal',
        icon: Database,
        iconClassName:
            'bg-slate-100 text-slate-700 ring-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700',
        permission: 'attendance.view',
    },
    {
        title: 'Permohonan Cuti',
        href: '/permohonan-cuti',
        icon: UserRoundCheck,
        iconClassName:
            'bg-green-100 text-green-700 ring-green-200 dark:bg-green-950 dark:text-green-300 dark:ring-green-800',
        permission: ['leave.manage', 'leave.supervise'],
    },
    {
        title: 'Cuti Asal',
        href: '/cuti',
        icon: CalendarDays,
        iconClassName:
            'bg-lime-100 text-lime-700 ring-lime-200 dark:bg-lime-950 dark:text-lime-300 dark:ring-lime-800',
        permission: 'leave.view',
    },
    {
        title: 'Permohonan OT',
        href: '/permohonan-ot',
        icon: UserRoundCheck,
        iconClassName:
            'bg-amber-100 text-amber-700 ring-amber-200 dark:bg-amber-950 dark:text-amber-300 dark:ring-amber-800',
        permission: ['overtime.manage', 'overtime.supervise'],
    },
    {
        title: 'OT Asal',
        href: '/kerja-lebih-masa',
        icon: Clock3,
        iconClassName:
            'bg-yellow-100 text-yellow-700 ring-yellow-200 dark:bg-yellow-950 dark:text-yellow-300 dark:ring-yellow-800',
        permission: 'overtime.view',
    },
];

const payrollNavItems: AuthorizedNavItem[] = [
    {
        title: 'Payroll',
        href: '/payroll',
        icon: Banknote,
        iconClassName:
            'bg-violet-100 text-violet-700 ring-violet-200 dark:bg-violet-950 dark:text-violet-300 dark:ring-violet-800',
        permission: 'payroll.view',
    },
    {
        title: 'Permohonan Tuntutan',
        href: '/permohonan-tuntutan',
        icon: ReceiptText,
        iconClassName:
            'bg-orange-100 text-orange-700 ring-orange-200 dark:bg-orange-950 dark:text-orange-300 dark:ring-orange-800',
        permission: ['claims.manage', 'claims.supervise'],
    },
    {
        title: 'Payroll Asal',
        href: '/payroll-asal',
        icon: Database,
        iconClassName:
            'bg-zinc-100 text-zinc-700 ring-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:ring-zinc-700',
        permission: 'payroll.view',
    },
    {
        title: 'Laporan Bulanan',
        href: '/laporan-bulanan',
        icon: FileText,
        iconClassName:
            'bg-blue-100 text-blue-700 ring-blue-200 dark:bg-blue-950 dark:text-blue-300 dark:ring-blue-800',
        permission: 'reports.view',
    },
    {
        title: 'Laporan Asal',
        href: '/laporan-bulanan-asal',
        icon: Database,
        iconClassName:
            'bg-stone-100 text-stone-700 ring-stone-200 dark:bg-stone-800 dark:text-stone-300 dark:ring-stone-700',
        permission: 'reports.view',
    },
];

const administrationNavItems: AuthorizedNavItem[] = [
    {
        title: 'Tetapan Syif',
        href: '/tetapan-syif',
        icon: CalendarClock,
        iconClassName:
            'bg-sky-100 text-sky-700 ring-sky-200 dark:bg-sky-950 dark:text-sky-300 dark:ring-sky-800',
        permission: 'roster.settings',
    },
    {
        title: 'Tetapan Kehadiran',
        href: '/tetapan-kehadiran',
        icon: MapPinned,
        iconClassName:
            'bg-cyan-100 text-cyan-700 ring-cyan-200 dark:bg-cyan-950 dark:text-cyan-300 dark:ring-cyan-800',
        permission: 'attendance.settings',
    },
    {
        title: 'Tetapan Cuti',
        href: '/tetapan-cuti',
        icon: SlidersHorizontal,
        iconClassName:
            'bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-950 dark:text-emerald-300 dark:ring-emerald-800',
        permission: 'leave.settings',
    },
    {
        title: 'Tetapan OT',
        href: '/tetapan-ot',
        icon: TimerReset,
        iconClassName:
            'bg-amber-100 text-amber-700 ring-amber-200 dark:bg-amber-950 dark:text-amber-300 dark:ring-amber-800',
        permission: 'overtime.settings',
    },
    {
        title: 'Tetapan Payroll',
        href: '/tetapan-payroll',
        icon: Banknote,
        iconClassName:
            'bg-violet-100 text-violet-700 ring-violet-200 dark:bg-violet-950 dark:text-violet-300 dark:ring-violet-800',
        permission: 'payroll.settings',
    },
    {
        title: 'Tetapan Tuntutan',
        href: '/tetapan-tuntutan',
        icon: ReceiptText,
        iconClassName:
            'bg-orange-100 text-orange-700 ring-orange-200 dark:bg-orange-950 dark:text-orange-300 dark:ring-orange-800',
        permission: 'claims.settings',
    },
    {
        title: 'Tetapan Statutori',
        href: '/tetapan-statutori',
        icon: ReceiptText,
        iconClassName:
            'bg-purple-100 text-purple-700 ring-purple-200 dark:bg-purple-950 dark:text-purple-300 dark:ring-purple-800',
        permission: 'payroll.settings',
    },
    {
        title: 'Tetapan Prestasi',
        href: '/tetapan-prestasi',
        icon: Target,
        iconClassName:
            'bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-950 dark:text-emerald-300 dark:ring-emerald-800',
        permission: 'performance.settings',
    },
    {
        title: 'Tetapan Pengambilan',
        href: '/tetapan-pengambilan',
        icon: UserPlus,
        iconClassName:
            'bg-pink-100 text-pink-700 ring-pink-200 dark:bg-pink-950 dark:text-pink-300 dark:ring-pink-800',
        permission: 'recruitment.settings',
    },
    {
        title: 'Tetapan Latihan',
        href: '/tetapan-latihan',
        icon: GraduationCap,
        iconClassName:
            'bg-indigo-100 text-indigo-700 ring-indigo-200 dark:bg-indigo-950 dark:text-indigo-300 dark:ring-indigo-800',
        permission: 'training.settings',
    },
    {
        title: 'Tetapan Dokumen',
        href: '/tetapan-dokumen',
        icon: FileCog,
        iconClassName:
            'bg-rose-100 text-rose-700 ring-rose-200 dark:bg-rose-950 dark:text-rose-300 dark:ring-rose-800',
        permission: 'documents.settings',
    },
    {
        title: 'Tetapan Disiplin',
        href: '/tetapan-disiplin',
        icon: ShieldAlert,
        iconClassName:
            'bg-red-100 text-red-700 ring-red-200 dark:bg-red-950 dark:text-red-300 dark:ring-red-800',
        permission: 'discipline.settings',
    },
    {
        title: 'Tetapan Clearance',
        href: '/tetapan-clearance',
        icon: ClipboardCheck,
        iconClassName:
            'bg-orange-100 text-orange-700 ring-orange-200 dark:bg-orange-950 dark:text-orange-300 dark:ring-orange-800',
        permission: 'separation.settings',
    },
    {
        title: 'Audit Trail',
        href: '/audit-trail',
        icon: History,
        iconClassName:
            'bg-rose-100 text-rose-700 ring-rose-200 dark:bg-rose-950 dark:text-rose-300 dark:ring-rose-800',
        permission: 'audit.view',
    },
    {
        title: 'Pengurusan Pengguna',
        href: '/pengguna',
        icon: ShieldCheck,
        iconClassName:
            'bg-indigo-100 text-indigo-700 ring-indigo-200 dark:bg-indigo-950 dark:text-indigo-300 dark:ring-indigo-800',
        permission: 'users.manage',
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/ridhwan-create/ibco-hr-solutions',
        icon: FolderGit2,
    },
];

export function AppSidebar() {
    const { auth, leaveApprovalAlerts } = usePage<{
        auth: Auth;
        leaveApprovalAlerts?: LeaveApprovalAlerts;
    }>().props;
    const visibleItems = (items: AuthorizedNavItem[]) =>
        items.filter((item) =>
            Array.isArray(item.permission)
                ? item.permission.some((permission) =>
                      hasPermission(auth, permission),
                  )
                : hasPermission(auth, item.permission),
        );

    const visibleMainItems = visibleItems(mainNavItems);
    const visibleEmployeeItems = visibleItems(employeeNavItems);
    const visibleSelfServiceItems = visibleItems(selfServiceNavItems);
    const visibleRecruitmentItems = visibleItems(recruitmentNavItems).map(
        (item) =>
            item.title === 'Pengambilan'
                ? {
                      ...item,
                      badge: leaveApprovalAlerts?.recruitment_total ?? 0,
                  }
                : {
                      ...item,
                      badge: leaveApprovalAlerts?.onboarding_total ?? 0,
                  },
    );
    const visibleAttendanceItems = visibleItems(attendanceNavItems).map(
        (item) =>
            item.title === 'Permohonan Cuti'
                ? {
                      ...item,
                      badge: leaveApprovalAlerts?.leave_total ?? 0,
                  }
                : item.title === 'Permohonan OT'
                  ? {
                        ...item,
                        badge: leaveApprovalAlerts?.overtime_total ?? 0,
                    }
                  : item,
    );
    const visiblePayrollItems = visibleItems(payrollNavItems).map((item) =>
        item.title === 'Permohonan Tuntutan'
            ? {
                  ...item,
                  badge: leaveApprovalAlerts?.claim_total ?? 0,
              }
            : item,
    );
    const visiblePerformanceItems = visibleItems(performanceNavItems).map(
        (item) => ({
            ...item,
            badge: leaveApprovalAlerts?.performance_total ?? 0,
        }),
    );
    const visibleTrainingItems = visibleItems(trainingNavItems).map((item) => ({
        ...item,
        badge: leaveApprovalAlerts?.training_total ?? 0,
    }));
    const visibleDocumentItems = visibleItems(documentNavItems).map((item) => ({
        ...item,
        badge: leaveApprovalAlerts?.document_total ?? 0,
    }));
    const visibleDisciplineItems = visibleItems(disciplineNavItems).map(
        (item) => ({
            ...item,
            badge: leaveApprovalAlerts?.discipline_total ?? 0,
        }),
    );
    const visibleSeparationItems = visibleItems(separationNavItems).map(
        (item) => ({
            ...item,
            badge: leaveApprovalAlerts?.separation_total ?? 0,
        }),
    );
    const visibleAdministrationItems = visibleItems(administrationNavItems);

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                {visibleMainItems.length > 0 && (
                    <NavMain
                        items={visibleMainItems}
                        label="Utama"
                        defaultOpen
                    />
                )}
                {visibleEmployeeItems.length > 0 && (
                    <NavMain items={visibleEmployeeItems} label="Pekerja" />
                )}
                {visibleSelfServiceItems.length > 0 && (
                    <NavMain
                        items={visibleSelfServiceItems}
                        label="Layan Diri Pekerja"
                    />
                )}
                {visibleRecruitmentItems.length > 0 && (
                    <NavMain
                        items={visibleRecruitmentItems}
                        label="Pengambilan & Onboarding"
                    />
                )}
                {visibleAttendanceItems.length > 0 && (
                    <NavMain
                        items={visibleAttendanceItems}
                        label="Masa & Kehadiran"
                    />
                )}
                {visiblePayrollItems.length > 0 && (
                    <NavMain
                        items={visiblePayrollItems}
                        label="Gaji & Laporan"
                    />
                )}
                {visiblePerformanceItems.length > 0 && (
                    <NavMain items={visiblePerformanceItems} label="Prestasi" />
                )}
                {visibleTrainingItems.length > 0 && (
                    <NavMain
                        items={visibleTrainingItems}
                        label="Latihan & Kompetensi"
                    />
                )}
                {visibleDocumentItems.length > 0 && (
                    <NavMain
                        items={visibleDocumentItems}
                        label="Dokumen & Surat HR"
                    />
                )}
                {visibleDisciplineItems.length > 0 && (
                    <NavMain
                        items={visibleDisciplineItems}
                        label="Disiplin & Aduan"
                    />
                )}
                {visibleSeparationItems.length > 0 && (
                    <NavMain
                        items={visibleSeparationItems}
                        label="Berhenti & Clearance"
                    />
                )}
                {visibleAdministrationItems.length > 0 && (
                    <NavMain
                        items={visibleAdministrationItems}
                        label="Pentadbiran"
                    />
                )}
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
