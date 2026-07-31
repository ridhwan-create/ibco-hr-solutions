import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    Calculator,
    CheckCheck,
    ClipboardCheck,
    Download,
    Eye,
    LockKeyhole,
    Landmark,
    Plus,
    ReceiptText,
    RotateCcw,
    Search,
    Trash2,
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
    period_label: string;
    status: PayrollStatus;
    currency: string;
    employee_count: number;
    total_basic_salary: number;
    total_earnings: number;
    total_deductions: number;
    total_net_pay: number;
    total_employee_statutory: number;
    total_employer_statutory: number;
    total_pcb: number;
    generated_at: string | null;
    generated_by: string | null;
    reviewed_at: string | null;
    reviewed_by: string | null;
    review_notes: string | null;
    approved_at: string | null;
    approved_by: string | null;
    approval_notes: string | null;
    finalized_at: string | null;
    finalized_by: string | null;
    returned_to_draft_at: string | null;
    returned_to_draft_by: string | null;
    return_reason: string | null;
};

type PayrollItem = {
    id: number;
    code: string;
    name: string;
    type: 'earning' | 'deduction';
    category: string;
    quantity: number | null;
    rate: number | null;
    multiplier: number | null;
    amount: number;
    is_manual: boolean;
    notes: string | null;
};

type PayrollEntry = {
    id: number;
    employee_number: string | null;
    employee_name: string;
    basic_salary: number;
    overtime_minutes: number;
    overtime_amount: number;
    claim_reimbursements: number;
    unpaid_leave_days: number;
    unpaid_leave_amount: number;
    gross_pay: number;
    total_deductions: number;
    net_pay: number;
    statutory: {
        id: number;
        kwsp_category: string;
        socso_category: string;
        epf_wages: number;
        socso_wages: number;
        eis_wages: number;
        pcb_wages: number;
        kwsp_employee: number;
        kwsp_employer: number;
        socso_employee: number;
        socso_employer: number;
        eis_employee: number;
        eis_employer: number;
        pcb: number;
        total_employee_deductions: number;
        total_employer_contributions: number;
        rate_version: string;
        is_overridden: boolean;
        override_notes: string | null;
    } | null;
    items: PayrollItem[];
};

