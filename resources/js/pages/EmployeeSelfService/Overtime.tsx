import { Head, router, useForm } from '@inertiajs/react';
import {
    AlertCircle,
    Bell,
    CalendarClock,
    CheckCircle2,
    Clock3,
    Database,
    Paperclip,
    Send,
    TimerReset,
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

type Status = 'pending' | 'approved' | 'rejected' | 'cancelled';
type Stage = 'supervisor' | 'hr' | 'completed';
type MatchStatus = 'matched' | 'partial' | 'incomplete' | 'not_found';

type Props = {
    employee: {
        id: number;
        employee_id: string | null;
        name: string | null;
    } | null;
    overtimeTypes: {
        id: number;
        name: string;
        rate_multiplier: number;
        minimum_minutes: number;
        maximum_hours: number;
        requires_attachment: boolean;
    }[];
    summary: {
        pending: number;
        approved: number;
        approved_hours: number;
        unread_notifications: number;
    };
    requests: {
        id: number;
        overtime_type: string;
        rate_multiplier: number;
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
        attendance_match_status: MatchStatus;
        attendance: {
            clock_in_at: string;
            clock_out_at: string | null;
            status: string;
        } | null;
        submitted_at: string;
        supervisor_reviewer: string | null;
        supervisor_review_notes: string | null;
        reviewer: string | null;
        review_notes: string | null;
        has_attachment: boolean;
        attachment_name: string | null;
    }[];
    legacyOvertime: {
        id: number;
        overtime_type: string | null;
        work_date: string | null;
        start_time: string | null;
        end_time: string | null;
        notes: string | null;
    }[];
    notifications: {
        id: number;
        title: string;
        message: string;
        read_at: string | null;
        created_at: string;
    }[];
    attendanceEvidence: {
        id: number;
        attendance_date: string;
        clock_in_at: string;
        clock_out_at: string | null;
        status: string;
    }[];
};

const statusLabel: Record<Status, string> = {
    pending: 'Menunggu',
    approved: 'Diluluskan',
    rejected: 'Ditolak',
    cancelled: 'Dibatalkan',
};

const statusStyle: Record<Status, string> = {
    pending:
        'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300',
    approved:
        'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
    rejected: 'border-red-500/30 bg-red-500/10 text-red-700 dark:text-red-300',
    cancelled:
        'border-slate-500/30 bg-slate-500/10 text-slate-700 dark:text-slate-300',
};

const stageLabel: Record<Stage, string> = {
    supervisor: 'Semakan penyelia',
    hr: 'Kelulusan HR',
    completed: 'Selesai',
};

const matchLabel: Record<MatchStatus, string> = {
    matched: 'Sepadan',
    partial: 'Sebahagian',
    incomplete: 'Belum clock-out',
    not_found: 'Tiada rekod',
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

export default function EmployeeOvertime({
    employee,
    overtimeTypes,
    summary,
    requests,
    legacyOvertime,
    notifications,
    attendanceEvidence,
}: Props) {
    const { data, setData, post, processing, errors, reset } = useForm({
        overtime_type_id: '',
        work_date: '',
        start_time: '',
        end_time: '',
        break_minutes: '0',
        reason: '',
        work_description: '',
        attachment: null as File | null,
    });
    const selectedType = useMemo(
        () =>
            overtimeTypes.find(
                (type) => type.id === Number(data.overtime_type_id),
            ) ?? null,
        [data.overtime_type_id, overtimeTypes],
    );
    const selectedAttendance = attendanceEvidence.find(
        (record) => record.attendance_date === data.work_date,
    );
    const formErrors = errors as Record<string, string>;
    const summaryCards: {
        label: string;
        value: string | number;
        icon: typeof Clock3;
    }[] = [
        { label: 'Menunggu', value: summary.pending, icon: Clock3 },
        {
            label: 'Diluluskan',
            value: summary.approved,
            icon: CheckCircle2,
        },
        {
            label: 'Jumlah Jam Diluluskan',
            value: summary.approved_hours.toFixed(2),
            icon: CalendarClock,
        },
    ];
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        post('/ot-saya', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    if (!employee) {
        return (
            <>
                <Head title="OT Saya" />
                <div className="mx-auto flex w-full max-w-4xl flex-1 p-4 md:p-6">
                    <Alert variant="destructive">
                        <AlertCircle />
                        <AlertTitle>Modul OT belum tersedia</AlertTitle>
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
            <Head title="OT Saya" />
            <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="flex items-center gap-2 text-2xl font-semibold tracking-tight">
                        <TimerReset className="size-6 text-primary" />
                        OT Saya
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Mohon kerja lebih masa dan ikuti semakan penyelia
                        sehingga kelulusan HR.
                    </p>
                </div>

                {notifications.length > 0 && (
                    <Card>
                        <CardHeader className="flex-row items-start justify-between gap-4">
                            <div>
                                <CardTitle className="flex items-center gap-2">
                                    <Bell className="size-5 text-primary" />
                                    Notifikasi OT
                                    {summary.unread_notifications > 0 && (
                                        <Badge>
                                            {summary.unread_notifications}{' '}
                                            baharu
                                        </Badge>
                                    )}
                                </CardTitle>
                                <CardDescription>
                                    Perkembangan dan keputusan permohonan
                                    terkini.
                                </CardDescription>
                            </div>
                            {summary.unread_notifications > 0 && (
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() =>
                                        router.patch(
                                            '/ot-saya/notifikasi/dibaca',
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

                <div className="grid gap-4 sm:grid-cols-3">
                    {summaryCards.map((card) => {
                        const Icon = card.icon;

                        return (
                            <Card key={card.label}>
                                <CardHeader className="pb-2">
                                    <CardDescription>
                                        {card.label}
                                    </CardDescription>
                                    <CardTitle className="flex items-center gap-2 text-2xl">
                                        <Icon className="size-5 text-primary" />
                                        {card.value}
                                    </CardTitle>
                                </CardHeader>
                            </Card>
                        );
                    })}
                </div>

                <div className="grid gap-6 xl:grid-cols-[0.8fr_1.2fr]">
                    <Card className="h-fit">
                        <CardHeader>
                            <CardTitle>Permohonan OT Baharu</CardTitle>
                            <CardDescription>
                                Waktu tamat yang lebih awal daripada waktu mula
                                dianggap tamat pada hari berikutnya.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={submit} className="space-y-4">
                                {formErrors.overtime && (
                                    <Alert variant="destructive">
                                        <AlertCircle />
                                        <AlertDescription>
                                            {formErrors.overtime}
                                        </AlertDescription>
                                    </Alert>
                                )}
                                <div className="space-y-2">
                                    <Label>Jenis OT</Label>
                                    <Select
                                        value={data.overtime_type_id}
                                        onValueChange={(value) =>
                                            setData('overtime_type_id', value)
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Pilih jenis OT" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {overtimeTypes.map((type) => (
                                                <SelectItem
                                                    key={type.id}
                                                    value={String(type.id)}
                                                >
                                                    {type.name} ·{' '}
                                                    {type.rate_multiplier}x
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        message={errors.overtime_type_id}
                                    />
                                    {selectedType && (
                                        <p className="text-xs text-muted-foreground">
                                            Minimum{' '}
                                            {selectedType.minimum_minutes} minit
                                            · maksimum{' '}
                                            {selectedType.maximum_hours} jam
                                            {selectedType.requires_attachment &&
                                                ' · lampiran wajib'}
                                        </p>
                                    )}
                                </div>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="space-y-2 sm:col-span-2">
                                        <Label htmlFor="work-date">
                                            Tarikh OT
                                        </Label>
                                        <Input
                                            id="work-date"
                                            type="date"
                                            value={data.work_date}
                                            onChange={(event) =>
                                                setData(
                                                    'work_date',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                        <InputError
                                            message={errors.work_date}
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="start-time">
                                            Waktu Mula
                                        </Label>
                                        <Input
                                            id="start-time"
                                            type="time"
                                            value={data.start_time}
                                            onChange={(event) =>
                                                setData(
                                                    'start_time',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                        <InputError
                                            message={errors.start_time}
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="end-time">
                                            Waktu Tamat
                                        </Label>
                                        <Input
                                            id="end-time"
                                            type="time"
                                            value={data.end_time}
                                            onChange={(event) =>
                                                setData(
                                                    'end_time',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                        <InputError message={errors.end_time} />
                                    </div>
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="break-minutes">
                                        Rehat (minit)
                                    </Label>
                                    <Input
                                        id="break-minutes"
                                        type="number"
                                        min="0"
                                        max="240"
                                        value={data.break_minutes}
                                        onChange={(event) =>
                                            setData(
                                                'break_minutes',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={errors.break_minutes}
                                    />
                                </div>
                                {data.work_date && (
                                    <Alert>
                                        <CalendarClock />
                                        <AlertTitle>Bukti Kehadiran</AlertTitle>
                                        <AlertDescription>
                                            {selectedAttendance
                                                ? `Rekod dijumpai: ${formatDateTime(selectedAttendance.clock_in_at)} hingga ${formatDateTime(selectedAttendance.clock_out_at)}.`
                                                : 'Belum ada rekod geolocation bagi tarikh ini. HR masih boleh menilai dengan justifikasi dan lampiran.'}
                                        </AlertDescription>
                                    </Alert>
                                )}
                                <div className="space-y-2">
                                    <Label htmlFor="reason">Tujuan OT</Label>
                                    <Input
                                        id="reason"
                                        value={data.reason}
                                        maxLength={1000}
                                        onChange={(event) =>
                                            setData(
                                                'reason',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="Contoh: penutupan akaun bulanan"
                                    />
                                    <InputError message={errors.reason} />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="work-description">
                                        Kerja Dilaksanakan
                                    </Label>
                                    <textarea
                                        id="work-description"
                                        rows={4}
                                        value={data.work_description}
                                        maxLength={2000}
                                        onChange={(event) =>
                                            setData(
                                                'work_description',
                                                event.target.value,
                                            )
                                        }
                                        className="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                    />
                                    <InputError
                                        message={errors.work_description}
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="attachment">
                                        Lampiran PDF/JPG/PNG
                                    </Label>
                                    <Input
                                        id="attachment"
                                        type="file"
                                        accept=".pdf,.jpg,.jpeg,.png"
                                        onChange={(event) =>
                                            setData(
                                                'attachment',
                                                event.target.files?.[0] ?? null,
                                            )
                                        }
                                    />
                                    <InputError message={errors.attachment} />
                                </div>
                                <Button
                                    type="submit"
                                    className="w-full"
                                    disabled={processing}
                                >
                                    <Send />
                                    Hantar Permohonan
                                </Button>
                            </form>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Sejarah Permohonan Baharu</CardTitle>
                            <CardDescription>
                                Jam diluluskan akan menjadi input modul Payroll,
                                bukan bayaran automatik.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {requests.length === 0 ? (
                                <p className="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground">
                                    Belum ada permohonan OT.
                                </p>
                            ) : (
                                requests.map((request) => (
                                    <div
                                        key={request.id}
                                        className="rounded-xl border p-4"
                                    >
                                        <div className="flex flex-wrap items-start justify-between gap-3">
                                            <div>
                                                <p className="font-semibold">
                                                    {request.overtime_type}
                                                </p>
                                                <p className="text-sm text-muted-foreground">
                                                    {formatDate(
                                                        request.work_date,
                                                    )}{' '}
                                                    ·{' '}
                                                    {formatDateTime(
                                                        request.start_at,
                                                    )}{' '}
                                                    hingga{' '}
                                                    {formatDateTime(
                                                        request.end_at,
                                                    )}
                                                </p>
                                            </div>
                                            <div className="flex gap-2">
                                                <Badge
                                                    variant="outline"
                                                    className={
                                                        statusStyle[
                                                            request.status
                                                        ]
                                                    }
                                                >
                                                    {
                                                        statusLabel[
                                                            request.status
                                                        ]
                                                    }
                                                </Badge>
                                                <Badge variant="secondary">
                                                    {
                                                        matchLabel[
                                                            request
                                                                .attendance_match_status
                                                        ]
                                                    }
                                                </Badge>
                                            </div>
                                        </div>
                                        <div className="mt-3 grid gap-2 text-sm sm:grid-cols-3">
                                            <p>
                                                Dipohon:{' '}
                                                <strong>
                                                    {hours(
                                                        request.requested_minutes,
                                                    )}
                                                </strong>
                                            </p>
                                            <p>
                                                Diluluskan:{' '}
                                                <strong>
                                                    {hours(
                                                        request.approved_minutes,
                                                    )}
                                                </strong>
                                            </p>
                                            <p>
                                                Peringkat:{' '}
                                                <strong>
                                                    {
                                                        stageLabel[
                                                            request
                                                                .approval_stage
                                                        ]
                                                    }
                                                </strong>
                                            </p>
                                        </div>
                                        <p className="mt-3 text-sm">
                                            {request.work_description}
                                        </p>
                                        {(request.supervisor_review_notes ||
                                            request.review_notes) && (
                                            <div className="mt-3 rounded-lg bg-muted/40 p-3 text-xs text-muted-foreground">
                                                {request.supervisor_review_notes && (
                                                    <p>
                                                        Penyelia:{' '}
                                                        {
                                                            request.supervisor_review_notes
                                                        }
                                                    </p>
                                                )}
                                                {request.review_notes && (
                                                    <p>
                                                        HR:{' '}
                                                        {request.review_notes}
                                                    </p>
                                                )}
                                            </div>
                                        )}
                                        <div className="mt-3 flex flex-wrap gap-2">
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
                                                        {
                                                            request.attachment_name
                                                        }
                                                    </a>
                                                </Button>
                                            )}
                                            {request.status === 'pending' && (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() => {
                                                        if (
                                                            window.confirm(
                                                                'Batalkan permohonan OT ini?',
                                                            )
                                                        ) {
                                                            router.patch(
                                                                `/ot-saya/${request.id}/batal`,
                                                                {},
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            );
                                                        }
                                                    }}
                                                >
                                                    <XCircle />
                                                    Batal
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                ))
                            )}
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Database className="size-5 text-primary" />
                            Sejarah OT Asal (db_spp)
                        </CardTitle>
                        <CardDescription>
                            Rujukan baca sahaja; rekod ini tidak dicampurkan
                            dengan permohonan baharu.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Tarikh</TableHead>
                                    <TableHead>Jenis</TableHead>
                                    <TableHead>Mula</TableHead>
                                    <TableHead>Tamat</TableHead>
                                    <TableHead>Catatan</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {legacyOvertime.map((record) => (
                                    <TableRow key={record.id}>
                                        <TableCell>
                                            {formatDate(record.work_date)}
                                        </TableCell>
                                        <TableCell>
                                            {record.overtime_type ?? '-'}
                                        </TableCell>
                                        <TableCell>
                                            {record.start_time ?? '-'}
                                        </TableCell>
                                        <TableCell>
                                            {record.end_time ?? '-'}
                                        </TableCell>
                                        <TableCell>
                                            {record.notes ?? '-'}
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {legacyOvertime.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={5}
                                            className="h-24 text-center text-muted-foreground"
                                        >
                                            Tiada rekod OT asal.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

EmployeeOvertime.layout = {
    breadcrumbs: [{ title: 'OT Saya', href: '/ot-saya' }],
};
