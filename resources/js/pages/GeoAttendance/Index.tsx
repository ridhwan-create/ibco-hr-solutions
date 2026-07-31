import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    AlertCircle,
    CalendarCheck2,
    CheckCircle2,
    Clock3,
    Database,
    FilePenLine,
    Filter,
    MapPin,
    Plus,
    Save,
    Search,
    ShieldCheck,
    UserRoundCheck,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import HeadingSmall from '@/components/heading-small';
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

type Employee = {
    id: number;
    employee_id: string | null;
    name: string | null;
};

type Office = {
    id: number;
    name: string;
    address: string | null;
    latitude: string;
    longitude: string;
    radius_meters: number;
    accuracy_limit_meters: number;
    is_active: boolean;
};

type AttendanceRecord = {
    id: number;
    attendance_date: string;
    roster_entry_id: number | null;
    scheduled_start_at: string | null;
    scheduled_end_at: string | null;
    scheduled_minutes: number;
    late_minutes: number;
    early_departure_minutes: number;
    attendance_day_type: string | null;
    clock_in_at: string;
    clock_out_at: string | null;
    clock_in_accuracy_meters: string | null;
    clock_in_distance_meters: string | null;
    clock_in_latitude: string | null;
    clock_in_longitude: string | null;
    clock_in_ip: string | null;
    clock_in_user_agent: string | null;
    clock_out_accuracy_meters: string | null;
    clock_out_distance_meters: string | null;
    clock_out_latitude: string | null;
    clock_out_longitude: string | null;
    clock_out_ip: string | null;
    clock_out_user_agent: string | null;
    source: 'geolocation' | 'manual';
    status: 'active' | 'cancelled';
    notes: string | null;
    office: { id: number; name: string } | null;
    employee: Employee | null;
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

type IndexProps = {
    canManage: boolean;
    records: Paginator<AttendanceRecord>;
    filters: {
        search: string;
        office_id: string;
        status: string;
        date_from: string;
        date_to: string;
    };
    offices: Office[];
    employeeOptions: Employee[];
    statistics: {
        today: number;
        clocked_out: number;
        open: number;
        cancelled: number;
    };
};

function formatDate(value: string): string {
    return new Intl.DateTimeFormat('ms-MY', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(new Date(`${value}T00:00:00`));
}

function formatDateTime(value: string | null): string {
    if (!value) {
        return '-';
    }

    return new Intl.DateTimeFormat('ms-MY', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}

function toDateTimeLocal(value: string | null): string {
    if (!value) {
        return '';
    }

    const date = new Date(value);
    const offset = date.getTimezoneOffset() * 60000;

    return new Date(date.getTime() - offset).toISOString().slice(0, 16);
}

function todayInputDate(): string {
    const date = new Date();
    const offset = date.getTimezoneOffset() * 60000;

    return new Date(date.getTime() - offset).toISOString().slice(0, 10);
}

function paginationLabel(label: string): string {
    return label
        .replace('&laquo; Previous', 'Sebelum')
        .replace('Next &raquo;', 'Seterusnya');
}

function ManualAttendanceDialog({
    employees,
    offices,
}: {
    employees: Employee[];
    offices: Office[];
}) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({
        employee_id: '',
        office_location_id: '',
        attendance_date: todayInputDate(),
        clock_in_at: '',
        clock_out_at: '',
        reason: '',
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        post('/kehadiran/manual', {
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
                <Button>
                    <Plus />
                    Rekod Manual
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Tambah Kehadiran Manual</DialogTitle>
                    <DialogDescription>
                        Rekod ini disimpan dalam ibco-hr-solutions dan tidak
                        mengubah jadual kehadiran asal db_spp.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-5">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-2 sm:col-span-2">
                            <Label>Pekerja</Label>
                            <Select
                                value={data.employee_id}
                                onValueChange={(value) =>
                                    setData('employee_id', value)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Pilih pekerja" />
                                </SelectTrigger>
                                <SelectContent>
                                    {employees.map((employee) => (
                                        <SelectItem
                                            key={employee.id}
                                            value={String(employee.id)}
                                        >
                                            {employee.employee_id ||
                                                `#${employee.id}`}{' '}
                                            — {employee.name || 'Tanpa nama'}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.employee_id} />
                        </div>
                        <div className="space-y-2">
                            <Label>Lokasi Pejabat</Label>
                            <Select
                                value={data.office_location_id}
                                onValueChange={(value) =>
                                    setData('office_location_id', value)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Pilih lokasi" />
                                </SelectTrigger>
                                <SelectContent>
                                    {offices
                                        .filter((office) => office.is_active)
                                        .map((office) => (
                                            <SelectItem
                                                key={office.id}
                                                value={String(office.id)}
                                            >
                                                {office.name}
                                            </SelectItem>
                                        ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.office_location_id} />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="manual-date">
                                Tarikh Kehadiran
                            </Label>
                            <Input
                                id="manual-date"
                                type="date"
                                value={data.attendance_date}
                                onChange={(event) =>
                                    setData(
                                        'attendance_date',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError message={errors.attendance_date} />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="manual-clock-in">Waktu Masuk</Label>
                            <Input
                                id="manual-clock-in"
                                type="datetime-local"
                                value={data.clock_in_at}
                                onChange={(event) =>
                                    setData('clock_in_at', event.target.value)
                                }
                            />
                            <InputError message={errors.clock_in_at} />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="manual-clock-out">
                                Waktu Keluar
                            </Label>
                            <Input
                                id="manual-clock-out"
                                type="datetime-local"
                                value={data.clock_out_at}
                                onChange={(event) =>
                                    setData('clock_out_at', event.target.value)
                                }
                            />
                            <InputError message={errors.clock_out_at} />
                        </div>
                        <div className="space-y-2 sm:col-span-2">
                            <Label htmlFor="manual-reason">Alasan Wajib</Label>
                            <textarea
                                id="manual-reason"
                                rows={3}
                                value={data.reason}
                                onChange={(event) =>
                                    setData('reason', event.target.value)
                                }
                                className="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                placeholder="Nyatakan sebab rekod manual dibuat..."
                            />
                            <InputError message={errors.reason} />
                        </div>
                    </div>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Batal
                            </Button>
                        </DialogClose>
                        <Button type="submit" disabled={processing}>
                            <Save />
                            {processing ? 'Menyimpan...' : 'Simpan Rekod'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function AdjustmentDialog({ record }: { record: AttendanceRecord }) {
    const [open, setOpen] = useState(false);
    const { data, setData, patch, processing, errors } = useForm({
        clock_in_at: toDateTimeLocal(record.clock_in_at),
        clock_out_at: toDateTimeLocal(record.clock_out_at),
        cancelled: record.status === 'cancelled',
        reason: '',
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        patch(`/kehadiran/${record.id}/pembetulan`, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline" size="sm">
                    <FilePenLine />
                    Betulkan
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Pembetulan Kehadiran</DialogTitle>
                    <DialogDescription>
                        Nilai sebelum dan selepas serta alasan akan disimpan
                        dalam Audit Trail.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-2">
                        <Label htmlFor={`adjust-in-${record.id}`}>
                            Waktu Masuk
                        </Label>
                        <Input
                            id={`adjust-in-${record.id}`}
                            type="datetime-local"
                            value={data.clock_in_at}
                            onChange={(event) =>
                                setData('clock_in_at', event.target.value)
                            }
                        />
                        <InputError message={errors.clock_in_at} />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor={`adjust-out-${record.id}`}>
                            Waktu Keluar
                        </Label>
                        <Input
                            id={`adjust-out-${record.id}`}
                            type="datetime-local"
                            value={data.clock_out_at}
                            onChange={(event) =>
                                setData('clock_out_at', event.target.value)
                            }
                        />
                        <InputError message={errors.clock_out_at} />
                    </div>
                    <div className="space-y-2">
                        <Label>Status Rekod</Label>
                        <Select
                            value={data.cancelled ? 'cancelled' : 'active'}
                            onValueChange={(value) =>
                                setData('cancelled', value === 'cancelled')
                            }
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="active">Aktif</SelectItem>
                                <SelectItem value="cancelled">
                                    Dibatalkan
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError message={errors.cancelled} />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor={`adjust-reason-${record.id}`}>
                            Alasan Wajib
                        </Label>
                        <textarea
                            id={`adjust-reason-${record.id}`}
                            rows={4}
                            value={data.reason}
                            onChange={(event) =>
                                setData('reason', event.target.value)
                            }
                            className="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            placeholder="Terangkan sebab pembetulan..."
                        />
                        <InputError message={errors.reason} />
                    </div>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Batal
                            </Button>
                        </DialogClose>
                        <Button type="submit" disabled={processing}>
                            <Save />
                            {processing ? 'Menyimpan...' : 'Simpan Pembetulan'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function RecordDetails({ record }: { record: AttendanceRecord }) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="ghost" size="sm">
                    Butiran
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Butiran Verifikasi Lokasi</DialogTitle>
                    <DialogDescription>
                        Data diambil hanya semasa Rakam Masuk atau Rakam Keluar
                        ditekan.
                    </DialogDescription>
                </DialogHeader>
                <div className="grid gap-4 text-sm sm:grid-cols-2">
                    <div className="space-y-3 rounded-xl border p-4">
                        <p className="font-semibold">Rakaman Masuk</p>
                        <p>
                            Koordinat:{' '}
                            {record.clock_in_latitude &&
                            record.clock_in_longitude
                                ? `${record.clock_in_latitude}, ${record.clock_in_longitude}`
                                : '-'}
                        </p>
                        <p>
                            Jarak:{' '}
                            {record.clock_in_distance_meters
                                ? `${Math.round(Number(record.clock_in_distance_meters))} m`
                                : '-'}
                        </p>
                        <p>
                            Ketepatan:{' '}
                            {record.clock_in_accuracy_meters
                                ? `±${Math.round(Number(record.clock_in_accuracy_meters))} m`
                                : '-'}
                        </p>
                        <p>IP: {record.clock_in_ip || '-'}</p>
                    </div>
                    <div className="space-y-3 rounded-xl border p-4">
                        <p className="font-semibold">Rakaman Keluar</p>
                        <p>
                            Koordinat:{' '}
                            {record.clock_out_latitude &&
                            record.clock_out_longitude
                                ? `${record.clock_out_latitude}, ${record.clock_out_longitude}`
                                : '-'}
                        </p>
                        <p>
                            Jarak:{' '}
                            {record.clock_out_distance_meters
                                ? `${Math.round(Number(record.clock_out_distance_meters))} m`
                                : '-'}
                        </p>
                        <p>
                            Ketepatan:{' '}
                            {record.clock_out_accuracy_meters
                                ? `±${Math.round(Number(record.clock_out_accuracy_meters))} m`
                                : '-'}
                        </p>
                        <p>IP: {record.clock_out_ip || '-'}</p>
                    </div>
                    <div className="space-y-1 sm:col-span-2">
                        <p className="font-semibold">Peranti / Browser Masuk</p>
                        <p className="break-all text-muted-foreground">
                            {record.clock_in_user_agent || '-'}
                        </p>
                    </div>
                    {record.notes && (
                        <div className="space-y-1 sm:col-span-2">
                            <p className="font-semibold">Catatan / Alasan</p>
                            <p className="text-muted-foreground">
                                {record.notes}
                            </p>
                        </div>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}

export default function GeoAttendanceIndex({
    canManage,
    records,
    filters,
    offices,
    employeeOptions,
    statistics,
}: IndexProps) {
    const [search, setSearch] = useState(filters.search);
    const [officeId, setOfficeId] = useState(filters.office_id);
    const [status, setStatus] = useState(filters.status);
    const [dateFrom, setDateFrom] = useState(filters.date_from);
    const [dateTo, setDateTo] = useState(filters.date_to);

    const applyFilters = (event?: FormEvent<HTMLFormElement>) => {
        event?.preventDefault();
        router.get(
            '/kehadiran',
            {
                search,
                office_id: officeId,
                status,
                date_from: dateFrom,
                date_to: dateTo,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    const goToPage = (url: string | null) => {
        if (!url) {
            return;
        }

        router.get(url, {}, { preserveState: true, preserveScroll: true });
    };

    const statisticCards = [
        {
            label: 'Hadir Hari Ini',
            value: statistics.today,
            icon: UserRoundCheck,
            className: 'text-emerald-600',
        },
        {
            label: 'Sudah Keluar',
            value: statistics.clocked_out,
            icon: CheckCircle2,
            className: 'text-blue-600',
        },
        {
            label: 'Belum Keluar',
            value: statistics.open,
            icon: Clock3,
            className: 'text-amber-600',
        },
        {
            label: 'Dibatalkan',
            value: statistics.cancelled,
            icon: AlertCircle,
            className: 'text-red-600',
        },
    ];

    return (
        <>
            <Head title="Kehadiran Geolocation" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div className="space-y-2">
                        <HeadingSmall
                            title="Kehadiran Geolocation"
                            description="Pantau rekod masuk/keluar yang disahkan dalam radius lokasi pejabat."
                        />
                        <div className="flex flex-wrap gap-2">
                            <Badge variant="secondary" className="gap-1.5">
                                <Database className="size-3.5" />
                                Rekod: ibco-hr-solutions
                            </Badge>
                            <Badge variant="outline" className="gap-1.5">
                                <ShieldCheck className="size-3.5" />
                                db_spp baca sahaja
                            </Badge>
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button asChild variant="outline">
                            <Link href="/kehadiran-asal">
                                <Database />
                                Kehadiran Asal (db_spp)
                            </Link>
                        </Button>
                        {canManage && (
                            <ManualAttendanceDialog
                                employees={employeeOptions}
                                offices={offices}
                            />
                        )}
                    </div>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {statisticCards.map((card) => {
                        const Icon = card.icon;

                        return (
                            <Card key={card.label}>
                                <CardHeader className="flex-row items-center justify-between">
                                    <div>
                                        <CardDescription>
                                            {card.label}
                                        </CardDescription>
                                        <CardTitle className="mt-1 text-3xl tabular-nums">
                                            {card.value}
                                        </CardTitle>
                                    </div>
                                    <Icon
                                        className={`size-6 ${card.className}`}
                                    />
                                </CardHeader>
                            </Card>
                        );
                    })}
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Filter className="size-5 text-muted-foreground" />
                            Carian & Penapis
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form
                            onSubmit={applyFilters}
                            className="grid gap-3 md:grid-cols-2 xl:grid-cols-5"
                        >
                            <div className="relative xl:col-span-2">
                                <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    className="pl-9"
                                    placeholder="Cari nama, ID atau NRIC..."
                                />
                            </div>
                            <Select
                                value={officeId || 'all'}
                                onValueChange={(value) =>
                                    setOfficeId(value === 'all' ? '' : value)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Semua lokasi" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        Semua lokasi
                                    </SelectItem>
                                    {offices.map((office) => (
                                        <SelectItem
                                            key={office.id}
                                            value={String(office.id)}
                                        >
                                            {office.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Select
                                value={status || 'all'}
                                onValueChange={(value) =>
                                    setStatus(value === 'all' ? '' : value)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Semua status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        Semua status
                                    </SelectItem>
                                    <SelectItem value="open">
                                        Belum Keluar
                                    </SelectItem>
                                    <SelectItem value="completed">
                                        Selesai
                                    </SelectItem>
                                    <SelectItem value="cancelled">
                                        Dibatalkan
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <Button type="submit">
                                <Search />
                                Cari
                            </Button>
                            <Input
                                type="date"
                                value={dateFrom}
                                onChange={(event) =>
                                    setDateFrom(event.target.value)
                                }
                                aria-label="Tarikh mula"
                            />
                            <Input
                                type="date"
                                value={dateTo}
                                onChange={(event) =>
                                    setDateTo(event.target.value)
                                }
                                aria-label="Tarikh tamat"
                            />
                        </form>
                    </CardContent>
                </Card>

                <Card className="gap-0 overflow-hidden">
                    <CardHeader className="border-b">
                        <CardTitle className="flex items-center gap-2">
                            <CalendarCheck2 className="size-5 text-primary" />
                            Senarai Rekod
                        </CardTitle>
                        <CardDescription>
                            {records.total.toLocaleString('ms-MY')} rekod
                            ditemui.
                        </CardDescription>
                    </CardHeader>

                    <div className="hidden overflow-x-auto lg:block">
                        <Table>
                            <TableHeader className="bg-muted/60">
                                <TableRow>
                                    <TableHead>Pekerja</TableHead>
                                    <TableHead>Tarikh / Lokasi</TableHead>
                                    <TableHead>Masuk</TableHead>
                                    <TableHead>Keluar</TableHead>
                                    <TableHead>Jadual / Varians</TableHead>
                                    <TableHead>Verifikasi</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-right">
                                        Tindakan
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {records.data.map((record) => (
                                    <TableRow key={record.id}>
                                        <TableCell>
                                            <p className="font-medium">
                                                {record.employee?.name ||
                                                    'Pekerja tidak ditemui'}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {record.employee?.employee_id ||
                                                    `#${record.employee?.id ?? record.id}`}
                                            </p>
                                        </TableCell>
                                        <TableCell>
                                            <p>
                                                {formatDate(
                                                    record.attendance_date,
                                                )}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {record.office?.name || '-'}
                                            </p>
                                        </TableCell>
                                        <TableCell>
                                            <p>
                                                {formatDateTime(
                                                    record.clock_in_at,
                                                )}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {record.clock_in_distance_meters
                                                    ? `${Math.round(Number(record.clock_in_distance_meters))} m`
                                                    : 'Manual'}
                                            </p>
                                        </TableCell>
                                        <TableCell>
                                            <p>
                                                {formatDateTime(
                                                    record.clock_out_at,
                                                )}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {record.clock_out_distance_meters
                                                    ? `${Math.round(Number(record.clock_out_distance_meters))} m`
                                                    : '-'}
                                            </p>
                                        </TableCell>
                                        <TableCell>
                                            <p className="text-sm">
                                                {record.scheduled_start_at
                                                    ? `${formatDateTime(record.scheduled_start_at)} – ${formatDateTime(record.scheduled_end_at)}`
                                                    : record.attendance_day_type ||
                                                      'Tiada roster'}
                                            </p>
                                            <div className="mt-1 flex flex-wrap gap-1">
                                                {record.late_minutes > 0 && (
                                                    <Badge
                                                        variant="outline"
                                                        className="border-amber-500/30 bg-amber-500/10 text-amber-700"
                                                    >
                                                        Lewat{' '}
                                                        {record.late_minutes}{' '}
                                                        min
                                                    </Badge>
                                                )}
                                                {record.early_departure_minutes >
                                                    0 && (
                                                    <Badge
                                                        variant="outline"
                                                        className="border-orange-500/30 bg-orange-500/10 text-orange-700"
                                                    >
                                                        Awal{' '}
                                                        {
                                                            record.early_departure_minutes
                                                        }{' '}
                                                        min
                                                    </Badge>
                                                )}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant="outline">
                                                {record.source === 'geolocation'
                                                    ? 'GPS'
                                                    : 'Manual HR'}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant={
                                                    record.status ===
                                                    'cancelled'
                                                        ? 'destructive'
                                                        : 'secondary'
                                                }
                                            >
                                                {record.status === 'cancelled'
                                                    ? 'Dibatalkan'
                                                    : record.clock_out_at
                                                      ? 'Selesai'
                                                      : 'Belum Keluar'}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex justify-end gap-1">
                                                <RecordDetails
                                                    record={record}
                                                />
                                                {canManage && (
                                                    <AdjustmentDialog
                                                        record={record}
                                                    />
                                                )}
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>

                    <div className="grid gap-3 p-4 lg:hidden">
                        {records.data.map((record) => (
                            <div
                                key={record.id}
                                className="space-y-4 rounded-xl border p-4"
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <p className="font-semibold">
                                            {record.employee?.name ||
                                                'Pekerja tidak ditemui'}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {record.employee?.employee_id} ·{' '}
                                            {formatDate(record.attendance_date)}
                                        </p>
                                    </div>
                                    <Badge
                                        variant={
                                            record.status === 'cancelled'
                                                ? 'destructive'
                                                : 'secondary'
                                        }
                                    >
                                        {record.status === 'cancelled'
                                            ? 'Dibatalkan'
                                            : record.clock_out_at
                                              ? 'Selesai'
                                              : 'Belum Keluar'}
                                    </Badge>
                                </div>
                                <div className="grid grid-cols-2 gap-3 text-sm">
                                    <div className="rounded-lg bg-muted/50 p-3">
                                        <p className="text-xs text-muted-foreground">
                                            Masuk
                                        </p>
                                        <p className="mt-1">
                                            {formatDateTime(record.clock_in_at)}
                                        </p>
                                    </div>
                                    <div className="rounded-lg bg-muted/50 p-3">
                                        <p className="text-xs text-muted-foreground">
                                            Keluar
                                        </p>
                                        <p className="mt-1">
                                            {formatDateTime(
                                                record.clock_out_at,
                                            )}
                                        </p>
                                    </div>
                                </div>
                                <div className="rounded-lg border p-3 text-sm">
                                    <p className="text-xs text-muted-foreground">
                                        Jadual Roster
                                    </p>
                                    <p className="mt-1">
                                        {record.scheduled_start_at
                                            ? `${formatDateTime(record.scheduled_start_at)} – ${formatDateTime(record.scheduled_end_at)}`
                                            : record.attendance_day_type ||
                                              'Tiada roster'}
                                    </p>
                                    {(record.late_minutes > 0 ||
                                        record.early_departure_minutes > 0) && (
                                        <p className="mt-1 text-xs text-amber-700">
                                            Lewat {record.late_minutes} min ·
                                            pulang awal{' '}
                                            {record.early_departure_minutes} min
                                        </p>
                                    )}
                                </div>
                                <p className="flex items-center gap-2 text-sm">
                                    <MapPin className="size-4 text-primary" />
                                    {record.office?.name || '-'}
                                </p>
                                <div className="flex gap-2">
                                    <RecordDetails record={record} />
                                    {canManage && (
                                        <AdjustmentDialog record={record} />
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>

                    {records.data.length === 0 && (
                        <p className="py-12 text-center text-sm text-muted-foreground">
                            Tiada rekod kehadiran geolocation ditemui.
                        </p>
                    )}

                    {records.last_page > 1 && (
                        <div className="flex flex-wrap items-center justify-between gap-3 border-t p-4">
                            <p className="text-sm text-muted-foreground">
                                Paparan {records.from ?? 0}–{records.to ?? 0}{' '}
                                daripada {records.total}
                            </p>
                            <div className="flex flex-wrap gap-1">
                                {records.links.map((link, index) => (
                                    <Button
                                        key={`${link.label}-${index}`}
                                        type="button"
                                        size="sm"
                                        variant={
                                            link.active ? 'default' : 'outline'
                                        }
                                        disabled={!link.url}
                                        onClick={() => goToPage(link.url)}
                                    >
                                        {paginationLabel(link.label)}
                                    </Button>
                                ))}
                            </div>
                        </div>
                    )}
                </Card>
            </div>
        </>
    );
}

GeoAttendanceIndex.layout = {
    breadcrumbs: [
        { title: 'Masa & Kehadiran', href: '/kehadiran' },
        { title: 'Kehadiran Geolocation', href: '/kehadiran' },
    ],
};