type Props = {
    payrollRun: PayrollRun;
    entries: {
        data: PayrollEntry[];
        from: number | null;
        to: number | null;
        total: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: { search: string };
    statistics: {
        negative_net_pay: number;
        with_overtime: number;
        with_unpaid_leave: number;
        with_claims: number;
    };
    manualComponents: {
        id: number;
        code: string;
        name: string;
        type: 'earning' | 'deduction';
        is_epf_wage: boolean;
        is_socso_wage: boolean;
        is_eis_wage: boolean;
        is_pcb_wage: boolean;
    }[];
    permissions: {
        can_manage: boolean;
        can_approve: boolean;
    };
};

const statusLabels: Record<PayrollStatus, string> = {
    draft: 'Draf',
    hr_reviewed: 'Menunggu Pelulus',
    approved: 'Diluluskan',
    finalized: 'Dimuktamadkan',
};

const itemLabels: Record<string, string> = {
    basic: 'Gaji Asas',
    recurring: 'Komponen Tetap',
    overtime: 'OT',
    claim_reimbursement: 'Bayaran Balik Tuntutan',
    unpaid_leave: 'Cuti Tanpa Gaji',
    manual: 'Pelarasan Manual',
    statutory: 'Statutori',
};

function money(value: number, currency = 'MYR'): string {
    return new Intl.NumberFormat('ms-MY', {
        style: 'currency',
        currency,
    }).format(value);
}

function dateTime(value: string | null): string {
    if (!value) {
        return '-';
    }

    return new Intl.DateTimeFormat('ms-MY', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function paginationLabel(label: string): string {
    return label
        .replace('&laquo; Previous', 'Sebelum')
        .replace('Next &raquo;', 'Seterusnya');
}

function WorkflowDialog({
    title,
    description,
    action,
    buttonLabel,
    buttonVariant = 'default',
    field = 'notes',
    required = false,
}: {
    title: string;
    description: string;
    action: string;
    buttonLabel: string;
    buttonVariant?: 'default' | 'outline' | 'destructive';
    field?: 'notes' | 'reason';
    required?: boolean;
}) {
    const [open, setOpen] = useState(false);
    const { data, setData, patch, processing, errors, reset } = useForm({
        notes: '',
        reason: '',
        payroll: '',
    });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        patch(action, {
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
                <Button variant={buttonVariant}>{buttonLabel}</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-2">
                        <Label htmlFor={`workflow-${field}`}>
                            {field === 'reason'
                                ? 'Sebab Pembetulan'
                                : 'Catatan'}{' '}
                            {required && '(Wajib)'}
                        </Label>
                        <textarea
                            id={`workflow-${field}`}
                            rows={4}
                            maxLength={2000}
                            value={data[field]}
                            onChange={(event) =>
                                setData(field, event.target.value)
                            }
                            className="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        />
                        <InputError message={errors[field]} />
                    </div>
                    <InputError message={errors.payroll} />
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Batal
                            </Button>
                        </DialogClose>
                        <Button
                            type="submit"
                            variant={buttonVariant}
                            disabled={processing}
                        >
                            Sahkan
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function BreakdownDialog({
    entry,
    payrollRun,
    canEdit,
}: {
    entry: PayrollEntry;
    payrollRun: PayrollRun;
    canEdit: boolean;
}) {
    const remove = (item: PayrollItem) => {
        if (
            !window.confirm(
                `Buang pelarasan "${item.name}" berjumlah ${money(item.amount, payrollRun.currency)}?`,
            )
        ) {
            return;
        }

        router.delete(
            `/payroll/${payrollRun.id}/pekerja/${entry.id}/pelarasan/${item.id}`,
            { preserveScroll: true },
        );
    };

    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    <Eye />
                    Pecahan
                </Button>
            </DialogTrigger>
            <DialogContent className="max-w-3xl">
                <DialogHeader>
                    <DialogTitle>{entry.employee_name}</DialogTitle>
                    <DialogDescription>
                        {entry.employee_number ?? 'Tiada ID'} · pecahan
                        pendapatan dan potongan.
                    </DialogDescription>
                </DialogHeader>
                <div className="max-h-[60vh] overflow-auto rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Komponen</TableHead>
                                <TableHead>Kategori</TableHead>
                                <TableHead className="text-right">
                                    Kuantiti
                                </TableHead>
                                <TableHead className="text-right">
                                    Jumlah
                                </TableHead>
                                {canEdit && <TableHead />}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {entry.items.map((item) => (
                                <TableRow key={item.id}>
                                    <TableCell>
                                        <p className="font-medium">
                                            {item.name}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {item.code}
                                            {item.notes
                                                ? ` · ${item.notes}`
                                                : ''}
                                        </p>
                                    </TableCell>
                                    <TableCell>
                                        <Badge
                                            variant="outline"
                                            className={
                                                item.type === 'earning'
                                                    ? 'border-emerald-500/30 text-emerald-700 dark:text-emerald-300'
                                                    : 'border-red-500/30 text-red-700 dark:text-red-300'
                                            }
                                        >
                                            {itemLabels[item.category] ??
                                                item.category}
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {item.quantity ?? '-'}
                                        {item.multiplier &&
                                        item.multiplier !== 1
                                            ? ` × ${item.multiplier}`
                                            : ''}
                                    </TableCell>
                                    <TableCell className="text-right font-medium tabular-nums">
                                        {item.type === 'deduction' ? '−' : ''}
                                        {money(
                                            item.amount,
                                            payrollRun.currency,
                                        )}
                                    </TableCell>
                                    {canEdit && (
                                        <TableCell>
                                            {item.is_manual && (
                                                <Button
                                                    size="icon"
                                                    variant="ghost"
                                                    aria-label="Buang pelarasan"
                                                    onClick={() => remove(item)}
                                                >
                                                    <Trash2 className="text-destructive" />
                                                </Button>
                                            )}
                                        </TableCell>
                                    )}
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
                <div className="grid grid-cols-2 gap-3 rounded-lg bg-muted/40 p-4 text-sm">
                    <span>Pendapatan Kasar</span>
                    <strong className="text-right">
                        {money(entry.gross_pay, payrollRun.currency)}
                    </strong>
                    <span>Jumlah Potongan</span>
                    <strong className="text-right text-red-600">
                        −{money(entry.total_deductions, payrollRun.currency)}
                    </strong>
                    <span className="border-t pt-2">Gaji Bersih</span>
                    <strong className="border-t pt-2 text-right">
                        {money(entry.net_pay, payrollRun.currency)}
                    </strong>
                </div>
                {entry.statutory && (
                    <div className="rounded-lg border border-primary/20 bg-primary/5 p-4 text-sm">
                        <div className="mb-2 flex items-center justify-between">
                            <strong>Caruman Majikan</strong>
                            <Badge variant="outline">
                                {entry.statutory.rate_version}
                            </Badge>
                        </div>
                        <div className="grid grid-cols-2 gap-2 md:grid-cols-4">
                            <span>
                                KWSP{' '}
                                {money(
                                    entry.statutory.kwsp_employer,
                                    payrollRun.currency,
                                )}
                            </span>
                            <span>
                                PERKESO{' '}
                                {money(
                                    entry.statutory.socso_employer,
                                    payrollRun.currency,
                                )}
                            </span>
                            <span>
                                EIS{' '}
                                {money(
                                    entry.statutory.eis_employer,
                                    payrollRun.currency,
                                )}
                            </span>
                            <strong>
                                Jumlah{' '}
                                {money(
                                    entry.statutory
                                        .total_employer_contributions,
                                    payrollRun.currency,
                                )}
                            </strong>
                        </div>
                        {entry.statutory.is_overridden && (
                            <p className="mt-2 text-xs text-amber-700">
                                Amaun dilaras HR ·{' '}
                                {entry.statutory.override_notes}
                            </p>
                        )}
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}

function AdjustmentDialog({
    entry,
    payrollRun,
    components,
}: {
    entry: PayrollEntry;
    payrollRun: PayrollRun;
    components: Props['manualComponents'];
}) {
    const [open, setOpen] = useState(false);
    const { data, setData, transform, post, processing, errors, reset } =
        useForm({
            payroll_component_id: 'custom',
            name: '',
            type: 'earning' as 'earning' | 'deduction',
            amount: '',
            is_epf_wage: false,
            is_socso_wage: false,
            is_eis_wage: false,
            is_pcb_wage: false,
            notes: '',
        });
    const selected =
        data.payroll_component_id === 'custom'
            ? null
            : components.find(
                  (component) =>
                      component.id === Number(data.payroll_component_id),
              );
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        transform((values) => ({
            ...values,
            payroll_component_id:
                values.payroll_component_id === 'custom'
                    ? null
                    : Number(values.payroll_component_id),
        }));
        post(`/payroll/${payrollRun.id}/pekerja/${entry.id}/pelarasan`, {
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
                    <Plus />
                    Pelarasan
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Tambah Pelarasan Payroll</DialogTitle>
                    <DialogDescription>
                        {entry.employee_name} · pelarasan hanya dibenarkan
                        ketika payroll masih Draf.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-2">
                        <Label>Komponen</Label>
                        <Select
                            value={data.payroll_component_id}
                            onValueChange={(value) => {
                                setData('payroll_component_id', value);

                                const component = components.find(
                                    (item) => item.id === Number(value),
                                );

                                if (component) {
                                    setData('type', component.type);
                                    setData(
                                        'is_epf_wage',
                                        component.is_epf_wage,
                                    );
                                    setData(
                                        'is_socso_wage',
                                        component.is_socso_wage,
                                    );
                                    setData(
                                        'is_eis_wage',
                                        component.is_eis_wage,
                                    );
                                    setData(
                                        'is_pcb_wage',
                                        component.is_pcb_wage,
                                    );
                                }
                            }}
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="custom">
                                    Komponen Khas
                                </SelectItem>
                                {components.map((component) => (
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
                    {!selected && (
                        <>
                            <div className="space-y-2">
                                <Label>Nama Pelarasan</Label>
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
                                    onValueChange={(
                                        value: 'earning' | 'deduction',
                                    ) => setData('type', value)}
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
                        </>
                    )}
                    <div className="space-y-2">
                        <Label>Jumlah (MYR)</Label>
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
                    {!selected && data.type === 'earning' && (
                        <div className="space-y-2">
                            <Label>Asas Upah Statutori</Label>
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
                    )}
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Batal
                            </Button>
                        </DialogClose>
                        <Button type="submit" disabled={processing}>
                            Simpan Pelarasan
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function StatutoryDialog({
    entry,
    payrollRun,
}: {
    entry: PayrollEntry;
    payrollRun: PayrollRun;
}) {
    const [open, setOpen] = useState(false);
    const statutory = entry.statutory;
    const { data, setData, put, processing, errors } = useForm({
        kwsp_employee: String(statutory?.kwsp_employee ?? 0),
        kwsp_employer: String(statutory?.kwsp_employer ?? 0),
        socso_employee: String(statutory?.socso_employee ?? 0),
        socso_employer: String(statutory?.socso_employer ?? 0),
        eis_employee: String(statutory?.eis_employee ?? 0),
        eis_employer: String(statutory?.eis_employer ?? 0),
        pcb: String(statutory?.pcb ?? 0),
        notes: statutory?.override_notes ?? '',
    });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        put(`/payroll/${payrollRun.id}/pekerja/${entry.id}/statutori`, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    <Landmark />
                    Statutori
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Pelarasan Statutori</DialogTitle>
                    <DialogDescription>
                        {entry.employee_name} · gunakan hanya selepas nilai
                        disahkan dengan jadual atau kalkulator rasmi.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-4 md:grid-cols-2">
                        {[
                            ['kwsp_employee', 'KWSP Pekerja'],
                            ['kwsp_employer', 'KWSP Majikan'],
                            ['socso_employee', 'PERKESO/SKBBK Pekerja'],
                            ['socso_employer', 'PERKESO/SKBBK Majikan'],
                            ['eis_employee', 'EIS Pekerja'],
                            ['eis_employer', 'EIS Majikan'],
                            ['pcb', 'PCB'],
                        ].map(([field, label]) => (
                            <div key={field} className="space-y-2">
                                <Label>{label} (RM)</Label>
                                <Input
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={
                                        data[
                                            field as
                                                | 'kwsp_employee'
                                                | 'kwsp_employer'
                                                | 'socso_employee'
                                                | 'socso_employer'
                                                | 'eis_employee'
                                                | 'eis_employer'
                                                | 'pcb'
                                        ]
                                    }
                                    onChange={(event) =>
                                        setData(
                                            field as
                                                | 'kwsp_employee'
                                                | 'kwsp_employer'
                                                | 'socso_employee'
                                                | 'socso_employer'
                                                | 'eis_employee'
                                                | 'eis_employer'
                                                | 'pcb',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={
                                        errors[
                                            field as
                                                | 'kwsp_employee'
                                                | 'kwsp_employer'
                                                | 'socso_employee'
                                                | 'socso_employer'
                                                | 'eis_employee'
                                                | 'eis_employer'
                                                | 'pcb'
                                        ]
                                    }
                                />
                            </div>
                        ))}
                    </div>
                    <div className="space-y-2">
                        <Label>Sebab Pelarasan (Wajib)</Label>
                        <textarea
                            value={data.notes}
                            onChange={(event) =>
                                setData('notes', event.target.value)
                            }
                            className="min-h-20 w-full rounded-md border bg-background px-3 py-2 text-sm"
                        />
                        <InputError message={errors.notes} />
                    </div>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Batal
                            </Button>
                        </DialogClose>
                        <Button type="submit" disabled={processing}>
                            Simpan dan Kira Semula
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function PayrollShow({
    payrollRun,
    entries,
    filters,
    statistics,
    manualComponents,
    permissions,
}: Props) {
    const [search, setSearch] = useState(filters.search);
    const applySearch = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.get(
            `/payroll/${payrollRun.id}`,
            { search },
            { preserveState: true, replace: true },
        );
    };
    const isDraft = payrollRun.status === 'draft';
    const canEdit = isDraft && permissions.can_manage;

    return (
        <>
            <Head title={`Payroll ${payrollRun.period_label}`} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <Button
                            asChild
                            variant="ghost"
                            size="sm"
                            className="-ml-3"
                        >
                            <Link href="/payroll">
                                <ArrowLeft />
                                Kembali
                            </Link>
                        </Button>
                        <h1 className="mt-2 flex items-center gap-2 text-2xl font-semibold">
                            {payrollRun.status === 'finalized' ? (
                                <LockKeyhole className="size-6 text-emerald-600" />
                            ) : (
                                <Calculator className="size-6 text-primary" />
                            )}
                            Payroll {payrollRun.period_label}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {statusLabels[payrollRun.status]} ·{' '}
                            {payrollRun.employee_count} pekerja
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button asChild variant="outline">
                            <a href={`/payroll/${payrollRun.id}/laporan.csv`}>
                                <Download />
                                CSV
                            </a>
                        </Button>
                        {canEdit && (
                            <Button
                                variant="outline"
                                onClick={() =>
                                    router.post(
                                        `/payroll/${payrollRun.id}/kira-semula`,
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <RotateCcw />
                                Kira Semula
                            </Button>
                        )}
                        {canEdit && (
                            <WorkflowDialog
                                title="Selesaikan Semakan HR"
                                description="Selepas dihantar, payroll dikunci daripada pelarasan sehingga pelulus mengembalikannya ke Draf."
                                action={`/payroll/${payrollRun.id}/semakan-hr`}
                                buttonLabel="Hantar kepada Pelulus"
                            />
                        )}
                        {payrollRun.status === 'hr_reviewed' &&
                            permissions.can_approve && (
                                <>
                                    <WorkflowDialog
                                        title="Luluskan Payroll"
                                        description="Semak jumlah keseluruhan sebelum memberikan kelulusan."
                                        action={`/payroll/${payrollRun.id}/lulus`}
                                        buttonLabel="Luluskan"
                                    />
                                    <WorkflowDialog
                                        title="Kembalikan ke Draf"
                                        description="Nyatakan sebab supaya HR boleh membuat pembetulan."
                                        action={`/payroll/${payrollRun.id}/kembali-draf`}
                                        buttonLabel="Kembali ke Draf"
                                        buttonVariant="outline"
                                        field="reason"
                                        required
                                    />
                                </>
                            )}
                        {payrollRun.status === 'approved' &&
                            permissions.can_approve && (
                                <>
                                    <WorkflowDialog
                                        title="Muktamadkan dan Kunci Payroll"
                                        description="Payroll yang dimuktamadkan tidak boleh dikira semula atau diubah."
                                        action={`/payroll/${payrollRun.id}/muktamad`}
                                        buttonLabel="Muktamadkan"
                                    />
                                    <WorkflowDialog
                                        title="Kembalikan ke Draf"
                                        description="Gunakan hanya jika pembetulan diperlukan sebelum payroll dimuktamadkan."
                                        action={`/payroll/${payrollRun.id}/kembali-draf`}
                                        buttonLabel="Kembali ke Draf"
                                        buttonVariant="outline"
                                        field="reason"
                                        required
                                    />
                                </>
                            )}
                    </div>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    {[
                        {
                            label: 'Gaji Asas',
                            value: money(
                                payrollRun.total_basic_salary,
                                payrollRun.currency,
                            ),
                        },
                        {
                            label: 'Pendapatan Kasar',
                            value: money(
                                payrollRun.total_earnings,
                                payrollRun.currency,
                            ),
                        },
                        {
                            label: 'Jumlah Potongan',
                            value: money(
                                payrollRun.total_deductions,
                                payrollRun.currency,
                            ),
                        },
                        {
                            label: 'Gaji Bersih',
                            value: money(
                                payrollRun.total_net_pay,
                                payrollRun.currency,
                            ),
                        },
                        {
                            label: 'Statutori Pekerja',
                            value: money(
                                payrollRun.total_employee_statutory,
                                payrollRun.currency,
                            ),
                        },
                        {
                            label: 'Caruman Majikan',
                            value: money(
                                payrollRun.total_employer_statutory,
                                payrollRun.currency,
                            ),
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
                            <ClipboardCheck className="size-5 text-primary" />
                            Status dan Jejak Kelulusan
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 text-sm md:grid-cols-4">
                        <div>
                            <p className="text-muted-foreground">Dijana</p>
                            <p className="font-medium">
                                {payrollRun.generated_by ?? '-'}
                            </p>
                            <p>{dateTime(payrollRun.generated_at)}</p>
                        </div>
                        <div>
                            <p className="text-muted-foreground">Semakan HR</p>
                            <p className="font-medium">
                                {payrollRun.reviewed_by ?? '-'}
                            </p>
                            <p>{dateTime(payrollRun.reviewed_at)}</p>
                        </div>
                        <div>
                            <p className="text-muted-foreground">Kelulusan</p>
                            <p className="font-medium">
                                {payrollRun.approved_by ?? '-'}
                            </p>
                            <p>{dateTime(payrollRun.approved_at)}</p>
                        </div>
                        <div>
                            <p className="text-muted-foreground">
                                Dimuktamadkan
                            </p>
                            <p className="font-medium">
                                {payrollRun.finalized_by ?? '-'}
                            </p>
                            <p>{dateTime(payrollRun.finalized_at)}</p>
                        </div>
                        {(payrollRun.review_notes ||
                            payrollRun.approval_notes ||
                            payrollRun.return_reason) && (
                            <div className="rounded-lg border bg-muted/30 p-3 md:col-span-4">
                                {payrollRun.review_notes && (
                                    <p>
                                        <strong>Catatan HR:</strong>{' '}
                                        {payrollRun.review_notes}
                                    </p>
                                )}
                                {payrollRun.approval_notes && (
                                    <p>
                                        <strong>Catatan Pelulus:</strong>{' '}
                                        {payrollRun.approval_notes}
                                    </p>
                                )}
                                {payrollRun.return_reason && (
                                    <p>
                                        <strong>Pembetulan terakhir:</strong>{' '}
                                        {payrollRun.return_reason}
                                    </p>
                                )}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {statistics.negative_net_pay > 0 && (
                    <Card className="border-red-500/40 bg-red-500/5">
                        <CardContent className="pt-6 text-sm">
                            Terdapat{' '}
                            <strong>{statistics.negative_net_pay}</strong> rekod
                            gaji bersih negatif. Payroll tidak boleh dihantar
                            kepada pelulus sehingga dibetulkan.
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader className="gap-4">
                        <div>
                            <CardTitle>Rekod Payroll Pekerja</CardTitle>
                            <CardDescription>
                                {statistics.with_overtime} dengan OT ·{' '}
                                {statistics.with_unpaid_leave} dengan potongan
                                cuti tanpa gaji · {statistics.with_claims}{' '}
                                dengan tuntutan
                            </CardDescription>
                        </div>
                        <form
                            onSubmit={applySearch}
                            className="flex max-w-lg gap-2"
                        >
                            <Input
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Cari nama atau ID pekerja"
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
                                        <TableHead className="text-right">
                                            Gaji Asas
                                        </TableHead>
                                        <TableHead className="text-right">
                                            OT
                                        </TableHead>
                                        <TableHead className="text-right">
                                            CTG
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Tuntutan
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Pendapatan
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Potongan
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Statutori
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Bersih
                                        </TableHead>
                                        <TableHead>Tindakan</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {entries.data.map((entry) => (
                                        <TableRow key={entry.id}>
                                            <TableCell>
                                                <p className="font-medium">
                                                    {entry.employee_name}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {entry.employee_number ??
                                                        'Tiada ID'}
                                                </p>
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {money(
                                                    entry.basic_salary,
                                                    payrollRun.currency,
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                <p>
                                                    {money(
                                                        entry.overtime_amount,
                                                        payrollRun.currency,
                                                    )}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {(
                                                        entry.overtime_minutes /
                                                        60
                                                    ).toFixed(2)}{' '}
                                                    jam
                                                </p>
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                <p>
                                                    {money(
                                                        entry.unpaid_leave_amount,
                                                        payrollRun.currency,
                                                    )}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {entry.unpaid_leave_days}{' '}
                                                    hari
                                                </p>
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {money(
                                                    entry.claim_reimbursements,
                                                    payrollRun.currency,
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {money(
                                                    entry.gross_pay,
                                                    payrollRun.currency,
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right text-red-600 tabular-nums">
                                                {money(
                                                    entry.total_deductions,
                                                    payrollRun.currency,
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                <p>
                                                    {money(
                                                        entry.statutory
                                                            ?.total_employee_deductions ??
                                                            0,
                                                        payrollRun.currency,
                                                    )}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    Majikan{' '}
                                                    {money(
                                                        entry.statutory
                                                            ?.total_employer_contributions ??
                                                            0,
                                                        payrollRun.currency,
                                                    )}
                                                </p>
                                            </TableCell>
                                            <TableCell
                                                className={`text-right font-semibold tabular-nums ${
                                                    entry.net_pay < 0
                                                        ? 'text-red-600'
                                                        : ''
                                                }`}
                                            >
                                                {money(
                                                    entry.net_pay,
                                                    payrollRun.currency,
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex flex-wrap gap-2">
                                                    <BreakdownDialog
                                                        entry={entry}
                                                        payrollRun={payrollRun}
                                                        canEdit={canEdit}
                                                    />
                                                    {canEdit && (
                                                        <>
                                                            <AdjustmentDialog
                                                                entry={entry}
                                                                payrollRun={
                                                                    payrollRun
                                                                }
                                                                components={
                                                                    manualComponents
                                                                }
                                                            />
                                                            <StatutoryDialog
                                                                entry={entry}
                                                                payrollRun={
                                                                    payrollRun
                                                                }
                                                            />
                                                        </>
                                                    )}
                                                    <Button
                                                        asChild
                                                        size="sm"
                                                        variant="outline"
                                                    >
                                                        <a
                                                            href={`/payroll/pekerja/${entry.id}/slip-gaji.pdf`}
                                                        >
                                                            <ReceiptText />
                                                            PDF
                                                        </a>
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                    {entries.data.length === 0 && (
                                        <TableRow>
                                            <TableCell
                                                colSpan={9}
                                                className="h-28 text-center text-muted-foreground"
                                            >
                                                Tiada rekod pekerja dijumpai.
                                            </TableCell>
                                        </TableRow>
                                    )}
                                </TableBody>
                            </Table>
                        </div>
                        {entries.links.length > 3 && (
                            <div className="flex flex-wrap gap-2">
                                {entries.links.map((link, index) => (
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

                {payrollRun.status === 'finalized' && (
                    <Card className="border-emerald-500/30 bg-emerald-500/5">
                        <CardContent className="flex items-center gap-3 pt-6 text-sm">
                            <CheckCheck className="size-5 text-emerald-600" />
                            Payroll ini telah dimuktamadkan dan semua nilai
                            dikunci sebagai snapshot rasmi.
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}
