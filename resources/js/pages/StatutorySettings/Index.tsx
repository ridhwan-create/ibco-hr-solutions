import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    BadgePercent,
    Building2,
    Calculator,
    Landmark,
    Pencil,
    ReceiptText,
    Search,
    ShieldCheck,
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

type StatutorySettings = {
    kwsp_effective_from: string;
    kwsp_table_limit: number;
    kwsp_employer_threshold: number;
    kwsp_employee_rate: number;
    kwsp_employer_rate_low: number;
    kwsp_employer_rate_high: number;
    kwsp_age60_employee_rate: number;
    kwsp_age60_employer_rate: number;
    kwsp_pr_age60_employee_rate: number;
    kwsp_pr_age60_employer_rate: number;
    kwsp_foreign_employee_rate: number;
    kwsp_foreign_employer_rate: number;
    socso_effective_from: string;
    socso_wage_ceiling: number;
    socso_first_employer_rate: number;
    socso_first_employee_rate: number;
    socso_skbbk_employee_rate: number;
    socso_second_employer_rate: number;
    eis_effective_from: string;
    eis_wage_ceiling: number;
    eis_employee_rate: number;
    eis_employer_rate: number;
    pcb_tax_year: number;
};

type StatutoryProfile = {
    id: number;
    kwsp_category: string;
    socso_category: string;
    eis_enabled: boolean;
    pcb_method: 'fixed' | 'none';
    pcb_monthly_amount: number;
    epf_number: string | null;
    socso_number: string | null;
    tax_number: string | null;
    effective_from: string;
    is_active: boolean;
    notes: string | null;
};

type Employee = {
    id: number;
    employee_number: string | null;
    employee_name: string;
    position: string | null;
    birth_date: string | null;
    age: number | null;
    nationality: string | null;
    legacy_epf_number: string | null;
    legacy_socso_number: string | null;
    statutory_profile: StatutoryProfile | null;
};

type Option = { value: string; label: string };

