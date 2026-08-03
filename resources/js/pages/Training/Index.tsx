import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    Award,
    BarChart3,
    Check,
    ClipboardCheck,
    Download,
    GraduationCap,
    Search,
    Target,
    UserPlus,
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

type Training = {
    id: number;
    request_number: string;
    employee: { id: number; employee_number: string; name: string } | null;
    employee_user_id: number;
    department_id: number | null;
    department: string | null;
    position_name: string | null;
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
    supervisor: string | null;
    supervisor_notes: string | null;
    hr_reviewer: string | null;
    hr_notes: string | null;
    attendance_status: string;
    attended_hours: number | null;
    assessment_score: number | null;
    passed: boolean | null;
    employee_rating: number | null;
    created_at: string;
    attachments: {
        id: number;
        type: string;
        name: string;
        valid_until: string | null;
    }[];
};
type Employee = {
    id: number;
    user_id: number;
    employee_number: string;
    name: string;
    email: string;
    department_id: number | null;
    department_name: string | null;
    position_name: string | null;
};
type Props = {
    requests: {
        data: Training[];
        links: { url: string | null; label: string; active: boolean }[];
        from: number | null;
        to: number | null;
        total: number;
    };
    filters: {
        search: string;
        status: string;
        department_id: string;
        year: string;
    };
    statistics: {
        total: number;
        pending_supervisor: number;
        pending_hr: number;
        approved: number;
        completed: number;
        total_spent: number;
        total_hours: number;
    };
    budgets: {
        id: number;
        department_id: number | null;
        department: string;
        budget_code: string | null;
        allocated: number;
        used: number;
        available: number;
    }[];
    departments: { id: number; name: string }[];
    sessions: {
        id: number;
        title: string;
        session_code: string;
        starts_at: string;
        cost: number;
    }[];
    employees: Employee[];
    competencies: {
        id: number;
        code: string;
        name: string;
        category: string;
        maximum_level: number;
    }[];
    competencyMatrix: (Employee & {
        gap_count: number;
        skills: {
            competency_id: number;
            code: string;
            name: string;
            current_level: number;
            required_level: number;
            gap: number;
            is_mandatory: boolean;
        }[];
    })[];
    developmentPlans: {
        id: number;
        employee_user_id: number;
        employee: string;
        competency: string | null;
        source: string;
        title: string;
        action_plan: string;
        target_level: number | null;
        due_date: string;
        status: string;
    }[];
    permissions: {
        can_manage: boolean;
        can_approve: boolean;
        can_supervise: boolean;
        can_assess: boolean;
    };
};

const statusLabel: Record<string, string> = {
    pending: 'Menunggu',
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
    kpi: 'KPI',
    pip: 'PIP',
    onboarding: 'Onboarding',
    mandatory: 'Wajib',
    competency_gap: 'Jurang Kompetensi',
    career: 'Kerjaya',
};

