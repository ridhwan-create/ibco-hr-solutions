import { Head, router, useForm } from '@inertiajs/react';
import { ClipboardCheck, Pencil, Plus, Trash2 } from 'lucide-react';
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
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';

type UserOption = { id: number; name: string; email: string };
type TemplateItem = {
    id: number;
    title: string;
    description: string | null;
    owner_type: string;
    assignee_user_id: number | null;
    due_offset_days: number;
    is_mandatory: boolean;
    employee_action_required: boolean;
    evidence_required: boolean;
    sort_order: number;
    assignee: UserOption | null;
};
type Template = {
    id: number;
    code: string;
    name: string;
    description: string | null;
    separation_type: string | null;
    minimum_notice_days: number;
    employee_can_apply: boolean;
    exit_interview_required: boolean;
    final_settlement_required: boolean;
    approver_user_id: number | null;
    is_active: boolean;
    cases_count: number;
    approver: UserOption | null;
    items: TemplateItem[];
};
type Props = {
    templates: Template[];
    approvers: UserOption[];
    assignees: UserOption[];
    types: string[];
    ownerTypes: string[];
};

const typeLabel: Record<string, string> = {
    resignation: 'Berhenti Sukarela',
    contract_end: 'Tamat Kontrak',
    retirement: 'Persaraan',
    termination: 'Penamatan',
    redundancy: 'Pengurangan Tenaga Kerja',
    medical: 'Perubatan',
    death: 'Kematian',
    other: 'Lain-lain',
};
const ownerLabel: Record<string, string> = {
    employee: 'Pekerja',
    supervisor: 'Penyelia',
    hr: 'HR',
    finance: 'Kewangan',
    ict: 'ICT',
    administration: 'Pentadbiran',
    payroll: 'Payroll',
    custom: 'Pegawai Khusus',
};

