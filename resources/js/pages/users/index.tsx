import { Head, Link, router } from '@inertiajs/react';
import {
    DatabaseZap,
    KeyRound,
    Pencil,
    Plus,
    Search,
    ShieldCheck,
    UserCog,
    UsersRound,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import HeadingSmall from '@/components/heading-small';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { Permission, UserRole } from '@/types';

type ManagedUser = {
    id: number;
    name: string;
    email: string;
    roles: UserRole[];
    role_labels: string[];
    email_verified_at: string | null;
    created_at: string;
    is_current_user: boolean;
    employee: {
        id: number;
        employee_id: string | null;
        name: string | null;
        office: string | null;
    } | null;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type RoleOption = {
    value: UserRole;
    label: string;
    description: string;
    permissions: Permission[];
};

type UserManagementProps = {
    users: {
        data: ManagedUser[];
        current_page: number;
        last_page: number;
        from: number | null;
        to: number | null;
        total: number;
        links: PaginationLink[];
    };
    filters: {
        search?: string;
    };
    roleCounts: Record<UserRole, number>;
    roleOptions: RoleOption[];
};

const permissionLabels: Record<Permission, string> = {
    'dashboard.view': 'Dashboard',
    'employees.view': 'Pekerja',
    'employees.manage': 'Urus Pekerja',
    'positions.view': 'Jawatan',
    'positions.manage': 'Urus Jawatan',
    'attendance.view': 'Kehadiran',
    'attendance.manage': 'Urus Kehadiran',
    'attendance.clock': 'Rakam Kehadiran Sendiri',
    'attendance.settings': 'Tetapan Geofence',
    'employee.profile.view': 'Profil Sendiri',
    'employee.profile.update': 'Kemas Kini Profil Sendiri',
    'leave.view': 'Cuti Asal',
    'leave.manage': 'Urus Permohonan Cuti',
    'leave.supervise': 'Semakan Penyelia',
    'leave.settings': 'Tetapan Cuti',
    'leave.self': 'Cuti Sendiri',
    'leave.apply': 'Mohon Cuti',
    'overtime.view': 'OT Asal',
    'overtime.manage': 'Urus Permohonan OT',
    'overtime.supervise': 'Semakan Penyelia OT',
    'overtime.settings': 'Tetapan OT',
    'overtime.self': 'OT Sendiri',
    'overtime.apply': 'Mohon OT',
    'roster.view': 'Jadual & Roster',
    'roster.manage': 'Urus Roster',
    'roster.publish': 'Terbit & Kunci Roster',
    'roster.supervise': 'Semak Pertukaran Syif',
    'roster.settings': 'Tetapan Syif',
    'roster.self': 'Jadual Sendiri',
    'roster.swap': 'Mohon Pertukaran Syif',
    'performance.view': 'Prestasi & KPI',
    'performance.manage': 'Urus Penilaian Prestasi',
    'performance.supervise': 'Penilaian Penyelia',
    'performance.moderate': 'Moderasi Prestasi',
    'performance.finalize': 'Muktamad Penilaian',
    'performance.settings': 'Tetapan Prestasi',
    'performance.self': 'Prestasi Sendiri',
    'claims.view': 'Tuntutan',
    'claims.manage': 'Urus Tuntutan',
    'claims.supervise': 'Semakan Penyelia Tuntutan',
    'claims.settings': 'Tetapan Tuntutan',
    'claims.self': 'Tuntutan Sendiri',
    'claims.apply': 'Mohon Tuntutan',
    'payroll.view': 'Payroll',
    'payroll.manage': 'Urus Payroll',
    'payroll.approve': 'Lulus & Muktamad Payroll',
    'payroll.settings': 'Tetapan Payroll',
    'payslip.self': 'Slip Gaji Sendiri',
    'reports.view': 'Laporan Bulanan',
    'audit.view': 'Audit Trail',
    'users.manage': 'Pengurusan Pengguna',
};

const roleStyles: Record<UserRole, string> = {
    super_admin:
        'border-violet-500/30 bg-violet-500/10 text-violet-700 dark:text-violet-300',
    hr_admin:
        'border-blue-500/30 bg-blue-500/10 text-blue-700 dark:text-blue-300',
    supervisor:
        'border-cyan-500/30 bg-cyan-500/10 text-cyan-700 dark:text-cyan-300',
    viewer: 'border-slate-500/30 bg-slate-500/10 text-slate-700 dark:text-slate-300',
    employee:
        'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
};

function formatDate(value: string): string {
    const parts = value.slice(0, 10).split('-');

    if (parts.length !== 3) {
        return value;
    }

    return `${parts[2]}/${parts[1]}/${parts[0]}`;
}

function paginationLabel(label: string): string {
    return label
        .replace('&laquo; Previous', 'Sebelum')
        .replace('Next &raquo;', 'Seterusnya');
}

function RoleBadges({ user }: { user: ManagedUser }) {
    return (
        <div className="space-y-1.5">
            <div className="flex flex-wrap gap-1.5">
                {user.roles.map((role, index) => (
                    <Badge
                        key={role}
                        variant="outline"
                        className={roleStyles[role]}
                    >
                        {user.role_labels[index]}
                    </Badge>
                ))}
            </div>
            {user.is_current_user && (
                <p className="text-xs text-muted-foreground">Akaun anda</p>
            )}
        </div>
    );
}

export default function UserManagement({
    users,
    filters,
    roleCounts,
    roleOptions,
}: UserManagementProps) {
    const [search, setSearch] = useState(filters.search ?? '');

    useEffect(() => {
        if (search === (filters.search ?? '')) {
            return;
        }

        const timer = window.setTimeout(() => {
            router.get(
                '/pengguna',
                { search },
                {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                },
            );
        }, 450);

        return () => window.clearTimeout(timer);
    }, [filters.search, search]);

    const goToPage = (url: string | null) => {
        if (!url) {
            return;
        }

        const page = new URL(url, window.location.origin).searchParams.get(
            'page',
        );

        router.get(
            '/pengguna',
            { search, page },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    return (
        <>
            <Head title="Roles & Permissions" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div className="space-y-2">
                        <HeadingSmall
                            title="Roles & Permissions"
                            description="Urus tahap akses pengguna kepada modul IBCO HR Solutions."
                        />
                        <Badge
                            variant="secondary"
                            className="gap-1.5 font-normal"
                        >
                            <ShieldCheck className="size-3.5" />
                            Database sistem: ibco-hr-solutions
                        </Badge>
                    </div>

                    <div className="flex w-full flex-col gap-3 lg:max-w-xl">
                        <div className="flex flex-wrap justify-end gap-2">
                            <Button asChild variant="outline">
                                <Link href="/pengguna/reset-kata-laluan">
                                    <KeyRound />
                                    Reset Kata Laluan
                                </Link>
                            </Button>
                            <Button asChild variant="outline">
                                <Link href="/pengguna/import-pekerja">
                                    <DatabaseZap />
                                    Import Pekerja
                                </Link>
                            </Button>
                            <Button asChild>
                                <Link href="/pengguna/create">
                                    <Plus />
                                    Tambah Pengguna
                                </Link>
                            </Button>
                        </div>
                        <div className="relative w-full lg:ml-auto lg:max-w-sm">
                            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Cari nama atau e-mel..."
                                className="pl-9"
                                aria-label="Cari pengguna"
                            />
                        </div>
                    </div>
                </div>

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    {roleOptions.map((role) => (
                        <Card key={role.value} className="gap-4">
                            <CardHeader className="flex-row items-start justify-between">
                                <div className="space-y-1.5">
                                    <CardTitle className="text-base">
                                        {role.label}
                                    </CardTitle>
                                    <CardDescription>
                                        {role.description}
                                    </CardDescription>
                                </div>
                                <div className="rounded-lg bg-primary/10 p-2.5 text-primary">
                                    {role.value === 'super_admin' ? (
                                        <ShieldCheck className="size-5" />
                                    ) : role.value === 'hr_admin' ? (
                                        <UserCog className="size-5" />
                                    ) : (
                                        <UsersRound className="size-5" />
                                    )}
                                </div>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <p className="text-3xl font-semibold tabular-nums">
                                    {(
                                        roleCounts[role.value] ?? 0
                                    ).toLocaleString('ms-MY')}
                                </p>
                                <div className="flex flex-wrap gap-1.5">
                                    {role.permissions.map((permission) => (
                                        <Badge
                                            key={permission}
                                            variant="outline"
                                            className="font-normal"
                                        >
                                            {permissionLabels[permission]}
                                        </Badge>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <Card className="gap-0 overflow-hidden">
                    <CardHeader className="border-b">
                        <CardTitle className="flex items-center gap-2">
                            <KeyRound className="size-5 text-muted-foreground" />
                            Senarai Pengguna
                        </CardTitle>
                        <CardDescription>
                            Daftar, pautkan pekerja, kemas kini akaun dan
                            tetapkan tahap akses pengguna sistem.
                        </CardDescription>
                    </CardHeader>

                    <div className="hidden overflow-x-auto md:block">
                        <Table>
                            <TableHeader className="bg-muted/60">
                                <TableRow>
                                    <TableHead>Pengguna</TableHead>
                                    <TableHead>Pekerja Dipautkan</TableHead>
                                    <TableHead>Status E-mel</TableHead>
                                    <TableHead>Tarikh Daftar</TableHead>
                                    <TableHead className="min-w-56">
                                        Role Pengguna
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Tindakan
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {users.data.length > 0 ? (
                                    users.data.map((user) => (
                                        <TableRow key={user.id}>
                                            <TableCell>
                                                <p className="font-medium">
                                                    {user.name}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {user.email}
                                                </p>
                                            </TableCell>
                                            <TableCell>
                                                {user.employee ? (
                                                    <div>
                                                        <p className="font-medium">
                                                            {user.employee
                                                                .name ||
                                                                'Tanpa nama'}
                                                        </p>
                                                        <p className="text-xs text-muted-foreground">
                                                            {user.employee
                                                                .employee_id ||
                                                                `#${user.employee.id}`}
                                                            {user.employee
                                                                .office
                                                                ? ` · ${user.employee.office}`
                                                                : ''}
                                                        </p>
                                                    </div>
                                                ) : (
                                                    <span className="text-sm text-muted-foreground">
                                                        Tidak berkaitan
                                                    </span>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <Badge
                                                    variant={
                                                        user.email_verified_at
                                                            ? 'secondary'
                                                            : 'outline'
                                                    }
                                                >
                                                    {user.email_verified_at
                                                        ? 'Disahkan'
                                                        : 'Belum disahkan'}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>
                                                {formatDate(user.created_at)}
                                            </TableCell>
                                            <TableCell>
                                                <RoleBadges user={user} />
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <Button
                                                    asChild
                                                    size="sm"
                                                    variant="outline"
                                                >
                                                    <Link
                                                        href={`/pengguna/${user.id}/edit`}
                                                    >
                                                        <Pencil />
                                                        Edit
                                                    </Link>
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    ))
                                ) : (
                                    <TableRow>
                                        <TableCell
                                            colSpan={6}
                                            className="h-28 text-center text-muted-foreground"
                                        >
                                            Tiada pengguna ditemui.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </div>

                    <CardContent className="space-y-3 py-4 md:hidden">
                        {users.data.length > 0 ? (
                            users.data.map((user) => (
                                <div
                                    key={user.id}
                                    className="space-y-4 rounded-lg border p-4"
                                >
                                    <div>
                                        <p className="font-medium">
                                            {user.name}
                                        </p>
                                        <p className="text-sm break-all text-muted-foreground">
                                            {user.email}
                                        </p>
                                    </div>
                                    <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                        <Badge variant="outline">
                                            {user.email_verified_at
                                                ? 'E-mel disahkan'
                                                : 'E-mel belum disahkan'}
                                        </Badge>
                                        <span>
                                            Daftar {formatDate(user.created_at)}
                                        </span>
                                    </div>
                                    {user.employee && (
                                        <div className="rounded-md bg-muted p-3 text-sm">
                                            <p className="font-medium">
                                                {user.employee.name ||
                                                    'Tanpa nama'}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {user.employee.employee_id ||
                                                    `#${user.employee.id}`}
                                                {user.employee.office
                                                    ? ` · ${user.employee.office}`
                                                    : ''}
                                            </p>
                                        </div>
                                    )}
                                    <RoleBadges user={user} />
                                    <Button
                                        asChild
                                        size="sm"
                                        variant="outline"
                                        className="w-full"
                                    >
                                        <Link
                                            href={`/pengguna/${user.id}/edit`}
                                        >
                                            <Pencil />
                                            Kemas Kini Pengguna
                                        </Link>
                                    </Button>
                                </div>
                            ))
                        ) : (
                            <p className="py-8 text-center text-sm text-muted-foreground">
                                Tiada pengguna ditemui.
                            </p>
                        )}
                    </CardContent>
                </Card>

                <div className="flex flex-col gap-3 rounded-xl border bg-card px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <p className="text-sm text-muted-foreground">
                        Menunjukkan {users.from ?? 0} hingga {users.to ?? 0}{' '}
                        daripada {users.total} pengguna
                    </p>

                    {users.last_page > 1 && (
                        <div className="flex flex-wrap gap-1">
                            {users.links.map((link, index) => (
                                <button
                                    key={`${link.label}-${index}`}
                                    type="button"
                                    disabled={!link.url}
                                    onClick={() => goToPage(link.url)}
                                    className={`min-w-9 rounded-md border px-3 py-1.5 text-sm transition-colors ${
                                        link.active
                                            ? 'border-primary bg-primary text-primary-foreground'
                                            : 'border-input bg-background hover:bg-accent'
                                    } disabled:pointer-events-none disabled:opacity-40`}
                                >
                                    {paginationLabel(link.label)}
                                </button>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}

UserManagement.layout = {
    breadcrumbs: [
        {
            title: 'Roles & Permissions',
            href: '/pengguna',
        },
    ],
};
