import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ClipboardCheck,
    Download,
    FileChartColumn,
    Paperclip,
    Search,
    TimerReset,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
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
import { Label } from '@/components/ui/label';
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

type Status = 'pending' | 'approved' | 'rejected' | 'cancelled';
type Stage = 'supervisor' | 'hr' | 'completed';

type OvertimeRequest = {
    id: number;
    employee: {
        id: number;
        employee_id: string | null;
        name: string | null;
    } | null;
    overtime_type: string;
    rate_multiplier: number;
    minimum_minutes: number;
    work_date: string;
    start_at: string;
    end_at: string;
    break_minutes: number;
    requested_minutes: number;
    approved_minutes: number | null;
    reason: string;
    work_description: string;
    status: Status;
    approval_stage: Stage;
    attendance_match_status: string;
    roster_day_type: string | null;
    roster_match_status: string;
    attendance: {
        clock_in_at: string;
        clock_out_at: string | null;
        status: string;
    } | null;
    submitted_at: string;
    supervisor_review_notes: string | null;
    supervisor_reviewer: string | null;
    review_notes: string | null;
    reviewer: string | null;
    has_attachment: boolean;
    attachment_name: string | null;
};

type Props = {
    requests: {
        data: OvertimeRequest[];
        from: number | null;
        to: number | null;
        total: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: {
        search: string;
        status: string;
        stage: string;
        overtime_type_id: string;
        month: string;
        all_months: boolean;
    };
    statistics: {
        total: number;
        pending_supervisor: number;
        pending_hr: number;
        approved: number;
        approved_hours: number;
    };
    overtimeTypes: { id: number; name: string }[];
    reportByType: {
        overtime_type: string;
        status: Status;
        total: number;
        requested_hours: number;
        approved_hours: number;
    }[];
    permissions: {
        can_supervise: boolean;
        can_manage: boolean;
    };
};

const statusLabels: Record<Status, string> = {
    pending: 'Menunggu',
    approved: 'Diluluskan',
    rejected: 'Ditolak',
    cancelled: 'Dibatalkan',
};

const statusStyles: Record<Status, string> = {
    pending:
        'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300',
    approved:
        'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
    rejected: 'border-red-500/30 bg-red-500/10 text-red-700 dark:text-red-300',
    cancelled:
        'border-slate-500/30 bg-slate-500/10 text-slate-700 dark:text-slate-300',
};

function formatDateTime(value: string | null): string {
    if (!value) {
        return '-';
    }

    const date = new Date(value);

    return Number.isNaN(date.getTime())
        ? value
        : new Intl.DateTimeFormat('ms-MY', {
              day: '2-digit',
              month: 'short',
              year: 'numeric',
              hour: '2-digit',
              minute: '2-digit',
          }).format(date);
}

function hours(minutes: number | null): string {
    return minutes === null ? '-' : `${(minutes / 60).toFixed(2)} jam`;
}

function paginationLabel(label: string): string {
    return label
        .replace('&laquo; Previous', 'Sebelum')
        .replace('Next &raquo;', 'Seterusnya');
}

function ReviewDialog({
    request,
    mode,
}: {
    request: OvertimeRequest;
    mode: 'supervisor' | 'hr';
}) {
    const [open, setOpen] = useState(false);
    const { data, setData, patch, processing, errors, reset } = useForm({
        status: 'approved' as 'approved' | 'rejected',
        approved_minutes: String(request.requested_minutes),
        review_notes: '',
    });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const suffix = mode === 'supervisor' ? 'semakan-penyelia' : 'semakan';

        patch(`/permohonan-ot/${request.id}/${suffix}`, {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                reset();
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm">
                    <ClipboardCheck />
                    Semak
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        Semakan {mode === 'supervisor' ? 'Penyelia' : 'HR'}
                    </DialogTitle>
                    <DialogDescription>
                        {request.employee?.name ?? 'Pekerja'} ·{' '}
                        {request.overtime_type} ·{' '}
                        {hours(request.requested_minutes)}
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="rounded-lg border bg-muted/30 p-3 text-sm">
                        <p>
                            Kehadiran:{' '}
                            <strong>
                                {request.attendance_match_status === 'matched'
                                    ? 'Sepadan dengan waktu OT'
                                    : request.attendance_match_status}
                            </strong>
                        </p>
                        <p className="mt-1 text-muted-foreground">
                            Clock-in{' '}
                            {formatDateTime(
                                request.attendance?.clock_in_at ?? null,
                            )}{' '}
                            · Clock-out{' '}
                            {formatDateTime(
                                request.attendance?.clock_out_at ?? null,
                            )}
                        </p>
                    </div>
                    <div className="space-y-2">
                        <Label>Keputusan</Label>
                        <Select
                            value={data.status}
                            onValueChange={(value: 'approved' | 'rejected') =>
                                setData('status', value)
                            }
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="approved">
                                    {mode === 'supervisor'
                                        ? 'Sokong dan hantar kepada HR'
                                        : 'Luluskan'}
                                </SelectItem>
                                <SelectItem value="rejected">Tolak</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError message={errors.status} />
                    </div>
                    {mode === 'hr' && data.status === 'approved' && (
                        <div className="space-y-2">
                            <Label htmlFor={`minutes-${request.id}`}>
                                Minit Diluluskan
                            </Label>
                            <Input
                                id={`minutes-${request.id}`}
                                type="number"
                                min={request.minimum_minutes}
                                max={request.requested_minutes}
                                value={data.approved_minutes}
                                onChange={(event) =>
                                    setData(
                                        'approved_minutes',
                                        event.target.value,
                                    )
                                }
                            />
                            <p className="text-xs text-muted-foreground">
                                {hours(Number(data.approved_minutes) || 0)} ·
                                maksimum {request.requested_minutes} minit
                            </p>
                            <InputError message={errors.approved_minutes} />
                        </div>
                    )}
                    <div className="space-y-2">
                        <Label htmlFor={`notes-${request.id}`}>
                            Catatan {data.status === 'rejected' && '(Wajib)'}
                        </Label>
                        <textarea
                            id={`notes-${request.id}`}
                            rows={4}
                            maxLength={1000}
                            value={data.review_notes}
                            onChange={(event) =>
                                setData('review_notes', event.target.value)
                            }
                            className="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        />
                        <InputError message={errors.review_notes} />
                    </div>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Tutup
                            </Button>
                        </DialogClose>
                        <Button type="submit" disabled={processing}>
                            Simpan Keputusan
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function CancellationDialog({ request }: { request: OvertimeRequest }) {
    const [open, setOpen] = useState(false);
    const { data, setData, patch, processing, errors, reset } = useForm({
        cancellation_notes: '',
    });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        patch(`/permohonan-ot/${request.id}/batal-kelulusan`, {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                reset();
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    <XCircle />
                    Batal Kelulusan
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Batalkan OT Diluluskan</DialogTitle>
                    <DialogDescription>
                        Jam diluluskan akan dikeluarkan daripada input Payroll
                        dan tindakan direkodkan dalam Audit Trail.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-2">
                        <Label>Sebab Pembatalan</Label>
                        <textarea
                            rows={4}
                            value={data.cancellation_notes}
                            onChange={(event) =>
                                setData(
                                    'cancellation_notes',
                                    event.target.value,
                                )
                            }
                            className="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm outline-none"
                        />
                        <InputError message={errors.cancellation_notes} />
                    </div>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Tutup
                            </Button>
                        </DialogClose>
                        <Button
                            type="submit"
                            variant="destructive"
                            disabled={processing}
                        >
                            Sahkan Pembatalan
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function OvertimeRequests({
    requests,
    filters,
    statistics,
    overtimeTypes,
    reportByType,
    permissions,
}: Props) {
    const [search, setSearch] = useState(filters.search);
    const [status, setStatus] = useState(filters.status || 'all');
    const [stage, setStage] = useState(filters.stage || 'all');
    const [type, setType] = useState(filters.overtime_type_id || 'all');
    const [month, setMonth] = useState(filters.month);
    const [allMonths, setAllMonths] = useState(filters.all_months);
    const applyFilters = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.get(
            '/permohonan-ot',
            {
                search,
                status: status === 'all' ? '' : status,
                stage: stage === 'all' ? '' : stage,
                overtime_type_id: type === 'all' ? '' : type,
                month,
                all_months: allMonths ? 1 : 0,
            },
            { preserveState: true, replace: true },
        );
    };
    const reportUrl = `/permohonan-ot/laporan.csv?${new URLSearchParams({
        search,
        status: status === 'all' ? '' : status,
        stage: stage === 'all' ? '' : stage,
        overtime_type_id: type === 'all' ? '' : type,
        month,
        all_months: allMonths ? '1' : '0',
    }).toString()}`;

    return (
        <>
            <Head title="Permohonan OT" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="flex items-center gap-2 text-2xl font-semibold">
                            <TimerReset className="size-6 text-primary" />
                            Permohonan Kerja Lebih Masa
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Semakan penyelia, pengesahan kehadiran dan kelulusan
                            akhir HR.
                        </p>
                    </div>
                    <Button asChild variant="outline">
                        <a href={reportUrl}>
                            <Download />
                            Laporan CSV
                        </a>
                    </Button>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    {[
                        { label: 'Semua', value: statistics.total },
                        {
                            label: 'Menunggu Penyelia',
                            value: statistics.pending_supervisor,
                        },
                        {
                            label: 'Menunggu HR',
                            value: statistics.pending_hr,
                        },
                        {
                            label: 'Diluluskan',
                            value: statistics.approved,
                        },
                        {
                            label: 'Jam Diluluskan',
                            value: statistics.approved_hours.toFixed(2),
                        },
                    ].map((item) => (
                        <Card key={item.label}>
                            <CardHeader className="pb-2">
                                <CardDescription>{item.label}</CardDescription>
                                <CardTitle className="text-2xl">
                                    {item.value}
                                </CardTitle>
                            </CardHeader>
                        </Card>
                    ))}
                </div>

                <form
                    onSubmit={applyFilters}
                    className="grid gap-3 rounded-xl border p-4 lg:grid-cols-[1.3fr_repeat(4,0.7fr)_auto]"
                >
                    <Input
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Cari nama, ID atau tujuan OT"
                    />
                    <Select value={status} onValueChange={setStatus}>
                        <SelectTrigger>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Semua status</SelectItem>
                            {Object.entries(statusLabels).map(
                                ([value, label]) => (
                                    <SelectItem key={value} value={value}>
                                        {label}
                                    </SelectItem>
                                ),
                            )}
                        </SelectContent>
                    </Select>
                    <Select value={stage} onValueChange={setStage}>
                        <SelectTrigger>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Semua peringkat</SelectItem>
                            <SelectItem value="supervisor">Penyelia</SelectItem>
                            <SelectItem value="hr">HR</SelectItem>
                            <SelectItem value="completed">Selesai</SelectItem>
                        </SelectContent>
                    </Select>
                    <Select value={type} onValueChange={setType}>
                        <SelectTrigger>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Semua jenis</SelectItem>
                            {overtimeTypes.map((overtimeType) => (
                                <SelectItem
                                    key={overtimeType.id}
                                    value={String(overtimeType.id)}
                                >
                                    {overtimeType.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Input
                        type="month"
                        value={month}
                        disabled={allMonths}
                        onChange={(event) => {
                            setMonth(event.target.value);
                            setAllMonths(false);
                        }}
                    />
                    {allMonths && (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setAllMonths(false)}
                        >
                            Semua bulan · pilih bulan
                        </Button>
                    )}
                    <Button type="submit">
                        <Search />
                        Tapis
                    </Button>
                </form>

                <Card>
                    <CardHeader>
                        <CardTitle>Senarai Permohonan</CardTitle>
                        <CardDescription>
                            {requests.from ?? 0}–{requests.to ?? 0} daripada{' '}
                            {requests.total} rekod.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Pekerja / Tarikh</TableHead>
                                    <TableHead>Jenis / Tempoh</TableHead>
                                    <TableHead>Kehadiran</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Tujuan</TableHead>
                                    <TableHead className="text-right">
                                        Tindakan
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {requests.data.map((request) => {
                                    const canSupervisorReview =
                                        permissions.can_supervise &&
                                        request.status === 'pending' &&
                                        request.approval_stage === 'supervisor';
                                    const canHrReview =
                                        permissions.can_manage &&
                                        request.status === 'pending' &&
                                        request.approval_stage === 'hr';

                                    return (
                                        <TableRow key={request.id}>
                                            <TableCell>
                                                <p className="font-medium">
                                                    {request.employee?.name ??
                                                        'Pekerja'}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {request.employee
                                                        ?.employee_id ??
                                                        '-'}{' '}
                                                    · {request.work_date}
                                                </p>
                                            </TableCell>
                                            <TableCell>
                                                <p className="font-medium">
                                                    {request.overtime_type}{' '}
                                                    <span className="text-xs text-muted-foreground">
                                                        {
                                                            request.rate_multiplier
                                                        }
                                                        x
                                                    </span>
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {formatDateTime(
                                                        request.start_at,
                                                    )}{' '}
                                                    –{' '}
                                                    {formatDateTime(
                                                        request.end_at,
                                                    )}
                                                </p>
                                                <p className="text-xs">
                                                    Dipohon{' '}
                                                    {hours(
                                                        request.requested_minutes,
                                                    )}
                                                    {request.approved_minutes !==
                                                        null &&
                                                        ` · lulus ${hours(request.approved_minutes)}`}
                                                </p>
                                            </TableCell>
                                            <TableCell>
                                                <Badge
                                                    variant={
                                                        request.attendance_match_status ===
                                                        'matched'
                                                            ? 'default'
                                                            : 'outline'
                                                    }
                                                >
                                                    {
                                                        request.attendance_match_status
                                                    }
                                                </Badge>
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    {formatDateTime(
                                                        request.attendance
                                                            ?.clock_out_at ??
                                                            null,
                                                    )}
                                                </p>
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    Roster:{' '}
                                                    {request.roster_day_type ??
                                                        'tiada'}{' '}
                                                    ·{' '}
                                                    {
                                                        request.roster_match_status
                                                    }
                                                </p>
                                            </TableCell>
                                            <TableCell>
                                                <Badge
                                                    variant="outline"
                                                    className={
                                                        statusStyles[
                                                            request.status
                                                        ]
                                                    }
                                                >
                                                    {
                                                        statusLabels[
                                                            request.status
                                                        ]
                                                    }
                                                </Badge>
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    {request.approval_stage}
                                                </p>
                                            </TableCell>
                                            <TableCell className="max-w-64">
                                                <p className="line-clamp-2 text-sm">
                                                    {request.reason}
                                                </p>
                                                <p className="mt-1 line-clamp-2 text-xs text-muted-foreground">
                                                    {request.work_description}
                                                </p>
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex flex-wrap justify-end gap-2">
                                                    {request.has_attachment && (
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            asChild
                                                        >
                                                            <a
                                                                href={`/ot-saya/${request.id}/lampiran`}
                                                            >
                                                                <Paperclip />
                                                                Lampiran
                                                            </a>
                                                        </Button>
                                                    )}
                                                    {canSupervisorReview && (
                                                        <ReviewDialog
                                                            request={request}
                                                            mode="supervisor"
                                                        />
                                                    )}
                                                    {canHrReview && (
                                                        <ReviewDialog
                                                            request={request}
                                                            mode="hr"
                                                        />
                                                    )}
                                                    {permissions.can_manage &&
                                                        request.status ===
                                                            'approved' && (
                                                            <CancellationDialog
                                                                request={
                                                                    request
                                                                }
                                                            />
                                                        )}
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    );
                                })}
                                {requests.data.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={6}
                                            className="h-24 text-center text-muted-foreground"
                                        >
                                            Tiada permohonan OT bagi penapis
                                            ini.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                        <div className="mt-4 flex flex-wrap gap-2">
                            {requests.links.map((link) =>
                                link.url ? (
                                    <Button
                                        key={link.label}
                                        size="sm"
                                        variant={
                                            link.active ? 'default' : 'outline'
                                        }
                                        asChild
                                    >
                                        <Link href={link.url}>
                                            {paginationLabel(link.label)}
                                        </Link>
                                    </Button>
                                ) : (
                                    <Button
                                        key={link.label}
                                        size="sm"
                                        variant="outline"
                                        disabled
                                    >
                                        {paginationLabel(link.label)}
                                    </Button>
                                ),
                            )}
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <FileChartColumn className="size-5 text-primary" />
                            Ringkasan Bulanan
                        </CardTitle>
                        <CardDescription>
                            Jumlah jam mengikut jenis dan status permohonan.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        {reportByType.map((row, index) => (
                            <div
                                key={`${row.overtime_type}-${row.status}-${index}`}
                                className="rounded-lg border p-4"
                            >
                                <div className="flex justify-between gap-3">
                                    <p className="font-medium">
                                        {row.overtime_type}
                                    </p>
                                    <Badge
                                        variant="outline"
                                        className={statusStyles[row.status]}
                                    >
                                        {statusLabels[row.status]}
                                    </Badge>
                                </div>
                                <p className="mt-2 text-sm text-muted-foreground">
                                    {row.total} permohonan ·{' '}
                                    {row.requested_hours.toFixed(2)} jam dipohon
                                    · {row.approved_hours.toFixed(2)} jam
                                    diluluskan
                                </p>
                            </div>
                        ))}
                        {reportByType.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                Tiada data ringkasan.
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

OvertimeRequests.layout = {
    breadcrumbs: [{ title: 'Permohonan OT', href: '/permohonan-ot' }],
};
