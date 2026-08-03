import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    CalendarClock,
    CheckCircle2,
    Download,
    FileLock2,
    Pencil,
    RefreshCw,
    Search,
    Send,
    ShieldCheck,
    TimerOff,
    UsersRound,
    XCircle,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
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

type Period = {
    id: number;
    period_start: string;
    period_end: string;
    status: 'draft' | 'published' | 'locked';
    notes: string | null;
    published_at: string | null;
    locked_at: string | null;
};

type RosterEntry = {
    id: number;
    employee: {
        employee_id: number;
        employee_number: string | null;
        name: string;
        department_name: string;
    } | null;
    office: { id: number; name: string } | null;
    shift_template: { id: number; code: string; name: string } | null;
    work_date: string;
    day_type: 'workday' | 'rest_day' | 'public_holiday' | 'off';
    scheduled_start_at: string | null;
    scheduled_end_at: string | null;
    break_minutes: number;
    source: string;
    notes: string | null;
    attendance: {
        clock_in_at: string;
        clock_out_at: string | null;
        late_minutes: number;
        early_departure_minutes: number;
    } | null;
    on_leave: boolean;
    is_absent: boolean;
};

type ShiftTemplate = {
    id: number;
    name: string;
    start_time: string;
    end_time: string;
    break_minutes: number;
    crosses_midnight: boolean;
};

type PendingSwap = {
    id: number;
    requester: string | null;
    target: string | null;
    reason: string;
    requester_entry: { work_date: string; shift_name: string | null };
    target_entry: { work_date: string; shift_name: string | null };
    created_at: string;
};

type Props = {
    period: Period | null;
    entries: {
        data: RosterEntry[];
        from: number | null;
        to: number | null;
        total: number;
        links: { url: string | null; label: string; active: boolean }[];
    } | null;
    filters: {
        search: string;
        month: string;
        department_id: string;
        office_id: string;
        day_type: string;
    };
    statistics: {
        employees: number;
        workdays: number;
        rest_days: number;
        public_holidays: number;
        late_minutes: number;
        early_departure_minutes: number;
        absent: number;
    };
    pendingSwaps: PendingSwap[];
    shiftTemplates: ShiftTemplate[];
    departmentOptions: { id: number; name: string }[];
    officeOptions: { id: number; name: string }[];
    permissions: {
        can_manage: boolean;
        can_publish: boolean;
        can_supervise: boolean;
    };
};

const dayLabels: Record<RosterEntry['day_type'], string> = {
    workday: 'Hari Bekerja',
    rest_day: 'Hari Rehat',
    public_holiday: 'Cuti Umum',
    off: 'Tidak Bertugas',
};

