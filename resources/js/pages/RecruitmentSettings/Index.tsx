import { Head, router, useForm } from '@inertiajs/react';
import {
    CheckCircle2,
    ListChecks,
    Pencil,
    Plus,
    Save,
    Settings2,
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

type TemplateTask = {
    id?: number;
    title: string;
    description: string;
    category: string;
    assignee_role: string;
    due_offset_days: string | number;
    is_required: boolean;
};
type OnboardingTemplate = {
    id: number;
    code: string;
    name: string;
    department_id: number | null;
    department: string | null;
    position_name: string | null;
    description: string | null;
    is_active: boolean;
    tasks: TemplateTask[];
};
type Props = {
    templates: OnboardingTemplate[];
    departments: { id: number; name: string }[];
    positionNames: string[];
};

const categoryLabel: Record<string, string> = {
    hr: 'HR',
    supervisor: 'Penyelia',
    it: 'ICT',
    finance: 'Kewangan',
    employee: 'Pekerja',
    facilities: 'Fasiliti',
    other: 'Lain-lain',
};
const assigneeLabel: Record<string, string> = {
    hr: 'HR',
    supervisor: 'Penyelia',
    employee: 'Pekerja Baharu',
    custom: 'Ditetapkan Kemudian',
};
const emptyTask = (): TemplateTask => ({
    title: '',
    description: '',
    category: 'hr',
    assignee_role: 'hr',
    due_offset_days: '0',
    is_required: true,
});

function TemplateDialog({
    template,
    departments,
    positionNames,
}: {
    template?: OnboardingTemplate;
    departments: Props['departments'];
    positionNames: string[];
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        code: template?.code ?? '',
        name: template?.name ?? '',
        department_id: template?.department_id
            ? String(template.department_id)
            : '',
        position_name: template?.position_name ?? '',
        description: template?.description ?? '',
        is_active: template?.is_active ?? true,
        tasks: template?.tasks.map((task) => ({
            ...task,
            due_offset_days: String(task.due_offset_days),
        })) ?? [emptyTask()],
    });
    const updateTask = (
        index: number,
        field: keyof TemplateTask,
        value: string | boolean,
    ) =>
        form.setData(
            'tasks',
            form.data.tasks.map((task, taskIndex) =>
                taskIndex === index ? { ...task, [field]: value } : task,
            ),
        );
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        };

        if (template) {
            form.put(`/tetapan-pengambilan/template/${template.id}`, options);
        } else {
            form.post('/tetapan-pengambilan/template', options);
        }
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    size={template ? 'sm' : 'default'}
                    variant={template ? 'outline' : 'default'}
                >
                    {template ? <Pencil /> : <Plus />}
                    {template ? 'Edit Template' : 'Tambah Template'}
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[92vh] overflow-y-auto sm:max-w-4xl">
                <DialogHeader>
                    <DialogTitle>
                        {template
                            ? 'Edit Template Onboarding'
                            : 'Template Onboarding Baharu'}
                    </DialogTitle>
                    <DialogDescription>
                        Tugasan disalin sebagai snapshot apabila tawaran calon
                        diterima.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-5">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-2">
                            <Label>Kod</Label>
                            <Input
                                value={form.data.code}
                                onChange={(event) =>
                                    form.setData('code', event.target.value)
                                }
                                placeholder="ONB-GENERAL"
                            />
                            <InputError message={form.errors.code} />
                        </div>
                        <div className="space-y-2">
                            <Label>Nama Template</Label>
                            <Input
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                            />
                            <InputError message={form.errors.name} />
                        </div>
                        <div className="space-y-2">
                            <Label>Jabatan (pilihan)</Label>
                            <Select
                                value={form.data.department_id || 'all'}
                                onValueChange={(value) =>
                                    form.setData(
                                        'department_id',
                                        value === 'all' ? '' : value,
                                    )
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        Template umum
                                    </SelectItem>
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
                        </div>
                        <div className="space-y-2">
                            <Label>Jawatan (pilihan)</Label>
                            <Select
                                value={form.data.position_name || 'all'}
                                onValueChange={(value) =>
                                    form.setData(
                                        'position_name',
                                        value === 'all' ? '' : value,
                                    )
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        Semua jawatan
                                    </SelectItem>
                                    {positionNames.map((position) => (
                                        <SelectItem
                                            key={position}
                                            value={position}
                                        >
                                            {position}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-2 sm:col-span-2">
                            <Label>Penerangan</Label>
                            <textarea
                                value={form.data.description}
                                onChange={(event) =>
                                    form.setData(
                                        'description',
                                        event.target.value,
                                    )
                                }
                                className="min-h-20 w-full rounded-md border bg-background px-3 py-2 text-sm"
                            />
                        </div>
                        <label className="flex items-center gap-2">
                            <Checkbox
                                checked={form.data.is_active}
                                onCheckedChange={(checked) =>
                                    form.setData('is_active', checked === true)
                                }
                            />
                            <span className="text-sm">Template aktif</span>
                        </label>
                    </div>

                    <div className="space-y-3">
                        <div className="flex items-center justify-between">
                            <div>
                                <Label className="text-base">
                                    Checklist Tugasan
                                </Label>
                                <p className="text-xs text-muted-foreground">
                                    Offset negatif bermaksud sebelum tarikh
                                    mula.
                                </p>
                            </div>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                onClick={() =>
                                    form.setData('tasks', [
                                        ...form.data.tasks,
                                        emptyTask(),
                                    ])
                                }
                            >
                                <Plus />
                                Tambah Tugasan
                            </Button>
                        </div>
                        {form.data.tasks.map((task, index) => (
                            <div
                                key={index}
                                className="space-y-3 rounded-lg border p-4"
                            >
                                <div className="flex items-center justify-between">
                                    <p className="font-medium">
                                        Tugasan {index + 1}
                                    </p>
                                    {form.data.tasks.length > 1 && (
                                        <Button
                                            type="button"
                                            size="icon"
                                            variant="outline"
                                            onClick={() =>
                                                form.setData(
                                                    'tasks',
                                                    form.data.tasks.filter(
                                                        (_, taskIndex) =>
                                                            taskIndex !== index,
                                                    ),
                                                )
                                            }
                                        >
                                            <Trash2 />
                                        </Button>
                                    )}
                                </div>
                                <div className="grid gap-3 sm:grid-cols-2">
                                    <div className="space-y-2 sm:col-span-2">
                                        <Label>Tajuk</Label>
                                        <Input
                                            value={task.title}
                                            onChange={(event) =>
                                                updateTask(
                                                    index,
                                                    'title',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Kategori</Label>
                                        <Select
                                            value={task.category}
                                            onValueChange={(value) =>
                                                updateTask(
                                                    index,
                                                    'category',
                                                    value,
                                                )
                                            }
                                        >
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {Object.entries(
                                                    categoryLabel,
                                                ).map(([value, label]) => (
                                                    <SelectItem
                                                        key={value}
                                                        value={value}
                                                    >
                                                        {label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Pemilik Lalai</Label>
                                        <Select
                                            value={task.assignee_role}
                                            onValueChange={(value) =>
                                                updateTask(
                                                    index,
                                                    'assignee_role',
                                                    value,
                                                )
                                            }
                                        >
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {Object.entries(
                                                    assigneeLabel,
                                                ).map(([value, label]) => (
                                                    <SelectItem
                                                        key={value}
                                                        value={value}
                                                    >
                                                        {label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Offset Tarikh (hari)</Label>
                                        <Input
                                            type="number"
                                            min="-30"
                                            max="365"
                                            value={task.due_offset_days}
                                            onChange={(event) =>
                                                updateTask(
                                                    index,
                                                    'due_offset_days',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                    <label className="flex items-center gap-2 pt-7">
                                        <Checkbox
                                            checked={task.is_required}
                                            onCheckedChange={(checked) =>
                                                updateTask(
                                                    index,
                                                    'is_required',
                                                    checked === true,
                                                )
                                            }
                                        />
                                        <span className="text-sm">
                                            Tugasan wajib
                                        </span>
                                    </label>
                                    <div className="space-y-2 sm:col-span-2">
                                        <Label>Penerangan</Label>
                                        <textarea
                                            value={task.description}
                                            onChange={(event) =>
                                                updateTask(
                                                    index,
                                                    'description',
                                                    event.target.value,
                                                )
                                            }
                                            className="min-h-16 w-full rounded-md border bg-background px-3 py-2 text-sm"
                                        />
                                    </div>
                                </div>
                            </div>
                        ))}
                        <InputError
                            message={
                                (form.errors as Record<string, string>).tasks
                            }
                        />
                    </div>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Tutup
                            </Button>
                        </DialogClose>
                        <Button disabled={form.processing}>
                            <Save />
                            Simpan Template
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function RecruitmentSettings({
    templates,
    departments,
    positionNames,
}: Props) {
    return (
        <>
            <Head title="Tetapan Pengambilan" />
            <div className="space-y-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            Tetapan Pengambilan & Onboarding
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Template checklist mengikut jabatan atau jawatan.
                        </p>
                    </div>
                    <TemplateDialog
                        departments={departments}
                        positionNames={positionNames}
                    />
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Settings2 className="size-5" />
                            Cara Pemilihan Template
                        </CardTitle>
                        <CardDescription>
                            Sistem mengutamakan padanan jabatan + jawatan,
                            diikuti jabatan, jawatan dan akhirnya template umum.
                            Perubahan template tidak mengubah checklist kes
                            onboarding yang telah dijana.
                        </CardDescription>
                    </CardHeader>
                </Card>

                {templates.length === 0 ? (
                    <Card>
                        <CardContent className="py-16 text-center">
                            <ListChecks className="mx-auto mb-3 size-10 text-muted-foreground" />
                            <p className="font-medium">
                                Belum ada template onboarding
                            </p>
                            <p className="text-sm text-muted-foreground">
                                Tambah template umum sebagai titik permulaan.
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-5 xl:grid-cols-2">
                        {templates.map((template) => (
                            <Card key={template.id}>
                                <CardHeader>
                                    <div className="flex items-start justify-between gap-3">
                                        <div>
                                            <div className="flex flex-wrap items-center gap-2">
                                                <CardTitle>
                                                    {template.name}
                                                </CardTitle>
                                                <Badge
                                                    variant={
                                                        template.is_active
                                                            ? 'default'
                                                            : 'secondary'
                                                    }
                                                >
                                                    {template.is_active
                                                        ? 'Aktif'
                                                        : 'Tidak Aktif'}
                                                </Badge>
                                            </div>
                                            <CardDescription>
                                                {template.code} ·{' '}
                                                {template.department ??
                                                    'Semua jabatan'}{' '}
                                                ·{' '}
                                                {template.position_name ??
                                                    'Semua jawatan'}
                                            </CardDescription>
                                        </div>
                                        <div className="flex gap-1">
                                            <TemplateDialog
                                                template={template}
                                                departments={departments}
                                                positionNames={positionNames}
                                            />
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    router.patch(
                                                        `/tetapan-pengambilan/template/${template.id}/status`,
                                                        {},
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                            >
                                                <CheckCircle2 />
                                                {template.is_active
                                                    ? 'Nyahaktif'
                                                    : 'Aktifkan'}
                                            </Button>
                                        </div>
                                    </div>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    {template.description && (
                                        <p className="text-sm text-muted-foreground">
                                            {template.description}
                                        </p>
                                    )}
                                    <div className="space-y-2">
                                        {template.tasks.map((task, index) => (
                                            <div
                                                key={task.id ?? index}
                                                className="flex items-start justify-between gap-3 rounded-lg border p-3"
                                            >
                                                <div>
                                                    <p className="text-sm font-medium">
                                                        {index + 1}.{' '}
                                                        {task.title}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {categoryLabel[
                                                            task.category
                                                        ] ?? task.category}{' '}
                                                        ·{' '}
                                                        {assigneeLabel[
                                                            task.assignee_role
                                                        ] ??
                                                            task.assignee_role}{' '}
                                                        · Hari{' '}
                                                        {Number(
                                                            task.due_offset_days,
                                                        ) >= 0
                                                            ? '+'
                                                            : ''}
                                                        {task.due_offset_days}
                                                    </p>
                                                </div>
                                                {task.is_required && (
                                                    <Badge variant="outline">
                                                        Wajib
                                                    </Badge>
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}
