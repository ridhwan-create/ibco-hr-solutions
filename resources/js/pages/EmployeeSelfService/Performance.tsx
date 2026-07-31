import { Head, router, useForm } from '@inertiajs/react';
import {
    AlertCircle,
    Bell,
    CheckCircle2,
    Download,
    FileUp,
    Save,
    Send,
    Target,
    TrendingUp,
    Trash2,
} from 'lucide-react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type Status =
    | 'goal_setting'
    | 'self_assessment'
    | 'supervisor_assessment'
    | 'hr_moderation'
    | 'finalized';
type Goal = {
    id: number;
    title: string;
    description: string | null;
    measure_type: string;
    target_value: number | null;
    unit: string | null;
    weight: number;
    scoring_guide: string | null;
    actual_achievement: string | null;
    self_score: number | null;
    self_comments: string | null;
    supervisor_score: number | null;
    supervisor_comments: string | null;
    moderated_score: number | null;
    moderation_comments: string | null;
};
type Review = {
    id: number;
    cycle: {
        name: string;
        period_start: string;
        period_end: string;
        self_assessment_due_at: string;
        supervisor_due_at: string;
    };
    template: string | null;
    supervisor: string | null;
    status: Status;
    self_score: number | null;
    supervisor_score: number | null;
    moderated_score: number | null;
    final_rating: string | null;
    employee_summary: string | null;
    supervisor_summary: string | null;
    strengths: string | null;
    improvement_areas: string | null;
    development_plan: string | null;
    hr_comments: string | null;
    goals: Goal[];
    evidence: {
        id: number;
        goal_id: number | null;
        original_name: string;
        mime_type: string;
        size: number;
        description: string | null;
    }[];
    pip: {
        status: string;
        start_date: string;
        end_date: string;
        reason: string;
        objectives: string;
        required_actions: string;
        support_required: string | null;
        success_criteria: string;
        outcome: string | null;
        checkins: {
            checkin_date: string;
            progress_status: string;
            progress_notes: string;
            next_actions: string | null;
        }[];
    } | null;
};
type Props = {
    employee: {
        id: number;
        employee_number: string | null;
        name: string;
        position_name: string | null;
        department: string | null;
    } | null;
    summary: {
        active: number;
        awaiting_self: number;
        finalized: number;
        latest_score: number | null;
        unread_notifications: number;
        active_pip: number;
    };
    reviews: Review[];
    notifications: {
        id: number;
        title: string;
        message: string;
        type: string;
        read_at: string | null;
        created_at: string;
    }[];
};

const statusLabel: Record<Status, string> = {
    goal_setting: 'Penyediaan Sasaran',
    self_assessment: 'Self-Assessment',
    supervisor_assessment: 'Penilaian Penyelia',
    hr_moderation: 'Moderasi HR',
    finalized: 'Dimuktamadkan',
};
const statusStyle: Record<Status, string> = {
    goal_setting:
        'border-slate-500/30 bg-slate-500/10 text-slate-700 dark:text-slate-300',
    self_assessment:
        'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300',
    supervisor_assessment:
        'border-sky-500/30 bg-sky-500/10 text-sky-700 dark:text-sky-300',
    hr_moderation:
        'border-violet-500/30 bg-violet-500/10 text-violet-700 dark:text-violet-300',
    finalized:
        'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
};

