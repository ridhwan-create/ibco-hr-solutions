import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    Banknote,
    BriefcaseBusiness,
    CalendarCheck2,
    CalendarDays,
    Clock3,
    Database,
    FileText,
    UsersRound,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import HeadingSmall from '@/components/heading-small';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { hasPermission } from '@/lib/permissions';
import type { Auth, Permission } from '@/types';

type StatisticKey =
    | 'pekerja'
    | 'jawatan'
    | 'kehadiran'
    | 'cuti'
    | 'kerja_lebih_masa'
    | 'payroll'
    | 'laporan_bulanan';

type Statistics = Partial<Record<StatisticKey, number>>;

type AttendanceRecord = {
    id: number;
    employee_id: string | null;
    nama_pekerja: string | null;
    waktu_masuk: string | null;
    waktu_keluar: string | null;
    catatan: string | null;
};

type LeaveRecord = {
    id: number;
    employee_id: string | null;
    nama_pekerja: string | null;
    jenis_cuti: string | null;
    tarikh_mula: string | null;
    tarikh_tamat: string | null;
    status_permohonan: string | null;
};

type DashboardProps = {
    statistics: Statistics;
    recentAttendance: AttendanceRecord[];
    recentLeave: LeaveRecord[];
};

type StatisticCard = {
    key: StatisticKey;
    title: string;
    description: string;
    href: string;
    icon: LucideIcon;
    iconClassName: string;
    permission: Permission;
};

const statisticCards: StatisticCard[] = [
    {
        key: 'pekerja',
        title: 'Pekerja',
        description: 'Rekod pekerja aktif',
        href: '/pekerja',
        icon: UsersRound,
        iconClassName: 'bg-blue-500/10 text-blue-600 dark:text-blue-400',
        permission: 'employees.view',
    },
    {
        key: 'jawatan',
        title: 'Jawatan',
        description: 'Maklumat jawatan aktif',
        href: '/jawatan',
        icon: BriefcaseBusiness,
        iconClassName: 'bg-violet-500/10 text-violet-600 dark:text-violet-400',
        permission: 'positions.view',
    },
    {
        key: 'kehadiran',
        title: 'Kehadiran',
        description: 'Rekod geolocation aktif',
        href: '/kehadiran',
        icon: CalendarCheck2,
        iconClassName:
            'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
        permission: 'attendance.view',
    },
    {
        key: 'cuti',
        title: 'Cuti',
        description: 'Jumlah rekod permohonan',
        href: '/cuti',
        icon: CalendarDays,
        iconClassName: 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
        permission: 'leave.view',
    },
    {
        key: 'kerja_lebih_masa',
        title: 'Kerja Lebih Masa',
        description: 'Jumlah rekod OT',
        href: '/kerja-lebih-masa',
        icon: Clock3,
        iconClassName: 'bg-orange-500/10 text-orange-600 dark:text-orange-400',
        permission: 'overtime.view',
    },
    {
        key: 'payroll',
        title: 'Payroll',
        description: 'Jumlah rekod payroll',
        href: '/payroll',
        icon: Banknote,
        iconClassName: 'bg-cyan-500/10 text-cyan-600 dark:text-cyan-400',
        permission: 'payroll.view',
    },
    {
        key: 'laporan_bulanan',
        title: 'Laporan Bulanan',
        description: 'Jumlah laporan pekerja',
        href: '/laporan-bulanan',
        icon: FileText,
        iconClassName: 'bg-rose-500/10 text-rose-600 dark:text-rose-400',
        permission: 'reports.view',
    },
];

function formatDate(value: string | null): string {
    if (!value) {
        return '-';
    }

    const parts = value.slice(0, 10).split('-');

    if (parts.length !== 3) {
        return value;
    }

    return `${parts[2]}/${parts[1]}/${parts[0]}`;
}

function formatDateTime(value: string | null): string {
    if (!value) {
        return '-';
    }

    const time = value.slice(11, 16);

    return time ? `${formatDate(value)}, ${time}` : formatDate(value);
}

function employeeLabel(
    employeeId: string | null,
    employeeName: string | null,
): string {
    return employeeName || employeeId || 'Pekerja tidak dikenal pasti';
}

