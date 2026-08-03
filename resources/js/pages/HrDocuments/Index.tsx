import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    Bell,
    Check,
    Download,
    FileCheck2,
    FileClock,
    FilePlus2,
    Files,
    FileX2,
    Paperclip,
    Pencil,
    RefreshCw,
    Search,
    Send,
    Upload,
    X,
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

type Template = {
    id: number;
    code: string;
    name: string;
    category: string;
    subject_template: string;
    body_template: string;
    available_variables: string[] | null;
    requires_approval: boolean;
    approver_user_id: number | null;
    acknowledgement_required: boolean;
    default_validity_months: number | null;
    confidentiality: string;
};

type Employee = {
    id: number;
    user_id: number;
    employee_number: string;
    name: string;
    email: string | null;
    department_id: number | null;
    department_name: string | null;
    position_name: string | null;
};

type Approver = { id: number; name: string; email: string };

type Attachment = {
    id: number;
    attachment_type: string;
    original_name: string;
    mime_type: string;
    size: number;
    visible_to_employee: boolean;
};

type Document = {
    id: number;
    reference_number: string | null;
    template_name: string;
    category: string;
    employee_user_id: number | null;
    employee_number: string | null;
    employee_name: string;
    employee_email: string | null;
    department_name: string | null;
    position_name: string | null;
    source_type: string;
    source_id: number | null;
    subject: string;
    body: string;
    rendered_subject: string;
    rendered_body: string;
    signatory_name: string | null;
    signatory_position: string | null;
    internal_notes: string | null;
    status: string;
    display_status: string;
    approval_required: boolean;
    approver_user_id: number | null;
    approver: Approver | null;
    approval_notes: string | null;
    rejection_reason: string | null;
    issued_at: string | null;
    effective_date: string | null;
    expiry_date: string | null;
    days_to_expiry: number | null;
    acknowledgement_required: boolean;
    acknowledged_at: string | null;
    void_reason: string | null;
    confidentiality: string;
    attachments: Attachment[];
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type Paginator<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: PaginationLink[];
};

type Props = {
    documents: Paginator<Document>;
    templates: Template[];
    employees: Employee[];
    approvers: Approver[];
    filters: {
        search: string;
        status: string;
        category: string;
        expiry: string;
    };
    categories: string[];
    sources: string[];
    statistics: {
        total: number;
        draft: number;
        pending: number;
        issued: number;
        expiring: number;
        expired: number;
    };
    canManage: boolean;
    canApprove: boolean;
    notifications: {
        id: number;
        document_id: number | null;
        title: string;
        message: string;
        read_at: string | null;
        created_at: string;
    }[];
    unreadNotifications: number;
};

