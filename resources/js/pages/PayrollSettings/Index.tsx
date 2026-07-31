import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    Banknote,
    CirclePlus,
    Coins,
    Pencil,
    Search,
    Settings2,
    SlidersHorizontal,
    UserRoundCog,
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

type PayrollComponent = {
    id: number;
    code: string;
    name: string;
    type: 'earning' | 'deduction';
    is_active: boolean;
    is_epf_wage: boolean;
    is_socso_wage: boolean;
    is_eis_wage: boolean;
    is_pcb_wage: boolean;
};

type EmployeeComponent = {
    id: number;
    payroll_component_id: number;
    code: string;
    name: string;
    type: 'earning' | 'deduction';
    amount: number;
    effective_from: string;
    effective_to: string | null;
    is_active: boolean;
};

type Employee = {
    id: number;
    employee_number: string | null;
    employee_name: string;
    position: string | null;
    legacy_salary: number | null;
    salary_profile: {
        id: number;
        basic_salary: number;
        effective_from: string;
        is_active: boolean;
        notes: string | null;
    } | null;
    components: EmployeeComponent[];
};

type Props = {
    settings: {
        currency: string;
        working_days_divisor: number;
        daily_hours: number;
        include_approved_overtime: boolean;
        deduct_unpaid_leave: boolean;
    };
    components: PayrollComponent[];
    employees: {
        data: Employee[];
        from: number | null;
        to: number | null;
        total: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: { search: string };
    statistics: {
        active_employees: number;
        configured_profiles: number;
        active_components: number;
    };
};

function money(value: number | null): string {
    if (value === null) {
        return '-';
    }

    return new Intl.NumberFormat('ms-MY', {
        style: 'currency',
        currency: 'MYR',
    }).format(value);
}

function paginationLabel(label: string): string {
    return label
        .replace('&laquo; Previous', 'Sebelum')
        .replace('Next &raquo;', 'Seterusnya');
}

function SettingsForm({ settings }: { settings: Props['settings'] }) {
    const { data, setData, put, processing, errors } = useForm({
        currency: settings.currency,
        working_days_divisor: String(settings.working_days_divisor),
        daily_hours: String(settings.daily_hours),
        include_approved_overtime: settings.include_approved_overtime,
        deduct_unpaid_leave: settings.deduct_unpaid_leave,
    });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        put('/tetapan-payroll/pengiraan', { preserveScroll: true });
    };

    return (
        <form
            onSubmit={submit}
            className="grid gap-4 md:grid-cols-2 xl:grid-cols-5"
        >
            <div className="space-y-2">
                <Label>Mata Wang</Label>
                <Input
                    maxLength={3}
                    value={data.currency}
                    onChange={(event) =>
                        setData('currency', event.target.value.toUpperCase())
                    }
                />
                <InputError message={errors.currency} />
            </div>
            <div className="space-y-2">
                <Label>Pembahagi Hari Bekerja</Label>
                <Input
                    type="number"
                    min="1"
                    max="31"
                    step="0.01"
                    value={data.working_days_divisor}
                    onChange={(event) =>
                        setData('working_days_divisor', event.target.value)
                    }
                />
                <InputError message={errors.working_days_divisor} />
            </div>
            <div className="space-y-2">
                <Label>Jam Bekerja Sehari</Label>
                <Input
                    type="number"
                    min="1"
                    max="24"
                    step="0.25"
                    value={data.daily_hours}
                    onChange={(event) =>
                        setData('daily_hours', event.target.value)
                    }
                />
                <InputError message={errors.daily_hours} />
            </div>
            <label className="flex cursor-pointer items-center gap-3 rounded-lg border p-3 text-sm">
                <input
                    type="checkbox"
                    checked={data.include_approved_overtime}
                    onChange={(event) =>
                        setData(
                            'include_approved_overtime',
                            event.target.checked,
                        )
                    }
                    className="size-4"
                />
                Masukkan OT diluluskan
            </label>
            <label className="flex cursor-pointer items-center gap-3 rounded-lg border p-3 text-sm">
                <input
                    type="checkbox"
                    checked={data.deduct_unpaid_leave}
                    onChange={(event) =>
                        setData('deduct_unpaid_leave', event.target.checked)
                    }
                    className="size-4"
                />
                Potong cuti tanpa gaji
            </label>
            <div className="md:col-span-2 xl:col-span-5">
                <Button type="submit" disabled={processing}>
                    Simpan Tetapan Pengiraan
                </Button>
            </div>
        </form>
    );
}

