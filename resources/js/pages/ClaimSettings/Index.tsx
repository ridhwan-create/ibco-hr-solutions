import { Head, router, useForm } from '@inertiajs/react';
import { Plus, Save, Settings2, Trash2, UserRoundCheck } from 'lucide-react';
import type { FormEvent } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type ClaimType = {
    id: number;
    code: string;
    name: string;
    description: string | null;
    max_per_claim: number | null;
    monthly_limit: number | null;
    annual_limit: number | null;
    requires_receipt: boolean;
    requires_receipt_number: boolean;
    allow_payroll_reimbursement: boolean;
    is_active: boolean;
};

type Props = {
    claimTypes: ClaimType[];
    departments: { id: number; name: string }[];
    supervisors: { id: number; name: string; email: string }[];
    assignments: {
        id: number;
        department_id: number;
        department: string;
        approver_user_id: number;
        approver_name: string | null;
        approver_email: string | null;
        is_active: boolean;
    }[];
    employees: {
        id: number;
        employee_number: string | null;
        name: string;
    }[];
    positions: {
        id: number;
        employee_id: number;
        title: string | null;
        employee_name: string | null;
    }[];
    limitOverrides: {
        id: number;
        claim_type: string;
        scope_type: string;
        scope_id: number;
        scope_label: string;
        max_per_claim: number | null;
        monthly_limit: number | null;
        annual_limit: number | null;
        is_active: boolean;
    }[];
};

function nullableNumber(value: number | null): string {
    return value === null ? '' : String(value);
}

