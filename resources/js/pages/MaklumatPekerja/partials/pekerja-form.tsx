import { Link, useForm } from '@inertiajs/react';
import { AlertCircle, Database, Save, UserRound } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
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
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
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

export type ReferenceOption = {
    value: string;
    label: string;
};

export type EmployeeFormOptions = {
    jantina: ReferenceOption[];
    agama: ReferenceOption[];
    bangsa: ReferenceOption[];
    statusPerkahwinan: ReferenceOption[];
    status: ReferenceOption[];
};

export type EmployeeFormData = {
    employeeID: string;
    nric: string;
    nama: string;
    alamat: string;
    jantina: string;
    tarikhlahir: string;
    agama: string;
    bangsa: string;
    kewarganegaraan: string;
    statusperkahwinan: string;
    notel: string;
    email: string;
    status: string;
};

type EmployeeFormProps = {
    mode: 'create' | 'edit';
    options: EmployeeFormOptions;
    initialValues: EmployeeFormData;
    employeeId?: number;
};

type FieldProps = {
    htmlFor: string;
    label: string;
    required?: boolean;
    error?: string;
    children: ReactNode;
};

type SelectFieldProps = {
    id: keyof EmployeeFormData;
    label: string;
    value: string;
    options: ReferenceOption[];
    onChange: (value: string) => void;
    error?: string;
    required?: boolean;
    disabled?: boolean;
};

const emptyOption = '__none__';

function Field({
    htmlFor,
    label,
    required = false,
    error,
    children,
}: FieldProps) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={htmlFor}>
                {label}
                {required && <span className="ml-1 text-destructive">*</span>}
            </Label>
            {children}
            <InputError message={error} />
        </div>
    );
}

