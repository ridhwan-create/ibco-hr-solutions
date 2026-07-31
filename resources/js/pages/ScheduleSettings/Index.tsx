import { Head, router, useForm } from '@inertiajs/react';
import {
    CalendarClock,
    Clock3,
    MoonStar,
    Pencil,
    Plus,
    Power,
    PowerOff,
    Save,
    UsersRound,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
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

type ShiftTemplate = {
    id: number;
    code: string;
    name: string;
    description: string | null;
    start_time: string;
    end_time: string;
    break_minutes: number;
    grace_minutes: number;
    early_departure_grace_minutes: number;
    crosses_midnight: boolean;
    work_days: number[];
    is_default: boolean;
    is_active: boolean;
};

type Assignment = {
    id: number;
    scope_type: 'employee' | 'department' | 'office';
    scope_label: string;
    shift_template: { id: number; code: string; name: string } | null;
    effective_from: string;
    effective_to: string | null;
    priority: number;
    notes: string | null;
    is_active: boolean;
};

type Option = { id: number; name: string };
type EmployeeOption = Option & { employee_id: string | null };

type Props = {
    templates: ShiftTemplate[];
    assignments: Assignment[];
    employeeOptions: EmployeeOption[];
    departmentOptions: Option[];
    officeOptions: Option[];
};

const weekdays = [
    { value: 1, label: 'Isn' },
    { value: 2, label: 'Sel' },
    { value: 3, label: 'Rab' },
    { value: 4, label: 'Kha' },
    { value: 5, label: 'Jum' },
    { value: 6, label: 'Sab' },
    { value: 7, label: 'Aha' },
];

function TemplateDialog({
    template,
    trigger,
}: {
    template?: ShiftTemplate;
    trigger: ReactNode;
}) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, put, processing, errors, reset } = useForm({
        code: template?.code ?? '',
        name: template?.name ?? '',
        description: template?.description ?? '',
        start_time: template?.start_time ?? '08:30',
        end_time: template?.end_time ?? '17:30',
        break_minutes: String(template?.break_minutes ?? 60),
        grace_minutes: String(template?.grace_minutes ?? 10),
        early_departure_grace_minutes: String(
            template?.early_departure_grace_minutes ?? 5,
        ),
        work_days: template?.work_days ?? [1, 2, 3, 4, 5],
        is_default: template?.is_default ?? false,
        is_active: template?.is_active ?? true,
    });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);

                if (!template) {
                    reset();
                }
            },
        };

        if (template) {
            put(`/tetapan-syif/template/${template.id}`, options);
        } else {
            post('/tetapan-syif/template', options);
        }
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {template
                            ? 'Edit Template Syif'
                            : 'Template Syif Baharu'}
                    </DialogTitle>
                    <DialogDescription>
                        Masa tamat yang lebih awal daripada masa mula akan
                        dianggap merentas tengah malam.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
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
                            placeholder="OFFICE"
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
                            placeholder="Waktu Pejabat"
                        />
                        <InputError message={errors.name} />
                    </div>
                    <div className="space-y-2">
                        <Label>Mula</Label>
                        <Input
                            type="time"
                            value={data.start_time}
                            onChange={(event) =>
                                setData('start_time', event.target.value)
                            }
                        />
                        <InputError message={errors.start_time} />
                    </div>
                    <div className="space-y-2">
                        <Label>Tamat</Label>
                        <Input
                            type="time"
                            value={data.end_time}
                            onChange={(event) =>
                                setData('end_time', event.target.value)
                            }
                        />
                        <InputError message={errors.end_time} />
                    </div>
                    <div className="space-y-2">
                        <Label>Rehat (minit)</Label>
                        <Input
                            type="number"
                            min="0"
                            max="240"
                            value={data.break_minutes}
                            onChange={(event) =>
                                setData('break_minutes', event.target.value)
                            }
                        />
                        <InputError message={errors.break_minutes} />
                    </div>
                    <div className="space-y-2">
                        <Label>Kelonggaran Lewat (minit)</Label>
                        <Input
                            type="number"
                            min="0"
                            max="120"
                            value={data.grace_minutes}
                            onChange={(event) =>
                                setData('grace_minutes', event.target.value)
                            }
                        />
                        <InputError message={errors.grace_minutes} />
                    </div>
                    <div className="space-y-2">
                        <Label>Kelonggaran Pulang Awal (minit)</Label>
                        <Input
                            type="number"
                            min="0"
                            max="120"
                            value={data.early_departure_grace_minutes}
                            onChange={(event) =>
                                setData(
                                    'early_departure_grace_minutes',
                                    event.target.value,
                                )
                            }
                        />
                        <InputError
                            message={errors.early_departure_grace_minutes}
                        />
                    </div>
                    <div className="space-y-2 sm:col-span-2">
                        <Label>Hari Bekerja</Label>
                        <div className="flex flex-wrap gap-2">
                            {weekdays.map((day) => (
                                <label
                                    key={day.value}
                                    className="flex items-center gap-2 rounded-lg border px-3 py-2 text-sm"
                                >
                                    <Checkbox
                                        checked={data.work_days.includes(
                                            day.value,
                                        )}
                                        onCheckedChange={(checked) =>
                                            setData(
                                                'work_days',
                                                checked
                                                    ? [
                                                          ...data.work_days,
                                                          day.value,
                                                      ].sort()
                                                    : data.work_days.filter(
                                                          (value) =>
                                                              value !==
                                                              day.value,
                                                      ),
                                            )
                                        }
                                    />
                                    {day.label}
                                </label>
                            ))}
                        </div>
                        <InputError message={errors.work_days} />
                    </div>
                    <div className="space-y-2 sm:col-span-2">
                        <Label>Catatan</Label>
                        <textarea
                            rows={3}
                            value={data.description}
                            onChange={(event) =>
                                setData('description', event.target.value)
                            }
                            className="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        />
                    </div>
                    <div className="flex flex-wrap gap-5 sm:col-span-2">
                        <label className="flex items-center gap-2 text-sm">
                            <Checkbox
                                checked={data.is_default}
                                onCheckedChange={(checked) =>
                                    setData('is_default', Boolean(checked))
                                }
                            />
                            Jadikan template lalai
                        </label>
                        <label className="flex items-center gap-2 text-sm">
                            <Checkbox
                                checked={data.is_active}
                                onCheckedChange={(checked) =>
                                    setData('is_active', Boolean(checked))
                                }
                            />
                            Aktif
                        </label>
                    </div>
                    <DialogFooter className="sm:col-span-2">
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