function formatDate(value: string): string {
    return new Intl.DateTimeFormat('ms-MY', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(new Date(`${value}T00:00:00`));
}

function formatTime(value: string | null): string {
    if (!value) {
        return '-';
    }

    return new Intl.DateTimeFormat('ms-MY', {
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}

function localTime(value: string | null): string {
    if (!value) {
        return '';
    }

    return new Date(value).toLocaleTimeString('en-GB', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    });
}

function EntryDialog({
    entry,
    templates,
}: {
    entry: RosterEntry;
    templates: ShiftTemplate[];
}) {
    const [open, setOpen] = useState(false);
    const { data, setData, put, processing, errors } = useForm({
        day_type: entry.day_type,
        shift_template_id: entry.shift_template
            ? String(entry.shift_template.id)
            : '',
        start_time: localTime(entry.scheduled_start_at),
        end_time: localTime(entry.scheduled_end_at),
        break_minutes: String(entry.break_minutes),
        notes: entry.notes ?? '',
    });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        put(`/jadual-roster/rekod/${entry.id}`, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };
    const selectTemplate = (value: string) => {
        setData('shift_template_id', value);
        const template = templates.find(
            (candidate) => candidate.id === Number(value),
        );

        if (template) {
            setData((current) => ({
                ...current,
                shift_template_id: value,
                start_time: template.start_time,
                end_time: template.end_time,
                break_minutes: String(template.break_minutes),
            }));
        }
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    <Pencil />
                    Edit
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Edit Jadual</DialogTitle>
                    <DialogDescription>
                        {entry.employee?.name || 'Pekerja'} ·{' '}
                        {formatDate(entry.work_date)}
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
                    <div className="space-y-2 sm:col-span-2">
                        <Label>Jenis Hari</Label>
                        <Select
                            value={data.day_type}
                            onValueChange={(value: RosterEntry['day_type']) =>
                                setData('day_type', value)
                            }
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {Object.entries(dayLabels).map(
                                    ([value, label]) => (
                                        <SelectItem key={value} value={value}>
                                            {label}
                                        </SelectItem>
                                    ),
                                )}
                            </SelectContent>
                        </Select>
                    </div>
                    {data.day_type === 'workday' && (
                        <>
                            <div className="space-y-2 sm:col-span-2">
                                <Label>Template</Label>
                                <Select
                                    value={data.shift_template_id}
                                    onValueChange={selectTemplate}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Pilih syif" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {templates.map((template) => (
                                            <SelectItem
                                                key={template.id}
                                                value={String(template.id)}
                                            >
                                                {template.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError
                                    message={errors.shift_template_id}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label>Mula</Label>
                                <Input
                                    type="time"
                                    value={data.start_time}
                                    onChange={(event) =>
                                        setData(
                                            'start_time',
                                            event.target.value,
                                        )
                                    }
                                />
                            </div>
                            <div className="space-y-2">
                                <Label>Tamat</Label>
                                <Input
                                    type="time"
                                    value={data.end_time}
                                    onChange={(event) =>
                                        setData('end_time', event.target.value)
                                    }
                                />
                            </div>
                            <div className="space-y-2">
                                <Label>Rehat (minit)</Label>
                                <Input
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
                            </div>
                        </>
                    )}
                    <div className="space-y-2 sm:col-span-2">
                        <Label>Catatan</Label>
                        <textarea
                            rows={3}
                            value={data.notes}
                            onChange={(event) =>
                                setData('notes', event.target.value)
                            }
                            className="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                        />
                    </div>
                    <InputError
                        message={errors.start_time || errors.end_time}
                        className="sm:col-span-2"
                    />
                    <DialogFooter className="sm:col-span-2">
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Tutup
                            </Button>
                        </DialogClose>
                        <Button type="submit" disabled={processing}>
                            Simpan
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function SwapReviewDialog({ swap }: { swap: PendingSwap }) {
    const [open, setOpen] = useState(false);
    const { data, setData, patch, processing, errors } = useForm({
        status: 'approved' as 'approved' | 'rejected',
        review_notes: '',
    });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        patch(`/jadual-roster/pertukaran/${swap.id}/semakan`, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm">
                    <CheckCircle2 />
                    Semak
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Semakan Pertukaran Syif</DialogTitle>
                    <DialogDescription>
                        {swap.requester} ↔ {swap.target}
                    </DialogDescription>
                </DialogHeader>
                <div className="grid gap-3 sm:grid-cols-2">
                    <div className="rounded-lg border p-3 text-sm">
                        <p className="font-medium">{swap.requester}</p>
                        <p className="text-muted-foreground">
                            {formatDate(swap.requester_entry.work_date)} ·{' '}
                            {swap.requester_entry.shift_name}
                        </p>
                    </div>
                    <div className="rounded-lg border p-3 text-sm">
                        <p className="font-medium">{swap.target}</p>
                        <p className="text-muted-foreground">
                            {formatDate(swap.target_entry.work_date)} ·{' '}
                            {swap.target_entry.shift_name}
                        </p>
                    </div>
                </div>
                <form onSubmit={submit} className="space-y-4">
                    <div className="rounded-lg bg-muted/50 p-3 text-sm">
                        {swap.reason}
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
                                    Luluskan
                                </SelectItem>
                                <SelectItem value="rejected">Tolak</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="space-y-2">
                        <Label>
                            Catatan {data.status === 'rejected' && '(Wajib)'}
                        </Label>
                        <textarea
                            rows={3}
                            value={data.review_notes}
                            onChange={(event) =>
                                setData('review_notes', event.target.value)
                            }
                            className="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm"
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

export default function RosterIndex({
    period,
    entries,
    filters,
    statistics,
    pendingSwaps,
    shiftTemplates,
    departmentOptions,
    officeOptions,
    permissions,
}: Props) {
    const [search, setSearch] = useState(filters.search);
    const [month, setMonth] = useState(filters.month);
    const [departmentId, setDepartmentId] = useState(
        filters.department_id || 'all',
    );
    const [officeId, setOfficeId] = useState(filters.office_id || 'all');
    const [dayType, setDayType] = useState(filters.day_type || 'all');
    const applyFilters = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.get(
            '/jadual-roster',
            {
                search,
                month,
                department_id: departmentId === 'all' ? '' : departmentId,
                office_id: officeId === 'all' ? '' : officeId,
                day_type: dayType === 'all' ? '' : dayType,
            },
            { preserveState: true, replace: true },
        );
    };
    const generate = () =>
        router.post('/jadual-roster/jana', { month }, { preserveScroll: true });
    const publish = () => {
        if (
            period &&
            window.confirm(
                'Terbitkan roster ini kepada semua Employee? Selepas diterbitkan, jadual hanya boleh berubah melalui pertukaran syif.',
            )
        ) {
            router.patch(`/jadual-roster/${period.id}/terbit`);
        }
    };
    const lock = () => {
        if (
            period &&
            window.confirm(
                'Kunci roster ini? Selepas dikunci, pertukaran syif juga tidak dibenarkan.',
            )
        ) {
            router.patch(`/jadual-roster/${period.id}/kunci`);
        }
    };
    const exportUrl = `/jadual-roster/laporan.csv?${new URLSearchParams({
        search,
        month,
        department_id: departmentId === 'all' ? '' : departmentId,
        office_id: officeId === 'all' ? '' : officeId,
        day_type: dayType === 'all' ? '' : dayType,
    }).toString()}`;
    const rows = entries?.data ?? [];
    const statisticCards: {
        label: string;
        value: number;
        icon: LucideIcon;
    }[] = [
        { label: 'Pekerja', value: statistics.employees, icon: UsersRound },
        {
            label: 'Hari Bekerja',
            value: statistics.workdays,
            icon: CalendarClock,
        },
        { label: 'Hari Rehat', value: statistics.rest_days, icon: TimerOff },
        {
            label: 'Cuti Umum',
            value: statistics.public_holidays,
            icon: CalendarClock,
        },
        {
            label: 'Lewat (min)',
            value: statistics.late_minutes,
            icon: XCircle,
        },
        {
            label: 'Pulang Awal',
            value: statistics.early_departure_minutes,
            icon: XCircle,
        },
        { label: 'Tidak Hadir', value: statistics.absent, icon: XCircle },
    ];

    return (
        <>
            <Head title="Jadual Kerja & Roster" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="flex items-center gap-2 text-2xl font-semibold">
                            <CalendarClock className="size-6 text-sky-600" />
                            Jadual Kerja, Syif & Roster
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Jadual rasmi untuk pengiraan kehadiran, lewat,
                            pulang awal dan pengelasan OT.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {period && (
                            <Button asChild variant="outline">
                                <a href={exportUrl}>
                                    <Download />
                                    CSV
                                </a>
                            </Button>
                        )}
                        {permissions.can_manage &&
                            (!period || period.status === 'draft') && (
                                <Button onClick={generate} variant="outline">
                                    <RefreshCw />
                                    {period ? 'Jana Semula' : 'Jana Roster'}
                                </Button>
                            )}
                        {permissions.can_publish &&
                            period?.status === 'draft' && (
                                <Button onClick={publish}>
                                    <Send />
                                    Terbitkan
                                </Button>
                            )}
                        {permissions.can_publish &&
                            period?.status === 'published' && (
                                <Button onClick={lock}>
                                    <FileLock2 />
                                    Kunci
                                </Button>
                            )}
                    </div>
                </div>

                <Card>
                    <CardContent className="flex flex-wrap items-center gap-3 pt-6">
                        <Badge
                            variant={
                                period?.status === 'locked'
                                    ? 'default'
                                    : 'secondary'
                            }
                            className="gap-1.5"
                        >
                            {period?.status === 'draft' && <Pencil />}
                            {period?.status === 'published' && <Send />}
                            {period?.status === 'locked' && <FileLock2 />}
                            {period
                                ? `Status: ${period.status}`
                                : 'Roster belum dijana'}
                        </Badge>
                        <span className="text-sm text-muted-foreground">
                            Bulan {month}
                        </span>
                        <Badge variant="outline" className="gap-1.5">
                            <ShieldCheck className="size-3.5" />
                            db_spp baca sahaja
                        </Badge>
                    </CardContent>
                </Card>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-7">
                    {statisticCards.map((card) => {
                        const Icon = card.icon;

                        return (
                            <Card key={card.label}>
                                <CardHeader className="gap-2">
                                    <Icon className="size-5 text-muted-foreground" />
                                    <CardDescription>
                                        {card.label}
                                    </CardDescription>
                                    <CardTitle className="text-2xl">
                                        {card.value}
                                    </CardTitle>
                                </CardHeader>
                            </Card>
                        );
                    })}
                </div>

                <form
                    onSubmit={applyFilters}
                    className="grid gap-3 rounded-xl border p-4 lg:grid-cols-[1.2fr_repeat(4,0.8fr)_auto]"
                >
                    <Input
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Cari nama atau ID pekerja"
                    />
                    <Input
                        type="month"
                        value={month}
                        onChange={(event) => setMonth(event.target.value)}
                    />
                    <Select
                        value={departmentId}
                        onValueChange={setDepartmentId}
                    >
                        <SelectTrigger>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Semua jabatan</SelectItem>
                            {departmentOptions.map((department) => (
                                <SelectItem
                                    key={department.id}
                                    value={String(department.id)}
                                >
                                    {department.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Select value={officeId} onValueChange={setOfficeId}>
                        <SelectTrigger>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Semua lokasi</SelectItem>
                            {officeOptions.map((office) => (
                                <SelectItem
                                    key={office.id}
                                    value={String(office.id)}
                                >
                                    {office.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Select value={dayType} onValueChange={setDayType}>
                        <SelectTrigger>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">
                                Semua jenis hari
                            </SelectItem>
                            {Object.entries(dayLabels).map(([value, label]) => (
                                <SelectItem key={value} value={value}>
                                    {label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Button type="submit">
                        <Search />
                        Tapis
                    </Button>
                </form>

                {permissions.can_supervise && pendingSwaps.length > 0 && (
                    <Card className="border-amber-500/30">
                        <CardHeader>
                            <CardTitle>
                                Pertukaran Syif Menunggu ({pendingSwaps.length})
                            </CardTitle>
                            <CardDescription>
                                Permohonan Employee yang memerlukan keputusan
                                penyelia atau HR.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {pendingSwaps.map((swap) => (
                                <div
                                    key={swap.id}
                                    className="flex flex-wrap items-center justify-between gap-3 rounded-xl border p-4"
                                >
                                    <div>
                                        <p className="font-medium">
                                            {swap.requester} ↔ {swap.target}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {formatDate(
                                                swap.requester_entry.work_date,
                                            )}{' '}
                                            ↔{' '}
                                            {formatDate(
                                                swap.target_entry.work_date,
                                            )}
                                        </p>
                                    </div>
                                    <SwapReviewDialog swap={swap} />
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                )}

                <Card className="gap-0 overflow-hidden">
                    <CardHeader className="border-b">
                        <CardTitle>Rekod Roster</CardTitle>
                        <CardDescription>
                            {entries
                                ? `${entries.from ?? 0}–${entries.to ?? 0} daripada ${entries.total} rekod`
                                : 'Jana roster untuk mula menyusun jadual.'}
                        </CardDescription>
                    </CardHeader>
                    <div className="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Pekerja</TableHead>
                                    <TableHead>Tarikh</TableHead>
                                    <TableHead>Syif</TableHead>
                                    <TableHead>Kehadiran</TableHead>
                                    <TableHead>Status Harian</TableHead>
                                    <TableHead className="text-right">
                                        Tindakan
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {rows.map((entry) => (
                                    <TableRow key={entry.id}>
                                        <TableCell>
                                            <p className="font-medium">
                                                {entry.employee?.name ||
                                                    'Pekerja'}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {entry.employee
                                                    ?.employee_number ||
                                                    '-'}{' '}
                                                ·{' '}
                                                {entry.employee
                                                    ?.department_name || '-'}
                                            </p>
                                        </TableCell>
                                        <TableCell>
                                            <p>{formatDate(entry.work_date)}</p>
                                            <p className="text-xs text-muted-foreground">
                                                {entry.office?.name || '-'}
                                            </p>
                                        </TableCell>
                                        <TableCell>
                                            <p className="font-medium">
                                                {entry.shift_template?.name ||
                                                    dayLabels[entry.day_type]}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {formatTime(
                                                    entry.scheduled_start_at,
                                                )}{' '}
                                                –{' '}
                                                {formatTime(
                                                    entry.scheduled_end_at,
                                                )}
                                            </p>
                                        </TableCell>
                                        <TableCell>
                                            {entry.attendance ? (
                                                <>
                                                    <p className="text-sm">
                                                        {formatTime(
                                                            entry.attendance
                                                                .clock_in_at,
                                                        )}{' '}
                                                        –{' '}
                                                        {formatTime(
                                                            entry.attendance
                                                                .clock_out_at,
                                                        )}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        Lewat{' '}
                                                        {
                                                            entry.attendance
                                                                .late_minutes
                                                        }{' '}
                                                        min · awal{' '}
                                                        {
                                                            entry.attendance
                                                                .early_departure_minutes
                                                        }{' '}
                                                        min
                                                    </p>
                                                </>
                                            ) : (
                                                '-'
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant={
                                                    entry.is_absent
                                                        ? 'destructive'
                                                        : 'outline'
                                                }
                                            >
                                                {entry.is_absent
                                                    ? 'Tidak Hadir'
                                                    : entry.on_leave
                                                      ? 'Cuti Diluluskan'
                                                      : dayLabels[
                                                            entry.day_type
                                                        ]}
                                            </Badge>
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                {entry.source}
                                            </p>
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {permissions.can_manage &&
                                                period?.status === 'draft' && (
                                                    <EntryDialog
                                                        entry={entry}
                                                        templates={
                                                            shiftTemplates
                                                        }
                                                    />
                                                )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                    {rows.length === 0 && (
                        <p className="py-12 text-center text-sm text-muted-foreground">
                            Tiada rekod roster bagi penapis ini.
                        </p>
                    )}
                    {entries && entries.links.length > 3 && (
                        <div className="flex flex-wrap gap-2 border-t p-4">
                            {entries.links.map((link) =>
                                link.url ? (
                                    <Button
                                        key={link.label}
                                        asChild
                                        size="sm"
                                        variant={
                                            link.active ? 'default' : 'outline'
                                        }
                                    >
                                        <Link href={link.url}>
                                            {link.label
                                                .replace(
                                                    '&laquo; Previous',
                                                    'Sebelum',
                                                )
                                                .replace(
                                                    'Next &raquo;',
                                                    'Seterusnya',
                                                )}
                                        </Link>
                                    </Button>
                                ) : null,
                            )}
                        </div>
                    )}
                </Card>
            </div>
        </>
    );
}

RosterIndex.layout = {
    breadcrumbs: [
        { title: 'Masa & Kehadiran', href: '/jadual-roster' },
        { title: 'Jadual & Roster', href: '/jadual-roster' },
    ],
};
