import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    BriefcaseBusiness,
    CalendarClock,
    CheckCircle2,
    Download,
    FileSearch,
    ListChecks,
    PauseCircle,
    Pencil,
    Search,
    Send,
    UserPlus,
    UsersRound,
    XCircle,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useEffect, useState } from 'react';
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

type RequisitionStatus =
    | 'draft'
    | 'pending_approval'
    | 'approved'
    | 'published'
    | 'on_hold'
    | 'closed'
    | 'cancelled';
type CandidateStage =
    | 'applied'
    | 'screening'
    | 'shortlisted'
    | 'interview'
    | 'offer'
    | 'hired'
    | 'rejected'
    | 'withdrawn';
type Requisition = {
    id: number;
    code: string;
    title: string;
    department_id: number | null;
    position_name: string | null;
    employment_type: string;
    vacancies: number;
    hiring_manager_user_id: number | null;
    hiring_manager: string | null;
    location: string | null;
    description: string;
    requirements: string;
    min_salary: number | null;
    max_salary: number | null;
    target_hire_date: string | null;
    status: RequisitionStatus;
    approval_notes: string | null;
    candidates_count: number;
};
type Candidate = {
    id: number;
    candidate_number: string;
    name: string;
    email: string;
    phone: string;
    source: string;
    stage: CandidateStage;
    rating: number | null;
    applied_at: string;
    requisition: { id: number; code: string; title: string };
    owner: string | null;
    offer_status: string | null;
    onboarding_status: string | null;
};
type Props = {
    candidates: {
        data: Candidate[];
        from: number | null;
        to: number | null;
        total: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
    requisitions: Requisition[];
    statistics: {
        open_requisitions: number;
        open_vacancies: number;
        active_candidates: number;
        interviews_14_days: number;
        pending_offers: number;
        active_onboarding: number;
    };
    pipeline: Record<CandidateStage, number>;
    upcomingInterviews: {
        id: number;
        candidate_id: number;
        candidate_name: string;
        candidate_number: string;
        round: number;
        type: string;
        scheduled_at: string;
        location_or_link: string | null;
    }[];
    departments: { id: number; name: string }[];
    positionNames: string[];
    users: { id: number; name: string; email: string; roles: string[] }[];
    filters: { search: string; stage: string; requisition_id: string | number };
    permissions: {
        can_manage: boolean;
        can_approve: boolean;
        can_interview: boolean;
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
const requisitionLabel: Record<RequisitionStatus, string> = {
    draft: 'Draf',
    pending_approval: 'Menunggu Kelulusan',
    approved: 'Diluluskan',
    published: 'Dibuka',
    on_hold: 'Ditangguhkan',
    closed: 'Ditutup',
    cancelled: 'Dibatalkan',
};
const statusClass: Record<string, string> = {
    draft: 'border-slate-500/30 bg-slate-500/10 text-slate-700 dark:text-slate-300',
    pending_approval:
        'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300',
    approved:
        'border-blue-500/30 bg-blue-500/10 text-blue-700 dark:text-blue-300',
    published:
        'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
    on_hold:
        'border-orange-500/30 bg-orange-500/10 text-orange-700 dark:text-orange-300',
    closed: 'border-zinc-500/30 bg-zinc-500/10 text-zinc-700 dark:text-zinc-300',
    cancelled: 'border-red-500/30 bg-red-500/10 text-red-700 dark:text-red-300',
    applied: 'border-sky-500/30 bg-sky-500/10 text-sky-700 dark:text-sky-300',
    screening:
        'border-cyan-500/30 bg-cyan-500/10 text-cyan-700 dark:text-cyan-300',
    shortlisted:
        'border-indigo-500/30 bg-indigo-500/10 text-indigo-700 dark:text-indigo-300',
    interview:
        'border-violet-500/30 bg-violet-500/10 text-violet-700 dark:text-violet-300',
    offer: 'border-pink-500/30 bg-pink-500/10 text-pink-700 dark:text-pink-300',
    hired: 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
    rejected: 'border-red-500/30 bg-red-500/10 text-red-700 dark:text-red-300',
    withdrawn:
        'border-zinc-500/30 bg-zinc-500/10 text-zinc-700 dark:text-zinc-300',
};
const employmentLabel: Record<string, string> = {
    permanent: 'Tetap',
    contract: 'Kontrak',
    temporary: 'Sementara',
    internship: 'Latihan Industri',
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

function RequisitionDialog({
    requisition,
    departments,
    positionNames,
    users,
}: Pick<Props, 'departments' | 'positionNames' | 'users'> & {
    requisition?: Requisition;
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        code: requisition?.code ?? '',
        title: requisition?.title ?? '',
        department_id: requisition?.department_id
            ? String(requisition.department_id)
            : '',
        position_name: requisition?.position_name ?? '',
        employment_type: requisition?.employment_type ?? 'permanent',
        vacancies: requisition ? String(requisition.vacancies) : '1',
        hiring_manager_user_id: requisition?.hiring_manager_user_id
            ? String(requisition.hiring_manager_user_id)
            : '',
        location: requisition?.location ?? '',
        description: requisition?.description ?? '',
        requirements: requisition?.requirements ?? '',
        min_salary:
            requisition?.min_salary === null ||
            requisition?.min_salary === undefined
                ? ''
                : String(requisition.min_salary),
        max_salary:
            requisition?.max_salary === null ||
            requisition?.max_salary === undefined
                ? ''
                : String(requisition.max_salary),
        target_hire_date: requisition?.target_hire_date ?? '',
    });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                if (!requisition) {
                    form.reset();
                }

                setOpen(false);
            },
        };

        if (requisition) {
            form.put(`/pengambilan/kekosongan/${requisition.id}`, options);
        } else {
            form.post('/pengambilan/kekosongan', options);
        }
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size={requisition ? 'sm' : 'default'} variant="outline">
                    {requisition ? <Pencil /> : <BriefcaseBusiness />}
                    {requisition ? 'Edit' : 'Kekosongan Baharu'}
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>
                        {requisition
                            ? 'Edit Kekosongan Jawatan'
                            : 'Permohonan Kekosongan Jawatan'}
                    </DialogTitle>
                    <DialogDescription>
                        Simpan sebagai Draf sebelum dihantar untuk kelulusan.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-2">
                            <Label>Kod</Label>
                            <Input
                                value={form.data.code}
                                onChange={(event) =>
                                    form.setData('code', event.target.value)
                                }
                                placeholder="REQ-2026-001"
                            />
                            <InputError message={form.errors.code} />
                        </div>
                        <div className="space-y-2">
                            <Label>Nama Jawatan / Kekosongan</Label>
                            <Input
                                value={form.data.title}
                                onChange={(event) =>
                                    form.setData('title', event.target.value)
                                }
                            />
                            <InputError message={form.errors.title} />
                        </div>
                        <div className="space-y-2">
                            <Label>Jabatan</Label>
                            <Select
                                value={form.data.department_id || 'none'}
                                onValueChange={(value) =>
                                    form.setData(
                                        'department_id',
                                        value === 'none' ? '' : value,
                                    )
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">
                                        Semua / Belum ditetapkan
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
                        </div>
                        <div className="space-y-2">
                            <Label>Nama Jawatan db_spp</Label>
                            <Select
                                value={form.data.position_name || 'none'}
                                onValueChange={(value) =>
                                    form.setData(
                                        'position_name',
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
                                    {positionNames.map((position) => (
                                        <SelectItem
                                            key={position}
                                            value={position}
                                        >
                                            {position}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-2">
                            <Label>Jenis Pekerjaan</Label>
                            <Select
                                value={form.data.employment_type}
                                onValueChange={(value) =>
                                    form.setData('employment_type', value)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {Object.entries(employmentLabel).map(
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
                            <Label>Bilangan Kekosongan</Label>
                            <Input
                                type="number"
                                min="1"
                                value={form.data.vacancies}
                                onChange={(event) =>
                                    form.setData(
                                        'vacancies',
                                        event.target.value,
                                    )
                                }
                            />
                        </div>
                        <div className="space-y-2">
                            <Label>Hiring Manager</Label>
                            <Select
                                value={
                                    form.data.hiring_manager_user_id || 'none'
                                }
                                onValueChange={(value) =>
                                    form.setData(
                                        'hiring_manager_user_id',
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
                        <div className="space-y-2">
                            <Label>Lokasi</Label>
                            <Input
                                value={form.data.location}
                                onChange={(event) =>
                                    form.setData('location', event.target.value)
                                }
                            />
                        </div>
                        <div className="space-y-2">
                            <Label>Gaji Minimum</Label>
                            <Input
                                type="number"
                                min="0"
                                step="0.01"
                                value={form.data.min_salary}
                                onChange={(event) =>
                                    form.setData(
                                        'min_salary',
                                        event.target.value,
                                    )
                                }
                            />
                        </div>
                        <div className="space-y-2">
                            <Label>Gaji Maksimum</Label>
                            <Input
                                type="number"
                                min="0"
                                step="0.01"
                                value={form.data.max_salary}
                                onChange={(event) =>
                                    form.setData(
                                        'max_salary',
                                        event.target.value,
                                    )
                                }
                            />
                        </div>
                        <div className="space-y-2">
                            <Label>Tarikh Sasaran Pengambilan</Label>
                            <Input
                                type="date"
                                value={form.data.target_hire_date}
                                onChange={(event) =>
                                    form.setData(
                                        'target_hire_date',
                                        event.target.value,
                                    )
                                }
                            />
                        </div>
                    </div>
                    <div className="space-y-2">
                        <Label>Deskripsi Tugas</Label>
                        <textarea
                            value={form.data.description}
                            onChange={(event) =>
                                form.setData('description', event.target.value)
                            }
                            className="min-h-28 w-full rounded-md border bg-background px-3 py-2 text-sm"
                        />
                        <InputError message={form.errors.description} />
                    </div>
                    <div className="space-y-2">
                        <Label>Syarat & Kelayakan</Label>
                        <textarea
                            value={form.data.requirements}
                            onChange={(event) =>
                                form.setData('requirements', event.target.value)
                            }
                            className="min-h-28 w-full rounded-md border bg-background px-3 py-2 text-sm"
                        />
                        <InputError message={form.errors.requirements} />
                    </div>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Tutup
                            </Button>
                        </DialogClose>
                        <Button disabled={form.processing}>
                            <BriefcaseBusiness />
                            {requisition ? 'Simpan Perubahan' : 'Simpan Draf'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function CandidateDialog({
    requisitions,
    users,
}: Pick<Props, 'requisitions' | 'users'>) {
    const [open, setOpen] = useState(false);
    const form = useForm<{
        recruitment_requisition_id: string;
        name: string;
        email: string;
        phone: string;
        nric: string;
        current_company: string;
        current_position: string;
        expected_salary: string;
        notice_period_days: string;
        source: string;
        owner_user_id: string;
        screening_notes: string;
        resume: File | null;
    }>({
        recruitment_requisition_id: '',
        name: '',
        email: '',
        phone: '',
        nric: '',
        current_company: '',
        current_position: '',
        expected_salary: '',
        notice_period_days: '',
        source: 'direct',
        owner_user_id: '',
        screening_notes: '',
        resume: null,
    });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post('/pengambilan/calon', {
            forceFormData: true,
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
                    Tambah Calon
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>Tambah Calon</DialogTitle>
                    <DialogDescription>
                        Rekodkan calon dan resume ke saluran pengambilan.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-2">
                        <Label>Kekosongan Jawatan</Label>
                        <Select
                            value={
                                form.data.recruitment_requisition_id ||
                                undefined
                            }
                            onValueChange={(value) =>
                                form.setData(
                                    'recruitment_requisition_id',
                                    value,
                                )
                            }
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Pilih kekosongan" />
                            </SelectTrigger>
                            <SelectContent>
                                {requisitions
                                    .filter((item) =>
                                        ['approved', 'published'].includes(
                                            item.status,
                                        ),
                                    )
                                    .map((item) => (
                                        <SelectItem
                                            key={item.id}
                                            value={String(item.id)}
                                        >
                                            {item.code} · {item.title}
                                        </SelectItem>
                                    ))}
                            </SelectContent>
                        </Select>
                        <InputError
                            message={form.errors.recruitment_requisition_id}
                        />
                    </div>
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
                        ].map(([field, label, type]) => (
                            <div className="space-y-2" key={field}>
                                <Label>{label}</Label>
                                <Input
                                    type={type}
                                    value={
                                        form.data[
                                            field as keyof typeof form.data
                                        ] as string
                                    }
                                    onChange={(event) =>
                                        form.setData(
                                            field as
                                                | 'name'
                                                | 'email'
                                                | 'phone'
                                                | 'nric'
                                                | 'current_company'
                                                | 'current_position'
                                                | 'expected_salary'
                                                | 'notice_period_days',
                                            event.target.value,
                                        )
                                    }
                                />
                            </div>
                        ))}
                        <div className="space-y-2">
                            <Label>Sumber Calon</Label>
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
                                value={form.data.owner_user_id || 'auto'}
                                onValueChange={(value) =>
                                    form.setData(
                                        'owner_user_id',
                                        value === 'auto' ? '' : value,
                                    )
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="auto">
                                        Pengguna semasa
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
                        <div className="space-y-2 sm:col-span-2">
                            <Label>Resume / CV (PDF, DOC, imej)</Label>
                            <Input
                                type="file"
                                accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                                onChange={(event) =>
                                    form.setData(
                                        'resume',
                                        event.target.files?.[0] ?? null,
                                    )
                                }
                            />
                            <InputError message={form.errors.resume} />
                        </div>
                    </div>
                    <div className="space-y-2">
                        <Label>Catatan Saringan Awal</Label>
                        <textarea
                            value={form.data.screening_notes}
                            onChange={(event) =>
                                form.setData(
                                    'screening_notes',
                                    event.target.value,
                                )
                            }
                            className="min-h-24 w-full rounded-md border bg-background px-3 py-2 text-sm"
                        />
                    </div>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Tutup
                            </Button>
                        </DialogClose>
                        <Button disabled={form.processing}>
                            <UserPlus />
                            Tambah Calon
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function RequisitionActions({
    requisition,
    canManage,
    canApprove,
}: {
    requisition: Requisition;
    canManage: boolean;
    canApprove: boolean;
}) {
    const act = (action: string) => {
        const notes =
            action === 'reject'
                ? window.prompt('Catatan penolakan:')
                : action === 'cancel'
                  ? window.prompt('Sebab pembatalan:')
                  : null;

        if ((action === 'reject' || action === 'cancel') && !notes) {
            return;
        }

        router.patch(
            `/pengambilan/kekosongan/${requisition.id}/status`,
            { action, notes },
            { preserveScroll: true },
        );
    };
    const actions: { action: string; label: string; icon: typeof Send }[] = [];

    if (requisition.status === 'draft' && canManage) {
        actions.push({ action: 'submit', label: 'Hantar', icon: Send });
    }

    if (requisition.status === 'pending_approval' && canApprove) {
        actions.push({
            action: 'approve',
            label: 'Lulus',
            icon: CheckCircle2,
        });
        actions.push({
            action: 'reject',
            label: 'Kembali Draf',
            icon: XCircle,
        });
    }

    if (requisition.status === 'approved' && canManage) {
        actions.push({ action: 'publish', label: 'Buka', icon: Send });
    }

    if (requisition.status === 'published' && canManage) {
        actions.push({ action: 'hold', label: 'Tangguh', icon: PauseCircle });
        actions.push({ action: 'close', label: 'Tutup', icon: XCircle });
    }

    if (requisition.status === 'on_hold' && canManage) {
        actions.push({ action: 'resume', label: 'Buka Semula', icon: Send });
        actions.push({ action: 'close', label: 'Tutup', icon: XCircle });
    }

    return (
        <div className="flex flex-wrap gap-1">
            {actions.map(({ action, label, icon: Icon }) => (
                <Button
                    key={action}
                    type="button"
                    size="sm"
                    variant="outline"
                    onClick={() => act(action)}
                >
                    <Icon />
                    {label}
                </Button>
            ))}
        </div>
    );
}

export default function RecruitmentIndex({
    candidates,
    requisitions,
    statistics,
    pipeline,
    upcomingInterviews,
    departments,
    positionNames,
    users,
    filters,
    permissions,
}: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    useEffect(() => {
        if (search === (filters.search ?? '')) {
            return;
        }

        const timer = window.setTimeout(() => {
            router.get(
                '/pengambilan',
                {
                    search,
                    stage: filters.stage || undefined,
                    requisition_id: filters.requisition_id || undefined,
                },
                { preserveState: true, replace: true },
            );
        }, 350);

        return () => window.clearTimeout(timer);
    }, [filters.requisition_id, filters.search, filters.stage, search]);
    const filter = (key: string, value: string) =>
        router.get(
            '/pengambilan',
            {
                search: search || undefined,
                stage:
                    key === 'stage'
                        ? value === 'all'
                            ? undefined
                            : value
                        : filters.stage || undefined,
                requisition_id:
                    key === 'requisition_id'
                        ? value === 'all'
                            ? undefined
                            : value
                        : filters.requisition_id || undefined,
            },
            { preserveState: true, replace: true },
        );
    const summaryCards: {
        label: string;
        value: number;
        icon: LucideIcon;
    }[] = [
        {
            label: 'Kekosongan Aktif',
            value: statistics.open_requisitions,
            icon: BriefcaseBusiness,
        },
        {
            label: 'Bilangan Posisi',
            value: statistics.open_vacancies,
            icon: UsersRound,
        },
        {
            label: 'Calon Aktif',
            value: statistics.active_candidates,
            icon: UserPlus,
        },
        {
            label: 'Temu Duga 14 Hari',
            value: statistics.interviews_14_days,
            icon: CalendarClock,
        },
        {
            label: 'Tawaran Tindakan',
            value: statistics.pending_offers,
            icon: FileSearch,
        },
        {
            label: 'Onboarding Aktif',
            value: statistics.active_onboarding,
            icon: ListChecks,
        },
    ];

    return (
        <>
            <Head title="Pengambilan & Calon" />
            <div className="space-y-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            Pengambilan & Calon
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Kekosongan, pipeline calon, temu duga, tawaran dan
                            onboarding dalam satu aliran.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <a href="/pengambilan/laporan.csv">
                                <Download />
                                Eksport CSV
                            </a>
                        </Button>
                        {permissions.can_manage && (
                            <>
                                <RequisitionDialog
                                    departments={departments}
                                    positionNames={positionNames}
                                    users={users}
                                />
                                <CandidateDialog
                                    requisitions={requisitions}
                                    users={users}
                                />
                            </>
                        )}
                    </div>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
                    {summaryCards.map(({ label, value, icon: Icon }) => (
                        <Card key={label}>
                            <CardContent className="flex items-center gap-3 p-4">
                                <div className="rounded-lg bg-pink-500/10 p-2 text-pink-700 dark:text-pink-300">
                                    <Icon className="size-5" />
                                </div>
                                <div>
                                    <p className="text-xl font-semibold">
                                        {value}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {label}
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Pipeline Calon</CardTitle>
                        <CardDescription>
                            Ringkasan semasa mengikut peringkat pengambilan.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-3 sm:grid-cols-4 xl:grid-cols-8">
                        {(Object.keys(stageLabel) as CandidateStage[]).map(
                            (stage) => (
                                <button
                                    key={stage}
                                    type="button"
                                    onClick={() => filter('stage', stage)}
                                    className="rounded-lg border p-3 text-left transition-colors hover:bg-muted"
                                >
                                    <p className="text-2xl font-semibold">
                                        {pipeline[stage] ?? 0}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {stageLabel[stage]}
                                    </p>
                                </button>
                            ),
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Kekosongan Jawatan</CardTitle>
                        <CardDescription>
                            Kelulusan, pembukaan dan bilangan calon bagi setiap
                            kekosongan.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Kekosongan</TableHead>
                                    <TableHead>Jabatan / Manager</TableHead>
                                    <TableHead>Jenis</TableHead>
                                    <TableHead>Posisi / Calon</TableHead>
                                    <TableHead>Status</TableHead>
                                    {(permissions.can_manage ||
                                        permissions.can_approve) && (
                                        <TableHead>Tindakan</TableHead>
                                    )}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {requisitions.length === 0 ? (
                                    <TableRow>
                                        <TableCell
                                            colSpan={6}
                                            className="py-10 text-center text-muted-foreground"
                                        >
                                            Belum ada kekosongan jawatan.
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    requisitions.map((requisition) => (
                                        <TableRow key={requisition.id}>
                                            <TableCell>
                                                <p className="font-medium">
                                                    {requisition.title}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {requisition.code}
                                                </p>
                                            </TableCell>
                                            <TableCell>
                                                <p>
                                                    {departments.find(
                                                        (item) =>
                                                            item.id ===
                                                            requisition.department_id,
                                                    )?.name ?? '—'}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {requisition.hiring_manager ??
                                                        'Belum ditetapkan'}
                                                </p>
                                            </TableCell>
                                            <TableCell>
                                                {employmentLabel[
                                                    requisition.employment_type
                                                ] ??
                                                    requisition.employment_type}
                                            </TableCell>
                                            <TableCell>
                                                {requisition.vacancies} posisi ·{' '}
                                                {requisition.candidates_count}{' '}
                                                calon
                                            </TableCell>
                                            <TableCell>
                                                <Badge
                                                    variant="outline"
                                                    className={
                                                        statusClass[
                                                            requisition.status
                                                        ]
                                                    }
                                                >
                                                    {
                                                        requisitionLabel[
                                                            requisition.status
                                                        ]
                                                    }
                                                </Badge>
                                            </TableCell>
                                            {(permissions.can_manage ||
                                                permissions.can_approve) && (
                                                <TableCell>
                                                    <div className="flex flex-wrap gap-1">
                                                        {permissions.can_manage &&
                                                            [
                                                                'draft',
                                                                'approved',
                                                            ].includes(
                                                                requisition.status,
                                                            ) && (
                                                                <RequisitionDialog
                                                                    requisition={
                                                                        requisition
                                                                    }
                                                                    departments={
                                                                        departments
                                                                    }
                                                                    positionNames={
                                                                        positionNames
                                                                    }
                                                                    users={
                                                                        users
                                                                    }
                                                                />
                                                            )}
                                                        <RequisitionActions
                                                            requisition={
                                                                requisition
                                                            }
                                                            canManage={
                                                                permissions.can_manage
                                                            }
                                                            canApprove={
                                                                permissions.can_approve
                                                            }
                                                        />
                                                    </div>
                                                </TableCell>
                                            )}
                                        </TableRow>
                                    ))
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                <div className="grid gap-6 xl:grid-cols-[1fr_22rem]">
                    <Card>
                        <CardHeader>
                            <CardTitle>Senarai Calon</CardTitle>
                            <CardDescription>
                                Klik calon untuk saringan, temu duga dan
                                tawaran.
                            </CardDescription>
                            <div className="grid gap-3 pt-2 sm:grid-cols-3">
                                <div className="relative">
                                    <Search className="absolute top-2.5 left-3 size-4 text-muted-foreground" />
                                    <Input
                                        value={search}
                                        onChange={(event) =>
                                            setSearch(event.target.value)
                                        }
                                        className="pl-9"
                                        placeholder="Cari calon..."
                                    />
                                </div>
                                <Select
                                    value={filters.stage || 'all'}
                                    onValueChange={(value) =>
                                        filter('stage', value)
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            Semua peringkat
                                        </SelectItem>
                                        {(
                                            Object.keys(
                                                stageLabel,
                                            ) as CandidateStage[]
                                        ).map((stage) => (
                                            <SelectItem
                                                key={stage}
                                                value={stage}
                                            >
                                                {stageLabel[stage]}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <Select
                                    value={
                                        String(filters.requisition_id) || 'all'
                                    }
                                    onValueChange={(value) =>
                                        filter('requisition_id', value)
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            Semua kekosongan
                                        </SelectItem>
                                        {requisitions.map((item) => (
                                            <SelectItem
                                                key={item.id}
                                                value={String(item.id)}
                                            >
                                                {item.code} · {item.title}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </CardHeader>
                        <CardContent className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Calon</TableHead>
                                        <TableHead>Kekosongan</TableHead>
                                        <TableHead>Sumber</TableHead>
                                        <TableHead>Peringkat</TableHead>
                                        <TableHead>Rating</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {candidates.data.length === 0 ? (
                                        <TableRow>
                                            <TableCell
                                                colSpan={5}
                                                className="py-10 text-center text-muted-foreground"
                                            >
                                                Tiada calon sepadan.
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        candidates.data.map((candidate) => (
                                            <TableRow key={candidate.id}>
                                                <TableCell>
                                                    <Link
                                                        href={`/pengambilan/calon/${candidate.id}`}
                                                        className="font-medium text-primary hover:underline"
                                                    >
                                                        {candidate.name}
                                                    </Link>
                                                    <p className="text-xs text-muted-foreground">
                                                        {
                                                            candidate.candidate_number
                                                        }{' '}
                                                        · {candidate.email}
                                                    </p>
                                                </TableCell>
                                                <TableCell>
                                                    {
                                                        candidate.requisition
                                                            .title
                                                    }
                                                </TableCell>
                                                <TableCell>
                                                    {sourceLabel[
                                                        candidate.source
                                                    ] ?? candidate.source}
                                                </TableCell>
                                                <TableCell>
                                                    <Badge
                                                        variant="outline"
                                                        className={
                                                            statusClass[
                                                                candidate.stage
                                                            ]
                                                        }
                                                    >
                                                        {
                                                            stageLabel[
                                                                candidate.stage
                                                            ]
                                                        }
                                                    </Badge>
                                                </TableCell>
                                                <TableCell>
                                                    {candidate.rating
                                                        ? `${candidate.rating.toFixed(1)} / 5`
                                                        : '—'}
                                                </TableCell>
                                            </TableRow>
                                        ))
                                    )}
                                </TableBody>
                            </Table>
                            <div className="mt-4 flex flex-wrap items-center justify-between gap-2">
                                <p className="text-xs text-muted-foreground">
                                    {candidates.from ?? 0}–{candidates.to ?? 0}{' '}
                                    daripada {candidates.total}
                                </p>
                                <div className="flex flex-wrap gap-1">
                                    {candidates.links.map((link, index) =>
                                        link.url ? (
                                            <Button
                                                key={index}
                                                size="sm"
                                                variant={
                                                    link.active
                                                        ? 'default'
                                                        : 'outline'
                                                }
                                                asChild
                                            >
                                                <Link
                                                    href={link.url}
                                                    preserveScroll
                                                    dangerouslySetInnerHTML={{
                                                        __html: link.label,
                                                    }}
                                                />
                                            </Button>
                                        ) : null,
                                    )}
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Temu Duga Akan Datang</CardTitle>
                            <CardDescription>
                                Jadual dalam 14 hari seterusnya.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {upcomingInterviews.length === 0 ? (
                                <p className="rounded-lg border border-dashed p-5 text-center text-sm text-muted-foreground">
                                    Tiada temu duga dijadualkan.
                                </p>
                            ) : (
                                upcomingInterviews.map((interview) => (
                                    <Link
                                        key={interview.id}
                                        href={`/pengambilan/calon/${interview.candidate_id}`}
                                        className="block rounded-lg border p-3 transition-colors hover:bg-muted"
                                    >
                                        <p className="font-medium">
                                            {interview.candidate_name}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            Pusingan {interview.round} ·{' '}
                                            {new Date(
                                                interview.scheduled_at,
                                            ).toLocaleString('ms-MY')}
                                        </p>
                                        <p className="mt-1 truncate text-xs">
                                            {interview.location_or_link ??
                                                'Lokasi belum ditetapkan'}
                                        </p>
                                    </Link>
                                ))
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}
