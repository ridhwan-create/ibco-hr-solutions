import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    Bell,
    CheckCircle2,
    ClipboardCheck,
    Download,
    FilePlus2,
    FileUp,
    Handshake,
    LogOut,
    PackageCheck,
    Plus,
    Search,
    WalletCards,
} from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
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
type EmployeeOption = {
    id: number;
    user_id: number;
    employee_number: string | null;
    name: string;
    department_name: string | null;
    position_name: string | null;
};
type Template = {
    id: number;
    name: string;
    description: string | null;
    separation_type: string | null;
    minimum_notice_days: number;
    exit_interview_required: boolean;
    final_settlement_required: boolean;
    approver_user_id: number | null;
    items_count: number;
};
type Attachment = {
    id: number;
    context: string;
    original_name: string;
    size: number;
    visible_to_employee: boolean;
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
    can_act: boolean;
    assignee: UserOption | null;
    attachments: Attachment[];
};
type Asset = {
    id: number;
    asset_type: string;
    asset_name: string;
    asset_tag: string | null;
    serial_number: string | null;
    expected_return_date: string | null;
    status: string;
    return_condition: string | null;
    estimated_value: string;
    charge_amount: string;
    notes: string | null;
};
type Handover = {
    id: number;
    title: string;
    description: string;
    due_date: string | null;
    status: string;
    submission_notes: string | null;
    review_notes: string | null;
    recipient_user_id: number | null;
    recipient: UserOption | null;
    can_review: boolean;
};
type Settlement = {
    id: number;
    salary_due: string;
    leave_encashment: string;
    gratuity: string;
    claims_due: string;
    other_payments: string;
    notice_deduction: string;
    asset_deduction: string;
    loan_deduction: string;
    other_deductions: string;
    net_amount: string;
    notes: string | null;
    status: string;
    prepared_by: number | null;
    verified_at: string | null;
};
type ExitInterview = {
    id: number;
    interviewer_user_id: number | null;
    scheduled_at: string | null;
    employee_submitted_at: string | null;
    completed_at: string | null;
    primary_reason?: string | null;
    employment_experience_rating?: number | null;
    manager_support_rating?: number | null;
    would_recommend?: boolean | null;
    positive_feedback?: string | null;
    improvement_feedback?: string | null;
    additional_feedback?: string | null;
    hr_private_notes?: string | null;
    interviewer?: UserOption | null;
};
type CaseListItem = {
    id: number;
    case_number: string;
    employee_name: string;
    employee_number: string | null;
    department_name: string | null;
    separation_type: string;
    status: string;
    approval_stage: string | null;
    approved_last_day: string | null;
    clearance_due_date: string | null;
    template: string | null;
    supervisor: string | null;
    hr_approver: string | null;
    created_at: string;
};
type SeparationCase = Omit<
    CaseListItem,
    'template' | 'supervisor' | 'hr_approver'