function ClaimTypeEditor({ type }: { type: ClaimType }) {
    const form = useForm({
        code: type.code,
        name: type.name,
        description: type.description ?? '',
        max_per_claim: nullableNumber(type.max_per_claim),
        monthly_limit: nullableNumber(type.monthly_limit),
        annual_limit: nullableNumber(type.annual_limit),
        requires_receipt: type.requires_receipt,
        requires_receipt_number: type.requires_receipt_number,
        allow_payroll_reimbursement: type.allow_payroll_reimbursement,
        is_active: type.is_active,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(`/tetapan-tuntutan/jenis/${type.id}`, {
            preserveScroll: true,
        });
    };

    return (
        <form
            onSubmit={submit}
            className="grid gap-3 rounded-xl border p-4 lg:grid-cols-6"
        >
            <div>
                <Label>Kod</Label>
                <Input
                    value={form.data.code}
                    onChange={(event) =>
                        form.setData('code', event.target.value)
                    }
                />
            </div>
            <div className="lg:col-span-2">
                <Label>Nama</Label>
                <Input
                    value={form.data.name}
                    onChange={(event) =>
                        form.setData('name', event.target.value)
                    }
                />
            </div>
            <div>
                <Label>Had / tuntutan</Label>
                <Input
                    type="number"
                    step="0.01"
                    value={form.data.max_per_claim}
                    onChange={(event) =>
                        form.setData('max_per_claim', event.target.value)
                    }
                />
            </div>
            <div>
                <Label>Had bulanan</Label>
                <Input
                    type="number"
                    step="0.01"
                    value={form.data.monthly_limit}
                    onChange={(event) =>
                        form.setData('monthly_limit', event.target.value)
                    }
                />
            </div>
            <div>
                <Label>Had tahunan</Label>
                <Input
                    type="number"
                    step="0.01"
                    value={form.data.annual_limit}
                    onChange={(event) =>
                        form.setData('annual_limit', event.target.value)
                    }
                />
            </div>
            <div className="lg:col-span-3">
                <Label>Penerangan</Label>
                <Input
                    value={form.data.description}
                    onChange={(event) =>
                        form.setData('description', event.target.value)
                    }
                />
            </div>
            <div className="flex flex-wrap items-center gap-4 lg:col-span-3">
                {[
                    ['requires_receipt', 'Wajib resit'],
                    ['requires_receipt_number', 'Wajib nombor resit'],
                    ['allow_payroll_reimbursement', 'Boleh masuk payroll'],
                ].map(([field, label]) => (
                    <Label key={field} className="flex items-center gap-2">
                        <Checkbox
                            checked={Boolean(
                                form.data[field as keyof typeof form.data],
                            )}
                            onCheckedChange={(checked) =>
                                form.setData(
                                    field as
                                        | 'requires_receipt'
                                        | 'requires_receipt_number'
                                        | 'allow_payroll_reimbursement',
                                    Boolean(checked),
                                )
                            }
                        />
                        {label}
                    </Label>
                ))}
            </div>
            <div className="flex flex-wrap items-center gap-2 lg:col-span-6">
                <Button type="submit" size="sm" disabled={form.processing}>
                    <Save />
                    Simpan
                </Button>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    onClick={() =>
                        router.patch(
                            `/tetapan-tuntutan/jenis/${type.id}/status`,
                            {},
                            { preserveScroll: true },
                        )
                    }
                >
                    {type.is_active ? 'Nyahaktif' : 'Aktifkan'}
                </Button>
                <Badge variant={type.is_active ? 'default' : 'secondary'}>
                    {type.is_active ? 'Aktif' : 'Tidak aktif'}
                </Badge>
                {Object.values(form.errors).map((error) => (
                    <span key={error} className="text-xs text-destructive">
                        {error}
                    </span>
                ))}
            </div>
        </form>
    );
}

export default function Index({
    claimTypes,
    departments,
    supervisors,
    assignments,
    employees,
    positions,
    limitOverrides,
}: Props) {
    const typeForm = useForm({
        code: '',
        name: '',
        description: '',
        max_per_claim: '',
        monthly_limit: '',
        annual_limit: '',
        requires_receipt: true,
        requires_receipt_number: false,
        allow_payroll_reimbursement: true,
        is_active: true,
    });
    const assignmentForm = useForm({
        department_id: '',
        approver_user_id: '',
        is_active: true,
    });
    const limitForm = useForm({
        claim_type_id: '',
        scope_type: 'employee',
        scope_id: '',
        max_per_claim: '',
        monthly_limit: '',
        annual_limit: '',
        is_active: true,
    });

    const submitType = (event: FormEvent) => {
        event.preventDefault();
        typeForm.post('/tetapan-tuntutan/jenis', {
            preserveScroll: true,
            onSuccess: () =>
                typeForm.reset(
                    'code',
                    'name',
                    'description',
                    'max_per_claim',
                    'monthly_limit',
                    'annual_limit',
                ),
        });
    };

    const submitAssignment = (event: FormEvent) => {
        event.preventDefault();
        assignmentForm.post('/tetapan-tuntutan/penyelia', {
            preserveScroll: true,
        });
    };

    const submitLimit = (event: FormEvent) => {
        event.preventDefault();
        limitForm.post('/tetapan-tuntutan/had-khas', {
            preserveScroll: true,
        });
    };
    const scopeOptions =
        limitForm.data.scope_type === 'employee' ? employees : positions;

    return (
        <>
            <Head title="Tetapan Tuntutan" />
            <div className="space-y-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Tetapan Tuntutan
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Jenis, had, pemetaan penyelia dan kelayakan bayaran
                        melalui payroll.
                    </p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Settings2 className="size-5" />
                            Jenis Tuntutan
                        </CardTitle>
                        <CardDescription>
                            Had kosong bermaksud tiada had pada peringkat
                            tersebut.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {claimTypes.map((type) => (
                            <ClaimTypeEditor key={type.id} type={type} />
                        ))}

                        <form
                            onSubmit={submitType}
                            className="grid gap-3 rounded-xl border border-dashed p-4 lg:grid-cols-6"
                        >
                            <Input
                                placeholder="Kod"
                                value={typeForm.data.code}
                                onChange={(event) =>
                                    typeForm.setData('code', event.target.value)
                                }
                            />
                            <Input
                                className="lg:col-span-2"
                                placeholder="Nama jenis"
                                value={typeForm.data.name}
                                onChange={(event) =>
                                    typeForm.setData('name', event.target.value)
                                }
                            />
                            <Input
                                type="number"
                                step="0.01"
                                placeholder="Had / tuntutan"
                                value={typeForm.data.max_per_claim}
                                onChange={(event) =>
                                    typeForm.setData(
                                        'max_per_claim',
                                        event.target.value,
                                    )
                                }
                            />
                            <Input
                                type="number"
                                step="0.01"
                                placeholder="Had bulanan"
                                value={typeForm.data.monthly_limit}
                                onChange={(event) =>
                                    typeForm.setData(
                                        'monthly_limit',
                                        event.target.value,
                                    )
                                }
                            />
                            <Input
                                type="number"
                                step="0.01"
                                placeholder="Had tahunan"
                                value={typeForm.data.annual_limit}
                                onChange={(event) =>
                                    typeForm.setData(
                                        'annual_limit',
                                        event.target.value,
                                    )
                                }
                            />
                            <Input
                                className="lg:col-span-3"
                                placeholder="Penerangan"
                                value={typeForm.data.description}
                                onChange={(event) =>
                                    typeForm.setData(
                                        'description',
                                        event.target.value,
                                    )
                                }
                            />
                            <div className="flex items-center gap-4 lg:col-span-3">
                                <Label className="flex items-center gap-2">
                                    <Checkbox
                                        checked={typeForm.data.requires_receipt}
                                        onCheckedChange={(checked) =>
                                            typeForm.setData(
                                                'requires_receipt',
                                                Boolean(checked),
                                            )
                                        }
                                    />
                                    Wajib resit
                                </Label>
                                <Label className="flex items-center gap-2">
                                    <Checkbox
                                        checked={
                                            typeForm.data
                                                .allow_payroll_reimbursement
                                        }
                                        onCheckedChange={(checked) =>
                                            typeForm.setData(
                                                'allow_payroll_reimbursement',
                                                Boolean(checked),
                                            )
                                        }
                                    />
                                    Payroll
                                </Label>
                                <Button type="submit" size="sm">
                                    <Plus />
                                    Tambah
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <div className="grid gap-6 xl:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <UserRoundCheck className="size-5" />
                                Penyelia Mengikut Jabatan
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <form
                                onSubmit={submitAssignment}
                                className="grid gap-3 md:grid-cols-2"
                            >
                                <Select
                                    value={assignmentForm.data.department_id}
                                    onValueChange={(value) =>
                                        assignmentForm.setData(
                                            'department_id',
                                            value,
                                        )
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Pilih jabatan" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {departments.map((department) => (
                                            <SelectItem
                                                key={department.id}
                                                value={String(department.id)}
                                            >
                                                {department.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <Select
                                    value={assignmentForm.data.approver_user_id}
                                    onValueChange={(value) =>
                                        assignmentForm.setData(
                                            'approver_user_id',
                                            value,
                                        )
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Pilih penyelia" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {supervisors.map((supervisor) => (
                                            <SelectItem
                                                key={supervisor.id}
                                                value={String(supervisor.id)}
                                            >
                                                {supervisor.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <Button type="submit" className="md:col-span-2">
                                    <Save />
                                    Simpan Pemetaan
                                </Button>
                            </form>
                            {assignments.map((assignment) => (
                                <div
                                    key={assignment.id}
                                    className="flex items-center justify-between gap-3 rounded-lg border p-3 text-sm"
                                >
                                    <div>
                                        <p className="font-medium">
                                            {assignment.department}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {assignment.approver_name} ·{' '}
                                            {assignment.approver_email}
                                        </p>
                                    </div>
                                    <Button
                                        size="icon"
                                        variant="ghost"
                                        onClick={() =>
                                            router.delete(
                                                `/tetapan-tuntutan/penyelia/${assignment.id}`,
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        <Trash2 />
                                    </Button>
                                </div>
                            ))}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Had Khas</CardTitle>
                            <CardDescription>
                                Override jenis tuntutan untuk pekerja atau rekod
                                jawatan tertentu.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <form
                                onSubmit={submitLimit}
                                className="grid gap-3 md:grid-cols-2"
                            >
                                <Select
                                    value={limitForm.data.claim_type_id}
                                    onValueChange={(value) =>
                                        limitForm.setData(
                                            'claim_type_id',
                                            value,
                                        )
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Jenis tuntutan" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {claimTypes.map((type) => (
                                            <SelectItem
                                                key={type.id}
                                                value={String(type.id)}
                                            >
                                                {type.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <Select
                                    value={limitForm.data.scope_type}
                                    onValueChange={(value) => {
                                        limitForm.setData('scope_type', value);
                                        limitForm.setData('scope_id', '');
                                    }}
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="employee">
                                            Pekerja
                                        </SelectItem>
                                        <SelectItem value="position">
                                            Jawatan
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <Select
                                    value={limitForm.data.scope_id}
                                    onValueChange={(value) =>
                                        limitForm.setData('scope_id', value)
                                    }
                                >
                                    <SelectTrigger className="md:col-span-2">
                                        <SelectValue placeholder="Pilih sasaran" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {scopeOptions.map((option) => (
                                            <SelectItem
                                                key={option.id}
                                                value={String(option.id)}
                                            >
                                                {'title' in option
                                                    ? `${option.title ?? 'Jawatan'} · ${option.employee_name ?? ''}`
                                                    : `${option.name} · ${option.employee_number ?? ''}`}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <Input
                                    type="number"
                                    step="0.01"
                                    placeholder="Had / tuntutan"
                                    value={limitForm.data.max_per_claim}
                                    onChange={(event) =>
                                        limitForm.setData(
                                            'max_per_claim',
                                            event.target.value,
                                        )
                                    }
                                />
                                <Input
                                    type="number"
                                    step="0.01"
                                    placeholder="Had bulanan"
                                    value={limitForm.data.monthly_limit}
                                    onChange={(event) =>
                                        limitForm.setData(
                                            'monthly_limit',
                                            event.target.value,
                                        )
                                    }
                                />
                                <Input
                                    type="number"
                                    step="0.01"
                                    placeholder="Had tahunan"
                                    value={limitForm.data.annual_limit}
                                    onChange={(event) =>
                                        limitForm.setData(
                                            'annual_limit',
                                            event.target.value,
                                        )
                                    }
                                />
                                <Button type="submit">
                                    <Save />
                                    Simpan Had
                                </Button>
                            </form>
                            {limitOverrides.map((override) => (
                                <div
                                    key={override.id}
                                    className="flex items-start justify-between gap-3 rounded-lg border p-3 text-sm"
                                >
                                    <div>
                                        <p className="font-medium">
                                            {override.claim_type} ·{' '}
                                            {override.scope_label}
                                        </p>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            Setiap:{' '}
                                            {override.max_per_claim ?? '-'} ·
                                            Bulanan:{' '}
                                            {override.monthly_limit ?? '-'} ·
                                            Tahunan:{' '}
                                            {override.annual_limit ?? '-'}
                                        </p>
                                    </div>
                                    <Button
                                        size="icon"
                                        variant="ghost"
                                        onClick={() =>
                                            router.delete(
                                                `/tetapan-tuntutan/had-khas/${override.id}`,
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        <Trash2 />
                                    </Button>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}
