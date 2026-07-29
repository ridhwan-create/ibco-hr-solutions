import { Head, useForm } from '@inertiajs/react';
import {
    BadgeCheck,
    BriefcaseBusiness,
    Database,
    IdCard,
    Mail,
    MapPin,
    Phone,
    Save,
    UserRound,
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

type Employee = {
    id: number;
    employee_id: string | null;
    name: string | null;
    nric: string | null;
    address: string | null;
    phone: string | null;
    email: string | null;
    birth_date: string | null;
    nationality: string | null;
    gender: string | null;
    religion: string | null;
    race: string | null;
    marital_status: string | null;
    employment_status: string | null;
};

type Position = {
    title: string | null;
    department: string | null;
    joined_at: string | null;
    leave_entitlement: string | null;
};

type Contact = {
    address: string | null;
    phone: string | null;
    email: string | null;
    is_updated_locally: boolean;
    updated_at: string | null;
};

type ProfileProps = {
    employee: Employee | null;
    position: Position | null;
    contact: Contact | null;
};

type ProfileForm = {
    address: string;
    phone: string;
    email: string;
};

function formatDate(value: string | null): string {
    if (!value) {
        return '-';
    }

    const date = new Date(`${value.slice(0, 10)}T00:00:00`);

    return Number.isNaN(date.getTime())
        ? value
        : new Intl.DateTimeFormat('ms-MY', {
              day: '2-digit',
              month: 'long',
              year: 'numeric',
          }).format(date);
}

function Detail({
    label,
    value,
}: {
    label: string;
    value: string | null | undefined;
}) {
    return (
        <div className="rounded-lg border bg-muted/20 p-3">
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className="mt-1 text-sm font-medium">{value || '-'}</p>
        </div>
    );
}

export default function EmployeeProfile({
    employee,
    position,
    contact,
}: ProfileProps) {
    const { data, setData, put, processing, errors, recentlySuccessful } =
        useForm<ProfileForm>({
            address: contact?.address ?? '',
            phone: contact?.phone ?? '',
            email: contact?.email ?? '',
        });
    const formErrors = errors as Record<string, string>;

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        put('/profil-saya', { preserveScroll: true });
    };

    if (!employee || !contact) {
        return (
            <>
                <Head title="Profil Saya" />
                <div className="mx-auto flex w-full max-w-4xl flex-1 p-4 md:p-6">
                    <Alert variant="destructive">
                        <IdCard />
                        <AlertTitle>Profil pekerja belum tersedia</AlertTitle>
                        <AlertDescription>
                            Akaun anda belum dipautkan kepada rekod pekerja
                            aktif. Hubungi Super Admin untuk melengkapkan pautan
                            akaun.
                        </AlertDescription>
                    </Alert>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title="Profil Saya" />

            <div className="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div className="space-y-1">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Profil Saya
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Semak maklumat perkhidmatan dan kemas kini maklumat
                            hubungan anda.
                        </p>
                    </div>
                    <Badge variant="outline" className="w-fit gap-1.5">
                        <BadgeCheck className="size-3.5 text-emerald-600" />
                        {employee.employment_status || 'Pekerja Aktif'}
                    </Badge>
                </div>

                <div className="grid gap-6 xl:grid-cols-[1fr_0.85fr]">
                    <div className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <UserRound className="size-5 text-primary" />
                                    Maklumat Peribadi
                                </CardTitle>
                                <CardDescription>
                                    Maklumat identiti ini dibaca daripada rekod
                                    induk organisasi.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-3 sm:grid-cols-2">
                                <Detail
                                    label="Nama Penuh"
                                    value={employee.name}
                                />
                                <Detail
                                    label="ID Pekerja"
                                    value={employee.employee_id}
                                />
                                <Detail
                                    label="No. Kad Pengenalan"
                                    value={employee.nric}
                                />
                                <Detail
                                    label="Tarikh Lahir"
                                    value={formatDate(employee.birth_date)}
                                />
                                <Detail
                                    label="Jantina"
                                    value={employee.gender}
                                />
                                <Detail
                                    label="Agama"
                                    value={employee.religion}
                                />
                                <Detail label="Bangsa" value={employee.race} />
                                <Detail
                                    label="Kewarganegaraan"
                                    value={employee.nationality}
                                />
                                <Detail
                                    label="Status Perkahwinan"
                                    value={employee.marital_status}
                                />
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <BriefcaseBusiness className="size-5 text-primary" />
                                    Maklumat Perkhidmatan
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-3 sm:grid-cols-2">
                                <Detail
                                    label="Jawatan"
                                    value={position?.title}
                                />
                                <Detail
                                    label="Jabatan / Unit"
                                    value={position?.department}
                                />
                                <Detail
                                    label="Tarikh Lapor Diri"
                                    value={formatDate(
                                        position?.joined_at ?? null,
                                    )}
                                />
                                <Detail
                                    label="Kelayakan Cuti"
                                    value={
                                        position?.leave_entitlement
                                            ? `${position.leave_entitlement} hari`
                                            : null
                                    }
                                />
                            </CardContent>
                        </Card>
                    </div>

                    <Card className="h-fit">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <IdCard className="size-5 text-primary" />
                                Maklumat Hubungan
                            </CardTitle>
                            <CardDescription>
                                Anda boleh mengemas kini alamat, telefon dan
                                e-mel hubungan sendiri.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={submit} className="space-y-5">
                                {formErrors.profile && (
                                    <Alert variant="destructive">
                                        <IdCard />
                                        <AlertTitle>
                                            Profil tidak dapat dikemas kini
                                        </AlertTitle>
                                        <AlertDescription>
                                            {formErrors.profile}
                                        </AlertDescription>
                                    </Alert>
                                )}

                                <div className="space-y-2">
                                    <Label htmlFor="profile-address">
                                        <MapPin className="size-4" />
                                        Alamat
                                    </Label>
                                    <textarea
                                        id="profile-address"
                                        value={data.address}
                                        onChange={(event) =>
                                            setData(
                                                'address',
                                                event.target.value,
                                            )
                                        }
                                        rows={4}
                                        maxLength={500}
                                        className="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-colors outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50"
                                        placeholder="Alamat surat-menyurat semasa"
                                    />
                                    <InputError message={errors.address} />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="profile-phone">
                                        <Phone className="size-4" />
                                        No. Telefon
                                    </Label>
                                    <Input
                                        id="profile-phone"
                                        value={data.phone}
                                        onChange={(event) =>
                                            setData('phone', event.target.value)
                                        }
                                        maxLength={30}
                                        autoComplete="tel"
                                        placeholder="Contoh: 0123456789"
                                    />
                                    <InputError message={errors.phone} />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="profile-email">
                                        <Mail className="size-4" />
                                        E-mel Hubungan
                                    </Label>
                                    <Input
                                        id="profile-email"
                                        type="email"
                                        value={data.email}
                                        onChange={(event) =>
                                            setData('email', event.target.value)
                                        }
                                        maxLength={255}
                                        autoComplete="email"
                                        placeholder="nama@contoh.com"
                                    />
                                    <InputError message={errors.email} />
                                </div>

                                <Button
                                    type="submit"
                                    className="w-full"
                                    disabled={processing}
                                >
                                    <Save />
                                    {processing
                                        ? 'Menyimpan...'
                                        : 'Simpan Maklumat'}
                                </Button>

                                {recentlySuccessful && (
                                    <p className="text-center text-sm text-emerald-600">
                                        Maklumat berjaya disimpan.
                                    </p>
                                )}

                                <div className="flex items-start gap-2 rounded-lg bg-muted/60 p-3 text-xs text-muted-foreground">
                                    <Database className="mt-0.5 size-4 shrink-0" />
                                    Rekod identiti dan perkhidmatan asal kekal
                                    sebagai rujukan. Perubahan maklumat hubungan
                                    direkodkan dalam sistem serta Audit Trail.
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

EmployeeProfile.layout = {
    breadcrumbs: [
        { title: 'Layan Diri Pekerja', href: '/profil-saya' },
        { title: 'Profil Saya', href: '/profil-saya' },
    ],
};