function ComponentDialog({ component }: { component?: PayrollComponent }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, put, processing, errors, reset } = useForm({
        code: component?.code ?? '',
        name: component?.name ?? '',
        type: component?.type ?? ('earning' as 'earning' | 'deduction'),
        is_active: component?.is_active ?? true,
        is_epf_wage: component?.is_epf_wage ?? true,
        is_socso_wage: component?.is_socso_wage ?? true,
        is_eis_wage: component?.is_eis_wage ?? true,
        is_pcb_wage: component?.is_pcb_wage ?? true,
    });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);

                if (!component) {
                    reset();
                }
            },
        };

        if (component) {
            put(`/tetapan-payroll/komponen/${component.id}`, options);
        } else {
            post('/tetapan-payroll/komponen', options);
        }
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    size={component ? 'icon' : 'sm'}
                    variant={component ? 'ghost' : 'default'}
                    aria-label={component ? 'Edit komponen' : undefined}
                >
                    {component ? <Pencil /> : <CirclePlus />}
                    {!component && 'Tambah Komponen'}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {component
                            ? 'Edit Komponen'
                            : 'Tambah Komponen Payroll'}
                    </DialogTitle>
                    <DialogDescription>
                        Komponen boleh ditetapkan sebagai pendapatan atau
                        potongan tetap pekerja.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-2">
                        <Label>Kod</Label>
                        <Input
                            value={data.code}
                            maxLength={32}
                            onChange={(event) =>
                                setData(
                                    'code',
                                    event.target.value.toUpperCase(),
                                )
                            }
                        />
                        <InputError message={errors.code} />
                    </div>
                    <div className="space-y-2">
                        <Label>Nama Komponen</Label>
                        <Input
                            value={data.name}
                            maxLength={150}
                            onChange={(event) =>
                                setData('name', event.target.value)
                            }
                        />
                        <InputError message={errors.name} />
                    </div>
                    <div className="space-y-2">
                        <Label>Jenis</Label>
                        <Select
                            value={data.type}
                            onValueChange={(value: 'earning' | 'deduction') => {
                                setData('type', value);

                                if (value === 'deduction') {
                                    setData('is_epf_wage', false);
                                    setData('is_socso_wage', false);
                                    setData('is_eis_wage', false);
                                    setData('is_pcb_wage', false);
                                }
                            }}
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="earning">
                                    Pendapatan
                                </SelectItem>
                                <SelectItem value="deduction">
                                    Potongan
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError message={errors.type} />
                    </div>
                    <div className="space-y-2">
                        <Label>Diambil Kira Sebagai Upah</Label>
                        <div className="grid grid-cols-2 gap-2 text-sm">
                            {[
                                ['is_epf_wage', 'KWSP'],
                                ['is_socso_wage', 'PERKESO'],
                                ['is_eis_wage', 'EIS'],
                                ['is_pcb_wage', 'PCB'],
                            ].map(([field, label]) => (
                                <label
                                    key={field}
                                    className="flex items-center gap-2 rounded-md border p-2"
                                >
                                    <input
                                        type="checkbox"
                                        checked={
                                            data[
                                                field as
                                                    | 'is_epf_wage'
                                                    | 'is_socso_wage'
                                                    | 'is_eis_wage'
                                                    | 'is_pcb_wage'
                                            ]
                                        }
                                        onChange={(event) =>
                                            setData(
                                                field as
                                                    | 'is_epf_wage'
                                                    | 'is_socso_wage'
                                                    | 'is_eis_wage'
                                                    | 'is_pcb_wage',
                                                event.target.checked,
                                            )
                                        }
                                    />
                                    {label}
                                </label>
                            ))}
                        </div>
                    </div>
                    <label className="flex items-center gap-3 rounded-lg border p-3 text-sm">
                        <input
                            type="checkbox"
                            checked={data.is_active}
                            onChange={(event) =>
                                setData('is_active', event.target.checked)
                            }
                            className="size-4"
                        />
                        Komponen aktif
                    </label>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Batal
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