function SelfAssessmentForm({ review }: { review: Review }) {
    const form = useForm({
        goals: review.goals.map((goal) => ({
            id: goal.id,
            actual_achievement: goal.actual_achievement ?? '',
            self_score: goal.self_score === null ? '' : String(goal.self_score),
            self_comments: goal.self_comments ?? '',
        })),
        employee_summary: review.employee_summary ?? '',
    });
    const setGoal = (
        index: number,
        field: 'actual_achievement' | 'self_score' | 'self_comments',
        value: string,
    ) => {
        form.setData(
            'goals',
            form.data.goals.map((goal, goalIndex) =>
                goalIndex === index ? { ...goal, [field]: value } : goal,
            ),
        );
    };
    const save = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.put(`/prestasi-saya/${review.id}/draf`, {
            preserveScroll: true,
        });
    };
    const submit = () => {
        if (
            window.confirm(
                'Hantar Self-Assessment kepada penyelia? Selepas dihantar, jawapan tidak boleh diubah.',
            )
        ) {
            form.patch(`/prestasi-saya/${review.id}/hantar`, {
                preserveScroll: true,
            });
        }
    };

    return (
        <form onSubmit={save} className="mt-5 space-y-4">
            {review.goals.map((goal, index) => (
                <div key={goal.id} className="rounded-xl border p-4">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p className="font-semibold">{goal.title}</p>
                            <p className="text-sm text-muted-foreground">
                                {goal.description || 'Tiada penerangan.'}
                            </p>
                        </div>
                        <Badge variant="outline">Pemberat {goal.weight}%</Badge>
                    </div>
                    <div className="mt-3 grid gap-3 sm:grid-cols-2">
                        <div className="rounded-md bg-muted/40 p-3 text-sm">
                            <p>
                                Sasaran:{' '}
                                <strong>
                                    {goal.target_value ?? '-'} {goal.unit}
                                </strong>
                            </p>
                            {goal.scoring_guide && (
                                <p className="mt-1 text-xs text-muted-foreground">
                                    {goal.scoring_guide}
                                </p>
                            )}
                        </div>
                        <div className="space-y-2">
                            <Label>Skor Kendiri (1–5)</Label>
                            <Select
                                value={form.data.goals[index].self_score}
                                onValueChange={(value) =>
                                    setGoal(index, 'self_score', value)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Pilih skor" />
                                </SelectTrigger>
                                <SelectContent>
                                    {['1', '2', '3', '4', '5'].map((score) => (
                                        <SelectItem key={score} value={score}>
                                            {score}.00
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-2 sm:col-span-2">
                            <Label>Pencapaian Sebenar</Label>
                            <textarea
                                className="min-h-24 w-full rounded-md border bg-background px-3 py-2 text-sm"
                                value={
                                    form.data.goals[index].actual_achievement
                                }
                                onChange={(event) =>
                                    setGoal(
                                        index,
                                        'actual_achievement',
                                        event.target.value,
                                    )
                                }
                                placeholder="Nyatakan hasil, angka atau output yang telah dicapai."
                            />
                        </div>
                        <div className="space-y-2 sm:col-span-2">
                            <Label>Ulasan Kendiri</Label>
                            <textarea
                                className="min-h-20 w-full rounded-md border bg-background px-3 py-2 text-sm"
                                value={form.data.goals[index].self_comments}
                                onChange={(event) =>
                                    setGoal(
                                        index,
                                        'self_comments',
                                        event.target.value,
                                    )
                                }
                            />
                        </div>
                    </div>
                </div>
            ))}
            <div className="space-y-2">
                <Label>Rumusan Self-Assessment</Label>
                <textarea
                    className="min-h-28 w-full rounded-md border bg-background px-3 py-2 text-sm"
                    value={form.data.employee_summary}
                    onChange={(event) =>
                        form.setData('employee_summary', event.target.value)
                    }
                    placeholder="Ringkaskan pencapaian utama, cabaran dan bantuan yang diperlukan."
                />
            </div>
            <InputError
                message={(form.errors as Record<string, string>).goals}
            />
            <div className="flex flex-wrap justify-end gap-2">
                <Button
                    type="submit"
                    variant="outline"
                    disabled={form.processing}
                >
                    <Save />
                    Simpan Draf
                </Button>
                <Button
                    type="button"
                    onClick={submit}
                    disabled={form.processing}
                >
                    <Send />
                    Hantar kepada Penyelia
                </Button>
            </div>
        </form>
    );
}

function EvidenceForm({ review }: { review: Review }) {
    const form = useForm({
        performance_goal_id: '',
        description: '',
        evidence: null as File | null,
    });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(`/prestasi-saya/${review.id}/bukti`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <div className="mt-5 rounded-xl border p-4">
            <h3 className="flex items-center gap-2 font-semibold">
                <FileUp className="size-4 text-emerald-600" />
                Bukti Pencapaian
            </h3>
            {review.status === 'self_assessment' && (
                <form
                    onSubmit={submit}
                    className="mt-3 grid gap-3 md:grid-cols-2"
                >
                    <div className="space-y-2">
                        <Label>Sasaran Berkaitan</Label>
                        <Select
                            value={form.data.performance_goal_id || 'general'}
                            onValueChange={(value) =>
                                form.setData(
                                    'performance_goal_id',
                                    value === 'general' ? '' : value,
                                )
                            }
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="general">
                                    Bukti Umum
                                </SelectItem>
                                {review.goals.map((goal) => (
                                    <SelectItem
                                        key={goal.id}
                                        value={String(goal.id)}
                                    >
                                        {goal.title}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="space-y-2">
                        <Label>Fail (maksimum 8 MB)</Label>
                        <Input
                            type="file"
                            accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx"
                            onChange={(event) =>
                                form.setData(
                                    'evidence',
                                    event.target.files?.[0] ?? null,
                                )
                            }
                        />
                    </div>
                    <div className="space-y-2 md:col-span-2">
                        <Label>Penerangan</Label>
                        <Input
                            value={form.data.description}
                            onChange={(event) =>
                                form.setData('description', event.target.value)
                            }
                            placeholder="Contoh: Laporan projek siap dan disahkan pelanggan"
                        />
                    </div>
                    <div className="md:col-span-2">
                        <Button disabled={form.processing}>
                            <FileUp />
                            Muat Naik Bukti
                        </Button>
                    </div>
                </form>
            )}
            <div className="mt-4 space-y-2">
                {review.evidence.map((evidence) => (
                    <div
                        key={evidence.id}
                        className="flex flex-wrap items-center justify-between gap-3 rounded-lg bg-muted/40 p-3 text-sm"
                    >
                        <div>
                            <p className="font-medium">
                                {evidence.original_name}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                {evidence.description || 'Tiada penerangan'} ·{' '}
                                {(evidence.size / 1024).toFixed(1)} KB
                            </p>
                        </div>
                        <div className="flex gap-2">
                            <Button size="sm" variant="outline" asChild>
                                <a
                                    href={`/prestasi-saya/${review.id}/bukti/${evidence.id}`}
                                >
                                    <Download />
                                    Muat Turun
                                </a>
                            </Button>
                            {review.status === 'self_assessment' && (
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    onClick={() => {
                                        if (
                                            window.confirm(
                                                'Buang fail bukti ini?',
                                            )
                                        ) {
                                            router.delete(
                                                `/prestasi-saya/${review.id}/bukti/${evidence.id}`,
                                                { preserveScroll: true },
                                            );
                                        }
                                    }}
                                >
                                    <Trash2 />
                                </Button>
                            )}
                        </div>
                    </div>
                ))}
                {review.evidence.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        Belum ada bukti dimuat naik.
                    </p>
                )}
            </div>
        </div>
    );
}

function ReviewCard({ review }: { review: Review }) {
    return (
        <Card>
            <CardHeader>
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <CardTitle className="flex items-center gap-2">
                            <Target className="size-5 text-emerald-600" />
                            {review.cycle.name}
                        </CardTitle>
                        <CardDescription>
                            {review.cycle.period_start} –{' '}
                            {review.cycle.period_end} ·{' '}
                            {review.template ?? 'Template KPI'} · Penyelia{' '}
                            {review.supervisor ?? 'belum ditetapkan'}
                        </CardDescription>
                    </div>
                    <Badge
                        variant="outline"
                        className={statusStyle[review.status]}
                    >
                        {statusLabel[review.status]}
                    </Badge>
                </div>
            </CardHeader>
            <CardContent>
                {review.status === 'self_assessment' && (
                    <Alert className="mb-4">
                        <AlertCircle />
                        <AlertTitle>Self-Assessment diperlukan</AlertTitle>
                        <AlertDescription>
                            Lengkapkan dan hantar sebelum{' '}
                            {review.cycle.self_assessment_due_at}.
                        </AlertDescription>
                    </Alert>
                )}
                <div className="grid gap-3 sm:grid-cols-3">
                    {[
                        ['Skor Kendiri', review.self_score],
                        ['Skor Penyelia', review.supervisor_score],
                        ['Skor Akhir', review.moderated_score],
                    ].map(([label, score]) => (
                        <div
                            key={String(label)}
                            className="rounded-lg border bg-muted/30 p-3"
                        >
                            <p className="text-xs text-muted-foreground">
                                {label}
                            </p>
                            <p className="text-xl font-semibold">
                                {typeof score === 'number'
                                    ? `${score.toFixed(2)} / 5.00`
                                    : '-'}
                            </p>
                        </div>
                    ))}
                </div>

                {review.status === 'self_assessment' ? (
                    <SelfAssessmentForm review={review} />
                ) : (
                    <div className="mt-5 space-y-3">
                        {review.goals.map((goal) => (
                            <div
                                key={goal.id}
                                className="rounded-lg border p-4"
                            >
                                <div className="flex flex-wrap justify-between gap-2">
                                    <p className="font-medium">{goal.title}</p>
                                    <Badge variant="outline">
                                        {goal.weight}%
                                    </Badge>
                                </div>
                                <p className="mt-2 text-sm text-muted-foreground">
                                    Pencapaian:{' '}
                                    {goal.actual_achievement ?? 'Belum diisi'}
                                </p>
                                <div className="mt-2 grid gap-2 text-sm sm:grid-cols-3">
                                    <p>
                                        Kendiri:{' '}
                                        <strong>
                                            {goal.self_score ?? '-'}
                                        </strong>
                                    </p>
                                    <p>
                                        Penyelia:{' '}
                                        <strong>
                                            {goal.supervisor_score ?? '-'}
                                        </strong>
                                    </p>
                                    <p>
                                        Akhir:{' '}
                                        <strong>
                                            {goal.moderated_score ?? '-'}
                                        </strong>
                                    </p>
                                </div>
                                {goal.supervisor_comments && (
                                    <p className="mt-2 text-sm">
                                        <strong>Ulasan Penyelia:</strong>{' '}
                                        {goal.supervisor_comments}
                                    </p>
                                )}
                            </div>
                        ))}
                    </div>
                )}

                <EvidenceForm review={review} />

                {review.status === 'finalized' && (
                    <div className="mt-5 space-y-4 rounded-xl border border-emerald-500/30 bg-emerald-500/5 p-4">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p className="text-sm text-muted-foreground">
                                    Rating Akhir
                                </p>
                                <p className="text-xl font-bold text-emerald-700 dark:text-emerald-300">
                                    {review.final_rating}
                                </p>
                            </div>
                            <Button asChild>
                                <a
                                    href={`/prestasi-saya/${review.id}/laporan.pdf`}
                                >
                                    <Download />
                                    Laporan PDF
                                </a>
                            </Button>
                        </div>
                        <div className="grid gap-3 text-sm md:grid-cols-2">
                            <div>
                                <strong>Kekuatan</strong>
                                <p>{review.strengths || '-'}</p>
                            </div>
                            <div>
                                <strong>Ruang Penambahbaikan</strong>
                                <p>{review.improvement_areas || '-'}</p>
                            </div>
                            <div>
                                <strong>Pelan Pembangunan</strong>
                                <p>{review.development_plan || '-'}</p>
                            </div>
                            <div>
                                <strong>Ulasan HR</strong>
                                <p>{review.hr_comments || '-'}</p>
                            </div>
                        </div>
                    </div>
                )}

                {review.pip && (
                    <div className="mt-5 rounded-xl border border-amber-500/30 bg-amber-500/5 p-4">
                        <div className="flex items-center justify-between gap-3">
                            <h3 className="font-semibold">
                                Pelan Peningkatan Prestasi (PIP)
                            </h3>
                            <Badge variant="outline">{review.pip.status}</Badge>
                        </div>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {review.pip.start_date} – {review.pip.end_date}
                        </p>
                        <div className="mt-3 grid gap-3 text-sm md:grid-cols-2">
                            <div>
                                <strong>Sebab</strong>
                                <p>{review.pip.reason}</p>
                            </div>
                            <div>
                                <strong>Objektif</strong>
                                <p>{review.pip.objectives}</p>
                            </div>
                            <div>
                                <strong>Tindakan</strong>
                                <p>{review.pip.required_actions}</p>
                            </div>
                            <div>
                                <strong>Kriteria Kejayaan</strong>
                                <p>{review.pip.success_criteria}</p>
                            </div>
                        </div>
                        {review.pip.checkins.length > 0 && (
                            <div className="mt-4 space-y-2">
                                <p className="font-medium">
                                    Sejarah Semakan Kemajuan
                                </p>
                                {review.pip.checkins.map((checkin, index) => (
                                    <div
                                        key={index}
                                        className="rounded-lg bg-background/70 p-3 text-sm"
                                    >
                                        <p className="font-medium">
                                            {checkin.checkin_date} ·{' '}
                                            {checkin.progress_status}
                                        </p>
                                        <p>{checkin.progress_notes}</p>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

export default function EmployeePerformance({
    employee,
    summary,
    reviews,
    notifications,
}: Props) {
    if (!employee) {
        return (
            <>
                <Head title="Prestasi Saya" />
                <div className="mx-auto flex w-full max-w-4xl flex-1 p-4 md:p-6">
                    <Alert variant="destructive">
                        <AlertCircle />
                        <AlertTitle>Modul prestasi belum tersedia</AlertTitle>
                        <AlertDescription>
                            Akaun anda belum dipautkan kepada rekod pekerja
                            aktif. Hubungi Super Admin untuk melengkapkan
                            pautan.
                        </AlertDescription>
                    </Alert>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title="Prestasi Saya" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="flex items-center gap-2 text-2xl font-semibold">
                        <TrendingUp className="size-6 text-emerald-600" />
                        Prestasi Saya
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {employee.name} · {employee.employee_number} ·{' '}
                        {employee.position_name} · {employee.department}
                    </p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    {[
                        ['Aktif', summary.active],
                        ['Tindakan Saya', summary.awaiting_self],
                        ['Dimuktamadkan', summary.finalized],
                        [
                            'Skor Terkini',
                            summary.latest_score === null
                                ? '-'
                                : summary.latest_score.toFixed(2),
                        ],
                        ['PIP Aktif', summary.active_pip],
                    ].map(([label, value]) => (
                        <Card key={String(label)}>
                            <CardContent className="pt-5">
                                <p className="text-sm text-muted-foreground">
                                    {label}
                                </p>
                                <p className="text-2xl font-semibold">
                                    {value}
                                </p>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                {notifications.length > 0 && (
                    <Card>
                        <CardHeader className="flex-row items-start justify-between gap-4">
                            <div>
                                <CardTitle className="flex items-center gap-2">
                                    <Bell className="size-5 text-amber-600" />
                                    Notifikasi Prestasi
                                </CardTitle>
                                <CardDescription>
                                    {summary.unread_notifications} belum dibaca
                                </CardDescription>
                            </div>
                            {summary.unread_notifications > 0 && (
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() =>
                                        router.patch(
                                            '/prestasi-saya/notifikasi/dibaca',
                                            {},
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    <CheckCircle2 />
                                    Tandakan Dibaca
                                </Button>
                            )}
                        </CardHeader>
                        <CardContent className="space-y-2">
                            {notifications.slice(0, 6).map((notification) => (
                                <div
                                    key={notification.id}
                                    className={`rounded-lg border p-3 ${
                                        notification.read_at
                                            ? 'opacity-70'
                                            : 'border-amber-500/30 bg-amber-500/5'
                                    }`}
                                >
                                    <p className="font-medium">
                                        {notification.title}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {notification.message}
                                    </p>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                )}

                <div className="space-y-5">
                    {reviews.map((review) => (
                        <ReviewCard key={review.id} review={review} />
                    ))}
                    {reviews.length === 0 && (
                        <Card>
                            <CardContent className="py-12 text-center text-muted-foreground">
                                Belum ada penilaian prestasi untuk akaun ini.
                            </CardContent>
                        </Card>
                    )}
                </div>
            </div>
        </>
    );
}
