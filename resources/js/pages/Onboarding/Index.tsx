import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    CheckCircle2,
    Copy,
    Download,
    KeyRound,
    Link2,
    Pencil,
    Play,
    RotateCcw,
    Search,
    ShieldCheck,
    Unlink,
    UserPlus,
    UserRoundCheck,
    XCircle,
} from 'lucide-react';
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

type OnboardingStatus = 'pending' | 'active' | 'completed' | 'cancelled';
type TaskStatus = 'pending' | 'in_progress' | 'completed' | 'waived';
type OnboardingTask = {
    id: number;
    title: string;
    description: string | null;
    category: string;
    assignee_role: string;
    assignee_user_id: number | null;
    assignee: string | null;
    due_date: string;
    is_required: boolean;
    status: TaskStatus;
    completion_notes: string | null;
};
type OnboardingCase = {
    id: number;
    candidate: {
        id: number;
        candidate_number: string;
        name: string;
        email: string;
        phone: string;
        identity_number: string | null;
        position: string;
        requisition_code: string;
    };
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
    offer: {
        position_name: string;
        department_id: number | null;
        employment_type: string;
        salary: number;
        probation_months: number;
        start_date: string;
        status: string;
    } | null;
    registration_defaults: {
        identity_number: string;
        employee_number: string;
        official_email: string;
    };
    manager_user_id: number | null;
    manager: string | null;
    buddy_user_id: number | null;
    buddy: string | null;
    start_date: string;
    status: OnboardingStatus;
    notes: string | null;
    progress: number;
    overdue_tasks: number;
    tasks: OnboardingTask[];
};
type Props = {
    cases: {
        data: OnboardingCase[];
        from: number | null;
        to: number | null;
        total: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
    statistics: {
        pending: number;
        active: number;
        completed: number;
        overdue_tasks: number;
    };
    users: { id: number; name: string; email: string }[];
    employeeUsers: { id: number; name: string; email: string }[];
    officeLocations: { id: number; name: string; address: string | null }[];
    legacyEmployees: {
        id: number;
        employee_number: string | null;
        name: string;
    }[];
    filters: { search: string; status: string };
    newEmployeeCredentials: {
        employee_number: string;
        name: string;
        email: string;
        temporary_password: string;
        activation_date: string;
        account_status: string;
    } | null;
    permissions: { can_manage: boolean; can_approve: boolean };
};

const statusLabel: Record<string, string> = {
    pending: 'Belum Bermula',
    active: 'Aktif',
    completed: 'Selesai',
    cancelled: 'Dibatalkan',
    in_progress: 'Dalam Tindakan',
    waived: 'Dikecualikan',
};
const categoryLabel: Record<string, string> = {
    hr: 'HR',
    supervisor: 'Penyelia',
    it: 'ICT',
    finance: 'Kewangan',
    employee: 'Pekerja',
    facilities: 'Fasiliti',
    other: 'Lain-lain',
};

function CaseSettingsDialog({
    item,
    users,
}: {
    item: OnboardingCase;
    users: Props['users'];
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        manager_user_id: item.manager_user_id
            ? String(item.manager_user_id)
            : '',
        buddy_user_id: item.buddy_user_id ? String(item.buddy_user_id) : '',
        notes: item.notes ?? '',
    });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.put(`/onboarding/${item.id}`, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    <Pencil />
                    Pasukan
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Pasukan Onboarding</DialogTitle>
                    <DialogDescription>
                        Tetapkan pengurus, buddy dan catatan dalaman.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    {[
                        ['manager_user_id', 'Pengurus / Penyelia'],
                        ['buddy_user_id', 'Buddy'],
                    ].map(([field, label]) => (
                        <div className="space-y-2" key={field}>
                            <Label>{label}</Label>
                            <Select
                                value={
                                    form.data[
                                        field as
                                            'manager_user_id' | 'buddy_user_id'
                                    ] || 'none'
                                }
                                onValueChange={(value) =>
                                    form.setData(
                                        field as
                                            'manager_user_id' | 'buddy_user_id',
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
                    ))}
                    <div className="space-y-2">
                        <Label>Catatan</Label>
                        <textarea
                            value={form.data.notes}
                            onChange={(event) =>
                                form.setData('notes', event.target.value)
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
                            <UserRoundCheck />
                            Simpan
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function LinkEmployeeDialog({
    item,
    employees,
    employeeUsers,
    officeLocations,
}: {
    item: OnboardingCase;
    employees: Props['legacyEmployees'];
    employeeUsers: Props['employeeUsers'];
    officeLocations: Props['officeLocations'];
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        legacy_employee_id: item.legacy_employee_id
            ? String(item.legacy_employee_id)
            : '',
        employee_user_id: item.employee_user_id
            ? String(item.employee_user_id)
            : '',
        office_location_id: '',
    });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.put(`/onboarding/${item.id}/paut-pekerja`, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    <Link2 />
                    Paut Pekerja Sedia Ada
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Pautkan Rekod Pekerja</DialogTitle>
                    <DialogDescription>
                        Pilih pekerja sedia ada daripada db_spp, akaun Employee
                        dan lokasi pejabat. Modul tidak menulis ke db_spp.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-2">
                        <Label>Rekod Pekerja db_spp</Label>
                        <Select
                            value={form.data.legacy_employee_id || undefined}
                            onValueChange={(value) =>
                                form.setData('legacy_employee_id', value)
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
                                        {employee.employee_number ??
                                            `ID ${employee.id}`}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.legacy_employee_id} />
                    </div>
                    <div className="space-y-2">
                        <Label>Akaun Employee</Label>
                        <Select
                            value={form.data.employee_user_id || undefined}
                            onValueChange={(value) =>
                                form.setData('employee_user_id', value)
                            }
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Pilih akaun" />
                            </SelectTrigger>
                            <SelectContent>
                                {employeeUsers.map((user) => (
                                    <SelectItem
                                        key={user.id}
                                        value={String(user.id)}
                                    >
                                        {user.name} · {user.email}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="space-y-2">
                        <Label>Lokasi Pejabat</Label>
                        <Select
                            value={form.data.office_location_id || undefined}
                            onValueChange={(value) =>
                                form.setData('office_location_id', value)
                            }
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Pilih lokasi" />
                            </SelectTrigger>
                            <SelectContent>
                                {officeLocations.map((office) => (
                                    <SelectItem
                                        key={office.id}
                                        value={String(office.id)}
                                    >
                                        {office.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Tutup
                            </Button>
                        </DialogClose>
                        <Button disabled={form.processing}>
                            <Link2 />
                            Pautkan
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function UnlinkEmployeeDialog({ item }: { item: OnboardingCase }) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        reason: '',
        deactivate_employee_link: false,
        confirmed: false,
    });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.delete(`/onboarding/${item.id}/paut-pekerja`, {
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
                <Button size="sm" variant="destructive">
                    <Unlink />
                    Batalkan Pautan Salah
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Batalkan Pautan Pekerja</DialogTitle>
                    <DialogDescription>
                        Gunakan tindakan ini apabila calon telah dipautkan
                        kepada rekod pekerja atau akaun Employee yang salah.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-3 rounded-lg border bg-muted/30 p-4 text-sm sm:grid-cols-2">
                        <div>
                            <p className="text-xs text-muted-foreground">
                                Calon
                            </p>
                            <p className="font-medium">{item.candidate.name}</p>
                        </div>
                        <div>
                            <p className="text-xs text-muted-foreground">
                                Pautan semasa
                            </p>
                            <p className="font-medium">
                                {item.employee_user ??
                                    'Akaun tidak dikenal pasti'}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                {item.legacy_employee_id
                                    ? `db_spp ID: ${item.legacy_employee_id}`
                                    : 'Tiada ID db_spp'}
                            </p>
                        </div>
                    </div>

                    <div className="rounded-lg border border-amber-500/30 bg-amber-500/5 p-4 text-sm">
                        <p className="font-medium">Kesan pembatalan</p>
                        <ul className="mt-2 list-disc space-y-1 pl-5 text-muted-foreground">
                            <li>
                                Pautan calon kepada rekod dan akaun semasa akan
                                dikosongkan.
                            </li>
                            <li>
                                Tugasan kategori pekerja akan ditetapkan semula
                                kepada Belum Bermula dan tiada pemilik.
                            </li>
                            <li>
                                Rekod asal dalam db_spp dan akaun pengguna tidak
                                akan dipadam.
                            </li>
                        </ul>
                    </div>

                    <div className="space-y-2">
                        <Label>Sebab Pembatalan</Label>
                        <textarea
                            value={form.data.reason}
                            onChange={(event) =>
                                form.setData('reason', event.target.value)
                            }
                            className="min-h-24 w-full rounded-md border bg-background px-3 py-2 text-sm"
                            placeholder="Contoh: Calon tersalah dipautkan kepada rekod DUMMY DATA."
                        />
                        <InputError message={form.errors.reason} />
                    </div>

                    <label className="flex cursor-pointer items-start gap-3 rounded-lg border p-3">
                        <input
                            type="checkbox"
                            checked={form.data.deactivate_employee_link}
                            onChange={(event) =>
                                form.setData(
                                    'deactivate_employee_link',
                                    event.target.checked,
                                )
                            }
                            className="mt-1 size-4"
                        />
                        <span className="text-sm">
                            <span className="font-medium">
                                Nyahaktifkan juga pautan akaun Employee dengan
                                rekod db_spp ini
                            </span>
                            <span className="mt-1 block text-muted-foreground">
                                Tandakan hanya jika pautan akaun itu juga salah.
                                Biarkan kosong jika akaun tersebut masih milik
                                pekerja sedia ada.
                            </span>
                        </span>
                    </label>
                    <InputError
                        message={form.errors.deactivate_employee_link}
                    />

                    <label className="flex cursor-pointer items-start gap-3 rounded-lg border p-3">
                        <input
                            type="checkbox"
                            checked={form.data.confirmed}
                            onChange={(event) =>
                                form.setData('confirmed', event.target.checked)
                            }
                            className="mt-1 size-4"
                        />
                        <span className="text-sm">
                            Saya mengesahkan pautan pekerja bagi calon ini
                            adalah salah dan perlu dibatalkan.
                        </span>
                    </label>
                    <InputError message={form.errors.confirmed} />
                    <InputError
                        message={(form.errors as Record<string, string>).unlink}
                    />

                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Tutup
                            </Button>
                        </DialogClose>
                        <Button
                            variant="destructive"
                            disabled={form.processing || !form.data.confirmed}
                        >
                            <Unlink />
                            {form.processing
                                ? 'Sedang Membatalkan...'
                                : 'Batalkan Pautan'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function RegisterEmployeeDialog({
    item,
    users,
    officeLocations,
}: {
    item: OnboardingCase;
    users: Props['users'];
    officeLocations: Props['officeLocations'];
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        identity_number: item.registration_defaults.identity_number,
        employee_number: item.registration_defaults.employee_number,
        official_email: item.registration_defaults.official_email,
        office_location_id: '',
        manager_user_id: item.manager_user_id
            ? String(item.manager_user_id)
            : '',
        confirmed: false,
    });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(`/onboarding/${item.id}/daftar-pekerja`, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };
    const money = new Intl.NumberFormat('ms-MY', {
        style: 'currency',
        currency: 'MYR',
    }).format(item.offer?.salary ?? 0);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm">
                    <UserPlus />
                    Sahkan & Daftar sebagai Pekerja
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[92vh] overflow-y-auto sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>Sahkan & Daftar sebagai Pekerja</DialogTitle>
                    <DialogDescription>
                        Maklumat calon dan tawaran telah diisi secara automatik.
                        Semak butiran di bawah sebelum mencipta rekod pekerja
                        dan akaun Employee.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-5">
                    <div className="grid gap-3 rounded-lg border bg-muted/30 p-4 sm:grid-cols-2">
                        {[
                            ['Nama', item.candidate.name],
                            ['Jawatan', item.offer?.position_name],
                            ['Jenis Pekerjaan', item.offer?.employment_type],
                            ['Gaji Bulanan', money],
                            ['Tarikh Mula', item.offer?.start_date],
                            [
                                'Tempoh Percubaan',
                                `${item.offer?.probation_months ?? 0} bulan`,
                            ],
                        ].map(([label, value]) => (
                            <div key={label}>
                                <p className="text-xs text-muted-foreground">
                                    {label}
                                </p>
                                <p className="font-medium">{value || '—'}</p>
                            </div>
                        ))}
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-2">
                            <Label>Nombor Pengenalan</Label>
                            <Input
                                value={form.data.identity_number}
                                onChange={(event) =>
                                    form.setData(
                                        'identity_number',
                                        event.target.value,
                                    )
                                }
                                placeholder="No. kad pengenalan / pasport"
                            />
                            <InputError message={form.errors.identity_number} />
                        </div>
                        <div className="space-y-2">
                            <Label>Nombor Pekerja</Label>
                            <Input
                                value={form.data.employee_number}
                                onChange={(event) =>
                                    form.setData(
                                        'employee_number',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError message={form.errors.employee_number} />
                        </div>
                        <div className="space-y-2 sm:col-span-2">
                            <Label>E-mel Log Masuk Rasmi</Label>
                            <Input
                                type="email"
                                value={form.data.official_email}
                                onChange={(event) =>
                                    form.setData(
                                        'official_email',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError message={form.errors.official_email} />
                        </div>
                        <div className="space-y-2">
                            <Label>Lokasi Pejabat</Label>
                            <Select
                                value={
                                    form.data.office_location_id || undefined
                                }
                                onValueChange={(value) =>
                                    form.setData('office_location_id', value)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Pilih lokasi" />
                                </SelectTrigger>
                                <SelectContent>
                                    {officeLocations.map((office) => (
                                        <SelectItem
                                            key={office.id}
                                            value={String(office.id)}
                                        >
                                            {office.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError
                                message={form.errors.office_location_id}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label>Penyelia</Label>
                            <Select
                                value={form.data.manager_user_id || 'none'}
                                onValueChange={(value) =>
                                    form.setData(
                                        'manager_user_id',
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

                    <div className="rounded-lg border border-blue-500/30 bg-blue-500/5 p-4 text-sm">
                        <div className="flex gap-3">
                            <ShieldCheck className="mt-0.5 size-5 shrink-0 text-blue-600" />
                            <div>
                                <p className="font-medium">
                                    Kawalan pendaftaran automatik
                                </p>
                                <p className="mt-1 text-muted-foreground">
                                    Sistem akan menyemak rekod pendua, mencipta
                                    rekod pekerja dan akaun Employee, memautkan
                                    lokasi serta tugasan onboarding, kemudian
                                    mengaktifkan akaun pada {item.start_date}.
                                    Tiada data akan ditulis ke db_spp.
                                </p>
                            </div>
                        </div>
                    </div>

                    <label className="flex cursor-pointer items-start gap-3 rounded-lg border p-3">
                        <input
                            type="checkbox"
                            checked={form.data.confirmed}
                            onChange={(event) =>
                                form.setData('confirmed', event.target.checked)
                            }
                            className="mt-1 size-4"
                        />
                        <span className="text-sm">
                            Saya mengesahkan maklumat calon, tawaran, tarikh
                            mula, lokasi dan penyelia telah disemak.
                        </span>
                    </label>
                    <InputError message={form.errors.confirmed} />
                    <InputError
                        message={
                            (form.errors as Record<string, string>).registration
                        }
                    />

                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Batal
                            </Button>
                        </DialogClose>
                        <Button
                            disabled={form.processing || !form.data.confirmed}
                        >
                            <UserPlus />
                            {form.processing
                                ? 'Sedang Mendaftar...'
                                : 'Sahkan & Daftar'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function EmployeeCredentialsDialog({
    credentials,
}: {
    credentials: NonNullable<Props['newEmployeeCredentials']>;
}) {
    const [open, setOpen] = useState(true);
    const copyCredentials = async () => {
        await navigator.clipboard.writeText(
            `Nama: ${credentials.name}\nNo. pekerja: ${credentials.employee_number}\nE-mel: ${credentials.email}\nKata laluan sementara: ${credentials.temporary_password}\nTarikh aktif: ${credentials.activation_date}`,
        );
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Pendaftaran Pekerja Berjaya</DialogTitle>
                    <DialogDescription>
                        Simpan atau serahkan kelayakan sementara ini melalui
                        saluran yang selamat. Kata laluan tidak akan dipaparkan
                        semula selepas dialog ditutup.
                    </DialogDescription>
                </DialogHeader>
                <div className="space-y-3 rounded-lg border bg-muted/30 p-4 text-sm">
                    {[
                        ['Nama', credentials.name],
                        ['No. Pekerja', credentials.employee_number],
                        ['E-mel', credentials.email],
                        [
                            'Kata Laluan Sementara',
                            credentials.temporary_password,
                        ],
                        ['Tarikh Aktif', credentials.activation_date],
                        [
                            'Status Akaun',
                            credentials.account_status === 'active'
                                ? 'Aktif'
                                : 'Menunggu Tarikh Mula',
                        ],
                    ].map(([label, value]) => (
                        <div
                            key={label}
                            className="grid grid-cols-[9rem_1fr] gap-3"
                        >
                            <span className="text-muted-foreground">
                                {label}
                            </span>
                            <strong className="break-all">{value}</strong>
                        </div>
                    ))}
                </div>
                <div className="flex items-start gap-3 rounded-lg border border-amber-500/30 bg-amber-500/5 p-3 text-sm">
                    <KeyRound className="mt-0.5 size-5 shrink-0 text-amber-600" />
                    Pekerja wajib menukar kata laluan sementara pada log masuk
                    pertama.
                </div>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={copyCredentials}
                    >
                        <Copy />
                        Salin Kelayakan
                    </Button>
                    <Button type="button" onClick={() => setOpen(false)}>
                        Selesai
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function TaskDialog({
    item,
    task,
    users,
}: {
    item: OnboardingCase;
    task: OnboardingTask;
    users: Props['users'];
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        status: task.status,
        assignee_user_id: task.assignee_user_id
            ? String(task.assignee_user_id)
            : '',
        completion_notes: task.completion_notes ?? '',
    });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.put(`/onboarding/${item.id}/tugasan/${task.id}`, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    <Pencil />
                    Kemas Kini
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{task.title}</DialogTitle>
                    <DialogDescription>
                        Tetapkan pemilik, status dan bukti catatan penyelesaian.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-2">
                        <Label>Status</Label>
                        <Select
                            value={form.data.status}
                            onValueChange={(value) =>
                                form.setData(
                                    'status',
                                    value as typeof form.data.status,
                                )
                            }
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {[
                                    ['pending', 'Belum Bermula'],
                                    ['in_progress', 'Dalam Tindakan'],
                                    ['completed', 'Selesai'],
                                    ['waived', 'Dikecualikan'],
                                ].map(([value, label]) => (
                                    <SelectItem key={value} value={value}>
                                        {label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="space-y-2">
                        <Label>Pemilik Tugasan</Label>
                        <Select
                            value={form.data.assignee_user_id || 'none'}
                            onValueChange={(value) =>
                                form.setData(
                                    'assignee_user_id',
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
                        <Label>Catatan Penyelesaian</Label>
                        <textarea
                            value={form.data.completion_notes}
                            onChange={(event) =>
                                form.setData(
                                    'completion_notes',
                                    event.target.value,
                                )
                            }
                            className="min-h-24 w-full rounded-md border bg-background px-3 py-2 text-sm"
                        />
                        <InputError message={form.errors.completion_notes} />
                    </div>
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

function CaseActions({
    item,
    canManage,
    canApprove,
}: {
    item: OnboardingCase;
    canManage: boolean;
    canApprove: boolean;
}) {
    const run = (action: string) => {
        const notes = ['cancel', 'reopen'].includes(action)
            ? window.prompt('Catatan:')
            : null;

        if (['cancel', 'reopen'].includes(action) && !notes) {
            return;
        }

        router.patch(
            `/onboarding/${item.id}/status`,
            { action, notes },
            { preserveScroll: true },
        );
    };

    return (
        <div className="flex flex-wrap gap-1">
            {canManage && item.status === 'pending' && (
                <Button size="sm" onClick={() => run('start')}>
                    <Play />
                    Mula
                </Button>
            )}
            {canApprove && item.status === 'active' && (
                <Button size="sm" onClick={() => run('complete')}>
                    <CheckCircle2 />
                    Lengkapkan
                </Button>
            )}
            {canManage && ['pending', 'active'].includes(item.status) && (
                <Button
                    size="sm"
                    variant="outline"
                    onClick={() => run('cancel')}
                >
                    <XCircle />
                    Batal
                </Button>
            )}
            {canManage && item.status === 'cancelled' && (
                <Button
                    size="sm"
                    variant="outline"
                    onClick={() => run('reopen')}
                >
                    <RotateCcw />
                    Buka Semula
                </Button>
            )}
        </div>
    );
}

export default function OnboardingIndex({
    cases,
    statistics,
    users,
    employeeUsers,
    officeLocations,
    legacyEmployees,
    filters,
    newEmployeeCredentials,
    permissions,
}: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    useEffect(() => {
        if (search === (filters.search ?? '')) {
            return;
        }

        const timer = window.setTimeout(() => {
            router.get(
                '/onboarding',
                { search, status: filters.status || undefined },
                { preserveState: true, replace: true },
            );
        }, 350);

        return () => window.clearTimeout(timer);
    }, [filters.search, filters.status, search]);

    return (
        <>
            <Head title="Pusat Onboarding" />
            {newEmployeeCredentials && (
                <EmployeeCredentialsDialog
                    credentials={newEmployeeCredentials}
                />
            )}
            <div className="space-y-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            Pusat Onboarding
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Checklist pra-lapor diri, pemilik tugasan dan
                            pengaktifan pekerja.
                        </p>
                    </div>
                    <Button variant="outline" asChild>
                        <a href="/onboarding/laporan.csv">
                            <Download />
                            Eksport CSV
                        </a>
                    </Button>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {[
                        ['Belum Bermula', statistics.pending, 'text-amber-600'],
                        ['Aktif', statistics.active, 'text-sky-600'],
                        ['Selesai', statistics.completed, 'text-emerald-600'],
                        [
                            'Tugasan Lewat',
                            statistics.overdue_tasks,
                            'text-red-600',
                        ],
                    ].map(([label, value, color]) => (
                        <Card key={label as string}>
                            <CardContent className="p-5">
                                <p
                                    className={`text-3xl font-semibold ${color as string}`}
                                >
                                    {value as number}
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    {label as string}
                                </p>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <Card>
                    <CardContent className="grid gap-3 p-4 sm:grid-cols-2">
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
                            value={filters.status || 'all'}
                            onValueChange={(value) =>
                                router.get(
                                    '/onboarding',
                                    {
                                        search: search || undefined,
                                        status:
                                            value === 'all' ? undefined : value,
                                    },
                                    { preserveState: true, replace: true },
                                )
                            }
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    Semua status
                                </SelectItem>
                                {(
                                    [
                                        'pending',
                                        'active',
                                        'completed',
                                        'cancelled',
                                    ] as OnboardingStatus[]
                                ).map((status) => (
                                    <SelectItem key={status} value={status}>
                                        {statusLabel[status]}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </CardContent>
                </Card>

                {cases.data.length === 0 ? (
                    <Card>
                        <CardContent className="py-16 text-center text-muted-foreground">
                            Belum ada kes onboarding sepadan.
                        </CardContent>
                    </Card>
                ) : (
                    <div className="space-y-5">
                        {cases.data.map((item) => (
                            <Card key={item.id}>
                                <CardHeader>
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <div className="flex flex-wrap items-center gap-2">
                                                <CardTitle>
                                                    {item.candidate.name}
                                                </CardTitle>
                                                <Badge variant="outline">
                                                    {statusLabel[item.status]}
                                                </Badge>
                                                {item.overdue_tasks > 0 && (
                                                    <Badge variant="destructive">
                                                        {item.overdue_tasks}{' '}
                                                        lewat
                                                    </Badge>
                                                )}
                                            </div>
                                            <CardDescription>
                                                {
                                                    item.candidate
                                                        .candidate_number
                                                }{' '}
                                                · {item.candidate.position} ·
                                                Mula {item.start_date}
                                            </CardDescription>
                                        </div>
                                        <div className="flex flex-wrap gap-2">
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                asChild
                                            >
                                                <Link
                                                    href={`/pengambilan/calon/${item.candidate.id}`}
                                                >
                                                    Profil Calon
                                                </Link>
                                            </Button>
                                            {(permissions.can_manage ||
                                                permissions.can_approve) && (
                                                <>
                                                    {permissions.can_manage && (
                                                        <CaseSettingsDialog
                                                            item={item}
                                                            users={users}
                                                        />
                                                    )}
                                                    {!item.employee_record_id &&
                                                        !item.legacy_employee_id &&
                                                        !item.employee_user_id &&
                                                        item.offer?.status ===
                                                            'accepted' &&
                                                        permissions.can_approve && (
                                                            <RegisterEmployeeDialog
                                                                item={item}
                                                                users={users}
                                                                officeLocations={
                                                                    officeLocations
                                                                }
                                                            />
                                                        )}
                                                    {!item.employee_record_id &&
                                                        !item.legacy_employee_id &&
                                                        !item.employee_user_id &&
                                                        permissions.can_manage && (
                                                            <LinkEmployeeDialog
                                                                item={item}
                                                                employees={
                                                                    legacyEmployees
                                                                }
                                                                employeeUsers={
                                                                    employeeUsers
                                                                }
                                                                officeLocations={
                                                                    officeLocations
                                                                }
                                                            />
                                                        )}
                                                    {!item.employee_record_id &&
                                                        (item.legacy_employee_id ||
                                                            item.employee_user_id) &&
                                                        [
                                                            'pending',
                                                            'active',
                                                        ].includes(
                                                            item.status,
                                                        ) &&
                                                        permissions.can_manage && (
                                                            <UnlinkEmployeeDialog
                                                                item={item}
                                                            />
                                                        )}
                                                    <CaseActions
                                                        item={item}
                                                        canManage={
                                                            permissions.can_manage
                                                        }
                                                        canApprove={
                                                            permissions.can_approve
                                                        }
                                                    />
                                                </>
                                            )}
                                        </div>
                                    </div>
                                </CardHeader>
                                <CardContent className="space-y-5">
                                    <div className="grid gap-4 md:grid-cols-[1fr_12rem]">
                                        <div>
                                            <div className="mb-2 flex justify-between text-sm">
                                                <span>
                                                    Kemajuan Checklist ·{' '}
                                                    {item.template ??
                                                        'Tanpa template'}
                                                </span>
                                                <strong>
                                                    {item.progress}%
                                                </strong>
                                            </div>
                                            <div className="h-2 overflow-hidden rounded-full bg-muted">
                                                <div
                                                    className="h-full bg-emerald-500"
                                                    style={{
                                                        width: `${item.progress}%`,
                                                    }}
                                                />
                                            </div>
                                        </div>
                                        <div className="rounded-lg border p-3 text-sm">
                                            <p className="text-muted-foreground">
                                                Rekod pekerja
                                            </p>
                                            <p className="font-medium">
                                                {item.employee_record
                                                    ?.employee_number ??
                                                    item.employee_user ??
                                                    'Belum didaftarkan'}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {item.employee_record
                                                    ? `${item.employee_record.status === 'active' ? 'Aktif' : 'Aktif mulai'} ${item.employee_record.activation_date} · ${item.employee_record.office ?? 'Lokasi belum ditetapkan'}`
                                                    : item.legacy_employee_id
                                                      ? `db_spp ID: ${item.legacy_employee_id}`
                                                      : 'Menunggu pengesahan HR'}
                                            </p>
                                        </div>
                                    </div>

                                    <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                                        {item.tasks.map((task) => {
                                            const overdue =
                                                ![
                                                    'completed',
                                                    'waived',
                                                ].includes(task.status) &&
                                                new Date(
                                                    `${task.due_date}T23:59:59`,
                                                ) < new Date();

                                            return (
                                                <div
                                                    key={task.id}
                                                    className={`rounded-lg border p-3 ${
                                                        overdue
                                                            ? 'border-red-500/40 bg-red-500/5'
                                                            : ''
                                                    }`}
                                                >
                                                    <div className="flex items-start justify-between gap-2">
                                                        <div>
                                                            <p className="font-medium">
                                                                {task.title}
                                                            </p>
                                                            <p className="text-xs text-muted-foreground">
                                                                {categoryLabel[
                                                                    task
                                                                        .category
                                                                ] ??
                                                                    task.category}{' '}
                                                                ·{' '}
                                                                {task.due_date}
                                                            </p>
                                                        </div>
                                                        <Badge variant="outline">
                                                            {
                                                                statusLabel[
                                                                    task.status
                                                                ]
                                                            }
                                                        </Badge>
                                                    </div>
                                                    <p className="mt-2 line-clamp-2 text-sm text-muted-foreground">
                                                        {task.description ||
                                                            'Tiada penerangan.'}
                                                    </p>
                                                    <div className="mt-3 flex items-center justify-between gap-2">
                                                        <span className="truncate text-xs">
                                                            {task.assignee ??
                                                                task.assignee_role}
                                                        </span>
                                                        {permissions.can_manage &&
                                                            item.status !==
                                                                'completed' && (
                                                                <TaskDialog
                                                                    item={item}
                                                                    task={task}
                                                                    users={
                                                                        users
                                                                    }
                                                                />
                                                            )}
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}

                <div className="flex flex-wrap items-center justify-between gap-2">
                    <p className="text-xs text-muted-foreground">
                        {cases.from ?? 0}–{cases.to ?? 0} daripada {cases.total}
                    </p>
                    <div className="flex flex-wrap gap-1">
                        {cases.links.map((link, index) =>
                            link.url ? (
                                <Button
                                    key={index}
                                    size="sm"
                                    variant={
                                        link.active ? 'default' : 'outline'
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
            </div>
        </>
    );
}