function SelectField({
    id,
    label,
    value,
    options,
    onChange,
    error,
    required = false,
    disabled = false,
}: SelectFieldProps) {
    return (
        <Field htmlFor={id} label={label} required={required} error={error}>
            <Select
                value={value || emptyOption}
                onValueChange={(selected) =>
                    onChange(selected === emptyOption ? '' : selected)
                }
                disabled={disabled}
                required={required}
            >
                <SelectTrigger
                    id={id}
                    className="w-full"
                    aria-invalid={Boolean(error)}
                >
                    <SelectValue placeholder={`Pilih ${label.toLowerCase()}`} />
                </SelectTrigger>
                <SelectContent align="start">
                    {!required && (
                        <SelectItem value={emptyOption}>
                            Tidak dinyatakan
                        </SelectItem>
                    )}
                    {options.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                            {option.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </Field>
    );
}

export default function PekerjaForm({
    mode,
    options,
    initialValues,
    employeeId,
}: EmployeeFormProps) {
    const [confirmationOpen, setConfirmationOpen] = useState(false);
    const { data, setData, post, put, processing, errors, isDirty } =
        useForm<EmployeeFormData>(initialValues);

    const isEditing = mode === 'edit';
    const title = isEditing ? 'Edit Maklumat Pekerja' : 'Tambah Pekerja';
    const description = isEditing
        ? 'Kemas kini maklumat pekerja yang dipilih.'
        : 'Daftarkan pekerja baharu ke dalam database HR.';

    const requestConfirmation = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setConfirmationOpen(true);
    };

    const submit = () => {
        const requestOptions = {
            preserveScroll: true,
            onError: () => setConfirmationOpen(false),
        };

        if (isEditing && employeeId !== undefined) {
            put(`/pekerja/${employeeId}`, requestOptions);

            return;
        }

        post('/pekerja', requestOptions);
    };

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div className="space-y-2">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {title}
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {description}
                        </p>
                    </div>
                    <Badge variant="secondary" className="gap-1.5 font-normal">
                        <Database className="size-3.5" />
                        Simpanan data: db_spp
                    </Badge>
                </div>

                <Button asChild variant="outline">
                    <Link
                        href={
                            isEditing && employeeId !== undefined
                                ? `/pekerja/${employeeId}`
                                : '/pekerja'
                        }
                    >
                        Batal
                    </Link>
                </Button>
            </div>

            {Object.keys(errors).length > 0 && (
                <Alert variant="destructive">
                    <AlertCircle />
                    <AlertTitle>Maklumat belum dapat disimpan</AlertTitle>
                    <AlertDescription>
                        Sila semak semula medan yang ditandakan di bawah.
                    </AlertDescription>
                </Alert>
            )}

            <form onSubmit={requestConfirmation} className="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <UserRound className="size-5 text-muted-foreground" />
                            Maklumat Utama
                        </CardTitle>
                        <CardDescription>
                            Medan bertanda * wajib dilengkapkan. ID pekerja dan
                            NRIC mestilah unik.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-5 md:grid-cols-2">
                        <Field
                            htmlFor="employeeID"
                            label="ID Pekerja"
                            required
                            error={errors.employeeID}
                        >
                            <Input
                                id="employeeID"
                                value={data.employeeID}
                                onChange={(event) =>
                                    setData('employeeID', event.target.value)
                                }
                                maxLength={15}
                                required
                                disabled={processing}
                                autoComplete="off"
                                aria-invalid={Boolean(errors.employeeID)}
                                placeholder="Contoh: EMP001"
                            />
                        </Field>

                        <Field
                            htmlFor="nric"
                            label="No. Kad Pengenalan"
                            required
                            error={errors.nric}
                        >
                            <Input
                                id="nric"
                                value={data.nric}
                                onChange={(event) =>
                                    setData('nric', event.target.value)
                                }
                                inputMode="numeric"
                                maxLength={14}
                                required
                                disabled={processing}
                                autoComplete="off"
                                aria-invalid={Boolean(errors.nric)}
                                placeholder="Contoh: 900101011234"
                            />
                        </Field>

                        <Field
                            htmlFor="nama"
                            label="Nama Penuh"
                            required
                            error={errors.nama}
                        >
                            <Input
                                id="nama"
                                value={data.nama}
                                onChange={(event) =>
                                    setData('nama', event.target.value)
                                }
                                maxLength={255}
                                required
                                disabled={processing}
                                autoComplete="name"
                                aria-invalid={Boolean(errors.nama)}
                                placeholder="Nama penuh pekerja"
                            />
                        </Field>

                        <SelectField
                            id="status"
                            label="Status Pekerja"
                            value={data.status}
                            options={options.status}
                            onChange={(value) => setData('status', value)}
                            error={errors.status}
                            required
                            disabled={processing}
                        />

                        <Field
                            htmlFor="tarikhlahir"
                            label="Tarikh Lahir"
                            error={errors.tarikhlahir}
                        >
                            <Input
                                id="tarikhlahir"
                                type="date"
                                value={data.tarikhlahir}
                                onChange={(event) =>
                                    setData('tarikhlahir', event.target.value)
                                }
                                max={new Date().toISOString().slice(0, 10)}
                                disabled={processing}
                                aria-invalid={Boolean(errors.tarikhlahir)}
                            />
                        </Field>

                        <SelectField
                            id="jantina"
                            label="Jantina"
                            value={data.jantina}
                            options={options.jantina}
                            onChange={(value) => setData('jantina', value)}
                            error={errors.jantina}
                            disabled={processing}
                        />

                        <SelectField
                            id="agama"
                            label="Agama"
                            value={data.agama}
                            options={options.agama}
                            onChange={(value) => setData('agama', value)}
                            error={errors.agama}
                            disabled={processing}
                        />

                        <SelectField
                            id="bangsa"
                            label="Bangsa"
                            value={data.bangsa}
                            options={options.bangsa}
                            onChange={(value) => setData('bangsa', value)}
                            error={errors.bangsa}
                            disabled={processing}
                        />

                        <SelectField
                            id="statusperkahwinan"
                            label="Status Perkahwinan"
                            value={data.statusperkahwinan}
                            options={options.statusPerkahwinan}
                            onChange={(value) =>
                                setData('statusperkahwinan', value)
                            }
                            error={errors.statusperkahwinan}
                            disabled={processing}
                        />

                        <Field
                            htmlFor="kewarganegaraan"
                            label="Kewarganegaraan"
                            error={errors.kewarganegaraan}
                        >
                            <Input
                                id="kewarganegaraan"
                                value={data.kewarganegaraan}
                                onChange={(event) =>
                                    setData(
                                        'kewarganegaraan',
                                        event.target.value,
                                    )
                                }
                                maxLength={255}
                                disabled={processing}
                                aria-invalid={Boolean(errors.kewarganegaraan)}
                                placeholder="Contoh: Malaysia"
                            />
                        </Field>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Maklumat Hubungan</CardTitle>
                        <CardDescription>
                            Maklumat ini boleh dikemas kini apabila terdapat
                            perubahan.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-5 md:grid-cols-2">
                        <Field
                            htmlFor="notel"
                            label="No. Telefon"
                            error={errors.notel}
                        >
                            <Input
                                id="notel"
                                type="tel"
                                value={data.notel}
                                onChange={(event) =>
                                    setData('notel', event.target.value)
                                }
                                maxLength={20}
                                disabled={processing}
                                autoComplete="tel"
                                aria-invalid={Boolean(errors.notel)}
                                placeholder="Contoh: 0123456789"
                            />
                        </Field>

                        <Field
                            htmlFor="email"
                            label="E-mel"
                            error={errors.email}
                        >
                            <Input
                                id="email"
                                type="email"
                                value={data.email}
                                onChange={(event) =>
                                    setData('email', event.target.value)
                                }
                                maxLength={255}
                                disabled={processing}
                                autoComplete="email"
                                aria-invalid={Boolean(errors.email)}
                                placeholder="nama@contoh.com"
                            />
                        </Field>

                        <div className="md:col-span-2">
                            <Field
                                htmlFor="alamat"
                                label="Alamat"
                                error={errors.alamat}
                            >
                                <textarea
                                    id="alamat"
                                    value={data.alamat}
                                    onChange={(event) =>
                                        setData('alamat', event.target.value)
                                    }
                                    maxLength={255}
                                    rows={4}
                                    disabled={processing}
                                    autoComplete="street-address"
                                    aria-invalid={Boolean(errors.alamat)}
                                    placeholder="Alamat surat-menyurat pekerja"
                                    className="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:bg-input/30 dark:aria-invalid:ring-destructive/40"
                                />
                            </Field>
                        </div>
                    </CardContent>
                </Card>

                <div className="flex flex-col-reverse gap-3 rounded-xl border bg-card p-4 sm:flex-row sm:items-center sm:justify-end">
                    <Button asChild variant="outline">
                        <Link
                            href={
                                isEditing && employeeId !== undefined
                                    ? `/pekerja/${employeeId}`
                                    : '/pekerja'
                            }
                        >
                            Batal
                        </Link>
                    </Button>
                    <Button
                        type="submit"
                        disabled={processing || (isEditing && !isDirty)}
                    >
                        <Save />
                        {isEditing ? 'Simpan Perubahan' : 'Tambah Pekerja'}
                    </Button>
                </div>
            </form>

            <Dialog open={confirmationOpen} onOpenChange={setConfirmationOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {isEditing
                                ? 'Sahkan perubahan pekerja'
                                : 'Sahkan pendaftaran pekerja'}
                        </DialogTitle>
                        <DialogDescription>
                            {isEditing
                                ? `Maklumat ${data.nama || 'pekerja ini'} akan dikemas kini dalam db_spp dan direkodkan dalam audit log.`
                                : `${data.nama || 'Pekerja baharu'} akan ditambah ke dalam db_spp dan tindakan ini direkodkan dalam audit log.`}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button variant="outline" disabled={processing}>
                                Semak Semula
                            </Button>
                        </DialogClose>
                        <Button
                            type="button"
                            onClick={submit}
                            disabled={processing}
                        >
                            <Save />
                            {processing ? 'Sedang menyimpan...' : 'Ya, Simpan'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
