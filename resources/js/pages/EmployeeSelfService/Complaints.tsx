import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    Bell,
    CheckCircle2,
    FileWarning,
    LockKeyhole,
    MessageSquareWarning,
    Paperclip,
    Send,
    ShieldCheck,
} from 'lucide-react';
import type { FormEvent } from 'react';
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

type Category = {
    id: number;
    code: string;
    name: string;
    description: string | null;
    default_severity: string;
    allow_protected_identity: boolean;
};

type Employee = {
    user_id: number;
    employee_number: string;
    name: string;
    department_name: string | null;
    position_name: string | null;
};

type Attachment = {
    id: number;
    attachment_context: string;
    original_name: string;
    mime_type: string;
    size: number;
    created_at: string;
};

type Event = {
    id: number;
    event_type: string;
    title: string;
    details: string | null;
    occurred_at: string;
    creator: string | null;
};

type Complaint = {
    id: number;
    case_number: string;
    category: string;
    title: string;
    subject_name: string | null;
    incident_at: string | null;
    incident_location: string | null;
    description: string;
    requested_resolution: string | null;
    identity_protected: boolean;
    severity: string;
    status: string;
    triage_notes: string | null;
    finding_outcome: string | null;
    decision_outcome: string | null;
    created_at: string;
    closed_at: string | null;
    closure_reason: string | null;
    events: Event[];
    attachments: Attachment[];
};

type SubjectCase = {
    id: number;
    case_number: string;
    category: string;
    title: string;
    severity: string;
    status: string;
    allegation_summary: string | null;
    show_cause_due_at: string | null;
    decision_outcome: string | null;
    decision_notes: string | null;
    decided_at: string | null;
    effective_date: string | null;
    appeal_deadline: string | null;
    closed_at: string | null;
    closure_reason: string | null;
    events: Event[];
    attachments: Attachment[];
    response: {
        id: number;
        statement: string;
        submitted_at: string;
    } | null;
    appeal: {
        id: number;
        grounds: string;
        desired_outcome: string | null;
        status: string;
        reviewed_at: string | null;
        decision_notes: string | null;
        revised_outcome: string | null;
    } | null;
    hr_document: {
        id: number;
        reference_number: string | null;
        status: string;
        category: string;
    } | null;
};

type Notification = {
    id: number;
    case_id: number | null;
    title: string;
    message: string;
    read_at: string | null;
    created_at: string;
};

type Props = {
    categories: Category[];
    employees: Employee[];
    complaints: Complaint[];
    subjectCases: SubjectCase[];
    notifications: Notification[];
    unreadNotifications: number;
};

const statusLabel: Record<string, string> = {
    submitted: 'Dihantar',
    triage: 'Triage HR',
    investigation: 'Dalam Siasatan',
    show_cause: 'Menunggu Jawapan',
    decision: 'Keputusan',
    appeal: 'Dalam Rayuan',
    closed: 'Ditutup',
    dismissed: 'Ditutup Selepas Triage',
    withdrawn: 'Ditarik Balik',
};

