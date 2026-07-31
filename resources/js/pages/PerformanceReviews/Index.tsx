import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    BarChart3,
    CheckCircle2,
    ClipboardCheck,
    Download,
    FileText,
    Plus,
    Search,
    Target,
    TrendingUp,
    UserRoundCheck,
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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

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
    employee: {
        id: number;
        employee_number: string | null;
        name: string;
    } | null;
    department: string | null;
    position_name: string | null;
    cycle: {
        id: number;
        name: string;
        self_assessment_due_at: string;
        supervisor_due_at: string;
        moderation_due_at: string;
    };
    template: string | null;
    supervisor: string | null;
    supervisor_user_id: number | null;
    status: Status;
    total_weight: number;
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
        id: number;
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
            id: number;
            checkin_date: string;
            progress_status: string;
            progress_notes: string;
            next_actions: string | null;
        }[];
    } | null;
};
type Props = {
    reviews: {
        data: Review[];
        from: number | null;
        to: number | null;
        total: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: {
        search: string;
        cycle_id: string;
        status: string;
        department_id: string;
    };
    statistics: {
        total: number;
        self_pending: number;
        supervisor_pending: number;
        hr_pending: number;
        finalized: number;
        average_score: number;
        active_pips: number;
    };
    cycles: {
        id: number;
        name: string;
        status: string;
        period_start: string;
        period_end: string;
    }[];
    departments: { id: number; name: string }[];
    departmentPerformance: {
        department_id: number | null;
        department: string;
        total: number;
        finalized: number;
        average_score: number | null;
    }[];
    templates: {
        id: number;
        name: string;
        department_id: number | null;
        position_name: string | null;
        total_weight: number;
    }[];
    employees: {
        id: number;
        employee_number: string;
        name: string;
        department_id: number | null;
        position_name: string | null;
        user_id: number;
    }[];
    supervisors: { id: number; name: string; email: string }[];
    permissions: {
        can_manage: boolean;
        can_supervise: boolean;
        can_moderate: boolean;
        can_finalize: boolean;
    };
};

const statusLabel: Record<Status, string> = {
    goal_setting: 'Penyediaan Sasaran',
    self_assessment: 'Self-Assessment',
    supervisor_assessment: 'Menunggu Penyelia',
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

function CreateReviewDialog({
    cycles,
    employees,
    templates,
    supervisors,
}: Pick<Props, 'cycles' | 'employees' | 'templates' | 'supervisors'>) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        performance_cycle_id: '',
        employee_id: '',
        performance_template_id: '',
        supervisor_user_id: '',
    });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post('/prestasi', {
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
                    Jana Individu
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Jana Penilaian Individu</DialogTitle>
                    <DialogDescription>
                        Sasaran template disalin sebagai snapshot ke rekod
                        pekerja.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-2">
                        <Label>Kitaran</Label>
                        <Select
                            value={form.data.performance_cycle_id || undefined}
                            onValueChange={(value) =>
                                form.setData('performance_cycle_id', value)
                            }
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Pilih kitaran" />
                            </SelectTrigger>
                            <SelectContent>
                                {cycles
                                    .filter((cycle) =>
                                        ['draft', 'open'].includes(
                                            cycle.status,
                                        ),
                                    )
                                    .map((cycle) => (
                                        <SelectItem
                                            key={cycle.id}
                                            value={String(cycle.id)}
                                        >
                                            {cycle.name} · {cycle.status}
                                        </SelectItem>
                                    ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="space-y-2">
                        <Label>Pekerja</Label>
                        <Select
                            value={form.data.employee_id || undefined}
                            onValueChange={(value) =>
                                form.setData('employee_id', value)
                            }
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Pilih pekerja" />
                            </SelectTrigger>
                            <SelectContent>
                                {employees.map((employee) => (
                                    <SelectItem
                                        key={employee.id}
                                        value={String(employee.id)}
                                    >
                                        {employee.name} ·{' '}
                                        {employee.employee_number}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="space-y-2">
                        <Label>Template KPI</Label>
                        <Select
                            value={
                                form.data.performance_template_id || undefined
                            }
                            onValueChange={(value) =>
                                form.setData('performance_template_id', value)
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
                                        disabled={
                                            Math.abs(
                                                template.total_weight - 100,
                                            ) > 0.01
                                        }
                                    >
                                        {template.name} ·{' '}
                                        {template.total_weight}%
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="space-y-2">
                        <Label>Penyelia (pilihan)</Label>
                        <Select
                            value={form.data.supervisor_user_id || 'auto'}
                            onValueChange={(value) =>
                                form.setData(
                                    'supervisor_user_id',
                                    value === 'auto' ? '' : value,
                                )
                            }
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="auto">
                                    Guna Tetapan Jabatan
                                </SelectItem>
                                {supervisors.map((supervisor) => (
                                    <SelectItem
                                        key={supervisor.id}
                                        value={String(supervisor.id)}
                                    >
                                        {supervisor.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <InputError
                        message={
                            (form.errors as Record<string, string>).employee_id
                        }
                    />
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Tutup
                            </Button>
                        </DialogClose>
                        <Button disabled={form.processing}>
                            <Target />
                            Jana Penilaian
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function SupervisorDialog({ review }: { review: Review }) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        goals: review.goals.map((goal) => ({
            id: goal.id,
            supervisor_score:
                goal.supervisor_score === null
                    ? String(goal.self_score ?? '')
                    : String(goal.supervisor_score),
            supervisor_comments: goal.supervisor_comments ?? '',
        })),
        supervisor_summary: review.supervisor_summary ?? '',
        strengths: review.strengths ?? '',
        improvement_areas: review.improvement_areas ?? '',
        development_plan: review.development_plan ?? '',
    });
    const setGoal = (index: number, field: string, value: string) =>
        form.setData(
            'goals',
            form.data.goals.map((goal, goalIndex) =>
                goalIndex === index ? { ...goal, [field]: value } : goal,
            ),
        );
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.put(`/prestasi/${review.id}/penyelia`, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm">
                    <ClipboardCheck />
                    Nilai
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[92vh] overflow-y-auto sm:max-w-4xl">
                <DialogHeader>
                    <DialogTitle>Penilaian Penyelia</DialogTitle>
                    <DialogDescription>
                        {review.employee?.name} · {review.cycle.name}
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    {review.goals.map((goal, index) => (
                        <div key={goal.id} className="rounded-lg border p-4">
                            <div className="flex justify-between gap-3">
                                <div>
                                    <p className="font-semibold">
                                        {goal.title}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        Pencapaian:{' '}
                                        {goal.actual_achievement || '-'}
                                    </p>
                                    <p className="text-sm">
                                        Skor kendiri:{' '}
                                        <strong>
                                            {goal.self_score ?? '-'}
                                        </strong>
                                    </p>
                                </div>
                                <Badge variant="outline">{goal.weight}%</Badge>
                            </div>
                            <div className="mt-3 grid gap-3 sm:grid-cols-[10rem_1fr]">
                                <div className="space-y-2">
                                    <Label>Skor Penyelia</Label>
                                    <Select
                                        value={
                                            form.data.goals[index]
                                                .supervisor_score
                                        }
                                        onValueChange={(value) =>
                                            setGoal(
                                                index,
                                                'supervisor_score',
                                                value,
                                            )
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Skor" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {['1', '2', '3', '4', '5'].map(
                                                (score) => (
                                                    <SelectItem
                                                        key={score}
                                                        value={score}
                                                    >
                                                        {score}.00
                                                    </SelectItem>
                                                ),
                                            )}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="space-y-2">
                                    <Label>Ulasan Penyelia</Label>
                                    <textarea
                                        className="min-h-20 w-full rounded-md border bg-background px-3 py-2 text-sm"
                                        value={
                                            form.data.goals[index]
                                                .supervisor_comments
                                        }
                                        onChange={(event) =>
                                            setGoal(
                                                index,
                                                'supervisor_comments',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </div>
                            </div>
                        </div>
                    ))}
                    {[
                        ['supervisor_summary', 'Rumusan Penyelia'],
                        ['strengths', 'Kekuatan'],
                        ['improvement_areas', 'Ruang Penambahbaikan'],
                        ['development_plan', 'Pelan Pembangunan'],
                    ].map(([field, label]) => (
                        <div className="space-y-2" key={field}>
                            <Label>{label}</Label>
                            <textarea
                                className="min-h-20 w-full rounded-md border bg-background px-3 py-2 text-sm"
                                value={
                                    form.data[
                                        field as
                                            | 'supervisor_summary'
                                            | 'strengths'
                                            | 'improvement_areas'
                                            | 'development_plan'
                                    ]
                                }
                                onChange={(event) =>
                                    form.setData(
                                        field as
                                            | 'supervisor_summary'
                                            | 'strengths'
                                            | 'improvement_areas'
                                            | 'development_plan',
                                        event.target.value,
                                    )
                                }
                            />
                        </div>
                    ))}
                    <InputError
                        message={(form.errors as Record<string, string>).goals}
                    />
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Tutup
                            </Button>
                        </DialogClose>
                        <Button disabled={form.processing}>
                            <UserRoundCheck />
                            Hantar kepada HR
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function ModerationDialog({
    review,
    canFinalize,
}: {
    review: Review;
    canFinalize: boolean;
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        goals: review.goals.map((goal) => ({
            id: goal.id,
            moderated_score:
                goal.moderated_score === null
                    ? String(goal.supervisor_score ?? '')
                    : String(goal.moderated_score),
            moderation_comments: goal.moderation_comments ?? '',
        })),
        hr_comments: review.hr_comments ?? '',
    });
    const setGoal = (index: number, field: string, value: string) =>
        form.setData(
            'goals',
            form.data.goals.map((goal, goalIndex) =>
                goalIndex === index ? { ...goal, [field]: value } : goal,
            ),
        );
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.put(`/prestasi/${review.id}/moderasi`, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm">
                    <BarChart3 />
                    Moderasi
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[92vh] overflow-y-auto sm:max-w-4xl">
                <DialogHeader>
                    <DialogTitle>Moderasi & Pengesahan HR</DialogTitle>
                    <DialogDescription>
                        {review.employee?.name} · Skor penyelia{' '}
                        {review.supervisor_score ?? '-'} / 5.00
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    {review.goals.map((goal, index) => (
                        <div
                            key={goal.id}
                            className="grid gap-3 rounded-lg border p-4 sm:grid-cols-[1fr_10rem]"
                        >
                            <div>
                                <p className="font-semibold">{goal.title}</p>
                                <p className="text-sm text-muted-foreground">
                                    Penyelia: {goal.supervisor_score ?? '-'} ·{' '}
                                    {goal.supervisor_comments || 'Tiada ulasan'}
                                </p>
                                <textarea
                                    className="mt-3 min-h-16 w-full rounded-md border bg-background px-3 py-2 text-sm"
                                    value={
                                        form.data.goals[index]
                                            .moderation_comments
                                    }
                                    onChange={(event) =>
                                        setGoal(
                                            index,
                                            'moderation_comments',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Catatan pelarasan skor (jika ada)"
                                />
                            </div>
                            <div className="space-y-2">
                                <Label>Skor Akhir</Label>
                                <Select
                                    value={
                                        form.data.goals[index].moderated_score
                                    }
                                    onValueChange={(value) =>
                                        setGoal(index, 'moderated_score', value)
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Skor" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {['1', '2', '3', '4', '5'].map(
                                            (score) => (
                                                <SelectItem
                                                    key={score}
                                                    value={score}
                                                >
                                                    {score}.00
                                                </SelectItem>
                                            ),
                                        )}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                    ))}
                    <div className="space-y-2">
                        <Label>Ulasan HR</Label>
                        <textarea
                            className="min-h-24 w-full rounded-md border bg-background px-3 py-2 text-sm"
                            value={form.data.hr_comments}
                            onChange={(event) =>
                                form.setData('hr_comments', event.target.value)
                            }
                        />
                    </div>
                    <DialogFooter className="flex-wrap">
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Tutup
                            </Button>
                        </DialogClose>
                        <Button disabled={form.processing}>
                            <BarChart3 />
                            Simpan Moderasi
                        </Button>
                        {canFinalize && review.moderated_score !== null && (
                            <Button
                                type="button"
                                variant="secondary"
                                onClick={() => {
                                    if (
                                        window.confirm(
                                            'Muktamadkan penilaian ini? Keputusan akan dipaparkan kepada pekerja.',
                                        )
                                    ) {
                                        router.patch(
                                            `/prestasi/${review.id}/muktamad`,
                                            {},
                                            { preserveScroll: true },
                                        );
                                        setOpen(false);
                                    }
                                }}
                            >
                                <CheckCircle2 />
                                Muktamadkan
                            </Button>
                        )}
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function PipDialog({ review }: { review: Review }) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        status: review.pip?.status ?? 'draft',
        start_date: review.pip?.start_date ?? '',
        end_date: review.pip?.end_date ?? '',
        reason: review.pip?.reason ?? '',
        objectives: review.pip?.objectives ?? '',
        required_actions: review.pip?.required_actions ?? '',
        support_required: review.pip?.support_required ?? '',
        success_criteria: review.pip?.success_criteria ?? '',
        outcome: review.pip?.outcome ?? '',
    });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.put(`/prestasi/${review.id}/pip`, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    <TrendingUp />
                    {review.pip ? 'Kemaskini PIP' : 'Buka PIP'}
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[92vh] overflow-y-auto sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>Pelan Peningkatan Prestasi (PIP)</DialogTitle>
                    <DialogDescription>
                        {review.employee?.name} · {review.final_rating}
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-3 sm:grid-cols-3">
                        <div className="space-y-2">
                            <Label>Status</Label>
                            <Select
                                value={form.data.status}
                                onValueChange={(value) =>
                                    form.setData('status', value)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="draft">Draf</SelectItem>
                                    <SelectItem value="active">
                                        Aktif
                                    </SelectItem>
                                    <SelectItem value="extended">
                                        Dilanjutkan
                                    </SelectItem>
                                    <SelectItem value="completed">
                                        Selesai
                                    </SelectItem>
                                    <SelectItem value="cancelled">
                                        Dibatalkan
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-2">
                            <Label>Tarikh Mula</Label>
                            <Input
                                type="date"
                                value={form.data.start_date}
                                onChange={(event) =>
                                    form.setData(
                                        'start_date',
                                        event.target.value,
                                    )
                                }
                            />
                        </div>
                        <div className="space-y-2">
                            <Label>Tarikh Tamat</Label>
                            <Input
                                type="date"
                                value={form.data.end_date}
                                onChange={(event) =>
                                    form.setData('end_date', event.target.value)
                                }
                            />
                        </div>
                    </div>
                    {[
                        ['reason', 'Sebab PIP'],
                        ['objectives', 'Objektif Peningkatan'],
                        ['required_actions', 'Tindakan Diperlukan'],
                        ['support_required', 'Sokongan Organisasi'],
                        ['success_criteria', 'Kriteria Kejayaan'],
                        ['outcome', 'Keputusan Akhir'],
                    ].map(([field, label]) => (
                        <div className="space-y-2" key={field}>
                            <Label>{label}</Label>
                            <textarea
                                className="min-h-20 w-full rounded-md border bg-background px-3 py-2 text-sm"
                                value={
                                    form.data[
                                        field as
                                            | 'reason'
                                            | 'objectives'
                                            | 'required_actions'
                                            | 'support_required'
                                            | 'success_criteria'
                                            | 'outcome'
                                    ]
                                }
                                onChange={(event) =>
                                    form.setData(
                                        field as
                                            | 'reason'
                                            | 'objectives'
                                            | 'required_actions'
                                            | 'support_required'
                                            | 'success_criteria'
                                            | 'outcome',
                                        event.target.value,
                                    )
                                }
                            />
                        </div>
                    ))}
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Tutup
                            </Button>
                        </DialogClose>
                        <Button disabled={form.processing}>
                            <TrendingUp />
                            Simpan PIP
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function CheckinDialog({ review }: { review: Review }) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        checkin_date: '',
        progress_status: 'on_track',
        progress_notes: '',
        next_actions: '',
    });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(`/prestasi/pip/${review.pip?.id}/semakan`, {
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
                    <Plus />
                    Semakan PIP
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Rekod Kemajuan PIP</DialogTitle>
                    <DialogDescription>
                        {review.employee?.name}
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-2">
                        <Label>Tarikh Semakan</Label>
                        <Input
                            type="date"
                            value={form.data.checkin_date}
                            onChange={(event) =>
                                form.setData('checkin_date', event.target.value)
                            }
                        />
                    </div>
                    <div className="space-y-2">
                        <Label>Status Kemajuan</Label>
                        <Select
                            value={form.data.progress_status}
                            onValueChange={(value) =>
                                form.setData('progress_status', value)
                            }
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="on_track">
                                    Mengikut Perancangan
                                </SelectItem>
                                <SelectItem value="needs_attention">
                                    Perlu Perhatian
                                </SelectItem>
                                <SelectItem value="completed">
                                    Selesai
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="space-y-2">
                        <Label>Catatan Kemajuan</Label>
                        <textarea
                            className="min-h-24 w-full rounded-md border bg-background px-3 py-2 text-sm"
                            value={form.data.progress_notes}
                            onChange={(event) =>
                                form.setData(
                                    'progress_notes',
                                    event.target.value,
                                )
                            }
                        />
                    </div>
                    <div className="space-y-2">
                        <Label>Tindakan Seterusnya</Label>
                        <textarea
                            className="min-h-20 w-full rounded-md border bg-background px-3 py-2 text-sm"
                            value={form.data.next_actions}
                            onChange={(event) =>
                                form.setData('next_actions', event.target.value)
                            }
                        />
                    </div>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Tutup
                            </Button>
                        </DialogClose>
                        <Button disabled={form.processing}>
                            Simpan Semakan
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function paginationLabel(label: string): string {
    return label
        .replace('&laquo; Previous', 'Sebelum')
        .replace('Next &raquo;', 'Seterusnya');
}

export default function PerformanceReviews({
    reviews,
    filters,
    statistics,
    cycles,
    departments,
    departmentPerformance,
    templates,
    employees,
    supervisors,
    permissions,
}: Props) {
    const [search, setSearch] = useState(filters.search);
    const applyFilters = (updates: Partial<Props['filters']> = {}) => {
        router.get(
            '/prestasi',
            { ...filters, search, ...updates },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };
    const selectedCycle = cycles.find(
        (cycle) => String(cycle.id) === filters.cycle_id,
    );

    return (
        <>
            <Head title="Prestasi & KPI" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="flex items-center gap-2 text-2xl font-semibold">
                            <Target className="size-6 text-emerald-600" />
                            Prestasi, KPI & Penilaian Tahunan
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Self-Assessment → Penyelia → Moderasi HR → Muktamad.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <a href="/prestasi/laporan.csv">
                                <Download />
                                Eksport CSV
                            </a>
                        </Button>
                        {permissions.can_manage && (
                            <CreateReviewDialog
                                cycles={cycles}
                                employees={employees}
                                templates={templates}
                                supervisors={supervisors}
                            />
                        )}
                        {permissions.can_manage &&
                            selectedCycle &&
                            ['draft', 'open'].includes(
                                selectedCycle.status,
                            ) && (
                                <Button
                                    onClick={() => {
                                        if (
                                            window.confirm(
                                                `Jana penilaian bagi semua pekerja berpaut untuk ${selectedCycle.name}?`,
                                            )
                                        ) {
                                            router.post(
                                                `/prestasi/kitaran/${selectedCycle.id}/jana`,
                                                {},
                                                { preserveScroll: true },
                                            );
                                        }
                                    }}
                                >
                                    <Target />
                                    Jana Pukal
                                </Button>
                            )}
                    </div>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
                    {[
                        ['Jumlah', statistics.total],
                        ['Tindakan Pekerja', statistics.self_pending],
                        ['Menunggu Penyelia', statistics.supervisor_pending],
                        ['Menunggu HR', statistics.hr_pending],
                        ['Dimuktamadkan', statistics.finalized],
                        ['PIP Aktif', statistics.active_pips],
                    ].map(([label, value]) => (
                        <Card key={String(label)}>
                            <CardContent className="pt-5">
                                <p className="text-xs text-muted-foreground">
                                    {label}
                                </p>
                                <p className="text-2xl font-semibold">
                                    {value}
                                </p>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Search className="size-5 text-emerald-600" />
                            Carian & Tapisan
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form
                            onSubmit={(event) => {
                                event.preventDefault();
                                applyFilters();
                            }}
                            className="grid gap-3 lg:grid-cols-[1fr_14rem_14rem_14rem_auto]"
                        >
                            <Input
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Nama atau ID pekerja"
                            />
                            <Select
                                value={filters.cycle_id || 'all'}
                                onValueChange={(value) =>
                                    applyFilters({
                                        cycle_id: value === 'all' ? '' : value,
                                    })
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        Semua Kitaran
                                    </SelectItem>
                                    {cycles.map((cycle) => (
                                        <SelectItem
                                            key={cycle.id}
                                            value={String(cycle.id)}
                                        >
                                            {cycle.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Select
                                value={filters.status || 'all'}
                                onValueChange={(value) =>
                                    applyFilters({
                                        status: value === 'all' ? '' : value,
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
                                value={filters.department_id || 'all'}
                                onValueChange={(value) =>
                                    applyFilters({
                                        department_id:
                                            value === 'all' ? '' : value,
                                    })
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        Semua Jabatan
                                    </SelectItem>
                                    {departments.map((department) => (
                                        <SelectItem
                                            key={department.id}
                                            value={String(department.id)}
                                        >
                                            {department.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Button>
                                <Search />
                                Cari
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                {permissions.can_manage && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <BarChart3 className="size-5 text-emerald-600" />
                                Prestasi Mengikut Jabatan
                            </CardTitle>
                            <CardDescription>
                                Purata hanya menggunakan penilaian yang telah
                                dimuktamadkan.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Jabatan</TableHead>
                                        <TableHead>Jumlah</TableHead>
                                        <TableHead>Dimuktamadkan</TableHead>
                                        <TableHead>Purata Skor</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {departmentPerformance.map((row) => (
                                        <TableRow
                                            key={
                                                row.department_id ??
                                                'without-department'
                                            }
                                        >
                                            <TableCell>
                                                {row.department}
                                            </TableCell>
                                            <TableCell>{row.total}</TableCell>
                                            <TableCell>
                                                {row.finalized}
                                            </TableCell>
                                            <TableCell>
                                                {row.average_score === null
                                                    ? '-'
                                                    : `${row.average_score.toFixed(2)} / 5.00`}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Senarai Penilaian</CardTitle>
                        <CardDescription>
                            Menunjukkan {reviews.from ?? 0}–{reviews.to ?? 0}{' '}
                            daripada {reviews.total} rekod · Purata akhir{' '}
                            {statistics.average_score.toFixed(2)} / 5.00.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {reviews.data.map((review) => (
                            <div
                                key={review.id}
                                className="rounded-xl border p-4"
                            >
                                <div className="flex flex-wrap items-start justify-between gap-4">
                                    <div>
                                        <p className="font-semibold">
                                            {review.employee?.name ??
                                                `Pekerja #${review.id}`}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {review.employee?.employee_number} ·{' '}
                                            {review.department} ·{' '}
                                            {review.position_name}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {review.cycle.name} · Penyelia{' '}
                                            {review.supervisor ??
                                                'belum ditetapkan'}
                                        </p>
                                    </div>
                                    <Badge
                                        variant="outline"
                                        className={statusStyle[review.status]}
                                    >
                                        {statusLabel[review.status]}
                                    </Badge>
                                </div>
                                <div className="mt-3 grid gap-2 text-sm sm:grid-cols-4">
                                    <div className="rounded-md bg-muted/40 p-2">
                                        Kendiri:{' '}
                                        <strong>
                                            {review.self_score ?? '-'}
                                        </strong>
                                    </div>
                                    <div className="rounded-md bg-muted/40 p-2">
                                        Penyelia:{' '}
                                        <strong>
                                            {review.supervisor_score ?? '-'}
                                        </strong>
                                    </div>
                                    <div className="rounded-md bg-muted/40 p-2">
                                        Akhir:{' '}
                                        <strong>
                                            {review.moderated_score ?? '-'}
                                        </strong>
                                    </div>
                                    <div className="rounded-md bg-muted/40 p-2">
                                        Rating:{' '}
                                        <strong>
                                            {review.final_rating ?? '-'}
                                        </strong>
                                    </div>
                                </div>
                                <details className="mt-3">
                                    <summary className="cursor-pointer text-sm font-medium text-emerald-700 dark:text-emerald-300">
                                        Lihat sasaran, ulasan dan bukti
                                    </summary>
                                    <div className="mt-3 space-y-3">
                                        {review.goals.map((goal) => (
                                            <div
                                                key={goal.id}
                                                className="rounded-lg bg-muted/30 p-3 text-sm"
                                            >
                                                <div className="flex justify-between gap-3">
                                                    <strong>
                                                        {goal.title}
                                                    </strong>
                                                    <span>{goal.weight}%</span>
                                                </div>
                                                <p className="text-muted-foreground">
                                                    Pencapaian:{' '}
                                                    {goal.actual_achievement ||
                                                        '-'}
                                                </p>
                                                <p>
                                                    Skor K/P/A:{' '}
                                                    {goal.self_score ?? '-'} /{' '}
                                                    {goal.supervisor_score ??
                                                        '-'}{' '}
                                                    /{' '}
                                                    {goal.moderated_score ??
                                                        '-'}
                                                </p>
                                            </div>
                                        ))}
                                        {review.evidence.map((evidence) => (
                                            <Button
                                                key={evidence.id}
                                                size="sm"
                                                variant="outline"
                                                asChild
                                            >
                                                <a
                                                    href={`/prestasi/${review.id}/bukti/${evidence.id}`}
                                                >
                                                    <FileText />
                                                    {evidence.original_name}
                                                </a>
                                            </Button>
                                        ))}
                                        {review.pip?.checkins.map((checkin) => (
                                            <div
                                                key={checkin.id}
                                                className="rounded-lg border border-amber-500/20 p-3 text-sm"
                                            >
                                                <strong>
                                                    PIP {checkin.checkin_date} ·{' '}
                                                    {checkin.progress_status}
                                                </strong>
                                                <p>{checkin.progress_notes}</p>
                                            </div>
                                        ))}
                                    </div>
                                </details>
                                <div className="mt-4 flex flex-wrap justify-end gap-2">
                                    {review.status ===
                                        'supervisor_assessment' &&
                                        permissions.can_supervise && (
                                            <SupervisorDialog review={review} />
                                        )}
                                    {review.status === 'hr_moderation' &&
                                        permissions.can_moderate && (
                                            <>
                                                <ModerationDialog
                                                    review={review}
                                                    canFinalize={
                                                        permissions.can_finalize
                                                    }
                                                />
                                                {permissions.can_finalize &&
                                                    review.moderated_score !==
                                                        null && (
                                                        <Button
                                                            size="sm"
                                                            variant="secondary"
                                                            onClick={() => {
                                                                if (
                                                                    window.confirm(
                                                                        'Muktamadkan penilaian ini?',
                                                                    )
                                                                ) {
                                                                    router.patch(
                                                                        `/prestasi/${review.id}/muktamad`,
                                                                        {},
                                                                        {
                                                                            preserveScroll: true,
                                                                        },
                                                                    );
                                                                }
                                                            }}
                                                        >
                                                            <CheckCircle2 />
                                                            Muktamad
                                                        </Button>
                                                    )}
                                            </>
                                        )}
                                    {review.status === 'finalized' && (
                                        <>
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                asChild
                                            >
                                                <a
                                                    href={`/prestasi/${review.id}/laporan.pdf`}
                                                >
                                                    <Download />
                                                    PDF
                                                </a>
                                            </Button>
                                            {permissions.can_manage && (
                                                <PipDialog review={review} />
                                            )}
                                            {permissions.can_manage &&
                                                review.pip && (
                                                    <CheckinDialog
                                                        review={review}
                                                    />
                                                )}
                                        </>
                                    )}
                                </div>
                            </div>
                        ))}
                        {reviews.data.length === 0 && (
                            <div className="py-12 text-center text-muted-foreground">
                                Tiada penilaian sepadan dengan tapisan.
                            </div>
                        )}
                        <div className="flex flex-wrap justify-center gap-1">
                            {reviews.links.map((link, index) =>
                                link.url ? (
                                    <Link
                                        key={index}
                                        href={link.url}
                                        preserveScroll
                                        className={`rounded-md border px-3 py-1.5 text-sm ${
                                            link.active
                                                ? 'bg-primary text-primary-foreground'
                                                : 'hover:bg-muted'
                                        }`}
                                    >
                                        {paginationLabel(link.label)}
                                    </Link>
                                ) : (
                                    <span
                                        key={index}
                                        className="rounded-md border px-3 py-1.5 text-sm opacity-40"
                                    >
                                        {paginationLabel(link.label)}
                                    </span>
                                ),
                            )}
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
