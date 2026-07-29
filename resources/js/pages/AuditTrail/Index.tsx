import { Head, router } from '@inertiajs/react';
import {
    ArchiveRestore,
    CalendarRange,
    Eye,
    Filter,
    History,
    RotateCcw,
    Search,
    UserCheck,
    UserMinus,
    UserPlus,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
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
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

type AuditValue = string | number | boolean | null;

type AuditEmployee = {
    id: number;
    employee_id: string | null;
    nama: string | null;
};

type AuditActor = {
    id: number;
    name: string;
    email: string;
};

type AuditRecord = {
    id: number;
    action: string;
    action_label: string;
    auditable_type:
        | 'maklumatpekerja'
        | 'maklumatjawatan'
        | 'geo_attendance_records'
        | 'office_locations'
        | 'employee_user_links'
        | 'users'
        | 'employee_personal_profiles'
        | 'employee_leave_requests';
    auditable_id: string;
    employee: AuditEmployee | null;
    user: AuditActor | null;
    old_values: Record<string, AuditValue>;
    new_values: Record<string, AuditValue>;
    ip_address: string | null;
    user_agent: string | null;
    created_at: string;
};

type InactiveEmployee = {
    id: number;
    employee_id: string | null;
    nama: string | null;
    nric: string | null;
    no_telefon: string | null;
    email: string | null;
    status: string | null;
    tarikh_dikemas_kini: string | null;
    dikemas_kini_oleh: string | null;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type Paginator<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: PaginationLink[];
};

type SelectOption = {
    value: string;
    label: string;
    email?: string;
};

type AuditTrailProps = {
    audits: Paginator<AuditRecord>;
    inactiveEmployees: Paginator<InactiveEmployee>;
    filters: {
        tab: 'audit' | 'inactive';
        search: string;
        action: string;
        user_id: string;
        date_from: string;
        date_to: string;
        inactive_search: string;
    };
    actionOptions: SelectOption[];
    userOptions: SelectOption[];
    statistics: {
        total: number;
        created: number;
        updated: number;
        deactivated: number;
        reactivated: number;
        inactive: number;
    };
};

const fieldLabels: Record<string, string> = {
    employeeID: 'ID Pekerja',
    nric: 'NRIC',
    nama: 'Nama',
    alamat: 'Alamat',
    jantina: 'Jantina',
    tarikhlahir: 'Tarikh Lahir',
    agama: 'Agama',
    bangsa: 'Bangsa',
    kewarganegaraan: 'Kewarganegaraan',
    statusperkahwinan: 'Status Perkahwinan',
    notel: 'No. Telefon',
    email: 'E-mel',
    status: 'Status Pekerjaan',
    id_pekerja: 'Pekerja',
    date_lapordiri: 'Tarikh Berkuat Kuasa',
    date_tempohcubaan: 'Tamat Tempoh Percubaan',
    id_department: 'Jabatan / Unit',
    jawatan: 'Jawatan',
    salary: 'Gaji Asas',
    id_bank: 'Bank',
    noakaun: 'No. Akaun',
    noepf: 'No. KWSP',
    nosocso: 'No. PERKESO',
    jumlahcuti: 'Kelayakan Cuti',
    mdf_dt: 'Tarikh Tamat',
    rcd_enable: 'Status Rekod',
    is_active: 'Status Aktif',
    employee_id: 'Pekerja',
    office_location_id: 'Lokasi Pejabat',
    clock_in_at: 'Waktu Masuk',
    clock_out_at: 'Waktu Keluar',
    distance_meters: 'Jarak (meter)',
    accuracy_meters: 'Ketepatan GPS (meter)',
    reason: 'Alasan',
    method: 'Kaedah',
    source: 'Sumber',
    address: 'Alamat',
    phone: 'No. Telefon',
    leave_type: 'Jenis Cuti',
    start_date: 'Tarikh Mula',
    end_date: 'Tarikh Tamat',
    requested_days: 'Bil. Hari Dipohon',
    review_notes: 'Catatan HR',
    reviewed_by: 'Disemak Oleh',
    name: 'Nama Pengguna',
    user_id: 'ID Pengguna',
    role: 'Role Utama',
    primary_role: 'Role Utama',
    roles: 'Role',
    email_verified_at: 'E-mel Disahkan',
};

const actionStyles: Record<string, string> = {
    'employee.created':
        'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
    'employee.updated':
        'border-blue-500/30 bg-blue-500/10 text-blue-700 dark:text-blue-300',
    'employee.deactivated':
        'border-red-500/30 bg-red-500/10 text-red-700 dark:text-red-300',
    'employee.reactivated':
        'border-violet-500/30 bg-violet-500/10 text-violet-700 dark:text-violet-300',
    'position.created':
        'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
    'position.changed':
        'border-blue-500/30 bg-blue-500/10 text-blue-700 dark:text-blue-300',
    'position.terminated':
        'border-red-500/30 bg-red-500/10 text-red-700 dark:text-red-300',
    'attendance.clocked_in':
        'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
    'attendance.clocked_out':
        'border-cyan-500/30 bg-cyan-500/10 text-cyan-700 dark:text-cyan-300',
    'attendance.manual_created':
        'border-violet-500/30 bg-violet-500/10 text-violet-700 dark:text-violet-300',
    'attendance.corrected':
        'border-blue-500/30 bg-blue-500/10 text-blue-700 dark:text-blue-300',
    'attendance.cancelled':
        'border-red-500/30 bg-red-500/10 text-red-700 dark:text-red-300',
    'user.password.bulk_reset':
        'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300',
    'employee.profile_updated':
        'border-blue-500/30 bg-blue-500/10 text-blue-700 dark:text-blue-300',
    'leave.submitted':
        'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300',
    'leave.supervisor_approved':
        'border-blue-500/30 bg-blue-500/10 text-blue-700 dark:text-blue-300',
    'leave.supervisor_rejected':
        'border-red-500/30 bg-red-500/10 text-red-700 dark:text-red-300',
    'leave.approved':
        'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
    'leave.rejected':
        'border-red-500/30 bg-red-500/10 text-red-700 dark:text-red-300',
    'leave.cancelled':
        'border-slate-500/30 bg-slate-500/10 text-slate-700 dark:text-slate-300',
    'leave.approved_cancelled':
        'border-slate-500/30 bg-slate-500/10 text-slate-700 dark:text-slate-300',
    'leave_type.created':
        'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
    'leave_type.updated':
        'border-blue-500/30 bg-blue-500/10 text-blue-700 dark:text-blue-300',
    'leave_type.activated':
        'border-violet-500/30 bg-violet-500/10 text-violet-700 dark:text-violet-300',
    'leave_type.deactivated':
        'border-red-500/30 bg-red-500/10 text-red-700 dark:text-red-300',
    'leave_entitlement.created':
        'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
    'leave_entitlement.updated':
        'border-blue-500/30 bg-blue-500/10 text-blue-700 dark:text-blue-300',
    'leave_approver.assigned':
        'border-blue-500/30 bg-blue-500/10 text-blue-700 dark:text-blue-300',
    'leave_approver.removed':
        'border-red-500/30 bg-red-500/10 text-red-700 dark:text-red-300',
    'public_holiday.created':
        'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
    'public_holiday.deleted':
        'border-red-500/30 bg-red-500/10 text-red-700 dark:text-red-300',
};

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

function formatDateTime(value: string): string {
    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat('ms-MY', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    }).format(date);
}

