import { Head, router, useForm } from '@inertiajs/react';
import {
    AlertCircle,
    Bell,
    CalendarCheck2,
    CalendarDays,
    Database,
    FileClock,
    Paperclip,
    Send,
    WalletCards,
    XCircle,
} from 'lucide-react';
import { useMemo } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
type DurationType = 'full_day' | 'first_half' | 'second_half';
type ApprovalStage = 'supervisor' | 'hr' | 'completed';

type Employee = {
    id: number;
    employee_id: string | null;
    name: string | null;
};

type LeaveType = {
    id: number;
    label: string;
    allow_half_day: boolean;
    requires_attachment: boolean;
    deduct_balance: boolean;
};

type LeaveBalance = {
    leave_type_id: number;
    leave_type: string;
    deduct_balance: boolean;
    entitled: number | null;
    carry_forward: number | null;
    adjustment: number | null;
    balance: number | null;
    reserved: number | null;
    available: number | null;
};

type LeaveRequest = {
    id: number;
    leave_type: string;
    start_date: string;
    end_date: string;
    duration_type: DurationType;
    requested_days: number;
    reason: string;
    status: LeaveStatus;
    approval_stage: ApprovalStage;
    submitted_at: string;
    supervisor_review_notes: string | null;
    supervisor_reviewer: string | null;
    review_notes: string | null;
    reviewer: string | null;
    has_attachment: boolean;
    attachment_name: string | null;
};

type LegacyLeave = {
    id: number;
    leave_type: string | null;
    start_date: string | null;
    end_date: string | null;
    requested_days: string | null;
    balance: string | null;
    status: string | null;
};

type LeaveNotification = {
    id: number;
    title: string;
    message: string;
    read_at: string | null;
    created_at: string;
};

type LeaveProps = {
    employee: Employee | null;
    leaveTypes: LeaveType[];
    balances: LeaveBalance[];
    summary: {
        entitlement: string | null;
        legacy_balance: string | null;
        pending: number;
        approved: number;
        unread_notifications: number;
    };
    requests: LeaveRequest[];
    legacyLeave: LegacyLeave[];
    notifications: LeaveNotification[];
};

type LeaveForm = {
    leave_type_id: string;
    start_date: string;
    end_date: string;
    duration_type: DurationType;
    reason: string;
    attachment: File | null;
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
    supervisor: 'Semakan penyelia',
    hr: 'Kelulusan HR',
    completed: 'Selesai',
};

const durationLabels: Record<DurationType, string> = {
    full_day: 'Sehari penuh',
    first_half: 'Separuh hari pertama',
    second_half: 'Separuh hari kedua',
};

function formatDate(value: string | null): string {
    if (!value) {
        return '-';
    }

    const date = new Date(`${value.slice(0, 10)}T00:00:00`);

    return Number.isNaN(date.getTime())
        ? value
        : new Intl.DateTimeFormat('ms-MY', {
              day: '2-digit',
              month: 'short',
              year: 'numeric',
          }).format(date);
}

