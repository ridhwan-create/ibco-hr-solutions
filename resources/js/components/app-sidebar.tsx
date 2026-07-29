import { Link, usePage } from '@inertiajs/react';
import {
    Banknote,
    BriefcaseBusiness,
    CalendarCheck2,
    CalendarDays,
    Clock3,
    Database,
    FileText,
    FolderGit2,
    History,
    IdCard,
    LayoutGrid,
    MapPinned,
    Navigation,
    ShieldCheck,
    SlidersHorizontal,
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
        permission: 'dashboard.view',
    },
];

const employeeNavItems: AuthorizedNavItem[] = [
    {
        title: 'Pekerja',
        href: '/pekerja',
        icon: UsersRound,
        permission: 'employees.view',
    },
    {
        title: 'Jawatan',
        href: '/jawatan',
        icon: BriefcaseBusiness,
        permission: 'positions.view',
    },
];

const selfServiceNavItems: AuthorizedNavItem[] = [
    {
        title: 'Profil Saya',
        href: '/profil-saya',
        icon: IdCard,
        permission: 'employee.profile.view',
    },
    {
        title: 'Cuti Saya',
        href: '/cuti-saya',
        icon: CalendarDays,
        permission: 'leave.self',
    },
    {
        title: 'Rakam Kehadiran',
        href: '/kehadiran/rakam',
        icon: Navigation,
        permission: 'attendance.clock',
    },
];

const attendanceNavItems: AuthorizedNavItem[] = [
    {
        title: 'Kehadiran',
        href: '/kehadiran',
        icon: CalendarCheck2,
        permission: 'attendance.view',
    },
    {
        title: 'Kehadiran Asal',
        href: '/kehadiran-asal',
        icon: Database,
        permission: 'attendance.view',
    },
    {
        title: 'Permohonan Cuti',
        href: '/permohonan-cuti',
        icon: UserRoundCheck,
        permission: ['leave.manage', 'leave.supervise'],
    },
    {
        title: 'Cuti Asal',
        href: '/cuti',
        icon: CalendarDays,
        permission: 'leave.view',
    },
    {
        title: 'Kerja Lebih Masa',
        href: '/kerja-lebih-masa',
        icon: Clock3,
        permission: 'overtime.view',
    },
];

const payrollNavItems: AuthorizedNavItem[] = [
    {
        title: 'Payroll',
        href: '/payroll',
        icon: Banknote,
        permission: 'payroll.view',
    },
    {
        title: 'Laporan Bulanan',
        href: '/laporan-bulanan',
        icon: FileText,
        permission: 'reports.view',
    },
];

const administrationNavItems: AuthorizedNavItem[] = [
    {
        title: 'Tetapan Kehadiran',
        href: '/tetapan-kehadiran',
        icon: MapPinned,
        permission: 'attendance.settings',
    },
    {
        title: 'Tetapan Cuti',
        href: '/tetapan-cuti',
        icon: SlidersHorizontal,
        permission: 'leave.settings',
    },
    {
        title: 'Audit Trail',
        href: '/audit-trail',
        icon: History,
        permission: 'audit.view',
    },
    {
        title: 'Pengurusan Pengguna',
        href: '/pengguna',
        icon: ShieldCheck,
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
    const visibleAttendanceItems = visibleItems(attendanceNavItems).map(
        (item) =>
            item.title === 'Permohonan Cuti'
                ? {
                      ...item,
                      badge: leaveApprovalAlerts?.total ?? 0,
                  }
                : item,
    );
    const visiblePayrollItems = visibleItems(payrollNavItems);
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
                    <NavMain items={visibleMainItems} label="Utama" />
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
