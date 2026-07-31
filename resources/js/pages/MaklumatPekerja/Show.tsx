import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    Banknote,
    BriefcaseBusiness,
    Building2,
    Cake,
    CalendarCheck2,
    CalendarDays,
    Clock3,
    CreditCard,
    Database,
    Flag,
    Globe2,
    Heart,
    History,
    IdCard,
    Landmark,
    Mail,
    MapPin,
    Pencil,
    Phone,
    ShieldCheck,
    UserRound,
    UsersRound,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

type NullableValue = string | number | null;

type Employee = {
    id: number;
    employee_id: string | null;
    nama: string | null;
    nric: string | null;
    alamat: string | null;
    tarikh_lahir: string | null;
    kewarganegaraan: string | null;
    no_telefon: string | null;
    email: string | null;
    jantina: string | null;
    agama: string | null;
    bangsa: string | null;
    status_perkahwinan: string | null;
    status: string | null;
};

type Employment = {
    id: number;
    jawatan: string | null;
    jabatan: string | null;
    tarikh_lapor_diri: string | null;
    tarikh_tamat_tempoh_cubaan: string | null;
    gaji_asas?: string | number | null;
    bank?: string | null;
    no_akaun?: string | null;
    no_kwsp?: string | null;
    no_perkeso?: string | null;
    kelayakan_cuti: string | null;
    aktif?: number;
    tarikh_tamat?: string | null;
};

type Statistics = {
    kehadiran: number;
    cuti: number;
    kerja_lebih_masa: number;
    payroll?: number;
};

type AttendanceRecord = {
    id: number;
    pilihan_jam: string | null;
    waktu_masuk: string | null;
    waktu_keluar: string | null;
    catatan: string | null;
};

type LeaveRecord = {
    id: number;
    jenis_cuti: string | null;
    tarikh_mula: string | null;
    tarikh_tamat: string | null;
    bilangan_hari: string | null;
    status_permohonan: string | null;
};

type OvertimeRecord = {
    id: number;
    jenis_ot: string | null;
    tarikh: string | null;
    waktu_masuk: string | null;
    waktu_keluar: string | null;
    catatan: string | null;
};

type PayrollRecord = {
    id: number;
    tempoh_gaji: string | null;
    bulan: string | null;
    no_kwsp: string | null;
    no_socso: string | null;
    no_akaun: string | null;
};

type EmployeeProfileProps = {
    pekerja: Employee;
    jawatan: Employment | null;
    jawatanHistory: Employment[];
    statistics: Statistics;
    recentAttendance: AttendanceRecord[];
    recentLeave: LeaveRecord[];
    recentOvertime: OvertimeRecord[];
    recentPayroll: PayrollRecord[];
    canViewPayroll: boolean;
    canManage: boolean;
    canManagePositions: boolean;
};

type InformationItemProps = {
    icon: LucideIcon;
    label: string;
    value: NullableValue;
    fullWidth?: boolean;
    type?: 'date' | 'currency';
};

type SummaryCard = {
    key: keyof Statistics;
    label: string;
    description: string;
    icon: LucideIcon;
    iconClassName: string;
};

const summaryCards: SummaryCard[] = [
    {
        key: 'kehadiran',
        label: 'Kehadiran',
        description: 'Jumlah rekod aktif',
        icon: CalendarCheck2,
        iconClassName:
            'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
    },
    {
        key: 'cuti',
        label: 'Cuti',
        description: 'Jumlah permohonan',
        icon: CalendarDays,
        iconClassName: 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
    },
    {
        key: 'kerja_lebih_masa',
        label: 'Kerja Lebih Masa',
        description: 'Jumlah rekod OT',
        icon: Clock3,
        iconClassName: 'bg-orange-500/10 text-orange-600 dark:text-orange-400',
    },
    {
        key: 'payroll',
        label: 'Payroll',
        description: 'Jumlah rekod gaji',
        icon: Banknote,
        iconClassName: 'bg-cyan-500/10 text-cyan-600 dark:text-cyan-400',
    },
];

function displayValue(value: NullableValue): string {
    if (value === null || value === undefined || value === '') {
        return '-';
    }

    return String(value);
}

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

function formatTime(value: string | null): string {
    return value ? value.slice(0, 5) : '-';
}

function formatCurrency(value: NullableValue): string {
    if (value === null || value === undefined || value === '') {
        return '-';
    }

    const amount = Number(value);

    if (Number.isNaN(amount)) {
        return String(value);
    }

    return new Intl.NumberFormat('ms-MY', {
        style: 'currency',
        currency: 'MYR',
    }).format(amount);
}

