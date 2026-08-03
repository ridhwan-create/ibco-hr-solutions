import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, ShieldAlert } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
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
    DialogContent,
    DialogDescription,
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
import { Textarea } from '@/components/ui/textarea';

type Category = {
    id: number;
    code: string;
    name: string;
    description: string | null;
    default_severity: string;
    sla_days: number;
    appeal_days: number;
    requires_show_cause: boolean;
    allow_protected_identity: boolean;
    is_active: boolean;
    cases_count: number;
};

type Props = {
    categories: Category[];
    severityOptions: string[];
};

const severityLabel: Record<string, string> = {
    low: 'Rendah',
    medium: 'Sederhana',
    high: 'Tinggi',
    critical: 'Kritikal',
};

function CategoryForm({
    category,
    severityOptions,
    onDone,
}: {
    category?: Category;
    severityOptions: string[];
    onDone: () => void;
}) {
    const form = useForm({
        code: category?.code ?? '',
        name: category?.name ?? '',
        description: category?.description ?? '',
        default_severity: category?.default_severity ?? 'medium',
        sla_days: String(category?.sla_days ?? 30),
        appeal_days: String(category?.appeal_days ?? 14),
        requires_show_cause: category?.requires_show_cause ?? true,
        allow_protected_identity: category?.allow_protected_identity ?? true,
    });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: onDone,
        };

        if (category) {
            form.put(`/tetapan-disiplin/kategori/${category.id}`, options);
        } else {
            form.post('/tetapan-disiplin/kategori', options);
        }
    };

    return (
        <form className="grid gap-4 md:grid-cols-2" onSubmit={submit}>
            <div className="space-y-2">
                <Label>Kod</Label>
                <Input
                    value={form.data.code}
                    onChange={(event) =>
                        form.setData('code', event.target.value)
                    }
                    placeholder="CONTOH_KOD"
                />
            </div>
            <div className="space-y-2">
                <Label>Nama Kategori</Label>
                <Input
                    value={form.data.name}
                    onChange={(event) =>
                        form.setData('name', event.target.value)
                    }
                />
            </div>
            <div className="space-y-2 md:col-span-2">
                <Label>Penerangan</Label>
                <Textarea
                    rows={3}
                    value={form.data.description}
                    onChange={(event) =>
                        form.setData('description', event.target.value)
                    }
                />
            </div>
            <div className="space-y-2">
                <Label>Tahap Risiko Lalai</Label>
                <Select
                    value={form.data.default_severity}
                    onValueChange={(value) =>
                        form.setData('default_severity', value)
                    }
                >
                    <SelectTrigger>
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {severityOptions.map((severity) => (
                            <SelectItem key={severity} value={severity}>
                                {severityLabel[severity] ?? severity}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>
            <div className="grid grid-cols-2 gap-3">
                <div className="space-y-2">
                    <Label>SLA (hari)</Label>
                    <Input
                        type="number"
                        min={1}
                        max={365}
                        value={form.data.sla_days}
                        onChange={(event) =>
                            form.setData('sla_days', event.target.value)
                        }
                    />
                </div>
                <div className="space-y-2">
                    <Label>Rayuan (hari)</Label>
                    <Input
                        type="number"
                        min={1}
                        max={90}
                        value={form.data.appeal_days}
                        onChange={(event) =>
                            form.setData('appeal_days', event.target.value)
                        }
                    />
                </div>
            </div>
            <label className="flex items-start gap-3 rounded-lg border p-3">
                <Checkbox
                    checked={form.data.requires_show_cause}
                    onCheckedChange={(checked) =>
                        form.setData('requires_show_cause', Boolean(checked))
                    }
                />
                <span>
                    <span className="block text-sm font-medium">
                        Wajib proses tunjuk sebab
                    </span>
                    <span className="block text-xs text-muted-foreground">
                        Digunakan apabila dapatan terbukti atau sebahagian
                        terbukti.
                    </span>
                </span>
            </label>
            <label className="flex items-start gap-3 rounded-lg border p-3">
                <Checkbox
                    checked={form.data.allow_protected_identity}
                    onCheckedChange={(checked) =>
                        form.setData(
                            'allow_protected_identity',
                            Boolean(checked),
                        )
                    }
                />
                <span>
                    <span className="block text-sm font-medium">
                        Benarkan identiti dilindungi
                    </span>
                    <span className="block text-xs text-muted-foreground">
                        Identiti tidak dipaparkan kepada responden atau pegawai
                        tanpa keperluan tugas.
                    </span>
                </span>
            </label>
            {Object.keys(form.errors).length > 0 && (
                <p className="text-sm text-red-600 md:col-span-2">
                    {Object.values(form.errors)[0]}
                </p>
            )}
            <div className="flex justify-end md:col-span-2">
                <Button disabled={form.processing}>
                    {category ? 'Simpan Perubahan' : 'Tambah Kategori'}
                </Button>
            </div>
        </form>
    );
}

function EditCategory({
    category,
    severityOptions,
}: {
    category: Category;
    severityOptions: string[];
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    <Pencil /> Edit
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Edit Kategori Aduan</DialogTitle>
                    <DialogDescription>
                        Perubahan hanya digunakan pada aduan baharu.
                    </DialogDescription>
                </DialogHeader>
                <CategoryForm
                    category={category}
                    severityOptions={severityOptions}
                    onDone={() => setOpen(false)}
                />
            </DialogContent>
        </Dialog>
    );
}

export default function DisciplineSettings({
    categories,
    severityOptions,
}: Props) {
    const [open, setOpen] = useState(false);

    return (
        <>
            <Head title="Tetapan Disiplin" />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Tetapan Disiplin & Aduan
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Kategori, risiko lalai, SLA siasatan, tempoh rayuan
                            dan perlindungan identiti.
                        </p>
                    </div>
                    <Dialog open={open} onOpenChange={setOpen}>
                        <DialogTrigger asChild>
                            <Button>
                                <Plus /> Kategori Baharu
                            </Button>
                        </DialogTrigger>
                        <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                            <DialogHeader>
                                <DialogTitle>Kategori Aduan Baharu</DialogTitle>
                                <DialogDescription>
                                    Tetapkan kawalan proses mengikut jenis
                                    aduan.
                                </DialogDescription>
                            </DialogHeader>
                            <CategoryForm
                                severityOptions={severityOptions}
                                onDone={() => setOpen(false)}
                            />
                        </DialogContent>
                    </Dialog>
                </div>

                <div className="grid gap-4 xl:grid-cols-2">
                    {categories.map((category) => (
                        <Card key={category.id}>
                            <CardHeader>
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <CardTitle className="flex items-center gap-2 text-base">
                                            <ShieldAlert className="size-4 text-red-600" />
                                            {category.name}
                                        </CardTitle>
                                        <CardDescription>
                                            {category.code} ·{' '}
                                            {category.cases_count} kes
                                        </CardDescription>
                                    </div>
                                    <Badge
                                        variant={
                                            category.is_active
                                                ? 'default'
                                                : 'secondary'
                                        }
                                    >
                                        {category.is_active
                                            ? 'Aktif'
                                            : 'Tidak Aktif'}
                                    </Badge>
                                </div>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <p className="text-sm text-muted-foreground">
                                    {category.description ||
                                        'Tiada penerangan.'}
                                </p>
                                <dl className="grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                                    <div>
                                        <dt className="text-muted-foreground">
                                            Risiko
                                        </dt>
                                        <dd className="font-medium">
                                            {
                                                severityLabel[
                                                    category.default_severity
                                                ]
                                            }
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-muted-foreground">
                                            SLA
                                        </dt>
                                        <dd className="font-medium">
                                            {category.sla_days} hari
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-muted-foreground">
                                            Rayuan
                                        </dt>
                                        <dd className="font-medium">
                                            {category.appeal_days} hari
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-muted-foreground">
                                            Tunjuk Sebab
                                        </dt>
                                        <dd className="font-medium">
                                            {category.requires_show_cause
                                                ? 'Wajib'
                                                : 'Ikut kes'}
                                        </dd>
                                    </div>
                                </dl>
                                <div className="flex flex-wrap gap-2">
                                    <EditCategory
                                        category={category}
                                        severityOptions={severityOptions}
                                    />
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() => {
                                            if (
                                                window.confirm(
                                                    `${category.is_active ? 'Nyahaktifkan' : 'Aktifkan'} kategori ${category.name}?`,
                                                )
                                            ) {
                                                router.patch(
                                                    `/tetapan-disiplin/kategori/${category.id}/status`,
                                                    {},
                                                    { preserveScroll: true },
                                                );
                                            }
                                        }}
                                    >
                                        {category.is_active
                                            ? 'Nyahaktifkan'
                                            : 'Aktifkan'}
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            </div>
        </>
    );
}

DisciplineSettings.layout = {
    breadcrumbs: [{ title: 'Tetapan Disiplin', href: '/tetapan-disiplin' }],
};
