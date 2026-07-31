import { Head, Link, router } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowRight,
    CalendarCheck2,
    CalendarDays,
    CheckCircle2,
    Clock3,
    Database,
    Download,
    FileSpreadsheet,
    Filter,
    Info,
    UsersRound,
    WalletCards,
} from 'lucide-react';
import { useMemo, useState } from 'react';
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
import { Label } from '@/components/ui/label';

type FilterOption = {
    id: number;
    name: string;
};

type PayrollSummary = {
    run_id: number | null;
    status: string;
    employee_count: number;
    gross_pay: number;
    deductions: number;
    net_pay: number;
    employer_contributions: number;
};

type Summary = {
    active_employees: number;
    working_days: number;
    attendance_days: number;
    attendance_employees: number;
    attendance_rate: number;
    average_work_hours: number;
    incomplete_clock_out: number;
    leave_requests: number;
    leave_days: number;
    pending_leave: number;
    overtime_requests: number;
    overtime_hours: number;
    pending_overtime: number;
    pending_actions: number;
    payroll: PayrollSummary;
};

type Breakdown = {
    name: string;
    days?: number;
    hours?: number;
};

type DepartmentRow = {
    department_id: number | null;
    name: string;
    employee_count: number;
    attendance_days: number;
    leave_days: number;
    overtime_hours: number;
    net_pay: number;
};

type TrendPoint = {
    period: string;
    label: string;
    attendance_rate: number;
    leave_days: number;
    overtime_hours: number;
    net_pay: number;
};

type Insight = {
    level: 'success' | 'warning' | 'info';
    title: string;
    message: string;
};

type MonthlyReport = {
    period: string;
    period_label: string;
    generated_at: string;
    filters: {
        department_id: number | null;
        office_location_id: number | null;
    };
    filter_options: {
        departments: FilterOption[];
        office_locations: FilterOption[];
    };
    summary: Summary;
    leave_breakdown: Breakdown[];
    overtime_breakdown: Breakdown[];
    departments: DepartmentRow[];
    trend: TrendPoint[];
    insights: Insight[];
};

const payrollStatusLabels: Record<string, string> = {
    not_generated: 'Belum Dijana',
    draft: 'Draf',
    hr_reviewed: 'Disemak HR',
    approved: 'Diluluskan',
    finalized: 'Dimuktamadkan',
};

function number(value: number, maximumFractionDigits = 1): string {
    return value.toLocaleString('ms-MY', {
        maximumFractionDigits,
    });
}

function money(value: number): string {
    return new Intl.NumberFormat('ms-MY', {
        style: 'currency',
        currency: 'MYR',
        maximumFractionDigits: 2,
    }).format(value);
}

function exportUrl(report: MonthlyReport): string {
    const params = new URLSearchParams({ period: report.period });

    if (report.filters.department_id !== null) {
        params.set('department_id', String(report.filters.department_id));
    }

    if (report.filters.office_location_id !== null) {
        params.set(
            'office_location_id',
            String(report.filters.office_location_id),
        );
    }

    return `/laporan-bulanan/laporan.csv?${params.toString()}`;
}

