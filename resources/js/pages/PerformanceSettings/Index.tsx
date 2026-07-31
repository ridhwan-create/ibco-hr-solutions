import { Head, router, useForm } from '@inertiajs/react';
import {
    CalendarRange,
    CheckCircle2,
    Pencil,
    Plus,
    Save,
    Settings2,
    ShieldCheck,
    Target,
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

type Rating = { label: string; minimum: string | number };
type Cycle = {
    id: number;
    code: string;
    name: string;
    cycle_type: 'annual' | 'half_year' | 'probation';
    period_start: string;
    period_end: string;
    self_assessment_due_at: string;
    supervisor_due_at: string;
    moderation_due_at: string;
    status: 'draft' | 'open' | 'in_review' | 'finalized';
    rating_scale: Rating[];
    reviews_count: number;
};
type TemplateItem = {
    id?: number;
    title: string;
    description: string;
    measure_type: 'quantitative' | 'qualitative';
    target_value: string | number | null;
    unit: string;
    weight: string | number;
    scoring_guide: string;
};
type KpiTemplate = {
    id: number;
    code: string;
    name: string;
    department_id: number | null;
    department: string | null;
    position_name: string | null;
    description: string | null;
    is_active: boolean;
    total_weight: number;
    items: TemplateItem[];
};
type Props = {
    cycles: Cycle[];
    templates: KpiTemplate[];
    departments: { id: number; name: string }[];
    positionNames: string[];
    supervisors: { id: number; name: string; email: string }[];
    assignments: {
        id: number;
        department_id: number;
        department: string;
        supervisor_user_id: number;
        supervisor_name: string | null;
        supervisor_email: string | null;
        is_active: boolean;
    }[];
};

const defaultRatings: Rating[] = [
    { label: 'Sangat Cemerlang', minimum: '4.5' },
    { label: 'Cemerlang', minimum: '4.0' },
    { label: 'Baik', minimum: '3.0' },
    { label: 'Perlu Peningkatan', minimum: '2.0' },
    { label: 'Tidak Memuaskan', minimum: '1.0' },
];
const emptyItem = (): TemplateItem => ({
    title: '',
    description: '',
    measure_type: 'quantitative',
    target_value: '',
    unit: '',
    weight: '',
    scoring_guide: '',
});
const cycleTypeLabel = {
    annual: 'Tahunan',
    half_year: 'Setengah Tahun',
    probation: 'Tempoh Percubaan',
};
const cycleStatusLabel = {
    draft: 'Draf',
    open: 'Dibuka',
    in_review: 'Dalam Semakan',
    finalized: 'Dimuktamadkan',
};

function CycleDialog({ cycle }: { cycle?: Cycle }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, put, processing, errors, reset } = useForm({
        code: cycle?.code ?? '',
        name: cycle?.name ?? '',
        cycle_type: cycle?.cycle_type ?? ('annual' as const),
        period_start: cycle?.period_start ?? '',
        period_end: cycle?.period_end ?? '',
        self_assessment_due_at: cycle?.self_assessment_due_at ?? '',
        supervisor_due_at: cycle?.supervisor_due_at ?? '',
        moderation_due_at: cycle?.moderation_due_at ?? '',
        rating_scale: (cycle?.rating_scale ?? defaultRatings).map((rating) => ({
            label: rating.label,
            minimum: String(rating.minimum),
        })),
    });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);

                if (!cycle) {
                    reset();
                }
            },
        };

        if (cycle) {
            put(`/tetapan-prestasi/kitaran/${cycle.id}`, options);
        } else {
            post('/tetapan-prestasi/kitaran', options);
        }
    };
    const setRating = (index: number, field: keyof Rating, value: string) => {
        setData(
            'rating_scale',
            data.rating_scale.map((rating, ratingIndex) =>
                ratingIndex === index ? { ...rating, [field]: value } : rating,
            ),
        );
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant={cycle ? 'outline' : 'default'}>
                    {cycle ? <Pencil /> : <Plus />}
                    {cycle ? 'Edit' : 'Tambah Kitaran'}
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>
                        {cycle ? 'Edit Kitaran Penilaian' : 'Kitaran Baharu'}
                    </DialogTitle>
                    <DialogDescription>
                        Tetapkan tempoh, tarikh akhir setiap fasa dan skala
                        rating 1 hingga 5.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-5">
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
                            <Label>Nama Kitaran</Label>
                            <Input
                                value={data.name}
                                onChange={(event) =>
                                    setData('name', event.target.value)
                                }
                            />
                            <InputError message={errors.name} />
                        </div>
                        <div className="space-y-2 sm:col-span-2">
                            <Label>Jenis Kitaran</Label>
                            <Select
                                value={data.cycle_type}
                                onValueChange={(
                                    value: 'annual' | 'half_year' | 'probation',
                                ) => setData('cycle_type', value)}
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="annual">
                                        Tahunan
                                    </SelectItem>
                                    <SelectItem value="half_year">
                                        Setengah Tahun
                                    </SelectItem>
                                    <SelectItem value="probation">
                                        Tempoh Percubaan
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        {[
                            ['period_start', 'Tarikh Mula'],
                            ['period_end', 'Tarikh Tamat'],
                            [
                                'self_assessment_due_at',
                                'Tarikh Akhir Self-Assessment',
                            ],
                            ['supervisor_due_at', 'Tarikh Akhir Penyelia'],
                            ['moderation_due_at', 'Tarikh Akhir Moderasi HR'],
                        ].map(([field, label]) => (
                            <div className="space-y-2" key={field}>
                                <Label>{label}</Label>
                                <Input
                                    type="date"
                                    value={
                                        data[
                                            field as keyof typeof data
                                        ] as string
                                    }
                                    onChange={(event) =>
                                        setData(
                                            field as
                                                | 'period_start'
                                                | 'period_end'
                                                | 'self_assessment_due_at'
                                                | 'supervisor_due_at'
                                                | 'moderation_due_at',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={
                                        errors[field as keyof typeof errors]
                                    }
                                />
                            </div>
                        ))}
                    </div>
                    <div className="space-y-3 rounded-lg border p-4">
                        <div>
                            <Label>Skala Rating</Label>
                            <p className="text-xs text-muted-foreground">
                                Sistem memilih rating pertama yang mempunyai
                                nilai minimum sama atau lebih rendah daripada
                                skor akhir.
                            </p>
                        </div>
                        {data.rating_scale.map((rating, index) => (
                            <div
                                key={index}
                                className="grid gap-2 sm:grid-cols-[1fr_9rem]"
                            >
                                <Input
                                    value={rating.label}
                                    onChange={(event) =>
                                        setRating(
                                            index,
                                            'label',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Nama rating"
                                />
                                <Input
                                    type="number"
                                    min="1"
                                    max="5"
                                    step="0.01"
                                    value={rating.minimum}
                                    onChange={(event) =>
                                        setRating(
                                            index,
                                            'minimum',
                                            event.target.value,
                                        )
                                    }
                                />
                            </div>
                        ))}
                        <InputError
                            message={
                                (errors as Record<string, string>).rating_scale
                            }
                        />
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

function TemplateDialog({
    template,
    departments,
    positionNames,
}: {
    template?: KpiTemplate;
    departments: Props['departments'];
    positionNames: string[];
}) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, put, processing, errors, reset } = useForm({
        code: template?.code ?? '',
        name: template?.name ?? '',
        department_id: template?.department_id
            ? String(template.department_id)
            : '',
        position_name: template?.position_name ?? '',
        description: template?.description ?? '',
        is_active: template?.is_active ?? true,
        items: template?.items.map((item) => ({
            ...item,
            target_value:
                item.target_value === null ? '' : String(item.target_value),
            weight: String(item.weight),
        })) ?? [emptyItem()],
    });
    const totalWeight = data.items.reduce(
        (sum, item) => sum + (Number(item.weight) || 0),
        0,
    );
    const updateItem = (
        index: number,
        field: keyof TemplateItem,
        value: string,
    ) => {
        setData(
            'items',
            data.items.map((item, itemIndex) =>
                itemIndex === index ? { ...item, [field]: value } : item,
            ),
        );
    };
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
            put(`/tetapan-prestasi/template/${template.id}`, options);
        } else {
            post('/tetapan-prestasi/template', options);
        }
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant={template ? 'outline' : 'default'}>
                    {template ? <Pencil /> : <Plus />}
                    {template ? 'Edit' : 'Tambah Template'}
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[92vh] overflow-y-auto sm:max-w-5xl">
                <DialogHeader>
                    <DialogTitle>
                        {template ? 'Edit Template KPI' : 'Template KPI Baharu'}
                    </DialogTitle>
                    <DialogDescription>
                        Template paling khusus mengikut jawatan dan jabatan akan
                        dipilih semasa penjanaan pukal.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-5">
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
                            <Label>Nama Template</Label>
                            <Input
                                value={data.name}
                                onChange={(event) =>
                                    setData('name', event.target.value)
                                }
                            />
                            <InputError message={errors.name} />
                        </div>
                        <div className="space-y-2">
                            <Label>Jabatan (pilihan)</Label>
                            <Select
                                value={data.department_id || 'all'}
                                onValueChange={(value) =>
                                    setData(
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
                                        Semua Jabatan
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
                                value={data.position_name || 'all'}
                                onValueChange={(value) =>
                                    setData(
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
                                        Semua Jawatan
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
                                className="min-h-20 w-full rounded-md border bg-background px-3 py-2 text-sm"
                                value={data.description}
                                onChange={(event) =>
                                    setData('description', event.target.value)
                                }
                            />
                        </div>
                    </div>

                    <div className="space-y-3">
                        <div className="flex items-center justify-between gap-3">
                            <div>
                                <Label>Sasaran / KPI</Label>
                                <p className="text-xs text-muted-foreground">
                                    Jumlah pemberat wajib tepat 100%.
                                </p>
                            </div>
                            <Badge
                                variant={
                                    Math.abs(totalWeight - 100) < 0.01
                                        ? 'default'
                                        : 'destructive'
                                }
                            >
                                {totalWeight.toFixed(2)}%
                            </Badge>
                        </div>
                        {data.items.map((item, index) => (
                            <div
                                key={index}
                                className="space-y-3 rounded-lg border p-4"
                            >
                                <div className="flex items-center justify-between">
                                    <p className="font-medium">
                                        KPI {index + 1}
                                    </p>
                                    {data.items.length > 1 && (
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            onClick={() =>
                                                setData(
                                                    'items',
                                                    data.items.filter(
                                                        (_, itemIndex) =>
                                                            itemIndex !== index,
                                                    ),
                                                )
                                            }
                                        >
                                            <Trash2 />
                                            Buang
                                        </Button>
                                    )}
                                </div>
                                <div className="grid gap-3 sm:grid-cols-2">
                                    <div className="space-y-2 sm:col-span-2">
                                        <Label>Tajuk Sasaran</Label>
                                        <Input
                                            value={item.title}
                                            onChange={(event) =>
                                                updateItem(
                                                    index,
                                                    'title',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Jenis Ukuran</Label>
                                        <Select
                                            value={item.measure_type}
                                            onValueChange={(
                                                value:
                                                    | 'quantitative'
                                                    | 'qualitative',
                                            ) =>
                                                updateItem(
                                                    index,
                                                    'measure_type',
                                                    value,
                                                )
                                            }
                                        >
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="quantitative">
                                                    Kuantitatif
                                                </SelectItem>
                                                <SelectItem value="qualitative">
                                                    Kualitatif
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Pemberat (%)</Label>
                                        <Input
                                            type="number"
                                            min="0.01"
                                            max="100"
                                            step="0.01"
                                            value={item.weight}
                                            onChange={(event) =>
                                                updateItem(
                                                    index,
                                                    'weight',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Nilai Sasaran</Label>
                                        <Input
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            value={item.target_value ?? ''}
                                            onChange={(event) =>
                                                updateItem(
                                                    index,
                                                    'target_value',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Unit</Label>
                                        <Input
                                            value={item.unit}
                                            onChange={(event) =>
                                                updateItem(
                                                    index,
                                                    'unit',
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="%, kes, hari, projek"
                                        />
                                    </div>
                                    <div className="space-y-2 sm:col-span-2">
                                        <Label>Penerangan Sasaran</Label>
                                        <textarea
                                            className="min-h-16 w-full rounded-md border bg-background px-3 py-2 text-sm"
                                            value={item.description}
                                            onChange={(event) =>
                                                updateItem(
                                                    index,
                                                    'description',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                    <div className="space-y-2 sm:col-span-2">
                                        <Label>Panduan Skor</Label>
                                        <textarea
                                            className="min-h-16 w-full rounded-md border bg-background px-3 py-2 text-sm"
                                            value={item.scoring_guide}
                                            onChange={(event) =>
                                                updateItem(
                                                    index,
                                                    'scoring_guide',
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="Contoh: 5 = ≥110%, 4 = 100–109%, 3 = 90–99%..."
                                        />
                                    </div>
                                </div>
                            </div>
                        ))}
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() =>
                                setData('items', [...data.items, emptyItem()])
                            }
                        >
                            <Plus />
                            Tambah KPI
                        </Button>
                        <InputError
                            message={(errors as Record<string, string>).items}
                        />
                    </div>

                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox
                            checked={data.is_active}
                            onCheckedChange={(checked) =>
                                setData('is_active', checked === true)
                            }
                        />
                        Template aktif
                    </label>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Tutup
                            </Button>
                        </DialogClose>
                        <Button
                            type="submit"
                            disabled={
                                processing ||
                                Math.abs(totalWeight - 100) >= 0.01
                            }
                        >
                            <Save />
                            Simpan Template
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function PerformanceSettings({
    cycles,
    templates,
    departments,
    positionNames,
    supervisors,
    assignments,
}: Props) {
    const assignmentForm = useForm({
        department_id: '',
        supervisor_user_id: '',
        is_active: true,
    });
    const saveAssignment = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        assignmentForm.post('/tetapan-prestasi/penyelia', {
            preserveScroll: true,
            onSuccess: () => assignmentForm.reset(),
        });
    };
    const changeCycleStatus = (cycle: Cycle) => {
        const next =
            cycle.status === 'draft'
                ? 'open'
                : cycle.status === 'open'
                  ? 'in_review'
                  : 'finalized';
        const message =
            next === 'open'
                ? 'Buka kitaran ini untuk Self-Assessment?'
                : next === 'in_review'
                  ? 'Tutup Self-Assessment dan pindahkan kitaran ke fasa semakan?'
                  : 'Muktamadkan keseluruhan kitaran? Semua penilaian pekerja mesti selesai.';

        if (window.confirm(message)) {
            router.patch(
                `/tetapan-prestasi/kitaran/${cycle.id}/status`,
                { status: next },
                { preserveScroll: true },
            );
        }
    };

    return (
        <>
            <Head title="Tetapan Prestasi" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="flex items-center gap-2 text-2xl font-semibold">
                        <Settings2 className="size-6 text-emerald-600" />
                        Tetapan Prestasi & KPI
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Urus kitaran, template KPI, skala rating dan penyelia
                        jabatan.
                    </p>
                </div>

                <Card>
                    <CardHeader className="flex-row items-start justify-between gap-4">
                        <div>
                            <CardTitle className="flex items-center gap-2">
                                <CalendarRange className="size-5 text-emerald-600" />
                                Kitaran Penilaian
                            </CardTitle>
                            <CardDescription>
                                Status bergerak Draf → Dibuka → Dalam Semakan →
                                Dimuktamadkan.
                            </CardDescription>
                        </div>
                        <CycleDialog />
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Kitaran</TableHead>
                                    <TableHead>Tempoh & Tarikh Akhir</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Penilaian</TableHead>
                                    <TableHead className="text-right">
                                        Tindakan
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {cycles.map((cycle) => (
                                    <TableRow key={cycle.id}>
                                        <TableCell>
                                            <p className="font-medium">
                                                {cycle.name}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {cycle.code} ·{' '}
                                                {
                                                    cycleTypeLabel[
                                                        cycle.cycle_type
                                                    ]
                                                }
                                            </p>
                                        </TableCell>
                                        <TableCell className="text-xs">
                                            <p>
                                                {cycle.period_start} –{' '}
                                                {cycle.period_end}
                                            </p>
                                            <p className="text-muted-foreground">
                                                Kendiri{' '}
                                                {cycle.self_assessment_due_at} ·
                                                Penyelia{' '}
                                                {cycle.supervisor_due_at} · HR{' '}
                                                {cycle.moderation_due_at}
                                            </p>
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant="outline">
                                                {cycleStatusLabel[cycle.status]}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            {cycle.reviews_count}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex justify-end gap-2">
                                                {cycle.status !==
                                                    'finalized' && (
                                                    <CycleDialog
                                                        cycle={cycle}
                                                    />
                                                )}
                                                {cycle.status !==
                                                    'finalized' && (
                                                    <Button
                                                        size="sm"
                                                        onClick={() =>
                                                            changeCycleStatus(
                                                                cycle,
                                                            )
                                                        }
                                                    >
                                                        <CheckCircle2 />
                                                        {cycle.status ===
                                                        'draft'
                                                            ? 'Buka'
                                                            : cycle.status ===
                                                                'open'
                                                              ? 'Semakan'
                                                              : 'Muktamad'}
                                                    </Button>
                                                )}
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {cycles.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={5}
                                            className="py-8 text-center text-muted-foreground"
                                        >
                                            Belum ada kitaran penilaian.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="flex-row items-start justify-between gap-4">
                        <div>
                            <CardTitle className="flex items-center gap-2">
                                <Target className="size-5 text-emerald-600" />
                                Template KPI
                            </CardTitle>
                            <CardDescription>
                                Sasaran disalin sebagai snapshot apabila
                                penilaian dijana.
                            </CardDescription>
                        </div>
                        <TemplateDialog
                            departments={departments}
                            positionNames={positionNames}
                        />
                    </CardHeader>
                    <CardContent className="grid gap-4 lg:grid-cols-2">
                        {templates.map((template) => (
                            <div
                                key={template.id}
                                className="rounded-xl border p-4"
                            >
                                <div className="flex items-start justify-between gap-4">
                                    <div>
                                        <p className="font-semibold">
                                            {template.name}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {template.code} ·{' '}
                                            {template.department ??
                                                'Semua Jabatan'}{' '}
                                            ·{' '}
                                            {template.position_name ??
                                                'Semua Jawatan'}
                                        </p>
                                    </div>
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
                                <div className="mt-3 space-y-2">
                                    {template.items.map((item, index) => (
                                        <div
                                            key={index}
                                            className="flex items-start justify-between gap-4 rounded-md bg-muted/40 p-2 text-sm"
                                        >
                                            <span>
                                                {index + 1}. {item.title}
                                            </span>
                                            <strong>
                                                {Number(item.weight).toFixed(0)}
                                                %
                                            </strong>
                                        </div>
                                    ))}
                                </div>
                                <div className="mt-4 flex flex-wrap justify-end gap-2">
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
                                                `/tetapan-prestasi/template/${template.id}/status`,
                                                {},
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
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
                        <CardTitle className="flex items-center gap-2">
                            <ShieldCheck className="size-5 text-emerald-600" />
                            Penyelia Penilaian Jabatan
                        </CardTitle>
                        <CardDescription>
                            Digunakan semasa penjanaan pukal dan untuk kawalan
                            akses penilaian penyelia.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-5">
                        <form
                            onSubmit={saveAssignment}
                            className="grid gap-3 rounded-lg border p-4 md:grid-cols-[1fr_1fr_auto]"
                        >
                            <div className="space-y-2">
                                <Label>Jabatan</Label>
                                <Select
                                    value={
                                        assignmentForm.data.department_id ||
                                        undefined
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
                                                value={String(department.id)}
                                            >
                                                {department.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="space-y-2">
                                <Label>Penyelia</Label>
                                <Select
                                    value={
                                        assignmentForm.data
                                            .supervisor_user_id || undefined
                                    }
                                    onValueChange={(value) =>
                                        assignmentForm.setData(
                                            'supervisor_user_id',
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
                                                {supervisor.name} ·{' '}
                                                {supervisor.email}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <Button
                                className="self-end"
                                disabled={assignmentForm.processing}
                            >
                                <Save />
                                Simpan
                            </Button>
                        </form>
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
                                        <TableCell>
                                            {assignment.department}
                                        </TableCell>
                                        <TableCell>
                                            <p>{assignment.supervisor_name}</p>
                                            <p className="text-xs text-muted-foreground">
                                                {assignment.supervisor_email}
                                            </p>
                                        </TableCell>
                                        <TableCell>
                                            {assignment.is_active
                                                ? 'Aktif'
                                                : 'Tidak Aktif'}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                onClick={() => {
                                                    if (
                                                        window.confirm(
                                                            'Buang penetapan penyelia ini?',
                                                        )
                                                    ) {
                                                        router.delete(
                                                            `/tetapan-prestasi/penyelia/${assignment.id}`,
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
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
