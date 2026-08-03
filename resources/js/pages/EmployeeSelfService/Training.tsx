import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    Award,
    BookOpenCheck,
    CalendarDays,
    CheckCircle2,
    Download,
    FileUp,
    GraduationCap,
    Plus,
    Star,
    Target,
    WalletCards,
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

type Session = {
    id: number;
    session_code: string;
    title: string;
    provider: string | null;
    starts_at: string;
    ends_at: string;
    venue: string | null;
    cost: number;
    available_seats: number;
};
type Attachment = {
    id: number;
    type: string;
    name: string;
    valid_until: string | null;
};
type TrainingRequest = {
    id: number;
    request_number: string;
    course_title: string;
    session: {
        session_code: string;
        starts_at: string;
        ends_at: string;
        venue: string | null;
        provider: string | null;
    } | null;
    development_source: string;
    development_plan: string | null;
    justification: string;
    estimated_cost: number;
    approved_cost: number | null;
    status: string;
    approval_stage: string | null;
    supervisor_notes: string | null;
    hr_notes: string | null;
    attendance_status: string;
    attended_hours: number | null;
    assessment_score: number | null;
    passed: boolean | null;
    employee_rating: number | null;
    employee_feedback: string | null;
    created_at: string;
    attachments: Attachment[];
};
type DevelopmentPlan = {
    id: number;
    title: string;
    source: string;
    competency: string | null;
    action_plan: string;
    target_level: number | null;
    due_date: string;
    status: string;
};
type CompetencyGap = {
    competency_id: number;
    code: string;
    name: string;
    category: string;
    current_level: number;
    required_level: number;
    gap: number;
    is_mandatory: boolean;
};
type Props = {
    employee: {
        employee_number: string;
        name: string;
        department_name: string | null;
        position_name: string | null;
    } | null;
    requests: TrainingRequest[];
    sessions: Session[];
    developmentPlans: DevelopmentPlan[];
    competencyGaps: CompetencyGap[];
    notifications: {
        id: number;
        title: string;
        message: string;
        read_at: string | null;
        created_at: string;
    }[];
    unreadNotifications: number;
};

const statusLabel: Record<string, string> = {
    pending: 'Menunggu Kelulusan',
    approved: 'Diluluskan',
    rejected: 'Ditolak',
    cancelled: 'Dibatalkan',
    completed: 'Selesai',
    planned: 'Dirancang',
    in_progress: 'Dalam Tindakan',
    attended: 'Hadir',
    passed: 'Lulus',
    failed: 'Tidak Lulus',
    no_show: 'Tidak Hadir',
    not_recorded: 'Belum Direkod',
};
const sourceLabel: Record<string, string> = {
    self: 'Permohonan Sendiri',
    kpi: 'KPI / Penilaian Prestasi',
    pip: 'Performance Improvement Plan',
    onboarding: 'Onboarding',
    mandatory: 'Latihan Wajib',
    competency_gap: 'Jurang Kompetensi',
};

