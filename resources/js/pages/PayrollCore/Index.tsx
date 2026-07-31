import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    Banknote,
    CalendarPlus,
    Database,
    Eye,
    LockKeyhole,
    Settings2,
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

type PayrollStatus = 'draft' | 'hr_reviewed' | 'approved' | 'finalized';

type PayrollRun = {
    id: number;
    period_start: string;
    period_label: string;
    status: PayrollStatus;
    currency: string;
    employee_count: number;
    total_earnings: number;
    total_deductions: number;
    total_net_pay: number;
    generated_at: string | null;
    generated_by: string | null;
    reviewed_by: string | null;
    approved_by: string | null;
    finalized_by: string | null;
};

type Props = {
    runs: {
        data: PayrollRun[];
        from: number | null;
        to: number | null;
        total: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: {
        status: string;
        year: string;
    };
    statistics: {
        total: number;
        draft: number;
        waiting_approval: number;
        finalized: number;
        finalized_net_pay: number;
    };
    salaryProfileCount: number;
    permissions: {
        can_manage: boolean;
        can_approve: boolean;
        can_configure: boolean;
    };
};

const statusLabels: Record<PayrollStatus, string> = {
    draft: 'Draf',
    hr_reviewed: 'Menunggu Pelulus',
    approved: 'Diluluskan',
    finalized: 'Dimuktamadkan',
};

const statusStyles: Record<PayrollStatus, string> = {
    draft: 'border-slate-500/30 bg-slate-500/10 text-slate-700 dark:text-slate-300',
    hr_reviewed:
        'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300',
    approved: 'border-sky-500/30 bg-sky-500/10 text-sky-700 dark:text-sky-300',
    finalized:
        'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
};

function money(value: number, currency = 'MYR'): string {
    return new Intl.NumberFormat('ms-MY', {
        style: 'currency',
        currency,
    }).format(value);
}

function paginationLabel(label: string): string {
    return label
        .replace('&laquo; Previous', 'Sebelum')
        .replace('Next &raquo;', 'Seterusnya');
}

function CreatePayrollDialog({ disabled }: { disabled: boolean }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({
        period: new Date().toISOString().slice(0, 7),
    });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        post('/payroll', {
            onSuccess: () => {
                setOpen(false);
                reset();
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button disabled={disabled}>
                    <CalendarPlus />
                    Jana Payroll
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Jana Payroll Bulanan</DialogTitle>
                    <DialogDescription>
                        Sistem mengambil profil gaji aktif, komponen tetap, OT
                        diluluskan dan cuti tanpa gaji bagi bulan dipilih.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-2">
                        <Label htmlFor="period">Bulan Payroll</Label>
                        <Input
                            id="period"
                            type="month"
                            value={data.period}
                            onChange={(event) =>
                                setData('period', event.target.value)
                            }
                        />
                        <InputError message={errors.period} />
                    </div>
                    <div className="rounded-lg border bg-muted/30 p-3 text-sm text-muted-foreground">
                        Payroll baharu bermula sebagai <strong>Draf</strong>.
                        Nilai boleh dikira semula dan dilaras sebelum semakan
                        HR.
                    </div>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Batal
                            </Button>
                        </DialogClose>
                        <Button type="submit" disabled={processing}>
                            Jana Sekarang
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function PayrollIndex({
    runs,
    filters,
    statistics,
    salaryProfileCount,
    permissions,
}: Props) {
    const [status, setStatus] = useState(filters.status || 'all');
    const [year, setYear] = useState(filters.year);
    const applyFilters = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.get(
            '/payroll',
            {
                status: status === 'all' ? '' : status,
                year,
            },
            { preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Payroll Core" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="flex items-center gap-2 text-2xl font-semibold">
                            <Banknote className="size-6 text-primary" />
                            Payroll Core
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Penjanaan dan kelulusan payroll bulanan berasaskan
                            gaji, komponen tetap, OT dan cuti tanpa gaji.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button asChild variant="outline">
                            <Link href="/payroll-asal">
                                <Database />
                                Payroll Asal
                            </Link>
                        </Button>
                        {permissions.can_configure && (
                            <Button asChild variant="outline">
                                <Link href="/tetapan-payroll">
                                    <Settings2 />
                                    Tetapan
                                </Link>
                            </Button>
                        )}
                        {permissions.can_manage && (
                            <CreatePayrollDialog
                                disabled={salaryProfileCount < 1}
                            />
                        )}
                    </div>
                </div>

                {salaryProfileCount < 1 && permissions.can_configure && (
                    <Card className="border-amber-500/40 bg-amber-500/5">
                        <CardContent className="flex flex-wrap items-center justify-between gap-3 pt-6">
                            <div>
                                <p className="font-medium">
                                    Profil gaji belum dikonfigurasi
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    Tetapkan gaji asas sekurang-kurangnya
                                    seorang pekerja sebelum menjana payroll.
                                </p>
                            </div>
                            <Button asChild size="sm">
                                <Link href="/tetapan-payroll">
                                    Buka Tetapan Payroll
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                )}

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    {[
                        { label: 'Jumlah Payroll', value: statistics.total },
                        { label: 'Draf', value: statistics.draft },
                        {
                            label: 'Menunggu Pelulus',
                            value: statistics.waiting_approval,
                        },
                        {
                            label: 'Dimuktamadkan',
                            value: statistics.finalized,
                        },
                        {
                            label: 'Gaji Bersih Muktamad',
                            value: money(statistics.finalized_net_pay),
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
                    className="flex flex-wrap items-end gap-3 rounded-xl border p-4"
                >
                    <div className="min-w-44 space-y-2">
                        <Label>Status</Label>
                        <Select value={status} onValueChange={setStatus}>
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    Semua Status
                                </SelectItem>
                                {Object.entries(statusLabels).map(
                                    ([value, label]) => (
                                        <SelectItem key={value} value={value}>
                                            {label}
                                        </SelectItem>
                                    ),
                                )}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="w-32 space-y-2">
                        <Label htmlFor="year">Tahun</Label>
                        <Input
                            id="year"
                            type="number"
                            min="2020"
                            max="2100"
                            value={year}
                            onChange={(event) => setYear(event.target.value)}
                        />
                    </div>
                    <Button type="submit" variant="secondary">
                        Tapis
                    </Button>
                </form>

                <Card>
                    <CardHeader>
                        <CardTitle>Senarai Payroll Bulanan</CardTitle>
                        <CardDescription>
                            Rekod yang dimuktamadkan dikunci daripada perubahan.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="overflow-x-auto rounded-lg border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Tempoh</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="text-center">
                                            Pekerja
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Pendapatan
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Potongan
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Gaji Bersih
                                        </TableHead>
                                        <TableHead>Tindakan</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {runs.data.length === 0 ? (
                                        <TableRow>
                                            <TableCell
                                                colSpan={7}
                                                className="h-28 text-center text-muted-foreground"
                                            >
                                                Belum ada payroll bagi tapisan
                                                ini.
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        runs.data.map((run) => (
                                            <TableRow key={run.id}>
                                                <TableCell>
                                                    <p className="font-medium">
                                                        {run.period_label}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        Dijana oleh{' '}
                                                        {run.generated_by ??
                                                            '-'}
                                                    </p>
                                                </TableCell>
                                                <TableCell>
                                                    <Badge
                                                        variant="outline"
                                                        className={
                                                            statusStyles[
                                                                run.status
                                                            ]
                                                        }
                                                    >
                                                        {run.status ===
                                                            'finalized' && (
                                                            <LockKeyhole className="mr-1 size-3" />
                                                        )}
                                                        {
                                                            statusLabels[
                                                                run.status
                                                            ]
                                                        }
                                                    </Badge>
                                                </TableCell>
                                                <TableCell className="text-center">
                                                    {run.employee_count}
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {money(
                                                        run.total_earnings,
                                                        run.currency,
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {money(
                                                        run.total_deductions,
                                                        run.currency,
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right font-semibold tabular-nums">
                                                    {money(
                                                        run.total_net_pay,
                                                        run.currency,
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    <Button
                                                        asChild
                                                        size="sm"
                                                        variant="outline"
                                                    >
                                                        <Link
                                                            href={`/payroll/${run.id}`}
                                                        >
                                                            <Eye />
                                                            Buka
                                                        </Link>
                                                    </Button>
                                                </TableCell>
                                            </TableRow>
                                        ))
                                    )}
                                </TableBody>
                            </Table>
                        </div>
                        {runs.links.length > 3 && (
                            <div className="flex flex-wrap gap-2">
                                {runs.links.map((link, index) => (
                                    <Button
                                        key={`${link.label}-${index}`}
                                        asChild={Boolean(link.url)}
                                        size="sm"
                                        variant={
                                            link.active ? 'default' : 'outline'
                                        }
                                        disabled={!link.url}
                                    >
                                        {link.url ? (
                                            <Link href={link.url}>
                                                {paginationLabel(link.label)}
                                            </Link>
                                        ) : (
                                            <span>
                                                {paginationLabel(link.label)}
                                            </span>
                                        )}
                                    </Button>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
