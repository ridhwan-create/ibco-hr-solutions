import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    Bell,
    CheckCircle2,
    ClipboardCheck,
    FileUp,
    Handshake,
    LogOut,
    PackageCheck,
    Plus,
} from 'lucide-react';
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

type Template = {
    id: number;
    name: string;
    description: string | null;
    separation_type: string;
    minimum_notice_days: number;
};
type Attachment = {
    id: number;
    original_name: string;
    size: number;
};
type Task = {
    id: number;
    title: string;
    description: string | null;
    owner_type: string;
    assigned_user_id: number | null;
    is_mandatory: boolean;
    employee_action_required: boolean;
    evidence_required: boolean;
    due_date: string | null;
    status: string;
    submission_notes: string | null;
    review_notes: string | null;
    assignee: { id: number; name: string } | null;
    attachments: Attachment[];
};
type Asset = {
    id: number;
    asset_name: string;
    asset_type: string;
    asset_tag: string | null;
    serial_number: string | null;
    expected_return_date: string | null;
    status: string;
    return_condition: string | null;
    charge_amount: string;
};
type Handover = {
    id: number;
    title: string;
    description: string;
    due_date: string | null;
    status: string;
    submission_notes: string | null;
    review_notes: string | null;
    recipient: { id: number; name: string } | null;
};
type ExitInterview = {
    scheduled_at: string | null;
    employee_submitted_at: string | null;
    completed_at: string | null;
    primary_reason: string | null;
};
type Case = {
    id: number;
    case_number: string;
    employee_name: string;
    employee_number: string | null;
    department_name: string | null;
    position_name: string | null;
    separation_type: string;
    reason_details: string;
    notice_submitted_date: string;
    proposed_last_day: string;
    approved_last_day: string | null;
    notice_days_required: number;
    notice_days_served: number;
    notice_shortfall_days: number;
    status: string;
    approval_stage: string | null;
    supervisor_decision: string | null;
    clearance_due_date: string | null;
    template: {
        name: string;
        exit_interview_required: boolean;
        final_settlement_required: boolean;
    } | null;
    tasks: Task[];
    assets: Asset[];
    handovers: Handover[];
    interview: ExitInterview | null;
    settlement: {
        status: string;
        net_amount?: string;
        verified_at?: string;
    } | null;
    acceptance_document: {
        id: number;
        reference_number: string | null;
        status: string;
    } | null;
    clearance_document: {
        id: number;
        reference_number: string | null;
        status: string;
    } | null;
};
type Props = {
    employeeLinked: boolean;
    employee: {
        employee_number: string | null;
        employee_name: string;
        department_name: string | null;
        position_name: string | null;
    } | null;
    templates: Template[];
    cases: Case[];
    notifications: {
        id: number;
        case_id: number | null;
        title: string;
        message: string;
        read_at: string | null;
        created_at: string;
    }[];
    unreadNotifications: number;
};

const statusLabel: Record<string, string> = {
    draft: 'Draf HR',
    pending_approval: 'Menunggu Kelulusan',
    approved: 'Diluluskan',
    clearance: 'Dalam Clearance',
    final_review: 'Semakan Akhir HR',
    completed: 'Selesai',
    rejected: 'Ditolak',
    cancelled: 'Dibatalkan',
    pending: 'Belum Mula',
    in_progress: 'Sedang Dibuat',
    submitted: 'Menunggu Pengesahan',
    waived: 'Dikecualikan',
    accepted: 'Diterima',
    returned: 'Dipulangkan',
    damaged: 'Rosak',
    lost: 'Hilang',
    verified: 'Disahkan',
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

function formatDate(value: string | null, withTime = false) {
    if (!value) {
        return '-';
    }

    return new Intl.DateTimeFormat('ms-MY', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        ...(withTime ? { hour: '2-digit', minute: '2-digit' } : {}),
    }).format(new Date(value));
}

function StatusBadge({ status }: { status: string }) {
    const variant = ['completed', 'accepted', 'returned', 'verified'].includes(
        status,
    )
        ? 'default'
        : ['rejected', 'cancelled', 'damaged', 'lost'].includes(status)
          ? 'destructive'
          : 'secondary';

    return <Badge variant={variant}>{statusLabel[status] ?? status}</Badge>;
}