function formatAuditValue(field: string, value: AuditValue | undefined) {
    if (value === null || value === undefined || value === '') {
        return '-';
    }

    if (['clock_in_at', 'clock_out_at'].includes(field)) {
        return formatDateTime(String(value));
    }

    if (
        [
            'tarikhlahir',
            'date_lapordiri',
            'date_tempohcubaan',
            'mdf_dt',
        ].includes(field)
    ) {
        return formatDate(String(value));
    }

    if (field === 'salary') {
        return new Intl.NumberFormat('ms-MY', {
            style: 'currency',
            currency: 'MYR',
        }).format(Number(value));
    }

    return String(value);
}

function paginationLabel(label: string): string {
    return label
        .replace('&laquo; Previous', 'Sebelum')
        .replace('Next &raquo;', 'Seterusnya');
}

function Pagination({ paginator }: { paginator: Paginator<unknown> }) {
    if (paginator.total === 0) {
        return null;
    }

    return (
        <div className="flex flex-col gap-3 border-t px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
            <p className="text-sm text-muted-foreground">
                Memaparkan {paginator.from ?? 0} hingga {paginator.to ?? 0}{' '}
                daripada {paginator.total} rekod
            </p>
            <div className="flex flex-wrap gap-2">
                {paginator.links.map((link, index) => (
                    <Button
                        key={`${link.label}-${index}`}
                        type="button"
                        size="sm"
                        variant={link.active ? 'default' : 'outline'}
                        disabled={!link.url}
                        onClick={() => {
                            if (link.url) {
                                router.visit(link.url, {
                                    preserveScroll: true,
                                    preserveState: true,
                                });
                            }
                        }}
                    >
                        {paginationLabel(link.label)}
                    </Button>
                ))}
            </div>
        </div>
    );
}

