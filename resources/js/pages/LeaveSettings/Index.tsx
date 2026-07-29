import { Head, router, useForm } from '@inertiajs/react';
import {
    CalendarDays,
    Check,
    Pencil,
    Plus,
    Save,
    Settings2,
    ShieldCheck,
    Trash2,
    UserRoundCheck,
    WalletCards,
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
import { Checkbox } from '@/components/ui/checkbox';
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

type LeaveType = {
    id: number;
    code: string;
    name: string;
    default_entitlement_days: number;
    deduct_balance: boolean;
    allow_half_day: boolean;
    requires_attachment: boolean;
    is_active: boolean;
};

type Employee = {
    id: number;
    employee_id: string | null;
    name: string;
    department_id: number | null;
};

type Entitlement = {
    id: number;
    employee_id: number;
    employee_number: string | null;
    employee_name: string | null;
    leave_type_id: number;
    leave_type: string | null;
    year: number;
    entitled_days: number;
    carry_forward_days: number;
    adjustment_days: number;
    balance: number;
    notes: string | null;
};

type Department = { id: number; name: string };
type Supervisor = { id: number; name: string; email: string };
type Assignment = {
    id: number;
    department_id: number;
    department: string;
    approver_user_id: number;
    approver_name: string | null;
    approver_email: string | null;
    is_active: boolean;
};
type Holiday = {
    id: number;
    name: string;
    holiday_date: string;
    is_active: boolean;
};

type Props = {
    filters: { year: number; search: string };
    leaveTypes: LeaveType[];
    employees: Employee[];
    entitlements: Entitlement[];
    departments: Department[];
    supervisors: Supervisor[];
    assignments: Assignment[];
    holidays: Holiday[];
};

type TypeFormData = {
    code: string;
    name: string;
    default_entitlement_days: string;
    deduct_balance: boolean;
    allow_half_day: boolean;
    requires_attachment: boolean;
    is_active: boolean;
};

function TypeDialog({ leaveType }: { leaveType?: LeaveType }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, put, processing, errors, reset } =
        useForm<TypeFormData>({
            code: leaveType?.code ?? '',
            name: leaveType?.name ?? '',
            default_entitlement_days: String(
                leaveType?.default_entitlement_days ?? 0,
            ),
            deduct_balance: leaveType?.deduct_balance ?? true,
            allow_half_day: leaveType?.allow_half_day ?? false,
            requires_attachment: leaveType?.requires_attachment ?? false,
            is_active: leaveType?.is_active ?? true,
        });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);

                if (!leaveType) {
                    reset();
                }
            },
        };

        if (leaveType) {
            put(`/tetapan-cuti/jenis/${leaveType.id}`, options);
        } else {
            post('/tetapan-cuti/jenis', options);
        }
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant={leaveType ? 'outline' : 'default'}>
                    {leaveType ? <Pencil /> : <Plus />}
                    {leaveType ? 'Edit' : 'Tambah Jenis'}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {leaveType ? 'Edit Jenis Cuti' : 'Jenis Cuti Baharu'}
                    </DialogTitle>
                    <DialogDescription>
                        Tetapkan kelayakan asas dan syarat permohonan.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor={`type-code-${leaveType?.id ?? 0}`}>
                                Kod
                            </Label>
                            <Input
                                id={`type-code-${leaveType?.id ?? 0}`}
                                value={data.code}
                                onChange={(event) =>
                                    setData(
                                        'code',
                                        event.target.value.toUpperCase(),
                                    )
                                }
                                placeholder="ANNUAL"
                            />
                            <InputError message={errors.code} />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor={`type-days-${leaveType?.id ?? 0}`}>
                                Kelayakan Asas
                            </Label>
                            <Input
                                id={`type-days-${leaveType?.id ?? 0}`}
                                type="number"
                                min="0"
                                max="365"
                                step="0.5"
                                value={data.default_entitlement_days}
                                onChange={(event) =>
                                    setData(
                                        'default_entitlement_days',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError
                                message={errors.default_entitlement_days}
                            />
                        </div>
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor={`type-name-${leaveType?.id ?? 0}`}>
                            Nama
                        </Label>
                        <Input
                            id={`type-name-${leaveType?.id ?? 0}`}
                            value={data.name}
                            onChange={(event) =>
                                setData('name', event.target.value)
                            }
                        />
                        <InputError message={errors.name} />
                    </div>
                    <div className="space-y-3 rounded-lg border p-3">
                        {[
                            [
                                'deduct_balance',
                                'Tolak daripada baki selepas kelulusan',
                            ],
                            ['allow_half_day', 'Benarkan separuh hari'],
                            [
                                'requires_attachment',
                                'Lampiran wajib semasa permohonan',
                            ],
                            ['is_active', 'Jenis cuti aktif'],
                        ].map(([field, label]) => (
                            <label
                                key={field}
                                className="flex items-center gap-3 text-sm"
                            >
                                <Checkbox
                                    checked={
                                        data[
                                            field as keyof Pick<
                                                TypeFormData,
                                                | 'deduct_balance'
                                                | 'allow_half_day'
                                                | 'requires_attachment'
                                                | 'is_active'
                                            >
                                        ]
                                    }
                                    onCheckedChange={(checked) =>
                                        setData(
                                            field as
                                                | 'deduct_balance'
                                                | 'allow_half_day'
                                                | 'requires_attachment'
                                                | 'is_active',
                                            checked === true,
                                        )
                                    }
                                />
                                {label}
                            </label>
                        ))}
                    </div>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Tutup
                            </Button>
                        </DialogClose>
                        <Button type="submit" disabled={processing}>
                            <Save />
                            Simpan
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function LeaveSettings({
    filters,
    leaveTypes,
    employees,
    entitlements,
    departments,
    supervisors,
    assignments,
    holidays,
}: Props) {
    const [search, setSearch] = useState(filters.search);
    const [year, setYear] = useState(String(filters.year));
    const entitlementForm = useForm({
        employee_id: '',
        leave_type_id: '',
        year: String(filters.year),
        entitled_days: '0',
        carry_forward_days: '0',
        adjustment_days: '0',
        notes: '',
    });
    const assignmentForm = useForm({
        department_id: '',
        approver_user_id: '',
        is_active: true,
    });
    const holidayForm = useForm({
        name: '',
        holiday_date: '',
    });
    const applyFilters = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.get(
            '/tetapan-cuti',
            { search, year },
            { preserveState: true, replace: true },
        );
    };
    const saveEntitlement = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        entitlementForm.post('/tetapan-cuti/kelayakan', {
            preserveScroll: true,
            onSuccess: () => entitlementForm.reset(),
        });
    };
    const saveAssignment = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        assignmentForm.post('/tetapan-cuti/penyelia', {
            preserveScroll: true,
            onSuccess: () => assignmentForm.reset(),
        });
    };
    const saveHoliday = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        holidayForm.post('/tetapan-cuti/cuti-umum', {
            preserveScroll: true,
            onSuccess: () => holidayForm.reset(),
        });
    };

    return (
        <>
            <Head title="Tetapan Cuti" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="flex items-center gap-2 text-2xl font-semibold tracking-tight">
                        <Settings2 className="size-6 text-primary" />
                        Tetapan Pengurusan Cuti
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Urus jenis cuti, kelayakan, penyelia jabatan dan cuti
                        umum dalam database sistem.
                    </p>
                </div>

                <form
                    onSubmit={applyFilters}
                    className="grid gap-3 rounded-xl border p-4 md:grid-cols-[1fr_180px_auto]"
                >
                    <Input
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Cari nama atau ID pekerja"
                    />
                    <Input
                        type="number"
                        min="2020"
                        max="2100"
                        value={year}
                        onChange={(event) => setYear(event.target.value)}
                    />
                    <Button type="submit">Terapkan Penapis</Button>
                </form>

                <Card>
                    <CardHeader className="flex-row items-start justify-between gap-4">
                        <div>
                            <CardTitle className="flex items-center gap-2">
                                <CalendarDays className="size-5 text-primary" />
                                Jenis Cuti
                            </CardTitle>
                            <CardDescription>
                                Jenis tidak aktif tidak boleh dipilih dalam
                                permohonan baharu.
                            </CardDescription>
                        </div>
                        <TypeDialog />
                    </CardHeader>
                    <CardContent className="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Kod / Nama</TableHead>
                                    <TableHead>Kelayakan Asas</TableHead>
                                    <TableHead>Syarat</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-right">
                                        Tindakan
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {leaveTypes.map((type) => (
                                    <TableRow key={type.id}>
                                        <TableCell>
                                            <p className="font-medium">
                                                {type.name}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {type.code}
                                            </p>
                                        </TableCell>
                                        <TableCell>
                                            {type.default_entitlement_days.toFixed(
                                                1,
                                            )}{' '}
                                            hari
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex flex-wrap gap-1">
                                                {type.deduct_balance && (
                                                    <Badge variant="secondary">
                                                        Potong baki
                                                    </Badge>
                                                )}
                                                {type.allow_half_day && (
                                                    <Badge variant="secondary">
                                                        Separuh hari
                                                    </Badge>
                                                )}
                                                {type.requires_attachment && (
                                                    <Badge variant="secondary">
                                                        Lampiran wajib
                                                    </Badge>
                                                )}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant={
                                                    type.is_active
                                                        ? 'default'
                                                        : 'outline'
                                                }
                                            >
                                                {type.is_active
                                                    ? 'Aktif'
                                                    : 'Tidak Aktif'}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex justify-end gap-2">
                                                <TypeDialog leaveType={type} />
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        router.patch(
                                                            `/tetapan-cuti/jenis/${type.id}/status`,
                                                            {},
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    {type.is_active
                                                        ? 'Nyahaktif'
                                                        : 'Aktifkan'}
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                <div className="grid gap-6 xl:grid-cols-[0.42fr_0.58fr]">
                    <Card className="h-fit">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <WalletCards className="size-5 text-primary" />
                                Tetapkan Kelayakan
                            </CardTitle>
                            <CardDescription>
                                Simpan atau kemas kini kelayakan individu bagi
                                tahun dipilih.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form
                                onSubmit={saveEntitlement}
                                className="space-y-4"
                            >
                                <div className="space-y-2">
                                    <Label>Pekerja</Label>
                                    <Select
                                        value={entitlementForm.data.employee_id}
                                        onValueChange={(value) =>
                                            entitlementForm.setData(
                                                'employee_id',
                                                value,
                                            )
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
                                                    {employee.name} ·{' '}
                                                    {employee.employee_id}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        message={
                                            entitlementForm.errors.employee_id
                                        }
                                    />
                                </div>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label>Jenis Cuti</Label>
                                        <Select
                                            value={
                                                entitlementForm.data
                                                    .leave_type_id
                                            }
                                            onValueChange={(value) =>
                                                entitlementForm.setData(
                                                    'leave_type_id',
                                                    value,
                                                )
                                            }
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Pilih jenis" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {leaveTypes.map((type) => (
                                                    <SelectItem
                                                        key={type.id}
                                                        value={String(type.id)}
                                                    >
                                                        {type.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <InputError
                                            message={
                                                entitlementForm.errors
                                                    .leave_type_id
                                            }
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Tahun</Label>
                                        <Input
                                            type="number"
                                            min="2020"
                                            max="2100"
                                            value={entitlementForm.data.year}
                                            onChange={(event) =>
                                                entitlementForm.setData(
                                                    'year',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                        <InputError
                                            message={
                                                entitlementForm.errors.year
                                            }
                                        />
                                    </div>
                                </div>
                                <div className="grid gap-4 sm:grid-cols-3">
                                    {[
                                        ['entitled_days', 'Kelayakan'],
                                        ['carry_forward_days', 'Bawa Hadapan'],
                                        ['adjustment_days', 'Pelarasan'],
                                    ].map(([field, label]) => (
                                        <div key={field} className="space-y-2">
                                            <Label>{label}</Label>
                                            <Input
                                                type="number"
                                                step="0.5"
                                                value={
                                                    entitlementForm.data[
                                                        field as
                                                            | 'entitled_days'
                                                            | 'carry_forward_days'
                                                            | 'adjustment_days'
                                                    ]
                                                }
                                                onChange={(event) =>
                                                    entitlementForm.setData(
                                                        field as
                                                            | 'entitled_days'
                                                            | 'carry_forward_days'
                                                            | 'adjustment_days',
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                            <InputError
                                                message={
                                                    entitlementForm.errors[
                                                        field as
                                                            | 'entitled_days'
                                                            | 'carry_forward_days'
                                                            | 'adjustment_days'
                                                    ]
                                                }
                                            />
                                        </div>
                                    ))}
                                </div>
                                <div className="space-y-2">
                                    <Label>Catatan</Label>
                                    <Input
                                        value={entitlementForm.data.notes}
                                        onChange={(event) =>
                                            entitlementForm.setData(
                                                'notes',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </div>
                                <Button
                                    className="w-full"
                                    disabled={entitlementForm.processing}
                                >
                                    <Save />
                                    Simpan Kelayakan
                                </Button>
                            </form>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>
                                Rekod Kelayakan {filters.year}
                            </CardTitle>
                            <CardDescription>
                                Baki termasuk bawa hadapan, pelarasan, potongan
                                kelulusan dan pemulangan pembatalan.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Pekerja</TableHead>
                                        <TableHead>Jenis</TableHead>
                                        <TableHead>Kelayakan</TableHead>
                                        <TableHead>Baki</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {entitlements.length === 0 ? (
                                        <TableRow>
                                            <TableCell
                                                colSpan={4}
                                                className="text-center text-muted-foreground"
                                            >
                                                Tiada rekod untuk penapis ini.
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        entitlements.map((entitlement) => (
                                            <TableRow key={entitlement.id}>
                                                <TableCell>
                                                    <p className="font-medium">
                                                        {
                                                            entitlement.employee_name
                                                        }
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {
                                                            entitlement.employee_number
                                                        }
                                                    </p>
                                                </TableCell>
                                                <TableCell>
                                                    {entitlement.leave_type}
                                                </TableCell>
                                                <TableCell>
                                                    {entitlement.entitled_days.toFixed(
                                                        1,
                                                    )}
                                                </TableCell>
                                                <TableCell className="font-semibold">
                                                    {entitlement.balance.toFixed(
                                                        1,
                                                    )}
                                                </TableCell>
                                            </TableRow>
                                        ))
                                    )}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-6 xl:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <UserRoundCheck className="size-5 text-primary" />
                                Penyelia Kelulusan Jabatan
                            </CardTitle>
                            <CardDescription>
                                Pekerja jabatan akan dihantar kepada penyelia
                                ini sebelum kelulusan HR.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-5">
                            {supervisors.length === 0 && (
                                <div className="rounded-lg border border-amber-500/30 bg-amber-500/10 p-3 text-sm text-amber-700 dark:text-amber-300">
                                    Daftarkan pengguna dengan role Penyelia /
                                    Ketua Jabatan terlebih dahulu.
                                </div>
                            )}
                            <form
                                onSubmit={saveAssignment}
                                className="grid gap-3 md:grid-cols-[1fr_1fr_auto]"
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
                                <Button disabled={assignmentForm.processing}>
                                    <Check />
                                    Tetapkan
                                </Button>
                            </form>
                            <InputError
                                message={
                                    assignmentForm.errors.department_id ??
                                    assignmentForm.errors.approver_user_id
                                }
                            />
                            <div className="space-y-2">
                                {assignments.map((assignment) => (
                                    <div
                                        key={assignment.id}
                                        className="flex items-center justify-between gap-3 rounded-lg border p-3"
                                    >
                                        <div>
                                            <p className="font-medium">
                                                {assignment.department}
                                            </p>
                                            <p className="text-sm text-muted-foreground">
                                                {assignment.approver_name} ·{' '}
                                                {assignment.approver_email}
                                            </p>
                                        </div>
                                        <Button
                                            size="icon"
                                            variant="ghost"
                                            onClick={() => {
                                                if (
                                                    window.confirm(
                                                        'Buang tetapan penyelia jabatan ini?',
                                                    )
                                                ) {
                                                    router.delete(
                                                        `/tetapan-cuti/penyelia/${assignment.id}`,
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    );
                                                }
                                            }}
                                        >
                                            <Trash2 />
                                        </Button>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <CalendarDays className="size-5 text-primary" />
                                Kalendar Cuti Umum {filters.year}
                            </CardTitle>
                            <CardDescription>
                                Tarikh ini dikecualikan daripada pengiraan hari
                                bekerja.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-5">
                            <form
                                onSubmit={saveHoliday}
                                className="grid gap-3 md:grid-cols-[1fr_180px_auto]"
                            >
                                <Input
                                    value={holidayForm.data.name}
                                    onChange={(event) =>
                                        holidayForm.setData(
                                            'name',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Nama cuti umum"
                                />
                                <Input
                                    type="date"
                                    value={holidayForm.data.holiday_date}
                                    onChange={(event) =>
                                        holidayForm.setData(
                                            'holiday_date',
                                            event.target.value,
                                        )
                                    }
                                />
                                <Button disabled={holidayForm.processing}>
                                    <Plus />
                                    Tambah
                                </Button>
                            </form>
                            <InputError
                                message={
                                    holidayForm.errors.name ??
                                    holidayForm.errors.holiday_date
                                }
                            />
                            <div className="space-y-2">
                                {holidays.map((holiday) => (
                                    <div
                                        key={holiday.id}
                                        className="flex items-center justify-between gap-3 rounded-lg border p-3"
                                    >
                                        <div>
                                            <p className="font-medium">
                                                {holiday.name}
                                            </p>
                                            <p className="text-sm text-muted-foreground">
                                                {new Intl.DateTimeFormat(
                                                    'ms-MY',
                                                    {
                                                        day: '2-digit',
                                                        month: 'long',
                                                        year: 'numeric',
                                                    },
                                                ).format(
                                                    new Date(
                                                        `${holiday.holiday_date}T00:00:00`,
                                                    ),
                                                )}
                                            </p>
                                        </div>
                                        <Button
                                            size="icon"
                                            variant="ghost"
                                            onClick={() => {
                                                if (
                                                    window.confirm(
                                                        'Buang cuti umum ini?',
                                                    )
                                                ) {
                                                    router.delete(
                                                        `/tetapan-cuti/cuti-umum/${holiday.id}`,
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    );
                                                }
                                            }}
                                        >
                                            <Trash2 />
                                        </Button>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <Card className="border-primary/20 bg-primary/5">
                    <CardContent className="flex items-start gap-3 pt-6">
                        <ShieldCheck className="mt-0.5 size-5 text-primary" />
                        <p className="text-sm text-muted-foreground">
                            Semua tetapan baharu disimpan dalam database sistem.
                            Data pekerja dan jabatan daripada db_spp hanya
                            dibaca untuk pemilihan dan rujukan.
                        </p>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
