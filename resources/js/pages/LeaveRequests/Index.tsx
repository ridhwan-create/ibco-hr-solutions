import { Head, router, useForm } from '@inertiajs/react';
import {
    CalendarDays,
    CheckCircle2,
    ClipboardCheck,
    Download,
    FileChartColumn,
    Paperclip,
    Search,
    ShieldCheck,
    XCircle,
} from 'lucide-react';
import { useMemo, useState } from 'react';
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

type LeaveStatus = 'pending' | 'approved' | 'rejected' | 'cancelled';
type ApprovalStage = 'supervisor' | 'hr' | 'completed';
type ViewMode = 'list' | 'calendar' | 'report';

type Employee = {
    id: number;
    employee_id: string | null;
    name: string | null;
};

type LeaveRequest = {
    id: number;
    employee: Employee | null;
    leave_type: string;
    start_date: string;
    end_date: string;
    duration_type: string;
    requested_days: number;
    reason: string;
    status: LeaveStatus;
    approval_stage: ApprovalStage;
    submitted_at: string;
    supervisor_reviewed_at: string | null;
    supervisor_review_notes: string | null;
    supervisor_reviewer: string | null;
    reviewed_at: string | null;
    review_notes: string | null;
    reviewer: string | null;
    has_attachment: boolean;
    attachment_name: string | null;
};

