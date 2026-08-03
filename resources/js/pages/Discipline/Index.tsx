import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    Bell,
    Download,
    FileSearch,
    Gavel,
    LockKeyhole,
    Paperclip,
    Search,
    ShieldAlert,
    UserRoundCheck,
    UsersRound,
} from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
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
    sla_days: number;
    appeal_days: number;
    requires_show_cause: boolean;
};
type Officer = { id: number; name: string; email: string };
type Member = {
    id: number;
    user_id: number;
    role: string;
    conflict_declared: boolean;
    has_conflict: boolean | null;
    conflict_notes: string | null;
    conflict_declared_at: string | null;
    recused_at: string | null;
    is_current_user: boolean;
    user: Officer | null;
};
type CaseEvent = {
    id: number;
    event_type: string;
    title: string;
    details: string | null;
    occurred_at: string;
    visible_to_complainant: boolean;
    visible_to_subject: boolean;
    creator: string | null;
};
type Attachment = {
    id: number;
    attachment_context: string;
    original_name: string;
    mime_type: string;
    size: number;
    visible_to_complainant: boolean;
    visible_to_subject: boolean;
    created_at: string;
};
type Appeal = {
    id: number;
    grounds: string;
    desired_outcome: string | null;
    status: string;
    reviewed_at: string | null;
    decision_notes: string | null;
    revised_outcome: string | null;
    appellant: { id: number; name: string } | null;
};
type CaseListItem = {
    id: number;
    case_number: string;
    title: string;
    subject_name: string | null;
    complainant_name: string | null;
    severity: string;
    status: string;
    target_completion_date: string | null;
    created_at: string;
    category: string | null;
    investigator: string | null;
};
type DisciplineCase = Omit<CaseListItem, 'category'> & {
    complaint_category_id: number;
    complainant_user_id: number | null;
    complainant_employee_number: string | null;
    complainant_email: string | null;
    complainant_department_name: string | null;
    identity_protected: boolean;
    subject_user_id: number | null;
    subject_employee_number: string | null;
    subject_email: string | null;
    subject_department_name: string | null;
    subject_position_name: string | null;
    incident_at: string | null;
    incident_location: string | null;
    description: string | null;
    requested_resolution: string | null;
    confidentiality: string;
    triage_notes: string | null;
    allegation_summary: string | null;
    finding_outcome: string | null;
    finding_summary: string | null;
    recommended_action: string | null;
    finding_submitted_at: string | null;
    show_cause_due_at: string | null;
    show_cause_expired: boolean;
    decision_outcome: string | null;
    decision_notes: string | null;
    decided_by: number | null;
    decided_at: string | null;
    effective_date: string | null;
    appeal_deadline: string | null;
    appeal_expired: boolean;
    closed_at: string | null;
    closure_reason: string | null;
    details_locked: boolean;
    category: Category | null;
    members: Member[];
    events: CaseEvent[];
    attachments: Attachment[];
    responses: {
        id: number;
        response_type: string;
        statement: string;
        submitted_at: string;
        user: { id: number; name: string } | null;
    }[];
    appeals: Appeal[];
    hr_document: {
        id: number;
        reference_number: string | null;
        status: string;
        category: string;
    } | null;
};
type Paginator<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
};
type Props = {
    cases: Paginator<CaseListItem>;
    selectedCase: DisciplineCase | null;
    categories: Category[];
    officers: Officer[];
    filters: {
        search: string;
        status: string;
        severity: string;
        category_id: string;
    };
    statistics: {
        total: number;
        new: number;
        investigation: number;
        decision: number;
        overdue: number;
    };
    permissions: { manage: boolean; investigate: boolean; approve: boolean };
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
    submitted: 'Baharu',
    triage: 'Triage',
    investigation: 'Siasatan',
    show_cause_pending: 'Sedia Tunjuk Sebab',
    show_cause: 'Tunggu Jawapan',
    decision: 'Keputusan / Tempoh Rayuan',
    appeal: 'Rayuan',
    closed: 'Ditutup',
    dismissed: 'Ditolak Semasa Triage',
    withdrawn: 'Ditarik Balik',
};
const severityLabel: Record<string, string> = {
    low: 'Rendah',
    medium: 'Sederhana',
    high: 'Tinggi',
    critical: 'Kritikal',
};
const outcomeLabel: Record<string, string> = {
    substantiated: 'Terbukti',
    partially_substantiated: 'Sebahagian Terbukti',
    unsubstantiated: 'Tidak Terbukti',
    inconclusive: 'Tidak Konklusif',
    no_action: 'Tiada Tindakan',
    counselling: 'Kaunseling',
    verbal_warning: 'Amaran Lisan',
    written_warning: 'Amaran Bertulis',
    final_warning: 'Amaran Akhir',
    suspension: 'Penggantungan',
    demotion: 'Penurunan Pangkat',
    termination: 'Penamatan',
    other: 'Tindakan Lain',
    pending: 'Menunggu Semakan',
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
function Field({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="space-y-2">
            <Label>{label}</Label>
            {children}
        </div>
    );
}
function StatusBadge({ status }: { status: string }) {
    const variant = ['closed', 'dismissed'].includes(status)
        ? 'default'
        : ['show_cause', 'appeal'].includes(status)
          ? 'destructive'
          : 'secondary';

    return <Badge variant={variant}>{statusLabel[status] ?? status}</Badge>;
}
function ErrorText({ errors }: { errors: Record<string, string> }) {
    const message = Object.values(errors)[0];

    return message ? <p className="text-sm text-red-600">{message}</p> : null;
}

function TriageForm({
    disciplineCase,
    officers,
}: {
    disciplineCase: DisciplineCase;
    officers: Officer[];
}) {
    const form = useForm({
        action: 'accept',
        severity: disciplineCase.severity,
        triage_notes: '',
        investigator_user_id: '',
        allegation_summary: disciplineCase.description ?? '',
        target_completion_date: '',
    });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.patch(`/disiplin-aduan/${disciplineCase.id}/triage`, {
            preserveScroll: true,
        });
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">Triage HR</CardTitle>
                <CardDescription>
                    Sahkan skop, tahap risiko dan pegawai penyiasat pertama.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form className="grid gap-4 md:grid-cols-2" onSubmit={submit}>
                    <Field label="Keputusan Triage">
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
                                <SelectItem value="accept">
                                    Terima untuk Siasatan
                                </SelectItem>
                                <SelectItem value="dismiss">
                                    Tutup Selepas Triage
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </Field>
                    <Field label="Tahap Risiko">
                        <Select
                            value={form.data.severity}
                            onValueChange={(value) =>
                                form.setData('severity', value)
                            }
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {Object.entries(severityLabel).map(
                                    ([value, label]) => (
                                        <SelectItem key={value} value={value}>
                                            {label}
                                        </SelectItem>
                                    ),
                                )}
                            </SelectContent>
                        </Select>
                    </Field>
                    {form.data.action === 'accept' && (
                        <>
                            <Field label="Pegawai Penyiasat">
                                <Select
                                    value={form.data.investigator_user_id}
                                    onValueChange={(value) =>
                                        form.setData(
                                            'investigator_user_id',
                                            value,
                                        )
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Pilih pegawai" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {officers.map((officer) => (
                                            <SelectItem
                                                key={officer.id}
                                                value={String(officer.id)}
                                            >
                                                {officer.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </Field>
                            <Field label="Sasaran Selesai">
                                <Input
                                    type="date"
                                    value={form.data.target_completion_date}
                                    onChange={(event) =>
                                        form.setData(
                                            'target_completion_date',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <div className="md:col-span-2">
                                <Field label="Ringkasan Dakwaan">
                                    <Textarea
                                        rows={4}
                                        value={form.data.allegation_summary}
                                        onChange={(event) =>
                                            form.setData(
                                                'allegation_summary',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>
                            </div>
                        </>
                    )}
                    <div className="md:col-span-2">
                        <Field label="Catatan Triage">
                            <Textarea
                                rows={3}
                                value={form.data.triage_notes}
                                onChange={(event) =>
                                    form.setData(
                                        'triage_notes',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                    </div>
                    <ErrorText errors={form.errors} />
                    <div className="flex justify-end md:col-span-2">
                        <Button disabled={form.processing}>
                            Simpan Keputusan Triage
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}

function ConflictDeclaration({
    disciplineCase,
    member,
}: {
    disciplineCase: DisciplineCase;
    member: Member;
}) {
    const form = useForm({ has_conflict: false, conflict_notes: '' });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.patch(
            `/disiplin-aduan/${disciplineCase.id}/pasukan/${member.id}/konflik`,
            { preserveScroll: true },
        );
    };

    return (
        <Card className="border-amber-300 dark:border-amber-800">
            <CardHeader>
                <CardTitle className="text-base">
                    Deklarasi Konflik Kepentingan
                </CardTitle>
                <CardDescription>
                    Akses tindakan siasatan dibuka hanya selepas deklarasi ini.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form className="space-y-3" onSubmit={submit}>
                    <div className="flex items-center gap-2">
                        <Checkbox
                            id={`conflict-${member.id}`}
                            checked={form.data.has_conflict}
                            onCheckedChange={(checked) =>
                                form.setData('has_conflict', Boolean(checked))
                            }
                        />
                        <Label htmlFor={`conflict-${member.id}`}>
                            Saya mempunyai konflik sebenar atau berpotensi
                        </Label>
                    </div>
                    <Textarea
                        rows={3}
                        value={form.data.conflict_notes}
                        onChange={(event) =>
                            form.setData('conflict_notes', event.target.value)
                        }
                        placeholder="Nyatakan hubungan atau sebab jika terdapat konflik."
                    />
                    <ErrorText errors={form.errors} />
                    <Button disabled={form.processing}>Hantar Deklarasi</Button>
                </form>
            </CardContent>
        </Card>
    );
}

function TeamForm({
    disciplineCase,
    officers,
}: {
    disciplineCase: DisciplineCase;
    officers: Officer[];
}) {
    const form = useForm({ user_id: '', role: 'panel' });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(`/disiplin-aduan/${disciplineCase.id}/pasukan`, {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">Pasukan Siasatan</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
                <div className="grid gap-2 md:grid-cols-2">
                    {disciplineCase.members.map((member) => (
                        <div
                            key={member.id}
                            className="rounded-lg border p-3 text-sm"
                        >
                            <div className="flex items-start justify-between gap-2">
                                <div>
                                    <div className="font-medium">
                                        {member.user?.name}
                                    </div>
                                    <div className="text-muted-foreground">
                                        {member.role}
                                    </div>
                                </div>
                                <Badge
                                    variant={
                                        member.recused_at
                                            ? 'destructive'
                                            : member.conflict_declared
                                              ? 'default'
                                              : 'secondary'
                                    }
                                >
                                    {member.recused_at
                                        ? 'Digugurkan'
                                        : member.conflict_declared
                                          ? 'Deklarasi Selesai'
                                          : 'Belum Deklarasi'}
                                </Badge>
                            </div>
                            {!member.recused_at && (
                                <Button
                                    className="mt-2"
                                    size="sm"
                                    variant="outline"
                                    onClick={() => {
                                        const reason = window.prompt(
                                            'Sebab menggugurkan pegawai:',
                                        );

                                        if (reason) {
                                            router.patch(
                                                `/disiplin-aduan/${disciplineCase.id}/pasukan/${member.id}/gugur`,
                                                { reason },
                                                { preserveScroll: true },
                                            );
                                        }
                                    }}
                                >
                                    Gugurkan
                                </Button>
                            )}
                        </div>
                    ))}
                </div>
                <form
                    className="grid gap-3 md:grid-cols-[1fr_180px_auto]"
                    onSubmit={submit}
                >
                    <Select
                        value={form.data.user_id}
                        onValueChange={(value) =>
                            form.setData('user_id', value)
                        }
                    >
                        <SelectTrigger>
                            <SelectValue placeholder="Pilih pegawai" />
                        </SelectTrigger>
                        <SelectContent>
                            {officers.map((officer) => (
                                <SelectItem
                                    key={officer.id}
                                    value={String(officer.id)}
                                >
                                    {officer.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Select
                        value={form.data.role}
                        onValueChange={(value) => form.setData('role', value)}
                    >
                        <SelectTrigger>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="investigator">
                                Penyiasat
                            </SelectItem>
                            <SelectItem value="panel">Panel</SelectItem>
                            <SelectItem value="advisor">Penasihat</SelectItem>
                        </SelectContent>
                    </Select>
                    <Button disabled={form.processing}>Tambah</Button>
                </form>
                <ErrorText errors={form.errors} />
            </CardContent>
        </Card>
    );
}

function InvestigationTools({
    disciplineCase,
}: {
    disciplineCase: DisciplineCase;
}) {
    const eventForm = useForm({
        event_type: 'interview',
        title: '',
        details: '',
        occurred_at: new Date().toISOString().slice(0, 16),
        visible_to_complainant: false,
        visible_to_subject: false,
    });
    const fileForm = useForm({
        attachment_context: 'investigation',
        attachment: null as File | null,
        visible_to_complainant: false,
        visible_to_subject: false,
    });
    const findingForm = useForm({
        finding_outcome: 'substantiated',
        finding_summary: '',
        recommended_action: '',
    });

    return (
        <div className="grid gap-4 xl:grid-cols-2">
            <Card>
                <CardHeader>
                    <CardTitle className="text-base">
                        Catatan Siasatan
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <form
                        className="space-y-3"
                        onSubmit={(event) => {
                            event.preventDefault();
                            eventForm.post(
                                `/disiplin-aduan/${disciplineCase.id}/kronologi`,
                                {
                                    preserveScroll: true,
                                    onSuccess: () =>
                                        eventForm.reset('title', 'details'),
                                },
                            );
                        }}
                    >
                        <Select
                            value={eventForm.data.event_type}
                            onValueChange={(value) =>
                                eventForm.setData('event_type', value)
                            }
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="interview">
                                    Temu Bual
                                </SelectItem>
                                <SelectItem value="evidence_review">
                                    Semakan Bukti
                                </SelectItem>
                                <SelectItem value="meeting">
                                    Mesyuarat
                                </SelectItem>
                                <SelectItem value="site_visit">
                                    Lawatan Tapak
                                </SelectItem>
                                <SelectItem value="correspondence">
                                    Surat-menyurat
                                </SelectItem>
                                <SelectItem value="other">Lain-lain</SelectItem>
                            </SelectContent>
                        </Select>
                        <Input
                            value={eventForm.data.title}
                            onChange={(event) =>
                                eventForm.setData('title', event.target.value)
                            }
                            placeholder="Tajuk catatan"
                        />
                        <Input
                            type="datetime-local"
                            value={eventForm.data.occurred_at}
                            onChange={(event) =>
                                eventForm.setData(
                                    'occurred_at',
                                    event.target.value,
                                )
                            }
                        />
                        <Textarea
                            rows={4}
                            value={eventForm.data.details}
                            onChange={(event) =>
                                eventForm.setData('details', event.target.value)
                            }
                            placeholder="Fakta dan pemerhatian"
                        />
                        <div className="flex flex-wrap gap-4 text-sm">
                            <label className="flex items-center gap-2">
                                <Checkbox
                                    checked={
                                        eventForm.data.visible_to_complainant
                                    }
                                    onCheckedChange={(checked) =>
                                        eventForm.setData(
                                            'visible_to_complainant',
                                            Boolean(checked),
                                        )
                                    }
                                />{' '}
                                Papar kepada pengadu
                            </label>
                            <label className="flex items-center gap-2">
                                <Checkbox
                                    checked={eventForm.data.visible_to_subject}
                                    onCheckedChange={(checked) =>
                                        eventForm.setData(
                                            'visible_to_subject',
                                            Boolean(checked),
                                        )
                                    }
                                />{' '}
                                Papar kepada subjek
                            </label>
                        </div>
                        <ErrorText errors={eventForm.errors} />
                        <Button disabled={eventForm.processing}>
                            Tambah Catatan
                        </Button>
                    </form>
                </CardContent>
            </Card>
            <Card>
                <CardHeader>
                    <CardTitle className="text-base">Muat Naik Bukti</CardTitle>
                </CardHeader>
                <CardContent>
                    <form
                        className="space-y-3"
                        onSubmit={(event) => {
                            event.preventDefault();
                            fileForm.post(
                                `/disiplin-aduan/${disciplineCase.id}/lampiran`,
                                {
                                    forceFormData: true,
                                    preserveScroll: true,
                                    onSuccess: () =>
                                        fileForm.reset('attachment'),
                                },
                            );
                        }}
                    >
                        <Select
                            value={fileForm.data.attachment_context}
                            onValueChange={(value) =>
                                fileForm.setData('attachment_context', value)
                            }
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="investigation">
                                    Bukti Siasatan
                                </SelectItem>
                                <SelectItem value="statement">
                                    Kenyataan
                                </SelectItem>
                                <SelectItem value="decision">
                                    Sokongan Keputusan
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <Input
                            type="file"
                            accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx"
                            onChange={(event) =>
                                fileForm.setData(
                                    'attachment',
                                    event.target.files?.[0] ?? null,
                                )
                            }
                        />
                        <div className="flex flex-wrap gap-4 text-sm">
                            <label className="flex items-center gap-2">
                                <Checkbox
                                    checked={
                                        fileForm.data.visible_to_complainant
                                    }
                                    onCheckedChange={(checked) =>
                                        fileForm.setData(
                                            'visible_to_complainant',
                                            Boolean(checked),
                                        )
                                    }
                                />{' '}
                                Papar kepada pengadu
                            </label>
                            <label className="flex items-center gap-2">
                                <Checkbox
                                    checked={fileForm.data.visible_to_subject}
                                    onCheckedChange={(checked) =>
                                        fileForm.setData(
                                            'visible_to_subject',
                                            Boolean(checked),
                                        )
                                    }
                                />{' '}
                                Papar kepada subjek
                            </label>
                        </div>
                        <ErrorText errors={fileForm.errors} />
                        <Button
                            disabled={
                                fileForm.processing || !fileForm.data.attachment
                            }
                        >
                            <Paperclip /> Simpan Bukti
                        </Button>
                    </form>
                </CardContent>
            </Card>
            <Card className="xl:col-span-2">
                <CardHeader>
                    <CardTitle className="text-base">
                        Dapatan Akhir Siasatan
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <form
                        className="grid gap-3 md:grid-cols-2"
                        onSubmit={(event) => {
                            event.preventDefault();
                            findingForm.patch(
                                `/disiplin-aduan/${disciplineCase.id}/dapatan`,
                                { preserveScroll: true },
                            );
                        }}
                    >
                        <Field label="Dapatan">
                            <Select
                                value={findingForm.data.finding_outcome}
                                onValueChange={(value) =>
                                    findingForm.setData(
                                        'finding_outcome',
                                        value,
                                    )
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="substantiated">
                                        Terbukti
                                    </SelectItem>
                                    <SelectItem value="partially_substantiated">
                                        Sebahagian Terbukti
                                    </SelectItem>
                                    <SelectItem value="unsubstantiated">
                                        Tidak Terbukti
                                    </SelectItem>
                                    <SelectItem value="inconclusive">
                                        Tidak Konklusif
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </Field>
                        <Field label="Cadangan Tindakan">
                            <Textarea
                                rows={3}
                                value={findingForm.data.recommended_action}
                                onChange={(event) =>
                                    findingForm.setData(
                                        'recommended_action',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                        <div className="md:col-span-2">
                            <Field label="Rumusan Bukti & Analisis">
                                <Textarea
                                    rows={6}
                                    value={findingForm.data.finding_summary}
                                    onChange={(event) =>
                                        findingForm.setData(
                                            'finding_summary',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                        </div>
                        <ErrorText errors={findingForm.errors} />
                        <div className="flex justify-end md:col-span-2">
                            <Button disabled={findingForm.processing}>
                                <FileSearch /> Hantar Dapatan
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    );
}

function ShowCauseForm({ disciplineCase }: { disciplineCase: DisciplineCase }) {
    const form = useForm({ due_at: '', create_hr_document: true });

    return (
        <Card className="border-amber-300 dark:border-amber-800">
            <CardHeader>
                <CardTitle className="text-base">
                    Keluarkan Arahan Tunjuk Sebab
                </CardTitle>
            </CardHeader>
            <CardContent>
                <form
                    className="grid gap-3 md:grid-cols-[1fr_1fr_auto]"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.patch(
                            `/disiplin-aduan/${disciplineCase.id}/tunjuk-sebab`,
                            { preserveScroll: true },
                        );
                    }}
                >
                    <Input
                        type="datetime-local"
                        value={form.data.due_at}
                        onChange={(event) =>
                            form.setData('due_at', event.target.value)
                        }
                    />
                    <label className="flex items-center gap-2 rounded-md border px-3">
                        <Checkbox
                            checked={form.data.create_hr_document}
                            onCheckedChange={(checked) =>
                                form.setData(
                                    'create_hr_document',
                                    Boolean(checked),
                                )
                            }
                        />{' '}
                        Jana draf Dokumen HR
                    </label>
                    <Button disabled={form.processing}>Keluarkan</Button>
                    <div className="md:col-span-3">
                        <ErrorText errors={form.errors} />
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}

function DecisionForm({ disciplineCase }: { disciplineCase: DisciplineCase }) {
    const form = useForm({
        decision_outcome: 'no_action',
        decision_notes: '',
        effective_date: '',
        create_hr_document: true,
    });

    return (
        <Card className="border-red-300 dark:border-red-800">
            <CardHeader>
                <CardTitle className="flex items-center gap-2 text-base">
                    <Gavel className="size-4" /> Keputusan Tatatertib
                </CardTitle>
                <CardDescription>
                    Pelulus tidak boleh menjadi penyiasat aktif dalam kes yang
                    sama.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form
                    className="grid gap-3 md:grid-cols-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.patch(
                            `/disiplin-aduan/${disciplineCase.id}/keputusan`,
                            { preserveScroll: true },
                        );
                    }}
                >
                    <Field label="Keputusan">
                        <Select
                            value={form.data.decision_outcome}
                            onValueChange={(value) =>
                                form.setData('decision_outcome', value)
                            }
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {[
                                    'no_action',
                                    'counselling',
                                    'verbal_warning',
                                    'written_warning',
                                    'final_warning',
                                    'suspension',
                                    'demotion',
                                    'termination',
                                    'other',
                                ].map((value) => (
                                    <SelectItem key={value} value={value}>
                                        {outcomeLabel[value]}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
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
                    <div className="md:col-span-2">
                        <Field label="Alasan & Keputusan">
                            <Textarea
                                rows={6}
                                value={form.data.decision_notes}
                                onChange={(event) =>
                                    form.setData(
                                        'decision_notes',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                    </div>
                    <label className="flex items-center gap-2">
                        <Checkbox
                            checked={form.data.create_hr_document}
                            onCheckedChange={(checked) =>
                                form.setData(
                                    'create_hr_document',
                                    Boolean(checked),
                                )
                            }
                        />{' '}
                        Jana draf surat tindakan dalam Dokumen HR
                    </label>
                    <ErrorText errors={form.errors} />
                    <div className="flex justify-end md:col-span-2">
                        <Button disabled={form.processing}>
                            <Gavel /> Rekod Keputusan
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}

function AppealReview({
    disciplineCase,
    appeal,
}: {
    disciplineCase: DisciplineCase;
    appeal: Appeal;
}) {
    const form = useForm({
        outcome: 'upheld',
        decision_notes: '',
        revised_outcome: '',
    });

    return (
        <Card className="border-purple-300 dark:border-purple-800">
            <CardHeader>
                <CardTitle className="text-base">
                    Semakan Rayuan Bebas
                </CardTitle>
                <CardDescription>
                    Pelulus keputusan asal tidak boleh menyemak rayuan ini.
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                <div className="rounded-lg bg-muted p-3 text-sm">
                    <div className="font-medium">Alasan Rayuan</div>
                    <p className="mt-1 whitespace-pre-wrap">{appeal.grounds}</p>
                </div>
                <form
                    className="grid gap-3 md:grid-cols-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.patch(
                            `/disiplin-aduan/${disciplineCase.id}/rayuan/${appeal.id}`,
                            { preserveScroll: true },
                        );
                    }}
                >
                    <Field label="Keputusan Rayuan">
                        <Select
                            value={form.data.outcome}
                            onValueChange={(value) =>
                                form.setData('outcome', value)
                            }
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="upheld">Kekalkan</SelectItem>
                                <SelectItem value="varied">
                                    Ubah Hukuman
                                </SelectItem>
                                <SelectItem value="overturned">
                                    Batalkan Keputusan
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </Field>
                    {form.data.outcome === 'varied' && (
                        <Field label="Keputusan Baharu">
                            <Select
                                value={form.data.revised_outcome}
                                onValueChange={(value) =>
                                    form.setData('revised_outcome', value)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Pilih keputusan" />
                                </SelectTrigger>
                                <SelectContent>
                                    {[
                                        'no_action',
                                        'counselling',
                                        'verbal_warning',
                                        'written_warning',
                                        'final_warning',
                                        'suspension',
                                        'demotion',
                                        'termination',
                                        'other',
                                    ].map((value) => (
                                        <SelectItem key={value} value={value}>
                                            {outcomeLabel[value]}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </Field>
                    )}
                    <div className="md:col-span-2">
                        <Field label="Alasan Semakan">
                            <Textarea
                                rows={5}
                                value={form.data.decision_notes}
                                onChange={(event) =>
                                    form.setData(
                                        'decision_notes',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                    </div>
                    <ErrorText errors={form.errors} />
                    <div className="flex justify-end md:col-span-2">
                        <Button disabled={form.processing}>
                            Putuskan Rayuan
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}

function CaseDetail({
    disciplineCase,
    officers,
    permissions,
}: {
    disciplineCase: DisciplineCase;
    officers: Officer[];
    permissions: Props['permissions'];
}) {
    const currentMember = disciplineCase.members.find(
        (member) => member.is_current_user && !member.recused_at,
    );
    const canInvestigate = Boolean(
        currentMember?.conflict_declared &&
        currentMember.has_conflict === false &&
        disciplineCase.status === 'investigation',
    );
    const pendingAppeal = disciplineCase.appeals.find(
        (appeal) => appeal.status === 'pending',
    );
    const showCauseExpired = disciplineCase.show_cause_expired;
    const appealExpired = disciplineCase.appeal_expired;

    return (
        <div className="space-y-4">
            <Card>
                <CardHeader>
                    <div className="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <CardTitle>{disciplineCase.case_number}</CardTitle>
                            <CardDescription>
                                {disciplineCase.category?.name} ·{' '}
                                {disciplineCase.title}
                            </CardDescription>
                        </div>
                        <div className="flex gap-2">
                            <Badge
                                variant={
                                    disciplineCase.severity === 'critical'
                                        ? 'destructive'
                                        : 'outline'
                                }
                            >
                                {severityLabel[disciplineCase.severity]}
                            </Badge>
                            <StatusBadge status={disciplineCase.status} />
                        </div>
                    </div>
                </CardHeader>
                <CardContent className="space-y-4">
                    {disciplineCase.details_locked ? (
                        <div className="flex items-start gap-3 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm dark:border-amber-800 dark:bg-amber-950/20">
                            <LockKeyhole className="size-5 shrink-0" />
                            <p>
                                Butiran kes dikunci sehingga deklarasi konflik
                                kepentingan dilengkapkan.
                            </p>
                        </div>
                    ) : (
                        <>
                            <dl className="grid gap-3 text-sm md:grid-cols-2 xl:grid-cols-4">
                                <div>
                                    <dt className="text-muted-foreground">
                                        Pengadu
                                    </dt>
                                    <dd className="font-medium">
                                        {disciplineCase.complainant_name}
                                    </dd>
                                    <dd className="text-xs text-muted-foreground">
                                        {
                                            disciplineCase.complainant_department_name
                                        }
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">
                                        Subjek
                                    </dt>
                                    <dd className="font-medium">
                                        {disciplineCase.subject_name ??
                                            'Tidak diketahui'}
                                    </dd>
                                    <dd className="text-xs text-muted-foreground">
                                        {disciplineCase.subject_department_name}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">
                                        Kejadian
                                    </dt>
                                    <dd className="font-medium">
                                        {formatDate(
                                            disciplineCase.incident_at,
                                            true,
                                        )}
                                    </dd>
                                    <dd className="text-xs text-muted-foreground">
                                        {disciplineCase.incident_location}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">
                                        Sasaran Selesai
                                    </dt>
                                    <dd className="font-medium">
                                        {formatDate(
                                            disciplineCase.target_completion_date,
                                        )}
                                    </dd>
                                </div>
                            </dl>
                            <div className="rounded-lg bg-muted p-4">
                                <div className="text-xs font-medium uppercase">
                                    Keterangan Aduan
                                </div>
                                <p className="mt-2 text-sm whitespace-pre-wrap">
                                    {disciplineCase.description}
                                </p>
                            </div>
                            {disciplineCase.allegation_summary && (
                                <div className="rounded-lg border p-4">
                                    <div className="text-xs font-medium uppercase">
                                        Ringkasan Dakwaan
                                    </div>
                                    <p className="mt-2 text-sm whitespace-pre-wrap">
                                        {disciplineCase.allegation_summary}
                                    </p>
                                </div>
                            )}
                            {disciplineCase.finding_outcome && (
                                <div className="rounded-lg border p-4">
                                    <div className="font-medium">
                                        Dapatan:{' '}
                                        {
                                            outcomeLabel[
                                                disciplineCase.finding_outcome
                                            ]
                                        }
                                    </div>
                                    <p className="mt-2 text-sm whitespace-pre-wrap">
                                        {disciplineCase.finding_summary}
                                    </p>
                                    <p className="mt-2 text-sm text-muted-foreground">
                                        Cadangan:{' '}
                                        {disciplineCase.recommended_action ||
                                            '-'}
                                    </p>
                                </div>
                            )}
                            {disciplineCase.decision_outcome && (
                                <div className="rounded-lg border border-red-200 p-4 dark:border-red-900">
                                    <div className="font-medium">
                                        Keputusan:{' '}
                                        {
                                            outcomeLabel[
                                                disciplineCase.decision_outcome
                                            ]
                                        }
                                    </div>
                                    <p className="mt-2 text-sm whitespace-pre-wrap">
                                        {disciplineCase.decision_notes}
                                    </p>
                                </div>
                            )}
                            {disciplineCase.responses.map((response) => (
                                <div
                                    key={response.id}
                                    className="rounded-lg border border-blue-200 p-4 dark:border-blue-900"
                                >
                                    <div className="font-medium">
                                        Jawapan {response.user?.name}
                                    </div>
                                    <p className="mt-2 text-sm whitespace-pre-wrap">
                                        {response.statement}
                                    </p>
                                    <div className="mt-2 text-xs text-muted-foreground">
                                        {formatDate(
                                            response.submitted_at,
                                            true,
                                        )}
                                    </div>
                                </div>
                            ))}
                            {disciplineCase.attachments.length > 0 && (
                                <div className="space-y-2">
                                    <div className="text-xs font-medium uppercase">
                                        Lampiran Persendirian
                                    </div>
                                    {disciplineCase.attachments.map(
                                        (attachment) => (
                                            <Link
                                                key={attachment.id}
                                                href={`/disiplin-aduan/${disciplineCase.id}/lampiran/${attachment.id}`}
                                                className="flex items-center justify-between rounded-md border px-3 py-2 text-sm hover:bg-muted"
                                            >
                                                <span className="flex min-w-0 items-center gap-2">
                                                    <Paperclip className="size-4 shrink-0" />
                                                    <span className="truncate">
                                                        {
                                                            attachment.original_name
                                                        }
                                                    </span>
                                                </span>
                                                <span className="text-xs text-muted-foreground">
                                                    {formatSize(
                                                        attachment.size,
                                                    )}
                                                </span>
                                            </Link>
                                        ),
                                    )}
                                </div>
                            )}
                        </>
                    )}
                </CardContent>
            </Card>
            {currentMember && !currentMember.conflict_declared && (
                <ConflictDeclaration
                    disciplineCase={disciplineCase}
                    member={currentMember}
                />
            )}
            {!disciplineCase.details_locked &&
                permissions.manage &&
                ['submitted', 'triage'].includes(disciplineCase.status) && (
                    <TriageForm
                        disciplineCase={disciplineCase}
                        officers={officers}
                    />
                )}
            {!disciplineCase.details_locked &&
                permissions.manage &&
                ['investigation', 'show_cause_pending'].includes(
                    disciplineCase.status,
                ) && (
                    <TeamForm
                        disciplineCase={disciplineCase}
                        officers={officers}
                    />
                )}
            {!disciplineCase.details_locked && canInvestigate && (
                <InvestigationTools disciplineCase={disciplineCase} />
            )}
            {!disciplineCase.details_locked &&
                permissions.manage &&
                disciplineCase.status === 'show_cause_pending' && (
                    <ShowCauseForm disciplineCase={disciplineCase} />
                )}
            {!disciplineCase.details_locked &&
                permissions.manage &&
                disciplineCase.status === 'show_cause' &&
                showCauseExpired &&
                disciplineCase.responses.length === 0 && (
                    <Card className="border-amber-300 dark:border-amber-800">
                        <CardContent className="flex flex-wrap items-center justify-between gap-3 p-4">
                            <div>
                                <div className="font-medium">
                                    Tempoh jawapan telah tamat
                                </div>
                                <div className="text-sm text-muted-foreground">
                                    Kes boleh diteruskan berdasarkan rekod sedia
                                    ada.
                                </div>
                            </div>
                            <Button
                                variant="outline"
                                onClick={() => {
                                    if (
                                        window.confirm(
                                            'Teruskan kes untuk keputusan tanpa jawapan pekerja?',
                                        )
                                    ) {
                                        router.patch(
                                            `/disiplin-aduan/${disciplineCase.id}/tunjuk-sebab/tanpa-jawapan`,
                                            {},
                                            { preserveScroll: true },
                                        );
                                    }
                                }}
                            >
                                Teruskan Tanpa Jawapan
                            </Button>
                        </CardContent>
                    </Card>
                )}
            {!disciplineCase.details_locked &&
                permissions.approve &&
                disciplineCase.status === 'decision' &&
                disciplineCase.finding_submitted_at &&
                !disciplineCase.decided_at && (
                    <DecisionForm disciplineCase={disciplineCase} />
                )}
            {!disciplineCase.details_locked &&
                permissions.approve &&
                disciplineCase.status === 'appeal' &&
                pendingAppeal && (
                    <AppealReview
                        disciplineCase={disciplineCase}
                        appeal={pendingAppeal}
                    />
                )}
            {!disciplineCase.details_locked &&
                permissions.manage &&
                disciplineCase.status === 'decision' &&
                Boolean(disciplineCase.decided_at) &&
                appealExpired &&
                !pendingAppeal && (
                    <Card>
                        <CardContent className="flex flex-wrap items-center justify-between gap-3 p-4">
                            <div>
                                <div className="font-medium">
                                    Tempoh rayuan selesai
                                </div>
                                <div className="text-sm text-muted-foreground">
                                    Tutup dan kunci kes selepas semakan rekod
                                    akhir.
                                </div>
                            </div>
                            <Button
                                onClick={() => {
                                    const reason = window.prompt(
                                        'Nyatakan alasan penutupan kes:',
                                    );

                                    if (reason) {
                                        router.patch(
                                            `/disiplin-aduan/${disciplineCase.id}/tutup`,
                                            { reason },
                                            { preserveScroll: true },
                                        );
                                    }
                                }}
                            >
                                Tutup Kes
                            </Button>
                        </CardContent>
                    </Card>
                )}
            {!disciplineCase.details_locked &&
                disciplineCase.events.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Kronologi Kes
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {disciplineCase.events.map((event) => (
                                <div
                                    key={event.id}
                                    className="border-l-2 pl-3 text-sm"
                                >
                                    <div className="font-medium">
                                        {event.title}
                                    </div>
                                    <div className="text-muted-foreground">
                                        {event.details}
                                    </div>
                                    <div className="mt-1 text-xs text-muted-foreground">
                                        {formatDate(event.occurred_at, true)} ·{' '}
                                        {event.creator}
                                    </div>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                )}
            {disciplineCase.hr_document && (
                <Card>
                    <CardContent className="flex items-center justify-between gap-3 p-4">
                        <div>
                            <div className="font-medium">
                                Draf / Surat Dokumen HR
                            </div>
                            <div className="text-sm text-muted-foreground">
                                {disciplineCase.hr_document.reference_number ??
                                    `Dokumen #${disciplineCase.hr_document.id}`}{' '}
                                · {disciplineCase.hr_document.status}
                            </div>
                        </div>
                        <Button asChild variant="outline">
                            <Link href="/dokumen-hr">Buka Dokumen HR</Link>
                        </Button>
                    </CardContent>
                </Card>
            )}
        </div>
    );
}

export default function DisciplineIndex({
    cases,
    selectedCase,
    categories,
    officers,
    filters,
    statistics,
    permissions,
    notifications,
    unreadNotifications,
}: Props) {
    const filterForm = useForm({ ...filters });
    const applyFilters = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.get('/disiplin-aduan', filterForm.data, {
            preserveState: true,
            replace: true,
        });
    };
    const selectCase = (id: number) =>
        router.get(
            '/disiplin-aduan',
            { ...filters, case_id: id },
            { preserveState: true, preserveScroll: true, replace: true },
        );

    return (
        <>
            <Head title="Disiplin & Aduan" />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Disiplin & Aduan
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Triage sulit, siasatan bebas, tunjuk sebab,
                            keputusan dan rayuan.
                        </p>
                    </div>
                    <div className="flex gap-2">
                        {unreadNotifications > 0 && (
                            <Button
                                variant="outline"
                                onClick={() =>
                                    router.patch(
                                        '/disiplin-aduan/notifikasi/dibaca',
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <Bell /> {unreadNotifications} Dibaca
                            </Button>
                        )}
                        <Button asChild variant="outline">
                            <Link href="/disiplin-aduan/laporan.csv">
                                <Download /> CSV
                            </Link>
                        </Button>
                    </div>
                </div>
                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                    {[
                        {
                            label: 'Jumlah Kes',
                            value: statistics.total,
                            icon: ShieldAlert,
                        },
                        {
                            label: 'Triage',
                            value: statistics.new,
                            icon: AlertTriangle,
                        },
                        {
                            label: 'Siasatan',
                            value: statistics.investigation,
                            icon: FileSearch,
                        },
                        {
                            label: 'Keputusan',
                            value: statistics.decision,
                            icon: Gavel,
                        },
                        {
                            label: 'Lewat SLA',
                            value: statistics.overdue,
                            icon: AlertTriangle,
                        },
                    ].map(({ label, value, icon: Icon }) => (
                        <Card key={label}>
                            <CardContent className="flex items-center justify-between p-4">
                                <div>
                                    <div className="text-xs text-muted-foreground">
                                        {label}
                                    </div>
                                    <div className="text-2xl font-semibold">
                                        {value}
                                    </div>
                                </div>
                                <Icon className="size-5 text-red-600" />
                            </CardContent>
                        </Card>
                    ))}
                </div>
                {notifications.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Notifikasi Tugasan
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-2 md:grid-cols-2">
                            {notifications.slice(0, 6).map((notification) => (
                                <button
                                    key={notification.id}
                                    type="button"
                                    onClick={() =>
                                        notification.case_id &&
                                        selectCase(notification.case_id)
                                    }
                                    className={`rounded-lg border p-3 text-left text-sm ${notification.read_at ? '' : 'border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950/20'}`}
                                >
                                    <div className="font-medium">
                                        {notification.title}
                                    </div>
                                    <div className="text-muted-foreground">
                                        {notification.message}
                                    </div>
                                </button>
                            ))}
                        </CardContent>
                    </Card>
                )}
                <Card>
                    <CardContent className="p-4">
                        <form
                            className="grid gap-3 md:grid-cols-[1fr_180px_180px_220px_auto]"
                            onSubmit={applyFilters}
                        >
                            <div className="relative">
                                <Search className="absolute top-2.5 left-3 size-4 text-muted-foreground" />
                                <Input
                                    className="pl-9"
                                    value={filterForm.data.search}
                                    onChange={(event) =>
                                        filterForm.setData(
                                            'search',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Cari nombor, tajuk atau pekerja"
                                />
                            </div>
                            <Select
                                value={filterForm.data.status || 'all'}
                                onValueChange={(value) =>
                                    filterForm.setData(
                                        'status',
                                        value === 'all' ? '' : value,
                                    )
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
                                            <SelectItem
                                                key={value}
                                                value={value}
                                            >
                                                {label}
                                            </SelectItem>
                                        ),
                                    )}
                                </SelectContent>
                            </Select>
                            <Select
                                value={filterForm.data.severity || 'all'}
                                onValueChange={(value) =>
                                    filterForm.setData(
                                        'severity',
                                        value === 'all' ? '' : value,
                                    )
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Semua tahap" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        Semua Tahap
                                    </SelectItem>
                                    {Object.entries(severityLabel).map(
                                        ([value, label]) => (
                                            <SelectItem
                                                key={value}
                                                value={value}
                                            >
                                                {label}
                                            </SelectItem>
                                        ),
                                    )}
                                </SelectContent>
                            </Select>
                            <Select
                                value={filterForm.data.category_id || 'all'}
                                onValueChange={(value) =>
                                    filterForm.setData(
                                        'category_id',
                                        value === 'all' ? '' : value,
                                    )
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
                                        <SelectItem
                                            key={category.id}
                                            value={String(category.id)}
                                        >
                                            {category.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Button>Tapiskan</Button>
                        </form>
                    </CardContent>
                </Card>
                <div className="grid items-start gap-4 xl:grid-cols-[340px_minmax(0,1fr)]">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Senarai Kes
                            </CardTitle>
                            <CardDescription>
                                {cases.total} rekod boleh dilihat
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            {cases.data.map((disciplineCase) => (
                                <button
                                    key={disciplineCase.id}
                                    type="button"
                                    onClick={() =>
                                        selectCase(disciplineCase.id)
                                    }
                                    className={`w-full rounded-lg border p-3 text-left transition hover:bg-muted ${selectedCase?.id === disciplineCase.id ? 'border-red-400 bg-red-50/50 dark:border-red-800 dark:bg-red-950/20' : ''}`}
                                >
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="font-medium">
                                            {disciplineCase.case_number}
                                        </div>
                                        <Badge
                                            variant={
                                                disciplineCase.severity ===
                                                'critical'
                                                    ? 'destructive'
                                                    : 'outline'
                                            }
                                        >
                                            {
                                                severityLabel[
                                                    disciplineCase.severity
                                                ]
                                            }
                                        </Badge>
                                    </div>
                                    <div className="mt-1 line-clamp-2 text-sm">
                                        {disciplineCase.title}
                                    </div>
                                    <div className="mt-2 flex items-center justify-between gap-2">
                                        <StatusBadge
                                            status={disciplineCase.status}
                                        />
                                        <span className="text-xs text-muted-foreground">
                                            {formatDate(
                                                disciplineCase.created_at,
                                            )}
                                        </span>
                                    </div>
                                </button>
                            ))}
                            {cases.data.length === 0 && (
                                <div className="py-8 text-center text-sm text-muted-foreground">
                                    Tiada kes ditemui.
                                </div>
                            )}
                            {cases.last_page > 1 && (
                                <div className="flex flex-wrap gap-1 pt-2">
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
                            disciplineCase={selectedCase}
                            officers={officers}
                            permissions={permissions}
                        />
                    ) : (
                        <Card>
                            <CardContent className="p-10 text-center text-sm text-muted-foreground">
                                <UsersRound className="mx-auto mb-3 size-8" />
                                Pilih satu kes untuk melihat butiran.
                            </CardContent>
                        </Card>
                    )}
                </div>
                <Separator />
                <div className="flex items-start gap-3 rounded-lg border p-4 text-sm text-muted-foreground">
                    <UserRoundCheck className="size-5 shrink-0" />
                    <p>
                        Rekod ini diklasifikasikan sebagai{' '}
                        <strong>Terhad</strong>. Jangan salin, kongsi atau muat
                        turun maklumat di luar tujuan tugas yang diluluskan.
                    </p>
                </div>
            </div>
        </>
    );
}

DisciplineIndex.layout = {
    breadcrumbs: [{ title: 'Disiplin & Aduan', href: '/disiplin-aduan' }],
};