function getInitials(name: string | null): string {
    if (!name) {
        return 'P';
    }

    return name
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((word) => word.charAt(0))
        .join('')
        .toUpperCase();
}

function InformationItem({
    icon: Icon,
    label,
    value,
    fullWidth = false,
    type,
}: InformationItemProps) {
    const formattedValue =
        type === 'date'
            ? formatDate(value === null ? null : String(value))
            : type === 'currency'
              ? formatCurrency(value)
              : displayValue(value);

    return (
        <div
            className={`flex gap-3 rounded-lg border bg-muted/20 p-3 ${
                fullWidth ? 'sm:col-span-2' : ''
            }`}
        >
            <div className="mt-0.5 rounded-md bg-background p-2 text-muted-foreground shadow-sm">
                <Icon className="size-4" />
            </div>
            <div className="min-w-0">
                <p className="text-xs text-muted-foreground">{label}</p>
                <p className="mt-1 text-sm font-medium break-words">
                    {formattedValue}
                </p>
            </div>
        </div>
    );
}

function EmptyRecords({ label }: { label: string }) {
    return (
        <div className="rounded-lg border border-dashed px-4 py-8 text-center text-sm text-muted-foreground">
            Tiada rekod {label} untuk pekerja ini.
        </div>
    );
}

export default function EmployeeProfile({
    pekerja,
    jawatan,
    jawatanHistory,
    statistics,
    recentAttendance,
    recentLeave,
    recentOvertime,
    recentPayroll,
    canViewPayroll,
    canManage,
    canManagePositions,
}: EmployeeProfileProps) {
    const employeeSearch = encodeURIComponent(
        pekerja.employee_id || pekerja.nama || '',
    );

    return (
        <>
            <Head title={`Profil ${pekerja.nama || 'Pekerja'}`} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <Button asChild variant="outline" size="sm">
                        <Link href="/pekerja">
                            <ArrowLeft />
                            Kembali ke Senarai Pekerja
                        </Link>
                    </Button>
                    <div className="flex flex-col gap-2 sm:flex-row">
                        {canManagePositions && (
                            <Button asChild variant="outline" size="sm">
                                <Link
                                    href={
                                        jawatan
                                            ? `/jawatan/${jawatan.id}/edit`
                                            : `/jawatan/create?employee_id=${pekerja.id}`
                                    }
                                >
                                    <BriefcaseBusiness />
                                    {jawatan
                                        ? 'Tukar Jawatan'
                                        : 'Tetapkan Jawatan'}
                                </Link>
                            </Button>
                        )}
                        {canManage && (
                            <Button asChild size="sm">
                                <Link href={`/pekerja/${pekerja.id}/edit`}>
                                    <Pencil />
                                    Edit Pekerja
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

                <Card className="overflow-hidden">
                    <div className="h-2 bg-gradient-to-r from-blue-600 via-cyan-500 to-emerald-500" />
                    <CardContent className="flex flex-col gap-5 pt-1 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex min-w-0 flex-col items-start gap-4 sm:flex-row sm:items-center">
                            <Avatar className="size-20 border-4 border-background shadow">
                                <AvatarFallback className="bg-primary text-xl font-semibold text-primary-foreground">
                                    {getInitials(pekerja.nama)}
                                </AvatarFallback>
                            </Avatar>

                            <div className="min-w-0 space-y-2">
                                <div>
                                    <h1 className="text-2xl font-bold tracking-tight md:text-3xl">
                                        {displayValue(pekerja.nama)}
                                    </h1>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        {displayValue(jawatan?.jawatan ?? null)}
                                        {jawatan?.jabatan
                                            ? ` · ${jawatan.jabatan}`
                                            : ''}
                                    </p>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    <Badge
                                        variant="outline"
                                        className="gap-1.5"
                                    >
                                        <IdCard className="size-3.5" />
                                        {displayValue(pekerja.employee_id)}
                                    </Badge>
                                    <Badge
                                        variant="secondary"
                                        className="gap-1.5"
                                    >
                                        <ShieldCheck className="size-3.5" />
                                        {displayValue(pekerja.status)}
                                    </Badge>
                                </div>
                            </div>
                        </div>

                        <div className="flex flex-wrap gap-2 sm:max-w-xs sm:justify-end">
                            <Badge
                                variant="secondary"
                                className="gap-1.5 font-normal"
                            >
                                <Database className="size-3.5" />
                                Sumber data: db_spp
                            </Badge>
                            <Badge variant="outline" className="font-normal">
                                {canManage
                                    ? 'Akses kemas kini dibenarkan'
                                    : 'Paparan baca sahaja'}
                            </Badge>
                        </div>
                    </CardContent>
                </Card>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {summaryCards
                        .filter(
                            (item) => item.key !== 'payroll' || canViewPayroll,
                        )
                        .map((item) => {
                            const Icon = item.icon;

                            return (
                                <Card key={item.key} className="gap-4">
                                    <CardHeader className="flex-row items-start justify-between">
                                        <div className="space-y-1.5">
                                            <CardDescription>
                                                {item.label}
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
                                    <CardContent className="text-sm text-muted-foreground">
                                        {item.description}
                                    </CardContent>
                                </Card>
                            );
                        })}
                </div>

                <Card>
                    <CardHeader className="flex-row items-start justify-between">
                        <div className="space-y-1.5">
                            <CardTitle className="flex items-center gap-2">
                                <History className="size-5 text-muted-foreground" />
                                Sejarah Jawatan
                            </CardTitle>
                            <CardDescription>
                                Rekod penempatan semasa dan terdahulu tanpa
                                menimpa sejarah.
                            </CardDescription>
                        </div>
                        <Link
                            href={`/jawatan?search=${employeeSearch}`}
                            className="text-sm font-medium text-muted-foreground hover:text-foreground"
                        >
                            Lihat semua
                        </Link>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {jawatanHistory.length > 0 ? (
                            jawatanHistory.map((record) => (
                                <div
                                    key={record.id}
                                    className="flex flex-col gap-3 rounded-lg border p-4 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div className="min-w-0">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <p className="font-medium">
                                                {displayValue(record.jawatan)}
                                            </p>
                                            <Badge
                                                variant={
                                                    Number(record.aktif) === 1
                                                        ? 'secondary'
                                                        : 'outline'
                                                }
                                            >
                                                {Number(record.aktif) === 1
                                                    ? 'Aktif'
                                                    : 'Sejarah'}
                                            </Badge>
                                        </div>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            {displayValue(record.jabatan)} ·{' '}
                                            {formatDate(
                                                record.tarikh_lapor_diri,
                                            )}
                                            {Number(record.aktif) !== 1
                                                ? ` hingga ${formatDate(
                                                      record.tarikh_tamat ??
                                                          null,
                                                  )}`
                                                : ''}
                                        </p>
                                        {canViewPayroll && (
                                            <p className="mt-1 text-sm font-medium">
                                                {formatCurrency(
                                                    record.gaji_asas ?? null,
                                                )}
                                            </p>
                                        )}
                                    </div>
                                    <Button asChild variant="outline" size="sm">
                                        <Link href={`/jawatan/${record.id}`}>
                                            Papar Rekod
                                        </Link>
                                    </Button>
                                </div>
                            ))
                        ) : (
                            <EmptyRecords label="sejarah jawatan" />
                        )}
                    </CardContent>
                </Card>

                <div className="grid gap-6 xl:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <UserRound className="size-5 text-muted-foreground" />
                                Maklumat Peribadi
                            </CardTitle>
                            <CardDescription>
                                Identiti dan maklumat hubungan pekerja.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-3 sm:grid-cols-2">
                            <InformationItem
                                icon={IdCard}
                                label="No. Kad Pengenalan"
                                value={pekerja.nric}
                            />
                            <InformationItem
                                icon={Cake}
                                label="Tarikh Lahir"
                                value={pekerja.tarikh_lahir}
                                type="date"
                            />
                            <InformationItem
                                icon={UserRound}
                                label="Jantina"
                                value={pekerja.jantina}
                            />
                            <InformationItem
                                icon={Heart}
                                label="Status Perkahwinan"
                                value={pekerja.status_perkahwinan}
                            />
                            <InformationItem
                                icon={Globe2}
                                label="Agama"
                                value={pekerja.agama}
                            />
                            <InformationItem
                                icon={UsersRound}
                                label="Bangsa"
                                value={pekerja.bangsa}
                            />
                            <InformationItem
                                icon={Flag}
                                label="Kewarganegaraan"
                                value={pekerja.kewarganegaraan}
                            />
                            <InformationItem
                                icon={Phone}
                                label="No. Telefon"
                                value={pekerja.no_telefon}
                            />
                            <InformationItem
                                icon={Mail}
                                label="E-mel"
                                value={pekerja.email}
                                fullWidth
                            />
                            <InformationItem
                                icon={MapPin}
                                label="Alamat"
                                value={pekerja.alamat}
                                fullWidth
                            />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <BriefcaseBusiness className="size-5 text-muted-foreground" />
                                Maklumat Pekerjaan
                            </CardTitle>
                            <CardDescription>
                                Rekod jawatan aktif paling terkini.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {jawatan ? (
                                <div className="grid gap-3 sm:grid-cols-2">
                                    <InformationItem
                                        icon={BriefcaseBusiness}
                                        label="Jawatan"
                                        value={jawatan.jawatan}
                                    />
                                    <InformationItem
                                        icon={Building2}
                                        label="Jabatan"
                                        value={jawatan.jabatan}
                                    />
                                    <InformationItem
                                        icon={CalendarCheck2}
                                        label="Tarikh Lapor Diri"
                                        value={jawatan.tarikh_lapor_diri}
                                        type="date"
                                    />
                                    <InformationItem
                                        icon={CalendarDays}
                                        label="Tamat Tempoh Percubaan"
                                        value={
                                            jawatan.tarikh_tamat_tempoh_cubaan
                                        }
                                        type="date"
                                    />
                                    <InformationItem
                                        icon={CalendarDays}
                                        label="Kelayakan Cuti"
                                        value={jawatan.kelayakan_cuti}
                                    />
                                    {canViewPayroll && (
                                        <>
                                            <InformationItem
                                                icon={Banknote}
                                                label="Gaji Asas"
                                                value={
                                                    jawatan.gaji_asas ?? null
                                                }
                                                type="currency"
                                            />
                                            <InformationItem
                                                icon={Landmark}
                                                label="Bank"
                                                value={jawatan.bank ?? null}
                                            />
                                            <InformationItem
                                                icon={CreditCard}
                                                label="No. Akaun"
                                                value={jawatan.no_akaun ?? null}
                                            />
                                            <InformationItem
                                                icon={IdCard}
                                                label="No. KWSP"
                                                value={jawatan.no_kwsp ?? null}
                                            />
                                            <InformationItem
                                                icon={ShieldCheck}
                                                label="No. PERKESO"
                                                value={
                                                    jawatan.no_perkeso ?? null
                                                }
                                            />
                                        </>
                                    )}
                                </div>
                            ) : (
                                <EmptyRecords label="jawatan aktif" />
                            )}
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-6 xl:grid-cols-2">
                    <Card>
                        <CardHeader className="flex-row items-start justify-between">
                            <div className="space-y-1.5">
                                <CardTitle className="flex items-center gap-2">
                                    <CalendarCheck2 className="size-5 text-muted-foreground" />
                                    Kehadiran Terkini
                                </CardTitle>
                                <CardDescription>
                                    Lima rekod masuk atau keluar paling baharu.
                                </CardDescription>
                            </div>
                            <Link
                                href={`/kehadiran?search=${employeeSearch}`}
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
                                        className="border-b py-3 last:border-0"
                                    >
                                        <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                            <div>
                                                <p className="text-sm font-medium">
                                                    {formatDateTime(
                                                        record.waktu_masuk,
                                                    )}
                                                </p>
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    Keluar:{' '}
                                                    {formatDateTime(
                                                        record.waktu_keluar,
                                                    )}
                                                </p>
                                            </div>
                                            {record.pilihan_jam && (
                                                <Badge variant="outline">
                                                    {record.pilihan_jam}
                                                </Badge>
                                            )}
                                        </div>
                                        {record.catatan && (
                                            <p className="mt-2 text-xs text-muted-foreground">
                                                {record.catatan}
                                            </p>
                                        )}
                                    </div>
                                ))
                            ) : (
                                <EmptyRecords label="kehadiran" />
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex-row items-start justify-between">
                            <div className="space-y-1.5">
                                <CardTitle className="flex items-center gap-2">
                                    <CalendarDays className="size-5 text-muted-foreground" />
                                    Cuti Terkini
                                </CardTitle>
                                <CardDescription>
                                    Lima permohonan cuti paling baharu.
                                </CardDescription>
                            </div>
                            <Link
                                href={`/cuti?search=${employeeSearch}`}
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
                                        className="border-b py-3 last:border-0"
                                    >
                                        <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                            <div>
                                                <p className="text-sm font-medium">
                                                    {displayValue(
                                                        record.jenis_cuti,
                                                    )}
                                                </p>
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    {formatDate(
                                                        record.tarikh_mula,
                                                    )}{' '}
                                                    hingga{' '}
                                                    {formatDate(
                                                        record.tarikh_tamat,
                                                    )}
                                                </p>
                                            </div>
                                            <Badge variant="outline">
                                                {displayValue(
                                                    record.status_permohonan,
                                                )}
                                            </Badge>
                                        </div>
                                        <p className="mt-2 text-xs text-muted-foreground">
                                            Bilangan hari:{' '}
                                            {displayValue(record.bilangan_hari)}
                                        </p>
                                    </div>
                                ))
                            ) : (
                                <EmptyRecords label="cuti" />
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex-row items-start justify-between">
                            <div className="space-y-1.5">
                                <CardTitle className="flex items-center gap-2">
                                    <Clock3 className="size-5 text-muted-foreground" />
                                    Kerja Lebih Masa Terkini
                                </CardTitle>
                                <CardDescription>
                                    Lima rekod OT paling baharu.
                                </CardDescription>
                            </div>
                            <Link
                                href={`/kerja-lebih-masa?search=${employeeSearch}`}
                                className="text-sm font-medium text-muted-foreground hover:text-foreground"
                            >
                                Lihat semua
                            </Link>
                        </CardHeader>
                        <CardContent className="space-y-1">
                            {recentOvertime.length > 0 ? (
                                recentOvertime.map((record) => (
                                    <div
                                        key={record.id}
                                        className="border-b py-3 last:border-0"
                                    >
                                        <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                            <div>
                                                <p className="text-sm font-medium">
                                                    {formatDate(record.tarikh)}
                                                </p>
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    {formatTime(
                                                        record.waktu_masuk,
                                                    )}{' '}
                                                    hingga{' '}
                                                    {formatTime(
                                                        record.waktu_keluar,
                                                    )}
                                                </p>
                                            </div>
                                            <Badge variant="outline">
                                                {displayValue(record.jenis_ot)}
                                            </Badge>
                                        </div>
                                        {record.catatan && (
                                            <p className="mt-2 text-xs text-muted-foreground">
                                                {record.catatan}
                                            </p>
                                        )}
                                    </div>
                                ))
                            ) : (
                                <EmptyRecords label="kerja lebih masa" />
                            )}
                        </CardContent>
                    </Card>

                    {canViewPayroll && (
                        <Card>
                            <CardHeader className="flex-row items-start justify-between">
                                <div className="space-y-1.5">
                                    <CardTitle className="flex items-center gap-2">
                                        <Banknote className="size-5 text-muted-foreground" />
                                        Payroll Terkini
                                    </CardTitle>
                                    <CardDescription>
                                        Lima rekod payroll paling baharu.
                                    </CardDescription>
                                </div>
                                <Link
                                    href={`/payroll-asal?search=${employeeSearch}`}
                                    className="text-sm font-medium text-muted-foreground hover:text-foreground"
                                >
                                    Lihat semua
                                </Link>
                            </CardHeader>
                            <CardContent className="space-y-1">
                                {recentPayroll.length > 0 ? (
                                    recentPayroll.map((record) => (
                                        <div
                                            key={record.id}
                                            className="border-b py-3 last:border-0"
                                        >
                                            <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                                <div>
                                                    <p className="text-sm font-medium">
                                                        Tempoh{' '}
                                                        {formatDate(
                                                            record.tempoh_gaji,
                                                        )}
                                                    </p>
                                                    <p className="mt-1 text-xs text-muted-foreground">
                                                        Bulan:{' '}
                                                        {displayValue(
                                                            record.bulan,
                                                        )}
                                                    </p>
                                                </div>
                                                <Badge variant="outline">
                                                    Rekod #{record.id}
                                                </Badge>
                                            </div>
                                            <div className="mt-2 grid gap-1 text-xs text-muted-foreground sm:grid-cols-3">
                                                <span>
                                                    Akaun:{' '}
                                                    {displayValue(
                                                        record.no_akaun,
                                                    )}
                                                </span>
                                                <span>
                                                    KWSP:{' '}
                                                    {displayValue(
                                                        record.no_kwsp,
                                                    )}
                                                </span>
                                                <span>
                                                    PERKESO:{' '}
                                                    {displayValue(
                                                        record.no_socso,
                                                    )}
                                                </span>
                                            </div>
                                        </div>
                                    ))
                                ) : (
                                    <EmptyRecords label="payroll" />
                                )}
                            </CardContent>
                        </Card>
                    )}
                </div>
            </div>
        </>
    );
}

EmployeeProfile.layout = {
    breadcrumbs: [
        { title: 'Pekerja', href: '/pekerja' },
        { title: 'Profil Pekerja', href: '#' },
    ],
};