type CalendarEntry = {
    id: number;
    employee_name: string;
    employee_id: string | null;
    leave_type: string;
    start_date: string;
    end_date: string;
    requested_days: number;
    status: LeaveStatus;
    approval_stage: ApprovalStage;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type LeaveRequestProps = {
    requests: {
        data: LeaveRequest[];
        current_page: number;
        last_page: number;
        from: number | null;
        to: number | null;
        total: number;
        links: PaginationLink[];
    };
    filters: {
        search: string;
        status: string;
        stage: string;
        leave_type_id: string;
        month: string;
    };
    statistics: {
        total: number;
        pending_supervisor: number;
        pending_hr: number;
        approved: number;
        rejected: number;
    };
    leaveTypes: { id: number; name: string }[];
    calendar: CalendarEntry[];
    reportByType: {
        leave_type: string;
        status: LeaveStatus;
        days: number;
        total: number;
    }[];
    permissions: {
        can_supervise: boolean;
        can_manage: boolean;
        can_approve: boolean;
    };
};

type ReviewForm = {
    status: 'approved' | 'rejected';
    review_notes: string;
};

const statusLabels: Record<LeaveStatus, string> = {
    pending: 'Menunggu',
    approved: 'Diluluskan',
    rejected: 'Ditolak',
    cancelled: 'Dibatalkan',
};

const statusStyles: Record<LeaveStatus, string> = {
    pending:
        'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300',
    approved:
        'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
    rejected: 'border-red-500/30 bg-red-500/10 text-red-700 dark:text-red-300',
    cancelled:
        'border-slate-500/30 bg-slate-500/10 text-slate-700 dark:text-slate-300',
};

const stageLabels: Record<ApprovalStage, string> = {
    supervisor: 'Penyelia',
    hr: 'HR',
    completed: 'Selesai',
};

function formatDate(value: string): string {
    const date = new Date(`${value.slice(0, 10)}T00:00:00`);

    return Number.isNaN(date.getTime())
        ? value
        : new Intl.DateTimeFormat('ms-MY', {
              day: '2-digit',
              month: 'short',
              year: 'numeric',
          }).format(date);
}

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

function paginationLabel(label: string): string {
    return label
        .replace('&laquo; Previous', 'Sebelum')
        .replace('Next &raquo;', 'Seterusnya');
}

function ReviewDialog({
    request,
    mode,
}: {
    request: LeaveRequest;
    mode: 'supervisor' | 'hr';
}) {
    const [open, setOpen] = useState(false);
    const { data, setData, patch, processing, errors, reset } =
        useForm<ReviewForm>({
            status: 'approved',
            review_notes: '',
        });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const suffix = mode === 'supervisor' ? 'semakan-penyelia' : 'semakan';

        patch(`/permohonan-cuti/${request.id}/${suffix}`, {
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
                        {request.leave_type} ·{' '}
                        {request.requested_days.toFixed(1)} hari
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
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
                                        ? 'Sokong dan hantar kepada Pengurus HR'
                                        : 'Luluskan'}
                                </SelectItem>
                                <SelectItem value="rejected">Tolak</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError message={errors.status} />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor={`notes-${mode}-${request.id}`}>
                            Catatan {data.status === 'rejected' && '(Wajib)'}
                        </Label>
                        <textarea
                            id={`notes-${mode}-${request.id}`}
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

function CancellationDialog({ request }: { request: LeaveRequest }) {
    const [open, setOpen] = useState(false);
    const { data, setData, patch, processing, errors, reset } = useForm({
        cancellation_notes: '',
    });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        patch(`/permohonan-cuti/${request.id}/batal-kelulusan`, {
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
                    Batal Cuti
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Batalkan Cuti Diluluskan</DialogTitle>
                    <DialogDescription>
                        Baki akan dipulangkan secara automatik dan tindakan ini
                        direkodkan dalam Audit Trail.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-2">
                        <Label htmlFor={`cancel-${request.id}`}>
                            Sebab Pembatalan
                        </Label>
                        <textarea
                            id={`cancel-${request.id}`}
                            rows={4}
                            maxLength={1000}
                            value={data.cancellation_notes}
                            onChange={(event) =>
                                setData(
                                    'cancellation_notes',
                                    event.target.value,
                                )
                            }
                            className="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
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

function LeaveCalendar({
    month,
    entries,
}: {
    month: string;
    entries: CalendarEntry[];
}) {
    const cells = useMemo(() => {
        const [year, monthNumber] = month.split('-').map(Number);
        const first = new Date(year, monthNumber - 1, 1);
        const lastDay = new Date(year, monthNumber, 0).getDate();
        const mondayOffset = (first.getDay() + 6) % 7;

        return [
            ...Array.from({ length: mondayOffset }, () => null),
            ...Array.from({ length: lastDay }, (_, index) => index + 1),
        ];
    }, [month]);
    const entriesForDay = (day: number) => {
        const date = `${month}-${String(day).padStart(2, '0')}`;

        return entries.filter(
            (entry) => entry.start_date <= date && entry.end_date >= date,
        );
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle>Kalendar Cuti Kakitangan</CardTitle>
                <CardDescription>
                    Permohonan menunggu dan cuti diluluskan bagi bulan dipilih.
                </CardDescription>
            </CardHeader>
            <CardContent className="overflow-x-auto">
                <div className="min-w-4xl">
                    <div className="grid grid-cols-7 gap-1 text-center text-xs font-medium text-muted-foreground">
                        {['Isn', 'Sel', 'Rab', 'Kha', 'Jum', 'Sab', 'Aha'].map(
                            (day) => (
                                <div key={day} className="p-2">
                                    {day}
                                </div>
                            ),
                        )}
                    </div>
                    <div className="grid grid-cols-7 gap-1">
                        {cells.map((day, index) => (
                            <div
                                key={`${day ?? 'empty'}-${index}`}
                                className="min-h-32 rounded-md border p-2"
                            >
                                {day && (
                                    <>
                                        <p className="text-sm font-semibold">
                                            {day}
                                        </p>
                                        <div className="mt-2 space-y-1">
                                            {entriesForDay(day)
                                                .slice(0, 4)
                                                .map((entry) => (
                                                    <div
                                                        key={entry.id}
                                                        className={`rounded px-2 py-1 text-[11px] ${
                                                            entry.status ===
                                                            'approved'
                                                                ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
                                                                : 'bg-amber-500/10 text-amber-700 dark:text-amber-300'
                                                        }`}
                                                        title={`${entry.employee_name} · ${entry.leave_type}`}
                                                    >
                                                        <p className="truncate font-medium">
                                                            {
                                                                entry.employee_name
                                                            }
                                                        </p>
                                                        <p className="truncate">
                                                            {entry.leave_type}
                                                        </p>
                                                    </div>
                                                ))}
                                            {entriesForDay(day).length > 4 && (
                                                <p className="text-[11px] text-muted-foreground">
                                                    +
                                                    {entriesForDay(day).length -
                                                        4}{' '}
                                                    lagi
                                                </p>
                                            )}
                                        </div>
                                    </>
                                )}
                            </div>
                        ))}
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

export default function LeaveRequests({
    requests,
    filters,
    statistics,
    leaveTypes,
    calendar,
    reportByType,
    permissions,
}: LeaveRequestProps) {
    const [view, setView] = useState<ViewMode>('list');
    const [search, setSearch] = useState(filters.search);
    const [status, setStatus] = useState(filters.status || 'all');
    const [stage, setStage] = useState(filters.stage || 'all');
    const [leaveTypeId, setLeaveTypeId] = useState(
        filters.leave_type_id || 'all',
    );
    const [month, setMonth] = useState(filters.month);
    const filterParams = {
        search,
        status: status === 'all' ? '' : status,
        stage: stage === 'all' ? '' : stage,
        leave_type_id: leaveTypeId === 'all' ? '' : leaveTypeId,
        month,
    };
    const applyFilters = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.get('/permohonan-cuti', filterParams, {
            preserveState: true,
            replace: true,
        });
    };
    const reportUrl = `/permohonan-cuti/laporan.csv?${new URLSearchParams(
        filterParams,
    ).toString()}`;

    return (
        <>
            <Head title="Permohonan Cuti" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Pengurusan Permohonan Cuti
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Semakan penyelia, kelulusan Pengurus HR, kalendar
                            dan laporan dalam satu aliran.
                        </p>
                    </div>
                    <Button asChild variant="outline">
                        <a href={reportUrl}>
                            <Download />
                            Muat Turun CSV
                        </a>
                    </Button>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    {[
                        ['Jumlah', statistics.total],
                        ['Menunggu Penyelia', statistics.pending_supervisor],
                        ['Menunggu Pengurus HR', statistics.pending_hr],
                        ['Diluluskan', statistics.approved],
                        ['Ditolak', statistics.rejected],
                    ].map(([label, value]) => (
                        <Card key={String(label)} className="gap-2">
                            <CardHeader>
                                <CardDescription>{label}</CardDescription>
                                <CardTitle className="text-2xl">
                                    {value}
                                </CardTitle>
                            </CardHeader>
                        </Card>
                    ))}
                </div>

                <Card>
                    <CardContent className="pt-6">
                        <form
                            onSubmit={applyFilters}
                            className="grid gap-3 md:grid-cols-2 xl:grid-cols-6"
                        >
                            <div className="relative xl:col-span-2">
                                <Search className="absolute top-2.5 left-3 size-4 text-muted-foreground" />
                                <Input
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    placeholder="Nama atau ID pekerja"
                                    className="pl-9"
                                />
                            </div>
                            <Input
                                type="month"
                                value={month}
                                onChange={(event) =>
                                    setMonth(event.target.value)
                                }
                            />
                            <Select value={status} onValueChange={setStatus}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        Semua Status
                                    </SelectItem>
                                    <SelectItem value="pending">
                                        Menunggu
                                    </SelectItem>
                                    <SelectItem value="approved">
                                        Diluluskan
                                    </SelectItem>
                                    <SelectItem value="rejected">
                                        Ditolak
                                    </SelectItem>
                                    <SelectItem value="cancelled">
                                        Dibatalkan
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <Select value={stage} onValueChange={setStage}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Peringkat" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        Semua Peringkat
                                    </SelectItem>
                                    <SelectItem value="supervisor">
                                        Penyelia
                                    </SelectItem>
                                    <SelectItem value="hr">HR</SelectItem>
                                    <SelectItem value="completed">
                                        Selesai
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <Select
                                value={leaveTypeId}
                                onValueChange={setLeaveTypeId}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Jenis cuti" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        Semua Jenis
                                    </SelectItem>
                                    {leaveTypes.map((type) => (
                                        <SelectItem
                                            key={type.id}
                                            value={String(type.id)}
                                        >
                                            {type.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Button type="submit" className="xl:col-start-6">
                                Tapis
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <div className="flex flex-wrap gap-2">
                    <Button
                        variant={view === 'list' ? 'default' : 'outline'}
                        onClick={() => setView('list')}
                    >
                        <ClipboardCheck />
                        Senarai
                    </Button>
                    <Button
                        variant={view === 'calendar' ? 'default' : 'outline'}
                        onClick={() => setView('calendar')}
                    >
                        <CalendarDays />
                        Kalendar
                    </Button>
                    <Button
                        variant={view === 'report' ? 'default' : 'outline'}
                        onClick={() => setView('report')}
                    >
                        <FileChartColumn />
                        Ringkasan Laporan
                    </Button>
                </div>

                {view === 'calendar' && (
                    <LeaveCalendar month={filters.month} entries={calendar} />
                )}

                {view === 'report' && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Ringkasan Mengikut Jenis Cuti</CardTitle>
                            <CardDescription>
                                Berdasarkan penapis dan bulan semasa.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Jenis Cuti</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Permohonan</TableHead>
                                        <TableHead>Jumlah Hari</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {reportByType.map((row, index) => (
                                        <TableRow
                                            key={`${row.leave_type}-${row.status}-${index}`}
                                        >
                                            <TableCell>
                                                {row.leave_type}
                                            </TableCell>
                                            <TableCell>
                                                <Badge
                                                    variant="outline"
                                                    className={
                                                        statusStyles[row.status]
                                                    }
                                                >
                                                    {statusLabels[row.status]}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>{row.total}</TableCell>
                                            <TableCell>
                                                {row.days.toFixed(1)}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                )}

                {view === 'list' && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Senarai Permohonan</CardTitle>
                            <CardDescription>
                                {requests.from ?? 0}–{requests.to ?? 0} daripada{' '}
                                {requests.total} rekod.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {requests.data.length === 0 ? (
                                <div className="rounded-lg border border-dashed p-10 text-center text-sm text-muted-foreground">
                                    Tiada permohonan untuk penapis ini.
                                </div>
                            ) : (
                                requests.data.map((request) => (
                                    <div
                                        key={request.id}
                                        className="space-y-4 rounded-xl border p-4"
                                    >
                                        <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                            <div className="space-y-1">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <p className="font-semibold">
                                                        {request.employee
                                                            ?.name ?? 'Pekerja'}
                                                    </p>
                                                    <Badge variant="secondary">
                                                        {request.employee
                                                            ?.employee_id ??
                                                            '-'}
                                                    </Badge>
                                                </div>
                                                <p className="text-sm">
                                                    {request.leave_type} ·{' '}
                                                    {formatDate(
                                                        request.start_date,
                                                    )}{' '}
                                                    –{' '}
                                                    {formatDate(
                                                        request.end_date,
                                                    )}{' '}
                                                    ·{' '}
                                                    {request.requested_days.toFixed(
                                                        1,
                                                    )}{' '}
                                                    hari
                                                </p>
                                                <p className="text-sm text-muted-foreground">
                                                    {request.reason}
                                                </p>
                                            </div>
                                            <div className="flex flex-wrap gap-2">
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
                                                <Badge variant="secondary">
                                                    {
                                                        stageLabels[
                                                            request
                                                                .approval_stage
                                                        ]
                                                    }
                                                </Badge>
                                            </div>
                                        </div>

                                        <div className="grid gap-3 rounded-lg bg-muted/40 p-3 text-sm md:grid-cols-2">
                                            <p>
                                                <strong>Dihantar:</strong>{' '}
                                                {formatDateTime(
                                                    request.submitted_at,
                                                )}
                                            </p>
                                            <p>
                                                <strong>Penyelia:</strong>{' '}
                                                {request.supervisor_reviewer ??
                                                    'Belum disemak'}
                                            </p>
                                            {request.supervisor_review_notes && (
                                                <p className="md:col-span-2">
                                                    <strong>
                                                        Catatan Penyelia:
                                                    </strong>{' '}
                                                    {
                                                        request.supervisor_review_notes
                                                    }
                                                </p>
                                            )}
                                            {request.review_notes && (
                                                <p className="md:col-span-2">
                                                    <strong>Catatan HR:</strong>{' '}
                                                    {request.review_notes}
                                                </p>
                                            )}
                                        </div>

                                        <div className="flex flex-wrap items-center gap-2">
                                            {request.has_attachment && (
                                                <Button
                                                    asChild
                                                    size="sm"
                                                    variant="outline"
                                                >
                                                    <a
                                                        href={`/cuti-saya/${request.id}/lampiran`}
                                                    >
                                                        <Paperclip />
                                                        {request.attachment_name ??
                                                            'Lampiran'}
                                                    </a>
                                                </Button>
                                            )}
                                            {permissions.can_supervise &&
                                                request.status === 'pending' &&
                                                request.approval_stage ===
                                                    'supervisor' && (
                                                    <ReviewDialog
                                                        request={request}
                                                        mode="supervisor"
                                                    />
                                                )}
                                            {permissions.can_approve &&
                                                request.status === 'pending' &&
                                                request.approval_stage ===
                                                    'hr' && (
                                                    <ReviewDialog
                                                        request={request}
                                                        mode="hr"
                                                    />
                                                )}
                                            {permissions.can_approve &&
                                                request.status ===
                                                    'approved' && (
                                                    <CancellationDialog
                                                        request={request}
                                                    />
                                                )}
                                            {request.status === 'approved' && (
                                                <span className="inline-flex items-center gap-1 text-sm text-emerald-700 dark:text-emerald-300">
                                                    <CheckCircle2 className="size-4" />
                                                    Diluluskan oleh{' '}
                                                    {request.reviewer ?? 'HR'}
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                ))
                            )}

                            {requests.last_page > 1 && (
                                <div className="flex flex-wrap justify-center gap-2 pt-2">
                                    {requests.links.map((link, index) => (
                                        <Button
                                            key={`${link.label}-${index}`}
                                            type="button"
                                            size="sm"
                                            variant={
                                                link.active
                                                    ? 'default'
                                                    : 'outline'
                                            }
                                            disabled={!link.url}
                                            onClick={() => {
                                                if (link.url) {
                                                    router.visit(link.url, {
                                                        preserveState: true,
                                                    });
                                                }
                                            }}
                                        >
                                            {paginationLabel(link.label)}
                                        </Button>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                )}

                {permissions.can_approve && (
                    <Card className="border-primary/20 bg-primary/5">
                        <CardContent className="flex items-start gap-3 pt-6">
                            <ShieldCheck className="mt-0.5 size-5 text-primary" />
                            <p className="text-sm text-muted-foreground">
                                Kelulusan akhir HR memotong baki secara
                                automatik. Pembatalan cuti diluluskan akan
                                memulangkan baki dan direkodkan dalam Audit
                                Trail.
                            </p>
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}