export default function MonthlyReportsIndex({
    report,
}: {
    report: MonthlyReport;
}) {
    const [period, setPeriod] = useState(report.period);
    const [departmentId, setDepartmentId] = useState(
        report.filters.department_id === null
            ? ''
            : String(report.filters.department_id),
    );
    const [officeLocationId, setOfficeLocationId] = useState(
        report.filters.office_location_id === null
            ? ''
            : String(report.filters.office_location_id),
    );
    const filtersActive =
        report.filters.department_id !== null ||
        report.filters.office_location_id !== null;
    const metricCards = [
        {
            label: 'Pekerja Aktif',
            value: number(report.summary.active_employees, 0),
            detail: `${number(report.summary.working_days, 0)} hari bekerja`,
            icon: UsersRound,
            className: 'bg-blue-500/10 text-blue-600 dark:text-blue-400',
        },
        {
            label: 'Kadar Kehadiran',
            value: `${number(report.summary.attendance_rate)}%`,
            detail: `${number(report.summary.attendance_days, 0)} rekod kehadiran`,
            icon: CalendarCheck2,
            className:
                'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
        },
        {
            label: 'Cuti Diluluskan',
            value: `${number(report.summary.leave_days)} hari`,
            detail: `${number(report.summary.leave_requests, 0)} permohonan`,
            icon: CalendarDays,
            className: 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
        },
        {
            label: 'OT Diluluskan',
            value: `${number(report.summary.overtime_hours)} jam`,
            detail: `${number(report.summary.overtime_requests, 0)} permohonan`,
            icon: Clock3,
            className: 'bg-orange-500/10 text-orange-600 dark:text-orange-400',
        },
        {
            label: 'Gaji Bersih',
            value: money(report.summary.payroll.net_pay),
            detail:
                payrollStatusLabels[report.summary.payroll.status] ??
                report.summary.payroll.status,
            icon: WalletCards,
            className: 'bg-cyan-500/10 text-cyan-600 dark:text-cyan-400',
        },
    ];

    const applyFilters = () => {
        router.get(
            '/laporan-bulanan',
            {
                period,
                department_id: departmentId || undefined,
                office_location_id: officeLocationId || undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    const resetFilters = () => {
        setDepartmentId('');
        setOfficeLocationId('');
        router.get(
            '/laporan-bulanan',
            { period },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    return (
        <>
            <Head title={`Laporan Bulanan ${report.period_label}`} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <HeadingSmall
                        title="Laporan Bulanan & Dashboard Eksekutif"
                        description={`Ringkasan kehadiran, cuti, OT dan payroll bagi ${report.period_label}.`}
                    />
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <Link href="/laporan-bulanan-asal">
                                <Database className="size-4" />
                                Laporan Asal
                            </Link>
                        </Button>
                        <Button asChild>
                            <a href={exportUrl(report)}>
                                <Download className="size-4" />
                                Eksport CSV
                            </a>
                        </Button>
                    </div>
                </div>

                <Card className="gap-4">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <Filter className="size-4" />
                            Tapisan Laporan
                        </CardTitle>
                        <CardDescription>
                            Pilih bulan, jabatan atau lokasi pejabat untuk
                            mengecilkan skop laporan.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 lg:grid-cols-[180px_1fr_1fr_auto] lg:items-end">
                            <div className="space-y-2">
                                <Label htmlFor="period">Bulan</Label>
                                <Input
                                    id="period"
                                    type="month"
                                    value={period}
                                    onChange={(event) =>
                                        setPeriod(event.target.value)
                                    }
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="department_id">Jabatan</Label>
                                <select
                                    id="department_id"
                                    value={departmentId}
                                    onChange={(event) =>
                                        setDepartmentId(event.target.value)
                                    }
                                    className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                >
                                    <option value="">Semua jabatan</option>
                                    {report.filter_options.departments.map(
                                        (department) => (
                                            <option
                                                key={department.id}
                                                value={department.id}
                                            >
                                                {department.name}
                                            </option>
                                        ),
                                    )}
                                </select>
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="office_location_id">
                                    Lokasi Pejabat
                                </Label>
                                <select
                                    id="office_location_id"
                                    value={officeLocationId}
                                    onChange={(event) =>
                                        setOfficeLocationId(event.target.value)
                                    }
                                    className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                >
                                    <option value="">Semua lokasi</option>
                                    {report.filter_options.office_locations.map(
                                        (office) => (
                                            <option
                                                key={office.id}
                                                value={office.id}
                                            >
                                                {office.name}
                                            </option>
                                        ),
                                    )}
                                </select>
                            </div>
                            <div className="flex gap-2">
                                <Button onClick={applyFilters}>
                                    Jana Laporan
                                </Button>
                                {filtersActive && (
                                    <Button
                                        variant="outline"
                                        onClick={resetFilters}
                                    >
                                        Reset
                                    </Button>
                                )}
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    {metricCards.map((metric) => {
                        const Icon = metric.icon;

                        return (
                            <Card key={metric.label} className="gap-4">
                                <CardHeader className="flex-row items-start justify-between">
                                    <div className="min-w-0 space-y-1.5">
                                        <CardDescription>
                                            {metric.label}
                                        </CardDescription>
                                        <CardTitle className="truncate text-2xl tabular-nums">
                                            {metric.value}
                                        </CardTitle>
                                    </div>
                                    <div
                                        className={`rounded-lg p-2 ${metric.className}`}
                                    >
                                        <Icon className="size-4" />
                                    </div>
                                </CardHeader>
                                <CardContent className="text-xs text-muted-foreground">
                                    {metric.detail}
                                </CardContent>
                            </Card>
                        );
                    })}
                </div>

                <div className="grid gap-6 xl:grid-cols-[1.5fr_1fr]">
                    <Card className="gap-4">
                        <CardHeader>
                            <CardTitle>Trend Enam Bulan</CardTitle>
                            <CardDescription>
                                Perbandingan kadar kehadiran dan jam OT sehingga
                                {` ${report.period_label}`}.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-6 lg:grid-cols-2">
                            <TrendBars
                                title="Kadar Kehadiran"
                                points={report.trend}
                                valueKey="attendance_rate"
                                fixedMaximum={100}
                                formatValue={(value) => `${number(value)}%`}
                                barClassName="bg-emerald-500"
                            />
                            <TrendBars
                                title="Jam OT"
                                points={report.trend}
                                valueKey="overtime_hours"
                                formatValue={(value) => `${number(value)} jam`}
                                barClassName="bg-orange-500"
                            />
                        </CardContent>
                    </Card>

                    <Card className="gap-4">
                        <CardHeader>
                            <CardTitle>Perhatian Pengurusan</CardTitle>
                            <CardDescription>
                                Perkara yang memerlukan semakan berdasarkan data
                                bulan dipilih.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {report.insights.map((insight) => (
                                <InsightItem
                                    key={`${insight.title}-${insight.message}`}
                                    insight={insight}
                                />
                            ))}
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-6 xl:grid-cols-3">
                    <BreakdownCard
                        title="Pecahan Cuti"
                        description="Hari cuti diluluskan mengikut jenis."
                        items={report.leave_breakdown}
                        valueKey="days"
                        unit="hari"
                        emptyLabel="Tiada cuti diluluskan bagi bulan ini."
                    />
                    <BreakdownCard
                        title="Pecahan OT"
                        description="Jam OT diluluskan mengikut jenis."
                        items={report.overtime_breakdown}
                        valueKey="hours"
                        unit="jam"
                        emptyLabel="Tiada OT diluluskan bagi bulan ini."
                    />
                    <Card className="gap-4">
                        <CardHeader>
                            <CardTitle>Ringkasan Payroll</CardTitle>
                            <CardDescription>
                                Nilai payroll mengikut skop tapisan semasa.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <SummaryRow
                                label="Status"
                                value={
                                    payrollStatusLabels[
                                        report.summary.payroll.status
                                    ] ?? report.summary.payroll.status
                                }
                            />
                            <SummaryRow
                                label="Pekerja"
                                value={number(
                                    report.summary.payroll.employee_count,
                                    0,
                                )}
                            />
                            <SummaryRow
                                label="Gaji kasar"
                                value={money(report.summary.payroll.gross_pay)}
                            />
                            <SummaryRow
                                label="Potongan"
                                value={money(report.summary.payroll.deductions)}
                            />
                            <SummaryRow
                                label="Caruman majikan"
                                value={money(
                                    report.summary.payroll
                                        .employer_contributions,
                                )}
                            />
                            <div className="flex items-center justify-between border-t pt-3">
                                <span className="font-medium">Gaji bersih</span>
                                <span className="font-semibold tabular-nums">
                                    {money(report.summary.payroll.net_pay)}
                                </span>
                            </div>
                            {report.summary.payroll.run_id !== null && (
                                <Button
                                    variant="outline"
                                    className="w-full"
                                    asChild
                                >
                                    <Link
                                        href={`/payroll/${report.summary.payroll.run_id}`}
                                    >
                                        Buka Payroll
                                        <ArrowRight className="size-4" />
                                    </Link>
                                </Button>
                            )}
                        </CardContent>
                    </Card>
                </div>

                <Card className="gap-4">
                    <CardHeader className="flex-row items-start justify-between gap-4">
                        <div className="space-y-1.5">
                            <CardTitle>Prestasi Mengikut Jabatan</CardTitle>
                            <CardDescription>
                                Perbandingan tenaga kerja, kehadiran, cuti, OT
                                dan gaji bersih.
                            </CardDescription>
                        </div>
                        <Badge variant="outline" className="shrink-0">
                            {report.departments.length} jabatan
                        </Badge>
                    </CardHeader>
                    <CardContent>
                        {report.departments.length > 0 ? (
                            <div className="overflow-x-auto rounded-lg border">
                                <table className="w-full text-sm">
                                    <thead className="bg-muted/50 text-left text-xs text-muted-foreground">
                                        <tr>
                                            <th className="px-4 py-3 font-medium">
                                                Jabatan
                                            </th>
                                            <th className="px-4 py-3 text-right font-medium">
                                                Pekerja
                                            </th>
                                            <th className="px-4 py-3 text-right font-medium">
                                                Kehadiran
                                            </th>
                                            <th className="px-4 py-3 text-right font-medium">
                                                Cuti
                                            </th>
                                            <th className="px-4 py-3 text-right font-medium">
                                                OT
                                            </th>
                                            <th className="px-4 py-3 text-right font-medium">
                                                Gaji Bersih
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {report.departments.map(
                                            (department) => (
                                                <tr
                                                    key={
                                                        department.department_id ??
                                                        department.name
                                                    }
                                                    className="border-t"
                                                >
                                                    <td className="px-4 py-3 font-medium">
                                                        {department.name}
                                                    </td>
                                                    <td className="px-4 py-3 text-right tabular-nums">
                                                        {number(
                                                            department.employee_count,
                                                            0,
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-3 text-right tabular-nums">
                                                        {number(
                                                            department.attendance_days,
                                                            0,
                                                        )}{' '}
                                                        hari
                                                    </td>
                                                    <td className="px-4 py-3 text-right tabular-nums">
                                                        {number(
                                                            department.leave_days,
                                                        )}{' '}
                                                        hari
                                                    </td>
                                                    <td className="px-4 py-3 text-right tabular-nums">
                                                        {number(
                                                            department.overtime_hours,
                                                        )}{' '}
                                                        jam
                                                    </td>
                                                    <td className="px-4 py-3 text-right font-medium tabular-nums">
                                                        {money(
                                                            department.net_pay,
                                                        )}
                                                    </td>
                                                </tr>
                                            ),
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        ) : (
                            <EmptyState label="Tiada pekerja aktif dalam skop tapisan ini." />
                        )}
                    </CardContent>
                </Card>

                <div className="flex flex-col gap-2 rounded-lg border bg-muted/20 p-4 text-sm text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-2">
                        <FileSpreadsheet className="size-4" />
                        Laporan dijana terus daripada snapshot payroll dan rekod
                        operasi sistem.
                    </div>
                    <span>
                        <code>db_spp</code> digunakan sebagai sumber rujukan
                        baca sahaja.
                    </span>
                </div>
            </div>
        </>
    );
}

function TrendBars({
    title,
    points,
    valueKey,
    fixedMaximum,
    formatValue,
    barClassName,
}: {
    title: string;
    points: TrendPoint[];
    valueKey: 'attendance_rate' | 'overtime_hours';
    fixedMaximum?: number;
    formatValue: (value: number) => string;
    barClassName: string;
}) {
    const maximum = useMemo(
        () =>
            fixedMaximum ??
            Math.max(...points.map((point) => point[valueKey]), 1),
        [fixedMaximum, points, valueKey],
    );

    return (
        <div className="space-y-3">
            <p className="text-sm font-medium">{title}</p>
            <div className="grid h-52 grid-cols-6 items-end gap-2 border-b">
                {points.map((point) => {
                    const value = point[valueKey];
                    const height =
                        value > 0
                            ? Math.max(
                                  4,
                                  Math.min(100, (value / maximum) * 100),
                              )
                            : 0;

                    return (
                        <div
                            key={`${title}-${point.period}`}
                            className="flex h-full min-w-0 flex-col justify-end gap-2"
                        >
                            <span className="truncate text-center text-[10px] font-medium tabular-nums">
                                {formatValue(value)}
                            </span>
                            <div className="flex h-36 items-end justify-center rounded-t bg-muted/30 px-1">
                                <div
                                    className={`w-full max-w-10 rounded-t transition-all ${barClassName}`}
                                    style={{ height: `${height}%` }}
                                />
                            </div>
                            <span className="truncate pb-2 text-center text-[10px] text-muted-foreground">
                                {point.label}
                            </span>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}

function InsightItem({ insight }: { insight: Insight }) {
    const config = {
        success: {
            icon: CheckCircle2,
            className:
                'border-emerald-500/30 bg-emerald-500/5 text-emerald-700 dark:text-emerald-300',
        },
        warning: {
            icon: AlertTriangle,
            className:
                'border-amber-500/30 bg-amber-500/5 text-amber-700 dark:text-amber-300',
        },
        info: {
            icon: Info,
            className:
                'border-blue-500/30 bg-blue-500/5 text-blue-700 dark:text-blue-300',
        },
    }[insight.level];
    const Icon = config.icon;

    return (
        <div className={`flex gap-3 rounded-lg border p-3 ${config.className}`}>
            <Icon className="mt-0.5 size-4 shrink-0" />
            <div>
                <p className="text-sm font-medium">{insight.title}</p>
                <p className="mt-0.5 text-xs opacity-80">{insight.message}</p>
            </div>
        </div>
    );
}

function BreakdownCard({
    title,
    description,
    items,
    valueKey,
    unit,
    emptyLabel,
}: {
    title: string;
    description: string;
    items: Breakdown[];
    valueKey: 'days' | 'hours';
    unit: string;
    emptyLabel: string;
}) {
    const maximum = Math.max(...items.map((item) => item[valueKey] ?? 0), 1);

    return (
        <Card className="gap-4">
            <CardHeader>
                <CardTitle>{title}</CardTitle>
                <CardDescription>{description}</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                {items.length > 0 ? (
                    items.map((item) => {
                        const value = item[valueKey] ?? 0;

                        return (
                            <div key={item.name} className="space-y-1.5">
                                <div className="flex justify-between gap-3 text-sm">
                                    <span className="truncate">
                                        {item.name}
                                    </span>
                                    <span className="shrink-0 font-medium tabular-nums">
                                        {number(value)} {unit}
                                    </span>
                                </div>
                                <div className="h-2 overflow-hidden rounded-full bg-muted">
                                    <div
                                        className="h-full rounded-full bg-primary"
                                        style={{
                                            width: `${(value / maximum) * 100}%`,
                                        }}
                                    />
                                </div>
                            </div>
                        );
                    })
                ) : (
                    <EmptyState label={emptyLabel} />
                )}
            </CardContent>
        </Card>
    );
}

function SummaryRow({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex items-center justify-between gap-4 text-sm">
            <span className="text-muted-foreground">{label}</span>
            <span className="text-right font-medium tabular-nums">{value}</span>
        </div>
    );
}

function EmptyState({ label }: { label: string }) {
    return (
        <div className="py-8 text-center text-sm text-muted-foreground">
            {label}
        </div>
    );
}

MonthlyReportsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Laporan Bulanan',
            href: '/laporan-bulanan',
        },
    ],
};
