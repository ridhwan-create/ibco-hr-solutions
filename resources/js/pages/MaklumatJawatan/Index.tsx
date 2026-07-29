import { Head, Link, router } from '@inertiajs/react';
import {
    BriefcaseBusiness,
    Building2,
    CircleOff,
    Eye,
    History,
    Pencil,
    Plus,
    RotateCcw,
    Search,
    UserRoundX,
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

type PositionRecord = {
    id: number;
    id_pekerja: number;
    employee_id: string | null;
    nama_pekerja: string | null;
    jabatan: string | null;
    jawatan: string | null;
    tarikh_berkuat_kuasa: string | null;
    tarikh_tamat_tempoh_cubaan: string | null;
    kelayakan_cuti: string | null;
    aktif: number;
    tarikh_tamat: string | null;
    gaji_asas?: string | number | null;
    bank?: string | null;
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
};

type JawatanIndexProps = {
    records: Paginator<PositionRecord>;
    filters: {
        search: string;
        status: 'active' | 'history';
        department_id: string;
    };
    departmentOptions: SelectOption[];
    statistics: {
        active: number;
        history: number;
        without_position: number;
    };
    canManage: boolean;
    canViewPayroll: boolean;
};

function formatDate(value: string | null): string {
    if (!value) {
        return '-';
    }

    const parts = value.slice(0, 10).split('-');

    return parts.length === 3 ? `${parts[2]}/${parts[1]}/${parts[0]}` : value;
}

function formatCurrency(value: string | number | null | undefined): string {
    if (value === null || value === undefined || value === '') {
        return '-';
    }

    return new Intl.NumberFormat('ms-MY', {
        style: 'currency',
        currency: 'MYR',
    }).format(Number(value));
}

function paginationLabel(label: string): string {
    return label
        .replace('&laquo; Previous', 'Sebelum')
        .replace('Next &raquo;', 'Seterusnya');
}

function TerminateButton({
    position,
    compact = false,
}: {
    position: PositionRecord;
    compact?: boolean;
}) {
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);

    const terminate = () => {
        router.delete(`/jawatan/${position.id}`, {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => {
                setProcessing(false);
                setOpen(false);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    type="button"
                    variant="destructive"
                    size="sm"
                    className={compact ? 'w-full' : undefined}
                >
                    <CircleOff />
                    Tamatkan
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Tamatkan jawatan aktif?</DialogTitle>
                    <DialogDescription>
                        Jawatan {position.jawatan ?? 'ini'} bagi{' '}
                        {position.nama_pekerja ??
                            position.employee_id ??
                            'pekerja ini'}{' '}
                        akan dipindahkan ke sejarah. Rekod tidak akan dipadam
                        secara kekal dan tindakan ini direkodkan dalam Audit
                        Trail.
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
                        variant="destructive"
                        onClick={terminate}
                        disabled={processing}
                    >
                        <CircleOff />
                        {processing
                            ? 'Sedang diproses...'
                            : 'Ya, Tamatkan Jawatan'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function PositionActions({
    position,
    canManage,
    compact = false,
}: {
    position: PositionRecord;
    canManage: boolean;
    compact?: boolean;
}) {
    const active = Number(position.aktif) === 1;

    return (
        <div
            className={
                compact ? 'grid grid-cols-2 gap-2' : 'flex justify-end gap-2'
            }
        >
            <Button
                asChild
                variant="outline"
                size="sm"
                className={compact ? 'col-span-2' : undefined}
            >
                <Link href={`/jawatan/${position.id}`}>
                    <Eye />
                    Papar
                </Link>
            </Button>
            {active && canManage && (
                <>
                    <Button asChild variant="outline" size="sm">
                        <Link href={`/jawatan/${position.id}/edit`}>
                            <Pencil />
                            Tukar
                        </Link>
                    </Button>
                    <TerminateButton position={position} compact={compact} />
                </>
            )}
        </div>
    );
}

export default function JawatanIndex({
    records,
    filters,
    departmentOptions,
    statistics,
    canManage,
    canViewPayroll,
}: JawatanIndexProps) {
    const [search, setSearch] = useState(filters.search);
    const [departmentId, setDepartmentId] = useState(
        filters.department_id || 'all',
    );

    const visit = (
        status: 'active' | 'history',
        nextSearch = search,
        nextDepartment = departmentId,
    ) => {
        router.get(
            '/jawatan',
            {
                status,
                search: nextSearch || undefined,
                department_id:
                    nextDepartment === 'all' ? undefined : nextDepartment,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    const submitFilters = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        visit(filters.status);
    };

    const resetFilters = () => {
        setSearch('');
        setDepartmentId('all');
        visit(filters.status, '', 'all');
    };

    const summaryCards = [
        {
            label: 'Jawatan Aktif',
            value: statistics.active,
            icon: BriefcaseBusiness,
            className: 'text-emerald-600 dark:text-emerald-300',
        },
        {
            label: 'Rekod Sejarah',
            value: statistics.history,
            icon: History,
            className: 'text-blue-600 dark:text-blue-300',
        },
        {
            label: 'Belum Ada Jawatan',
            value: statistics.without_position,
            icon: UserRoundX,
            className: 'text-amber-600 dark:text-amber-300',
        },
    ];

    return (
        <>
            <Head title="Jawatan & Penempatan" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div className="space-y-2">
                        <HeadingSmall
                            title="Jawatan & Penempatan Pekerja"
                            description="Urus penempatan semasa dan semak sejarah pertukaran jawatan tanpa memadam rekod lama."
                        />
                        <Badge
                            variant="secondary"
                            className="gap-1.5 font-normal"
                        >
                            <BriefcaseBusiness className="size-3.5" />
                            Sumber data: db_spp
                        </Badge>
                    </div>

                    {canManage && (
                        <Button asChild>
                            <Link href="/jawatan/create">
                                <Plus />
                                Tambah Penempatan
                            </Link>
                        </Button>
                    )}
                </div>

                <div className="grid gap-4 sm:grid-cols-3">
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
                        variant={
                            filters.status === 'active' ? 'default' : 'ghost'
                        }
                        className="justify-start sm:flex-1 sm:justify-center"
                        onClick={() => visit('active')}
                    >
                        <BriefcaseBusiness />
                        Jawatan Aktif ({statistics.active})
                    </Button>
                    <Button
                        type="button"
                        variant={
                            filters.status === 'history' ? 'default' : 'ghost'
                        }
                        className="justify-start sm:flex-1 sm:justify-center"
                        onClick={() => visit('history')}
                    >
                        <History />
                        Sejarah Jawatan ({statistics.history})
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <Search className="size-4" />
                            Carian & Penapis
                        </CardTitle>
                        <CardDescription>
                            Cari nama, ID pekerja, jawatan atau jabatan.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form
                            onSubmit={submitFilters}
                            className="grid gap-3 md:grid-cols-[minmax(0,1fr)_minmax(220px,0.35fr)_auto]"
                        >
                            <div className="relative">
                                <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    placeholder="Nama, ID, jawatan atau jabatan..."
                                    className="pl-9"
                                    aria-label="Cari rekod jawatan"
                                />
                            </div>
                            <Select
                                value={departmentId}
                                onValueChange={setDepartmentId}
                            >
                                <SelectTrigger className="w-full">
                                    <Building2 />
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        Semua Jabatan
                                    </SelectItem>
                                    {departmentOptions.map((option) => (
                                        <SelectItem
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <div className="flex gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={resetFilters}
                                >
                                    <RotateCcw />
                                    Reset
                                </Button>
                                <Button type="submit">
                                    <Search />
                                    Cari
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
                                    <TableHead className="w-16">No.</TableHead>
                                    <TableHead>Pekerja</TableHead>
                                    <TableHead>Jawatan / Jabatan</TableHead>
                                    <TableHead>Berkuat Kuasa</TableHead>
                                    {filters.status === 'history' && (
                                        <TableHead>Tarikh Tamat</TableHead>
                                    )}
                                    {canViewPayroll && (
                                        <TableHead className="text-right">
                                            Gaji Asas
                                        </TableHead>
                                    )}
                                    <TableHead>Status</TableHead>
                                    <TableHead className="min-w-64 text-right">
                                        Tindakan
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {records.data.length > 0 ? (
                                    records.data.map((position, index) => (
                                        <TableRow key={position.id}>
                                            <TableCell className="text-muted-foreground">
                                                {(records.from ?? 1) + index}
                                            </TableCell>
                                            <TableCell>
                                                <p className="font-medium">
                                                    {position.nama_pekerja ??
                                                        '-'}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {position.employee_id ??
                                                        '-'}
                                                </p>
                                            </TableCell>
                                            <TableCell>
                                                <p className="font-medium">
                                                    {position.jawatan ?? '-'}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {position.jabatan ?? '-'}
                                                </p>
                                            </TableCell>
                                            <TableCell>
                                                {formatDate(
                                                    position.tarikh_berkuat_kuasa,
                                                )}
                                            </TableCell>
                                            {filters.status === 'history' && (
                                                <TableCell>
                                                    {formatDate(
                                                        position.tarikh_tamat,
                                                    )}
                                                </TableCell>
                                            )}
                                            {canViewPayroll && (
                                                <TableCell className="text-right font-medium tabular-nums">
                                                    {formatCurrency(
                                                        position.gaji_asas,
                                                    )}
                                                </TableCell>
                                            )}
                                            <TableCell>
                                                <Badge
                                                    variant={
                                                        Number(
                                                            position.aktif,
                                                        ) === 1
                                                            ? 'secondary'
                                                            : 'outline'
                                                    }
                                                >
                                                    {Number(position.aktif) ===
                                                    1
                                                        ? 'Aktif'
                                                        : 'Sejarah'}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <PositionActions
                                                    position={position}
                                                    canManage={canManage}
                                                />
                                            </TableCell>
                                        </TableRow>
                                    ))
                                ) : (
                                    <TableRow>
                                        <TableCell
                                            colSpan={
                                                6 +
                                                (filters.status === 'history'
                                                    ? 1
                                                    : 0) +
                                                (canViewPayroll ? 1 : 0)
                                            }
                                            className="py-12 text-center text-muted-foreground"
                                        >
                                            Tiada rekod jawatan ditemui.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </div>
                </div>

                <div className="grid gap-4 md:hidden">
                    {records.data.length > 0 ? (
                        records.data.map((position) => (
                            <Card key={position.id}>
                                <CardHeader className="gap-3 pb-3">
                                    <div className="flex items-start justify-between gap-3">
                                        <div>
                                            <CardTitle className="text-base">
                                                {position.nama_pekerja ?? '-'}
                                            </CardTitle>
                                            <CardDescription>
                                                {position.employee_id ?? '-'}
                                            </CardDescription>
                                        </div>
                                        <Badge
                                            variant={
                                                Number(position.aktif) === 1
                                                    ? 'secondary'
                                                    : 'outline'
                                            }
                                        >
                                            {Number(position.aktif) === 1
                                                ? 'Aktif'
                                                : 'Sejarah'}
                                        </Badge>
                                    </div>
                                </CardHeader>
                                <CardContent className="space-y-4 text-sm">
                                    <div>
                                        <p className="text-muted-foreground">
                                            Jawatan / Jabatan
                                        </p>
                                        <p className="font-medium">
                                            {position.jawatan ?? '-'}
                                        </p>
                                        <p>{position.jabatan ?? '-'}</p>
                                    </div>
                                    <div className="grid grid-cols-2 gap-3">
                                        <div>
                                            <p className="text-muted-foreground">
                                                Berkuat Kuasa
                                            </p>
                                            <p className="font-medium">
                                                {formatDate(
                                                    position.tarikh_berkuat_kuasa,
                                                )}
                                            </p>
                                        </div>
                                        {filters.status === 'history' && (
                                            <div>
                                                <p className="text-muted-foreground">
                                                    Tarikh Tamat
                                                </p>
                                                <p className="font-medium">
                                                    {formatDate(
                                                        position.tarikh_tamat,
                                                    )}
                                                </p>
                                            </div>
                                        )}
                                    </div>
                                    {canViewPayroll && (
                                        <div>
                                            <p className="text-muted-foreground">
                                                Gaji Asas
                                            </p>
                                            <p className="font-medium">
                                                {formatCurrency(
                                                    position.gaji_asas,
                                                )}
                                            </p>
                                        </div>
                                    )}
                                    <PositionActions
                                        position={position}
                                        canManage={canManage}
                                        compact
                                    />
                                </CardContent>
                            </Card>
                        ))
                    ) : (
                        <Card>
                            <CardContent className="py-12 text-center text-muted-foreground">
                                Tiada rekod jawatan ditemui.
                            </CardContent>
                        </Card>
                    )}
                </div>

                <div className="flex flex-col gap-3 rounded-xl border bg-card px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <p className="text-sm text-muted-foreground">
                        Menunjukkan {records.from ?? 0} hingga {records.to ?? 0}{' '}
                        daripada {records.total} rekod
                    </p>
                    {records.last_page > 1 && (
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
                                    onClick={() => {
                                        if (link.url) {
                                            router.visit(link.url, {
                                                preserveState: true,
                                                preserveScroll: true,
                                            });
                                        }
                                    }}
                                >
                                    {paginationLabel(link.label)}
                                </Button>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}

JawatanIndex.layout = {
    breadcrumbs: [{ title: 'Jawatan', href: '/jawatan' }],
};