function NoticeForm({ templates }: { templates: Template[] }) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        separation_template_id: '',
        reason_category: '',
        reason_details: '',
        proposed_last_day: '',
    });
    const selected = templates.find(
        (item) => String(item.id) === form.data.separation_template_id,
    );
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post('/pengakhiran-saya', {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                form.reset();
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button disabled={templates.length === 0}>
                    <LogOut /> Hantar Notis Berhenti
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Notis Berhenti Kerja</DialogTitle>
                    <DialogDescription>
                        Notis akan dihantar kepada penyelia dan HR. Tarikh akhir
                        hanya muktamad selepas diluluskan.
                    </DialogDescription>
                </DialogHeader>
                <form className="space-y-4" onSubmit={submit}>
                    <div className="space-y-2">
                        <Label>Jenis Proses</Label>
                        <Select
                            value={form.data.separation_template_id}
                            onValueChange={(value) =>
                                form.setData('separation_template_id', value)
                            }
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
                                        {template.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {selected && (
                            <p className="text-xs text-muted-foreground">
                                Notis minimum {selected.minimum_notice_days}{' '}
                                hari.
                            </p>
                        )}
                    </div>
                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="space-y-2">
                            <Label>Kategori Sebab</Label>
                            <Input
                                value={form.data.reason_category}
                                onChange={(event) =>
                                    form.setData(
                                        'reason_category',
                                        event.target.value,
                                    )
                                }
                                placeholder="Contoh: Peluang kerjaya"
                            />
                        </div>
                        <div className="space-y-2">
                            <Label>Cadangan Hari Terakhir</Label>
                            <Input
                                type="date"
                                value={form.data.proposed_last_day}
                                onChange={(event) =>
                                    form.setData(
                                        'proposed_last_day',
                                        event.target.value,
                                    )
                                }
                            />
                        </div>
                    </div>
                    <div className="space-y-2">
                        <Label>Sebab dan Penjelasan</Label>
                        <Textarea
                            rows={5}
                            value={form.data.reason_details}
                            onChange={(event) =>
                                form.setData(
                                    'reason_details',
                                    event.target.value,
                                )
                            }
                        />
                    </div>
                    {Object.keys(form.errors).length > 0 && (
                        <p className="text-sm text-red-600">
                            {Object.values(form.errors)[0]}
                        </p>
                    )}
                    <div className="flex justify-end">
                        <Button disabled={form.processing}>Hantar Notis</Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function TaskCard({ task, caseId }: { task: Task; caseId: number }) {
    const form = useForm({ submission_notes: task.submission_notes ?? '' });
    const fileForm = useForm({ attachment: null as File | null });
    const canSubmit =
        (task.employee_action_required || task.owner_type === 'employee') &&
        ['pending', 'in_progress', 'rejected'].includes(task.status);

    return (
        <div className="space-y-3 rounded-lg border p-4">
            <div className="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <div className="font-medium">{task.title}</div>
                    <p className="text-xs text-muted-foreground">
                        {task.assignee?.name ?? 'Belum ditugaskan'} · Tamat{' '}
                        {formatDate(task.due_date)}
                    </p>
                </div>
                <StatusBadge status={task.status} />
            </div>
            {task.description && (
                <p className="text-sm text-muted-foreground">
                    {task.description}
                </p>
            )}
            {task.review_notes && (
                <p className="rounded-md bg-red-50 p-2 text-sm text-red-700 dark:bg-red-950/30 dark:text-red-300">
                    Catatan semakan: {task.review_notes}
                </p>
            )}
            {task.attachments.length > 0 && (
                <div className="flex flex-wrap gap-2">
                    {task.attachments.map((attachment) => (
                        <Button
                            key={attachment.id}
                            size="sm"
                            variant="outline"
                            asChild
                        >
                            <a
                                href={`/pengakhiran-saya/${caseId}/lampiran/${attachment.id}`}
                            >
                                {attachment.original_name}
                            </a>
                        </Button>
                    ))}
                </div>
            )}
            {canSubmit && (
                <div className="space-y-3 border-t pt-3">
                    {task.evidence_required && (
                        <form
                            className="flex flex-wrap items-center gap-2"
                            onSubmit={(event) => {
                                event.preventDefault();
                                fileForm.post(
                                    `/pengakhiran-saya/${caseId}/tugasan/${task.id}/lampiran`,
                                    {
                                        forceFormData: true,
                                        preserveScroll: true,
                                        onSuccess: () =>
                                            fileForm.reset('attachment'),
                                    },
                                );
                            }}
                        >
                            <Input
                                className="max-w-sm"
                                type="file"
                                accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.xlsx"
                                onChange={(event) =>
                                    fileForm.setData(
                                        'attachment',
                                        event.target.files?.[0] ?? null,
                                    )
                                }
                            />
                            <Button
                                size="sm"
                                variant="outline"
                                disabled={
                                    fileForm.processing ||
                                    !fileForm.data.attachment
                                }
                            >
                                <FileUp /> Muat Naik Bukti
                            </Button>
                        </form>
                    )}
                    <form
                        className="space-y-2"
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.patch(
                                `/pengakhiran-saya/${caseId}/tugasan/${task.id}/hantar`,
                                { preserveScroll: true },
                            );
                        }}
                    >
                        <Textarea
                            rows={3}
                            value={form.data.submission_notes}
                            onChange={(event) =>
                                form.setData(
                                    'submission_notes',
                                    event.target.value,
                                )
                            }
                            placeholder="Nyatakan tindakan yang telah diselesaikan"
                        />
                        {(Object.keys(form.errors).length > 0 ||
                            Object.keys(fileForm.errors).length > 0) && (
                            <p className="text-sm text-red-600">
                                {Object.values(form.errors)[0] ??
                                    Object.values(fileForm.errors)[0]}
                            </p>
                        )}
                        <Button size="sm" disabled={form.processing}>
                            Hantar untuk Pengesahan
                        </Button>
                    </form>
                </div>
            )}
        </div>
    );
}

function EmployeeHandoverCard({
    item,
    caseId,
}: {
    item: Handover;
    caseId: number;
}) {
    const submitForm = useForm({
        submission_notes: item.submission_notes ?? '',
    });

    return (
        <div className="rounded-lg border p-3 text-sm">
            <div className="flex justify-between gap-2">
                <div>
                    <div className="font-medium">{item.title}</div>
                    <div className="text-muted-foreground">
                        Penerima: {item.recipient?.name ?? 'Penyelia'}
                    </div>
                </div>
                <StatusBadge status={item.status} />
            </div>
            <p className="mt-2 text-muted-foreground">{item.description}</p>
            {item.review_notes && (
                <p className="mt-2 text-red-600">
                    Catatan: {item.review_notes}
                </p>
            )}
            {['pending', 'rejected'].includes(item.status) && (
                <form
                    className="mt-3 space-y-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        submitForm.patch(
                            `/pengakhiran-saya/${caseId}/serahan-tugas/${item.id}/hantar`,
                            { preserveScroll: true },
                        );
                    }}
                >
                    <Textarea
                        rows={2}
                        value={submitForm.data.submission_notes}
                        onChange={(event) =>
                            submitForm.setData(
                                'submission_notes',
                                event.target.value,
                            )
                        }
                        placeholder="Catatan serahan"
                    />
                    <Button size="sm">Hantar Serahan</Button>
                </form>
            )}
        </div>
    );
}