function ActionBadge({ action, label }: { action: string; label: string }) {
    return (
        <Badge
            variant="outline"
            className={actionStyles[action] ?? 'bg-muted text-foreground'}
        >
            {label}
        </Badge>
    );
}

function AuditDetailDialog({ audit }: { audit: AuditRecord }) {
    const changedFields = Array.from(
        new Set([
            ...Object.keys(audit.old_values),
            ...Object.keys(audit.new_values),
        ]),
    );

    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button type="button" variant="outline" size="sm">
                    <Eye />
                    Butiran
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>Butiran Audit #{audit.id}</DialogTitle>
                    <DialogDescription>
                        {audit.action_label} pada{' '}
                        {formatDateTime(audit.created_at)}
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-3 rounded-lg border bg-muted/30 p-4 text-sm sm:grid-cols-2">
                    <div>
                        <p className="text-muted-foreground">Pekerja</p>
                        <p className="font-medium">
                            {audit.employee?.nama ??
                                `Rekod #${audit.auditable_id}`}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            {audit.employee?.employee_id ?? '-'}
                        </p>
                    </div>
                    <div>
                        <p className="text-muted-foreground">Pengguna</p>
                        <p className="font-medium">
                            {audit.user?.name ?? 'Sistem / Pengguna Dipadam'}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            {audit.user?.email ?? '-'}
                        </p>
                    </div>
                    <div>
                        <p className="text-muted-foreground">Alamat IP</p>
                        <p className="font-medium">{audit.ip_address ?? '-'}</p>
                    </div>
                    <div>
                        <p className="text-muted-foreground">Tarikh & Masa</p>
                        <p className="font-medium">
                            {formatDateTime(audit.created_at)}
                        </p>
                    </div>
                </div>

                <div className="overflow-hidden rounded-lg border">
                    <div className="overflow-x-auto">
                        <Table>
                            <TableHeader className="bg-muted/60">
                                <TableRow>
                                    <TableHead>Medan</TableHead>
                                    <TableHead>Nilai Sebelum</TableHead>
                                    <TableHead>Nilai Selepas</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {changedFields.length > 0 ? (
                                    changedFields.map((field) => (
                                        <TableRow key={field}>
                                            <TableCell className="font-medium">
                                                {fieldLabels[field] ?? field}
                                            </TableCell>
                                            <TableCell className="max-w-64 whitespace-normal">
                                                {formatAuditValue(
                                                    field,
                                                    audit.old_values[field],
                                                )}
                                            </TableCell>
                                            <TableCell className="max-w-64 whitespace-normal">
                                                {formatAuditValue(
                                                    field,
                                                    audit.new_values[field],
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))
                                ) : (
                                    <TableRow>
                                        <TableCell
                                            colSpan={3}
                                            className="py-8 text-center text-muted-foreground"
                                        >
                                            Tiada nilai perubahan direkodkan.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </div>
                </div>

                <div className="rounded-lg border p-4 text-sm">
                    <p className="mb-1 font-medium">Peranti / Pelayar</p>
                    <p className="break-all text-muted-foreground">
                        {audit.user_agent ?? '-'}
                    </p>
                </div>

                <DialogFooter>
                    <DialogClose asChild>
                        <Button variant="outline">Tutup</Button>
                    </DialogClose>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function RestoreEmployeeButton({ employee }: { employee: InactiveEmployee }) {
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);

    const restore = () => {
        router.patch(
            `/audit-trail/pekerja-tidak-aktif/${employee.id}/aktifkan`,
            {},
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => {
                    setProcessing(false);
                    setOpen(false);
                },
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button type="button" variant="outline" size="sm">
                    <ArchiveRestore />
                    Aktifkan Semula
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Aktifkan semula pekerja?</DialogTitle>
                    <DialogDescription>
                        {employee.nama ?? employee.employee_id ?? 'Pekerja ini'}{' '}
                        akan kembali dipaparkan dalam senarai pekerja aktif.
                        Tindakan ini akan direkodkan dalam audit trail.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <DialogClose asChild>
                        <Button variant="outline" disabled={processing}>
                            Batal
                        </Button>
                    </DialogClose>
                    <Button
                        type="button"
                        onClick={restore}
                        disabled={processing}
                    >
                        <UserCheck />
                        {processing
                            ? 'Sedang diproses...'
                            : 'Ya, Aktifkan Semula'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

export default function AuditTrail({
    audits,
    inactiveEmployees,
    filters,
    actionOptions,
    userOptions,
    statistics,
}: AuditTrailProps) {
    const [search, setSearch] = useState(filters.search);
    const [action, setAction] = useState(filters.action || 'all');
    const [userId, setUserId] = useState(filters.user_id || 'all');
    const [dateFrom, setDateFrom] = useState(filters.date_from);
    const [dateTo, setDateTo] = useState(filters.date_to);
    const [inactiveSearch, setInactiveSearch] = useState(
        filters.inactive_search,
    );

    const auditParams = () => ({
        tab: 'audit',
        search: search || undefined,
        action: action === 'all' ? undefined : action,
        user_id: userId === 'all' ? undefined : userId,
        date_from: dateFrom || undefined,
        date_to: dateTo || undefined,
    });

    const submitAuditFilters = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.get('/audit-trail', auditParams(), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const resetAuditFilters = () => {
        setSearch('');
        setAction('all');
        setUserId('all');
        setDateFrom('');
        setDateTo('');
        router.get(
            '/audit-trail',
            { tab: 'audit' },
            {
                preserveState: false,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    const submitInactiveSearch = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.get(
            '/audit-trail',
            {
                tab: 'inactive',
                inactive_search: inactiveSearch || undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    const switchTab = (tab: 'audit' | 'inactive') => {
        router.get(
            '/audit-trail',
            tab === 'audit'
                ? auditParams()
                : {
                      tab: 'inactive',
                      inactive_search: inactiveSearch || undefined,
                  },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    const summaryCards = [
        {
            label: 'Jumlah Audit',
            value: statistics.total,
            icon: History,
            className: 'text-slate-600 dark:text-slate-300',
        },
        {
            label: 'Rekod Ditambah',
            value: statistics.created,
            icon: UserPlus,
            className: 'text-emerald-600 dark:text-emerald-300',
        },
        {
            label: 'Dikemas Kini',
            value: statistics.updated,
            icon: RotateCcw,
            className: 'text-blue-600 dark:text-blue-300',
        },
        {
            label: 'Ditamatkan / Dinyahaktifkan',
            value: statistics.deactivated,
            icon: UserMinus,
            className: 'text-red-600 dark:text-red-300',
        },
        {
            label: 'Diaktifkan Semula',
            value: statistics.reactivated,
            icon: UserCheck,
            className: 'text-violet-600 dark:text-violet-300',
        },
        {
            label: 'Masih Tidak Aktif',
            value: statistics.inactive,
            icon: ArchiveRestore,
            className: 'text-amber-600 dark:text-amber-300',
        },
    ];

    return (
        <>
            <Head title="Audit Trail" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="space-y-2">
                    <HeadingSmall
                        title="Audit Trail & Pekerja Tidak Aktif"
                        description="Jejaki perubahan pekerja dan penempatan jawatan serta pulihkan rekod pekerja yang telah dinyahaktifkan."
                    />
                    <div className="flex flex-wrap gap-2">
                        <Badge
                            variant="secondary"
                            className="gap-1.5 font-normal"
                        >
                            <History className="size-3.5" />
                            Audit: database sistem
                        </Badge>
                        <Badge
                            variant="secondary"
                            className="gap-1.5 font-normal"
                        >
                            <ArchiveRestore className="size-3.5" />
                            Pekerja: db_spp
                        </Badge>
                    </div>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
                    {summaryCards.map((card) => (
                        <Card key={card.label}>
                            <CardContent className="flex items-center justify-between p-5">
                                <div>
                                    <p className="text-sm text-muted-foreground">
                                        {card.label}
                                    </p>
                                    <p className="mt-1 text-2xl font-semibold">
                                        {card.value.toLocaleString('ms-MY')}
                                    </p>
                                </div>
                                <card.icon
                                    className={`size-6 ${card.className}`}
                                />
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <div className="flex flex-col gap-2 rounded-xl border bg-card p-2 sm:flex-row">
                    <Button
                        type="button"
                        variant={filters.tab === 'audit' ? 'default' : 'ghost'}
                        className="justify-start sm:justify-center"
                        onClick={() => switchTab('audit')}
                    >
                        <History />
                        Audit Trail
                    </Button>
                    <Button
                        type="button"
                        variant={
                            filters.tab === 'inactive' ? 'default' : 'ghost'
                        }
                        className="justify-start sm:justify-center"
                        onClick={() => switchTab('inactive')}
                    >
                        <ArchiveRestore />
                        Pekerja Tidak Aktif
                        <Badge variant="secondary">{statistics.inactive}</Badge>
                    </Button>
                </div>

                {filters.tab === 'audit' ? (
                    <>
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <Filter className="size-4" />
                                    Carian & Penapis
                                </CardTitle>
                                <CardDescription>
                                    Tapis mengikut pekerja, pengguna, tindakan
                                    atau julat tarikh.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <form
                                    onSubmit={submitAuditFilters}
                                    className="grid gap-4 lg:grid-cols-6"
                                >
                                    <div className="space-y-2 lg:col-span-2">
                                        <label
                                            htmlFor="audit-search"
                                            className="text-sm font-medium"
                                        >
                                            Kata Kunci
                                        </label>
                                        <div className="relative">
                                            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                            <Input
                                                id="audit-search"
                                                value={search}
                                                onChange={(event) =>
                                                    setSearch(
                                                        event.target.value,
                                                    )
                                                }
                                                placeholder="Nama, ID, pengguna atau IP..."
                                                className="pl-9"
                                            />
                                        </div>
                                    </div>
                                    <div className="space-y-2">
                                        <label className="text-sm font-medium">
                                            Tindakan
                                        </label>
                                        <Select
                                            value={action}
                                            onValueChange={setAction}
                                        >
                                            <SelectTrigger className="w-full">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="all">
                                                    Semua Tindakan
                                                </SelectItem>
                                                {actionOptions.map((option) => (
                                                    <SelectItem
                                                        key={option.value}
                                                        value={option.value}
                                                    >
                                                        {option.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="space-y-2">
                                        <label className="text-sm font-medium">
                                            Pengguna
                                        </label>
                                        <Select
                                            value={userId}
                                            onValueChange={setUserId}
                                        >
                                            <SelectTrigger className="w-full">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="all">
                                                    Semua Pengguna
                                                </SelectItem>
                                                {userOptions.map((option) => (
                                                    <SelectItem
                                                        key={option.value}
                                                        value={option.value}
                                                    >
                                                        {option.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="space-y-2">
                                        <label
                                            htmlFor="date-from"
                                            className="text-sm font-medium"
                                        >
                                            Tarikh Mula
                                        </label>
                                        <Input
                                            id="date-from"
                                            type="date"
                                            value={dateFrom}
                                            max={dateTo || undefined}
                                            onChange={(event) =>
                                                setDateFrom(event.target.value)
                                            }
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <label
                                            htmlFor="date-to"
                                            className="text-sm font-medium"
                                        >
                                            Tarikh Tamat
                                        </label>
                                        <Input
                                            id="date-to"
                                            type="date"
                                            value={dateTo}
                                            min={dateFrom || undefined}
                                            onChange={(event) =>
                                                setDateTo(event.target.value)
                                            }
                                        />
                                    </div>
                                    <div className="flex flex-col gap-2 sm:flex-row lg:col-span-6 lg:justify-end">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={resetAuditFilters}
                                        >
                                            <RotateCcw />
                                            Reset
                                        </Button>
                                        <Button type="submit">
                                            <Filter />
                                            Tapis Rekod
                                        </Button>
                                    </div>
                                </form>
                            </CardContent>
                        </Card>

                        <div className="hidden overflow-hidden rounded-xl border bg-card md:block">
                            <div className="overflow-x-auto">
                                <Table>
                                    <TableHeader className="bg-muted/60">
                                        <TableRow>
                                            <TableHead className="w-16">
                                                No.
                                            </TableHead>
                                            <TableHead>Tarikh & Masa</TableHead>
                                            <TableHead>Tindakan</TableHead>
                                            <TableHead>Pekerja</TableHead>
                                            <TableHead>Pengguna</TableHead>
                                            <TableHead>Alamat IP</TableHead>
                                            <TableHead className="text-right">
                                                Butiran
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {audits.data.length > 0 ? (
                                            audits.data.map((audit, index) => (
                                                <TableRow key={audit.id}>
                                                    <TableCell className="text-muted-foreground">
                                                        {(audits.from ?? 1) +
                                                            index}
                                                    </TableCell>
                                                    <TableCell className="whitespace-nowrap">
                                                        {formatDateTime(
                                                            audit.created_at,
                                                        )}
                                                    </TableCell>
                                                    <TableCell>
                                                        <ActionBadge
                                                            action={
                                                                audit.action
                                                            }
                                                            label={
                                                                audit.action_label
                                                            }
                                                        />
                                                    </TableCell>
                                                    <TableCell>
                                                        <p className="font-medium">
                                                            {audit.employee
                                                                ?.nama ??
                                                                `Rekod #${audit.auditable_id}`}
                                                        </p>
                                                        <p className="text-xs text-muted-foreground">
                                                            {audit.employee
                                                                ?.employee_id ??
                                                                '-'}
                                                        </p>
                                                    </TableCell>
                                                    <TableCell>
                                                        <p className="font-medium">
                                                            {audit.user?.name ??
                                                                'Sistem'}
                                                        </p>
                                                        <p className="text-xs text-muted-foreground">
                                                            {audit.user
                                                                ?.email ?? '-'}
                                                        </p>
                                                    </TableCell>
                                                    <TableCell>
                                                        {audit.ip_address ??
                                                            '-'}
                                                    </TableCell>
                                                    <TableCell className="text-right">
                                                        <AuditDetailDialog
                                                            audit={audit}
                                                        />
                                                    </TableCell>
                                                </TableRow>
                                            ))
                                        ) : (
                                            <TableRow>
                                                <TableCell
                                                    colSpan={7}
                                                    className="py-12 text-center"
                                                >
                                                    <History className="mx-auto mb-3 size-8 text-muted-foreground" />
                                                    <p className="font-medium">
                                                        Tiada rekod audit
                                                        ditemui
                                                    </p>
                                                    <p className="mt-1 text-sm text-muted-foreground">
                                                        Cuba ubah carian atau
                                                        penapis.
                                                    </p>
                                                </TableCell>
                                            </TableRow>
                                        )}
                                    </TableBody>
                                </Table>
                            </div>
                            <Pagination paginator={audits} />
                        </div>

                        <div className="grid gap-4 md:hidden">
                            {audits.data.length > 0 ? (
                                audits.data.map((audit) => (
                                    <Card key={audit.id}>
                                        <CardHeader className="gap-3 pb-3">
                                            <div className="flex items-start justify-between gap-3">
                                                <div>
                                                    <CardTitle className="text-base">
                                                        {audit.employee?.nama ??
                                                            `Rekod #${audit.auditable_id}`}
                                                    </CardTitle>
                                                    <CardDescription>
                                                        {audit.employee
                                                            ?.employee_id ??
                                                            '-'}
                                                    </CardDescription>
                                                </div>
                                                <ActionBadge
                                                    action={audit.action}
                                                    label={audit.action_label}
                                                />
                                            </div>
                                        </CardHeader>
                                        <CardContent className="space-y-3 text-sm">
                                            <div className="grid grid-cols-2 gap-3">
                                                <div>
                                                    <p className="text-muted-foreground">
                                                        Pengguna
                                                    </p>
                                                    <p className="font-medium">
                                                        {audit.user?.name ??
                                                            'Sistem'}
                                                    </p>
                                                </div>
                                                <div>
                                                    <p className="text-muted-foreground">
                                                        Alamat IP
                                                    </p>
                                                    <p className="font-medium">
                                                        {audit.ip_address ??
                                                            '-'}
                                                    </p>
                                                </div>
                                            </div>
                                            <div>
                                                <p className="text-muted-foreground">
                                                    Tarikh & Masa
                                                </p>
                                                <p className="font-medium">
                                                    {formatDateTime(
                                                        audit.created_at,
                                                    )}
                                                </p>
                                            </div>
                                            <AuditDetailDialog audit={audit} />
                                        </CardContent>
                                    </Card>
                                ))
                            ) : (
                                <Card>
                                    <CardContent className="py-12 text-center">
                                        <History className="mx-auto mb-3 size-8 text-muted-foreground" />
                                        <p className="font-medium">
                                            Tiada rekod audit ditemui
                                        </p>
                                    </CardContent>
                                </Card>
                            )}
                            <div className="overflow-hidden rounded-xl border bg-card">
                                <Pagination paginator={audits} />
                            </div>
                        </div>
                    </>
                ) : (
                    <>
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <Search className="size-4" />
                                    Cari Pekerja Tidak Aktif
                                </CardTitle>
                                <CardDescription>
                                    Cari menggunakan nama, ID pekerja, NRIC atau
                                    e-mel.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <form
                                    onSubmit={submitInactiveSearch}
                                    className="flex flex-col gap-3 sm:flex-row"
                                >
                                    <div className="relative flex-1">
                                        <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                        <Input
                                            value={inactiveSearch}
                                            onChange={(event) =>
                                                setInactiveSearch(
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="Cari nama, ID, NRIC atau e-mel..."
                                            className="pl-9"
                                            aria-label="Cari pekerja tidak aktif"
                                        />
                                    </div>
                                    <Button type="submit">
                                        <Search />
                                        Cari
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => {
                                            setInactiveSearch('');
                                            router.get(
                                                '/audit-trail',
                                                { tab: 'inactive' },
                                                {
                                                    preserveState: false,
                                                    preserveScroll: true,
                                                    replace: true,
                                                },
                                            );
                                        }}
                                    >
                                        <RotateCcw />
                                        Reset
                                    </Button>
                                </form>
                            </CardContent>
                        </Card>

                        <div className="hidden overflow-hidden rounded-xl border bg-card md:block">
                            <div className="overflow-x-auto">
                                <Table>
                                    <TableHeader className="bg-muted/60">
                                        <TableRow>
                                            <TableHead className="w-16">
                                                No.
                                            </TableHead>
                                            <TableHead>ID Pekerja</TableHead>
                                            <TableHead>Nama</TableHead>
                                            <TableHead>NRIC</TableHead>
                                            <TableHead>Hubungan</TableHead>
                                            <TableHead>
                                                Kemas Kini Terakhir
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Tindakan
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {inactiveEmployees.data.length > 0 ? (
                                            inactiveEmployees.data.map(
                                                (employee, index) => (
                                                    <TableRow key={employee.id}>
                                                        <TableCell className="text-muted-foreground">
                                                            {(inactiveEmployees.from ??
                                                                1) + index}
                                                        </TableCell>
                                                        <TableCell>
                                                            {employee.employee_id ??
                                                                '-'}
                                                        </TableCell>
                                                        <TableCell>
                                                            <p className="font-medium">
                                                                {employee.nama ??
                                                                    '-'}
                                                            </p>
                                                            <p className="text-xs text-muted-foreground">
                                                                {employee.status ??
                                                                    '-'}
                                                            </p>
                                                        </TableCell>
                                                        <TableCell>
                                                            {employee.nric ??
                                                                '-'}
                                                        </TableCell>
                                                        <TableCell>
                                                            <p>
                                                                {employee.email ??
                                                                    '-'}
                                                            </p>
                                                            <p className="text-xs text-muted-foreground">
                                                                {employee.no_telefon ??
                                                                    '-'}
                                                            </p>
                                                        </TableCell>
                                                        <TableCell>
                                                            <p>
                                                                {formatDate(
                                                                    employee.tarikh_dikemas_kini,
                                                                )}
                                                            </p>
                                                            <p className="text-xs text-muted-foreground">
                                                                {employee.dikemas_kini_oleh ??
                                                                    '-'}
                                                            </p>
                                                        </TableCell>
                                                        <TableCell className="text-right">
                                                            <RestoreEmployeeButton
                                                                employee={
                                                                    employee
                                                                }
                                                            />
                                                        </TableCell>
                                                    </TableRow>
                                                ),
                                            )
                                        ) : (
                                            <TableRow>
                                                <TableCell
                                                    colSpan={7}
                                                    className="py-12 text-center"
                                                >
                                                    <UserCheck className="mx-auto mb-3 size-8 text-muted-foreground" />
                                                    <p className="font-medium">
                                                        Tiada pekerja tidak
                                                        aktif ditemui
                                                    </p>
                                                </TableCell>
                                            </TableRow>
                                        )}
                                    </TableBody>
                                </Table>
                            </div>
                            <Pagination paginator={inactiveEmployees} />
                        </div>

                        <div className="grid gap-4 md:hidden">
                            {inactiveEmployees.data.length > 0 ? (
                                inactiveEmployees.data.map((employee) => (
                                    <Card key={employee.id}>
                                        <CardHeader className="pb-3">
                                            <div className="flex items-start justify-between gap-3">
                                                <div>
                                                    <CardTitle className="text-base">
                                                        {employee.nama ?? '-'}
                                                    </CardTitle>
                                                    <CardDescription>
                                                        {employee.employee_id ??
                                                            '-'}
                                                    </CardDescription>
                                                </div>
                                                <Badge variant="destructive">
                                                    Tidak Aktif
                                                </Badge>
                                            </div>
                                        </CardHeader>
                                        <CardContent className="space-y-3 text-sm">
                                            <div className="grid grid-cols-2 gap-3">
                                                <div>
                                                    <p className="text-muted-foreground">
                                                        NRIC
                                                    </p>
                                                    <p className="font-medium">
                                                        {employee.nric ?? '-'}
                                                    </p>
                                                </div>
                                                <div>
                                                    <p className="text-muted-foreground">
                                                        Status
                                                    </p>
                                                    <p className="font-medium">
                                                        {employee.status ?? '-'}
                                                    </p>
                                                </div>
                                            </div>
                                            <div>
                                                <p className="text-muted-foreground">
                                                    E-mel / Telefon
                                                </p>
                                                <p className="font-medium">
                                                    {employee.email ?? '-'}
                                                </p>
                                                <p>
                                                    {employee.no_telefon ?? '-'}
                                                </p>
                                            </div>
                                            <div className="flex items-center gap-2 text-muted-foreground">
                                                <CalendarRange className="size-4" />
                                                {formatDate(
                                                    employee.tarikh_dikemas_kini,
                                                )}
                                                {employee.dikemas_kini_oleh
                                                    ? ` · ${employee.dikemas_kini_oleh}`
                                                    : ''}
                                            </div>
                                            <RestoreEmployeeButton
                                                employee={employee}
                                            />
                                        </CardContent>
                                    </Card>
                                ))
                            ) : (
                                <Card>
                                    <CardContent className="py-12 text-center">
                                        <UserCheck className="mx-auto mb-3 size-8 text-muted-foreground" />
                                        <p className="font-medium">
                                            Tiada pekerja tidak aktif ditemui
                                        </p>
                                    </CardContent>
                                </Card>
                            )}
                            <div className="overflow-hidden rounded-xl border bg-card">
                                <Pagination paginator={inactiveEmployees} />
                            </div>
                        </div>
                    </>
                )}
            </div>
        </>
    );
}

AuditTrail.layout = {
    breadcrumbs: [{ title: 'Audit Trail', href: '/audit-trail' }],
};