function formatDateTime(value: string): string {
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

function formatDays(value: number | null): string {
    return value === null ? '-' : `${value.toFixed(1)} hari`;
}

function StatusBadge({ status }: { status: LeaveStatus }) {
    return (
        <Badge variant="outline" className={statusStyles[status]}>
            {statusLabels[status]}
        </Badge>
    );
}

export default function EmployeeLeave({
    employee,
    leaveTypes,
    balances,
    summary,
    requests,
    legacyLeave,
    notifications,
}: LeaveProps) {
    const { data, setData, post, processing, errors, reset } =
        useForm<LeaveForm>({
            leave_type_id: '',
            start_date: '',
            end_date: '',
            duration_type: 'full_day',
            reason: '',
            attachment: null,
        });
    const selectedType = useMemo(
        () =>
            leaveTypes.find(
                (leaveType) => leaveType.id === Number(data.leave_type_id),
            ) ?? null,
        [data.leave_type_id, leaveTypes],
    );
    const formErrors = errors as Record<string, string>;

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        post('/cuti-saya', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    const cancelRequest = (request: LeaveRequest) => {
        if (
            window.confirm(
                `Batalkan permohonan ${request.leave_type} pada ${formatDate(request.start_date)}?`,
            )
        ) {
            router.patch(
                `/cuti-saya/${request.id}/batal`,
                {},
                { preserveScroll: true },
            );
        }
    };

    if (!employee) {
        return (
            <>
                <Head title="Cuti Saya" />
                <div className="mx-auto flex w-full max-w-4xl flex-1 p-4 md:p-6">
                    <Alert variant="destructive">
                        <AlertCircle />
                        <AlertTitle>Modul cuti belum tersedia</AlertTitle>
                        <AlertDescription>
                            Akaun anda belum dipautkan kepada rekod pekerja
                            aktif. Hubungi Super Admin untuk melengkapkan
                            pautan.
                        </AlertDescription>
                    </Alert>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title="Cuti Saya" />
            <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="space-y-1">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Cuti Saya
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Mohon cuti, semak baki dan ikuti kelulusan penyelia
                        sehingga HR.
                    </p>
                </div>

                {notifications.length > 0 && (
                    <Card>
                        <CardHeader className="flex-row items-start justify-between gap-4">
                            <div>
                                <CardTitle className="flex items-center gap-2">
                                    <Bell className="size-5 text-primary" />
                                    Notifikasi Cuti
                                    {summary.unread_notifications > 0 && (
                                        <Badge>
                                            {summary.unread_notifications}{' '}
                                            baharu
                                        </Badge>
                                    )}
                                </CardTitle>
                                <CardDescription>
                                    Keputusan dan perkembangan permohonan
                                    terkini.
                                </CardDescription>
                            </div>
                            {summary.unread_notifications > 0 && (
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={() =>
                                        router.patch(
                                            '/cuti-saya/notifikasi/dibaca',
                                            {},
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    Tandakan Dibaca
                                </Button>
                            )}
                        </CardHeader>
                        <CardContent className="grid gap-3 md:grid-cols-2">
                            {notifications.slice(0, 6).map((notification) => (
                                <div
                                    key={notification.id}
                                    className={`rounded-lg border p-3 ${
                                        notification.read_at
                                            ? 'bg-muted/20'
                                            : 'border-primary/30 bg-primary/5'
                                    }`}
                                >
                                    <p className="font-medium">
                                        {notification.title}
                                    </p>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        {notification.message}
                                    </p>
                                    <p className="mt-2 text-xs text-muted-foreground">
                                        {formatDateTime(
                                            notification.created_at,
                                        )}
                                    </p>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                )}

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {balances.map((balance) => (
                        <Card key={balance.leave_type_id} className="gap-3">
                            <CardHeader>
                                <CardDescription>
                                    {balance.leave_type}
                                </CardDescription>
                                <CardTitle className="flex items-center gap-2 text-2xl">
                                    <WalletCards className="size-5 text-primary" />
                                    {balance.deduct_balance
                                        ? formatDays(balance.available)
                                        : 'Tiada potongan'}
                                </CardTitle>
                            </CardHeader>
                            {balance.deduct_balance && (
                                <CardContent className="text-xs text-muted-foreground">
                                    Baki sebenar {formatDays(balance.balance)} ·
                                    Menunggu {formatDays(balance.reserved)}
                                </CardContent>
                            )}
                        </Card>
                    ))}
                </div>

                <div className="grid gap-6 xl:grid-cols-[0.8fr_1.2fr]">
                    <Card className="h-fit">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <CalendarDays className="size-5 text-primary" />
                                Permohonan Cuti Baharu
                            </CardTitle>
                            <CardDescription>
                                Hari bekerja mengecualikan Sabtu, Ahad dan cuti
                                umum yang ditetapkan HR.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={submit} className="space-y-5">
                                {formErrors.leave && (
                                    <Alert variant="destructive">
                                        <AlertCircle />
                                        <AlertTitle>
                                            Permohonan tidak dapat dihantar
                                        </AlertTitle>
                                        <AlertDescription>
                                            {formErrors.leave}
                                        </AlertDescription>
                                    </Alert>
                                )}

                                <div className="space-y-2">
                                    <Label htmlFor="leave-type">
                                        Jenis Cuti
                                    </Label>
                                    <Select
                                        value={data.leave_type_id}
                                        onValueChange={(value) => {
                                            setData('leave_type_id', value);
                                            setData(
                                                'duration_type',
                                                'full_day',
                                            );
                                        }}
                                    >
                                        <SelectTrigger id="leave-type">
                                            <SelectValue placeholder="Pilih jenis cuti" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {leaveTypes.map((type) => (
                                                <SelectItem
                                                    key={type.id}
                                                    value={String(type.id)}
                                                >
                                                    {type.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        message={errors.leave_type_id}
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="leave-duration">
                                        Tempoh Harian
                                    </Label>
                                    <Select
                                        value={data.duration_type}
                                        onValueChange={(
                                            value: DurationType,
                                        ) => {
                                            setData('duration_type', value);

                                            if (
                                                value !== 'full_day' &&
                                                data.start_date
                                            ) {
                                                setData(
                                                    'end_date',
                                                    data.start_date,
                                                );
                                            }
                                        }}
                                        disabled={!selectedType?.allow_half_day}
                                    >
                                        <SelectTrigger id="leave-duration">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="full_day">
                                                Sehari penuh
                                            </SelectItem>
                                            <SelectItem value="first_half">
                                                Separuh hari pertama
                                            </SelectItem>
                                            <SelectItem value="second_half">
                                                Separuh hari kedua
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        message={errors.duration_type}
                                    />
                                </div>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="leave-start">
                                            Tarikh Mula
                                        </Label>
                                        <Input
                                            id="leave-start"
                                            type="date"
                                            value={data.start_date}
                                            min={new Date()
                                                .toISOString()
                                                .slice(0, 10)}
                                            onChange={(event) => {
                                                setData(
                                                    'start_date',
                                                    event.target.value,
                                                );

                                                if (
                                                    data.duration_type !==
                                                    'full_day'
                                                ) {
                                                    setData(
                                                        'end_date',
                                                        event.target.value,
                                                    );
                                                }
                                            }}
                                        />
                                        <InputError
                                            message={errors.start_date}
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="leave-end">
                                            Tarikh Tamat
                                        </Label>
                                        <Input
                                            id="leave-end"
                                            type="date"
                                            value={data.end_date}
                                            min={
                                                data.start_date ||
                                                new Date()
                                                    .toISOString()
                                                    .slice(0, 10)
                                            }
                                            disabled={
                                                data.duration_type !==
                                                'full_day'
                                            }
                                            onChange={(event) =>
                                                setData(
                                                    'end_date',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                        <InputError message={errors.end_date} />
                                    </div>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="leave-reason">
                                        Tujuan / Catatan
                                    </Label>
                                    <textarea
                                        id="leave-reason"
                                        value={data.reason}
                                        onChange={(event) =>
                                            setData(
                                                'reason',
                                                event.target.value,
                                            )
                                        }
                                        rows={4}
                                        maxLength={1000}
                                        className="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                        placeholder="Nyatakan tujuan permohonan cuti"
                                    />
                                    <InputError message={errors.reason} />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="leave-attachment">
                                        Lampiran{' '}
                                        {selectedType?.requires_attachment
                                            ? '(Wajib)'
                                            : '(Pilihan)'}
                                    </Label>
                                    <Input
                                        id="leave-attachment"
                                        type="file"
                                        accept=".pdf,.jpg,.jpeg,.png"
                                        onChange={(event) =>
                                            setData(
                                                'attachment',
                                                event.target.files?.[0] ?? null,
                                            )
                                        }
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        PDF, JPG atau PNG. Maksimum 5 MB.
                                    </p>
                                    <InputError message={errors.attachment} />
                                </div>

                                <Button
                                    type="submit"
                                    className="w-full"
                                    disabled={processing}
                                >
                                    <Send />
                                    {processing
                                        ? 'Menghantar...'
                                        : 'Hantar Permohonan'}
                                </Button>
                            </form>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <FileClock className="size-5 text-primary" />
                                Permohonan Terkini
                            </CardTitle>
                            <CardDescription>
                                Status kelulusan penyelia dan HR bagi 30
                                permohonan terkini.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {requests.length === 0 ? (
                                <p className="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground">
                                    Belum ada permohonan cuti.
                                </p>
                            ) : (
                                <div className="divide-y rounded-xl border">
                                    {requests.map((request) => (
                                        <div
                                            key={request.id}
                                            className="space-y-3 p-4"
                                        >
                                            <div className="flex flex-wrap items-start justify-between gap-3">
                                                <div>
                                                    <p className="font-medium">
                                                        {request.leave_type}
                                                    </p>
                                                    <p className="text-sm text-muted-foreground">
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
                                                </div>
                                                <div className="flex flex-wrap gap-2">
                                                    <StatusBadge
                                                        status={request.status}
                                                    />
                                                    {request.status ===
                                                        'pending' && (
                                                        <Badge variant="secondary">
                                                            {
                                                                stageLabels[
                                                                    request
                                                                        .approval_stage
                                                                ]
                                                            }
                                                        </Badge>
                                                    )}
                                                </div>
                                            </div>
                                            <p className="text-sm">
                                                {request.reason}
                                            </p>
                                            <div className="flex flex-wrap items-center gap-3 text-xs text-muted-foreground">
                                                <span>
                                                    {
                                                        durationLabels[
                                                            request
                                                                .duration_type
                                                        ]
                                                    }
                                                </span>
                                                <span>
                                                    Dihantar{' '}
                                                    {formatDateTime(
                                                        request.submitted_at,
                                                    )}
                                                </span>
                                                {request.has_attachment && (
                                                    <a
                                                        href={`/cuti-saya/${request.id}/lampiran`}
                                                        className="inline-flex items-center gap-1 text-primary hover:underline"
                                                    >
                                                        <Paperclip className="size-3.5" />
                                                        {request.attachment_name ??
                                                            'Lampiran'}
                                                    </a>
                                                )}
                                            </div>
                                            {(request.supervisor_review_notes ||
                                                request.review_notes) && (
                                                <div className="rounded-lg bg-muted/50 p-3 text-sm">
                                                    {request.supervisor_review_notes && (
                                                        <p>
                                                            <strong>
                                                                Penyelia:
                                                            </strong>{' '}
                                                            {
                                                                request.supervisor_review_notes
                                                            }
                                                        </p>
                                                    )}
                                                    {request.review_notes && (
                                                        <p>
                                                            <strong>HR:</strong>{' '}
                                                            {
                                                                request.review_notes
                                                            }
                                                        </p>
                                                    )}
                                                </div>
                                            )}
                                            {request.status === 'pending' && (
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        cancelRequest(request)
                                                    }
                                                >
                                                    <XCircle />
                                                    Batalkan
                                                </Button>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-6 xl:grid-cols-[0.35fr_0.65fr]">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <CalendarCheck2 className="size-5 text-primary" />
                                Ringkasan Rekod Asal
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="grid grid-cols-2 gap-3">
                            <div className="rounded-lg border p-3">
                                <p className="text-xs text-muted-foreground">
                                    Kelayakan
                                </p>
                                <p className="text-xl font-semibold">
                                    {summary.entitlement ?? '-'}
                                </p>
                            </div>
                            <div className="rounded-lg border p-3">
                                <p className="text-xs text-muted-foreground">
                                    Baki
                                </p>
                                <p className="text-xl font-semibold">
                                    {summary.legacy_balance ?? '-'}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Database className="size-5 text-primary" />
                                Sejarah Cuti Asal (db_spp)
                            </CardTitle>
                            <CardDescription>
                                Rujukan baca sahaja; tidak mengubah rekod sistem
                                lama.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Jenis</TableHead>
                                        <TableHead>Tarikh</TableHead>
                                        <TableHead>Hari</TableHead>
                                        <TableHead>Baki</TableHead>
                                        <TableHead>Status</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {legacyLeave.length === 0 ? (
                                        <TableRow>
                                            <TableCell
                                                colSpan={5}
                                                className="text-center text-muted-foreground"
                                            >
                                                Tiada sejarah cuti asal.
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        legacyLeave.map((leave) => (
                                            <TableRow key={leave.id}>
                                                <TableCell>
                                                    {leave.leave_type || '-'}
                                                </TableCell>
                                                <TableCell>
                                                    {formatDate(
                                                        leave.start_date,
                                                    )}{' '}
                                                    –{' '}
                                                    {formatDate(leave.end_date)}
                                                </TableCell>
                                                <TableCell>
                                                    {leave.requested_days ||
                                                        '-'}
                                                </TableCell>
                                                <TableCell>
                                                    {leave.balance || '-'}
                                                </TableCell>
                                                <TableCell>
                                                    {leave.status || '-'}
                                                </TableCell>
                                            </TableRow>
                                        ))
                                    )}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}
