import { Head, router, useForm } from '@inertiajs/react';
import {
    Building2,
    Link2,
    MapPin,
    Pencil,
    Plus,
    Power,
    PowerOff,
    Save,
    ShieldCheck,
    Unlink,
    UserRoundCheck,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import HeadingSmall from '@/components/heading-small';
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

type Office = {
    id: number;
    name: string;
    address: string | null;
    latitude: string;
    longitude: string;
    radius_meters: number;
    accuracy_limit_meters: number;
    is_active: boolean;
    active_links_count: number;
};

type UserOption = {
    id: number;
    name: string;
    email: string;
};

type EmployeeOption = {
    id: number;
    employee_id: string | null;
    name: string | null;
};

type EmployeeLink = {
    id: number;
    is_active: boolean;
    user: (UserOption & { role: string }) | null;
    employee:
        | (EmployeeOption & {
              is_active: boolean;
          })
        | null;
    office: { id: number; name: string } | null;
};

type SettingsProps = {
    offices: Office[];
    links: EmployeeLink[];
    userOptions: UserOption[];
    employeeOptions: EmployeeOption[];
};

type OfficeFormData = {
    name: string;
    address: string;
    latitude: string;
    longitude: string;
    radius_meters: string;
    accuracy_limit_meters: string;
};

function OfficeDialog({
    office,
    trigger,
}: {
    office?: Office;
    trigger: React.ReactNode;
}) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, put, processing, errors, reset } =
        useForm<OfficeFormData>({
            name: office?.name ?? '',
            address: office?.address ?? '',
            latitude: office?.latitude ?? '',
            longitude: office?.longitude ?? '',
            radius_meters: String(office?.radius_meters ?? 100),
            accuracy_limit_meters: String(office?.accuracy_limit_meters ?? 100),
        });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);

                if (!office) {
                    reset();
                }
            },
        };

        if (office) {
            put(`/tetapan-kehadiran/lokasi/${office.id}`, options);
        } else {
            post('/tetapan-kehadiran/lokasi', options);
        }
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {office
                            ? 'Kemas Kini Lokasi Pejabat'
                            : 'Tambah Lokasi Pejabat'}
                    </DialogTitle>
                    <DialogDescription>
                        Koordinat, radius dan had ketepatan ini digunakan oleh
                        server untuk mengesahkan rakaman kehadiran.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="space-y-5">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-2 sm:col-span-2">
                            <Label htmlFor={`office-name-${office?.id ?? 0}`}>
                                Nama Lokasi
                            </Label>
                            <Input
                                id={`office-name-${office?.id ?? 0}`}
                                value={data.name}
                                onChange={(event) =>
                                    setData('name', event.target.value)
                                }
                                placeholder="Contoh: IBCO Solutions HQ"
                            />
                            <InputError message={errors.name} />
                        </div>

                        <div className="space-y-2 sm:col-span-2">
                            <Label
                                htmlFor={`office-address-${office?.id ?? 0}`}
                            >
                                Alamat
                            </Label>
                            <textarea
                                id={`office-address-${office?.id ?? 0}`}
                                value={data.address}
                                onChange={(event) =>
                                    setData('address', event.target.value)
                                }
                                rows={3}
                                className="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                placeholder="Alamat penuh pejabat"
                            />
                            <InputError message={errors.address} />
                        </div>

                        <div className="space-y-2">
                            <Label
                                htmlFor={`office-latitude-${office?.id ?? 0}`}
                            >
                                Latitude
                            </Label>
                            <Input
                                id={`office-latitude-${office?.id ?? 0}`}
                                type="number"
                                step="0.0000001"
                                value={data.latitude}
                                onChange={(event) =>
                                    setData('latitude', event.target.value)
                                }
                                placeholder="3.1390000"
                            />
                            <InputError message={errors.latitude} />
                        </div>

                        <div className="space-y-2">
                            <Label
                                htmlFor={`office-longitude-${office?.id ?? 0}`}
                            >
                                Longitude
                            </Label>
                            <Input
                                id={`office-longitude-${office?.id ?? 0}`}
                                type="number"
                                step="0.0000001"
                                value={data.longitude}
                                onChange={(event) =>
                                    setData('longitude', event.target.value)
                                }
                                placeholder="101.6869000"
                            />
                            <InputError message={errors.longitude} />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor={`office-radius-${office?.id ?? 0}`}>
                                Radius Dibenarkan (meter)
                            </Label>
                            <Input
                                id={`office-radius-${office?.id ?? 0}`}
                                type="number"
                                min={20}
                                max={5000}
                                value={data.radius_meters}
                                onChange={(event) =>
                                    setData('radius_meters', event.target.value)
                                }
                            />
                            <InputError message={errors.radius_meters} />
                        </div>

                        <div className="space-y-2">
                            <Label
                                htmlFor={`office-accuracy-${office?.id ?? 0}`}
                            >
                                Had Ketepatan GPS (meter)
                            </Label>
                            <Input
                                id={`office-accuracy-${office?.id ?? 0}`}
                                type="number"
                                min={10}
                                max={1000}
                                value={data.accuracy_limit_meters}
                                onChange={(event) =>
                                    setData(
                                        'accuracy_limit_meters',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError
                                message={errors.accuracy_limit_meters}
                            />
                        </div>
                    </div>

                    <div className="rounded-lg bg-muted p-3 text-xs text-muted-foreground">
                        Radius asal ialah 100 meter. Ketepatan GPS yang lebih
                        besar daripada had akan ditolak walaupun koordinat
                        berada dalam radius.
                    </div>

                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Batal
                            </Button>
                        </DialogClose>
                        <Button type="submit" disabled={processing}>
                            <Save />
                            {processing ? 'Menyimpan...' : 'Simpan Lokasi'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function LinkForm({
    users,
    employees,
    offices,
}: {
    users: UserOption[];
    employees: EmployeeOption[];
    offices: Office[];
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        user_id: '',
        employee_id: '',
        office_location_id: '',
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        post('/tetapan-kehadiran/pautan', {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    return (
        <form onSubmit={submit} className="grid gap-4 lg:grid-cols-3">
            <div className="space-y-2">
                <Label>Akaun Employee</Label>
                <Select
                    value={data.user_id}
                    onValueChange={(value) => setData('user_id', value)}
                >
                    <SelectTrigger>
                        <SelectValue placeholder="Pilih akaun" />
                    </SelectTrigger>
                    <SelectContent>
                        {users.map((user) => (
                            <SelectItem key={user.id} value={String(user.id)}>
                                {user.name} — {user.email}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={errors.user_id} />
            </div>

            <div className="space-y-2">
                <Label>Rekod Pekerja db_spp</Label>
                <Select
                    value={data.employee_id}
                    onValueChange={(value) => setData('employee_id', value)}
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
                                {employee.employee_id || `#${employee.id}`} —{' '}
                                {employee.name || 'Tanpa nama'}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={errors.employee_id} />
            </div>

            <div className="space-y-2">
                <Label>Lokasi Pejabat</Label>
                <Select
                    value={data.office_location_id}
                    onValueChange={(value) =>
                        setData('office_location_id', value)
                    }
                >
                    <SelectTrigger>
                        <SelectValue placeholder="Pilih lokasi" />
                    </SelectTrigger>
                    <SelectContent>
                        {offices
                            .filter((office) => office.is_active)
                            .map((office) => (
                                <SelectItem
                                    key={office.id}
                                    value={String(office.id)}
                                >
                                    {office.name} ({office.radius_meters} m)
                                </SelectItem>
                            ))}
                    </SelectContent>
                </Select>
                <InputError message={errors.office_location_id} />
            </div>

            <div className="lg:col-span-3">
                <Button
                    type="submit"
                    disabled={
                        processing ||
                        !data.user_id ||
                        !data.employee_id ||
                        !data.office_location_id
                    }
                >
                    <Link2 />
                    {processing ? 'Menyimpan...' : 'Simpan Pautan'}
                </Button>
            </div>
        </form>
    );
}

export default function AttendanceSettings({
    offices,
    links,
    userOptions,
    employeeOptions,
}: SettingsProps) {
    const toggleOffice = (office: Office) => {
        if (
            !window.confirm(
                office.is_active
                    ? `Nyahaktifkan lokasi ${office.name}?`
                    : `Aktifkan semula lokasi ${office.name}?`,
            )
        ) {
            return;
        }

        router.patch(
            `/tetapan-kehadiran/lokasi/${office.id}/status`,
            {},
            { preserveScroll: true },
        );
    };

    const deactivateLink = (link: EmployeeLink) => {
        if (
            !window.confirm(
                `Nyahaktifkan pautan ${link.user?.name || 'pengguna ini'}?`,
            )
        ) {
            return;
        }

        router.patch(
            `/tetapan-kehadiran/pautan/${link.id}/nyahaktif`,
            {},
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Tetapan Kehadiran" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div className="space-y-2">
                        <HeadingSmall
                            title="Tetapan Kehadiran Geolocation"
                            description="Urus lokasi pejabat dan pautan akaun Employee tanpa mengubah db_spp."
                        />
                        <Badge variant="secondary" className="gap-1.5">
                            <ShieldCheck className="size-3.5" />
                            Data baharu: ibco-hr-solutions
                        </Badge>
                    </div>
                    <OfficeDialog
                        trigger={
                            <Button>
                                <Plus />
                                Tambah Lokasi
                            </Button>
                        }
                    />
                </div>

                <div className="grid gap-4 sm:grid-cols-3">
                    <Card>
                        <CardHeader className="flex-row items-center justify-between">
                            <div>
                                <CardDescription>Lokasi Aktif</CardDescription>
                                <CardTitle className="mt-1 text-3xl">
                                    {
                                        offices.filter(
                                            (office) => office.is_active,
                                        ).length
                                    }
                                </CardTitle>
                            </div>
                            <Building2 className="size-6 text-primary" />
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader className="flex-row items-center justify-between">
                            <div>
                                <CardDescription>Pautan Aktif</CardDescription>
                                <CardTitle className="mt-1 text-3xl">
                                    {
                                        links.filter((link) => link.is_active)
                                            .length
                                    }
                                </CardTitle>
                            </div>
                            <UserRoundCheck className="size-6 text-emerald-600" />
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader className="flex-row items-center justify-between">
                            <div>
                                <CardDescription>
                                    Radius Standard
                                </CardDescription>
                                <CardTitle className="mt-1 text-3xl">
                                    100 m
                                </CardTitle>
                            </div>
                            <MapPin className="size-6 text-violet-600" />
                        </CardHeader>
                    </Card>
                </div>

                <Card className="gap-0 overflow-hidden">
                    <CardHeader className="border-b">
                        <CardTitle>Lokasi Pejabat</CardTitle>
                        <CardDescription>
                            Setiap lokasi mempunyai radius dan had ketepatan GPS
                            tersendiri.
                        </CardDescription>
                    </CardHeader>
                    <div className="grid gap-3 p-4 md:grid-cols-2 xl:grid-cols-3">
                        {offices.map((office) => (
                            <div
                                key={office.id}
                                className="space-y-4 rounded-xl border p-4"
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <p className="font-semibold">
                                            {office.name}
                                        </p>
                                        <p className="mt-1 line-clamp-2 text-xs text-muted-foreground">
                                            {office.address ||
                                                'Alamat tidak dinyatakan'}
                                        </p>
                                    </div>
                                    <Badge
                                        variant={
                                            office.is_active
                                                ? 'secondary'
                                                : 'outline'
                                        }
                                    >
                                        {office.is_active
                                            ? 'Aktif'
                                            : 'Tidak Aktif'}
                                    </Badge>
                                </div>
                                <div className="grid grid-cols-3 gap-2 text-center text-xs">
                                    <div className="rounded-lg bg-muted p-2">
                                        <p className="text-muted-foreground">
                                            Radius
                                        </p>
                                        <p className="font-semibold">
                                            {office.radius_meters} m
                                        </p>
                                    </div>
                                    <div className="rounded-lg bg-muted p-2">
                                        <p className="text-muted-foreground">
                                            Ketepatan
                                        </p>
                                        <p className="font-semibold">
                                            ±{office.accuracy_limit_meters} m
                                        </p>
                                    </div>
                                    <div className="rounded-lg bg-muted p-2">
                                        <p className="text-muted-foreground">
                                            Pekerja
                                        </p>
                                        <p className="font-semibold">
                                            {office.active_links_count}
                                        </p>
                                    </div>
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    {office.latitude}, {office.longitude}
                                </p>
                                <div className="flex gap-2">
                                    <OfficeDialog
                                        office={office}
                                        trigger={
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                className="flex-1"
                                            >
                                                <Pencil />
                                                Edit
                                            </Button>
                                        }
                                    />
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        className="flex-1"
                                        onClick={() => toggleOffice(office)}
                                    >
                                        {office.is_active ? (
                                            <PowerOff />
                                        ) : (
                                            <Power />
                                        )}
                                        {office.is_active
                                            ? 'Nyahaktif'
                                            : 'Aktifkan'}
                                    </Button>
                                </div>
                            </div>
                        ))}
                        {offices.length === 0 && (
                            <p className="py-10 text-center text-sm text-muted-foreground md:col-span-2 xl:col-span-3">
                                Tambah sekurang-kurangnya satu lokasi pejabat
                                sebelum memautkan pekerja.
                            </p>
                        )}
                    </div>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Pautan Akaun Employee</CardTitle>
                        <CardDescription>
                            Satu akaun sistem dipautkan kepada satu pekerja
                            aktif dalam db_spp dan satu lokasi pejabat.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <LinkForm
                            users={userOptions}
                            employees={employeeOptions}
                            offices={offices}
                        />
                    </CardContent>
                </Card>

                <Card className="gap-0 overflow-hidden">
                    <CardHeader className="border-b">
                        <CardTitle>Senarai Pautan</CardTitle>
                        <CardDescription>
                            Pautan dinyahaktifkan secara terkawal dan tidak
                            dipadam secara kekal.
                        </CardDescription>
                    </CardHeader>
                    <div className="hidden overflow-x-auto md:block">
                        <Table>
                            <TableHeader className="bg-muted/60">
                                <TableRow>
                                    <TableHead>Akaun</TableHead>
                                    <TableHead>Pekerja db_spp</TableHead>
                                    <TableHead>Lokasi</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-right">
                                        Tindakan
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {links.map((link) => (
                                    <TableRow key={link.id}>
                                        <TableCell>
                                            <p className="font-medium">
                                                {link.user?.name ||
                                                    'Akaun dipadam'}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {link.user?.email || '-'}
                                            </p>
                                        </TableCell>
                                        <TableCell>
                                            <p className="font-medium">
                                                {link.employee?.name ||
                                                    'Rekod tidak ditemui'}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {link.employee?.employee_id ||
                                                    `#${link.employee?.id ?? '-'}`}
                                            </p>
                                        </TableCell>
                                        <TableCell>
                                            {link.office?.name || '-'}
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant={
                                                    link.is_active
                                                        ? 'secondary'
                                                        : 'outline'
                                                }
                                            >
                                                {link.is_active
                                                    ? 'Aktif'
                                                    : 'Tidak Aktif'}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {link.is_active && (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() =>
                                                        deactivateLink(link)
                                                    }
                                                >
                                                    <Unlink />
                                                    Nyahaktif
                                                </Button>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>

                    <div className="grid gap-3 p-4 md:hidden">
                        {links.map((link) => (
                            <div
                                key={link.id}
                                className="space-y-3 rounded-xl border p-4"
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <p className="font-medium">
                                            {link.employee?.name ||
                                                'Rekod pekerja tidak ditemui'}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {link.user?.name} ·{' '}
                                            {link.user?.email}
                                        </p>
                                    </div>
                                    <Badge variant="outline">
                                        {link.is_active
                                            ? 'Aktif'
                                            : 'Tidak Aktif'}
                                    </Badge>
                                </div>
                                <p className="text-sm">
                                    {link.office?.name ||
                                        'Lokasi tidak tersedia'}
                                </p>
                                {link.is_active && (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        className="w-full"
                                        onClick={() => deactivateLink(link)}
                                    >
                                        <Unlink />
                                        Nyahaktifkan Pautan
                                    </Button>
                                )}
                            </div>
                        ))}
                    </div>
                </Card>
            </div>
        </>
    );
}

AttendanceSettings.layout = {
    breadcrumbs: [
        { title: 'Pentadbiran', href: '/tetapan-kehadiran' },
        { title: 'Tetapan Kehadiran', href: '/tetapan-kehadiran' },
    ],
};