function HandoverSection({ separationCase }: { separationCase: Case }) {
    const form = useForm({ title: '', description: '', due_date: '' });

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2 text-base">
                    <Handshake className="size-4" /> Serahan Tugas
                </CardTitle>
                <CardDescription>
                    Senaraikan projek, fail, akses atau tindakan yang perlu
                    diterima pegawai pengganti.
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                {separationCase.handovers.map((item) => (
                    <EmployeeHandoverCard
                        key={item.id}
                        item={item}
                        caseId={separationCase.id}
                    />
                ))}
                <Separator />
                <form
                    className="grid gap-3 md:grid-cols-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(
                            `/pengakhiran-saya/${separationCase.id}/serahan-tugas`,
                            {
                                preserveScroll: true,
                                onSuccess: () => form.reset(),
                            },
                        );
                    }}
                >
                    <Input
                        value={form.data.title}
                        onChange={(event) =>
                            form.setData('title', event.target.value)
                        }
                        placeholder="Tajuk projek atau tanggungjawab"
                    />
                    <Input
                        type="date"
                        value={form.data.due_date}
                        onChange={(event) =>
                            form.setData('due_date', event.target.value)
                        }
                    />
                    <Textarea
                        className="md:col-span-2"
                        rows={3}
                        value={form.data.description}
                        onChange={(event) =>
                            form.setData('description', event.target.value)
                        }
                        placeholder="Butiran fail, status semasa dan tindakan seterusnya"
                    />
                    {Object.keys(form.errors).length > 0 && (
                        <p className="text-sm text-red-600 md:col-span-2">
                            {Object.values(form.errors)[0]}
                        </p>
                    )}
                    <Button
                        className="w-fit"
                        size="sm"
                        disabled={form.processing}
                    >
                        <Plus /> Tambah Item Serahan
                    </Button>
                </form>
            </CardContent>
        </Card>
    );
}

