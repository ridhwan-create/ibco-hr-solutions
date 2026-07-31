import { Head, router, useForm } from '@inertiajs/react';
import {
    BellRing,
    CalendarClock,
    CheckCheck,
    Clock3,
    Repeat2,
    TimerOff,
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

type Entry = {
    id: number;
    work_date: string;
    day_type: 'workday' | 'rest_day' | 'public_holiday' | 'off';
    shift_name: string | null;
    shift_code: string | null;
    scheduled_start_at: string | null;
    scheduled_end_at: string | null;
    break_minutes: number;
    notes: string | null;
    employee_name?: string | null;
};

type SwapRequest = {
    id: number;
    status: 'pending' | 'approved' | 'rejected' | 'cancelled';
    reason: string;
    review_notes: string | null;
    reviewer: string | null;
    is_requester: boolean;
    requester_name: string | null;
    target_name: string | null;
    requester_entry: Entry | null;
    target_entry: Entry | null;
    created_at: string;
};

type Props = {
    month: string;
    employee: {
        id: number;
        employee_id: string | null;
        name: string | null;
        office_name: string | null;
    } | null;
    period: {
        id: number;
        status: 'published' | 'locked';
        published_at: string | null;
        locked_at: string | null;
    } | null;
    entries: Entry[];
    swapOptions: Entry[];
    swapRequests: SwapRequest[];
    notifications: {
        id: number;
        title: string;
        message: string;
        read_at: string | null;
        created_at: string;
    }[];
    summary: {
        workdays: number;
        rest_days: number;
        public_holidays: number;
        pending_swaps: number;
        unread_notifications: number;
    };
};

const dayLabels: Record<Entry['day_type'], string> = {
    workday: 'Hari Bekerja',
    rest_day: 'Hari Rehat',
    public_holiday: 'Cuti Umum',
    off: 'Tidak Bertugas',
};

function formatDate(value: string): string {
    return new Intl.DateTimeFormat('ms-MY', {
        weekday: 'short',
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

function SwapDialog({
    entries,
    options,
}: {
    entries: Entry[];
    options: Entry[];
}) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({
        requester_roster_entry_id: '',
        target_roster_entry_id: '',
        reason: '',
    });
    const eligibleEntries = entries.filter(
        (entry) =>
            entry.day_type === 'workday' &&
            new Date(`${entry.work_date}T23:59:59`) >= new Date(),
    );
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        post('/jadual-saya/pertukaran', {
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
                <Button disabled={eligibleEntries.length === 0}>
                    <Repeat2 />
                    Mohon Pertukaran
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Permohonan Pertukaran Syif</DialogTitle>
                    <DialogDescription>
                        Pilih jadual sendiri dan jadual rakan dalam bulan yang
                        sama. Permohonan perlu diluluskan penyelia atau HR.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-2">
                        <Label>Jadual Saya</Label>
                        <Select
                            value={data.requester_roster_entry_id}
                            onValueChange={(value) =>
                                setData('requester_roster_entry_id', value)
                            }
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Pilih tarikh" />
                            </SelectTrigger>
                            <SelectContent>
                                {eligibleEntries.map((entry) => (
                                    <SelectItem
                                        key={entry.id}
                                        value={String(entry.id)}
                                    >
                                        {formatDate(entry.work_date)} ·{' '}
                                        {entry.shift_name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError
                            message={errors.requester_roster_entry_id}
                        />
                    </div>
                    <div className="space-y-2">
                        <Label>Jadual Rakan</Label>
                        <Select
                            value={data.target_roster_entry_id}
                            onValueChange={(value) =>
                                setData('target_roster_entry_id', value)
                            }
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Pilih pekerja dan tarikh" />
                            </SelectTrigger>
                            <SelectContent>
                                {options.map((entry) => (
                                    <SelectItem
                                        key={entry.id}
                                        value={String(entry.id)}
                                    >
                                        {entry.employee_name} ·{' '}
                                        {formatDate(entry.work_date)} ·{' '}
                                        {entry.shift_name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.target_roster_entry_id} />
                    </div>
                    <div className="space-y-2">
                        <Label>Sebab Pertukaran</Label>
                        <textarea
                            rows={4}
                            value={data.reason}
                            onChange={(event) =>
                                setData('reason', event.target.value)
                            }
                            className="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                        />
                        <InputError message={errors.reason} />
                    </div>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Tutup
                            </Button>
                        </DialogClose>
                        <Button type="submit" disabled={processing}>
                            Hantar Permohonan
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function EmployeeRoster({
    month,
    employee,
    period,
    entries,
    swapOptions,
    swapRequests,
    notifications,
    summary,
}: Props) {
    const [selectedMonth, setSelectedMonth] = useState(month);
    const statisticCards: {
        label: string;
        value: number;
        icon: LucideIcon;
    }[] = [
        {
            label: 'Hari Bekerja',
            value: summary.workdays,
            icon: CalendarClock,
        },
        { label: 'Hari Rehat', value: summary.rest_days, icon: TimerOff },
        {
            label: 'Cuti Umum',
            value: summary.public_holidays,
            icon: CalendarClock,
        },
        {
            label: 'Pertukaran Menunggu',
            value: summary.pending_swaps,
            icon: Repeat2,
        },
        {
            label: 'Notifikasi Baharu',
            value: summary.unread_notifications,
            icon: BellRing,
        },
    ];

    const changeMonth = (value: string) => {
        setSelectedMonth(value);
        router.get(
            '/jadual-saya',
            { month: value },
            { preserveState: true, replace: true },
        );
    };
    const markNotificationsRead = () =>
        router.patch(
            '/jadual-saya/notifikasi/dibaca',
            {},
            { preserveScroll: true },
        );
    const cancelSwap = (swap: SwapRequest) => {
        if (window.confirm('Batalkan permohonan pertukaran syif ini?')) {
            router.patch(`/jadual-saya/pertukaran/${swap.id}/batal`);
        }
    };

    return (
        <>
            <Head title="Jadual Saya" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="flex items-center gap-2 text-2xl font-semibold">
                            <CalendarClock className="size-6 text-sky-600" />
                            Jadual Kerja Saya
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {employee
                                ? `${employee.name} · ${employee.employee_id || '-'} · ${employee.office_name || '-'}`
                                : 'Akaun belum dipautkan kepada rekod pekerja.'}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Input
                            type="month"
                            value={selectedMonth}
                            onChange={(event) =>
                                changeMonth(event.target.value)
                            }
                            className="w-auto"
                        />
                        {period?.status === 'published' && (
                            <SwapDialog
                                entries={entries}
                                options={swapOptions}
                            />
                        )}
                    </div>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    {statisticCards.map((card) => {
                        const Icon = card.icon;

                        return (
                            <Card key={card.label}>
                                <CardHeader className="flex-row items-center justify-between">
                                    <div>
                                        <CardDescription>
                                            {card.label}
                                        </CardDescription>
                                        <CardTitle className="mt-1 text-3xl">
                                            {card.value}
                                        </CardTitle>
                                    </div>
                                    <Icon className="size-6 text-muted-foreground" />
                                </CardHeader>
                            </Card>
                        );
                    })}
                </div>

                {!period && (
                    <Card>
                        <CardContent className="py-12 text-center">
                            <CalendarClock className="mx-auto size-10 text-muted-foreground" />
                            <p className="mt-3 font-medium">
                                Roster bulan ini belum diterbitkan
                            </p>
                            <p className="text-sm text-muted-foreground">
                                Jadual akan dipaparkan selepas HR menerbitkan
                                roster.
                            </p>
                        </CardContent>
                    </Card>
                )}

                {period && (
                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between gap-4">
                                <div>
                                    <CardTitle>Roster Bulanan</CardTitle>
                                    <CardDescription>
                                        Status: {period.status}
                                    </CardDescription>
                                </div>
                                <Badge
                                    variant={
                                        period.status === 'locked'
                                            ? 'default'
                                            : 'secondary'
                                    }
                                >
                                    {period.status === 'locked'
                                        ? 'Dikunci'
                                        : 'Diterbitkan'}
                                </Badge>
                            </div>
                        </CardHeader>
                        <CardContent className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                            {entries.map((entry) => (
                                <div
                                    key={entry.id}
                                    className="rounded-xl border p-4"
                                >
                                    <div className="flex items-start justify-between gap-3">
                                        <div>
                                            <p className="font-medium">
                                                {formatDate(entry.work_date)}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {entry.shift_name ||
                                                    dayLabels[entry.day_type]}
                                            </p>
                                        </div>
                                        <Badge
                                            variant={
                                                entry.day_type === 'workday'
                                                    ? 'secondary'
                                                    : 'outline'
                                            }
                                        >
                                            {dayLabels[entry.day_type]}
                                        </Badge>
                                    </div>
                                    {entry.day_type === 'workday' && (
                                        <div className="mt-3 flex items-center gap-2 rounded-lg bg-muted/50 p-3 text-sm">
                                            <Clock3 className="size-4 text-sky-600" />
                                            <span>
                                                {formatTime(
                                                    entry.scheduled_start_at,
                                                )}{' '}
                                                –{' '}
                                                {formatTime(
                                                    entry.scheduled_end_at,
                                                )}{' '}
                                                · rehat {entry.break_minutes}{' '}
                                                min
                                            </span>
                                        </div>
                                    )}
                                    {entry.notes && (
                                        <p className="mt-2 text-xs text-muted-foreground">
                                            {entry.notes}
                                        </p>
                                    )}
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                )}

                {swapRequests.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Sejarah Pertukaran Syif</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {swapRequests.map((swap) => (
                                <div
                                    key={swap.id}
                                    className="flex flex-wrap items-center justify-between gap-3 rounded-xl border p-4"
                                >
                                    <div>
                                        <p className="font-medium">
                                            {swap.requester_name} ↔{' '}
                                            {swap.target_name}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {swap.requester_entry
                                                ? formatDate(
                                                      swap.requester_entry
                                                          .work_date,
                                                  )
                                                : '-'}{' '}
                                            ↔{' '}
                                            {swap.target_entry
                                                ? formatDate(
                                                      swap.target_entry
                                                          .work_date,
                                                  )
                                                : '-'}
                                        </p>
                                        {swap.review_notes && (
                                            <p className="mt-1 text-xs">
                                                Catatan: {swap.review_notes}
                                            </p>
                                        )}
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Badge
                                            variant={
                                                swap.status === 'approved'
                                                    ? 'default'
                                                    : swap.status === 'rejected'
                                                      ? 'destructive'
                                                      : 'secondary'
                                            }
                                        >
                                            {swap.status}
                                        </Badge>
                                        {swap.is_requester &&
                                            swap.status === 'pending' && (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        cancelSwap(swap)
                                                    }
                                                >
                                                    Batal
                                                </Button>
                                            )}
                                    </div>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                )}

                {notifications.length > 0 && (
                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between gap-3">
                                <CardTitle className="flex items-center gap-2">
                                    <BellRing className="size-5" />
                                    Notifikasi Roster
                                </CardTitle>
                                {summary.unread_notifications > 0 && (
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={markNotificationsRead}
                                    >
                                        <CheckCheck />
                                        Tandakan Dibaca
                                    </Button>
                                )}
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            {notifications.map((notification) => (
                                <div
                                    key={notification.id}
                                    className={`rounded-xl border p-4 ${
                                        notification.read_at
                                            ? ''
                                            : 'border-sky-500/30 bg-sky-500/5'
                                    }`}
                                >
                                    <p className="font-medium">
                                        {notification.title}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {notification.message}
                                    </p>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

EmployeeRoster.layout = {
    breadcrumbs: [
        { title: 'Layan Diri Pekerja', href: '/jadual-saya' },
        { title: 'Jadual Saya', href: '/jadual-saya' },
    ],
};