function RequestDialog({
    sessions,
    plans,
}: {
    sessions: Session[];
    plans: DevelopmentPlan[];
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        training_session_id: '',
        course_title: '',
        justification: '',
        development_source: 'self',
        development_plan_id: '',
        estimated_cost: '0',
        supporting_document: null as File | null,
    });
    const selectSession = (value: string) => {
        const session = sessions.find((item) => item.id === Number(value));
        form.setData((data) => ({
            ...data,
            training_session_id: value === 'external' ? '' : value,
            course_title: session?.title ?? data.course_title,
            estimated_cost: session
                ? String(session.cost)
                : data.estimated_cost,
        }));
    };
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post('/latihan-saya', {
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
                <Button>
                    <Plus />
                    Mohon Latihan
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[92vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Permohonan Latihan Baharu</DialogTitle>
                    <DialogDescription>
                        Pilih sesi dalaman atau masukkan kursus luar yang
                        dicadangkan.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-2">
                        <Label>Sesi / Kursus</Label>
                        <Select
                            value={form.data.training_session_id || 'external'}
                            onValueChange={selectSession}
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="external">
                                    Kursus luar / belum dijadualkan
                                </SelectItem>
                                {sessions.map((session) => (
                                    <SelectItem
                                        key={session.id}
                                        value={String(session.id)}
                                    >
                                        {session.title} ·{' '}
                                        {new Date(
                                            session.starts_at,
                                        ).toLocaleDateString('ms-MY')}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.training_session_id} />
                    </div>
                    <div className="space-y-2">
                        <Label>Nama Kursus</Label>
                        <Input
                            value={form.data.course_title}
                            onChange={(event) =>
                                form.setData('course_title', event.target.value)
                            }
                            disabled={Boolean(form.data.training_session_id)}
                            placeholder="Contoh: Laravel Security Fundamentals"
                        />
                        <InputError message={form.errors.course_title} />
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-2">
                            <Label>Sumber Pembangunan</Label>
                            <Select
                                value={form.data.development_source}
                                onValueChange={(value) =>
                                    form.setData('development_source', value)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {Object.entries(sourceLabel).map(
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
                        </div>
                        <div className="space-y-2">
                            <Label>Anggaran Kos (RM)</Label>
                            <Input
                                type="number"
                                min="0"
                                step="0.01"
                                value={form.data.estimated_cost}
                                onChange={(event) =>
                                    form.setData(
                                        'estimated_cost',
                                        event.target.value,
                                    )
                                }
                            />
                        </div>
                        <div className="space-y-2 sm:col-span-2">
                            <Label>Pelan Pembangunan (pilihan)</Label>
                            <Select
                                value={form.data.development_plan_id || 'none'}
                                onValueChange={(value) =>
                                    form.setData(
                                        'development_plan_id',
                                        value === 'none' ? '' : value,
                                    )
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">
                                        Tidak dipautkan
                                    </SelectItem>
                                    {plans
                                        .filter(
                                            (plan) =>
                                                ![
                                                    'completed',
                                                    'cancelled',
                                                ].includes(plan.status),
                                        )
                                        .map((plan) => (
                                            <SelectItem
                                                key={plan.id}
                                                value={String(plan.id)}
                                            >
                                                {plan.title}
                                            </SelectItem>
                                        ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    <div className="space-y-2">
                        <Label>Justifikasi</Label>
                        <textarea
                            className="min-h-28 w-full rounded-md border bg-background px-3 py-2 text-sm"
                            value={form.data.justification}
                            onChange={(event) =>
                                form.setData(
                                    'justification',
                                    event.target.value,
                                )
                            }
                        />
                        <InputError message={form.errors.justification} />
                    </div>
                    <div className="space-y-2">
                        <Label>Dokumen Sokongan (PDF/JPG/PNG)</Label>
                        <Input
                            type="file"
                            accept=".pdf,.jpg,.jpeg,.png"
                            onChange={(event) =>
                                form.setData(
                                    'supporting_document',
                                    event.target.files?.[0] ?? null,
                                )
                            }
                        />
                        <InputError message={form.errors.supporting_document} />
                    </div>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Tutup
                            </Button>
                        </DialogClose>
                        <Button disabled={form.processing}>
                            Hantar Permohonan
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function CertificateDialog({ training }: { training: TrainingRequest }) {
    const [open, setOpen] = useState(false);
    const form = useForm({ certificate: null as File | null, valid_until: '' });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(`/latihan-saya/${training.id}/sijil`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    <FileUp />
                    Muat Naik Sijil
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Sijil {training.course_title}</DialogTitle>
                    <DialogDescription>
                        Fail disimpan secara persendirian dan hanya boleh
                        dicapai oleh anda serta HR.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-2">
                        <Label>Sijil</Label>
                        <Input
                            type="file"
                            accept=".pdf,.jpg,.jpeg,.png"
                            onChange={(event) =>
                                form.setData(
                                    'certificate',
                                    event.target.files?.[0] ?? null,
                                )
                            }
                        />
                        <InputError message={form.errors.certificate} />
                    </div>
                    <div className="space-y-2">
                        <Label>Sah Sehingga (pilihan)</Label>
                        <Input
                            type="date"
                            value={form.data.valid_until}
                            onChange={(event) =>
                                form.setData('valid_until', event.target.value)
                            }
                        />
                    </div>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Tutup
                            </Button>
                        </DialogClose>
                        <Button disabled={form.processing}>Simpan Sijil</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function EvaluationDialog({ training }: { training: TrainingRequest }) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        employee_rating: String(training.employee_rating ?? 5),
        employee_feedback: training.employee_feedback ?? '',
    });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.put(`/latihan-saya/${training.id}/penilaian`, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    <Star />
                    Nilai Latihan
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Penilaian Keberkesanan</DialogTitle>
                    <DialogDescription>
                        Maklum balas membantu HR menilai penyedia dan kandungan
                        latihan.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-2">
                        <Label>Rating</Label>
                        <Select
                            value={form.data.employee_rating}
                            onValueChange={(value) =>
                                form.setData('employee_rating', value)
                            }
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {[1, 2, 3, 4, 5].map((rating) => (
                                    <SelectItem
                                        key={rating}
                                        value={String(rating)}
                                    >
                                        {rating} / 5
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="space-y-2">
                        <Label>Maklum Balas</Label>
                        <textarea
                            className="min-h-28 w-full rounded-md border bg-background px-3 py-2 text-sm"
                            value={form.data.employee_feedback}
                            onChange={(event) =>
                                form.setData(
                                    'employee_feedback',
                                    event.target.value,
                                )
                            }
                        />
                        <InputError message={form.errors.employee_feedback} />
                    </div>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Tutup
                            </Button>
                        </DialogClose>
                        <Button disabled={form.processing}>
                            Simpan Penilaian
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function EmployeeTraining({
    employee,
    requests,
    sessions,
    developmentPlans,
    competencyGaps,
    notifications,
    unreadNotifications,
}: Props) {
    const completed = requests.filter((item) => item.status === 'completed');
    const hours = completed.reduce(
        (total, item) => total + (item.attended_hours ?? 0),
        0,
    );
    const certificates = requests
        .flatMap((item) => item.attachments)
        .filter((item) => item.type === 'certificate');

    return (
        <>
            <Head title="Latihan & Kompetensi Saya" />
            <div className="flex h-full flex-1 flex-col gap-5 overflow-x-auto rounded-xl p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Latihan & Kompetensi Saya
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {employee
                                ? `${employee.employee_number} · ${employee.position_name ?? 'Jawatan belum ditetapkan'} · ${employee.department_name ?? 'Tanpa jabatan'}`
                                : 'Profil pekerja belum dipautkan.'}
                        </p>
                    </div>
                    {employee && (
                        <RequestDialog
                            sessions={sessions}
                            plans={developmentPlans}
                        />
                    )}
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <Card>
                        <CardContent className="flex items-center gap-3 p-5">
                            <GraduationCap className="size-8 text-indigo-600" />
                            <div>
                                <p className="text-xs text-muted-foreground">
                                    Latihan Selesai
                                </p>
                                <p className="text-2xl font-semibold">
                                    {completed.length}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="flex items-center gap-3 p-5">
                            <BookOpenCheck className="size-8 text-emerald-600" />
                            <div>
                                <p className="text-xs text-muted-foreground">
                                    Jumlah Jam
                                </p>
                                <p className="text-2xl font-semibold">
                                    {hours.toFixed(1)}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="flex items-center gap-3 p-5">
                            <Award className="size-8 text-amber-600" />
                            <div>
                                <p className="text-xs text-muted-foreground">
                                    Sijil
                                </p>
                                <p className="text-2xl font-semibold">
                                    {certificates.length}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="flex items-center gap-3 p-5">
                            <Target className="size-8 text-rose-600" />
                            <div>
                                <p className="text-xs text-muted-foreground">
                                    Jurang Kompetensi
                                </p>
                                <p className="text-2xl font-semibold">
                                    {
                                        competencyGaps.filter(
                                            (item) => item.gap > 0,
                                        ).length
                                    }
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {unreadNotifications > 0 && (
                    <Card className="border-indigo-500/30 bg-indigo-500/5">
                        <CardHeader className="pb-2">
                            <div className="flex items-center justify-between">
                                <CardTitle className="text-base">
                                    Notifikasi Latihan ({unreadNotifications})
                                </CardTitle>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() =>
                                        router.patch(
                                            '/latihan-saya/notifikasi/dibaca',
                                        )
                                    }
                                >
                                    Tandakan Dibaca
                                </Button>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            {notifications
                                .filter((item) => !item.read_at)
                                .map((item) => (
                                    <div
                                        key={item.id}
                                        className="rounded-md border bg-background p-3"
                                    >
                                        <p className="font-medium">
                                            {item.title}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {item.message}
                                        </p>
                                    </div>
                                ))}
                        </CardContent>
                    </Card>
                )}

                <div className="grid gap-5 xl:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Matriks Kompetensi Saya</CardTitle>
                            <CardDescription>
                                Tahap 0 bermaksud belum dinilai. Fokus kepada
                                jurang terbesar terlebih dahulu.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {competencyGaps.length === 0 ? (
                                <p className="py-8 text-center text-sm text-muted-foreground">
                                    Tiada keperluan kompetensi ditetapkan untuk
                                    jawatan anda.
                                </p>
                            ) : (
                                competencyGaps.map((item) => (
                                    <div
                                        key={item.competency_id}
                                        className="rounded-lg border p-3"
                                    >
                                        <div className="flex items-start justify-between gap-2">
                                            <div>
                                                <p className="font-medium">
                                                    {item.name}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {item.code} ·{' '}
                                                    {item.category}
                                                </p>
                                            </div>
                                            <Badge
                                                variant={
                                                    item.gap > 0
                                                        ? 'destructive'
                                                        : 'outline'
                                                }
                                            >
                                                {item.gap > 0
                                                    ? `Jurang ${item.gap}`
                                                    : 'Memenuhi'}
                                            </Badge>
                                        </div>
                                        <div className="mt-3 grid grid-cols-2 gap-2 text-sm">
                                            <span>
                                                Semasa:{' '}
                                                <strong>
                                                    {item.current_level}
                                                </strong>
                                            </span>
                                            <span>
                                                Sasaran:{' '}
                                                <strong>
                                                    {item.required_level}
                                                </strong>
                                                {item.is_mandatory
                                                    ? ' · Wajib'
                                                    : ''}
                                            </span>
                                        </div>
                                    </div>
                                ))
                            )}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Pelan Pembangunan</CardTitle>
                            <CardDescription>
                                Pelan daripada KPI, PIP, onboarding atau jurang
                                kompetensi.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {developmentPlans.length === 0 ? (
                                <p className="py-8 text-center text-sm text-muted-foreground">
                                    Tiada pelan pembangunan aktif.
                                </p>
                            ) : (
                                developmentPlans.map((plan) => (
                                    <div
                                        key={plan.id}
                                        className="rounded-lg border p-3"
                                    >
                                        <div className="flex items-start justify-between gap-2">
                                            <div>
                                                <p className="font-medium">
                                                    {plan.title}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {plan.competency ??
                                                        sourceLabel[
                                                            plan.source
                                                        ] ??
                                                        plan.source}
                                                </p>
                                            </div>
                                            <Badge variant="outline">
                                                {statusLabel[plan.status] ??
                                                    plan.status}
                                            </Badge>
                                        </div>
                                        <p className="mt-2 text-sm text-muted-foreground">
                                            {plan.action_plan}
                                        </p>
                                        <p className="mt-2 text-xs">
                                            Tarikh sasaran: {plan.due_date}
                                            {plan.target_level
                                                ? ` · Tahap ${plan.target_level}`
                                                : ''}
                                        </p>
                                    </div>
                                ))
                            )}
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Rekod Permohonan & Latihan</CardTitle>
                        <CardDescription>
                            Jejak kelulusan, kehadiran, keputusan, sijil dan
                            penilaian.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {requests.length === 0 ? (
                            <p className="py-12 text-center text-sm text-muted-foreground">
                                Belum ada rekod latihan.
                            </p>
                        ) : (
                            requests.map((training) => (
                                <div
                                    key={training.id}
                                    className="rounded-lg border p-4"
                                >
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <p className="font-semibold">
                                                {training.course_title}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {training.request_number} ·{' '}
                                                {sourceLabel[
                                                    training.development_source
                                                ] ??
                                                    training.development_source}
                                            </p>
                                        </div>
                                        <Badge
                                            variant={
                                                training.status === 'rejected'
                                                    ? 'destructive'
                                                    : 'outline'
                                            }
                                        >
                                            {statusLabel[training.status] ??
                                                training.status}
                                            {training.approval_stage
                                                ? ` · ${training.approval_stage === 'supervisor' ? 'Penyelia' : 'HR'}`
                                                : ''}
                                        </Badge>
                                    </div>
                                    {training.session && (
                                        <div className="mt-3 grid gap-2 text-sm sm:grid-cols-3">
                                            <span>
                                                <CalendarDays className="mr-1 inline size-4" />
                                                {new Date(
                                                    training.session.starts_at,
                                                ).toLocaleDateString('ms-MY')}
                                            </span>
                                            <span>
                                                {training.session.provider ??
                                                    'Penyedia dalaman'}
                                            </span>
                                            <span>
                                                {training.session.venue ??
                                                    'Lokasi belum ditetapkan'}
                                            </span>
                                        </div>
                                    )}
                                    <p className="mt-3 text-sm text-muted-foreground">
                                        {training.justification}
                                    </p>
                                    <div className="mt-3 flex flex-wrap items-center gap-2 text-sm">
                                        <Badge variant="secondary">
                                            <WalletCards className="mr-1 size-3" />
                                            RM{' '}
                                            {(
                                                training.approved_cost ??
                                                training.estimated_cost
                                            ).toFixed(2)}
                                        </Badge>
                                        {training.status === 'completed' && (
                                            <Badge variant="secondary">
                                                <CheckCircle2 className="mr-1 size-3" />
                                                {statusLabel[
                                                    training.attendance_status
                                                ] ??
                                                    training.attendance_status}{' '}
                                                · {training.attended_hours ?? 0}{' '}
                                                jam
                                            </Badge>
                                        )}
                                        {training.assessment_score !== null && (
                                            <Badge variant="secondary">
                                                Skor {training.assessment_score}
                                            </Badge>
                                        )}
                                    </div>
                                    {(training.supervisor_notes ||
                                        training.hr_notes) && (
                                        <div className="mt-3 rounded-md bg-muted p-3 text-sm">
                                            <p>
                                                {training.supervisor_notes &&
                                                    `Penyelia: ${training.supervisor_notes}`}
                                            </p>
                                            <p>
                                                {training.hr_notes &&
                                                    `HR: ${training.hr_notes}`}
                                            </p>
                                        </div>
                                    )}
                                    <div className="mt-4 flex flex-wrap gap-2">
                                        {training.status === 'pending' && (
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    window.confirm(
                                                        'Batalkan permohonan latihan ini?',
                                                    ) &&
                                                    router.patch(
                                                        `/latihan-saya/${training.id}/batal`,
                                                    )
                                                }
                                            >
                                                Batalkan
                                            </Button>
                                        )}
                                        {['approved', 'completed'].includes(
                                            training.status,
                                        ) && (
                                            <CertificateDialog
                                                training={training}
                                            />
                                        )}
                                        {training.status === 'completed' && (
                                            <EvaluationDialog
                                                training={training}
                                            />
                                        )}
                                        {training.attachments.map(
                                            (attachment) => (
                                                <Button
                                                    key={attachment.id}
                                                    size="sm"
                                                    variant="ghost"
                                                    asChild
                                                >
                                                    <Link
                                                        href={`/latihan-saya/${training.id}/lampiran/${attachment.id}`}
                                                    >
                                                        <Download />
                                                        {attachment.type ===
                                                        'certificate'
                                                            ? 'Sijil'
                                                            : attachment.name}
                                                    </Link>
                                                </Button>
                                            ),
                                        )}
                                    </div>
                                </div>
                            ))
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