function ExitInterviewForm({ separationCase }: { separationCase: Case }) {
    const form = useForm({
        primary_reason: '',
        employment_experience_rating: '4',
        manager_support_rating: '4',
        would_recommend: true,
        positive_feedback: '',
        improvement_feedback: '',
        additional_feedback: '',
    });

    if (!separationCase.interview) {
        return null;
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">Exit Interview</CardTitle>
                <CardDescription>
                    Maklum balas ini hanya boleh dilihat oleh HR. Jadual:{' '}
                    {formatDate(separationCase.interview.scheduled_at, true)}
                </CardDescription>
            </CardHeader>
            <CardContent>
                {separationCase.interview.employee_submitted_at ? (
                    <div className="flex items-center gap-2 text-sm text-emerald-700">
                        <CheckCircle2 className="size-4" /> Dihantar pada{' '}
                        {formatDate(
                            separationCase.interview.employee_submitted_at,
                            true,
                        )}
                    </div>
                ) : (
                    <form
                        className="grid gap-4 md:grid-cols-2"
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.put(
                                `/pengakhiran-saya/${separationCase.id}/exit-interview`,
                                { preserveScroll: true },
                            );
                        }}
                    >
                        <div className="space-y-2 md:col-span-2">
                            <Label>Sebab Utama Berhenti</Label>
                            <Input
                                value={form.data.primary_reason}
                                onChange={(event) =>
                                    form.setData(
                                        'primary_reason',
                                        event.target.value,
                                    )
                                }
                            />
                        </div>
                        <div className="space-y-2">
                            <Label>Pengalaman Keseluruhan (1–5)</Label>
                            <Input
                                type="number"
                                min={1}
                                max={5}
                                value={form.data.employment_experience_rating}
                                onChange={(event) =>
                                    form.setData(
                                        'employment_experience_rating',
                                        event.target.value,
                                    )
                                }
                            />
                        </div>
                        <div className="space-y-2">
                            <Label>Sokongan Penyelia (1–5)</Label>
                            <Input
                                type="number"
                                min={1}
                                max={5}
                                value={form.data.manager_support_rating}
                                onChange={(event) =>
                                    form.setData(
                                        'manager_support_rating',
                                        event.target.value,
                                    )
                                }
                            />
                        </div>
                        <Textarea
                            rows={3}
                            value={form.data.positive_feedback}
                            onChange={(event) =>
                                form.setData(
                                    'positive_feedback',
                                    event.target.value,
                                )
                            }
                            placeholder="Perkara yang baik"
                        />
                        <Textarea
                            rows={3}
                            value={form.data.improvement_feedback}
                            onChange={(event) =>
                                form.setData(
                                    'improvement_feedback',
                                    event.target.value,
                                )
                            }
                            placeholder="Cadangan penambahbaikan"
                        />
                        <label className="flex items-center gap-2 text-sm md:col-span-2">
                            <Checkbox
                                checked={form.data.would_recommend}
                                onCheckedChange={(checked) =>
                                    form.setData(
                                        'would_recommend',
                                        Boolean(checked),
                                    )
                                }
                            />
                            Saya akan mengesyorkan IBCO sebagai tempat kerja
                        </label>
                        <Textarea
                            className="md:col-span-2"
                            rows={3}
                            value={form.data.additional_feedback}
                            onChange={(event) =>
                                form.setData(
                                    'additional_feedback',
                                    event.target.value,
                                )
                            }
                            placeholder="Maklum balas tambahan"
                        />
                        {Object.keys(form.errors).length > 0 && (
                            <p className="text-sm text-red-600 md:col-span-2">
                                {Object.values(form.errors)[0]}
                            </p>
                        )}
                        <Button className="w-fit" disabled={form.processing}>
                            Hantar Maklum Balas
                        </Button>
                    </form>
                )}
            </CardContent>
        </Card>
    );
}