function SalaryProfileDialog({ employee }: { employee: Employee }) {
    const [open, setOpen] = useState(false);
    const defaultSalary =
        employee.salary_profile?.basic_salary ?? employee.legacy_salary ?? '';
    const { data, setData, post, processing, errors } = useForm({
        employee_id: employee.id,
        basic_salary: String(defaultSalary),
        effective_from:
            employee.salary_profile?.effective_from ??
            new Date().toISOString().slice(0, 7) + '-01',
        is_active: employee.salary_profile?.is_active ?? true,
        notes: employee.salary_profile?.notes ?? '',
    });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        post('/tetapan-payroll/profil-gaji', {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    <Banknote />
                    {employee.salary_profile ? 'Edit Gaji' : 'Tetapkan Gaji'}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Profil Gaji Pekerja</DialogTitle>
                    <DialogDescription>
                        {employee.employee_name} ·{' '}
                        {employee.employee_number ?? 'Tiada ID'}
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-2">
                        <Label>Gaji Asas Bulanan (MYR)</Label>
                        <Input
                            type="number"
                            min="0.01"
                            step="0.01"
                            value={data.basic_salary}
                            onChange={(event) =>
                                setData('basic_salary', event.target.value)
                            }
                        />
                        {employee.legacy_salary !== null && (
                            <p className="text-xs text-muted-foreground">
                                Rujukan gaji asal:{' '}
                                {money(employee.legacy_salary)}
                            </p>
                        )}
                        <InputError message={errors.basic_salary} />
                    </div>
                    <div className="space-y-2">
                        <Label>Bulan Berkuat Kuasa</Label>
                        <Input
                            type="month"
                            value={data.effective_from.slice(0, 7)}
                            onChange={(event) =>
                                setData(
                                    'effective_from',
                                    `${event.target.value}-01`,
                                )
                            }
                        />
                        <InputError message={errors.effective_from} />
                    </div>
                    <div className="space-y-2">
                        <Label>Catatan</Label>
                        <textarea
                            rows={3}
                            value={data.notes}
                            onChange={(event) =>
                                setData('notes', event.target.value)
                            }
                            className="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                        />
                        <InputError message={errors.notes} />
                    </div>
                    <label className="flex items-center gap-3 rounded-lg border p-3 text-sm">
                        <input
                            type="checkbox"
                            checked={data.is_active}
                            onChange={(event) =>
                                setData('is_active', event.target.checked)
                            }
                            className="size-4"
                        />
                        Profil gaji aktif
                    </label>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Batal
                            </Button>
                        </DialogClose>
                        <Button type="submit" disabled={processing}>
                            Simpan Profil
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function EmployeeComponentDialog({
    employee,
    components,
}: {
    employee: Employee;
    components: PayrollComponent[];
}) {
    const [open, setOpen] = useState(false);
    const activeComponents = components.filter(
        (component) => component.is_active,
    );
    const { data, setData, post, processing, errors, reset } = useForm({
        employee_id: employee.id,
        payroll_component_id: activeComponents[0]
            ? String(activeComponents[0].id)
            : '',
        amount: '',
        effective_from: new Date().toISOString().slice(0, 7) + '-01',
        effective_to: '',
        is_active: true,
        notes: '',
    });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        post('/tetapan-payroll/komponen-pekerja', {
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
                <Button
                    size="sm"
                    variant="outline"
                    disabled={
                        !employee.salary_profile || activeComponents.length < 1
                    }
                >
                    <Coins />
                    Komponen Tetap
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Tetapkan Komponen Pekerja</DialogTitle>
                    <DialogDescription>
                        {employee.employee_name} · komponen sama akan dikemas
                        kini, bukan digandakan.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-2">
                        <Label>Komponen</Label>
                        <Select
                            value={data.payroll_component_id}
                            onValueChange={(value) =>
                                setData('payroll_component_id', value)
                            }
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Pilih komponen" />
                            </SelectTrigger>
                            <SelectContent>
                                {activeComponents.map((component) => (
                                    <SelectItem
                                        key={component.id}
                                        value={String(component.id)}
                                    >
                                        {component.name} ·{' '}
                                        {component.type === 'earning'
                                            ? 'Pendapatan'
                                            : 'Potongan'}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.payroll_component_id} />
                    </div>
                    <div className="space-y-2">
                        <Label>Jumlah Bulanan (MYR)</Label>
                        <Input
                            type="number"
                            min="0.01"
                            step="0.01"
                            value={data.amount}
                            onChange={(event) =>
                                setData('amount', event.target.value)
                            }
                        />
                        <InputError message={errors.amount} />
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-2">
                            <Label>Bermula</Label>
                            <Input
                                type="month"
                                value={data.effective_from.slice(0, 7)}
                                onChange={(event) =>
                                    setData(
                                        'effective_from',
                                        `${event.target.value}-01`,
                                    )
                                }
                            />
                            <InputError message={errors.effective_from} />
                        </div>
                        <div className="space-y-2">
                            <Label>Tamat (Pilihan)</Label>
                            <Input
                                type="month"
                                value={
                                    data.effective_to
                                        ? data.effective_to.slice(0, 7)
                                        : ''
                                }
                                onChange={(event) =>
                                    setData(
                                        'effective_to',
                                        event.target.value
                                            ? `${event.target.value}-01`
                                            : '',
                                    )
                                }
                            />
                            <InputError message={errors.effective_to} />
                        </div>
                    </div>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Batal
                            </Button>
                        </DialogClose>
                        <Button type="submit" disabled={processing}>
                            Simpan Komponen
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function PayrollSettings({
    settings,
    components,
    employees,
    filters,
    statistics,
}: Props) {
    const [search, setSearch] = useState(filters.search);
    const applySearch = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.get(
            '/tetapan-payroll',
            { search },
            { preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Tetapan Payroll" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <Button asChild variant="ghost" size="sm" className="-ml-3">
                        <Link href="/payroll">
                            <ArrowLeft />
                            Kembali ke Payroll
                        </Link>
                    </Button>
                    <h1 className="mt-2 flex items-center gap-2 text-2xl font-semibold">
                        <Settings2 className="size-6 text-primary" />
                        Tetapan Payroll
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Konfigurasi formula, komponen tetap dan profil gaji
                        pekerja tanpa mengubah `db_spp`.
                    </p>
                </div>

                <div className="grid gap-4 sm:grid-cols-3">
                    {[
                        {
                            label: 'Pekerja Aktif',
                            value: statistics.active_employees,
                        },
                        {
                            label: 'Profil Gaji Aktif',
                            value: statistics.configured_profiles,
                        },
                        {
                            label: 'Komponen Aktif',
                            value: statistics.active_components,
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

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <SlidersHorizontal className="size-5 text-primary" />
                            Formula Pengiraan
                        </CardTitle>
                        <CardDescription>
                            Kadar harian = gaji asas ÷ pembahagi hari bekerja.
                            Kadar OT sejam = kadar harian ÷ jam sehari.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <SettingsForm settings={settings} />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="flex-row items-start justify-between gap-4">
                        <div>
                            <CardTitle className="flex items-center gap-2">
                                <Coins className="size-5 text-primary" />
                                Komponen Payroll
                            </CardTitle>
                            <CardDescription>
                                Contoh: elaun telefon, elaun perjalanan atau
                                potongan pinjaman.
                            </CardDescription>
                        </div>
                        <ComponentDialog />
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto rounded-lg border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Kod</TableHead>
                                        <TableHead>Nama</TableHead>
                                        <TableHead>Jenis</TableHead>
                                        <TableHead>Asas Upah</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Tindakan</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {components.map((component) => (
                                        <TableRow key={component.id}>
                                            <TableCell className="font-mono text-xs">
                                                {component.code}
                                            </TableCell>
                                            <TableCell className="font-medium">
                                                {component.name}
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex flex-wrap gap-1">
                                                    {[
                                                        [
                                                            component.is_epf_wage,
                                                            'KWSP',
                                                        ],
                                                        [
                                                            component.is_socso_wage,
                                                            'PERKESO',
                                                        ],
                                                        [
                                                            component.is_eis_wage,
                                                            'EIS',
                                                        ],
                                                        [
                                                            component.is_pcb_wage,
                                                            'PCB',
                                                        ],
                                                    ]
                                                        .filter(
                                                            ([enabled]) =>
                                                                enabled,
                                                        )
                                                        .map(([, label]) => (
                                                            <Badge
                                                                key={String(
                                                                    label,
                                                                )}
                                                                variant="secondary"
                                                            >
                                                                {String(label)}
                                                            </Badge>
                                                        ))}
                                                    {!component.is_epf_wage &&
                                                        !component.is_socso_wage &&
                                                        !component.is_eis_wage &&
                                                        !component.is_pcb_wage &&
                                                        '-'}
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <Badge
                                                    variant="outline"
                                                    className={
                                                        component.type ===
                                                        'earning'
                                                            ? 'border-emerald-500/30 text-emerald-700'
                                                            : 'border-red-500/30 text-red-700'
                                                    }
                                                >
                                                    {component.type ===
                                                    'earning'
                                                        ? 'Pendapatan'
                                                        : 'Potongan'}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>
                                                {component.is_active
                                                    ? 'Aktif'
                                                    : 'Tidak Aktif'}
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex gap-1">
                                                    <ComponentDialog
                                                        component={component}
                                                    />
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        onClick={() =>
                                                            router.patch(
                                                                `/tetapan-payroll/komponen/${component.id}/status`,
                                                                {},
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            )
                                                        }
                                                    >
                                                        {component.is_active
                                                            ? 'Nyahaktif'
                                                            : 'Aktifkan'}
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <UserRoundCog className="size-5 text-primary" />
                            Profil Gaji Pekerja
                        </CardTitle>
                        <CardDescription>
                            Nilai `legacy_salary` daripada jawatan lama
                            dipaparkan sebagai rujukan. Simpanan baharu dibuat
                            dalam database aplikasi.
                        </CardDescription>
                        <form
                            onSubmit={applySearch}
                            className="flex max-w-xl gap-2 pt-2"
                        >
                            <Input
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Cari nama, ID atau jawatan"
                            />
                            <Button type="submit" variant="secondary">
                                <Search />
                                Cari
                            </Button>
                        </form>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="overflow-x-auto rounded-lg border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Pekerja</TableHead>
                                        <TableHead>Jawatan</TableHead>
                                        <TableHead className="text-right">
                                            Gaji Asal
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Gaji Sistem
                                        </TableHead>
                                        <TableHead>Komponen Tetap</TableHead>
                                        <TableHead>Tindakan</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {employees.data.map((employee) => (
                                        <TableRow key={employee.id}>
                                            <TableCell>
                                                <p className="font-medium">
                                                    {employee.employee_name}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {employee.employee_number ??
                                                        'Tiada ID'}
                                                </p>
                                            </TableCell>
                                            <TableCell>
                                                {employee.position ?? '-'}
                                            </TableCell>
                                            <TableCell className="text-right text-muted-foreground tabular-nums">
                                                {money(employee.legacy_salary)}
                                            </TableCell>
                                            <TableCell className="text-right font-medium tabular-nums">
                                                {employee.salary_profile
                                                    ? money(
                                                          employee
                                                              .salary_profile
                                                              .basic_salary,
                                                      )
                                                    : 'Belum ditetapkan'}
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex max-w-sm flex-wrap gap-1">
                                                    {employee.components
                                                        .length > 0 ? (
                                                        employee.components.map(
                                                            (component) => (
                                                                <button
                                                                    type="button"
                                                                    key={
                                                                        component.id
                                                                    }
                                                                    title="Klik untuk tukar status"
                                                                    onClick={() =>
                                                                        router.patch(
                                                                            `/tetapan-payroll/komponen-pekerja/${component.id}/status`,
                                                                            {},
                                                                            {
                                                                                preserveScroll: true,
                                                                            },
                                                                        )
                                                                    }
                                                                    className={`rounded-full border px-2 py-1 text-xs ${
                                                                        component.is_active
                                                                            ? 'border-primary/30 bg-primary/5'
                                                                            : 'opacity-50'
                                                                    }`}
                                                                >
                                                                    {
                                                                        component.name
                                                                    }{' '}
                                                                    ·{' '}
                                                                    {money(
                                                                        component.amount,
                                                                    )}
                                                                </button>
                                                            ),
                                                        )
                                                    ) : (
                                                        <span className="text-sm text-muted-foreground">
                                                            Tiada
                                                        </span>
                                                    )}
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex flex-wrap gap-2">
                                                    <SalaryProfileDialog
                                                        employee={employee}
                                                    />
                                                    <EmployeeComponentDialog
                                                        employee={employee}
                                                        components={components}
                                                    />
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                        {employees.links.length > 3 && (
                            <div className="flex flex-wrap gap-2">
                                {employees.links.map((link, index) => (
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
