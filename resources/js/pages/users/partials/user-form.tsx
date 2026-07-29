import { Link, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    Database,
    Save,
    ShieldCheck,
    UserRound,
} from 'lucide-react';
import type { FormEvent } from 'react';
import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
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
import type { UserRole } from '@/types';

export type SystemUserFormData = {
    name: string;
    email: string;
    roles: UserRole[];
    employee_id: string;
    office_location_id: string;
    password: string;
    password_confirmation: string;
};

type RoleOption = {
    value: UserRole;
    label: string;
    description: string;
};

type EmployeeOption = {
    id: number;
    employee_id: string | null;
    name: string | null;
    email: string | null;
};

type OfficeOption = {
    id: number;
    name: string;
    radius_meters: number;
};

export type SystemUserFormOptions = {
    roles: RoleOption[];
    employees: EmployeeOption[];
    offices: OfficeOption[];
};

type UserFormProps = {
    mode: 'create' | 'edit';
    initialValues: SystemUserFormData;
    options: SystemUserFormOptions;
    userId?: number;
    protectSuperAdmin?: boolean;
};

export default function UserForm({
    mode,
    initialValues,
    options,
    userId,
    protectSuperAdmin = false,
}: UserFormProps) {
    const { data, setData, post, put, processing, errors, transform } =
        useForm<SystemUserFormData>(initialValues);
    const selectedRoles = options.roles.filter((role) =>
        data.roles.includes(role.value),
    );
    const isEmployee = data.roles.includes('employee');

    const toggleRole = (role: UserRole, checked: boolean) => {
        if (protectSuperAdmin && role === 'super_admin' && !checked) {
            return;
        }

        setData(
            'roles',
            checked
                ? [...new Set([...data.roles, role])]
                : data.roles.filter((value) => value !== role),
        );
    };

    const selectEmployee = (value: string) => {
        const employee = options.employees.find(
            (option) => String(option.id) === value,
        );

        setData({
            ...data,
            employee_id: value,
            name: employee?.name || data.name,
            email: employee?.email || data.email,
        });
    };

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        transform((formData) => ({
            ...formData,
            employee_id: isEmployee ? formData.employee_id : '',
            office_location_id: isEmployee ? formData.office_location_id : '',
        }));

        if (mode === 'create') {
            post('/pengguna');

            return;
        }

        put(`/pengguna/${userId}`);
    };

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div className="space-y-2">
                    <HeadingSmall
                        title={
                            mode === 'create'
                                ? 'Tambah Pengguna Sistem'
                                : 'Kemas Kini Pengguna'
                        }
                        description={
                            mode === 'create'
                                ? 'Daftarkan pekerja atau pengguna pentadbiran dengan role yang sesuai.'
                                : 'Kemas kini identiti, role, pautan pekerja atau kata laluan pengguna.'
                        }
                    />
                    <Badge variant="secondary" className="gap-1.5 font-normal">
                        <ShieldCheck className="size-3.5" />
                        Akaun disimpan dalam ibco-hr-solutions
                    </Badge>
                </div>

                <Button asChild variant="outline">
                    <Link href="/pengguna">
                        <ArrowLeft />
                        Kembali
                    </Link>
                </Button>
            </div>

            <form onSubmit={submit} className="space-y-6">
                <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <UserRound className="size-5 text-muted-foreground" />
                                Maklumat Akaun
                            </CardTitle>
                            <CardDescription>
                                E-mel digunakan semasa login. Akaun yang
                                didaftarkan oleh Super Admin dianggap telah
                                disahkan.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-5 sm:grid-cols-2">
                            <div className="space-y-2 sm:col-span-2">
                                <Label>Role Pengguna</Label>
                                <div className="grid gap-3 sm:grid-cols-2">
                                    {options.roles.map((role) => {
                                        const checked = data.roles.includes(
                                            role.value,
                                        );
                                        const locked =
                                            protectSuperAdmin &&
                                            role.value === 'super_admin';

                                        return (
                                            <label
                                                key={role.value}
                                                className={`flex cursor-pointer items-start gap-3 rounded-lg border p-3 ${
                                                    checked
                                                        ? 'border-primary bg-primary/5'
                                                        : ''
                                                }`}
                                            >
                                                <Checkbox
                                                    checked={checked}
                                                    onCheckedChange={(value) =>
                                                        toggleRole(
                                                            role.value,
                                                            value === true,
                                                        )
                                                    }
                                                    disabled={
                                                        processing || locked
                                                    }
                                                    aria-label={role.label}
                                                />
                                                <span className="space-y-1">
                                                    <span className="block text-sm font-medium">
                                                        {role.label}
                                                    </span>
                                                    <span className="block text-xs text-muted-foreground">
                                                        {role.description}
                                                    </span>
                                                </span>
                                            </label>
                                        );
                                    })}
                                </div>
                                <InputError message={errors.roles} />
                                {protectSuperAdmin && (
                                    <p className="text-xs text-muted-foreground">
                                        Role Super Admin akaun anda dikunci
                                        untuk keselamatan. Role lain masih boleh
                                        ditambah atau dibuang.
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="user-name">Nama Penuh</Label>
                                <Input
                                    id="user-name"
                                    value={data.name}
                                    onChange={(event) =>
                                        setData('name', event.target.value)
                                    }
                                    autoComplete="name"
                                    placeholder="Nama pengguna"
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="user-email">Alamat E-mel</Label>
                                <Input
                                    id="user-email"
                                    type="email"
                                    value={data.email}
                                    onChange={(event) =>
                                        setData('email', event.target.value)
                                    }
                                    autoComplete="email"
                                    placeholder="nama@ibco.com"
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="user-password">
                                    {mode === 'create'
                                        ? 'Kata Laluan Sementara'
                                        : 'Kata Laluan Baharu'}
                                </Label>
                                <PasswordInput
                                    id="user-password"
                                    value={data.password}
                                    onChange={(event) =>
                                        setData('password', event.target.value)
                                    }
                                    autoComplete="new-password"
                                    placeholder={
                                        mode === 'create'
                                            ? 'Masukkan kata laluan'
                                            : 'Biarkan kosong jika tidak berubah'
                                    }
                                    required={mode === 'create'}
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="user-password-confirmation">
                                    Sahkan Kata Laluan
                                </Label>
                                <PasswordInput
                                    id="user-password-confirmation"
                                    value={data.password_confirmation}
                                    onChange={(event) =>
                                        setData(
                                            'password_confirmation',
                                            event.target.value,
                                        )
                                    }
                                    autoComplete="new-password"
                                    placeholder="Ulang kata laluan"
                                    required={mode === 'create'}
                                />
                                <InputError
                                    message={errors.password_confirmation}
                                />
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="h-fit">
                        <CardHeader>
                            <CardTitle className="text-base">
                                Akses Gabungan Role
                            </CardTitle>
                            <CardDescription>
                                Sistem menggabungkan permission daripada semua
                                role yang dipilih.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3 text-sm">
                            <div className="rounded-lg bg-muted p-3">
                                {selectedRoles.length > 0 ? (
                                    <div className="flex flex-wrap gap-2">
                                        {selectedRoles.map((role) => (
                                            <Badge
                                                key={role.value}
                                                variant="outline"
                                            >
                                                {role.label}
                                            </Badge>
                                        ))}
                                    </div>
                                ) : (
                                    <p className="text-sm text-destructive">
                                        Pilih sekurang-kurangnya satu role.
                                    </p>
                                )}
                            </div>
                            {mode === 'edit' && (
                                <p className="text-xs text-muted-foreground">
                                    Isi kata laluan hanya jika Super Admin mahu
                                    menetapkan kata laluan baharu.
                                </p>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {isEmployee && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Database className="size-5 text-muted-foreground" />
                                Pautan Pekerja & Kehadiran
                            </CardTitle>
                            <CardDescription>
                                Pilih rekod pekerja aktif dan lokasi geofence.
                                Maklumat pekerja daripada db_spp hanya dibaca;
                                tiada jadual asal diubah.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-5 lg:grid-cols-2">
                            <div className="space-y-2">
                                <Label>Rekod Pekerja db_spp</Label>
                                <Select
                                    value={data.employee_id}
                                    onValueChange={selectEmployee}
                                    disabled={processing}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Pilih pekerja aktif" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {options.employees.map((employee) => (
                                            <SelectItem
                                                key={employee.id}
                                                value={String(employee.id)}
                                            >
                                                {employee.employee_id ||
                                                    `#${employee.id}`}{' '}
                                                —{' '}
                                                {employee.name || 'Tanpa nama'}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.employee_id} />
                                {options.employees.length === 0 && (
                                    <p className="text-xs text-amber-700 dark:text-amber-300">
                                        Tiada pekerja aktif yang belum
                                        didaftarkan sebagai pengguna.
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label>Lokasi Pejabat / Geofence</Label>
                                <Select
                                    value={data.office_location_id}
                                    onValueChange={(value) =>
                                        setData('office_location_id', value)
                                    }
                                    disabled={processing}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Pilih lokasi pejabat" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {options.offices.map((office) => (
                                            <SelectItem
                                                key={office.id}
                                                value={String(office.id)}
                                            >
                                                {office.name} (
                                                {office.radius_meters} m)
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError
                                    message={errors.office_location_id}
                                />
                                {options.offices.length === 0 && (
                                    <p className="text-xs text-amber-700 dark:text-amber-300">
                                        Tambah lokasi aktif dalam Tetapan
                                        Kehadiran sebelum mendaftarkan Employee.
                                    </p>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                )}

                <div className="flex flex-col-reverse gap-3 border-t pt-5 sm:flex-row sm:justify-end">
                    <Button asChild type="button" variant="outline">
                        <Link href="/pengguna">Batal</Link>
                    </Button>
                    <Button
                        type="submit"
                        disabled={
                            processing ||
                            data.roles.length === 0 ||
                            (isEmployee &&
                                (!data.employee_id || !data.office_location_id))
                        }
                    >
                        <Save />
                        {processing
                            ? 'Menyimpan...'
                            : mode === 'create'
                              ? 'Daftar Pengguna'
                              : 'Simpan Perubahan'}
                    </Button>
                </div>
            </form>
        </div>
    );
}