export default function Dashboard({
    statistics,
    recentAttendance,
    recentLeave,
}: DashboardProps) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const visibleStatisticCards = statisticCards.filter((card) =>
        hasPermission(auth, card.permission),
    );

    return (
        <>
            <Head title="Dashboard" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <HeadingSmall
                        title="Dashboard HR"
                        description="Ringkasan data HR lama dan kehadiran geolocation baharu."
                    />
                    <Badge
                        variant="secondary"
                        className="gap-1.5 self-start font-normal sm:self-auto"
                    >
                        <Database className="size-3.5" />
                        Sumber data: dua database berasingan
                    </Badge>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {visibleStatisticCards.map((item) => {
                        const Icon = item.icon;

                        return (
                            <Link
                                key={item.key}
                                href={item.href}
                                className="group rounded-xl focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            >
                                <Card className="h-full gap-4 transition-colors group-hover:border-foreground/20 group-hover:bg-muted/30">
                                    <CardHeader className="flex-row items-start justify-between">
                                        <div className="space-y-1.5">
                                            <CardDescription>
                                                {item.title}
                                            </CardDescription>
                                            <CardTitle className="text-3xl tabular-nums">
                                                {(
                                                    statistics[item.key] ?? 0
                                                ).toLocaleString('ms-MY')}
                                            </CardTitle>
                                        </div>
                                        <div
                                            className={`rounded-lg p-2.5 ${item.iconClassName}`}
                                        >
                                            <Icon className="size-5" />
                                        </div>
                                    </CardHeader>
                                    <CardContent className="flex items-center justify-between text-sm">
                                        <span className="text-muted-foreground">
                                            {item.description}
                                        </span>
                                        <ArrowRight className="size-4 text-muted-foreground transition-transform group-hover:translate-x-1 group-hover:text-foreground" />
                                    </CardContent>
                                </Card>
                            </Link>
                        );
                    })}
                </div>

                <div className="grid gap-6 xl:grid-cols-2">
                    <Card className="gap-4">
                        <CardHeader className="flex-row items-center justify-between">
                            <div className="space-y-1.5">
                                <CardTitle>Rekod Kehadiran Terkini</CardTitle>
                                <CardDescription>
                                    Lima rekod masuk atau keluar paling baharu.
                                </CardDescription>
                            </div>
                            <Link
                                href="/kehadiran"
                                className="text-sm font-medium text-muted-foreground hover:text-foreground"
                            >
                                Lihat semua
                            </Link>
                        </CardHeader>
                        <CardContent className="space-y-1">
                            {recentAttendance.length > 0 ? (
                                recentAttendance.map((record) => (
                                    <div
                                        key={record.id}
                                        className="flex flex-col gap-2 border-b py-3 last:border-0 sm:flex-row sm:items-center sm:justify-between"
                                    >
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-medium">
                                                {employeeLabel(
                                                    record.employee_id,
                                                    record.nama_pekerja,
                                                )}
                                            </p>
                                            <p className="truncate text-xs text-muted-foreground">
                                                {record.employee_id ||
                                                    'Tiada ID pekerja'}
                                                {record.catatan
                                                    ? ` · ${record.catatan}`
                                                    : ''}
                                            </p>
                                        </div>
                                        <div className="shrink-0 text-left sm:text-right">
                                            <p className="text-sm">
                                                {formatDateTime(
                                                    record.waktu_masuk,
                                                )}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                Keluar:{' '}
                                                {formatDateTime(
                                                    record.waktu_keluar,
                                                )}
                                            </p>
                                        </div>
                                    </div>
                                ))
                            ) : (
                                <p className="py-8 text-center text-sm text-muted-foreground">
                                    Tiada rekod kehadiran ditemui.
                                </p>
                            )}
                        </CardContent>
                    </Card>

                    <Card className="gap-4">
                        <CardHeader className="flex-row items-center justify-between">
                            <div className="space-y-1.5">
                                <CardTitle>Permohonan Cuti Terkini</CardTitle>
                                <CardDescription>
                                    Lima permohonan mengikut tarikh mula
                                    terkini.
                                </CardDescription>
                            </div>
                            <Link
                                href="/cuti"
                                className="text-sm font-medium text-muted-foreground hover:text-foreground"
                            >
                                Lihat semua
                            </Link>
                        </CardHeader>
                        <CardContent className="space-y-1">
                            {recentLeave.length > 0 ? (
                                recentLeave.map((record) => (
                                    <div
                                        key={record.id}
                                        className="flex flex-col gap-2 border-b py-3 last:border-0 sm:flex-row sm:items-center sm:justify-between"
                                    >
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-medium">
                                                {employeeLabel(
                                                    record.employee_id,
                                                    record.nama_pekerja,
                                                )}
                                            </p>
                                            <p className="truncate text-xs text-muted-foreground">
                                                {record.jenis_cuti ||
                                                    'Jenis cuti tidak dinyatakan'}
                                            </p>
                                        </div>
                                        <div className="shrink-0 text-left sm:text-right">
                                            <p className="text-sm">
                                                {formatDate(record.tarikh_mula)}{' '}
                                                –{' '}
                                                {formatDate(
                                                    record.tarikh_tamat,
                                                )}
                                            </p>
                                            <Badge
                                                variant="outline"
                                                className="mt-1 font-normal"
                                            >
                                                {record.status_permohonan ||
                                                    'Tiada status'}
                                            </Badge>
                                        </div>
                                    </div>
                                ))
                            ) : (
                                <p className="py-8 text-center text-sm text-muted-foreground">
                                    Tiada rekod cuti ditemui.
                                </p>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: '/dashboard',
        },
    ],
};