> & {
    employee_user_id: number | null;
    department_id: number | null;
    position_name: string | null;
    initiated_by_employee: boolean;
    reason_category: string | null;
    reason_details: string;
    notice_submitted_date: string;
    proposed_last_day: string;
    notice_days_required: number;
    notice_days_served: number;
    notice_shortfall_days: number;
    notice_waived: boolean;
    waiver_notes: string | null;
    supervisor_user_id: number | null;
    supervisor_decision: string | null;
    supervisor_notes: string | null;
    hr_approver_user_id: number | null;
    hr_decision: string | null;
    hr_notes: string | null;
    clearance_started_at: string | null;
    eligible_for_rehire: boolean | null;
    closure_notes: string | null;
    completed_at: string | null;
    template: {
        id: number;
        name: string;
        exit_interview_required: boolean;
        final_settlement_required: boolean;
    } | null;
    supervisor: UserOption | null;
    hr_approver: UserOption | null;
    tasks: Task[];
    attachments: Attachment[];
    assets: Asset[];
    handovers: Handover[];
    interview: ExitInterview | null;
    settlement: Settlement | { status: string } | null;
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
    completion_blockers: string[];
};
type Paginator<T> = {
    data: T[];
    total: number;
    current_page: number;
    last_page: number;
    links: { url: string | null; label: string; active: boolean }[];
};
type Props = {
    cases: Paginator<CaseListItem>;
    selectedCase: SeparationCase | null;
    templates: Template[];
    employees: EmployeeOption[];
    supervisors: UserOption[];
    approvers: UserOption[];
    clearanceUsers: UserOption[];
    types: string[];
    filters: { search: string; status: string; type: string };
    statistics: {
        total: number;
        pending: number;
        clearance: number;
        overdue: number;
        completed: number;
    };
    permissions: {
        manage: boolean;
        supervise: boolean;
        approve: boolean;
        clearance: boolean;
    };
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
    draft: 'Draf',
    pending_approval: 'Menunggu Kelulusan',
    approved: 'Diluluskan',
    clearance: 'Dalam Clearance',
    final_review: 'Semakan Akhir',
    completed: 'Selesai',
    rejected: 'Ditolak',
    cancelled: 'Dibatalkan',
    pending: 'Belum Mula',
    in_progress: 'Sedang Dibuat',
    submitted: 'Menunggu Semakan',
    waived: 'Dikecualikan',
    accepted: 'Diterima',
    returned: 'Dipulangkan',
    damaged: 'Rosak',
    lost: 'Hilang',
    pending_verification: 'Menunggu Pengesahan',
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
const ownerLabel: Record<string, string> = {
    employee: 'Pekerja',
    supervisor: 'Penyelia',
    hr: 'HR',
    finance: 'Kewangan',
    ict: 'ICT',
    administration: 'Pentadbiran',
    payroll: 'Payroll',
    custom: 'Khusus',
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
function money(value: string | number | null | undefined) {
    return `RM ${Number(value ?? 0).toLocaleString('ms-MY', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
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
function Field({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="space-y-2">
            <Label>{label}</Label>
            {children}
        </div>
    );
}
function ErrorText({ errors }: { errors: Record<string, string> }) {
    const message = Object.values(errors)[0];

    return message ? <p className="text-sm text-red-600">{message}</p> : null;
}

function CreateCaseDialog({
    templates,
    employees,
    supervisors,
    approvers,
    types,
}: {
    templates: Template[];
    employees: EmployeeOption[];
    supervisors: UserOption[];
    approvers: UserOption[];
    types: string[];
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        separation_template_id: '',
        employee_user_id: '',
        separation_type: 'contract_end',
        reason_category: '',
        reason_details: '',
        notice_submitted_date: new Date().toISOString().slice(0, 10),
        proposed_last_day: '',
        supervisor_user_id: 'none',
        hr_approver_user_id: '',
    });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            supervisor_user_id:
                data.supervisor_user_id === 'none'
                    ? null
                    : data.supervisor_user_id,
        }));
        form.post('/berhenti-clearance', {
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
                <Button>
                    <Plus /> Kes Pengakhiran
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>Kes Pengakhiran Baharu</DialogTitle>
                    <DialogDescription>
                        Gunakan untuk tamat kontrak, persaraan, penamatan atau
                        kes yang dimulakan oleh HR. Pencipta kes tidak boleh
                        menjadi pelulusnya.
                    </DialogDescription>
                </DialogHeader>
                <form className="grid gap-4 md:grid-cols-2" onSubmit={submit}>
                    <Field label="Template Clearance">
                        <Select
                            value={form.data.separation_template_id}
                            onValueChange={(value) => {
                                form.setData('separation_template_id', value);
                                const template = templates.find(
                                    (item) => String(item.id) === value,
                                );

                                if (template?.separation_type) {
                                    form.setData(
                                        'separation_type',
                                        template.separation_type,
                                    );
                                }
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
                                        {template.name} ({template.items_count}{' '}
                                        item)
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </Field>
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
                                        {employee.name} ·{' '}
                                        {employee.employee_number}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </Field>
                    <Field label="Jenis Pengakhiran">
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
                    </Field>
                    <Field label="Kategori Sebab">
                        <Input
                            value={form.data.reason_category}
                            onChange={(event) =>
                                form.setData(
                                    'reason_category',
                                    event.target.value,
                                )
                            }
                        />
                    </Field>
                    <Field label="Tarikh Notis / Keputusan">
                        <Input
                            type="date"
                            value={form.data.notice_submitted_date}
                            onChange={(event) =>
                                form.setData(
                                    'notice_submitted_date',
                                    event.target.value,
                                )
                            }
                        />
                    </Field>
                    <Field label="Cadangan Hari Terakhir">
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
                    </Field>
                    <Field label="Penyelia Semakan">
                        <Select
                            value={form.data.supervisor_user_id}
                            onValueChange={(value) =>
                                form.setData('supervisor_user_id', value)
                            }
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">
                                    Terus ke HR
                                </SelectItem>
                                {supervisors.map((user) => (
                                    <SelectItem
                                        key={user.id}
                                        value={String(user.id)}
                                    >
                                        {user.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </Field>
                    <Field label="Pelulus HR">
                        <Select
                            value={form.data.hr_approver_user_id}
                            onValueChange={(value) =>
                                form.setData('hr_approver_user_id', value)
                            }
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Pilih pelulus" />
                            </SelectTrigger>
                            <SelectContent>
                                {approvers.map((user) => (
                                    <SelectItem
                                        key={user.id}
                                        value={String(user.id)}
                                    >
                                        {user.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </Field>
                    <div className="space-y-2 md:col-span-2">
                        <Label>Sebab dan Butiran</Label>
                        <Textarea
                            rows={4}
                            value={form.data.reason_details}
                            onChange={(event) =>
                                form.setData(
                                    'reason_details',
                                    event.target.value,
                                )
                            }
                        />
                    </div>
                    <ErrorText errors={form.errors} />
                    <div className="flex justify-end md:col-span-2">
                        <Button disabled={form.processing}>Cipta Draf</Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function SupervisorReview({
    separationCase,
}: {
    separationCase: SeparationCase;
}) {
    const form = useForm({ action: 'support', notes: '' });

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">Semakan Penyelia</CardTitle>
                <CardDescription>
                    Sahkan serahan tugas dan kesesuaian tarikh sebelum dihantar
                    kepada HR.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form
                    className="grid gap-3 md:grid-cols-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.patch(
                            `/berhenti-clearance/${separationCase.id}/semakan-penyelia`,
                            { preserveScroll: true },
                        );
                    }}
                >
                    <Select
                        value={form.data.action}
                        onValueChange={(value) => form.setData('action', value)}
                    >
                        <SelectTrigger>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="support">Sokong</SelectItem>
                            <SelectItem value="reject">Tolak</SelectItem>
                        </SelectContent>
                    </Select>
                    <Textarea
                        rows={3}
                        value={form.data.notes}
                        onChange={(event) =>
                            form.setData('notes', event.target.value)
                        }
                        placeholder="Catatan semakan"
                    />
                    <ErrorText errors={form.errors} />
                    <Button className="w-fit" disabled={form.processing}>
                        Simpan Semakan
                    </Button>
                </form>
            </CardContent>
        </Card>
    );
}

function HrApproval({ separationCase }: { separationCase: SeparationCase }) {
    const form = useForm({
        action: 'approve',
        approved_last_day:
            separationCase.approved_last_day ??
            separationCase.proposed_last_day,
        notice_waived: false,
        waiver_notes: '',
        notes: '',
    });

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">
                    Kelulusan Pengurus HR
                </CardTitle>
                <CardDescription>
                    Kelulusan akan menjana checklist clearance secara automatik.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form
                    className="grid gap-4 md:grid-cols-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.patch(
                            `/berhenti-clearance/${separationCase.id}/kelulusan-hr`,
                            { preserveScroll: true },
                        );
                    }}
                >
                    <Field label="Keputusan">
                        <Select
                            value={form.data.action}
                            onValueChange={(value) =>
                                form.setData('action', value)
                            }
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="approve">Lulus</SelectItem>
                                <SelectItem value="reject">Tolak</SelectItem>
                            </SelectContent>
                        </Select>
                    </Field>
                    <Field label="Hari Terakhir Diluluskan">
                        <Input
                            type="date"
                            value={form.data.approved_last_day}
                            onChange={(event) =>
                                form.setData(
                                    'approved_last_day',
                                    event.target.value,
                                )
                            }
                        />
                    </Field>
                    <label className="flex items-center gap-2 rounded-lg border p-3 text-sm">
                        <Checkbox
                            checked={form.data.notice_waived}
                            onCheckedChange={(checked) =>
                                form.setData('notice_waived', Boolean(checked))
                            }
                        />
                        Lepaskan kekurangan notis
                    </label>
                    <Textarea
                        rows={3}
                        value={form.data.waiver_notes}
                        onChange={(event) =>
                            form.setData('waiver_notes', event.target.value)
                        }
                        placeholder="Sebab pelepasan notis"
                    />
                    <Textarea
                        className="md:col-span-2"
                        rows={3}
                        value={form.data.notes}
                        onChange={(event) =>
                            form.setData('notes', event.target.value)
                        }
                        placeholder="Catatan keputusan HR"
                    />
                    <ErrorText errors={form.errors} />
                    <Button className="w-fit" disabled={form.processing}>
                        Simpan Keputusan
                    </Button>
                </form>
            </CardContent>
        </Card>
    );
}

function TaskCard({
    task,
    caseId,
    clearanceUsers,
    canManage,
}: {
    task: Task;
    caseId: number;
    clearanceUsers: UserOption[];
    canManage: boolean;
}) {
    const actionForm = useForm({
        action: 'complete',
        assigned_user_id: task.assigned_user_id
            ? String(task.assigned_user_id)
            : '',
        notes: '',
    });
    const fileForm = useForm({
        context: 'task_evidence',
        attachment: null as File | null,
        visible_to_employee: true,
    });

    return (
        <div className="space-y-3 rounded-lg border p-4">
            <div className="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <div className="font-medium">{task.title}</div>
                    <p className="text-xs text-muted-foreground">
                        {ownerLabel[task.owner_type] ?? task.owner_type} ·{' '}
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
            {task.submission_notes && (
                <p className="rounded-md bg-muted p-2 text-sm">
                    Serahan pekerja: {task.submission_notes}
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
                                href={`/berhenti-clearance/${caseId}/lampiran/${attachment.id}`}
                            >
                                {attachment.original_name}
                            </a>
                        </Button>
                    ))}
                </div>
            )}
            {task.can_act && (
                <form
                    className="grid gap-2 border-t pt-3 md:grid-cols-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        actionForm.patch(
                            `/berhenti-clearance/${caseId}/tugasan/${task.id}`,
                            { preserveScroll: true },
                        );
                    }}
                >
                    <Select
                        value={actionForm.data.action}
                        onValueChange={(value) =>
                            actionForm.setData('action', value)
                        }
                    >
                        <SelectTrigger>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {canManage && (
                                <SelectItem value="assign">Tugaskan</SelectItem>
                            )}
                            <SelectItem value="start">Mula</SelectItem>
                            <SelectItem value="complete">Selesai</SelectItem>
                            <SelectItem value="reject">Kembalikan</SelectItem>
                            {canManage && (
                                <SelectItem value="waive">
                                    Kecualikan
                                </SelectItem>
                            )}
                            {canManage && (
                                <SelectItem value="reopen">
                                    Buka Semula
                                </SelectItem>
                            )}
                        </SelectContent>
                    </Select>
                    {actionForm.data.action === 'assign' ? (
                        <Select
                            value={actionForm.data.assigned_user_id}
                            onValueChange={(value) =>
                                actionForm.setData('assigned_user_id', value)
                            }
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Pilih pegawai" />
                            </SelectTrigger>
                            <SelectContent>
                                {clearanceUsers.map((user) => (
                                    <SelectItem
                                        key={user.id}
                                        value={String(user.id)}
                                    >
                                        {user.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    ) : (
                        <Input
                            className="md:col-span-2"
                            value={actionForm.data.notes}
                            onChange={(event) =>
                                actionForm.setData('notes', event.target.value)
                            }
                            placeholder="Catatan tindakan"
                        />
                    )}
                    <Button size="sm" disabled={actionForm.processing}>
                        Simpan
                    </Button>
                    <ErrorText errors={actionForm.errors} />
                </form>
            )}
            {task.can_act && (
                <form
                    className="flex flex-wrap items-center gap-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        fileForm.post(
                            `/berhenti-clearance/${caseId}/tugasan/${task.id}/lampiran`,
                            {
                                forceFormData: true,
                                preserveScroll: true,
                                onSuccess: () => fileForm.reset('attachment'),
                            },
                        );
                    }}
                >
                    <Input
                        className="max-w-sm"
                        type="file"
                        onChange={(event) =>
                            fileForm.setData(
                                'attachment',
                                event.target.files?.[0] ?? null,
                            )
                        }
                    />
                    <label className="flex items-center gap-2 text-xs">
                        <Checkbox
                            checked={fileForm.data.visible_to_employee}
                            onCheckedChange={(checked) =>
                                fileForm.setData(
                                    'visible_to_employee',
                                    Boolean(checked),
                                )
                            }
                        />
                        Papar kepada pekerja
                    </label>
                    <Button
                        size="sm"
                        variant="outline"
                        disabled={
                            fileForm.processing || !fileForm.data.attachment
                        }
                    >
                        <FileUp /> Bukti
                    </Button>
                </form>
            )}
        </div>
    );
}

function AssetsSection({
    separationCase,
    canManage,
}: {
    separationCase: SeparationCase;
    canManage: boolean;
}) {
    const form = useForm({
        asset_type: 'ict',
        asset_name: '',
        asset_tag: '',
        serial_number: '',
        issued_date: '',
        expected_return_date:
            separationCase.approved_last_day ??
            separationCase.proposed_last_day,
        estimated_value: '0',
        notes: '',
    });

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2 text-base">
                    <PackageCheck className="size-4" /> Aset & Pemulangan
                </CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
                <div className="grid gap-3 md:grid-cols-2">
                    {separationCase.assets.map((asset) => (
                        <AssetCard
                            key={asset.id}
                            asset={asset}
                            caseId={separationCase.id}
                            canManage={canManage}
                        />
                    ))}
                </div>
                {canManage && (
                    <>
                        <Separator />
                        <form
                            className="grid gap-3 md:grid-cols-4"
                            onSubmit={(event) => {
                                event.preventDefault();
                                form.post(
                                    `/berhenti-clearance/${separationCase.id}/aset`,
                                    {
                                        preserveScroll: true,
                                        onSuccess: () =>
                                            form.reset(
                                                'asset_name',
                                                'asset_tag',
                                                'serial_number',
                                                'notes',
                                            ),
                                    },
                                );
                            }}
                        >
                            <Input
                                value={form.data.asset_type}
                                onChange={(event) =>
                                    form.setData(
                                        'asset_type',
                                        event.target.value,
                                    )
                                }
                                placeholder="Jenis aset"
                            />
                            <Input
                                value={form.data.asset_name}
                                onChange={(event) =>
                                    form.setData(
                                        'asset_name',
                                        event.target.value,
                                    )
                                }
                                placeholder="Nama aset"
                            />
                            <Input
                                value={form.data.asset_tag}
                                onChange={(event) =>
                                    form.setData(
                                        'asset_tag',
                                        event.target.value,
                                    )
                                }
                                placeholder="Tag aset"
                            />
                            <Input
                                value={form.data.serial_number}
                                onChange={(event) =>
                                    form.setData(
                                        'serial_number',
                                        event.target.value,
                                    )
                                }
                                placeholder="Nombor siri"
                            />
                            <Input
                                type="date"
                                value={form.data.expected_return_date}
                                onChange={(event) =>
                                    form.setData(
                                        'expected_return_date',
                                        event.target.value,
                                    )
                                }
                            />
                            <Input
                                type="number"
                                min={0}
                                step="0.01"
                                value={form.data.estimated_value}
                                onChange={(event) =>
                                    form.setData(
                                        'estimated_value',
                                        event.target.value,
                                    )
                                }
                                placeholder="Nilai"
                            />
                            <Input
                                className="md:col-span-2"
                                value={form.data.notes}
                                onChange={(event) =>
                                    form.setData('notes', event.target.value)
                                }
                                placeholder="Catatan"
                            />
                            <ErrorText errors={form.errors} />
                            <Button className="w-fit" size="sm">
                                <Plus /> Tambah Aset
                            </Button>
                        </form>
                    </>
                )}
            </CardContent>
        </Card>
    );
}

function AssetCard({
    asset,
    caseId,
    canManage,
}: {
    asset: Asset;
    caseId: number;
    canManage: boolean;
}) {
    const form = useForm({
        status: asset.status,
        return_condition: asset.return_condition ?? 'good',
        charge_amount: asset.charge_amount ?? '0',
        notes: asset.notes ?? '',
    });

    return (
        <div className="space-y-3 rounded-lg border p-3 text-sm">
            <div className="flex justify-between gap-2">
                <div>
                    <div className="font-medium">{asset.asset_name}</div>
                    <div className="text-muted-foreground">
                        {asset.asset_tag ??
                            asset.serial_number ??
                            asset.asset_type}
                    </div>
                </div>
                <StatusBadge status={asset.status} />
            </div>
            <div className="text-muted-foreground">
                Nilai {money(asset.estimated_value)} · Caj{' '}
                {money(asset.charge_amount)}
            </div>
            {canManage && (
                <form
                    className="grid gap-2 md:grid-cols-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.patch(
                            `/berhenti-clearance/${caseId}/aset/${asset.id}`,
                            { preserveScroll: true },
                        );
                    }}
                >
                    <Select
                        value={form.data.status}
                        onValueChange={(value) => form.setData('status', value)}
                    >
                        <SelectTrigger>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {[
                                'pending',
                                'returned',
                                'damaged',
                                'lost',
                                'waived',
                            ].map((status) => (
                                <SelectItem key={status} value={status}>
                                    {statusLabel[status] ?? status}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Select
                        value={form.data.return_condition}
                        onValueChange={(value) =>
                            form.setData('return_condition', value)
                        }
                    >
                        <SelectTrigger>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="good">Baik</SelectItem>
                            <SelectItem value="fair">Memuaskan</SelectItem>
                            <SelectItem value="damaged">Rosak</SelectItem>
                            <SelectItem value="not_returned">
                                Tidak Dipulangkan
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <Input
                        type="number"
                        min={0}
                        step="0.01"
                        value={form.data.charge_amount}
                        onChange={(event) =>
                            form.setData('charge_amount', event.target.value)
                        }
                        placeholder="Caj"
                    />
                    <Input
                        value={form.data.notes}
                        onChange={(event) =>
                            form.setData('notes', event.target.value)
                        }
                        placeholder="Catatan"
                    />
                    <Button size="sm" className="w-fit">
                        Simpan Aset
                    </Button>
                </form>
            )}
        </div>
    );
}

function HandoverCard({
    item,
    caseId,
    canReview,
    canWaive,
}: {
    item: Handover;
    caseId: number;
    canReview: boolean;
    canWaive: boolean;
}) {
    const form = useForm({
        action: item.status === 'submitted' ? 'accept' : 'waive',
        notes: '',
    });

    return (
        <div className="space-y-3 rounded-lg border p-3 text-sm">
            <div className="flex justify-between gap-2">
                <div>
                    <div className="font-medium">{item.title}</div>
                    <div className="text-muted-foreground">
                        Penerima: {item.recipient?.name ?? '-'}
                    </div>
                </div>
                <StatusBadge status={item.status} />
            </div>
            <p className="text-muted-foreground">{item.description}</p>
            {item.submission_notes && <p>Catatan: {item.submission_notes}</p>}
            {canReview &&
                (item.status === 'submitted' ||
                    (canWaive && item.status === 'pending')) && (
                    <form
                        className="grid gap-2 md:grid-cols-3"
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.patch(
                                `/berhenti-clearance/${caseId}/serahan-tugas/${item.id}`,
                                { preserveScroll: true },
                            );
                        }}
                    >
                        <Select
                            value={form.data.action}
                            onValueChange={(value) =>
                                form.setData('action', value)
                            }
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {item.status === 'submitted' && (
                                    <>
                                        <SelectItem value="accept">
                                            Terima
                                        </SelectItem>
                                        <SelectItem value="reject">
                                            Kembalikan
                                        </SelectItem>
                                    </>
                                )}
                                {canWaive && (
                                    <SelectItem value="waive">
                                        Kecualikan
                                    </SelectItem>
                                )}
                            </SelectContent>
                        </Select>
                        <Input
                            value={form.data.notes}
                            onChange={(event) =>
                                form.setData('notes', event.target.value)
                            }
                            placeholder="Catatan semakan"
                        />
                        <Button size="sm">Simpan</Button>
                    </form>
                )}
        </div>
    );
}

function InterviewSection({
    separationCase,
    canManage,
}: {
    separationCase: SeparationCase;
    canManage: boolean;
}) {
    const interview = separationCase.interview;
    const form = useForm({
        interviewer_user_id: interview?.interviewer_user_id
            ? String(interview.interviewer_user_id)
            : '',
        scheduled_at: interview?.scheduled_at?.slice(0, 16) ?? '',
        hr_private_notes: interview?.hr_private_notes ?? '',
        completed: Boolean(interview?.completed_at),
    });

    if (!interview) {
        return null;
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">Exit Interview</CardTitle>
                <CardDescription>
                    Maklum balas pekerja diklasifikasikan sebagai sulit HR.
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                <div className="grid gap-3 text-sm md:grid-cols-3">
                    <div>
                        <div className="text-muted-foreground">Jadual</div>
                        <div>{formatDate(interview.scheduled_at, true)}</div>
                    </div>
                    <div>
                        <div className="text-muted-foreground">
                            Maklum Balas
                        </div>
                        <div>
                            {formatDate(interview.employee_submitted_at, true)}
                        </div>
                    </div>
                    <div>
                        <div className="text-muted-foreground">Selesai</div>
                        <div>{formatDate(interview.completed_at, true)}</div>
                    </div>
                </div>
                {interview.employee_submitted_at &&
                    interview.primary_reason && (
                        <div className="rounded-lg border p-3 text-sm">
                            <div className="font-medium">
                                Sebab utama: {interview.primary_reason}
                            </div>
                            <p className="text-muted-foreground">
                                Pengalaman{' '}
                                {interview.employment_experience_rating}/5 ·
                                Sokongan penyelia{' '}
                                {interview.manager_support_rating}/5
                            </p>
                            {interview.improvement_feedback && (
                                <p className="mt-2">
                                    Penambahbaikan:{' '}
                                    {interview.improvement_feedback}
                                </p>
                            )}
                        </div>
                    )}
                {canManage && (
                    <form
                        className="grid gap-3 md:grid-cols-2"
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.put(
                                `/berhenti-clearance/${separationCase.id}/exit-interview`,
                                { preserveScroll: true },
                            );
                        }}
                    >
                        <Input
                            type="datetime-local"
                            value={form.data.scheduled_at}
                            onChange={(event) =>
                                form.setData('scheduled_at', event.target.value)
                            }
                        />
                        <label className="flex items-center gap-2 rounded-lg border p-3 text-sm">
                            <Checkbox
                                checked={form.data.completed}
                                onCheckedChange={(checked) =>
                                    form.setData('completed', Boolean(checked))
                                }
                            />
                            Tandakan exit interview selesai
                        </label>
                        <Textarea
                            className="md:col-span-2"
                            rows={4}
                            value={form.data.hr_private_notes}
                            onChange={(event) =>
                                form.setData(
                                    'hr_private_notes',
                                    event.target.value,
                                )
                            }
                            placeholder="Catatan sulit HR"
                        />
                        <ErrorText errors={form.errors} />
                        <Button className="w-fit">Simpan Exit Interview</Button>
                    </form>
                )}
            </CardContent>
        </Card>
    );
}

function SettlementSection({
    separationCase,
    canManage,
    canApprove,
}: {
    separationCase: SeparationCase;
    canManage: boolean;
    canApprove: boolean;
}) {
    const settlement = separationCase.settlement;

    if (!settlement || !('id' in settlement)) {
        return null;
    }

    return (
        <SettlementForm
            settlement={settlement}
            caseId={separationCase.id}
            canManage={canManage}
            canApprove={canApprove}
        />
    );
}

function SettlementForm({
    settlement,
    caseId,
    canManage,
    canApprove,
}: {
    settlement: Settlement;
    caseId: number;
    canManage: boolean;
    canApprove: boolean;
}) {
    const form = useForm({
        salary_due: settlement.salary_due,
        leave_encashment: settlement.leave_encashment,
        gratuity: settlement.gratuity,
        claims_due: settlement.claims_due,
        other_payments: settlement.other_payments,
        notice_deduction: settlement.notice_deduction,
        loan_deduction: settlement.loan_deduction,
        other_deductions: settlement.other_deductions,
        notes: settlement.notes ?? '',
        submit: false,
    });
    const fields: [keyof typeof form.data, string][] = [
        ['salary_due', 'Gaji Belum Dibayar'],
        ['leave_encashment', 'Tunai Baki Cuti'],
        ['gratuity', 'Ganjaran'],
        ['claims_due', 'Tuntutan Belum Dibayar'],
        ['other_payments', 'Bayaran Lain'],
        ['notice_deduction', 'Potongan Notis'],
        ['loan_deduction', 'Potongan Pinjaman'],
        ['other_deductions', 'Potongan Lain'],
    ];

    return (
        <Card>
            <CardHeader>
                <div className="flex justify-between gap-3">
                    <div>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <WalletCards className="size-4" /> Final Settlement
                        </CardTitle>
                        <CardDescription>
                            Rekod ini tidak menulis ke payroll atau pangkalan
                            data pekerja asal.
                        </CardDescription>
                    </div>
                    <StatusBadge status={settlement.status} />
                </div>
            </CardHeader>
            <CardContent className="space-y-4">
                <div className="grid gap-3 text-sm md:grid-cols-3">
                    <div className="rounded-lg border p-3">
                        Potongan aset
                        <div className="font-semibold">
                            {money(settlement.asset_deduction)}
                        </div>
                    </div>
                    <div className="rounded-lg border p-3">
                        Jumlah bersih
                        <div className="font-semibold">
                            {money(settlement.net_amount)}
                        </div>
                    </div>
                    <div className="rounded-lg border p-3">
                        Disahkan
                        <div className="font-semibold">
                            {formatDate(settlement.verified_at, true)}
                        </div>
                    </div>
                </div>
                {canManage && settlement.status !== 'verified' && (
                    <form
                        className="grid gap-3 md:grid-cols-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.transform((data) => ({
                                ...data,
                                submit: false,
                            }));
                            form.put(
                                `/berhenti-clearance/${caseId}/final-settlement`,
                                { preserveScroll: true },
                            );
                        }}
                    >
                        {fields.map(([field, label]) => (
                            <Field key={field} label={label}>
                                <Input
                                    type="number"
                                    min={0}
                                    step="0.01"
                                    value={String(form.data[field])}
                                    onChange={(event) =>
                                        form.setData(field, event.target.value)
                                    }
                                />
                            </Field>
                        ))}
                        <Textarea
                            className="md:col-span-4"
                            rows={3}
                            value={form.data.notes}
                            onChange={(event) =>
                                form.setData('notes', event.target.value)
                            }
                            placeholder="Catatan pengiraan"
                        />
                        <ErrorText errors={form.errors} />
                        <div className="flex gap-2 md:col-span-4">
                            <Button
                                type="submit"
                                variant="outline"
                                disabled={form.processing}
                            >
                                Simpan Draf
                            </Button>
                            <Button
                                type="button"
                                disabled={form.processing}
                                onClick={() => {
                                    form.transform((data) => ({
                                        ...data,
                                        submit: true,
                                    }));
                                    form.put(
                                        `/berhenti-clearance/${caseId}/final-settlement`,
                                        { preserveScroll: true },
                                    );
                                }}
                            >
                                Hantar untuk Pengesahan
                            </Button>
                        </div>
                    </form>
                )}
                {canApprove && settlement.status === 'pending_verification' && (
                    <Button
                        onClick={() =>
                            router.patch(
                                `/berhenti-clearance/${caseId}/final-settlement/sahkan`,
                            )
                        }
                    >
                        <CheckCircle2 /> Sahkan Final Settlement
                    </Button>
                )}
            </CardContent>
        </Card>
    );
}

function FinalActions({
    separationCase,
    canManage,
    canApprove,
}: {
    separationCase: SeparationCase;
    canManage: boolean;
    canApprove: boolean;
}) {
    const form = useForm({ eligible_for_rehire: true, closure_notes: '' });

    if (!canManage && !canApprove) {
        return null;
    }

    return (
        <Card className="border-orange-300 dark:border-orange-800">
            <CardHeader>
                <CardTitle className="text-base">
                    Dokumen & Penutupan Kes
                </CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
                {canManage && (
                    <div className="flex flex-wrap gap-2">
                        <Button
                            variant="outline"
                            onClick={() =>
                                router.post(
                                    `/berhenti-clearance/${separationCase.id}/dokumen`,
                                    { kind: 'acceptance' },
                                )
                            }
                        >
                            <FilePlus2 /> Surat Penerimaan / Penamatan
                        </Button>
                        {separationCase.status === 'completed' && (
                            <Button
                                variant="outline"
                                onClick={() =>
                                    router.post(
                                        `/berhenti-clearance/${separationCase.id}/dokumen`,
                                        { kind: 'clearance' },
                                    )
                                }
                            >
                                <FilePlus2 /> Surat Selesai Clearance
                            </Button>
                        )}
                        {(separationCase.acceptance_document ||
                            separationCase.clearance_document) && (
                            <Button variant="outline" asChild>
                                <Link href="/dokumen-hr">Buka Dokumen HR</Link>
                            </Button>
                        )}
                    </div>
                )}
                {separationCase.completion_blockers.length > 0 && (
                    <div className="rounded-lg bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-950/30 dark:text-amber-300">
                        <div className="font-medium">Belum boleh ditutup:</div>
                        <ul className="mt-1 list-disc pl-5">
                            {separationCase.completion_blockers.map(
                                (blocker) => (
                                    <li key={blocker}>{blocker}</li>
                                ),
                            )}
                        </ul>
                    </div>
                )}
                {canApprove && separationCase.status !== 'completed' && (
                    <form
                        className="grid gap-3 md:grid-cols-2"
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.patch(
                                `/berhenti-clearance/${separationCase.id}/selesai`,
                                { preserveScroll: true },
                            );
                        }}
                    >
                        <label className="flex items-center gap-2 rounded-lg border p-3 text-sm">
                            <Checkbox
                                checked={form.data.eligible_for_rehire}
                                onCheckedChange={(checked) =>
                                    form.setData(
                                        'eligible_for_rehire',
                                        Boolean(checked),
                                    )
                                }
                            />
                            Layak dipertimbangkan untuk pengambilan semula
                        </label>
                        <Textarea
                            rows={3}
                            value={form.data.closure_notes}
                            onChange={(event) =>
                                form.setData(
                                    'closure_notes',
                                    event.target.value,
                                )
                            }
                            placeholder="Rumusan penutupan"
                        />
                        <ErrorText errors={form.errors} />
                        <Button
                            className="w-fit"
                            disabled={
                                form.processing ||
                                separationCase.completion_blockers.length > 0
                            }
                        >
                            Tutup Kes & Sahkan Clearance
                        </Button>
                    </form>
                )}
            </CardContent>
        </Card>
    );
}

function CaseDetail({
    separationCase,
    clearanceUsers,
    permissions,
}: {
    separationCase: SeparationCase;
    clearanceUsers: UserOption[];
    permissions: Props['permissions'];
}) {
    const canSupervisorReview =
        permissions.supervise &&
        separationCase.status === 'pending_approval' &&
        separationCase.approval_stage === 'supervisor';
    const canHrReview =
        permissions.approve &&
        separationCase.status === 'pending_approval' &&
        separationCase.approval_stage === 'hr';

    return (
        <div className="space-y-5">
            <Card>
                <CardHeader>
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <CardTitle>{separationCase.case_number}</CardTitle>
                            <CardDescription>
                                {separationCase.employee_name} ·{' '}
                                {separationCase.employee_number ?? '-'} ·{' '}
                                {separationCase.department_name ?? '-'}
                            </CardDescription>
                        </div>
                        <StatusBadge status={separationCase.status} />
                    </div>
                </CardHeader>
                <CardContent className="grid gap-4 text-sm md:grid-cols-4">
                    <div>
                        <div className="text-muted-foreground">Jenis</div>
                        <div className="font-medium">
                            {typeLabel[separationCase.separation_type] ??
                                separationCase.separation_type}
                        </div>
                    </div>
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
                        <div className="text-muted-foreground">Penyelia</div>
                        <div className="font-medium">
                            {separationCase.supervisor?.name ?? '-'}
                        </div>
                    </div>
                    <div>
                        <div className="text-muted-foreground">Pelulus HR</div>
                        <div className="font-medium">
                            {separationCase.hr_approver?.name ?? '-'}
                        </div>
                    </div>
                    <div>
                        <div className="text-muted-foreground">
                            Notis Diserah
                        </div>
                        <div className="font-medium">
                            {separationCase.notice_days_served} hari
                        </div>
                    </div>
                    <div>
                        <div className="text-muted-foreground">Kekurangan</div>
                        <div className="font-medium">
                            {separationCase.notice_shortfall_days} hari
                        </div>
                    </div>
                    <div className="md:col-span-4">
                        <div className="text-muted-foreground">Sebab</div>
                        <p>{separationCase.reason_details}</p>
                    </div>
                </CardContent>
            </Card>
            {permissions.manage && separationCase.status === 'draft' && (
                <Card>
                    <CardContent className="flex flex-wrap gap-2 p-4">
                        <Button
                            onClick={() =>
                                router.patch(
                                    `/berhenti-clearance/${separationCase.id}/hantar`,
                                )
                            }
                        >
                            Hantar untuk Kelulusan
                        </Button>
                        <Button
                            variant="outline"
                            onClick={() => {
                                const reason =
                                    window.prompt('Sebab pembatalan:');

                                if (reason) {
                                    router.patch(
                                        `/berhenti-clearance/${separationCase.id}/batal`,
                                        { reason },
                                    );
                                }
                            }}
                        >
                            Batal Kes
                        </Button>
                    </CardContent>
                </Card>
            )}
            {canSupervisorReview && (
                <SupervisorReview separationCase={separationCase} />
            )}
            {canHrReview && <HrApproval separationCase={separationCase} />}

            {separationCase.tasks.length > 0 && (
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <ClipboardCheck className="size-4" /> Checklist
                            Clearance
                        </CardTitle>
                        <CardDescription>
                            Tugasan wajib perlu selesai atau dikecualikan dengan
                            sebab sebelum kes boleh ditutup.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {separationCase.tasks.map((task) => (
                            <TaskCard
                                key={task.id}
                                task={task}
                                caseId={separationCase.id}
                                clearanceUsers={clearanceUsers}
                                canManage={permissions.manage}
                            />
                        ))}
                    </CardContent>
                </Card>
            )}

            {['clearance', 'final_review', 'completed'].includes(
                separationCase.status,
            ) && (
                <>
                    <AssetsSection
                        separationCase={separationCase}
                        canManage={permissions.manage}
                    />
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Handshake className="size-4" /> Serahan Tugas
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-3 md:grid-cols-2">
                            {separationCase.handovers.length > 0 ? (
                                separationCase.handovers.map((item) => (
                                    <HandoverCard
                                        key={item.id}
                                        item={item}
                                        caseId={separationCase.id}
                                        canReview={item.can_review}
                                        canWaive={permissions.manage}
                                    />
                                ))
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    Pekerja belum menambah item serahan tugas.
                                </p>
                            )}
                        </CardContent>
                    </Card>
                    <InterviewSection
                        separationCase={separationCase}
                        canManage={permissions.manage}
                    />
                    <SettlementSection
                        separationCase={separationCase}
                        canManage={permissions.manage}
                        canApprove={permissions.approve}
                    />
                    <FinalActions
                        separationCase={separationCase}
                        canManage={permissions.manage}
                        canApprove={permissions.approve}
                    />
                </>
            )}
        </div>
    );
}

export default function SeparationsIndex({
    cases,
    selectedCase,
    templates,
    employees,
    supervisors,
    approvers,
    clearanceUsers,
    types,
    filters,
    statistics,
    permissions,
    notifications,
    unreadNotifications,
}: Props) {
    const [search, setSearch] = useState(filters.search);
    const runFilter = (extra: Record<string, string> = {}) =>
        router.get(
            '/berhenti-clearance',
            { search, status: filters.status, type: filters.type, ...extra },
            { preserveState: true, replace: true },
        );

    return (
        <>
            <Head title="Berhenti & Clearance" />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Berhenti Kerja & Clearance
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Kelulusan pengakhiran, clearance unit, aset, serahan
                            tugas, exit interview dan penyelesaian akhir.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {unreadNotifications > 0 && (
                            <Button
                                variant="outline"
                                onClick={() =>
                                    router.patch(
                                        '/berhenti-clearance/notifikasi/dibaca',
                                    )
                                }
                            >
                                <Bell /> {unreadNotifications} Baharu
                            </Button>
                        )}
                        <Button variant="outline" asChild>
                            <a href="/berhenti-clearance/laporan.csv">
                                <Download /> CSV
                            </a>
                        </Button>
                        {permissions.manage && (
                            <CreateCaseDialog
                                templates={templates}
                                employees={employees}
                                supervisors={supervisors}
                                approvers={approvers}
                                types={types}
                            />
                        )}
                    </div>
                </div>

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                    {[
                        ['Kes', statistics.total],
                        ['Kelulusan', statistics.pending],
                        ['Clearance', statistics.clearance],
                        ['Lewat', statistics.overdue],
                        ['Selesai', statistics.completed],
                    ].map(([label, value]) => (
                        <Card key={label}>
                            <CardContent className="p-4">
                                <div className="text-xs text-muted-foreground">
                                    {label}
                                </div>
                                <div className="text-2xl font-semibold">
                                    {value}
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                {notifications.length > 0 && (
                    <Card>
                        <CardContent className="flex gap-3 overflow-x-auto p-3">
                            {notifications.slice(0, 6).map((notification) => (
                                <button
                                    key={notification.id}
                                    className="min-w-64 rounded-lg border p-3 text-left text-sm hover:bg-muted"
                                    onClick={() =>
                                        notification.case_id &&
                                        runFilter({
                                            case_id: String(
                                                notification.case_id,
                                            ),
                                        })
                                    }
                                >
                                    <div className="font-medium">
                                        {notification.title}
                                    </div>
                                    <p className="line-clamp-2 text-muted-foreground">
                                        {notification.message}
                                    </p>
                                </button>
                            ))}
                        </CardContent>
                    </Card>
                )}

                <div className="grid gap-5 xl:grid-cols-[360px_minmax(0,1fr)]">
                    <Card className="h-fit">
                        <CardHeader>
                            <CardTitle className="text-base">
                                Senarai Kes
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <form
                                className="flex gap-2"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    runFilter();
                                }}
                            >
                                <Input
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    placeholder="Cari kes atau pekerja"
                                />
                                <Button size="icon" variant="outline">
                                    <Search />
                                </Button>
                            </form>
                            <div className="grid grid-cols-2 gap-2">
                                <Select
                                    value={filters.status || 'all'}
                                    onValueChange={(value) =>
                                        runFilter({
                                            status:
                                                value === 'all' ? '' : value,
                                        })
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            Semua Status
                                        </SelectItem>
                                        {[
                                            'draft',
                                            'pending_approval',
                                            'clearance',
                                            'final_review',
                                            'completed',
                                            'rejected',
                                            'cancelled',
                                        ].map((status) => (
                                            <SelectItem
                                                key={status}
                                                value={status}
                                            >
                                                {statusLabel[status]}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <Select
                                    value={filters.type || 'all'}
                                    onValueChange={(value) =>
                                        runFilter({
                                            type: value === 'all' ? '' : value,
                                        })
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            Semua Jenis
                                        </SelectItem>
                                        {types.map((type) => (
                                            <SelectItem key={type} value={type}>
                                                {typeLabel[type] ?? type}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="space-y-2">
                                {cases.data.map((item) => (
                                    <button
                                        key={item.id}
                                        className={`w-full rounded-lg border p-3 text-left text-sm transition-colors hover:bg-muted ${
                                            selectedCase?.id === item.id
                                                ? 'border-orange-500 bg-orange-50 dark:bg-orange-950/20'
                                                : ''
                                        }`}
                                        onClick={() =>
                                            runFilter({
                                                case_id: String(item.id),
                                            })
                                        }
                                    >
                                        <div className="flex justify-between gap-2">
                                            <span className="font-medium">
                                                {item.case_number}
                                            </span>
                                            <StatusBadge status={item.status} />
                                        </div>
                                        <div className="mt-1 font-medium">
                                            {item.employee_name}
                                        </div>
                                        <div className="text-muted-foreground">
                                            {item.department_name ?? '-'} ·{' '}
                                            {formatDate(
                                                item.approved_last_day ??
                                                    item.clearance_due_date,
                                            )}
                                        </div>
                                    </button>
                                ))}
                            </div>
                            {cases.last_page > 1 && (
                                <div className="flex flex-wrap gap-1">
                                    {cases.links.map((link, index) => (
                                        <Button
                                            key={`${link.label}-${index}`}
                                            size="sm"
                                            variant={
                                                link.active
                                                    ? 'default'
                                                    : 'outline'
                                            }
                                            disabled={!link.url}
                                            onClick={() =>
                                                link.url &&
                                                router.get(
                                                    link.url,
                                                    {},
                                                    { preserveState: true },
                                                )
                                            }
                                            dangerouslySetInnerHTML={{
                                                __html: link.label,
                                            }}
                                        />
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {selectedCase ? (
                        <CaseDetail
                            separationCase={selectedCase}
                            clearanceUsers={clearanceUsers}
                            permissions={permissions}
                        />
                    ) : (
                        <Card>
                            <CardContent className="p-12 text-center text-sm text-muted-foreground">
                                <LogOut className="mx-auto mb-3 size-8" />
                                Pilih kes untuk melihat butiran.
                            </CardContent>
                        </Card>
                    )}
                </div>
            </div>
        </>
    );
}

SeparationsIndex.layout = {
    breadcrumbs: [
        { title: 'Berhenti & Clearance', href: '/berhenti-clearance' },
    ],
};