function ReviewDialog({
    training,
    stage,
}: {
    training: Training;
    stage: 'supervisor' | 'hr';
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        action: stage === 'supervisor' ? 'support' : 'approve',
        approved_cost: String(training.estimated_cost),
        notes: '',
    });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const url =
            stage === 'supervisor'
                ? `/latihan-kompetensi/${training.id}/semakan-penyelia`
                : `/latihan-kompetensi/${training.id}/semakan`;
        form.patch(url, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm">
                    <ClipboardCheck />
                    Semak {stage === 'supervisor' ? 'Penyelia' : 'HR'}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{training.course_title}</DialogTitle>
                    <DialogDescription>
                        {training.employee?.name} · {training.request_number}
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-2">
                        <Label>Keputusan</Label>
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
                                {stage === 'supervisor' ? (
                                    <>
                                        <SelectItem value="support">
                                            Sokong
                                        </SelectItem>
                                        <SelectItem value="reject">
                                            Tolak
                                        </SelectItem>
                                    </>
                                ) : (
                                    <>
                                        <SelectItem value="approve">
                                            Luluskan
                                        </SelectItem>
                                        <SelectItem value="reject">
                                            Tolak
                                        </SelectItem>
                                    </>
                                )}
                            </SelectContent>
                        </Select>
                    </div>
                    {stage === 'hr' && form.data.action === 'approve' && (
                        <div className="space-y-2">
                            <Label>Kos Diluluskan (RM)</Label>
                            <Input
                                type="number"
                                min="0"
                                step="0.01"
                                value={form.data.approved_cost}
                                onChange={(event) =>
                                    form.setData(
                                        'approved_cost',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError message={form.errors.approved_cost} />
                        </div>
                    )}
                    <div className="space-y-2">
                        <Label>Catatan</Label>
                        <textarea
                            className="min-h-28 w-full rounded-md border bg-background px-3 py-2 text-sm"
                            value={form.data.notes}
                            onChange={(event) =>
                                form.setData('notes', event.target.value)
                            }
                        />
                        <InputError message={form.errors.notes} />
                    </div>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Tutup
                            </Button>
                        </DialogClose>
                        <Button disabled={form.processing}>
                            Simpan Keputusan
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function CompletionDialog({ training }: { training: Training }) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        attendance_status: 'attended',
        attended_hours: '0',
        assessment_score: '',
        notes: '',
    });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.put(`/latihan-kompetensi/${training.id}/penyelesaian`, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    <Check />
                    Rekod Penyelesaian
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Kehadiran & Keputusan</DialogTitle>
                    <DialogDescription>
                        {training.employee?.name} · {training.course_title}
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-2">
                        <Label>Status</Label>
                        <Select
                            value={form.data.attendance_status}
                            onValueChange={(value) =>
                                form.setData('attendance_status', value)
                            }
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="attended">Hadir</SelectItem>
                                <SelectItem value="passed">Lulus</SelectItem>
                                <SelectItem value="failed">
                                    Tidak Lulus
                                </SelectItem>
                                <SelectItem value="no_show">
                                    Tidak Hadir
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-2">
                            <Label>Jam Dihadiri</Label>
                            <Input
                                type="number"
                                min="0"
                                step="0.25"
                                value={form.data.attended_hours}
                                onChange={(event) =>
                                    form.setData(
                                        'attended_hours',
                                        event.target.value,
                                    )
                                }
                                disabled={
                                    form.data.attendance_status === 'no_show'
                                }
                            />
                        </div>
                        <div className="space-y-2">
                            <Label>Skor (pilihan)</Label>
                            <Input
                                type="number"
                                min="0"
                                max="100"
                                step="0.01"
                                value={form.data.assessment_score}
                                onChange={(event) =>
                                    form.setData(
                                        'assessment_score',
                                        event.target.value,
                                    )
                                }
                            />
                        </div>
                    </div>
                    <div className="space-y-2">
                        <Label>Catatan</Label>
                        <textarea
                            className="min-h-24 w-full rounded-md border bg-background px-3 py-2 text-sm"
                            value={form.data.notes}
                            onChange={(event) =>
                                form.setData('notes', event.target.value)
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
                            Muktamadkan Rekod
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function NominationDialog({
    employees,
    sessions,
    plans,
}: {
    employees: Employee[];
    sessions: Props['sessions'];
    plans: Props['developmentPlans'];
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        employee_user_id: '',
        training_session_id: '',
        development_plan_id: '',
        development_source: 'competency_gap',
        justification: '',
    });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post('/latihan-kompetensi/pencalonan', {
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
                    <UserPlus />
                    Calonkan Pekerja
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Pencalonan Latihan</DialogTitle>
                    <DialogDescription>
                        HR boleh mendaftarkan pekerja terus tertakluk kepada
                        kapasiti dan bajet.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-2">
                        <Label>Pekerja</Label>
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
                    </div>
                    <div className="space-y-2">
                        <Label>Sesi</Label>
                        <Select
                            value={form.data.training_session_id}
                            onValueChange={(value) =>
                                form.setData('training_session_id', value)
                            }
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Pilih sesi" />
                            </SelectTrigger>
                            <SelectContent>
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
                    </div>
                    <div className="space-y-2">
                        <Label>Sumber</Label>
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
                                {[
                                    'competency_gap',
                                    'kpi',
                                    'pip',
                                    'onboarding',
                                    'mandatory',
                                ].map((value) => (
                                    <SelectItem key={value} value={value}>
                                        {sourceLabel[value]}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="space-y-2">
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
                                            String(plan.employee_user_id) ===
                                                form.data.employee_user_id &&
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
                    <div className="space-y-2">
                        <Label>Justifikasi</Label>
                        <textarea
                            className="min-h-24 w-full rounded-md border bg-background px-3 py-2 text-sm"
                            value={form.data.justification}
                            onChange={(event) =>
                                form.setData(
                                    'justification',
                                    event.target.value,
                                )
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
                            Daftar Pekerja
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function CompetencyDialog({
    employees,
    competencies,
}: {
    employees: Employee[];
    competencies: Props['competencies'];
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        employee_user_id: '',
        competency_id: '',
        current_level: '1',
        assessment_source: 'manager',
        evidence_notes: '',
    });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post('/latihan-kompetensi/penilaian-kompetensi', {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline">
                    <Award />
                    Nilai Kompetensi
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Penilaian Kompetensi Pekerja</DialogTitle>
                    <DialogDescription>
                        Rekod tahap semasa berserta sumber dan bukti penilaian.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-2">
                        <Label>Pekerja</Label>
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
                    </div>
                    <div className="space-y-2">
                        <Label>Kompetensi</Label>
                        <Select
                            value={form.data.competency_id}
                            onValueChange={(value) =>
                                form.setData('competency_id', value)
                            }
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Pilih kompetensi" />
                            </SelectTrigger>
                            <SelectContent>
                                {competencies.map((competency) => (
                                    <SelectItem
                                        key={competency.id}
                                        value={String(competency.id)}
                                    >
                                        {competency.code} · {competency.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-2">
                            <Label>Tahap Semasa</Label>
                            <Input
                                type="number"
                                min="0"
                                max="10"
                                value={form.data.current_level}
                                onChange={(event) =>
                                    form.setData(
                                        'current_level',
                                        event.target.value,
                                    )
                                }
                            />
                        </div>
                        <div className="space-y-2">
                            <Label>Sumber</Label>
                            <Select
                                value={form.data.assessment_source}
                                onValueChange={(value) =>
                                    form.setData('assessment_source', value)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="manager">
                                        Penilaian Penyelia
                                    </SelectItem>
                                    <SelectItem value="assessment">
                                        Ujian Kompetensi
                                    </SelectItem>
                                    <SelectItem value="certificate">
                                        Sijil
                                    </SelectItem>
                                    <SelectItem value="training">
                                        Latihan
                                    </SelectItem>
                                    <SelectItem value="self_verified">
                                        Kendiri Disahkan
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    <div className="space-y-2">
                        <Label>Bukti / Catatan</Label>
                        <textarea
                            className="min-h-24 w-full rounded-md border bg-background px-3 py-2 text-sm"
                            value={form.data.evidence_notes}
                            onChange={(event) =>
                                form.setData(
                                    'evidence_notes',
                                    event.target.value,
                                )
                            }
                        />
                    </div>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Tutup
                            </Button>
                        </DialogClose>
                        <Button disabled={form.processing}>Simpan Tahap</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function DevelopmentPlanDialog({
    employees,
    competencies,
}: {
    employees: Employee[];
    competencies: Props['competencies'];
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        employee_user_id: '',
        competency_id: '',
        source: 'competency_gap',
        title: '',
        action_plan: '',
        target_level: '',
        due_date: '',
    });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post('/latihan-kompetensi/pelan-pembangunan', {
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
                <Button variant="outline">
                    <Target />
                    Pelan Pembangunan
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Pelan Pembangunan Individu</DialogTitle>
                    <DialogDescription>
                        Pautkan jurang kompetensi, KPI, PIP atau keperluan
                        kerjaya kepada tindakan yang boleh dijejak.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-2">
                        <Label>Pekerja</Label>
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
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-2">
                            <Label>Kompetensi</Label>
                            <Select
                                value={form.data.competency_id || 'none'}
                                onValueChange={(value) =>
                                    form.setData(
                                        'competency_id',
                                        value === 'none' ? '' : value,
                                    )
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">Umum</SelectItem>
                                    {competencies.map((competency) => (
                                        <SelectItem
                                            key={competency.id}
                                            value={String(competency.id)}
                                        >
                                            {competency.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-2">
                            <Label>Sumber</Label>
                            <Select
                                value={form.data.source}
                                onValueChange={(value) =>
                                    form.setData('source', value)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {[
                                        'competency_gap',
                                        'kpi',
                                        'pip',
                                        'onboarding',
                                        'career',
                                    ].map((value) => (
                                        <SelectItem key={value} value={value}>
                                            {sourceLabel[value]}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    <div className="space-y-2">
                        <Label>Tajuk</Label>
                        <Input
                            value={form.data.title}
                            onChange={(event) =>
                                form.setData('title', event.target.value)
                            }
                        />
                    </div>
                    <div className="space-y-2">
                        <Label>Pelan Tindakan</Label>
                        <textarea
                            className="min-h-24 w-full rounded-md border bg-background px-3 py-2 text-sm"
                            value={form.data.action_plan}
                            onChange={(event) =>
                                form.setData('action_plan', event.target.value)
                            }
                        />
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-2">
                            <Label>Tahap Sasaran</Label>
                            <Input
                                type="number"
                                min="1"
                                max="10"
                                value={form.data.target_level}
                                onChange={(event) =>
                                    form.setData(
                                        'target_level',
                                        event.target.value,
                                    )
                                }
                            />
                        </div>
                        <div className="space-y-2">
                            <Label>Tarikh Sasaran</Label>
                            <Input
                                type="date"
                                value={form.data.due_date}
                                onChange={(event) =>
                                    form.setData('due_date', event.target.value)
                                }
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Tutup
                            </Button>
                        </DialogClose>
                        <Button disabled={form.processing}>Simpan Pelan</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function TrainingIndex({
    requests,
    filters,
    statistics,
    budgets,
    departments,
    sessions,
    employees,
    competencies,
    competencyMatrix,
    developmentPlans,
    permissions,
}: Props) {
    const [search, setSearch] = useState(filters.search);
    const applyFilters = (next: Partial<Props['filters']>) =>
        router.get(
            '/latihan-kompetensi',
            { ...filters, search, ...next },
            { preserveState: true, replace: true },
        );

    return (
        <>
            <Head title="Latihan & Kompetensi" />
            <div className="flex h-full flex-1 flex-col gap-5 overflow-x-auto rounded-xl p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Latihan & Kompetensi
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Kelulusan latihan, bajet, kehadiran, sijil, matriks
                            kompetensi dan pelan pembangunan.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {permissions.can_manage && (
                            <NominationDialog
                                employees={employees}
                                sessions={sessions}
                                plans={developmentPlans}
                            />
                        )}
                        {permissions.can_assess && (
                            <CompetencyDialog
                                employees={employees}
                                competencies={competencies}
                            />
                        )}
                        {permissions.can_manage && (
                            <DevelopmentPlanDialog
                                employees={employees}
                                competencies={competencies}
                            />
                        )}
                        <Button variant="outline" asChild>
                            <Link href="/latihan-kompetensi/laporan.csv">
                                <Download />
                                CSV
                            </Link>
                        </Button>
                    </div>
                </div>
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <Card>
                        <CardContent className="flex items-center gap-3 p-5">
                            <ClipboardCheck className="size-8 text-amber-600" />
                            <div>
                                <p className="text-xs text-muted-foreground">
                                    Menunggu Tindakan
                                </p>
                                <p className="text-2xl font-semibold">
                                    {statistics.pending_supervisor +
                                        statistics.pending_hr}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {statistics.pending_supervisor} penyelia ·{' '}
                                    {statistics.pending_hr} HR
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="flex items-center gap-3 p-5">
                            <GraduationCap className="size-8 text-indigo-600" />
                            <div>
                                <p className="text-xs text-muted-foreground">
                                    Selesai
                                </p>
                                <p className="text-2xl font-semibold">
                                    {statistics.completed}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="flex items-center gap-3 p-5">
                            <WalletCards className="size-8 text-emerald-600" />
                            <div>
                                <p className="text-xs text-muted-foreground">
                                    Belanja Diluluskan
                                </p>
                                <p className="text-xl font-semibold">
                                    RM {statistics.total_spent.toFixed(2)}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="flex items-center gap-3 p-5">
                            <BarChart3 className="size-8 text-sky-600" />
                            <div>
                                <p className="text-xs text-muted-foreground">
                                    Jam Latihan
                                </p>
                                <p className="text-2xl font-semibold">
                                    {statistics.total_hours.toFixed(1)}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>
                <Card>
                    <CardContent className="grid gap-3 p-4 md:grid-cols-4">
                        <div className="relative md:col-span-2">
                            <Search className="absolute top-2.5 left-3 size-4 text-muted-foreground" />
                            <Input
                                className="pl-9"
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                onKeyDown={(event) =>
                                    event.key === 'Enter' && applyFilters({})
                                }
                                placeholder="Cari nama, nombor atau kursus..."
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
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    Semua status
                                </SelectItem>
                                {[
                                    'pending',
                                    'approved',
                                    'completed',
                                    'rejected',
                                    'cancelled',
                                ].map((status) => (
                                    <SelectItem key={status} value={status}>
                                        {statusLabel[status]}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Select
                            value={filters.department_id || 'all'}
                            onValueChange={(value) =>
                                applyFilters({
                                    department_id: value === 'all' ? '' : value,
                                })
                            }
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    Semua jabatan
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
                    </CardContent>
                </Card>
                {budgets.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>
                                Penggunaan Bajet {filters.year}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                            {budgets.map((budget) => (
                                <div
                                    key={budget.id}
                                    className="rounded-lg border p-3"
                                >
                                    <div className="flex items-center justify-between">
                                        <p className="font-medium">
                                            {budget.department}
                                        </p>
                                        <Badge variant="outline">
                                            {budget.budget_code ?? 'Umum'}
                                        </Badge>
                                    </div>
                                    <div className="mt-2 h-2 overflow-hidden rounded-full bg-muted">
                                        <div
                                            className="h-full bg-indigo-500"
                                            style={{
                                                width: `${budget.allocated > 0 ? Math.min(100, (budget.used / budget.allocated) * 100) : 0}%`,
                                            }}
                                        />
                                    </div>
                                    <div className="mt-2 flex justify-between text-xs">
                                        <span>
                                            Digunakan RM{' '}
                                            {budget.used.toFixed(2)}
                                        </span>
                                        <span>
                                            Baki RM{' '}
                                            {budget.available.toFixed(2)}
                                        </span>
                                    </div>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                )}
                <Card>
                    <CardHeader>
                        <CardTitle>Permohonan & Rekod Latihan</CardTitle>
                        <CardDescription>
                            {requests.from ?? 0}–{requests.to ?? 0} daripada{' '}
                            {requests.total} rekod.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {requests.data.length === 0 ? (
                            <p className="py-12 text-center text-sm text-muted-foreground">
                                Tiada rekod sepadan.
                            </p>
                        ) : (
                            requests.data.map((training) => (
                                <div
                                    key={training.id}
                                    className="rounded-lg border p-4"
                                >
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <p className="font-semibold">
                                                {training.course_title}
                                            </p>
                                            <p className="text-sm">
                                                {
                                                    training.employee
                                                        ?.employee_number
                                                }{' '}
                                                · {training.employee?.name}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {training.request_number} ·{' '}
                                                {training.department ??
                                                    'Tanpa jabatan'}{' '}
                                                ·{' '}
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
                                    <div className="mt-3 grid gap-2 text-sm md:grid-cols-4">
                                        <span>
                                            Anggaran: RM{' '}
                                            {training.estimated_cost.toFixed(2)}
                                        </span>
                                        <span>
                                            Diluluskan:{' '}
                                            {training.approved_cost === null
                                                ? '—'
                                                : `RM ${training.approved_cost.toFixed(2)}`}
                                        </span>
                                        <span>
                                            Sesi:{' '}
                                            {training.session
                                                ? new Date(
                                                      training.session
                                                          .starts_at,
                                                  ).toLocaleDateString('ms-MY')
                                                : 'Belum dijadualkan'}
                                        </span>
                                        <span>
                                            Keputusan:{' '}
                                            {statusLabel[
                                                training.attendance_status
                                            ] ?? training.attendance_status}
                                        </span>
                                    </div>
                                    <p className="mt-3 text-sm text-muted-foreground">
                                        {training.justification}
                                    </p>
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
                                        {training.status === 'pending' &&
                                            training.approval_stage ===
                                                'supervisor' &&
                                            permissions.can_supervise && (
                                                <ReviewDialog
                                                    training={training}
                                                    stage="supervisor"
                                                />
                                            )}
                                        {training.status === 'pending' &&
                                            training.approval_stage === 'hr' &&
                                            permissions.can_approve && (
                                                <ReviewDialog
                                                    training={training}
                                                    stage="hr"
                                                />
                                            )}
                                        {training.status === 'approved' &&
                                            permissions.can_manage && (
                                                <CompletionDialog
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
                                                        href={`/latihan-kompetensi/${training.id}/lampiran/${attachment.id}`}
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
                                        {training.employee_rating && (
                                            <Badge variant="secondary">
                                                Rating{' '}
                                                {training.employee_rating}/5
                                            </Badge>
                                        )}
                                    </div>
                                </div>
                            ))
                        )}
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>Matriks Jurang Kompetensi</CardTitle>
                        <CardDescription>
                            Pekerja disusun mengikut jumlah kompetensi yang
                            belum mencapai tahap jawatan.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-3 lg:grid-cols-2">
                        {competencyMatrix.map((employee) => (
                            <div
                                key={employee.user_id}
                                className="rounded-lg border p-4"
                            >
                                <div className="flex items-start justify-between">
                                    <div>
                                        <p className="font-medium">
                                            {employee.name}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {employee.employee_number} ·{' '}
                                            {employee.position_name ??
                                                'Tanpa jawatan'}
                                        </p>
                                    </div>
                                    <Badge
                                        variant={
                                            employee.gap_count > 0
                                                ? 'destructive'
                                                : 'outline'
                                        }
                                    >
                                        {employee.gap_count} jurang
                                    </Badge>
                                </div>
                                <div className="mt-3 space-y-2">
                                    {employee.skills.length === 0 ? (
                                        <p className="text-sm text-muted-foreground">
                                            Tiada keperluan ditetapkan.
                                        </p>
                                    ) : (
                                        employee.skills.map((skill) => (
                                            <div
                                                key={skill.competency_id}
                                                className="flex items-center justify-between text-sm"
                                            >
                                                <span>
                                                    {skill.code} · {skill.name}
                                                </span>
                                                <span
                                                    className={
                                                        skill.gap > 0
                                                            ? 'font-semibold text-red-600'
                                                            : 'text-emerald-600'
                                                    }
                                                >
                                                    {skill.current_level}/
                                                    {skill.required_level}
                                                </span>
                                            </div>
                                        ))
                                    )}
                                </div>
                            </div>
                        ))}
                    </CardContent>
                </Card>
                {requests.links.length > 3 && (
                    <div className="flex flex-wrap justify-center gap-1">
                        {requests.links.map((link, index) => (
                            <Button
                                key={index}
                                size="sm"
                                variant={link.active ? 'default' : 'outline'}
                                disabled={!link.url}
                                onClick={() => link.url && router.get(link.url)}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}