function CaseDetail({ separationCase }: { separationCase: Case }) {
    const completedTasks = separationCase.tasks.filter((task) =>
        ['completed', 'waived'].includes(task.status),
    ).length;
    const cancel = () => {
        const reason = window.prompt('Sebab membatalkan permohonan:');

        if (reason) {
            router.patch(
                `/pengakhiran-saya/${separationCase.id}/batal`,
                { reason },
                { preserveScroll: true },
            );
        }
    };

    return (
        <div className="space-y-5">
            <Card>
                <CardHeader>
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <CardTitle>{separationCase.case_number}</CardTitle>
                            <CardDescription>
                                {typeLabel[separationCase.separation_type] ??
                                    separationCase.separation_type}{' '}
                                · {separationCase.template?.name}
                            </CardDescription>
                        </div>
                        <div className="flex items-center gap-2">
                            <StatusBadge status={separationCase.status} />
                            {separationCase.status === 'pending_approval' &&
                                !separationCase.supervisor_decision && (
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={cancel}
                                    >
                                        Batal Permohonan
                                    </Button>
                                )}
                        </div>
                    </div>
                </CardHeader>
                <CardContent className="grid gap-4 text-sm md:grid-cols-4">
                    <div>
                        <div className="text-muted-foreground">
                            Tarikh Notis
                        </div>
                        <div className="font-medium">
                            {formatDate(separationCase.notice_submitted_date)}
                        </div>
                    </div>
                    <div>
                        <div className="text-muted-foreground">
                            Cadangan Akhir
                        </div>
                        <div className="font-medium">
                            {formatDate(separationCase.proposed_last_day)}
                        </div>
                    </div>
                    <div>
                        <div className="text-muted-foreground">
                            Akhir Diluluskan
                        </div>
                        <div className="font-medium">
                            {formatDate(separationCase.approved_last_day)}
                        </div>
                    </div>
                    <div>
                        <div className="text-muted-foreground">
                            Kemajuan Clearance
                        </div>
                        <div className="font-medium">
                            {completedTasks}/{separationCase.tasks.length}{' '}
                            tugasan
                        </div>
                    </div>
                    <div className="md:col-span-4">
                        <div className="text-muted-foreground">Sebab</div>
                        <p>{separationCase.reason_details}</p>
                    </div>
                    {separationCase.notice_shortfall_days > 0 && (
                        <div className="rounded-md bg-amber-50 p-3 text-amber-800 md:col-span-4 dark:bg-amber-950/30 dark:text-amber-300">
                            Kekurangan notis:{' '}
                            {separationCase.notice_shortfall_days} hari. HR akan
                            menentukan pelepasan atau potongan.
                        </div>
                    )}
                </CardContent>
            </Card>

            {separationCase.tasks.length > 0 && (
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <ClipboardCheck className="size-4" /> Checklist
                            Clearance
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {separationCase.tasks.map((task) => (
                            <TaskCard
                                key={task.id}
                                task={task}
                                caseId={separationCase.id}
                            />
                        ))}
                    </CardContent>
                </Card>
            )}

            {separationCase.assets.length > 0 && (
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <PackageCheck className="size-4" /> Pemulangan Aset
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-3 md:grid-cols-2">
                        {separationCase.assets.map((asset) => (
                            <div
                                key={asset.id}
                                className="rounded-lg border p-3 text-sm"
                            >
                                <div className="flex justify-between gap-2">
                                    <div className="font-medium">
                                        {asset.asset_name}
                                    </div>
                                    <StatusBadge status={asset.status} />
                                </div>
                                <p className="text-muted-foreground">
                                    {asset.asset_tag ??
                                        asset.serial_number ??
                                        asset.asset_type}
                                </p>
                                {Number(asset.charge_amount) > 0 && (
                                    <p className="mt-2 text-red-600">
                                        Caj: RM{' '}
                                        {Number(asset.charge_amount).toFixed(2)}
                                    </p>
                                )}
                            </div>
                        ))}
                    </CardContent>
                </Card>
            )}

            {['clearance', 'final_review'].includes(separationCase.status) && (
                <>
                    <HandoverSection separationCase={separationCase} />
                    <ExitInterviewForm separationCase={separationCase} />
                </>
            )}

            <Card>
                <CardHeader>
                    <CardTitle className="text-base">
                        Dokumen & Penyelesaian Akhir
                    </CardTitle>
                </CardHeader>
                <CardContent className="flex flex-wrap items-center gap-3 text-sm">
                    <Badge variant="outline">
                        Final settlement:{' '}
                        {statusLabel[
                            separationCase.settlement?.status ?? 'pending'
                        ] ??
                            separationCase.settlement?.status ??
                            'Belum tersedia'}
                    </Badge>
                    {separationCase.settlement?.status === 'verified' && (
                        <Badge variant="default">
                            Bersih RM{' '}
                            {Number(
                                separationCase.settlement.net_amount,
                            ).toFixed(2)}
                        </Badge>
                    )}
                    {(separationCase.acceptance_document ||
                        separationCase.clearance_document) && (
                        <Button variant="outline" size="sm" asChild>
                            <Link href="/dokumen-saya">Buka Dokumen Saya</Link>
                        </Button>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}

export default function EmployeeSeparation({
    employeeLinked,
    employee,
    templates,
    cases,
    notifications,
    unreadNotifications,
}: Props) {
    const [selectedId, setSelectedId] = useState(cases[0]?.id ?? 0);
    const selected = cases.find((item) => item.id === selectedId) ?? cases[0];

    return (
        <>
            <Head title="Pengakhiran Saya" />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Pengakhiran & Clearance Saya
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Hantar notis, jejak kelulusan, selesaikan clearance,
                            pulangkan aset dan lengkapkan exit interview.
                        </p>
                    </div>
                    <div className="flex gap-2">
                        {unreadNotifications > 0 && (
                            <Button
                                variant="outline"
                                onClick={() =>
                                    router.patch(
                                        '/pengakhiran-saya/notifikasi/dibaca',
                                    )
                                }
                            >
                                <Bell /> {unreadNotifications} Baharu
                            </Button>
                        )}
                        {employeeLinked && <NoticeForm templates={templates} />}
                    </div>
                </div>

                {!employeeLinked && (
                    <Card className="border-amber-300 dark:border-amber-800">
                        <CardContent className="p-5 text-sm text-amber-800 dark:text-amber-300">
                            Akaun anda belum dipautkan kepada rekod pekerja
                            aktif. Hubungi HR sebelum menghantar notis.
                        </CardContent>
                    </Card>
                )}

                {employee && (
                    <Card>
                        <CardContent className="grid gap-3 p-4 text-sm md:grid-cols-4">
                            <div>
                                <div className="text-muted-foreground">
                                    Pekerja
                                </div>
                                <div className="font-medium">
                                    {employee.employee_name}
                                </div>
                            </div>
                            <div>
                                <div className="text-muted-foreground">
                                    No. Pekerja
                                </div>
                                <div className="font-medium">
                                    {employee.employee_number ?? '-'}
                                </div>
                            </div>
                            <div>
                                <div className="text-muted-foreground">
                                    Jabatan
                                </div>
                                <div className="font-medium">
                                    {employee.department_name ?? '-'}
                                </div>
                            </div>
                            <div>
                                <div className="text-muted-foreground">
                                    Jawatan
                                </div>
                                <div className="font-medium">
                                    {employee.position_name ?? '-'}
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {notifications.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Notifikasi Terkini
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-2 md:grid-cols-2">
                            {notifications.slice(0, 6).map((notification) => (
                                <button
                                    key={notification.id}
                                    className="rounded-lg border p-3 text-left text-sm hover:bg-muted"
                                    onClick={() =>
                                        notification.case_id &&
                                        setSelectedId(notification.case_id)
                                    }
                                >
                                    <div className="font-medium">
                                        {notification.title}
                                    </div>
                                    <p className="text-muted-foreground">
                                        {notification.message}
                                    </p>
                                </button>
                            ))}
                        </CardContent>
                    </Card>
                )}

                {cases.length > 1 && (
                    <div className="flex flex-wrap gap-2">
                        {cases.map((item) => (
                            <Button
                                key={item.id}
                                variant={
                                    selected?.id === item.id
                                        ? 'default'
                                        : 'outline'
                                }
                                onClick={() => setSelectedId(item.id)}
                            >
                                {item.case_number}
                            </Button>
                        ))}
                    </div>
                )}

                {selected ? (
                    <CaseDetail separationCase={selected} />
                ) : (
                    <Card>
                        <CardContent className="p-12 text-center text-sm text-muted-foreground">
                            <LogOut className="mx-auto mb-3 size-8" />
                            Tiada notis pengakhiran direkodkan.
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

EmployeeSeparation.layout = {
    breadcrumbs: [{ title: 'Pengakhiran Saya', href: '/pengakhiran-saya' }],
};
