import { Head, router, useForm } from '@inertiajs/react';
import {
    Braces,
    FileCog,
    FilePlus2,
    Hash,
    Pencil,
    Power,
    ShieldCheck,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
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

type Approver = { id: number; name: string; email: string };

type Template = {
    id: number;
    code: string;
    name: string;
    category: string;
    subject_template: string;
    body_template: string;
    available_variables: string[] | null;
    sequence_key: string;
    requires_approval: boolean;
    approver_user_id: number | null;
    approver: Approver | null;
    acknowledgement_required: boolean;
    default_validity_months: number | null;
    confidentiality: string;
    is_active: boolean;
    documents_count: number;
};

type Sequence = {
    id: number;
    sequence_key: string;
    name: string;
    prefix: string;
    format: string;
    next_number: number;
    last_year: number | null;
    reset_annually: boolean;
    is_active: boolean;
    preview: string;
};

type Props = {
    templates: Template[];
    sequences: Sequence[];
    approvers: Approver[];
    categories: string[];
    confidentialityLevels: string[];
    systemVariables: string[];
};

const categoryLabel: Record<string, string> = {
    contract: 'Kontrak',
    confirmation: 'Pengesahan Jawatan',
    salary_revision: 'Pelarasan Gaji',
    promotion: 'Kenaikan Pangkat',
    transfer: 'Pertukaran',
    warning: 'Amaran',
    show_cause: 'Tunjuk Sebab',
    memo: 'Memo',
    termination: 'Penamatan',
    resignation: 'Berhenti Kerja',
    custom: 'Lain-lain',
};

const confidentialityLabel: Record<string, string> = {
    internal: 'Dalaman',
    confidential: 'Sulit',
    restricted: 'Terhad',
};

function Field({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="space-y-2">
            <Label>{label}</Label>
            {children}
        </div>
    );
}

function CheckField({
    label,
    checked,
    set,
}: {
    label: string;
    checked: boolean;
    set: (checked: boolean) => void;
}) {
    return (
        <label className="flex items-center gap-2 rounded-md border p-3 text-sm">
            <Checkbox
                checked={checked}
                onCheckedChange={(value) => set(value === true)}
            />
            {label}
        </label>
    );
}

function TemplateDialog({
    template,
    sequences,
    approvers,
    categories,
    confidentialityLevels,
}: {
    template?: Template;
    sequences: Sequence[];
    approvers: Approver[];
    categories: string[];
    confidentialityLevels: string[];
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        code: template?.code ?? '',
        name: template?.name ?? '',
        category: template?.category ?? 'contract',
        subject_template: template?.subject_template ?? '',
        body_template: template?.body_template ?? '',
        available_variables_input:
            template?.available_variables?.join(', ') ?? '',
        sequence_key:
            template?.sequence_key ?? sequences[0]?.sequence_key ?? 'DEFAULT',
        requires_approval: template?.requires_approval ?? true,
        approver_user_id: template?.approver_user_id
            ? String(template.approver_user_id)
            : '',
        acknowledgement_required: template?.acknowledgement_required ?? false,
        default_validity_months: template?.default_validity_months
            ? String(template.default_validity_months)
            : '',
        confidentiality: template?.confidentiality ?? 'confidential',
        is_active: template?.is_active ?? true,
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            available_variables: data.available_variables_input
                .split(',')
                .map((value) => value.trim())
                .filter(Boolean),
            approver_user_id: data.approver_user_id || null,
            default_validity_months: data.default_validity_months || null,
        }));
        const options = {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        };

        if (template) {
            form.put(`/tetapan-dokumen/template/${template.id}`, options);
        } else {
            form.post('/tetapan-dokumen/template', options);
        }
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                {template ? (
                    <Button size="sm" variant="outline">
                        <Pencil /> Edit
                    </Button>
                ) : (
                    <Button disabled={sequences.length === 0}>
                        <FilePlus2 /> Template
                    </Button>
                )}
            </DialogTrigger>
            <DialogContent className="max-h-[92vh] overflow-y-auto sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>
                        {template ? 'Kemas Kini Template' : 'Template Surat HR'}
                    </DialogTitle>
                    <DialogDescription>
                        Gunakan pemboleh ubah dalam format {'{{employee_name}}'}{' '}
                        untuk kandungan dinamik.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Kod Template">
                            <Input
                                value={form.data.code}
                                onChange={(event) =>
                                    form.setData(
                                        'code',
                                        event.target.value.toUpperCase(),
                                    )
                                }
                            />
                            <InputError message={form.errors.code} />
                        </Field>
                        <Field label="Nama Template">
                            <Input
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                            />
                        </Field>
                        <Field label="Kategori">
                            <Select
                                value={form.data.category}
                                onValueChange={(value) =>
                                    form.setData('category', value)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {categories.map((category) => (
                                        <SelectItem
                                            key={category}
                                            value={category}
                                        >
                                            {categoryLabel[category] ??
                                                category}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </Field>
                        <Field label="Siri Nombor Rujukan">
                            <Select
                                value={form.data.sequence_key}
                                onValueChange={(value) =>
                                    form.setData('sequence_key', value)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {sequences.map((sequence) => (
                                        <SelectItem
                                            key={sequence.id}
                                            value={sequence.sequence_key}
                                        >
                                            {sequence.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </Field>
                    </div>
                    <Field label="Subjek Template">
                        <Input
                            value={form.data.subject_template}
                            onChange={(event) =>
                                form.setData(
                                    'subject_template',
                                    event.target.value,
                                )
                            }
                        />
                    </Field>
                    <Field label="Kandungan Template">
                        <textarea
                            className="min-h-56 w-full rounded-md border bg-background px-3 py-2 text-sm"
                            value={form.data.body_template}
                            onChange={(event) =>
                                form.setData(
                                    'body_template',
                                    event.target.value,
                                )
                            }
                        />
                        <InputError message={form.errors.body_template} />
                    </Field>
                    <Field label="Pemboleh Ubah Tambahan (pisahkan dengan koma)">
                        <Input
                            value={form.data.available_variables_input}
                            onChange={(event) =>
                                form.setData(
                                    'available_variables_input',
                                    event.target.value,
                                )
                            }
                        />
                    </Field>
                    <div className="grid gap-4 sm:grid-cols-3">
                        <Field label="Pelulus Khusus">
                            <Select
                                value={form.data.approver_user_id || 'default'}
                                onValueChange={(value) =>
                                    form.setData(
                                        'approver_user_id',
                                        value === 'default' ? '' : value,
                                    )
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="default">
                                        Pelulus Default
                                    </SelectItem>
                                    {approvers.map((approver) => (
                                        <SelectItem
                                            key={approver.id}
                                            value={String(approver.id)}
                                        >
                                            {approver.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </Field>
                        <Field label="Tempoh Sah (bulan)">
                            <Input
                                type="number"
                                min="1"
                                max="1200"
                                value={form.data.default_validity_months}
                                onChange={(event) =>
                                    form.setData(
                                        'default_validity_months',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                        <Field label="Kerahsiaan">
                            <Select
                                value={form.data.confidentiality}
                                onValueChange={(value) =>
                                    form.setData('confidentiality', value)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {confidentialityLevels.map((level) => (
                                        <SelectItem key={level} value={level}>
                                            {confidentialityLabel[level] ??
                                                level}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </Field>
                    </div>
                    <div className="grid gap-3 sm:grid-cols-3">
                        <CheckField
                            label="Perlu kelulusan"
                            checked={form.data.requires_approval}
                            set={(checked) =>
                                form.setData('requires_approval', checked)
                            }
                        />
                        <CheckField
                            label="Perlu perakuan pekerja"
                            checked={form.data.acknowledgement_required}
                            set={(checked) =>
                                form.setData(
                                    'acknowledgement_required',
                                    checked,
                                )
                            }
                        />
                        <CheckField
                            label="Template aktif"
                            checked={form.data.is_active}
                            set={(checked) =>
                                form.setData('is_active', checked)
                            }
                        />
                    </div>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Tutup
                            </Button>
                        </DialogClose>
                        <Button disabled={form.processing}>Simpan</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function SequenceDialog({ sequence }: { sequence?: Sequence }) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        sequence_key: sequence?.sequence_key ?? '',
        name: sequence?.name ?? '',
        prefix: sequence?.prefix ?? 'IBCO/HR',
        format: sequence?.format ?? '{{PREFIX}}/{{YEAR}}/{{SEQ:05}}',
        next_number: String(sequence?.next_number ?? 1),
        reset_annually: sequence?.reset_annually ?? true,
        is_active: sequence?.is_active ?? true,
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post('/tetapan-dokumen/siri', {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                {sequence ? (
                    <Button size="sm" variant="outline">
                        <Pencil /> Edit
                    </Button>
                ) : (
                    <Button variant="outline">
                        <Hash /> Siri Rujukan
                    </Button>
                )}
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Siri Nombor Rujukan</DialogTitle>
                    <DialogDescription>
                        Token disokong: {'{{PREFIX}}'}, {'{{YEAR}}'}, {'{{YY}}'}
                        dan {'{{SEQ:05}}'}.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Kunci Siri">
                            <Input
                                disabled={Boolean(sequence)}
                                value={form.data.sequence_key}
                                onChange={(event) =>
                                    form.setData(
                                        'sequence_key',
                                        event.target.value.toUpperCase(),
                                    )
                                }
                            />
                            <InputError message={form.errors.sequence_key} />
                        </Field>
                        <Field label="Nama">
                            <Input
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                            />
                        </Field>
                        <Field label="Awalan">
                            <Input
                                value={form.data.prefix}
                                onChange={(event) =>
                                    form.setData('prefix', event.target.value)
                                }
                            />
                        </Field>
                        <Field label="Nombor Seterusnya">
                            <Input
                                type="number"
                                min="1"
                                value={form.data.next_number}
                                onChange={(event) =>
                                    form.setData(
                                        'next_number',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                    </div>
                    <Field label="Format">
                        <Input
                            value={form.data.format}
                            onChange={(event) =>
                                form.setData('format', event.target.value)
                            }
                        />
                        <InputError message={form.errors.format} />
                    </Field>
                    <div className="grid gap-3 sm:grid-cols-2">
                        <CheckField
                            label="Reset setiap tahun"
                            checked={form.data.reset_annually}
                            set={(checked) =>
                                form.setData('reset_annually', checked)
                            }
                        />
                        <CheckField
                            label="Siri aktif"
                            checked={form.data.is_active}
                            set={(checked) =>
                                form.setData('is_active', checked)
                            }
                        />
                    </div>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Tutup
                            </Button>
                        </DialogClose>
                        <Button disabled={form.processing}>Simpan</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function DocumentSettings({
    templates,
    sequences,
    approvers,
    categories,
    confidentialityLevels,
    systemVariables,
}: Props) {
    return (
        <>
            <Head title="Tetapan Dokumen" />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Tetapan Dokumen & Surat HR
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Urus template, pemboleh ubah, pelulus dan siri
                            nombor rujukan rasmi.
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <SequenceDialog />
                        <TemplateDialog
                            sequences={sequences}
                            approvers={approvers}
                            categories={categories}
                            confidentialityLevels={confidentialityLevels}
                        />
                    </div>
                </div>

                {sequences.length === 0 && (
                    <Card className="border-amber-300 dark:border-amber-800">
                        <CardContent className="flex items-center gap-3 p-4 text-sm">
                            <Hash className="size-5 text-amber-600" />
                            Cipta sekurang-kurangnya satu siri nombor rujukan
                            sebelum menambah template.
                        </CardContent>
                    </Card>
                )}

                <div className="grid gap-4 lg:grid-cols-3">
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <FileCog className="size-4" /> Template Surat
                            </CardTitle>
                            <CardDescription>
                                Kandungan aktif boleh digunakan untuk menjana
                                draf baharu.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Template</TableHead>
                                        <TableHead>Kategori</TableHead>
                                        <TableHead>Kelulusan</TableHead>
                                        <TableHead>Digunakan</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="text-right">
                                            Tindakan
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {templates.map((template) => (
                                        <TableRow key={template.id}>
                                            <TableCell>
                                                <div className="font-medium">
                                                    {template.name}
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    {template.code} ·{' '}
                                                    {template.sequence_key}
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                {categoryLabel[
                                                    template.category
                                                ] ?? template.category}
                                            </TableCell>
                                            <TableCell>
                                                {template.requires_approval
                                                    ? (template.approver
                                                          ?.name ??
                                                      'Pelulus Default')
                                                    : 'Tidak diperlukan'}
                                            </TableCell>
                                            <TableCell>
                                                {template.documents_count}
                                            </TableCell>
                                            <TableCell>
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
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex justify-end gap-2">
                                                    <TemplateDialog
                                                        template={template}
                                                        sequences={sequences}
                                                        approvers={approvers}
                                                        categories={categories}
                                                        confidentialityLevels={
                                                            confidentialityLevels
                                                        }
                                                    />
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        onClick={() =>
                                                            router.patch(
                                                                `/tetapan-dokumen/template/${template.id}/status`,
                                                                {},
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            )
                                                        }
                                                    >
                                                        <Power />
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Braces className="size-4" /> Pemboleh Ubah
                            </CardTitle>
                            <CardDescription>
                                Salin token ini ke subjek atau kandungan.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-wrap gap-2">
                            {systemVariables.map((variable) => (
                                <code
                                    key={variable}
                                    className="rounded bg-muted px-2 py-1 text-xs"
                                >
                                    {`{{${variable}}}`}
                                </code>
                            ))}
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <Hash className="size-4" /> Siri Nombor Rujukan
                        </CardTitle>
                        <CardDescription>
                            Nombor diperuntukkan hanya apabila dokumen
                            dikeluarkan.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        {sequences.map((sequence) => (
                            <div
                                key={sequence.id}
                                className="rounded-lg border p-4"
                            >
                                <div className="flex items-start justify-between gap-2">
                                    <div>
                                        <div className="font-medium">
                                            {sequence.name}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {sequence.sequence_key}
                                        </div>
                                    </div>
                                    <Badge
                                        variant={
                                            sequence.is_active
                                                ? 'default'
                                                : 'secondary'
                                        }
                                    >
                                        {sequence.is_active
                                            ? 'Aktif'
                                            : 'Tidak Aktif'}
                                    </Badge>
                                </div>
                                <div className="my-3 rounded bg-muted p-2 font-mono text-sm">
                                    {sequence.preview}
                                </div>
                                <div className="mb-3 flex items-center gap-2 text-xs text-muted-foreground">
                                    <ShieldCheck className="size-3.5" /> Nombor
                                    seterusnya: {sequence.next_number}
                                </div>
                                <SequenceDialog sequence={sequence} />
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

DocumentSettings.layout = {
    breadcrumbs: [{ title: 'Tetapan Dokumen', href: '/tetapan-dokumen' }],
};