const outcomeLabel: Record<string, string> = {
    no_action: 'Tiada Tindakan',
    counselling: 'Kaunseling',
    verbal_warning: 'Amaran Lisan',
    written_warning: 'Amaran Bertulis',
    final_warning: 'Amaran Akhir',
    suspension: 'Penggantungan',
    demotion: 'Penurunan Pangkat',
    termination: 'Penamatan',
    other: 'Tindakan Lain',
    upheld: 'Dikekalkan',
    varied: 'Diubah',
    overturned: 'Dibatalkan',
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

function formatSize(bytes: number) {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

function StatusBadge({ status }: { status: string }) {
    const variant = ['closed', 'dismissed'].includes(status)
        ? 'default'
        : status === 'withdrawn'
          ? 'secondary'
          : ['show_cause', 'appeal'].includes(status)
            ? 'destructive'
            : 'outline';

    return <Badge variant={variant}>{statusLabel[status] ?? status}</Badge>;
}

function Field({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <div className="space-y-2">
            <Label>{label}</Label>
            {children}
        </div>
    );
}

function NewComplaintForm({
    categories,
    employees,
}: {
    categories: Category[];
    employees: Employee[];
}) {
    const form = useForm({
        complaint_category_id: '',
        subject_user_id: '',
        subject_name: '',
        title: '',
        incident_at: '',
        incident_location: '',
        description: '',
        requested_resolution: '',
        identity_protected: true,
        attachments: [] as File[],
    });
    const selectedCategory = categories.find(
        (category) => String(category.id) === form.data.complaint_category_id,
    );

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post('/aduan-saya', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <Card className="border-red-200 dark:border-red-900">
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <MessageSquareWarning className="size-5 text-red-600" />
                    Hantar Aduan Sulit
                </CardTitle>
                <CardDescription>
                    Maklumat disimpan dalam storan persendirian dan hanya boleh
                    dibuka oleh pegawai yang mempunyai keperluan tugas.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form className="grid gap-4 md:grid-cols-2" onSubmit={submit}>
                    <Field label="Kategori Aduan">
                        <Select
                            value={form.data.complaint_category_id}
                            onValueChange={(value) =>
                                form.setData('complaint_category_id', value)
                            }
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Pilih kategori" />
                            </SelectTrigger>
                            <SelectContent>
                                {categories.map((category) => (
                                    <SelectItem
                                        key={category.id}
                                        value={String(category.id)}
                                    >
                                        {category.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {selectedCategory?.description && (
                            <p className="text-xs text-muted-foreground">
                                {selectedCategory.description}
                            </p>
                        )}
                    </Field>
                    <Field label="Pekerja yang Diadukan (jika diketahui)">
                        <Select
                            value={form.data.subject_user_id || 'manual'}
                            onValueChange={(value) =>
                                form.setData(
                                    'subject_user_id',
                                    value === 'manual' ? '' : value,
                                )
                            }
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Pilih pekerja" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="manual">
                                    Tidak tersenarai / tidak diketahui
                                </SelectItem>
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
                    {!form.data.subject_user_id && (
                        <Field label="Nama / Keterangan Individu">
                            <Input
                                value={form.data.subject_name}
                                onChange={(event) =>
                                    form.setData(
                                        'subject_name',
                                        event.target.value,
                                    )
                                }
                                placeholder="Nama atau keterangan individu"
                            />
                        </Field>
                    )}
                    <Field label="Tajuk Ringkas">
                        <Input
                            value={form.data.title}
                            onChange={(event) =>
                                form.setData('title', event.target.value)
                            }
                            placeholder="Ringkasan isu"
                        />
                    </Field>
                    <Field label="Tarikh & Masa Kejadian">
                        <Input
                            type="datetime-local"
                            value={form.data.incident_at}
                            onChange={(event) =>
                                form.setData('incident_at', event.target.value)
                            }
                        />
                    </Field>
                    <Field label="Lokasi Kejadian">
                        <Input
                            value={form.data.incident_location}
                            onChange={(event) =>
                                form.setData(
                                    'incident_location',
                                    event.target.value,
                                )
                            }
                            placeholder="Pejabat, platform dalam talian atau lokasi lain"
                        />
                    </Field>
                    <div className="md:col-span-2">
                        <Field label="Keterangan Lengkap">
                            <Textarea
                                rows={6}
                                value={form.data.description}
                                onChange={(event) =>
                                    form.setData(
                                        'description',
                                        event.target.value,
                                    )
                                }
                                placeholder="Nyatakan fakta, urutan kejadian, pihak terlibat dan saksi jika ada."
                            />
                        </Field>
                    </div>
                    <div className="md:col-span-2">
                        <Field label="Penyelesaian yang Diharapkan (pilihan)">
                            <Textarea
                                rows={3}
                                value={form.data.requested_resolution}
                                onChange={(event) =>
                                    form.setData(
                                        'requested_resolution',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                    </div>
                    <Field label="Bukti Sokongan (maksimum 5 fail)">
                        <Input
                            type="file"
                            multiple
                            accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                            onChange={(event) =>
                                form.setData(
                                    'attachments',
                                    Array.from(event.target.files ?? []),
                                )
                            }
                        />
                    </Field>
                    <div className="flex items-start gap-3 rounded-lg border p-3">
                        <Checkbox
                            id="identity-protected"
                            checked={form.data.identity_protected}
                            disabled={
                                selectedCategory?.allow_protected_identity ===
                                false
                            }
                            onCheckedChange={(checked) =>
                                form.setData(
                                    'identity_protected',
                                    Boolean(checked),
                                )
                            }
                        />
                        <div>
                            <Label htmlFor="identity-protected">
                                Lindungi identiti saya daripada responden
                            </Label>
                            <p className="text-xs text-muted-foreground">
                                HR masih boleh mengesahkan identiti untuk
                                mencegah penyalahgunaan dan menjalankan siasatan
                                yang adil.
                            </p>
                        </div>
                    </div>
                    {Object.keys(form.errors).length > 0 && (
                        <div className="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700 md:col-span-2 dark:border-red-900 dark:bg-red-950/30 dark:text-red-300">
                            {Object.values(form.errors)[0]}
                        </div>
                    )}
                    <div className="flex justify-end md:col-span-2">
                        <Button
                            disabled={
                                form.processing || categories.length === 0
                            }
                        >
                            <Send /> Hantar Aduan Sulit
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}

function Attachments({
    caseId,
    items,
}: {
    caseId: number;
    items: Attachment[];
}) {
    if (items.length === 0) {
        return null;
    }

    return (
        <div className="space-y-2">
            <div className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                Lampiran
            </div>
            {items.map((attachment) => (
                <Link
                    key={attachment.id}
                    href={`/aduan-saya/${caseId}/lampiran/${attachment.id}`}
                    className="flex items-center justify-between rounded-md border px-3 py-2 text-sm hover:bg-muted"
                >
                    <span className="flex min-w-0 items-center gap-2">
                        <Paperclip className="size-4 shrink-0" />
                        <span className="truncate">
                            {attachment.original_name}
                        </span>
                    </span>
                    <span className="text-xs text-muted-foreground">
                        {formatSize(attachment.size)}
                    </span>
                </Link>
            ))}
        </div>
    );
}

function Timeline({ events }: { events: Event[] }) {
    return (
        <div className="space-y-3">
            <div className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                Perkembangan Kes
            </div>
            {events.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    Belum ada perkembangan yang boleh dipaparkan.
                </p>
            ) : (
                events.map((event) => (
                    <div key={event.id} className="border-l-2 pl-3 text-sm">
                        <div className="font-medium">{event.title}</div>
                        <div className="text-muted-foreground">
                            {event.details}
                        </div>
                        <div className="mt-1 text-xs text-muted-foreground">
                            {formatDate(event.occurred_at, true)}
                        </div>
                    </div>
                ))
            )}
        </div>
    );
}

function ShowCauseResponse({
    disciplineCase,
}: {
    disciplineCase: SubjectCase;
}) {
    const form = useForm({ statement: '', attachments: [] as File[] });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(`/aduan-saya/${disciplineCase.id}/jawapan-tunjuk-sebab`, {
            forceFormData: true,
            preserveScroll: true,
        });
    };

    if (disciplineCase.status !== 'show_cause' || disciplineCase.response) {
        return null;
    }

    return (
        <form
            className="space-y-3 rounded-lg border border-amber-300 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-950/20"
            onSubmit={submit}
        >
            <div>
                <div className="font-medium">Jawapan Tunjuk Sebab</div>
                <div className="text-sm text-muted-foreground">
                    Tarikh akhir:{' '}
                    {formatDate(disciplineCase.show_cause_due_at, true)}
                </div>
            </div>
            <Textarea
                rows={6}
                value={form.data.statement}
                onChange={(event) =>
                    form.setData('statement', event.target.value)
                }
                placeholder="Berikan penjelasan lengkap dan fakta sokongan."
            />
            <Input
                type="file"
                multiple
                accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                onChange={(event) =>
                    form.setData(
                        'attachments',
                        Array.from(event.target.files ?? []),
                    )
                }
            />
            {form.errors.statement && (
                <p className="text-sm text-red-600">{form.errors.statement}</p>
            )}
            <Button disabled={form.processing}>
                <Send /> Hantar Jawapan
            </Button>
        </form>
    );
}

function AppealForm({ disciplineCase }: { disciplineCase: SubjectCase }) {
    const form = useForm({
        grounds: '',
        desired_outcome: '',
        attachments: [] as File[],
    });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(`/aduan-saya/${disciplineCase.id}/rayuan`, {
            forceFormData: true,
            preserveScroll: true,
        });
    };

    if (
        disciplineCase.status !== 'decision' ||
        disciplineCase.appeal ||
        !disciplineCase.appeal_deadline
    ) {
        return null;
    }

    return (
        <form className="space-y-3 rounded-lg border p-4" onSubmit={submit}>
            <div>
                <div className="font-medium">Kemukakan Rayuan</div>
                <div className="text-sm text-muted-foreground">
                    Rayuan perlu dihantar sebelum{' '}
                    {formatDate(disciplineCase.appeal_deadline)}.
                </div>
            </div>
            <Textarea
                rows={5}
                value={form.data.grounds}
                onChange={(event) =>
                    form.setData('grounds', event.target.value)
                }
                placeholder="Nyatakan alasan rayuan dan fakta baharu jika ada."
            />
            <Textarea
                rows={2}
                value={form.data.desired_outcome}
                onChange={(event) =>
                    form.setData('desired_outcome', event.target.value)
                }
                placeholder="Keputusan yang dimohon (pilihan)"
            />
            <Input
                type="file"
                multiple
                accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                onChange={(event) =>
                    form.setData(
                        'attachments',
                        Array.from(event.target.files ?? []),
                    )
                }
            />
            {form.errors.grounds && (
                <p className="text-sm text-red-600">{form.errors.grounds}</p>
            )}
            <Button variant="outline" disabled={form.processing}>
                <FileWarning /> Hantar Rayuan
            </Button>
        </form>
    );
}

export default function Complaints({
    categories,
    employees,
    complaints,
    subjectCases,
    notifications,
    unreadNotifications,
}: Props) {
    const withdraw = (complaint: Complaint) => {
        const reason = window.prompt('Nyatakan sebab menarik balik aduan:');

        if (!reason) {
            return;
        }

        router.patch(
            `/aduan-saya/${complaint.id}/tarik-balik`,
            { reason },
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Aduan Saya" />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Aduan Saya
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Saluran sulit untuk aduan tempat kerja, jawapan
                            tunjuk sebab dan rayuan tatatertib.
                        </p>
                    </div>
                    {unreadNotifications > 0 && (
                        <Button
                            variant="outline"
                            onClick={() =>
                                router.patch(
                                    '/aduan-saya/notifikasi/dibaca',
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            <Bell /> Tandakan {unreadNotifications} Dibaca
                        </Button>
                    )}
                </div>

                <div className="grid gap-4 md:grid-cols-3">
                    <Card>
                        <CardContent className="flex items-center gap-3 p-4">
                            <LockKeyhole className="size-7 text-red-600" />
                            <div>
                                <div className="font-medium">
                                    Storan Persendirian
                                </div>
                                <div className="text-xs text-muted-foreground">
                                    Fail tidak disimpan secara awam.
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="flex items-center gap-3 p-4">
                            <ShieldCheck className="size-7 text-emerald-600" />
                            <div>
                                <div className="font-medium">Need-to-Know</div>
                                <div className="text-xs text-muted-foreground">
                                    Hanya pegawai berautoriti boleh membuka kes.
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="flex items-center gap-3 p-4">
                            <CheckCircle2 className="size-7 text-blue-600" />
                            <div>
                                <div className="font-medium">Audit Trail</div>
                                <div className="text-xs text-muted-foreground">
                                    Setiap tindakan penting direkodkan.
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {notifications.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Bell className="size-4" /> Notifikasi Sulit
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-2 md:grid-cols-2">
                            {notifications.slice(0, 8).map((notification) => (
                                <div
                                    key={notification.id}
                                    className={`rounded-lg border p-3 text-sm ${
                                        notification.read_at
                                            ? 'bg-muted/20'
                                            : 'border-red-200 bg-red-50/60 dark:border-red-900 dark:bg-red-950/20'
                                    }`}
                                >
                                    <div className="font-medium">
                                        {notification.title}
                                    </div>
                                    <div className="text-muted-foreground">
                                        {notification.message}
                                    </div>
                                    <div className="mt-1 text-xs text-muted-foreground">
                                        {formatDate(
                                            notification.created_at,
                                            true,
                                        )}
                                    </div>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                )}

                <NewComplaintForm
                    categories={categories}
                    employees={employees}
                />

                <section className="space-y-4">
                    <div>
                        <h2 className="text-lg font-semibold">
                            Aduan yang Saya Hantar
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Identiti responden tidak akan dipaparkan di luar
                            proses yang dibenarkan.
                        </p>
                    </div>
                    {complaints.length === 0 ? (
                        <Card>
                            <CardContent className="p-6 text-center text-sm text-muted-foreground">
                                Belum ada aduan dihantar.
                            </CardContent>
                        </Card>
                    ) : (
                        complaints.map((complaint) => (
                            <Card key={complaint.id}>
                                <CardHeader>
                                    <div className="flex flex-wrap items-start justify-between gap-2">
                                        <div>
                                            <CardTitle className="text-base">
                                                {complaint.title}
                                            </CardTitle>
                                            <CardDescription>
                                                {complaint.case_number} ·{' '}
                                                {complaint.category} ·{' '}
                                                {formatDate(
                                                    complaint.created_at,
                                                )}
                                            </CardDescription>
                                        </div>
                                        <StatusBadge
                                            status={complaint.status}
                                        />
                                    </div>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="grid gap-3 text-sm md:grid-cols-3">
                                        <div>
                                            <div className="text-muted-foreground">
                                                Subjek
                                            </div>
                                            <div className="font-medium">
                                                {complaint.subject_name ??
                                                    'Tidak diketahui'}
                                            </div>
                                        </div>
                                        <div>
                                            <div className="text-muted-foreground">
                                                Kejadian
                                            </div>
                                            <div className="font-medium">
                                                {formatDate(
                                                    complaint.incident_at,
                                                    true,
                                                )}
                                            </div>
                                        </div>
                                        <div>
                                            <div className="text-muted-foreground">
                                                Perlindungan
                                            </div>
                                            <div className="font-medium">
                                                {complaint.identity_protected
                                                    ? 'Identiti dilindungi'
                                                    : 'Aduan bernama'}
                                            </div>
                                        </div>
                                    </div>
                                    <p className="text-sm whitespace-pre-wrap">
                                        {complaint.description}
                                    </p>
                                    <Attachments
                                        caseId={complaint.id}
                                        items={complaint.attachments}
                                    />
                                    <Timeline events={complaint.events} />
                                    {complaint.status === 'submitted' && (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => withdraw(complaint)}
                                        >
                                            Tarik Balik Aduan
                                        </Button>
                                    )}
                                </CardContent>
                            </Card>
                        ))
                    )}
                </section>

                {subjectCases.length > 0 && (
                    <section className="space-y-4">
                        <Separator />
                        <div>
                            <h2 className="text-lg font-semibold">
                                Tindakan yang Memerlukan Perhatian Saya
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Identiti pengadu yang dilindungi tidak
                                didedahkan.
                            </p>
                        </div>
                        {subjectCases.map((disciplineCase) => (
                            <Card
                                key={disciplineCase.id}
                                className="border-amber-200 dark:border-amber-900"
                            >
                                <CardHeader>
                                    <div className="flex flex-wrap items-start justify-between gap-2">
                                        <div>
                                            <CardTitle className="text-base">
                                                {disciplineCase.title}
                                            </CardTitle>
                                            <CardDescription>
                                                {disciplineCase.case_number} ·{' '}
                                                {disciplineCase.category}
                                            </CardDescription>
                                        </div>
                                        <StatusBadge
                                            status={disciplineCase.status}
                                        />
                                    </div>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    {disciplineCase.allegation_summary && (
                                        <div className="rounded-lg bg-muted p-3">
                                            <div className="text-xs font-medium uppercase">
                                                Ringkasan Dakwaan
                                            </div>
                                            <p className="mt-1 text-sm whitespace-pre-wrap">
                                                {
                                                    disciplineCase.allegation_summary
                                                }
                                            </p>
                                        </div>
                                    )}
                                    {disciplineCase.decision_outcome && (
                                        <div className="rounded-lg border p-3">
                                            <div className="font-medium">
                                                Keputusan:{' '}
                                                {outcomeLabel[
                                                    disciplineCase
                                                        .decision_outcome
                                                ] ??
                                                    disciplineCase.decision_outcome}
                                            </div>
                                            <p className="mt-1 text-sm whitespace-pre-wrap text-muted-foreground">
                                                {disciplineCase.decision_notes}
                                            </p>
                                        </div>
                                    )}
                                    {disciplineCase.response && (
                                        <div className="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm dark:border-emerald-900 dark:bg-emerald-950/20">
                                            Jawapan tunjuk sebab dihantar pada{' '}
                                            {formatDate(
                                                disciplineCase.response
                                                    .submitted_at,
                                                true,
                                            )}
                                            .
                                        </div>
                                    )}
                                    {disciplineCase.appeal && (
                                        <div className="rounded-lg border p-3 text-sm">
                                            <div className="font-medium">
                                                Rayuan:{' '}
                                                {outcomeLabel[
                                                    disciplineCase.appeal.status
                                                ] ??
                                                    disciplineCase.appeal
                                                        .status}
                                            </div>
                                            {disciplineCase.appeal
                                                .decision_notes && (
                                                <p className="text-muted-foreground">
                                                    {
                                                        disciplineCase.appeal
                                                            .decision_notes
                                                    }
                                                </p>
                                            )}
                                        </div>
                                    )}
                                    {disciplineCase.hr_document && (
                                        <Link
                                            href="/dokumen-saya"
                                            className="inline-flex items-center gap-2 text-sm font-medium text-blue-700 hover:underline dark:text-blue-300"
                                        >
                                            <FileWarning className="size-4" />
                                            Buka dokumen rasmi di Dokumen Saya
                                        </Link>
                                    )}
                                    <Attachments
                                        caseId={disciplineCase.id}
                                        items={disciplineCase.attachments}
                                    />
                                    <ShowCauseResponse
                                        disciplineCase={disciplineCase}
                                    />
                                    <AppealForm
                                        disciplineCase={disciplineCase}
                                    />
                                    <Timeline events={disciplineCase.events} />
                                </CardContent>
                            </Card>
                        ))}
                    </section>
                )}

                <div className="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/20 dark:text-amber-200">
                    <AlertTriangle className="mt-0.5 size-5 shrink-0" />
                    <p>
                        Saluran ini bukan talian kecemasan. Jika terdapat
                        ancaman keselamatan segera, hubungi penyelia,
                        keselamatan atau pihak berkuasa yang berkaitan dengan
                        segera.
                    </p>
                </div>
            </div>
        </>
    );
}

Complaints.layout = {
    breadcrumbs: [{ title: 'Aduan Saya', href: '/aduan-saya' }],
};
