import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    CalendarClock,
    CheckCircle2,
    ClipboardCheck,
    Download,
    FilePlus2,
    FileText,
    ListChecks,
    Pencil,
    Plus,
    Send,
    Star,
    Trash2,
    UserRoundCheck,
    XCircle,
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
import type { Auth } from '@/types';

type CandidateStage =
    | 'applied'
    | 'screening'
    | 'shortlisted'
    | 'interview'
    | 'offer'
    | 'hired'
    | 'rejected'
    | 'withdrawn';
type Candidate = {
    id: number;
    candidate_number: string;
    name: string;
    email: string;
    phone: string;
    nric: string | null;
    current_company: string | null;
    current_position: string | null;
    expected_salary: number | null;
    notice_period_days: number | null;
    source: string;
    stage: CandidateStage;
    rating: number | null;
    screening_notes: string | null;
    rejection_reason: string | null;
    withdrawal_reason: string | null;
    applied_at: string;
    owner_user_id: number | null;
    owner: string | null;
    requisition: {
        id: number;
        code: string;
        title: string;
        department_id: number | null;
        position_name: string | null;
        employment_type: string;
        hiring_manager_user_id: number | null;
        hiring_manager: string | null;
    };
    documents: {
        id: number;
        document_type: string;
        original_name: string;
        mime_type: string;
        size: number;
        created_at: string;
    }[];
    interviews: {
        id: number;
        round: number;
        interview_type: string;
        scheduled_at: string;
        duration_minutes: number;
        location_or_link: string | null;
        panel_user_ids: number[];
        panel: { id: number; name: string }[];
        status: string;
        overall_score: number | null;
        overall_recommendation: string | null;
        notes: string | null;
        scorecards: {
            id: number;
            panel_user_id: number;
            panel_name: string;
            technical_score: number;
            communication_score: number;
            culture_score: number;
            overall_score: number;
            recommendation: string;
            strengths: string;
            concerns: string | null;
            comments: string | null;
        }[];
    }[];
    offers: {
        id: number;
        offer_number: string;
        position_name: string;
        department_id: number | null;
        employment_type: string;
        salary: number;
        start_date: string;
        probation_months: number;
        expiry_date: string;
        terms: string | null;
        status: string;
        approval_notes: string | null;
        response_notes: string | null;
    }[];
    onboarding: {
        id: number;
        template: string | null;
        legacy_employee_id: number | null;
        employee_record_id: number | null;
        employee_user_id: number | null;
        employee_user: string | null;
        employee_record: {
            directory_id: number;
            employee_number: string;
            official_email: string;
            status: string;
            activation_date: string;
            office: string | null;
        } | null;
        manager_user_id: number | null;
        buddy_user_id: number | null;
        start_date: string;
        status: string;
        notes: string | null;
        progress: number;
        tasks: {
            id: number;
            title: string;
            category: string;
            status: string;
            due_date: string;
        }[];
    } | null;
};
type Props = {
    candidate: Candidate;
    users: { id: number; name: string; email: string; roles: string[] }[];
    onboardingTemplates: {
        id: number;
        name: string;
        department_id: number | null;
        position_name: string | null;
        tasks_count: number;
    }[];
    permissions: {
        can_manage: boolean;
        can_approve: boolean;
        can_interview: boolean;
        can_manage_onboarding: boolean;
    };
};

const stageLabel: Record<CandidateStage, string> = {
    applied: 'Permohonan',
    screening: 'Saringan',
    shortlisted: 'Disenarai Pendek',
    interview: 'Temu Duga',
    offer: 'Tawaran',
    hired: 'Diambil Bekerja',
    rejected: 'Tidak Berjaya',
    withdrawn: 'Menarik Diri',
};
const statusLabel: Record<string, string> = {
    scheduled: 'Dijadualkan',
    completed: 'Selesai',
    cancelled: 'Dibatalkan',
    no_show: 'Tidak Hadir',
    draft: 'Draf',
    pending_approval: 'Menunggu Kelulusan',
    approved: 'Diluluskan',
    sent: 'Dihantar',
    accepted: 'Diterima',
    declined: 'Ditolak Calon',
    expired: 'Tamat Tempoh',
    withdrawn: 'Ditarik Balik',
    pending: 'Belum Bermula',
    active: 'Aktif',
};
const recommendationLabel: Record<string, string> = {
    strong_yes: 'Sangat Disyorkan',
    yes: 'Disyorkan',
    no: 'Tidak Disyorkan',
    strong_no: 'Sangat Tidak Disyorkan',
};
const sourceLabel: Record<string, string> = {
    direct: 'Permohonan Terus',
    referral: 'Rujukan Pekerja',
    job_portal: 'Portal Kerjaya',
    social_media: 'Media Sosial',
    agency: 'Agensi',
    career_fair: 'Karnival Kerjaya',
    other: 'Lain-lain',
};