function AssignmentForm({
    templates,
    employees,
    departments,
    offices,
}: {
    templates: ShiftTemplate[];
    employees: EmployeeOption[];
    departments: Option[];
    offices: Option[];
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        shift_template_id: '',
        scope_type: 'department' as 'employee' | 'department' | 'office',
        employee_id: '',
        department_id: '',
        office_location_id: '',
        effective_from: new Date().toISOString().slice(0, 10),
        effective_to: '',
        priority: '100',
        notes: '',
    });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        post('/tetapan-syif/penetapan', {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    return (
        <form
            onSubmit={submit}
            className="grid gap-4 md:grid-cols-2 xl:grid-cols-4"
        >
            <div className="space-y-2">
                <Label>Template Syif</Label>
                <Select
                    value={data.shift_template_id}
                    onValueChange={(value) =>
                        setData('shift_template_id', value)
                    }
                >
                    <SelectTrigger>
                        <SelectValue placeholder="Pilih template" />
                    </SelectTrigger>
                    <SelectContent>
                        {templates
                            .filter((template) => template.is_active)
                            .map((template) => (
                                <SelectItem
                                    key={template.id}
                                    value={String(template.id)}
                                >
                                    {template.name}
                                </SelectItem>
                            ))}
                    </SelectContent>
                </Select>
                <InputError message={errors.shift_template_id} />
            </div>
            <div className="space-y-2">
                <Label>Skop</Label>
                <Select
                    value={data.scope_type}
                    onValueChange={(
                        value: 'employee' | 'department' | 'office',
                    ) => setData('scope_type', value)}
                >
                    <SelectTrigger>
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="employee">Pekerja</SelectItem>
                        <SelectItem value="department">Jabatan</SelectItem>
                        <SelectItem value="office">Lokasi</SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <div className="space-y-2 xl:col-span-2">
                <Label>Sasaran</Label>
                {data.scope_type === 'employee' && (
                    <Select
                        value={data.employee_id}
                        onValueChange={(value) => setData('employee_id', value)}
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
                                    {employee.employee_id || `#${employee.id}`}{' '}
                                    — {employee.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                )}
                {data.scope_type === 'department' && (
                    <Select
                        value={data.department_id}
                        onValueChange={(value) =>
                            setData('department_id', value)
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
                )}
                {data.scope_type === 'office' && (
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
                )}
                <InputError
                    message={
                        errors.employee_id ||
                        errors.department_id ||
                        errors.office_location_id
                    }
                />
            </div>
            <div className="space-y-2">
                <Label>Berkuat Kuasa</Label>
                <Input
                    type="date"
                    value={data.effective_from}
                    onChange={(event) =>
                        setData('effective_from', event.target.value)
                    }
                />
                <InputError message={errors.effective_from} />
            </div>
            <div className="space-y-2">
                <Label>Tamat (Pilihan)</Label>
                <Input
                    type="date"
                    value={data.effective_to}
                    onChange={(event) =>
                        setData('effective_to', event.target.value)
                    }
                />
                <InputError message={errors.effective_to} />
            </div>
            <div className="space-y-2">
                <Label>Keutamaan</Label>
                <Input
                    type="number"
                    min="1"
                    max="999"
                    value={data.priority}
                    onChange={(event) =>
                        setData('priority', event.target.value)
                    }
                />
                <p className="text-xs text-muted-foreground">
                    Skop pekerja sentiasa mengatasi jabatan dan lokasi.
                </p>
            </div>
            <div className="space-y-2">
                <Label>Catatan</Label>
                <Input
                    value={data.notes}
                    onChange={(event) => setData('notes', event.target.value)}
                />
            </div>
            <div className="xl:col-span-4">
                <Button type="submit" disabled={processing}>
                    <Plus />
                    Simpan Penetapan
                </Button>
            </div>
        </form>
    );
}

export default function ScheduleSettings({
    templates,
    assignments,
    employeeOptions,
    departmentOptions,
    officeOptions,
}: Props) {
    const toggleTemplate = (template: ShiftTemplate) => {
        router.patch(
            `/tetapan-syif/template/${template.id}/status`,
            {},
            { preserveScroll: true },
        );
    };
    const toggleAssignment = (assignment: Assignment) => {
        router.patch(
            `/tetapan-syif/penetapan/${assignment.id}/status`,
            {},
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Tetapan Syif" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <HeadingSmall
                        title="Tetapan Jadual Kerja & Syif"
                        description="Urus template masa, hari bekerja dan penetapan mengikut pekerja, jabatan atau lokasi."
                    />
                    <TemplateDialog
                        trigger={
                            <Button>
                                <Plus />
                                Template Baharu
                            </Button>
                        }
                    />
                </div>

                <div className="grid gap-4 sm:grid-cols-3">
                    <Card>
                        <CardHeader className="flex-row items-center justify-between">
                            <div>
                                <CardDescription>
                                    Template Aktif
                                </CardDescription>
                                <CardTitle className="mt-1 text-3xl">
                                    {
                                        templates.filter(
                                            (template) => template.is_active,
                                        ).length
                                    }
                                </CardTitle>
                            </div>
                            <CalendarClock className="size-6 text-sky-600" />
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader className="flex-row items-center justify-between">
                            <div>
                                <CardDescription>
                                    Penetapan Aktif
                                </CardDescription>
                                <CardTitle className="mt-1 text-3xl">
                                    {
                                        assignments.filter(
                                            (assignment) =>
                                                assignment.is_active,
                                        ).length
                                    }
                                </CardTitle>
                            </div>
                            <UsersRound className="size-6 text-emerald-600" />
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader className="flex-row items-center justify-between">
                            <div>
                                <CardDescription>Syif Malam</CardDescription>
                                <CardTitle className="mt-1 text-3xl">
                                    {
                                        templates.filter(
                                            (template) =>
                                                template.crosses_midnight &&
                                                template.is_active,
                                        ).length
                                    }
                                </CardTitle>
                            </div>
                            <MoonStar className="size-6 text-violet-600" />
                        </CardHeader>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Template Syif</CardTitle>
                        <CardDescription>
                            Template lalai digunakan apabila tiada penetapan
                            khusus yang berkuat kuasa.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        {templates.map((template) => (
                            <div
                                key={template.id}
                                className="space-y-3 rounded-xl border p-4"
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <p className="font-semibold">
                                            {template.name}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {template.code}
                                        </p>
                                    </div>
                                    <div className="flex gap-1">
                                        {template.is_default && (
                                            <Badge>Lalai</Badge>
                                        )}
                                        <Badge
                                            variant={
                                                template.is_active
                                                    ? 'secondary'
                                                    : 'outline'
                                            }
                                        >
                                            {template.is_active
                                                ? 'Aktif'
                                                : 'Tidak Aktif'}
                                        </Badge>
                                    </div>
                                </div>
                                <div className="rounded-lg bg-muted/50 p-3">
                                    <p className="flex items-center gap-2 font-medium">
                                        <Clock3 className="size-4" />
                                        {template.start_time} –{' '}
                                        {template.end_time}
                                    </p>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        Rehat {template.break_minutes} min ·
                                        lewat {template.grace_minutes} min
                                        {template.crosses_midnight
                                            ? ' · merentas tengah malam'
                                            : ''}
                                    </p>
                                </div>
                                <div className="flex flex-wrap gap-1">
                                    {weekdays.map((day) => (
                                        <Badge
                                            key={day.value}
                                            variant={
                                                template.work_days.includes(
                                                    day.value,
                                                )
                                                    ? 'secondary'
                                                    : 'outline'
                                            }
                                        >
                                            {day.label}
                                        </Badge>
                                    ))}
                                </div>
                                <div className="flex gap-2">
                                    <TemplateDialog
                                        template={template}
                                        trigger={
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                className="flex-1"
                                            >
                                                <Pencil />
                                                Edit
                                            </Button>
                                        }
                                    />
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        className="flex-1"
                                        onClick={() => toggleTemplate(template)}
                                    >
                                        {template.is_active ? (
                                            <PowerOff />
                                        ) : (
                                            <Power />
                                        )}
                                        {template.is_active
                                            ? 'Nyahaktif'
                                            : 'Aktifkan'}
                                    </Button>
                                </div>
                            </div>
                        ))}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Penetapan Jadual</CardTitle>
                        <CardDescription>
                            Keutamaan: pekerja → jabatan → lokasi → template
                            lalai.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <AssignmentForm
                            templates={templates}
                            employees={employeeOptions}
                            departments={departmentOptions}
                            offices={officeOptions}
                        />
                    </CardContent>
                </Card>

                <Card className="gap-0 overflow-hidden">
                    <CardHeader className="border-b">
                        <CardTitle>Senarai Penetapan</CardTitle>
                    </CardHeader>
                    <div className="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Skop</TableHead>
                                    <TableHead>Template</TableHead>
                                    <TableHead>Tempoh</TableHead>
                                    <TableHead>Keutamaan</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-right">
                                        Tindakan
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {assignments.map((assignment) => (
                                    <TableRow key={assignment.id}>
                                        <TableCell>
                                            <p className="font-medium">
                                                {assignment.scope_label}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {assignment.scope_type}
                                            </p>
                                        </TableCell>
                                        <TableCell>
                                            {assignment.shift_template?.name ||
                                                '-'}
                                        </TableCell>
                                        <TableCell>
                                            {assignment.effective_from} hingga{' '}
                                            {assignment.effective_to ||
                                                'berterusan'}
                                        </TableCell>
                                        <TableCell>
                                            {assignment.priority}
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant={
                                                    assignment.is_active
                                                        ? 'secondary'
                                                        : 'outline'
                                                }
                                            >
                                                {assignment.is_active
                                                    ? 'Aktif'
                                                    : 'Tidak Aktif'}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    toggleAssignment(assignment)
                                                }
                                            >
                                                {assignment.is_active ? (
                                                    <PowerOff />
                                                ) : (
                                                    <Power />
                                                )}
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                </Card>
            </div>
        </>
    );
}

ScheduleSettings.layout = {
    breadcrumbs: [
        { title: 'Pentadbiran', href: '/tetapan-syif' },
        { title: 'Tetapan Syif', href: '/tetapan-syif' },
    ],
};
