import { Head, router, useForm } from '@inertiajs/react';
import {
    Award,
    BookOpen,
    Building2,
    CalendarPlus,
    GraduationCap,
    Plus,
    ShieldCheck,
    Target,
    Trash2,
    WalletCards,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
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
import { Checkbox } from '@/components/ui/checkbox';
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

type Provider = {
    id: number;
    code: string;
    name: string;
    contact_person: string | null;
    email: string | null;
    phone: string | null;
    accreditation: string | null;
    notes: string | null;
    is_active: boolean;
    courses_count: number;
};
type Course = {
    id: number;
    training_provider_id: number | null;
    code: string;
    title: string;
    category: string;
    delivery_method: string;
    description: string | null;
    learning_objectives: string | null;
    duration_hours: string;
    cpd_points: string;
    default_cost: string;
    currency: string;
    certificate_validity_months: number | null;
    is_mandatory: boolean;
    is_active: boolean;
    provider: { id: number; name: string } | null;
    sessions_count: number;
};
type Session = {
    id: number;
    training_course_id: number;
    session_code: string;
    starts_at: string;
    ends_at: string;
    registration_deadline: string | null;
    venue: string | null;
    facilitator: string | null;
    capacity: number;
    cost_per_participant: string;
    budget_code: string | null;
    status: string;
    notes: string | null;
    enrolled_count: number;
    course: Course;
};
type Competency = {
    id: number;
    code: string;
    name: string;
    category: string;
    description: string | null;
    maximum_level: number;
    level_descriptions: string[] | null;
    is_active: boolean;
    requirements_count: number;
};
type Props = {
    year: number;
    providers: Provider[];
    courses: Course[];
    sessions: Session[];
    budgets: {
        id: number;
        year: number;
        department_id: number | null;
        department: string;
        budget_code: string | null;
        allocated_amount: number;
        notes: string | null;
        allocated: number;
        used: number;
        available: number;
    }[];
    competencies: Competency[];
    requirements: {
        id: number;
        competency_id: number;
        competency: string;
        department_id: number | null;
        department: string;
        position_name: string | null;
        required_level: number;
        is_mandatory: boolean;
        notes: string | null;
    }[];
    assignments: {
        id: number;
        department_id: number;
        department: string;
        approver_user_id: number;
        approver: string;
        is_active: boolean;
    }[];
    departments: { id: number; name: string }[];
    positionNames: string[];
    supervisors: { id: number; name: string; email: string }[];
};

function FormDialog({
    trigger,
    title,
    description,
    children,
    submit,
    processing,
}: {
    trigger: ReactNode;
    title: string;
    description: string;
    children: ReactNode;
    submit: (event: FormEvent<HTMLFormElement>, close: () => void) => void;
    processing: boolean;
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent className="max-h-[92vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>
                <form
                    onSubmit={(event) => submit(event, () => setOpen(false))}
                    className="space-y-4"
                >
                    {children}
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Tutup
                            </Button>
                        </DialogClose>
                        <Button disabled={processing}>Simpan</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function ProviderDialog() {
    const form = useForm({
        code: '',
        name: '',
        contact_person: '',
        email: '',
        phone: '',
        accreditation: '',
        notes: '',
        is_active: true,
    });

    return (
        <FormDialog
            trigger={
                <Button>
                    <Plus />
                    Penyedia
                </Button>
            }
            title="Penyedia Latihan"
            description="Simpan organisasi, fasilitator atau badan akreditasi."
            processing={form.processing}
            submit={(event, close) => {
                event.preventDefault();
                form.post('/tetapan-latihan/penyedia', {
                    preserveScroll: true,
                    onSuccess: () => {
                        form.reset();
                        close();
                    },
                });
            }}
        >
            <div className="grid gap-4 sm:grid-cols-2">
                <Field label="Kod">
                    <Input
                        value={form.data.code}
                        onChange={(event) =>
                            form.setData('code', event.target.value)
                        }
                    />
                    <InputError message={form.errors.code} />
                </Field>
                <Field label="Nama">
                    <Input
                        value={form.data.name}
                        onChange={(event) =>
                            form.setData('name', event.target.value)
                        }
                    />
                </Field>
                <Field label="Pegawai Dihubungi">
                    <Input
                        value={form.data.contact_person}
                        onChange={(event) =>
                            form.setData('contact_person', event.target.value)
                        }
                    />
                </Field>
                <Field label="E-mel">
                    <Input
                        type="email"
                        value={form.data.email}
                        onChange={(event) =>
                            form.setData('email', event.target.value)
                        }
                    />
                </Field>
                <Field label="Telefon">
                    <Input
                        value={form.data.phone}
                        onChange={(event) =>
                            form.setData('phone', event.target.value)
                        }
                    />
                </Field>
                <Field label="Akreditasi">
                    <Input
                        value={form.data.accreditation}
                        onChange={(event) =>
                            form.setData('accreditation', event.target.value)
                        }
                    />
                </Field>
            </div>
            <Field label="Catatan">
                <textarea
                    className="min-h-20 w-full rounded-md border bg-background px-3 py-2 text-sm"
                    value={form.data.notes}
                    onChange={(event) =>
                        form.setData('notes', event.target.value)
                    }
                />
            </Field>
            <Check
                label="Penyedia aktif"
                checked={form.data.is_active}
                set={(checked) => form.setData('is_active', checked)}
            />
        </FormDialog>
    );
}

function CourseDialog({ providers }: { providers: Provider[] }) {
    const form = useForm({
        training_provider_id: '',
        code: '',
        title: '',
        category: 'technical',
        delivery_method: 'physical',
        description: '',
        learning_objectives: '',
        duration_hours: '8',
        cpd_points: '0',
        default_cost: '0',
        currency: 'MYR',
        certificate_validity_months: '',
        is_mandatory: false,
        is_active: true,
    });

    return (
        <FormDialog
            trigger={
                <Button>
                    <Plus />
                    Kursus
                </Button>
            }
            title="Katalog Kursus"
            description="Template kursus boleh digunakan semula untuk banyak sesi."
            processing={form.processing}
            submit={(event, close) => {
                event.preventDefault();
                form.post('/tetapan-latihan/kursus', {
                    preserveScroll: true,
                    onSuccess: () => {
                        form.reset();
                        close();
                    },
                });
            }}
        >
            <div className="grid gap-4 sm:grid-cols-2">
                <Field label="Kod">
                    <Input
                        value={form.data.code}
                        onChange={(event) =>
                            form.setData('code', event.target.value)
                        }
                    />
                </Field>
                <Field label="Tajuk">
                    <Input
                        value={form.data.title}
                        onChange={(event) =>
                            form.setData('title', event.target.value)
                        }
                    />
                </Field>
                <Field label="Penyedia">
                    <Select
                        value={form.data.training_provider_id || 'none'}
                        onValueChange={(value) =>
                            form.setData(
                                'training_provider_id',
                                value === 'none' ? '' : value,
                            )
                        }
                    >
                        <SelectTrigger>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="none">
                                Dalaman / belum ditetapkan
                            </SelectItem>
                            {providers
                                .filter((item) => item.is_active)
                                .map((item) => (
                                    <SelectItem
                                        key={item.id}
                                        value={String(item.id)}
                                    >
                                        {item.name}
                                    </SelectItem>
                                ))}
                        </SelectContent>
                    </Select>
                </Field>
                <Field label="Kategori">
                    <Input
                        value={form.data.category}
                        onChange={(event) =>
                            form.setData('category', event.target.value)
                        }
                    />
                </Field>
                <Field label="Kaedah">
                    <Select
                        value={form.data.delivery_method}
                        onValueChange={(value) =>
                            form.setData('delivery_method', value)
                        }
                    >
                        <SelectTrigger>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="physical">Bersemuka</SelectItem>
                            <SelectItem value="online">Dalam Talian</SelectItem>
                            <SelectItem value="hybrid">Hibrid</SelectItem>
                            <SelectItem value="self_paced">Kendiri</SelectItem>
                            <SelectItem value="on_the_job">
                                On-the-job
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </Field>
                <Field label="Tempoh (jam)">
                    <Input
                        type="number"
                        min="0"
                        step="0.25"
                        value={form.data.duration_hours}
                        onChange={(event) =>
                            form.setData('duration_hours', event.target.value)
                        }
                    />
                </Field>
                <Field label="Mata CPD">
                    <Input
                        type="number"
                        min="0"
                        step="0.25"
                        value={form.data.cpd_points}
                        onChange={(event) =>
                            form.setData('cpd_points', event.target.value)
                        }
                    />
                </Field>
                <Field label="Kos Lalai (RM)">
                    <Input
                        type="number"
                        min="0"
                        step="0.01"
                        value={form.data.default_cost}
                        onChange={(event) =>
                            form.setData('default_cost', event.target.value)
                        }
                    />
                </Field>
                <Field label="Tempoh Sah Sijil (bulan)">
                    <Input
                        type="number"
                        min="1"
                        value={form.data.certificate_validity_months}
                        onChange={(event) =>
                            form.setData(
                                'certificate_validity_months',
                                event.target.value,
                            )
                        }
                    />
                </Field>
            </div>
            <Field label="Penerangan">
                <textarea
                    className="min-h-20 w-full rounded-md border bg-background px-3 py-2 text-sm"
                    value={form.data.description}
                    onChange={(event) =>
                        form.setData('description', event.target.value)
                    }
                />
            </Field>
            <Field label="Objektif Pembelajaran">
                <textarea
                    className="min-h-20 w-full rounded-md border bg-background px-3 py-2 text-sm"
                    value={form.data.learning_objectives}
                    onChange={(event) =>
                        form.setData('learning_objectives', event.target.value)
                    }
                />
            </Field>
            <div className="flex gap-6">
                <Check
                    label="Latihan wajib"
                    checked={form.data.is_mandatory}
                    set={(checked) => form.setData('is_mandatory', checked)}
                />
                <Check
                    label="Kursus aktif"
                    checked={form.data.is_active}
                    set={(checked) => form.setData('is_active', checked)}
                />
            </div>
        </FormDialog>
    );
}

function SessionDialog({ courses }: { courses: Course[] }) {
    const form = useForm({
        training_course_id: '',
        session_code: '',
        starts_at: '',
        ends_at: '',
        registration_deadline: '',
        venue: '',
        facilitator: '',
        capacity: '20',
        cost_per_participant: '0',
        budget_code: '',
        status: 'draft',
        notes: '',
    });

    return (
        <FormDialog
            trigger={
                <Button>
                    <CalendarPlus />
                    Sesi
                </Button>
            }
            title="Jadualkan Sesi Latihan"
            description="Buka pendaftaran selepas tarikh, kapasiti dan kos disahkan."
            processing={form.processing}
            submit={(event, close) => {
                event.preventDefault();
                form.post('/tetapan-latihan/sesi', {
                    preserveScroll: true,
                    onSuccess: () => {
                        form.reset();
                        close();
                    },
                });
            }}
        >
            <Field label="Kursus">
                <Select
                    value={form.data.training_course_id}
                    onValueChange={(value) =>
                        form.setData('training_course_id', value)
                    }
                >
                    <SelectTrigger>
                        <SelectValue placeholder="Pilih kursus" />
                    </SelectTrigger>
                    <SelectContent>
                        {courses
                            .filter((item) => item.is_active)
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
            </Field>
            <div className="grid gap-4 sm:grid-cols-2">
                <Field label="Kod Sesi">
                    <Input
                        value={form.data.session_code}
                        onChange={(event) =>
                            form.setData('session_code', event.target.value)
                        }
                    />
                </Field>
                <Field label="Status">
                    <Select
                        value={form.data.status}
                        onValueChange={(value) => form.setData('status', value)}
                    >
                        <SelectTrigger>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="draft">Draf</SelectItem>
                            <SelectItem value="open">Dibuka</SelectItem>
                            <SelectItem value="closed">Ditutup</SelectItem>
                        </SelectContent>
                    </Select>
                </Field>
                <Field label="Mula">
                    <Input
                        type="datetime-local"
                        value={form.data.starts_at}
                        onChange={(event) =>
                            form.setData('starts_at', event.target.value)
                        }
                    />
                </Field>
                <Field label="Tamat">
                    <Input
                        type="datetime-local"
                        value={form.data.ends_at}
                        onChange={(event) =>
                            form.setData('ends_at', event.target.value)
                        }
                    />
                </Field>
                <Field label="Tarikh Tutup Pendaftaran">
                    <Input
                        type="date"
                        value={form.data.registration_deadline}
                        onChange={(event) =>
                            form.setData(
                                'registration_deadline',
                                event.target.value,
                            )
                        }
                    />
                </Field>
                <Field label="Kapasiti">
                    <Input
                        type="number"
                        min="1"
                        value={form.data.capacity}
                        onChange={(event) =>
                            form.setData('capacity', event.target.value)
                        }
                    />
                </Field>
                <Field label="Lokasi / Pautan">
                    <Input
                        value={form.data.venue}
                        onChange={(event) =>
                            form.setData('venue', event.target.value)
                        }
                    />
                </Field>
                <Field label="Fasilitator">
                    <Input
                        value={form.data.facilitator}
                        onChange={(event) =>
                            form.setData('facilitator', event.target.value)
                        }
                    />
                </Field>
                <Field label="Kos Seorang (RM)">
                    <Input
                        type="number"
                        min="0"
                        step="0.01"
                        value={form.data.cost_per_participant}
                        onChange={(event) =>
                            form.setData(
                                'cost_per_participant',
                                event.target.value,
                            )
                        }
                    />
                </Field>
                <Field label="Kod Bajet">
                    <Input
                        value={form.data.budget_code}
                        onChange={(event) =>
                            form.setData('budget_code', event.target.value)
                        }
                    />
                </Field>
            </div>
        </FormDialog>
    );
}

function BudgetDialog({
    year,
    departments,
}: {
    year: number;
    departments: Props['departments'];
}) {
    const form = useForm({
        year: String(year),
        department_id: '',
        budget_code: '',
        allocated_amount: '0',
        notes: '',
    });

    return (
        <FormDialog
            trigger={
                <Button>
                    <WalletCards />
                    Bajet
                </Button>
            }
            title="Bajet Latihan Tahunan"
            description="Bajet jabatan mengambil keutamaan berbanding bajet umum."
            processing={form.processing}
            submit={(event, close) => {
                event.preventDefault();
                form.post('/tetapan-latihan/bajet', {
                    preserveScroll: true,
                    onSuccess: () => close(),
                });
            }}
        >
            <div className="grid gap-4 sm:grid-cols-2">
                <Field label="Tahun">
                    <Input
                        type="number"
                        value={form.data.year}
                        onChange={(event) =>
                            form.setData('year', event.target.value)
                        }
                    />
                </Field>
                <Field label="Jabatan">
                    <Select
                        value={form.data.department_id || 'all'}
                        onValueChange={(value) =>
                            form.setData(
                                'department_id',
                                value === 'all' ? '' : value,
                            )
                        }
                    >
                        <SelectTrigger>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Bajet Umum</SelectItem>
                            {departments.map((item) => (
                                <SelectItem
                                    key={item.id}
                                    value={String(item.id)}
                                >
                                    {item.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </Field>
                <Field label="Kod Bajet">
                    <Input
                        value={form.data.budget_code}
                        onChange={(event) =>
                            form.setData('budget_code', event.target.value)
                        }
                    />
                </Field>
                <Field label="Peruntukan (RM)">
                    <Input
                        type="number"
                        min="0"
                        step="0.01"
                        value={form.data.allocated_amount}
                        onChange={(event) =>
                            form.setData('allocated_amount', event.target.value)
                        }
                    />
                </Field>
            </div>
            <Field label="Catatan">
                <textarea
                    className="min-h-20 w-full rounded-md border bg-background px-3 py-2 text-sm"
                    value={form.data.notes}
                    onChange={(event) =>
                        form.setData('notes', event.target.value)
                    }
                />
            </Field>
        </FormDialog>
    );
}

function CompetencyDialog() {
    const form = useForm({
        code: '',
        name: '',
        category: 'core',
        description: '',
        maximum_level: '5',
        level_descriptions: [] as string[],
        is_active: true,
    });

    return (
        <FormDialog
            trigger={
                <Button>
                    <Award />
                    Kompetensi
                </Button>
            }
            title="Kerangka Kompetensi"
            description="Gunakan skala yang konsisten untuk semua pekerja dan jawatan."
            processing={form.processing}
            submit={(event, close) => {
                event.preventDefault();
                form.post('/tetapan-latihan/kompetensi', {
                    preserveScroll: true,
                    onSuccess: () => {
                        form.reset();
                        close();
                    },
                });
            }}
        >
            <div className="grid gap-4 sm:grid-cols-2">
                <Field label="Kod">
                    <Input
                        value={form.data.code}
                        onChange={(event) =>
                            form.setData('code', event.target.value)
                        }
                    />
                </Field>
                <Field label="Nama">
                    <Input
                        value={form.data.name}
                        onChange={(event) =>
                            form.setData('name', event.target.value)
                        }
                    />
                </Field>
                <Field label="Kategori">
                    <Input
                        value={form.data.category}
                        onChange={(event) =>
                            form.setData('category', event.target.value)
                        }
                    />
                </Field>
                <Field label="Tahap Maksimum">
                    <Input
                        type="number"
                        min="1"
                        max="10"
                        value={form.data.maximum_level}
                        onChange={(event) =>
                            form.setData('maximum_level', event.target.value)
                        }
                    />
                </Field>
            </div>
            <Field label="Penerangan">
                <textarea
                    className="min-h-24 w-full rounded-md border bg-background px-3 py-2 text-sm"
                    value={form.data.description}
                    onChange={(event) =>
                        form.setData('description', event.target.value)
                    }
                />
            </Field>
            <Check
                label="Kompetensi aktif"
                checked={form.data.is_active}
                set={(checked) => form.setData('is_active', checked)}
            />
        </FormDialog>
    );
}

function RequirementDialog({
    competencies,
    departments,
    positionNames,
}: Pick<Props, 'competencies' | 'departments' | 'positionNames'>) {
    const form = useForm({
        competency_id: '',
        department_id: '',
        position_name: '',
        required_level: '1',
        is_mandatory: true,
        notes: '',
    });

    return (
        <FormDialog
            trigger={
                <Button variant="outline">
                    <Target />
                    Keperluan Jawatan
                </Button>
            }
            title="Keperluan Kompetensi"
            description="Skop kosong bermaksud semua jabatan atau semua jawatan."
            processing={form.processing}
            submit={(event, close) => {
                event.preventDefault();
                form.post('/tetapan-latihan/keperluan', {
                    preserveScroll: true,
                    onSuccess: () => {
                        form.reset();
                        close();
                    },
                });
            }}
        >
            <Field label="Kompetensi">
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
                        {competencies
                            .filter((item) => item.is_active)
                            .map((item) => (
                                <SelectItem
                                    key={item.id}
                                    value={String(item.id)}
                                >
                                    {item.code} · {item.name}
                                </SelectItem>
                            ))}
                    </SelectContent>
                </Select>
            </Field>
            <div className="grid gap-4 sm:grid-cols-2">
                <Field label="Jabatan">
                    <Select
                        value={form.data.department_id || 'all'}
                        onValueChange={(value) =>
                            form.setData(
                                'department_id',
                                value === 'all' ? '' : value,
                            )
                        }
                    >
                        <SelectTrigger>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Semua jabatan</SelectItem>
                            {departments.map((item) => (
                                <SelectItem
                                    key={item.id}
                                    value={String(item.id)}
                                >
                                    {item.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </Field>
                <Field label="Jawatan">
                    <Select
                        value={form.data.position_name || 'all'}
                        onValueChange={(value) =>
                            form.setData(
                                'position_name',
                                value === 'all' ? '' : value,
                            )
                        }
                    >
                        <SelectTrigger>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Semua jawatan</SelectItem>
                            {positionNames.map((item) => (
                                <SelectItem key={item} value={item}>
                                    {item}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </Field>
                <Field label="Tahap Diperlukan">
                    <Input
                        type="number"
                        min="1"
                        max="10"
                        value={form.data.required_level}
                        onChange={(event) =>
                            form.setData('required_level', event.target.value)
                        }
                    />
                </Field>
            </div>
            <Check
                label="Keperluan wajib"
                checked={form.data.is_mandatory}
                set={(checked) => form.setData('is_mandatory', checked)}
            />
        </FormDialog>
    );
}

function AssignmentDialog({
    departments,
    supervisors,
}: Pick<Props, 'departments' | 'supervisors'>) {
    const form = useForm({
        department_id: '',
        approver_user_id: '',
        is_active: true,
    });

    return (
        <FormDialog
            trigger={
                <Button variant="outline">
                    <ShieldCheck />
                    Penyelia
                </Button>
            }
            title="Penyelia Kelulusan Latihan"
            description="Satu penyelia utama bagi setiap jabatan."
            processing={form.processing}
            submit={(event, close) => {
                event.preventDefault();
                form.post('/tetapan-latihan/penyelia', {
                    preserveScroll: true,
                    onSuccess: () => close(),
                });
            }}
        >
            <Field label="Jabatan">
                <Select
                    value={form.data.department_id}
                    onValueChange={(value) =>
                        form.setData('department_id', value)
                    }
                >
                    <SelectTrigger>
                        <SelectValue placeholder="Pilih jabatan" />
                    </SelectTrigger>
                    <SelectContent>
                        {departments.map((item) => (
                            <SelectItem key={item.id} value={String(item.id)}>
                                {item.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </Field>
            <Field label="Penyelia">
                <Select
                    value={form.data.approver_user_id}
                    onValueChange={(value) =>
                        form.setData('approver_user_id', value)
                    }
                >
                    <SelectTrigger>
                        <SelectValue placeholder="Pilih penyelia" />
                    </SelectTrigger>
                    <SelectContent>
                        {supervisors.map((item) => (
                            <SelectItem key={item.id} value={String(item.id)}>
                                {item.name} · {item.email}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </Field>
            <Check
                label="Penetapan aktif"
                checked={form.data.is_active}
                set={(checked) => form.setData('is_active', checked)}
            />
        </FormDialog>
    );
}

function Field({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="space-y-2">
            <Label>{label}</Label>
            {children}
        </div>
    );
}
function Check({
    label,
    checked,
    set,
}: {
    label: string;
    checked: boolean;
    set: (checked: boolean) => void;
}) {
    return (
        <label className="flex items-center gap-2 text-sm">
            <Checkbox
                checked={checked}
                onCheckedChange={(value) => set(value === true)}
            />
            {label}
        </label>
    );
}

export default function TrainingSettings({
    year,
    providers,
    courses,
    sessions,
    budgets,
    competencies,
    requirements,
    assignments,
    departments,
    positionNames,
    supervisors,
}: Props) {
    return (
        <>
            <Head title="Tetapan Latihan & Kompetensi" />
            <div className="flex h-full flex-1 flex-col gap-5 overflow-x-auto rounded-xl p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Tetapan Latihan & Kompetensi
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Katalog, sesi, bajet, kerangka kompetensi dan aliran
                            kelulusan.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <ProviderDialog />
                        <CourseDialog providers={providers} />
                        <SessionDialog courses={courses} />
                        <BudgetDialog year={year} departments={departments} />
                        <CompetencyDialog />
                        <RequirementDialog
                            competencies={competencies}
                            departments={departments}
                            positionNames={positionNames}
                        />
                        <AssignmentDialog
                            departments={departments}
                            supervisors={supervisors}
                        />
                    </div>
                </div>
                <div className="grid gap-5 xl:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Building2 className="size-5" />
                                Penyedia
                            </CardTitle>
                            <CardDescription>
                                {providers.length} penyedia berdaftar.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            {providers.map((provider) => (
                                <div
                                    key={provider.id}
                                    className="flex items-center justify-between rounded-lg border p-3"
                                >
                                    <div>
                                        <p className="font-medium">
                                            {provider.name}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {provider.code} ·{' '}
                                            {provider.accreditation ??
                                                'Tiada akreditasi'}{' '}
                                            · {provider.courses_count} kursus
                                        </p>
                                    </div>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() =>
                                            router.patch(
                                                `/tetapan-latihan/penyedia/${provider.id}/status`,
                                            )
                                        }
                                    >
                                        {provider.is_active
                                            ? 'Aktif'
                                            : 'Tidak Aktif'}
                                    </Button>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <BookOpen className="size-5" />
                                Katalog Kursus
                            </CardTitle>
                            <CardDescription>
                                {courses.length} kursus boleh digunakan semula.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            {courses.map((course) => (
                                <div
                                    key={course.id}
                                    className="rounded-lg border p-3"
                                >
                                    <div className="flex items-start justify-between">
                                        <div>
                                            <p className="font-medium">
                                                {course.title}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {course.code} ·{' '}
                                                {course.provider?.name ??
                                                    'Dalaman'}{' '}
                                                · {course.duration_hours} jam ·
                                                RM{' '}
                                                {Number(
                                                    course.default_cost,
                                                ).toFixed(2)}
                                            </p>
                                        </div>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() =>
                                                router.patch(
                                                    `/tetapan-latihan/kursus/${course.id}/status`,
                                                )
                                            }
                                        >
                                            {course.is_active
                                                ? 'Aktif'
                                                : 'Tidak Aktif'}
                                        </Button>
                                    </div>
                                    <div className="mt-2 flex gap-2">
                                        <Badge variant="secondary">
                                            {course.category}
                                        </Badge>
                                        {course.is_mandatory && (
                                            <Badge>Wajib</Badge>
                                        )}
                                        <Badge variant="outline">
                                            {course.sessions_count} sesi
                                        </Badge>
                                    </div>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                </div>
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <GraduationCap className="size-5" />
                            Sesi Latihan
                        </CardTitle>
                        <CardDescription>
                            Draf → Dibuka → Ditutup → Selesai. Sesi dengan
                            peserta diluluskan tidak boleh dibatalkan terus.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-3 lg:grid-cols-2">
                        {sessions.map((session) => (
                            <div
                                key={session.id}
                                className="rounded-lg border p-3"
                            >
                                <div className="flex items-start justify-between">
                                    <div>
                                        <p className="font-medium">
                                            {session.course?.title}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {session.session_code} ·{' '}
                                            {new Date(
                                                session.starts_at,
                                            ).toLocaleString('ms-MY')}
                                        </p>
                                    </div>
                                    <Badge variant="outline">
                                        {session.status}
                                    </Badge>
                                </div>
                                <p className="mt-2 text-sm">
                                    {session.venue ?? 'Lokasi belum ditetapkan'}{' '}
                                    · {session.enrolled_count}/
                                    {session.capacity} peserta · RM{' '}
                                    {Number(
                                        session.cost_per_participant,
                                    ).toFixed(2)}
                                </p>
                                <div className="mt-3 flex gap-2">
                                    {session.status === 'draft' && (
                                        <Button
                                            size="sm"
                                            onClick={() =>
                                                router.patch(
                                                    `/tetapan-latihan/sesi/${session.id}/status`,
                                                    { status: 'open' },
                                                )
                                            }
                                        >
                                            Buka
                                        </Button>
                                    )}
                                    {session.status === 'open' && (
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() =>
                                                router.patch(
                                                    `/tetapan-latihan/sesi/${session.id}/status`,
                                                    { status: 'closed' },
                                                )
                                            }
                                        >
                                            Tutup Pendaftaran
                                        </Button>
                                    )}
                                    {session.status === 'closed' && (
                                        <Button
                                            size="sm"
                                            onClick={() =>
                                                router.patch(
                                                    `/tetapan-latihan/sesi/${session.id}/status`,
                                                    { status: 'completed' },
                                                )
                                            }
                                        >
                                            Tandakan Selesai
                                        </Button>
                                    )}
                                </div>
                            </div>
                        ))}
                    </CardContent>
                </Card>
                <div className="grid gap-5 xl:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle>Bajet {year}</CardTitle>
                                    <CardDescription>
                                        Peruntukan, penggunaan dan baki.
                                    </CardDescription>
                                </div>
                                <Input
                                    className="w-28"
                                    type="number"
                                    value={year}
                                    onChange={(event) =>
                                        router.get(
                                            '/tetapan-latihan',
                                            { year: event.target.value },
                                            {
                                                preserveState: true,
                                                replace: true,
                                            },
                                        )
                                    }
                                />
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {budgets.map((budget) => (
                                <div
                                    key={budget.id}
                                    className="rounded-lg border p-3"
                                >
                                    <div className="flex justify-between">
                                        <p className="font-medium">
                                            {budget.department}
                                        </p>
                                        <Badge variant="outline">
                                            {budget.budget_code ?? 'Umum'}
                                        </Badge>
                                    </div>
                                    <div className="mt-2 h-2 overflow-hidden rounded-full bg-muted">
                                        <div
                                            className="h-full bg-emerald-500"
                                            style={{
                                                width: `${budget.allocated > 0 ? Math.min(100, (budget.used / budget.allocated) * 100) : 0}%`,
                                            }}
                                        />
                                    </div>
                                    <div className="mt-2 flex justify-between text-xs">
                                        <span>
                                            RM {budget.used.toFixed(2)}{' '}
                                            digunakan
                                        </span>
                                        <span>
                                            RM {budget.available.toFixed(2)}{' '}
                                            baki
                                        </span>
                                    </div>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Penyelia Kelulusan</CardTitle>
                            <CardDescription>
                                Permohonan tanpa penetapan terus dihantar ke HR.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            {assignments.map((assignment) => (
                                <div
                                    key={assignment.id}
                                    className="flex items-center justify-between rounded-lg border p-3"
                                >
                                    <div>
                                        <p className="font-medium">
                                            {assignment.department}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {assignment.approver}
                                        </p>
                                    </div>
                                    <Badge
                                        variant={
                                            assignment.is_active
                                                ? 'outline'
                                                : 'secondary'
                                        }
                                    >
                                        {assignment.is_active
                                            ? 'Aktif'
                                            : 'Tidak Aktif'}
                                    </Badge>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                </div>
                <div className="grid gap-5 xl:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Kerangka Kompetensi</CardTitle>
                            <CardDescription>
                                Kompetensi teras, teknikal, kepimpinan dan
                                fungsi.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            {competencies.map((competency) => (
                                <div
                                    key={competency.id}
                                    className="flex items-center justify-between rounded-lg border p-3"
                                >
                                    <div>
                                        <p className="font-medium">
                                            {competency.name}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {competency.code} ·{' '}
                                            {competency.category} · Skala 1–
                                            {competency.maximum_level} ·{' '}
                                            {competency.requirements_count}{' '}
                                            keperluan
                                        </p>
                                    </div>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() =>
                                            router.patch(
                                                `/tetapan-latihan/kompetensi/${competency.id}/status`,
                                            )
                                        }
                                    >
                                        {competency.is_active
                                            ? 'Aktif'
                                            : 'Tidak Aktif'}
                                    </Button>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Keperluan Jawatan</CardTitle>
                            <CardDescription>
                                Tahap sasaran mengikut jabatan dan jawatan.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            {requirements.map((requirement) => (
                                <div
                                    key={requirement.id}
                                    className="flex items-start justify-between rounded-lg border p-3"
                                >
                                    <div>
                                        <p className="font-medium">
                                            {requirement.competency} · Tahap{' '}
                                            {requirement.required_level}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {requirement.department} ·{' '}
                                            {requirement.position_name ??
                                                'Semua jawatan'}
                                            {requirement.is_mandatory
                                                ? ' · Wajib'
                                                : ''}
                                        </p>
                                    </div>
                                    <Button
                                        size="icon"
                                        variant="ghost"
                                        onClick={() =>
                                            window.confirm(
                                                'Padam keperluan kompetensi ini?',
                                            ) &&
                                            router.delete(
                                                `/tetapan-latihan/keperluan/${requirement.id}`,
                                            )
                                        }
                                    >
                                        <Trash2 />
                                    </Button>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}
