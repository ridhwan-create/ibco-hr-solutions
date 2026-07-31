import { Head, router, useForm } from '@inertiajs/react';
import {
    Pencil,
    Plus,
    Save,
    Settings2,
    ShieldCheck,
    TimerReset,
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

type OvertimeType = {
    id: number;
    code: string;
    name: string;
    rate_multiplier: number;
    minimum_minutes: number;
    maximum_hours: number;
    requires_attachment: boolean;
    is_active: boolean;
};

type Props = {
    overtimeTypes: OvertimeType[];
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
};

type TypeForm = {
    code: string;
    name: string;
    rate_multiplier: string;
    minimum_minutes: string;
    maximum_hours: string;
    requires_attachment: boolean;
    is_active: boolean;
};

function TypeDialog({ overtimeType }: { overtimeType?: OvertimeType }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, put, processing, errors, reset } =
        useForm<TypeForm>({
            code: overtimeType?.code ?? '',
            name: overtimeType?.name ?? '',
            rate_multiplier: String(overtimeType?.rate_multiplier ?? 1.5),
            minimum_minutes: String(overtimeType?.minimum_minutes ?? 30),
            maximum_hours: String(overtimeType?.maximum_hours ?? 4),
            requires_attachment: overtimeType?.requires_attachment ?? false,
            is_active: overtimeType?.is_active ?? true,
        });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);

                if (!overtimeType) {
                    reset();
                }
            },
        };

        if (overtimeType) {
            put(`/tetapan-ot/jenis/${overtimeType.id}`, options);
        } else {
            post('/tetapan-ot/jenis', options);
        }
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    size="sm"
                    variant={overtimeType ? 'outline' : 'default'}
                >
                    {overtimeType ? <Pencil /> : <Plus />}
                    {overtimeType ? 'Edit' : 'Tambah Jenis'}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {overtimeType ? 'Edit Jenis OT' : 'Jenis OT Baharu'}
                    </DialogTitle>
                    <DialogDescription>
                        Tetapkan kadar gandaan serta julat masa yang dibenarkan.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-2">
                            <Label>Kod</Label>
                            <Input
                                value={data.code}
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
                            <Label>Nama</Label>
                            <Input
                                value={data.name}
                                onChange={(event) =>
                                    setData('name', event.target.value)
                                }
                            />
                            <InputError message={errors.name} />
                        </div>
                        <div className="space-y-2">
                            <Label>Kadar Gandaan</Label>
                            <Input
                                type="number"
                                min="0"
                                max="10"
                                step="0.01"
                                value={data.rate_multiplier}
                                onChange={(event) =>
                                    setData(
                                        'rate_multiplier',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError message={errors.rate_multiplier} />
                        </div>
                        <div className="space-y-2">
                            <Label>Minimum Minit</Label>
                            <Input
                                type="number"
                                min="1"
                                max="720"
                                value={data.minimum_minutes}
                                onChange={(event) =>
                                    setData(
                                        'minimum_minutes',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError message={errors.minimum_minutes} />
                        </div>
                        <div className="space-y-2 sm:col-span-2">
                            <Label>Maksimum Jam Sehari</Label>
                            <Input
                                type="number"
                                min="0.5"
                                max="24"
                                step="0.5"
                                value={data.maximum_hours}
                                onChange={(event) =>
                                    setData('maximum_hours', event.target.value)
                                }
                            />
                            <InputError message={errors.maximum_hours} />
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-5">
                        <label className="flex items-center gap-2 text-sm">
                            <Checkbox
                                checked={data.requires_attachment}
                                onCheckedChange={(checked) =>
                                    setData(
                                        'requires_attachment',
                                        checked === true,
                                    )
                                }
                            />
                            Lampiran wajib
                        </label>
                        <label className="flex items-center gap-2 text-sm">
                            <Checkbox
                                checked={data.is_active}
                                onCheckedChange={(checked) =>
                                    setData('is_active', checked === true)
                                }
                            />
                            Aktif
                        </label>
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

export default function OvertimeSettings({
    overtimeTypes,
    departments,
    supervisors,
    assignments,
}: Props) {
    const assignmentForm = useForm({
        department_id: '',
        approver_user_id: '',
        is_active: true,
    });
    const saveAssignment = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        assignmentForm.post('/tetapan-ot/penyelia', {
            preserveScroll: true,
            onSuccess: () => assignmentForm.reset(),
        });
    };

    return (
        <>
            <Head title="Tetapan OT" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="flex items-center gap-2 text-2xl font-semibold">
                        <Settings2 className="size-6 text-primary" />
                        Tetapan Kerja Lebih Masa
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Urus jenis OT, kadar gandaan, had jam dan penyelia
                        jabatan.
                    </p>
                </div>

                <Card>
                    <CardHeader className="flex-row items-start justify-between gap-4">
                        <div>
                            <CardTitle className="flex items-center gap-2">
                                <TimerReset className="size-5 text-primary" />
                                Jenis dan Polisi OT
                            </CardTitle>
                            <CardDescription>
                                Kadar gandaan disediakan sebagai input Payroll;
                                tiada bayaran dijana pada peringkat ini.
                            </CardDescription>
                        </div>
                        <TypeDialog />
                    </CardHeader>
                    <CardContent className="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Kod / Nama</TableHead>
                                    <TableHead>Kadar</TableHead>
                                    <TableHead>Tempoh</TableHead>
                                    <TableHead>Syarat</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-right">
                                        Tindakan
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {overtimeTypes.map((type) => (
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
                                            {type.rate_multiplier.toFixed(2)}x
                                        </TableCell>
                                        <TableCell>
                                            {type.minimum_minutes} min –{' '}
                                            {type.maximum_hours} jam
                                        </TableCell>
                                        <TableCell>
                                            {type.requires_attachment
                                                ? 'Lampiran wajib'
                                                : 'Lampiran pilihan'}
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant={
                                                    type.is_active
                                                        ? 'default'
                                                        : 'secondary'
                                                }
                                            >
                                                {type.is_active
                                                    ? 'Aktif'
                                                    : 'Tidak aktif'}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex justify-end gap-2">
                                                <TypeDialog
                                                    overtimeType={type}
                                                />
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        router.patch(
                                                            `/tetapan-ot/jenis/${type.id}/status`,
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

                <div className="grid gap-6 xl:grid-cols-[0.8fr_1.2fr]">
                    <Card className="h-fit">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <ShieldCheck className="size-5 text-primary" />
                                Tetapkan Penyelia OT
                            </CardTitle>
                            <CardDescription>
                                Jabatan tanpa pemetaan akan dihantar terus
                                kepada HR.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form
                                onSubmit={saveAssignment}
                                className="space-y-4"
                            >
                                <div className="space-y-2">
                                    <Label>Jabatan</Label>
                                    <Select
                                        value={
                                            assignmentForm.data.department_id
                                        }
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
                                                    value={String(
                                                        department.id,
                                                    )}
                                                >
                                                    {department.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        message={
                                            assignmentForm.errors.department_id
                                        }
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label>Penyelia</Label>
                                    <Select
                                        value={
                                            assignmentForm.data.approver_user_id
                                        }
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
                                                    value={String(
                                                        supervisor.id,
                                                    )}
                                                >
                                                    {supervisor.name} ·{' '}
                                                    {supervisor.email}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        message={
                                            assignmentForm.errors
                                                .approver_user_id
                                        }
                                    />
                                </div>
                                <Button
                                    type="submit"
                                    className="w-full"
                                    disabled={assignmentForm.processing}
                                >
                                    <Save />
                                    Simpan Pemetaan
                                </Button>
                            </form>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Penyelia Mengikut Jabatan</CardTitle>
                            <CardDescription>
                                Pemetaan sedia ada akan dikemas kini jika
                                jabatan yang sama dipilih semula.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Jabatan</TableHead>
                                        <TableHead>Penyelia</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="text-right">
                                            Tindakan
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {assignments.map((assignment) => (
                                        <TableRow key={assignment.id}>
                                            <TableCell className="font-medium">
                                                {assignment.department}
                                            </TableCell>
                                            <TableCell>
                                                <p>
                                                    {assignment.approver_name ??
                                                        '-'}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {assignment.approver_email}
                                                </p>
                                            </TableCell>
                                            <TableCell>
                                                <Badge
                                                    variant={
                                                        assignment.is_active
                                                            ? 'default'
                                                            : 'secondary'
                                                    }
                                                >
                                                    {assignment.is_active
                                                        ? 'Aktif'
                                                        : 'Tidak aktif'}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <Button
                                                    size="sm"
                                                    variant="destructive"
                                                    onClick={() => {
                                                        if (
                                                            window.confirm(
                                                                `Buang pemetaan ${assignment.department}?`,
                                                            )
                                                        ) {
                                                            router.delete(
                                                                `/tetapan-ot/penyelia/${assignment.id}`,
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            );
                                                        }
                                                    }}
                                                >
                                                    <Trash2 />
                                                    Buang
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                    {assignments.length === 0 && (
                                        <TableRow>
                                            <TableCell
                                                colSpan={4}
                                                className="h-24 text-center text-muted-foreground"
                                            >
                                                Belum ada pemetaan penyelia OT.
                                            </TableCell>
                                        </TableRow>
                                    )}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

OvertimeSettings.layout = {
    breadcrumbs: [{ title: 'Tetapan OT', href: '/tetapan-ot' }],
};