type Props = {
    statutorySettings: StatutorySettings;
    payslipSettings: {
        company_name: string;
        company_registration_no: string | null;
        company_address: string | null;
        payslip_note: string | null;
    };
    employees: {
        data: Employee[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: { search: string };
    statistics: {
        active_employees: number;
        configured_profiles: number;
        pcb_profiles: number;
    };
    kwspCategories: Option[];
    socsoCategories: Option[];
};

function paginationLabel(label: string): string {
    return label
        .replace('&laquo; Previous', 'Sebelum')
        .replace('Next &raquo;', 'Seterusnya');
}

function NumberField({
    label,
    value,
    onChange,
    error,
    suffix = '%',
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
    error?: string;
    suffix?: string;
}) {
    return (
        <div className="space-y-2">
            <Label>{label}</Label>
            <div className="relative">
                <Input
                    type="number"
                    min="0"
                    step="0.001"
                    value={value}
                    onChange={(event) => onChange(event.target.value)}
                    className="pr-12"
                />
                <span className="absolute top-2.5 right-3 text-xs text-muted-foreground">
                    {suffix}
                </span>
            </div>
            <InputError message={error} />
        </div>
    );
}

function RatesForm({ settings }: { settings: StatutorySettings }) {
    const { data, setData, put, processing, errors } = useForm(
        Object.fromEntries(
            Object.entries(settings).map(([key, value]) => [
                key,
                String(value),
            ]),
        ) as Record<keyof StatutorySettings, string>,
    );
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        put('/tetapan-statutori/kadar', { preserveScroll: true });
    };
    const field = (
        key: keyof StatutorySettings,
        label: string,
        suffix = '%',
    ) => (
        <NumberField
            label={label}
            value={data[key]}
            onChange={(value) => setData(key, value)}
            error={errors[key]}
            suffix={suffix}
        />
    );

    return (
        <form onSubmit={submit} className="space-y-6">
            <div className="space-y-3">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 className="font-semibold">KWSP</h3>
                        <p className="text-xs text-muted-foreground">
                            Jadual julat upah sehingga had jadual; upah lebih
                            tinggi menggunakan kadar peratus.
                        </p>
                    </div>
                    <div className="space-y-1">
                        <Label>Berkuat Kuasa</Label>
                        <Input
                            type="date"
                            value={data.kwsp_effective_from}
                            onChange={(event) =>
                                setData(
                                    'kwsp_effective_from',
                                    event.target.value,
                                )
                            }
                        />
                    </div>
                </div>
                <div className="grid gap-4 md:grid-cols-3 xl:grid-cols-6">
                    {field('kwsp_table_limit', 'Had Jadual', 'RM')}
                    {field('kwsp_employer_threshold', 'Ambang Majikan', 'RM')}
                    {field('kwsp_employee_rate', 'Pekerja Bawah 60')}
                    {field('kwsp_employer_rate_low', 'Majikan ≤ Ambang')}
                    {field('kwsp_employer_rate_high', 'Majikan > Ambang')}
                    {field('kwsp_age60_employer_rate', 'Majikan 60+')}
                    {field(
                        'kwsp_age60_employee_rate',
                        'Pekerja Warganegara 60+',
                    )}
                    {field('kwsp_pr_age60_employee_rate', 'Pekerja PR 60+')}
                    {field('kwsp_pr_age60_employer_rate', 'Majikan PR 60+')}
                    {field(
                        'kwsp_foreign_employee_rate',
                        'Pekerja Bukan Warganegara',
                    )}
                    {field(
                        'kwsp_foreign_employer_rate',
                        'Majikan Bukan Warganegara',
                    )}
                </div>
            </div>

            <div className="border-t pt-5">
                <div className="mb-3 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 className="font-semibold">PERKESO / SKBBK</h3>
                        <p className="text-xs text-muted-foreground">
                            Jadual kategori pertama/kedua termasuk perlindungan
                            24 jam.
                        </p>
                    </div>
                    <div className="space-y-1">
                        <Label>Berkuat Kuasa</Label>
                        <Input
                            type="date"
                            value={data.socso_effective_from}
                            onChange={(event) =>
                                setData(
                                    'socso_effective_from',
                                    event.target.value,
                                )
                            }
                        />
                    </div>
                </div>
                <div className="grid gap-4 md:grid-cols-3 xl:grid-cols-5">
                    {field('socso_wage_ceiling', 'Siling Upah', 'RM')}
                    {field(
                        'socso_first_employer_rate',
                        'Majikan Kategori Pertama',
                    )}
                    {field('socso_first_employee_rate', 'Pekerja Keilatan')}
                    {field('socso_skbbk_employee_rate', 'Pekerja SKBBK')}
                    {field(
                        'socso_second_employer_rate',
                        'Majikan Kategori Kedua',
                    )}
                </div>
            </div>

            <div className="border-t pt-5">
                <div className="mb-3 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 className="font-semibold">EIS & PCB</h3>
                        <p className="text-xs text-muted-foreground">
                            PCB pekerja disimpan sebagai amaun disahkan HR,
                            bukan kadar peratus tetap.
                        </p>
                    </div>
                    <div className="space-y-1">
                        <Label>EIS Berkuat Kuasa</Label>
                        <Input
                            type="date"
                            value={data.eis_effective_from}
                            onChange={(event) =>
                                setData(
                                    'eis_effective_from',
                                    event.target.value,
                                )
                            }
                        />
                    </div>
                </div>
                <div className="grid gap-4 md:grid-cols-4">
                    {field('eis_wage_ceiling', 'Siling Upah EIS', 'RM')}
                    {field('eis_employee_rate', 'EIS Pekerja')}
                    {field('eis_employer_rate', 'EIS Majikan')}
                    {field('pcb_tax_year', 'Tahun Taksiran PCB', 'Tahun')}
                </div>
            </div>

            <Button type="submit" disabled={processing}>
                <Calculator />
                Simpan Kadar Statutori
            </Button>
        </form>
    );
}

function PayslipSettingsForm({
    settings,
}: {
    settings: Props['payslipSettings'];
}) {
    const { data, setData, put, processing, errors } = useForm({
        company_name: settings.company_name ?? '',
        company_registration_no: settings.company_registration_no ?? '',
        company_address: settings.company_address ?? '',
        payslip_note: settings.payslip_note ?? '',
    });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        put('/tetapan-statutori/slip-gaji', { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="grid gap-4 md:grid-cols-2">
            <div className="space-y-2">
                <Label>Nama Majikan</Label>
                <Input
                    value={data.company_name}
                    onChange={(event) =>
                        setData('company_name', event.target.value)
                    }
                />
                <InputError message={errors.company_name} />
            </div>
            <div className="space-y-2">
                <Label>No. Pendaftaran</Label>
                <Input
                    value={data.company_registration_no}
                    onChange={(event) =>
                        setData('company_registration_no', event.target.value)
                    }
                />
                <InputError message={errors.company_registration_no} />
            </div>
            <div className="space-y-2 md:col-span-2">
                <Label>Alamat Majikan</Label>
                <textarea
                    value={data.company_address}
                    onChange={(event) =>
                        setData('company_address', event.target.value)
                    }
                    className="min-h-20 w-full rounded-md border bg-background px-3 py-2 text-sm"
                />
                <InputError message={errors.company_address} />
            </div>
            <div className="space-y-2 md:col-span-2">
                <Label>Catatan Kaki Slip</Label>
                <Input
                    value={data.payslip_note}
                    onChange={(event) =>
                        setData('payslip_note', event.target.value)
                    }
                />
                <InputError message={errors.payslip_note} />
            </div>
            <div className="md:col-span-2">
                <Button type="submit" disabled={processing}>
                    <ReceiptText />
                    Simpan Tetapan Slip
                </Button>
            </div>
        </form>
    );
}

function ProfileDialog({
    employee,
    kwspCategories,
    socsoCategories,
}: {
    employee: Employee;
    kwspCategories: Option[];
    socsoCategories: Option[];
}) {
    const [open, setOpen] = useState(false);
    const profile = employee.statutory_profile;
    const nationality = (employee.nationality ?? '').trim().toLowerCase();
    const isMalaysian = nationality === '' || nationality.includes('malaysia');
    const inferredKwsp = isMalaysian
        ? (employee.age ?? 0) >= 60
            ? 'citizen_60_plus'
            : 'citizen_below_60'
        : 'non_malaysian';
    const { data, setData, post, processing, errors } = useForm({
        employee_id: employee.id,
        kwsp_category: profile?.kwsp_category ?? inferredKwsp,
        socso_category:
            profile?.socso_category ??
            ((employee.age ?? 0) >= 60 ? 'second' : 'first'),
        eis_enabled:
            profile?.eis_enabled ?? (isMalaysian && (employee.age ?? 0) < 60),
        pcb_method: profile?.pcb_method ?? ('fixed' as 'fixed' | 'none'),
        pcb_monthly_amount: String(profile?.pcb_monthly_amount ?? 0),
        epf_number: profile?.epf_number ?? employee.legacy_epf_number ?? '',
        socso_number:
            profile?.socso_number ?? employee.legacy_socso_number ?? '',
        tax_number: profile?.tax_number ?? '',
        effective_from:
            profile?.effective_from?.slice(0, 7) ??
            new Date().toISOString().slice(0, 7),
        is_active: profile?.is_active ?? true,
        notes: profile?.notes ?? '',
    });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        post('/tetapan-statutori/profil-pekerja', {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    <Pencil />
                    {profile ? 'Edit' : 'Tetapkan'}
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Profil Statutori Pekerja</DialogTitle>
                    <DialogDescription>
                        {employee.employee_name} ·{' '}
                        {employee.employee_number ?? 'Tiada ID'}
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="space-y-2">
                            <Label>Kategori KWSP</Label>
                            <Select
                                value={data.kwsp_category}
                                onValueChange={(value) =>
                                    setData('kwsp_category', value)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {kwspCategories.map((option) => (
                                        <SelectItem
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.kwsp_category} />
                        </div>
                        <div className="space-y-2">
                            <Label>Kategori PERKESO</Label>
                            <Select
                                value={data.socso_category}
                                onValueChange={(value) =>
                                    setData('socso_category', value)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {socsoCategories.map((option) => (
                                        <SelectItem
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.socso_category} />
                        </div>
                        <div className="space-y-2">
                            <Label>No. KWSP</Label>
                            <Input
                                value={data.epf_number}
                                onChange={(event) =>
                                    setData('epf_number', event.target.value)
                                }
                            />
                            <InputError message={errors.epf_number} />
                        </div>
                        <div className="space-y-2">
                            <Label>No. PERKESO</Label>
                            <Input
                                value={data.socso_number}
                                onChange={(event) =>
                                    setData('socso_number', event.target.value)
                                }
                            />
                            <InputError message={errors.socso_number} />
                        </div>
                        <div className="space-y-2">
                            <Label>No. Cukai</Label>
                            <Input
                                value={data.tax_number}
                                onChange={(event) =>
                                    setData('tax_number', event.target.value)
                                }
                            />
                            <InputError message={errors.tax_number} />
                        </div>
                        <div className="space-y-2">
                            <Label>PCB Bulanan Disahkan (RM)</Label>
                            <Input
                                type="number"
                                min="0"
                                step="0.01"
                                value={data.pcb_monthly_amount}
                                onChange={(event) =>
                                    setData(
                                        'pcb_monthly_amount',
                                        event.target.value,
                                    )
                                }
                                disabled={data.pcb_method === 'none'}
                            />
                            <InputError message={errors.pcb_monthly_amount} />
                        </div>
                        <div className="space-y-2">
                            <Label>Kaedah PCB</Label>
                            <Select
                                value={data.pcb_method}
                                onValueChange={(value: 'fixed' | 'none') =>
                                    setData('pcb_method', value)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="fixed">
                                        Amaun Disahkan HR/LHDN
                                    </SelectItem>
                                    <SelectItem value="none">
                                        Tiada Potongan
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-2">
                            <Label>Berkuat Kuasa</Label>
                            <Input
                                type="month"
                                value={data.effective_from}
                                onChange={(event) =>
                                    setData(
                                        'effective_from',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError message={errors.effective_from} />
                        </div>
                    </div>
                    <div className="grid gap-3 md:grid-cols-2">
                        <label className="flex items-center gap-3 rounded-lg border p-3 text-sm">
                            <input
                                type="checkbox"
                                checked={data.eis_enabled}
                                onChange={(event) =>
                                    setData('eis_enabled', event.target.checked)
                                }
                                className="size-4"
                            />
                            Caruman EIS diaktifkan
                        </label>
                        <label className="flex items-center gap-3 rounded-lg border p-3 text-sm">
                            <input
                                type="checkbox"
                                checked={data.is_active}
                                onChange={(event) =>
                                    setData('is_active', event.target.checked)
                                }
                                className="size-4"
                            />
                            Profil statutori aktif
                        </label>
                    </div>
                    <div className="space-y-2">
                        <Label>Catatan HR</Label>
                        <textarea
                            value={data.notes}
                            onChange={(event) =>
                                setData('notes', event.target.value)
                            }
                            className="min-h-20 w-full rounded-md border bg-background px-3 py-2 text-sm"
                        />
                    </div>
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

export default function StatutorySettingsPage({
    statutorySettings,
    payslipSettings,
    employees,
    filters,
    statistics,
    kwspCategories,
    socsoCategories,
}: Props) {
    const [search, setSearch] = useState(filters.search);
    const applySearch = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.get(
            '/tetapan-statutori',
            { search },
            { preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Tetapan Statutori & Slip Gaji" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <Button asChild variant="ghost" size="sm" className="-ml-3">
                        <Link href="/payroll">
                            <ArrowLeft />
                            Kembali ke Payroll
                        </Link>
                    </Button>
                    <h1 className="mt-2 flex items-center gap-2 text-2xl font-semibold">
                        <Landmark className="size-6 text-primary" />
                        Tetapan Statutori & Slip Gaji
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Urus kadar KWSP, PERKESO/SKBBK, EIS, PCB dan profil
                        pekerja tanpa menulis ke `db_spp`.
                    </p>
                </div>

                <div className="grid gap-4 sm:grid-cols-3">
                    {[
                        {
                            label: 'Pekerja Aktif',
                            value: statistics.active_employees,
                            icon: UserRoundCog,
                        },
                        {
                            label: 'Profil Statutori',
                            value: statistics.configured_profiles,
                            icon: ShieldCheck,
                        },
                        {
                            label: 'PCB Ditetapkan',
                            value: statistics.pcb_profiles,
                            icon: BadgePercent,
                        },
                    ].map((item) => (
                        <Card key={item.label}>
                            <CardHeader className="pb-2">
                                <CardDescription className="flex items-center gap-2">
                                    <item.icon className="size-4" />
                                    {item.label}
                                </CardDescription>
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
                            <Calculator className="size-5 text-primary" />
                            Kadar & Tarikh Kuat Kuasa
                        </CardTitle>
                        <CardDescription>
                            Nilai ini digunakan pada payroll Draf dan disimpan
                            sebagai snapshot. Semak terhadap jadual rasmi
                            sebelum membuat perubahan.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <RatesForm settings={statutorySettings} />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Building2 className="size-5 text-primary" />
                            Kepala Slip Gaji
                        </CardTitle>
                        <CardDescription>
                            Maklumat majikan yang akan dipaparkan pada PDF.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <PayslipSettingsForm settings={payslipSettings} />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <UserRoundCog className="size-5 text-primary" />
                            Profil Statutori Pekerja
                        </CardTitle>
                        <CardDescription>
                            Tarikh lahir, kewarganegaraan dan nombor lama
                            dipaparkan daripada `db_spp` sebagai rujukan
                            baca-sahaja.
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
                                        <TableHead>Umur / Negara</TableHead>
                                        <TableHead>KWSP</TableHead>
                                        <TableHead>PERKESO / EIS</TableHead>
                                        <TableHead>PCB Bulanan</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Tindakan</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {employees.data.map((employee) => {
                                        const profile =
                                            employee.statutory_profile;

                                        return (
                                            <TableRow key={employee.id}>
                                                <TableCell>
                                                    <p className="font-medium">
                                                        {employee.employee_name}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {employee.employee_number ??
                                                            'Tiada ID'}{' '}
                                                        ·{' '}
                                                        {employee.position ??
                                                            '-'}
                                                    </p>
                                                </TableCell>
                                                <TableCell>
                                                    <p>
                                                        {employee.age !== null
                                                            ? `${employee.age} tahun`
                                                            : 'Umur tiada'}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {employee.nationality ??
                                                            '-'}
                                                    </p>
                                                </TableCell>
                                                <TableCell>
                                                    <p className="text-sm">
                                                        {profile?.kwsp_category ??
                                                            'Automatik'}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {profile?.epf_number ??
                                                            employee.legacy_epf_number ??
                                                            'No. belum ada'}
                                                    </p>
                                                </TableCell>
                                                <TableCell>
                                                    <p className="text-sm">
                                                        {profile?.socso_category ??
                                                            'Automatik'}
                                                    </p>
                                                    <Badge
                                                        variant="outline"
                                                        className="mt-1"
                                                    >
                                                        EIS{' '}
                                                        {profile
                                                            ? profile.eis_enabled
                                                                ? 'Aktif'
                                                                : 'Tidak Aktif'
                                                            : 'Automatik'}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell className="tabular-nums">
                                                    RM{' '}
                                                    {(
                                                        profile?.pcb_monthly_amount ??
                                                        0
                                                    ).toFixed(2)}
                                                </TableCell>
                                                <TableCell>
                                                    <Badge
                                                        variant={
                                                            profile?.is_active
                                                                ? 'default'
                                                                : 'secondary'
                                                        }
                                                    >
                                                        {profile
                                                            ? profile.is_active
                                                                ? 'Ditetapkan'
                                                                : 'Tidak Aktif'
                                                            : 'Inferens Sistem'}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell>
                                                    <ProfileDialog
                                                        employee={employee}
                                                        kwspCategories={
                                                            kwspCategories
                                                        }
                                                        socsoCategories={
                                                            socsoCategories
                                                        }
                                                    />
                                                </TableCell>
                                            </TableRow>
                                        );
                                    })}
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