function TemplateForm({
    template,
    types,
    approvers,
    onDone,
}: {
    template?: Template;
    types: string[];
    approvers: UserOption[];
    onDone: () => void;
}) {
    const form = useForm({
        code: template?.code ?? '',
        name: template?.name ?? '',
        description: template?.description ?? '',
        separation_type: template?.separation_type ?? 'resignation',
        minimum_notice_days: String(template?.minimum_notice_days ?? 30),
        employee_can_apply: template?.employee_can_apply ?? false,
        exit_interview_required: template?.exit_interview_required ?? true,
        final_settlement_required: template?.final_settlement_required ?? true,
        approver_user_id: template?.approver_user_id
            ? String(template.approver_user_id)
            : 'none',
    });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            approver_user_id:
                data.approver_user_id === 'none' ? null : data.approver_user_id,
        }));

        if (template) {
            form.put(`/tetapan-clearance/template/${template.id}`, {
                preserveScroll: true,
                onSuccess: onDone,
            });
        } else {
            form.post('/tetapan-clearance/template', {
                preserveScroll: true,
                onSuccess: onDone,
            });
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
                    placeholder="RESIGNATION"
                />
            </div>
            <div className="space-y-2">
                <Label>Nama Template</Label>
                <Input
                    value={form.data.name}
                    onChange={(event) =>
                        form.setData('name', event.target.value)
                    }
                />
            </div>
            <div className="space-y-2">
                <Label>Jenis Lalai</Label>
                <Select
                    value={form.data.separation_type}
                    onValueChange={(value) =>
                        form.setData('separation_type', value)
                    }
                >
                    <SelectTrigger>
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {types.map((type) => (
                            <SelectItem key={type} value={type}>
                                {typeLabel[type] ?? type}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>
            <div className="space-y-2">
                <Label>Notis Minimum (hari)</Label>
                <Input
                    type="number"
                    min={0}
                    max={365}
                    value={form.data.minimum_notice_days}
                    onChange={(event) =>
                        form.setData('minimum_notice_days', event.target.value)
                    }
                />
            </div>
            <div className="space-y-2 md:col-span-2">
                <Label>Pelulus HR Lalai</Label>
                <Select
                    value={form.data.approver_user_id}
                    onValueChange={(value) =>
                        form.setData('approver_user_id', value)
                    }
                >
                    <SelectTrigger>
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="none">Pilih semasa kes</SelectItem>
                        {approvers.map((user) => (
                            <SelectItem key={user.id} value={String(user.id)}>
                                {user.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
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
            <label className="flex items-start gap-3 rounded-lg border p-3">
                <Checkbox
                    checked={form.data.employee_can_apply}
                    onCheckedChange={(checked) =>
                        form.setData('employee_can_apply', Boolean(checked))
                    }
                />
                <span className="text-sm">
                    Pekerja boleh memulakan permohonan
                </span>
            </label>
            <label className="flex items-start gap-3 rounded-lg border p-3">
                <Checkbox
                    checked={form.data.exit_interview_required}
                    onCheckedChange={(checked) =>
                        form.setData(
                            'exit_interview_required',
                            Boolean(checked),
                        )
                    }
                />
                <span className="text-sm">Exit interview wajib</span>
            </label>
            <label className="flex items-start gap-3 rounded-lg border p-3 md:col-span-2">
                <Checkbox
                    checked={form.data.final_settlement_required}
                    onCheckedChange={(checked) =>
                        form.setData(
                            'final_settlement_required',
                            Boolean(checked),
                        )
                    }
                />
                <span className="text-sm">Final settlement wajib disahkan</span>
            </label>
            {Object.keys(form.errors).length > 0 && (
                <p className="text-sm text-red-600 md:col-span-2">
                    {Object.values(form.errors)[0]}
                </p>
            )}
            <div className="flex justify-end md:col-span-2">
                <Button disabled={form.processing}>
                    {template ? 'Simpan Perubahan' : 'Tambah Template'}
                </Button>
            </div>
        </form>
    );
}

function ItemForm({
    template,
    item,
    ownerTypes,
    assignees,
    onDone,
}: {
    template: Template;
    item?: TemplateItem;
    ownerTypes: string[];
    assignees: UserOption[];
    onDone: () => void;
}) {
    const form = useForm({
        title: item?.title ?? '',
        description: item?.description ?? '',
        owner_type: item?.owner_type ?? 'hr',
        assignee_user_id: item?.assignee_user_id
            ? String(item.assignee_user_id)
            : 'none',
        due_offset_days: String(item?.due_offset_days ?? 0),
        is_mandatory: item?.is_mandatory ?? true,
        employee_action_required: item?.employee_action_required ?? false,
        evidence_required: item?.evidence_required ?? false,
        sort_order: String(
            item?.sort_order ?? (template.items.length + 1) * 10,
        ),
    });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            assignee_user_id:
                data.assignee_user_id === 'none' ? null : data.assignee_user_id,
        }));
        const options = { preserveScroll: true, onSuccess: onDone };

        if (item) {
            form.put(
                `/tetapan-clearance/template/${template.id}/item/${item.id}`,
                options,
            );
        } else {
            form.post(
                `/tetapan-clearance/template/${template.id}/item`,
                options,
            );
        }
    };

    return (
        <form className="grid gap-4 md:grid-cols-2" onSubmit={submit}>
            <div className="space-y-2 md:col-span-2">
                <Label>Tajuk Tugasan</Label>
                <Input
                    value={form.data.title}
                    onChange={(event) =>
                        form.setData('title', event.target.value)
                    }
                />
            </div>
            <div className="space-y-2">
                <Label>Pemilik Proses</Label>
                <Select
                    value={form.data.owner_type}
                    onValueChange={(value) => form.setData('owner_type', value)}
                >
                    <SelectTrigger>
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {ownerTypes.map((owner) => (
                            <SelectItem key={owner} value={owner}>
                                {ownerLabel[owner] ?? owner}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>
            <div className="space-y-2">
                <Label>Pegawai Lalai</Label>
                <Select
                    value={form.data.assignee_user_id}
                    onValueChange={(value) =>
                        form.setData('assignee_user_id', value)
                    }
                >
                    <SelectTrigger>
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="none">
                            Ditugaskan semasa kes
                        </SelectItem>
                        {assignees.map((user) => (
                            <SelectItem key={user.id} value={String(user.id)}>
                                {user.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>
            <div className="space-y-2">
                <Label>Offset Tarikh Akhir</Label>
                <Input
                    type="number"
                    min={-90}
                    max={180}
                    value={form.data.due_offset_days}
                    onChange={(event) =>
                        form.setData('due_offset_days', event.target.value)
                    }
                />
                <p className="text-xs text-muted-foreground">
                    Negatif = sebelum hari terakhir, positif = selepas.
                </p>
            </div>
            <div className="space-y-2">
                <Label>Susunan</Label>
                <Input
                    type="number"
                    min={0}
                    value={form.data.sort_order}
                    onChange={(event) =>
                        form.setData('sort_order', event.target.value)
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
            {[
                ['is_mandatory', 'Tugasan wajib'],
                ['employee_action_required', 'Tindakan pekerja diperlukan'],
                ['evidence_required', 'Bukti wajib dimuat naik'],
            ].map(([field, label]) => (
                <label
                    key={field}
                    className="flex items-center gap-2 rounded-lg border p-3 text-sm"
                >
                    <Checkbox
                        checked={Boolean(
                            form.data[field as keyof typeof form.data],
                        )}
                        onCheckedChange={(checked) =>
                            form.setData(
                                field as
                                    | 'is_mandatory'
                                    | 'employee_action_required'
                                    | 'evidence_required',
                                Boolean(checked),
                            )
                        }
                    />
                    {label}
                </label>
            ))}
            {Object.keys(form.errors).length > 0 && (
                <p className="text-sm text-red-600 md:col-span-2">
                    {Object.values(form.errors)[0]}
                </p>
            )}
            <div className="flex justify-end md:col-span-2">
                <Button disabled={form.processing}>
                    {item ? 'Simpan Item' : 'Tambah Item'}
                </Button>
            </div>
        </form>
    );
}

function EditTemplate({
    template,
    types,
    approvers,
}: {
    template: Template;
    types: string[];
    approvers: UserOption[];
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    <Pencil /> Edit Template
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Edit Template Pengakhiran</DialogTitle>
                    <DialogDescription>
                        Perubahan tidak mengubah checklist kes yang telah
                        dijana.
                    </DialogDescription>
                </DialogHeader>
                <TemplateForm
                    template={template}
                    types={types}
                    approvers={approvers}
                    onDone={() => setOpen(false)}
                />
            </DialogContent>
        </Dialog>
    );
}

function EditItem({
    template,
    item,
    ownerTypes,
    assignees,
}: {
    template: Template;
    item: TemplateItem;
    ownerTypes: string[];
    assignees: UserOption[];
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
                    <DialogTitle>Edit Item Checklist</DialogTitle>
                </DialogHeader>
                <ItemForm
                    template={template}
                    item={item}
                    ownerTypes={ownerTypes}
                    assignees={assignees}
                    onDone={() => setOpen(false)}
                />
            </DialogContent>
        </Dialog>
    );
}

function TemplateCard({
    template,
    types,
    ownerTypes,
    approvers,
    assignees,
}: {
    template: Template;
    types: string[];
    ownerTypes: string[];
    approvers: UserOption[];
    assignees: UserOption[];
}) {
    const [itemOpen, setItemOpen] = useState(false);

    return (
        <Card>
            <CardHeader>
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <ClipboardCheck className="size-4 text-orange-600" />
                            {template.name}
                        </CardTitle>
                        <CardDescription>
                            {template.code} · {template.cases_count} kes ·{' '}
                            {template.items.length} item
                        </CardDescription>
                    </div>
                    <Badge
                        variant={template.is_active ? 'default' : 'secondary'}
                    >
                        {template.is_active ? 'Aktif' : 'Tidak Aktif'}
                    </Badge>
                </div>
            </CardHeader>
            <CardContent className="space-y-4">
                <div className="grid gap-3 text-sm md:grid-cols-4">
                    <div>
                        <div className="text-muted-foreground">Jenis</div>
                        <div className="font-medium">
                            {typeLabel[template.separation_type ?? ''] ?? '-'}
                        </div>
                    </div>
                    <div>
                        <div className="text-muted-foreground">Notis</div>
                        <div className="font-medium">
                            {template.minimum_notice_days} hari
                        </div>
                    </div>
                    <div>
                        <div className="text-muted-foreground">Pelulus</div>
                        <div className="font-medium">
                            {template.approver?.name ?? 'Pilih semasa kes'}
                        </div>
                    </div>
                    <div>
                        <div className="text-muted-foreground">Layan Diri</div>
                        <div className="font-medium">
                            {template.employee_can_apply ? 'Ya' : 'Tidak'}
                        </div>
                    </div>
                </div>
                {template.description && (
                    <p className="text-sm text-muted-foreground">
                        {template.description}
                    </p>
                )}
                <div className="flex flex-wrap gap-2">
                    <EditTemplate
                        template={template}
                        types={types}
                        approvers={approvers}
                    />
                    <Button
                        size="sm"
                        variant="outline"
                        onClick={() =>
                            router.patch(
                                `/tetapan-clearance/template/${template.id}/status`,
                                {},
                                { preserveScroll: true },
                            )
                        }
                    >
                        {template.is_active ? 'Nyahaktifkan' : 'Aktifkan'}
                    </Button>
                    <Dialog open={itemOpen} onOpenChange={setItemOpen}>
                        <DialogTrigger asChild>
                            <Button size="sm" variant="outline">
                                <Plus /> Item Checklist
                            </Button>
                        </DialogTrigger>
                        <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                            <DialogHeader>
                                <DialogTitle>Tambah Item Checklist</DialogTitle>
                            </DialogHeader>
                            <ItemForm
                                template={template}
                                ownerTypes={ownerTypes}
                                assignees={assignees}
                                onDone={() => setItemOpen(false)}
                            />
                        </DialogContent>
                    </Dialog>
                </div>
                <Separator />
                <div className="space-y-2">
                    {template.items.map((item) => (
                        <div
                            key={item.id}
                            className="flex flex-wrap items-center justify-between gap-3 rounded-lg border p-3 text-sm"
                        >
                            <div>
                                <div className="font-medium">{item.title}</div>
                                <div className="text-muted-foreground">
                                    {ownerLabel[item.owner_type] ??
                                        item.owner_type}
                                    {' · '}
                                    {item.assignee?.name ??
                                        'Auto / belum ditugaskan'}
                                    {' · '}
                                    Offset {item.due_offset_days} hari
                                </div>
                            </div>
                            <div className="flex gap-2">
                                {item.is_mandatory && (
                                    <Badge variant="outline">Wajib</Badge>
                                )}
                                {item.evidence_required && (
                                    <Badge variant="outline">Bukti</Badge>
                                )}
                                <EditItem
                                    template={template}
                                    item={item}
                                    ownerTypes={ownerTypes}
                                    assignees={assignees}
                                />
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() => {
                                        if (
                                            window.confirm(
                                                `Buang item ${item.title}?`,
                                            )
                                        ) {
                                            router.delete(
                                                `/tetapan-clearance/template/${template.id}/item/${item.id}`,
                                                { preserveScroll: true },
                                            );
                                        }
                                    }}
                                >
                                    <Trash2 />
                                </Button>
                            </div>
                        </div>
                    ))}
                </div>
            </CardContent>
        </Card>
    );
}

export default function SeparationSettings({
    templates,
    approvers,
    assignees,
    types,
    ownerTypes,
}: Props) {
    const [open, setOpen] = useState(false);

    return (
        <>
            <Head title="Tetapan Clearance" />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Tetapan Berhenti & Clearance
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Template pengakhiran, notis, pelulus, pemilik
                            proses, SLA dan checklist berbilang unit.
                        </p>
                    </div>
                    <Dialog open={open} onOpenChange={setOpen}>
                        <DialogTrigger asChild>
                            <Button>
                                <Plus /> Template Baharu
                            </Button>
                        </DialogTrigger>
                        <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                            <DialogHeader>
                                <DialogTitle>
                                    Template Pengakhiran Baharu
                                </DialogTitle>
                                <DialogDescription>
                                    Selepas template dicipta, tambah item
                                    checklist bagi setiap unit clearance.
                                </DialogDescription>
                            </DialogHeader>
                            <TemplateForm
                                types={types}
                                approvers={approvers}
                                onDone={() => setOpen(false)}
                            />
                        </DialogContent>
                    </Dialog>
                </div>
                <div className="space-y-5">
                    {templates.map((template) => (
                        <TemplateCard
                            key={template.id}
                            template={template}
                            types={types}
                            ownerTypes={ownerTypes}
                            approvers={approvers}
                            assignees={assignees}
                        />
                    ))}
                </div>
            </div>
        </>
    );
}

SeparationSettings.layout = {
    breadcrumbs: [{ title: 'Tetapan Clearance', href: '/tetapan-clearance' }],
};