const statusLabel: Record<string, string> = {
    draft: 'Draf',
    pending_approval: 'Menunggu Kelulusan',
    approved: 'Diluluskan',
    rejected: 'Ditolak',
    issued: 'Dikeluarkan',
    acknowledged: 'Diperakui',
    voided: 'Dibatalkan',
    expired: 'Tamat Tempoh',
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

const sourceLabel: Record<string, string> = {
    manual: 'Manual',
    recruitment: 'Pengambilan',
    onboarding: 'Onboarding',
    performance: 'Prestasi',
    payroll: 'Payroll',
    discipline: 'Disiplin',
    separation: 'Berhenti Kerja',
};

const systemVariables = new Set([
    'employee_name',
    'employee_number',
    'employee_email',
    'department_name',
    'position_name',
    'reference_number',
    'issue_date',
    'effective_date',
    'expiry_date',
    'signatory_name',
    'signatory_position',
    'company_name',
    'today',
]);

function formatDate(value: string | null) {
    if (!value) {
        return '-';
    }

    return new Intl.DateTimeFormat('ms-MY', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(new Date(value));
}

function Field({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="space-y-2">
            <Label>{label}</Label>
            {children}
        </div>
    );
}

function StatusBadge({ status }: { status: string }) {
    const variant =
        status === 'approved' || status === 'acknowledged'
            ? 'default'
            : ['rejected', 'voided', 'expired'].includes(status)
              ? 'destructive'
              : 'secondary';

    return <Badge variant={variant}>{statusLabel[status] ?? status}</Badge>;
}

function CreateDialog({
    templates,
    employees,
    approvers,
    sources,
}: {
    templates: Template[];
    employees: Employee[];
    approvers: Approver[];
    sources: string[];
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        document_template_id: '',
        employee_user_id: '',
        source_type: 'manual',
        source_id: '',
        effective_date: '',
        expiry_date: '',
        signatory_name: '',
        signatory_position: '',
        approver_user_id: '',
        internal_notes: '',
        custom_variables: {} as Record<string, string>,
    });
    const selected = templates.find(
        (template) => String(template.id) === form.data.document_template_id,
    );
    const customVariables = (selected?.available_variables ?? []).filter(
        (variable) => !systemVariables.has(variable),
    );

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            source_id: data.source_id || null,
            expiry_date: data.expiry_date || null,
            effective_date: data.effective_date || null,
            approver_user_id: data.approver_user_id || null,
        }));
        form.post('/dokumen-hr', {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setOpen(false);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button disabled={templates.length === 0}>
                    <FilePlus2 /> Jana Dokumen
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[92vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Jana Draf Dokumen HR</DialogTitle>
                    <DialogDescription>
                        Identiti pekerja dan kandungan template disimpan sebagai
                        snapshot.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <Field label="Template">
                        <Select
                            value={form.data.document_template_id}
                            onValueChange={(value) => {
                                form.setData('document_template_id', value);
                                const template = templates.find(
                                    (item) => String(item.id) === value,
                                );
                                form.setData(
                                    'approver_user_id',
                                    template?.approver_user_id
                                        ? String(template.approver_user_id)
                                        : '',
                                );
                                form.setData(
                                    'custom_variables',
                                    Object.fromEntries(
                                        (template?.available_variables ?? [])
                                            .filter(
                                                (variable) =>
                                                    !systemVariables.has(
                                                        variable,
                                                    ),
                                            )
                                            .map((variable) => [variable, '']),
                                    ),
                                );
                            }}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Pilih template" />
                            </SelectTrigger>
                            <SelectContent>
                                {templates.map((template) => (
                                    <SelectItem
                                        key={template.id}
                                        value={String(template.id)}
                                    >
                                        {template.code} · {template.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError
                            message={form.errors.document_template_id}
                        />
                    </Field>
                    {selected && (
                        <div className="rounded-md bg-muted p-3 text-sm">
                            <div className="font-medium">
                                {selected.subject_template}
                            </div>
                            <div className="mt-1 text-muted-foreground">
                                {categoryLabel[selected.category]} ·{' '}
                                {selected.requires_approval
                                    ? 'Perlu kelulusan'
                                    : 'Kelulusan tidak diperlukan'}
                            </div>
                        </div>
                    )}
                    {customVariables.length > 0 && (
                        <div className="grid gap-4 sm:grid-cols-2">
                            {customVariables.map((variable) => (
                                <Field key={variable} label={variable}>
                                    <Input
                                        value={
                                            form.data.custom_variables[
                                                variable
                                            ] ?? ''
                                        }
                                        onChange={(event) =>
                                            form.setData('custom_variables', {
                                                ...form.data.custom_variables,
                                                [variable]: event.target.value,
                                            })
                                        }
                                    />
                                </Field>
                            ))}
                        </div>
                    )}
                    <Field label="Pekerja">
                        <Select
                            value={form.data.employee_user_id}
                            onValueChange={(value) =>
                                form.setData('employee_user_id', value)
                            }
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Pilih pekerja" />
                            </SelectTrigger>
                            <SelectContent>
                                {employees.map((employee) => (
                                    <SelectItem
                                        key={employee.user_id}
                                        value={String(employee.user_id)}
                                    >
                                        {employee.employee_number} ·{' '}
                                        {employee.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </Field>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Sumber Dokumen">
                            <Select
                                value={form.data.source_type}
                                onValueChange={(value) =>
                                    form.setData('source_type', value)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {sources.map((source) => (
                                        <SelectItem key={source} value={source}>
                                            {sourceLabel[source] ?? source}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </Field>
                        <Field label="ID Rekod Sumber (pilihan)">
                            <Input
                                type="number"
                                min="1"
                                value={form.data.source_id}
                                onChange={(event) =>
                                    form.setData(
                                        'source_id',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                        <Field label="Tarikh Kuat Kuasa">
                            <Input
                                type="date"
                                value={form.data.effective_date}
                                onChange={(event) =>
                                    form.setData(
                                        'effective_date',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                        <Field label="Tarikh Tamat">
                            <Input
                                type="date"
                                value={form.data.expiry_date}
                                onChange={(event) =>
                                    form.setData(
                                        'expiry_date',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                        <Field label="Nama Penandatangan">
                            <Input
                                value={form.data.signatory_name}
                                onChange={(event) =>
                                    form.setData(
                                        'signatory_name',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                        <Field label="Jawatan Penandatangan">
                            <Input
                                value={form.data.signatory_position}
                                onChange={(event) =>
                                    form.setData(
                                        'signatory_position',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                    </div>
                    <Field label="Pelulus">
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
                    <Field label="Catatan Dalaman">
                        <textarea
                            className="min-h-20 w-full rounded-md border bg-background px-3 py-2 text-sm"
                            value={form.data.internal_notes}
                            onChange={(event) =>
                                form.setData(
                                    'internal_notes',
                                    event.target.value,
                                )
                            }
                        />
                    </Field>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Tutup
                            </Button>
                        </DialogClose>
                        <Button disabled={form.processing}>Jana Draf</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function EditDialog({
    document,
    approvers,
    sources,
}: {
    document: Document;
    approvers: Approver[];
    sources: string[];
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        subject: document.subject,
        body: document.body,
        source_type: document.source_type,
        source_id: document.source_id ? String(document.source_id) : '',
        effective_date: document.effective_date?.slice(0, 10) ?? '',
        expiry_date: document.expiry_date?.slice(0, 10) ?? '',
        signatory_name: document.signatory_name ?? '',
        signatory_position: document.signatory_position ?? '',
        approver_user_id: document.approver_user_id
            ? String(document.approver_user_id)
            : '',
        acknowledgement_required: document.acknowledgement_required,
        confidentiality: document.confidentiality,
        internal_notes: document.internal_notes ?? '',
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            source_id: data.source_id || null,
            effective_date: data.effective_date || null,
            expiry_date: data.expiry_date || null,
            approver_user_id: data.approver_user_id || null,
        }));
        form.put(`/dokumen-hr/${document.id}`, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    <Pencil /> Edit
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[92vh] overflow-y-auto sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>Kemas Kini Draf</DialogTitle>
                    <DialogDescription>
                        Pemboleh ubah dalam format {'{{variable}}'} akan
                        dirender ketika PDF dijana.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <Field label="Subjek">
                        <Input
                            value={form.data.subject}
                            onChange={(event) =>
                                form.setData('subject', event.target.value)
                            }
                        />
                    </Field>
                    <Field label="Kandungan">
                        <textarea
                            className="min-h-64 w-full rounded-md border bg-background px-3 py-2 text-sm"
                            value={form.data.body}
                            onChange={(event) =>
                                form.setData('body', event.target.value)
                            }
                        />
                        <InputError message={form.errors.body} />
                    </Field>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Sumber">
                            <Select
                                value={form.data.source_type}
                                onValueChange={(value) =>
                                    form.setData('source_type', value)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {sources.map((source) => (
                                        <SelectItem key={source} value={source}>
                                            {sourceLabel[source] ?? source}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </Field>
                        <Field label="ID Sumber">
                            <Input
                                type="number"
                                min="1"
                                value={form.data.source_id}
                                onChange={(event) =>
                                    form.setData(
                                        'source_id',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                        <Field label="Tarikh Kuat Kuasa">
                            <Input
                                type="date"
                                value={form.data.effective_date}
                                onChange={(event) =>
                                    form.setData(
                                        'effective_date',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                        <Field label="Tarikh Tamat">
                            <Input
                                type="date"
                                value={form.data.expiry_date}
                                onChange={(event) =>
                                    form.setData(
                                        'expiry_date',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                        <Field label="Nama Penandatangan">
                            <Input
                                value={form.data.signatory_name}
                                onChange={(event) =>
                                    form.setData(
                                        'signatory_name',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                        <Field label="Jawatan Penandatangan">
                            <Input
                                value={form.data.signatory_position}
                                onChange={(event) =>
                                    form.setData(
                                        'signatory_position',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                        <Field label="Pelulus">
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
                                    <SelectItem value="internal">
                                        Dalaman
                                    </SelectItem>
                                    <SelectItem value="confidential">
                                        Sulit
                                    </SelectItem>
                                    <SelectItem value="restricted">
                                        Terhad
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </Field>
                    </div>
                    <label className="flex items-center gap-2 rounded-md border p-3 text-sm">
                        <Checkbox
                            checked={form.data.acknowledgement_required}
                            onCheckedChange={(value) =>
                                form.setData(
                                    'acknowledgement_required',
                                    value === true,
                                )
                            }
                        />
                        Perlu perakuan penerimaan pekerja
                    </label>
                    <Field label="Catatan Dalaman">
                        <textarea
                            className="min-h-20 w-full rounded-md border bg-background px-3 py-2 text-sm"
                            value={form.data.internal_notes}
                            onChange={(event) =>
                                form.setData(
                                    'internal_notes',
                                    event.target.value,
                                )
                            }
                        />
                    </Field>
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

function AttachmentDialog({ document }: { document: Document }) {
    const [open, setOpen] = useState(false);
    const form = useForm<{
        attachment_type: string;
        attachment: File | null;
        visible_to_employee: boolean;
    }>({
        attachment_type: 'supporting',
        attachment: null,
        visible_to_employee: false,
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(`/dokumen-hr/${document.id}/lampiran`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setOpen(false);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    <Paperclip /> Lampiran
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Muat Naik Lampiran</DialogTitle>
                    <DialogDescription>
                        PDF, Word atau imej sehingga 10 MB. Lampiran sokongan
                        kekal untuk HR sahaja.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <Field label="Jenis Lampiran">
                        <Select
                            value={form.data.attachment_type}
                            onValueChange={(value) => {
                                form.setData('attachment_type', value);

                                if (value === 'supporting') {
                                    form.setData('visible_to_employee', false);
                                }
                            }}
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="supporting">
                                    Sokongan Dalaman
                                </SelectItem>
                                <SelectItem value="final_copy">
                                    Salinan Akhir
                                </SelectItem>
                                <SelectItem value="signed_copy">
                                    Salinan Bertandatangan
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </Field>
                    <Field label="Fail">
                        <Input
                            type="file"
                            accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                            onChange={(event) =>
                                form.setData(
                                    'attachment',
                                    event.target.files?.[0] ?? null,
                                )
                            }
                        />
                        <InputError message={form.errors.attachment} />
                    </Field>
                    <label className="flex items-center gap-2 rounded-md border p-3 text-sm">
                        <Checkbox
                            disabled={
                                form.data.attachment_type === 'supporting'
                            }
                            checked={form.data.visible_to_employee}
                            onCheckedChange={(value) =>
                                form.setData(
                                    'visible_to_employee',
                                    value === true,
                                )
                            }
                        />
                        Paparkan kepada pekerja
                    </label>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Tutup
                            </Button>
                        </DialogClose>
                        <Button disabled={form.processing}>
                            <Upload /> Muat Naik
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function DocumentActions({
    document,
    canManage,
    canApprove,
    approvers,
    sources,
}: {
    document: Document;
    canManage: boolean;
    canApprove: boolean;
    approvers: Approver[];
    sources: string[];
}) {
    const review = (action: 'approve' | 'reject') => {
        const notes = window.prompt(
            action === 'approve'
                ? 'Catatan kelulusan (pilihan):'
                : 'Nyatakan sebab penolakan:',
            '',
        );

        if (notes === null || (action === 'reject' && !notes.trim())) {
            return;
        }

        router.patch(
            `/dokumen-hr/${document.id}/semakan`,
            { action, notes },
            { preserveScroll: true },
        );
    };

    return (
        <div className="flex flex-wrap justify-end gap-2">
            <Button size="sm" variant="outline" asChild>
                <Link href={`/dokumen-hr/${document.id}/pdf`}>
                    <Download /> PDF
                </Link>
            </Button>
            {canManage && ['draft', 'rejected'].includes(document.status) && (
                <EditDialog
                    document={document}
                    approvers={approvers}
                    sources={sources}
                />
            )}
            {canManage && document.status !== 'voided' && (
                <AttachmentDialog document={document} />
            )}
            {canManage && document.status === 'draft' && (
                <Button
                    size="sm"
                    onClick={() =>
                        router.patch(
                            `/dokumen-hr/${document.id}/hantar`,
                            {},
                            { preserveScroll: true },
                        )
                    }
                >
                    <Send /> Hantar
                </Button>
            )}
            {canApprove && document.status === 'pending_approval' && (
                <>
                    <Button size="sm" onClick={() => review('approve')}>
                        <Check /> Lulus
                    </Button>
                    <Button
                        size="sm"
                        variant="destructive"
                        onClick={() => review('reject')}
                    >
                        <X /> Tolak
                    </Button>
                </>
            )}
            {canManage && document.status === 'approved' && (
                <Button
                    size="sm"
                    onClick={() => {
                        if (
                            window.confirm(
                                'Keluarkan dokumen ini dan peruntukkan nombor rujukan rasmi?',
                            )
                        ) {
                            router.patch(
                                `/dokumen-hr/${document.id}/keluar`,
                                {},
                                { preserveScroll: true },
                            );
                        }
                    }}
                >
                    <FileCheck2 /> Keluarkan
                </Button>
            )}
            {canManage &&
                ['issued', 'acknowledged'].includes(document.status) && (
                    <>
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() =>
                                router.post(
                                    `/dokumen-hr/${document.id}/pembaharuan`,
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            <RefreshCw /> Perbaharui
                        </Button>
                        <Button
                            size="sm"
                            variant="destructive"
                            onClick={() => {
                                const reason = window.prompt(
                                    'Nyatakan sebab pembatalan dokumen:',
                                );

                                if (!reason?.trim()) {
                                    return;
                                }

                                router.patch(
                                    `/dokumen-hr/${document.id}/batal`,
                                    { reason },
                                    { preserveScroll: true },
                                );
                            }}
                        >
                            <FileX2 /> Batal
                        </Button>
                    </>
                )}
        </div>
    );
}

export default function HrDocuments({
    documents,
    templates,
    employees,
    approvers,
    filters,
    categories,
    sources,
    statistics,
    canManage,
    canApprove,
    notifications,
    unreadNotifications,
}: Props) {
    const [search, setSearch] = useState(filters.search);
    const applyFilters = (overrides: Record<string, string> = {}) => {
        router.get(
            '/dokumen-hr',
            { ...filters, search, ...overrides },
            { preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Dokumen & Surat HR" />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Dokumen & Surat HR
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Jana, lulus, keluarkan dan arkibkan surat rasmi
                            pekerja dengan jejak audit lengkap.
                        </p>
                    </div>
                    <div className="flex gap-2">
                        {unreadNotifications > 0 && (
                            <Button
                                variant="outline"
                                onClick={() =>
                                    router.patch(
                                        '/dokumen-hr/notifikasi/dibaca',
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <Bell /> {unreadNotifications}
                            </Button>
                        )}
                        <Button variant="outline" asChild>
                            <Link href="/dokumen-hr/laporan.csv">
                                <Download /> CSV
                            </Link>
                        </Button>
                        {canManage && (
                            <CreateDialog
                                templates={templates}
                                employees={employees}
                                approvers={approvers}
                                sources={sources}
                            />
                        )}
                    </div>
                </div>

                {notifications.some(
                    (notification) => !notification.read_at,
                ) && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Bell className="size-4" /> Tindakan &
                                Notifikasi
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-2 md:grid-cols-2 xl:grid-cols-3">
                            {notifications
                                .filter((notification) => !notification.read_at)
                                .slice(0, 6)
                                .map((notification) => (
                                    <div
                                        key={notification.id}
                                        className="rounded-lg border border-rose-200 bg-rose-50/60 p-3 text-sm dark:border-rose-900 dark:bg-rose-950/20"
                                    >
                                        <div className="font-medium">
                                            {notification.title}
                                        </div>
                                        <div className="text-muted-foreground">
                                            {notification.message}
                                        </div>
                                    </div>
                                ))}
                        </CardContent>
                    </Card>
                )}

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
                    <StatCard
                        label="Jumlah"
                        value={statistics.total}
                        icon={<Files />}
                    />
                    <StatCard
                        label="Draf"
                        value={statistics.draft}
                        icon={<FileClock />}
                    />
                    <StatCard
                        label="Menunggu"
                        value={statistics.pending}
                        icon={<FileClock />}
                    />
                    <StatCard
                        label="Dikeluarkan"
                        value={statistics.issued}
                        icon={<FileCheck2 />}
                    />
                    <StatCard
                        label="Tamat ≤ 30 Hari"
                        value={statistics.expiring}
                        icon={<AlertTriangle />}
                    />
                    <StatCard
                        label="Tamat Tempoh"
                        value={statistics.expired}
                        icon={<FileX2 />}
                    />
                </div>

                <Card>
                    <CardContent className="grid gap-3 p-4 lg:grid-cols-[minmax(220px,1fr)_180px_180px_180px_auto]">
                        <div className="relative">
                            <Search className="absolute top-2.5 left-3 size-4 text-muted-foreground" />
                            <Input
                                className="pl-9"
                                placeholder="Rujukan, pekerja atau subjek"
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                onKeyDown={(event) => {
                                    if (event.key === 'Enter') {
                                        applyFilters();
                                    }
                                }}
                            />
                        </div>
                        <Select
                            value={filters.status || 'all'}
                            onValueChange={(value) =>
                                applyFilters({
                                    status: value === 'all' ? '' : value,
                                })
                            }
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Semua status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    Semua Status
                                </SelectItem>
                                {Object.entries(statusLabel).map(
                                    ([value, label]) => (
                                        <SelectItem key={value} value={value}>
                                            {label}
                                        </SelectItem>
                                    ),
                                )}
                            </SelectContent>
                        </Select>
                        <Select
                            value={filters.category || 'all'}
                            onValueChange={(value) =>
                                applyFilters({
                                    category: value === 'all' ? '' : value,
                                })
                            }
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Semua kategori" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    Semua Kategori
                                </SelectItem>
                                {categories.map((category) => (
                                    <SelectItem key={category} value={category}>
                                        {categoryLabel[category] ?? category}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Select
                            value={filters.expiry || 'all'}
                            onValueChange={(value) =>
                                applyFilters({ expiry: value })
                            }
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    Semua Tempoh
                                </SelectItem>
                                <SelectItem value="30_days">
                                    Tamat ≤ 30 Hari
                                </SelectItem>
                                <SelectItem value="expired">
                                    Tamat Tempoh
                                </SelectItem>
                                <SelectItem value="none">
                                    Tiada Tarikh Tamat
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <Button
                            variant="outline"
                            onClick={() => applyFilters()}
                        >
                            Tapis
                        </Button>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Daftar Dokumen
                        </CardTitle>
                        <CardDescription>
                            {documents.total} rekod ditemui.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Dokumen</TableHead>
                                    <TableHead>Pekerja</TableHead>
                                    <TableHead>Tarikh</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Lampiran</TableHead>
                                    <TableHead className="min-w-80 text-right">
                                        Tindakan
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {documents.data.map((document) => (
                                    <TableRow key={document.id}>
                                        <TableCell className="max-w-72">
                                            <div className="truncate font-medium">
                                                {document.rendered_subject}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {document.reference_number ??
                                                    `DRAF #${document.id}`}{' '}
                                                ·{' '}
                                                {categoryLabel[
                                                    document.category
                                                ] ?? document.category}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <div className="font-medium">
                                                {document.employee_name}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {document.employee_number} ·{' '}
                                                {document.department_name ??
                                                    '-'}
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-sm">
                                            <div>
                                                Kuat kuasa:{' '}
                                                {formatDate(
                                                    document.effective_date,
                                                )}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                Tamat:{' '}
                                                {formatDate(
                                                    document.expiry_date,
                                                )}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <StatusBadge
                                                status={document.display_status}
                                            />
                                            {document.status === 'rejected' && (
                                                <div className="mt-1 max-w-48 text-xs text-red-600">
                                                    {document.rejection_reason}
                                                </div>
                                            )}
                                            {document.status ===
                                                'pending_approval' && (
                                                <div className="mt-1 text-xs text-muted-foreground">
                                                    {document.approver?.name ??
                                                        'Pelulus Default'}
                                                </div>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant="outline">
                                                {document.attachments.length}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            <DocumentActions
                                                document={document}
                                                canManage={canManage}
                                                canApprove={canApprove}
                                                approvers={approvers}
                                                sources={sources}
                                            />
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>

                        {documents.data.length === 0 && (
                            <div className="flex min-h-40 flex-col items-center justify-center gap-2 text-center text-muted-foreground">
                                <Files className="size-8" />
                                Tiada dokumen sepadan dengan penapis.
                            </div>
                        )}

                        {documents.last_page > 1 && (
                            <div className="mt-4 flex flex-wrap justify-end gap-1">
                                {documents.links.map((link, index) => (
                                    <Button
                                        key={`${link.label}-${index}`}
                                        size="sm"
                                        variant={
                                            link.active ? 'default' : 'outline'
                                        }
                                        disabled={!link.url}
                                        asChild={Boolean(link.url)}
                                    >
                                        {link.url ? (
                                            <Link
                                                href={link.url}
                                                preserveScroll
                                                preserveState
                                            >
                                                <span
                                                    dangerouslySetInnerHTML={{
                                                        __html: link.label,
                                                    }}
                                                />
                                            </Link>
                                        ) : (
                                            <span
                                                dangerouslySetInnerHTML={{
                                                    __html: link.label,
                                                }}
                                            />
                                        )}
                                    </Button>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

HrDocuments.layout = {
    breadcrumbs: [{ title: 'Dokumen & Surat HR', href: '/dokumen-hr' }],
};

function StatCard({
    label,
    value,
    icon,
}: {
    label: string;
    value: number;
    icon: ReactNode;
}) {
    return (
        <Card>
            <CardContent className="flex items-center justify-between p-4">
                <div>
                    <div className="text-xs text-muted-foreground">{label}</div>
                    <div className="text-xl font-semibold">{value}</div>
                </div>
                <span className="text-rose-600 [&>svg]:size-5">{icon}</span>
            </CardContent>
        </Card>
    );
}