function CandidateEditDialog({
    candidate,
    users,
}: {
    candidate: Candidate;
    users: Props['users'];
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        name: candidate.name,
        email: candidate.email,
        phone: candidate.phone,
        nric: candidate.nric ?? '',
        current_company: candidate.current_company ?? '',
        current_position: candidate.current_position ?? '',
        expected_salary: candidate.expected_salary
            ? String(candidate.expected_salary)
            : '',
        notice_period_days: candidate.notice_period_days
            ? String(candidate.notice_period_days)
            : '',
        source: candidate.source,
        owner_user_id: candidate.owner_user_id
            ? String(candidate.owner_user_id)
            : '',
        screening_notes: candidate.screening_notes ?? '',
        rating: candidate.rating ? String(candidate.rating) : '',
    });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.put(`/pengambilan/calon/${candidate.id}`, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline">
                    <Pencil />
                    Edit Profil
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Edit Profil & Saringan</DialogTitle>
                    <DialogDescription>
                        Kemas kini maklumat calon, pemilik dan rating saringan.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        {[
                            ['name', 'Nama Penuh', 'text'],
                            ['email', 'E-mel', 'email'],
                            ['phone', 'Telefon', 'text'],
                            ['nric', 'No. Pengenalan', 'text'],
                            ['current_company', 'Majikan Semasa', 'text'],
                            ['current_position', 'Jawatan Semasa', 'text'],
                            ['expected_salary', 'Jangkaan Gaji', 'number'],
                            [
                                'notice_period_days',
                                'Tempoh Notis (hari)',
                                'number',
                            ],
                            ['rating', 'Rating (1–5)', 'number'],
                        ].map(([field, label, type]) => (
                            <div className="space-y-2" key={field}>
                                <Label>{label}</Label>
                                <Input
                                    type={type}
                                    min={field === 'rating' ? '1' : undefined}
                                    max={field === 'rating' ? '5' : undefined}
                                    step={
                                        field === 'rating' ? '0.1' : undefined
                                    }
                                    value={
                                        form.data[
                                            field as keyof typeof form.data
                                        ]
                                    }
                                    onChange={(event) =>
                                        form.setData(
                                            field as keyof typeof form.data,
                                            event.target.value,
                                        )
                                    }
                                />
                            </div>
                        ))}
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
                            <Label>Pemilik / Recruiter</Label>
                            <Select
                                value={form.data.owner_user_id || 'none'}
                                onValueChange={(value) =>
                                    form.setData(
                                        'owner_user_id',
                                        value === 'none' ? '' : value,
                                    )
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">
                                        Belum ditetapkan
                                    </SelectItem>
                                    {users.map((user) => (
                                        <SelectItem
                                            key={user.id}
                                            value={String(user.id)}
                                        >
                                            {user.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    <div className="space-y-2">
                        <Label>Catatan Saringan</Label>
                        <textarea
                            value={form.data.screening_notes}
                            onChange={(event) =>
                                form.setData(
                                    'screening_notes',
                                    event.target.value,
                                )
                            }
                            className="min-h-28 w-full rounded-md border bg-background px-3 py-2 text-sm"
                        />
                    </div>
                    <InputError
                        message={(form.errors as Record<string, string>).email}
                    />
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Tutup
                            </Button>
                        </DialogClose>
                        <Button disabled={form.processing}>
                            <CheckCircle2 />
                            Simpan
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function StageDialog({ candidate }: { candidate: Candidate }) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        stage: candidate.stage === 'hired' ? 'offer' : candidate.stage,
        reason: '',
    });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.patch(`/pengambilan/calon/${candidate.id}/peringkat`, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline">
                    <UserRoundCheck />
                    Tukar Peringkat
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Tukar Peringkat Calon</DialogTitle>
                    <DialogDescription>
                        Status Diambil Bekerja hanya ditetapkan melalui tawaran
                        yang diterima.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-2">
                        <Label>Peringkat</Label>
                        <Select
                            value={form.data.stage}
                            onValueChange={(value) =>
                                form.setData(
                                    'stage',
                                    value as typeof form.data.stage,
                                )
                            }
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {(
                                    Object.entries(stageLabel) as [
                                        CandidateStage,
                                        string,
                                    ][]
                                )
                                    .filter(([stage]) => stage !== 'hired')
                                    .map(([stage, label]) => (
                                        <SelectItem key={stage} value={stage}>
                                            {label}
                                        </SelectItem>
                                    ))}
                            </SelectContent>
                        </Select>
                    </div>
                    {['rejected', 'withdrawn'].includes(form.data.stage) && (
                        <div className="space-y-2">
                            <Label>Sebab</Label>
                            <textarea
                                value={form.data.reason}
                                onChange={(event) =>
                                    form.setData('reason', event.target.value)
                                }
                                className="min-h-24 w-full rounded-md border bg-background px-3 py-2 text-sm"
                            />
                        </div>
                    )}
                    <InputError message={form.errors.reason} />
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Tutup
                            </Button>
                        </DialogClose>
                        <Button disabled={form.processing}>
                            <CheckCircle2 />
                            Kemas Kini
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function DocumentDialog({ candidateId }: { candidateId: number }) {
    const [open, setOpen] = useState(false);
    const form = useForm<{ document_type: string; file: File | null }>({
        document_type: 'resume',
        file: null,
    });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(`/pengambilan/calon/${candidateId}/dokumen`, {
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
                <Button size="sm">
                    <FilePlus2 />
                    Muat Naik
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Dokumen Calon</DialogTitle>
                    <DialogDescription>
                        Fail disimpan secara persendirian dan hanya boleh dimuat
                        turun oleh pengguna berizin.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-2">
                        <Label>Jenis Dokumen</Label>
                        <Select
                            value={form.data.document_type}
                            onValueChange={(value) =>
                                form.setData('document_type', value)
                            }
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {[
                                    ['resume', 'Resume / CV'],
                                    ['cover_letter', 'Surat Iringan'],
                                    ['certificate', 'Sijil'],
                                    ['identity', 'Pengenalan Diri'],
                                    ['reference', 'Rujukan'],
                                    ['offer_letter', 'Surat Tawaran'],
                                    ['other', 'Lain-lain'],
                                ].map(([value, label]) => (
                                    <SelectItem key={value} value={value}>
                                        {label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="space-y-2">
                        <Label>Fail</Label>
                        <Input
                            type="file"
                            accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                            onChange={(event) =>
                                form.setData(
                                    'file',
                                    event.target.files?.[0] ?? null,
                                )
                            }
                        />
                        <InputError message={form.errors.file} />
                    </div>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Tutup
                            </Button>
                        </DialogClose>
                        <Button disabled={form.processing}>
                            <FilePlus2 />
                            Muat Naik
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function InterviewDialog({
    candidateId,
    users,
    nextRound,
}: {
    candidateId: number;
    users: Props['users'];
    nextRound: number;
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        round: String(nextRound),
        interview_type: 'physical',
        scheduled_at: '',
        duration_minutes: '60',
        location_or_link: '',
        panel_user_ids: [] as string[],
        notes: '',
    });
    const togglePanel = (id: string) =>
        form.setData(
            'panel_user_ids',
            form.data.panel_user_ids.includes(id)
                ? form.data.panel_user_ids.filter((item) => item !== id)
                : [...form.data.panel_user_ids, id],
        );
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(`/pengambilan/calon/${candidateId}/temu-duga`, {
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
                <Button size="sm">
                    <Plus />
                    Jadualkan
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Jadual Temu Duga</DialogTitle>
                    <DialogDescription>
                        Tetapkan tarikh, kaedah dan panel penilai.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-2">
                            <Label>Pusingan</Label>
                            <Input
                                type="number"
                                min="1"
                                value={form.data.round}
                                onChange={(event) =>
                                    form.setData('round', event.target.value)
                                }
                            />
                        </div>
                        <div className="space-y-2">
                            <Label>Jenis</Label>
                            <Select
                                value={form.data.interview_type}
                                onValueChange={(value) =>
                                    form.setData('interview_type', value)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {[
                                        ['phone', 'Telefon'],
                                        ['video', 'Video'],
                                        ['physical', 'Bersemuka'],
                                        ['technical', 'Teknikal'],
                                        ['final', 'Akhir'],
                                    ].map(([value, label]) => (
                                        <SelectItem key={value} value={value}>
                                            {label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-2">
                            <Label>Tarikh & Masa</Label>
                            <Input
                                type="datetime-local"
                                value={form.data.scheduled_at}
                                onChange={(event) =>
                                    form.setData(
                                        'scheduled_at',
                                        event.target.value,
                                    )
                                }
                            />
                        </div>
                        <div className="space-y-2">
                            <Label>Tempoh (minit)</Label>
                            <Input
                                type="number"
                                min="15"
                                value={form.data.duration_minutes}
                                onChange={(event) =>
                                    form.setData(
                                        'duration_minutes',
                                        event.target.value,
                                    )
                                }
                            />
                        </div>
                        <div className="space-y-2 sm:col-span-2">
                            <Label>Lokasi / Pautan</Label>
                            <Input
                                value={form.data.location_or_link}
                                onChange={(event) =>
                                    form.setData(
                                        'location_or_link',
                                        event.target.value,
                                    )
                                }
                            />
                        </div>
                    </div>
                    <div className="space-y-2">
                        <Label>Panel Penilai</Label>
                        <div className="grid gap-2 rounded-lg border p-3 sm:grid-cols-2">
                            {users.map((user) => (
                                <label
                                    key={user.id}
                                    className="flex cursor-pointer items-center gap-2 rounded-md p-2 hover:bg-muted"
                                >
                                    <input
                                        type="checkbox"
                                        checked={form.data.panel_user_ids.includes(
                                            String(user.id),
                                        )}
                                        onChange={() =>
                                            togglePanel(String(user.id))
                                        }
                                    />
                                    <span className="text-sm">{user.name}</span>
                                </label>
                            ))}
                        </div>
                        <InputError
                            message={
                                (form.errors as Record<string, string>)
                                    .panel_user_ids
                            }
                        />
                    </div>
                    <div className="space-y-2">
                        <Label>Catatan</Label>
                        <textarea
                            value={form.data.notes}
                            onChange={(event) =>
                                form.setData('notes', event.target.value)
                            }
                            className="min-h-20 w-full rounded-md border bg-background px-3 py-2 text-sm"
                        />
                    </div>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Tutup
                            </Button>
                        </DialogClose>
                        <Button disabled={form.processing}>
                            <CalendarClock />
                            Jadualkan
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function ScorecardDialog({
    candidateId,
    interview,
}: {
    candidateId: number;
    interview: Candidate['interviews'][number];
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        technical_score: '',
        communication_score: '',
        culture_score: '',
        recommendation: 'yes',
        strengths: '',
        concerns: '',
        comments: '',
    });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.put(
            `/pengambilan/calon/${candidateId}/temu-duga/${interview.id}/scorecard`,
            {
                preserveScroll: true,
                onSuccess: () => setOpen(false),
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    <ClipboardCheck />
                    Scorecard
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Scorecard Temu Duga</DialogTitle>
                    <DialogDescription>
                        Berikan skor 1 hingga 5 dan cadangan pemilihan.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-4 sm:grid-cols-3">
                        {[
                            ['technical_score', 'Teknikal'],
                            ['communication_score', 'Komunikasi'],
                            ['culture_score', 'Kesesuaian Budaya'],
                        ].map(([field, label]) => (
                            <div className="space-y-2" key={field}>
                                <Label>{label}</Label>
                                <Input
                                    type="number"
                                    min="1"
                                    max="5"
                                    step="0.1"
                                    value={
                                        form.data[
                                            field as
                                                | 'technical_score'
                                                | 'communication_score'
                                                | 'culture_score'
                                        ]
                                    }
                                    onChange={(event) =>
                                        form.setData(
                                            field as
                                                | 'technical_score'
                                                | 'communication_score'
                                                | 'culture_score',
                                            event.target.value,
                                        )
                                    }
                                />
                            </div>
                        ))}
                    </div>
                    <div className="space-y-2">
                        <Label>Cadangan</Label>
                        <Select
                            value={form.data.recommendation}
                            onValueChange={(value) =>
                                form.setData('recommendation', value)
                            }
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {Object.entries(recommendationLabel).map(
                                    ([value, label]) => (
                                        <SelectItem key={value} value={value}>
                                            {label}
                                        </SelectItem>
                                    ),
                                )}
                            </SelectContent>
                        </Select>
                    </div>
                    {[
                        ['strengths', 'Kekuatan'],
                        ['concerns', 'Kebimbangan / Risiko'],
                        ['comments', 'Ulasan Tambahan'],
                    ].map(([field, label]) => (
                        <div className="space-y-2" key={field}>
                            <Label>{label}</Label>
                            <textarea
                                value={
                                    form.data[
                                        field as
                                            | 'strengths'
                                            | 'concerns'
                                            | 'comments'
                                    ]
                                }
                                onChange={(event) =>
                                    form.setData(
                                        field as
                                            | 'strengths'
                                            | 'concerns'
                                            | 'comments',
                                        event.target.value,
                                    )
                                }
                                className="min-h-20 w-full rounded-md border bg-background px-3 py-2 text-sm"
                            />
                        </div>
                    ))}
                    <InputError message={form.errors.strengths} />
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Tutup
                            </Button>
                        </DialogClose>
                        <Button disabled={form.processing}>
                            <ClipboardCheck />
                            Hantar Scorecard
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function OfferDialog({ candidate }: { candidate: Candidate }) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        position_name:
            candidate.requisition.position_name ?? candidate.requisition.title,
        department_id: candidate.requisition.department_id
            ? String(candidate.requisition.department_id)
            : '',
        employment_type: candidate.requisition.employment_type,
        salary: candidate.expected_salary
            ? String(candidate.expected_salary)
            : '',
        start_date: '',
        probation_months: '3',
        expiry_date: '',
        terms: '',
    });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(`/pengambilan/calon/${candidate.id}/tawaran`, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm">
                    <Plus />
                    Sediakan Tawaran
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Tawaran Pekerjaan</DialogTitle>
                    <DialogDescription>
                        Tawaran bermula sebagai Draf dan perlu diluluskan
                        sebelum direkod sebagai dihantar.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-2 sm:col-span-2">
                            <Label>Jawatan</Label>
                            <Input
                                value={form.data.position_name}
                                onChange={(event) =>
                                    form.setData(
                                        'position_name',
                                        event.target.value,
                                    )
                                }
                            />
                        </div>
                        <div className="space-y-2">
                            <Label>Gaji Bulanan</Label>
                            <Input
                                type="number"
                                min="0"
                                step="0.01"
                                value={form.data.salary}
                                onChange={(event) =>
                                    form.setData('salary', event.target.value)
                                }
                            />
                        </div>
                        <div className="space-y-2">
                            <Label>Tempoh Percubaan (bulan)</Label>
                            <Input
                                type="number"
                                min="0"
                                value={form.data.probation_months}
                                onChange={(event) =>
                                    form.setData(
                                        'probation_months',
                                        event.target.value,
                                    )
                                }
                            />
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
                            <Label>Tarikh Tamat Tawaran</Label>
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
                        </div>
                    </div>
                    <div className="space-y-2">
                        <Label>Terma Tambahan</Label>
                        <textarea
                            value={form.data.terms}
                            onChange={(event) =>
                                form.setData('terms', event.target.value)
                            }
                            className="min-h-28 w-full rounded-md border bg-background px-3 py-2 text-sm"
                        />
                    </div>
                    <InputError message={form.errors.expiry_date} />
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Tutup
                            </Button>
                        </DialogClose>
                        <Button disabled={form.processing}>
                            <FileText />
                            Simpan Draf
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function OfferActions({
    candidate,
    offer,
    canManage,
    canApprove,
    templates,
}: {
    candidate: Candidate;
    offer: Candidate['offers'][number];
    canManage: boolean;
    canApprove: boolean;
    templates: Props['onboardingTemplates'];
}) {
    const run = (action: string) => {
        let notes: string | null = null;
        let onboardingTemplateId: number | null = null;

        if (['reject', 'decline', 'withdraw'].includes(action)) {
            notes = window.prompt('Catatan / sebab:');

            if (!notes) {
                return;
            }
        }

        if (action === 'accept' && templates.length > 0) {
            const selection = window.prompt(
                `ID template onboarding (pilihan):\n${templates
                    .map((template) => `${template.id} — ${template.name}`)
                    .join('\n')}`,
            );
            onboardingTemplateId = selection ? Number(selection) : null;
        }

        router.patch(
            `/pengambilan/calon/${candidate.id}/tawaran/${offer.id}/status`,
            {
                action,
                notes,
                onboarding_template_id: onboardingTemplateId,
            },
            { preserveScroll: true },
        );
    };

    return (
        <div className="flex flex-wrap gap-1">
            {offer.status === 'draft' && canManage && (
                <Button
                    size="sm"
                    variant="outline"
                    onClick={() => run('submit')}
                >
                    <Send />
                    Hantar Kelulusan
                </Button>
            )}
            {offer.status === 'pending_approval' && canApprove && (
                <>
                    <Button size="sm" onClick={() => run('approve')}>
                        <CheckCircle2 />
                        Lulus
                    </Button>
                    <Button
                        size="sm"
                        variant="outline"
                        onClick={() => run('reject')}
                    >
                        <XCircle />
                        Kembali Draf
                    </Button>
                </>
            )}
            {offer.status === 'approved' && canManage && (
                <Button size="sm" onClick={() => run('send')}>
                    <Send />
                    Rekod Dihantar
                </Button>
            )}
            {offer.status === 'sent' && canManage && (
                <>
                    <Button size="sm" onClick={() => run('accept')}>
                        <CheckCircle2 />
                        Diterima
                    </Button>
                    <Button
                        size="sm"
                        variant="outline"
                        onClick={() => run('decline')}
                    >
                        <XCircle />
                        Ditolak
                    </Button>
                </>
            )}
        </div>
    );
}

export default function RecruitmentShow({
    candidate,
    users,
    onboardingTemplates,
    permissions,
}: Props) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const currentUserId = auth.user.id;
    const nextRound =
        Math.max(0, ...candidate.interviews.map((item) => item.round)) + 1;
    const formatMoney = (value: number | null) =>
        value === null
            ? '—'
            : new Intl.NumberFormat('ms-MY', {
                  style: 'currency',
                  currency: 'MYR',
              }).format(value);

    return (
        <>
            <Head title={`${candidate.name} · Pengambilan`} />
            <div className="space-y-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="flex items-start gap-3">
                        <Button size="icon" variant="outline" asChild>
                            <Link href="/pengambilan">
                                <ArrowLeft />
                            </Link>
                        </Button>
                        <div>
                            <div className="flex flex-wrap items-center gap-2">
                                <h1 className="text-2xl font-semibold">
                                    {candidate.name}
                                </h1>
                                <Badge variant="outline">
                                    {stageLabel[candidate.stage]}
                                </Badge>
                            </div>
                            <p className="text-sm text-muted-foreground">
                                {candidate.candidate_number} ·{' '}
                                {candidate.requisition.code} ·{' '}
                                {candidate.requisition.title}
                            </p>
                        </div>
                    </div>
                    {permissions.can_manage && (
                        <div className="flex flex-wrap gap-2">
                            <CandidateEditDialog
                                candidate={candidate}
                                users={users}
                            />
                            {candidate.stage !== 'hired' && (
                                <StageDialog candidate={candidate} />
                            )}
                        </div>
                    )}
                </div>

                <div className="grid gap-6 xl:grid-cols-[22rem_1fr]">
                    <div className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Profil Calon</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3 text-sm">
                                {[
                                    ['E-mel', candidate.email],
                                    ['Telefon', candidate.phone],
                                    ['No. Pengenalan', candidate.nric],
                                    [
                                        'Majikan Semasa',
                                        candidate.current_company,
                                    ],
                                    [
                                        'Jawatan Semasa',
                                        candidate.current_position,
                                    ],
                                    [
                                        'Jangkaan Gaji',
                                        formatMoney(candidate.expected_salary),
                                    ],
                                    [
                                        'Tempoh Notis',
                                        candidate.notice_period_days === null
                                            ? null
                                            : `${candidate.notice_period_days} hari`,
                                    ],
                                    [
                                        'Sumber',
                                        sourceLabel[candidate.source] ??
                                            candidate.source,
                                    ],
                                    ['Recruiter', candidate.owner],
                                    [
                                        'Hiring Manager',
                                        candidate.requisition.hiring_manager,
                                    ],
                                ].map(([label, value]) => (
                                    <div
                                        key={label}
                                        className="grid grid-cols-[8rem_1fr] gap-2"
                                    >
                                        <span className="text-muted-foreground">
                                            {label}
                                        </span>
                                        <span>{value || '—'}</span>
                                    </div>
                                ))}
                                <div className="border-t pt-3">
                                    <p className="text-muted-foreground">
                                        Rating Saringan
                                    </p>
                                    <p className="mt-1 flex items-center gap-1 font-medium">
                                        <Star className="size-4 text-amber-500" />
                                        {candidate.rating
                                            ? `${candidate.rating.toFixed(1)} / 5`
                                            : 'Belum dinilai'}
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="flex-row items-center justify-between">
                                <div>
                                    <CardTitle>Dokumen</CardTitle>
                                    <CardDescription>
                                        Lampiran persendirian calon.
                                    </CardDescription>
                                </div>
                                {permissions.can_manage && (
                                    <DocumentDialog
                                        candidateId={candidate.id}
                                    />
                                )}
                            </CardHeader>
                            <CardContent className="space-y-2">
                                {candidate.documents.length === 0 ? (
                                    <p className="rounded-lg border border-dashed p-4 text-center text-sm text-muted-foreground">
                                        Tiada dokumen.
                                    </p>
                                ) : (
                                    candidate.documents.map((document) => (
                                        <div
                                            key={document.id}
                                            className="flex items-center justify-between gap-2 rounded-lg border p-3"
                                        >
                                            <div className="min-w-0">
                                                <p className="truncate text-sm font-medium">
                                                    {document.original_name}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {document.document_type} ·{' '}
                                                    {Math.ceil(
                                                        document.size / 1024,
                                                    )}{' '}
                                                    KB
                                                </p>
                                            </div>
                                            <div className="flex gap-1">
                                                <Button
                                                    size="icon"
                                                    variant="outline"
                                                    asChild
                                                >
                                                    <a
                                                        href={`/pengambilan/calon/${candidate.id}/dokumen/${document.id}`}
                                                    >
                                                        <Download />
                                                    </a>
                                                </Button>
                                                {permissions.can_manage && (
                                                    <Button
                                                        size="icon"
                                                        variant="outline"
                                                        onClick={() => {
                                                            if (
                                                                window.confirm(
                                                                    'Padam dokumen ini?',
                                                                )
                                                            ) {
                                                                router.delete(
                                                                    `/pengambilan/calon/${candidate.id}/dokumen/${document.id}`,
                                                                    {
                                                                        preserveScroll: true,
                                                                    },
                                                                );
                                                            }
                                                        }}
                                                    >
                                                        <Trash2 />
                                                    </Button>
                                                )}
                                            </div>
                                        </div>
                                    ))
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    <div className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Catatan Saringan</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p className="text-sm whitespace-pre-wrap">
                                    {candidate.screening_notes ||
                                        'Belum ada catatan saringan.'}
                                </p>
                                {(candidate.rejection_reason ||
                                    candidate.withdrawal_reason) && (
                                    <div className="mt-4 rounded-lg border border-red-500/30 bg-red-500/5 p-3 text-sm">
                                        {candidate.rejection_reason ??
                                            candidate.withdrawal_reason}
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="flex-row items-center justify-between">
                                <div>
                                    <CardTitle>Temu Duga & Scorecard</CardTitle>
                                    <CardDescription>
                                        Penjadualan panel dan keputusan setiap
                                        pusingan.
                                    </CardDescription>
                                </div>
                                {permissions.can_manage && (
                                    <InterviewDialog
                                        candidateId={candidate.id}
                                        users={users}
                                        nextRound={nextRound}
                                    />
                                )}
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {candidate.interviews.length === 0 ? (
                                    <p className="rounded-lg border border-dashed p-5 text-center text-sm text-muted-foreground">
                                        Belum ada temu duga.
                                    </p>
                                ) : (
                                    candidate.interviews.map((interview) => {
                                        const submitted =
                                            interview.scorecards.some(
                                                (scorecard) =>
                                                    scorecard.panel_user_id ===
                                                    currentUserId,
                                            );
                                        const isPanel =
                                            interview.panel_user_ids.includes(
                                                currentUserId,
                                            );

                                        return (
                                            <div
                                                key={interview.id}
                                                className="rounded-lg border p-4"
                                            >
                                                <div className="flex flex-wrap items-start justify-between gap-2">
                                                    <div>
                                                        <p className="font-medium">
                                                            Pusingan{' '}
                                                            {interview.round} ·{' '}
                                                            {
                                                                interview.interview_type
                                                            }
                                                        </p>
                                                        <p className="text-xs text-muted-foreground">
                                                            {new Date(
                                                                interview.scheduled_at,
                                                            ).toLocaleString(
                                                                'ms-MY',
                                                            )}{' '}
                                                            ·{' '}
                                                            {
                                                                interview.duration_minutes
                                                            }{' '}
                                                            minit
                                                        </p>
                                                        <p className="mt-1 text-xs">
                                                            Panel:{' '}
                                                            {interview.panel
                                                                .map(
                                                                    (panel) =>
                                                                        panel.name,
                                                                )
                                                                .join(', ')}
                                                        </p>
                                                    </div>
                                                    <div className="flex items-center gap-2">
                                                        <Badge variant="outline">
                                                            {statusLabel[
                                                                interview.status
                                                            ] ??
                                                                interview.status}
                                                        </Badge>
                                                        {permissions.can_interview &&
                                                            isPanel &&
                                                            !submitted &&
                                                            interview.status ===
                                                                'scheduled' && (
                                                                <ScorecardDialog
                                                                    candidateId={
                                                                        candidate.id
                                                                    }
                                                                    interview={
                                                                        interview
                                                                    }
                                                                />
                                                            )}
                                                    </div>
                                                </div>
                                                {interview.overall_score !==
                                                    null && (
                                                    <div className="mt-3 flex flex-wrap gap-3 rounded-md bg-muted p-3 text-sm">
                                                        <span>
                                                            Skor keseluruhan:{' '}
                                                            <strong>
                                                                {interview.overall_score.toFixed(
                                                                    2,
                                                                )}{' '}
                                                                / 5
                                                            </strong>
                                                        </span>
                                                        <span>
                                                            Cadangan:{' '}
                                                            <strong>
                                                                {recommendationLabel[
                                                                    interview.overall_recommendation ??
                                                                        ''
                                                                ] ??
                                                                    interview.overall_recommendation}
                                                            </strong>
                                                        </span>
                                                    </div>
                                                )}
                                                {interview.scorecards.length >
                                                    0 && (
                                                    <div className="mt-3 grid gap-2 md:grid-cols-2">
                                                        {interview.scorecards.map(
                                                            (scorecard) => (
                                                                <div
                                                                    key={
                                                                        scorecard.id
                                                                    }
                                                                    className="rounded-md border p-3 text-sm"
                                                                >
                                                                    <p className="font-medium">
                                                                        {
                                                                            scorecard.panel_name
                                                                        }{' '}
                                                                        ·{' '}
                                                                        {scorecard.overall_score.toFixed(
                                                                            2,
                                                                        )}
                                                                        /5
                                                                    </p>
                                                                    <p className="text-xs text-muted-foreground">
                                                                        {
                                                                            recommendationLabel[
                                                                                scorecard
                                                                                    .recommendation
                                                                            ]
                                                                        }
                                                                    </p>
                                                                    <p className="mt-2">
                                                                        {
                                                                            scorecard.strengths
                                                                        }
                                                                    </p>
                                                                </div>
                                                            ),
                                                        )}
                                                    </div>
                                                )}
                                            </div>
                                        );
                                    })
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="flex-row items-center justify-between">
                                <div>
                                    <CardTitle>Tawaran Pekerjaan</CardTitle>
                                    <CardDescription>
                                        Kelulusan tawaran dan rekod keputusan
                                        calon.
                                    </CardDescription>
                                </div>
                                {permissions.can_manage &&
                                    !candidate.offers.some((offer) =>
                                        [
                                            'draft',
                                            'pending_approval',
                                            'approved',
                                            'sent',
                                            'accepted',
                                        ].includes(offer.status),
                                    ) && <OfferDialog candidate={candidate} />}
                            </CardHeader>
                            <CardContent>
                                {candidate.offers.length === 0 ? (
                                    <p className="rounded-lg border border-dashed p-5 text-center text-sm text-muted-foreground">
                                        Belum ada tawaran.
                                    </p>
                                ) : (
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>Tawaran</TableHead>
                                                <TableHead>
                                                    Gaji / Mula
                                                </TableHead>
                                                <TableHead>Status</TableHead>
                                                <TableHead>Tindakan</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {candidate.offers.map((offer) => (
                                                <TableRow key={offer.id}>
                                                    <TableCell>
                                                        <p className="font-medium">
                                                            {
                                                                offer.position_name
                                                            }
                                                        </p>
                                                        <p className="text-xs text-muted-foreground">
                                                            {offer.offer_number}
                                                        </p>
                                                    </TableCell>
                                                    <TableCell>
                                                        <p>
                                                            {formatMoney(
                                                                offer.salary,
                                                            )}
                                                        </p>
                                                        <p className="text-xs text-muted-foreground">
                                                            {offer.start_date}
                                                        </p>
                                                    </TableCell>
                                                    <TableCell>
                                                        <Badge variant="outline">
                                                            {statusLabel[
                                                                offer.status
                                                            ] ?? offer.status}
                                                        </Badge>
                                                    </TableCell>
                                                    <TableCell>
                                                        {(permissions.can_manage ||
                                                            permissions.can_approve) && (
                                                            <OfferActions
                                                                candidate={
                                                                    candidate
                                                                }
                                                                offer={offer}
                                                                canManage={
                                                                    permissions.can_manage
                                                                }
                                                                canApprove={
                                                                    permissions.can_approve
                                                                }
                                                                templates={
                                                                    onboardingTemplates
                                                                }
                                                            />
                                                        )}
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                )}
                            </CardContent>
                        </Card>

                        {candidate.onboarding && (
                            <Card>
                                <CardHeader className="flex-row items-center justify-between">
                                    <div>
                                        <CardTitle>Onboarding</CardTitle>
                                        <CardDescription>
                                            Kes dijana daripada tawaran yang
                                            diterima.
                                        </CardDescription>
                                    </div>
                                    <Button variant="outline" asChild>
                                        <Link href="/onboarding">
                                            <ListChecks />
                                            Pusat Onboarding
                                        </Link>
                                    </Button>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <div>
                                            <p className="font-medium">
                                                {candidate.onboarding
                                                    .template ??
                                                    'Tanpa template'}
                                            </p>
                                            <p className="text-sm text-muted-foreground">
                                                Mula{' '}
                                                {
                                                    candidate.onboarding
                                                        .start_date
                                                }{' '}
                                                ·{' '}
                                                {statusLabel[
                                                    candidate.onboarding.status
                                                ] ??
                                                    candidate.onboarding.status}
                                            </p>
                                        </div>
                                        <p className="text-2xl font-semibold">
                                            {candidate.onboarding.progress}%
                                        </p>
                                    </div>
                                    <div className="h-2 overflow-hidden rounded-full bg-muted">
                                        <div
                                            className="h-full bg-emerald-500"
                                            style={{
                                                width: `${candidate.onboarding.progress}%`,
                                            }}
                                        />
                                    </div>
                                    <div className="rounded-lg border bg-muted/30 p-4 text-sm">
                                        {candidate.onboarding
                                            .employee_record ? (
                                            <div className="grid gap-3 sm:grid-cols-2">
                                                {[
                                                    [
                                                        'No. Pekerja',
                                                        candidate.onboarding
                                                            .employee_record
                                                            .employee_number,
                                                    ],
                                                    [
                                                        'E-mel Rasmi',
                                                        candidate.onboarding
                                                            .employee_record
                                                            .official_email,
                                                    ],
                                                    [
                                                        'Lokasi',
                                                        candidate.onboarding
                                                            .employee_record
                                                            .office,
                                                    ],
                                                    [
                                                        'Status Akaun',
                                                        candidate.onboarding
                                                            .employee_record
                                                            .status === 'active'
                                                            ? 'Aktif'
                                                            : `Aktif mulai ${candidate.onboarding.employee_record.activation_date}`,
                                                    ],
                                                ].map(([label, value]) => (
                                                    <div key={label}>
                                                        <p className="text-xs text-muted-foreground">
                                                            {label}
                                                        </p>
                                                        <p className="font-medium">
                                                            {value || '—'}
                                                        </p>
                                                    </div>
                                                ))}
                                            </div>
                                        ) : candidate.onboarding
                                              .legacy_employee_id ? (
                                            <p>
                                                Dipautkan kepada pekerja sedia
                                                ada db_spp ID:{' '}
                                                {
                                                    candidate.onboarding
                                                        .legacy_employee_id
                                                }
                                            </p>
                                        ) : (
                                            <p className="text-muted-foreground">
                                                Menunggu Pengurus HR menggunakan
                                                tindakan “Sahkan & Daftar
                                                sebagai Pekerja” di Pusat
                                                Onboarding.
                                            </p>
                                        )}
                                    </div>
                                </CardContent>
                            </Card>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}
