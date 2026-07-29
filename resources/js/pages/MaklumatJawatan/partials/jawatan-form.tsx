import { Link, useForm } from '@inertiajs/react';
import {
    AlertCircle,
    Banknote,
    BriefcaseBusiness,
    Building2,
    CalendarDays,
    Database,
    Landmark,
    Save,
    UserRound,
} from 'lucide-react';
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

export type PositionFormOptions = {
    employees?: ReferenceOption[];
    departments: ReferenceOption[];
    banks: ReferenceOption[];
};

export type PositionFormData = {
    id_pekerja: string;
    date_lapordiri: string;
    date_tempohcubaan: string;
    id_department: string;
    jawatan: string;
    salary: string;
    id_bank: string;
    noakaun: string;
    noepf: string;
    nosocso: string;
    jumlahcuti: string;
};

type PositionFormProps = {
    mode: 'create' | 'edit';
    options: PositionFormOptions;
    initialValues: PositionFormData;
    positionId?: number;
    employeeLabel?: string;
};

type FieldProps = {
    htmlFor: string;
    label: string;
    required?: boolean;
    error?: string;
    description?: string;
    children: ReactNode;
};

const emptyOption = '__none__';

function Field({
    htmlFor,
    label,
    required = false,
    error,
    description,
    children,
}: FieldProps) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={htmlFor}>
                {label}
                {required && <span className="ml-1 text-destructive">*</span>}
            </Label>
            {children}
            {description && (
                <p className="text-xs text-muted-foreground">{description}</p>
            )}
            <InputError message={error} />
        </div>
    );
}

function ReferenceSelect({
    id,
    label,
    value,
    options,
    onChange,
    error,
    required = false,
    disabled = false,
}: {
    id: keyof PositionFormData;
    label: string;
    value: string;
    options: ReferenceOption[];
    onChange: (value: string) => void;
    error?: string;
    required?: boolean;
    disabled?: boolean;
}) {
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

export default function JawatanForm({
    mode,
    options,
    initialValues,
    positionId,
    employeeLabel,
}: PositionFormProps) {
    const [confirmationOpen, setConfirmationOpen] = useState(false);
    const { data, setData, post, put, processing, errors } =
        useForm<PositionFormData>(initialValues);
    const isEditing = mode === 'edit';

    const requestConfirmation = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setConfirmationOpen(true);
    };

    const submit = () => {
        const requestOptions = {
            preserveScroll: true,
            onError: () => setConfirmationOpen(false),
        };

        if (isEditing && positionId !== undefined) {
            put(`/jawatan/${positionId}`, requestOptions);

            return;
        }

        post('/jawatan', requestOptions);
    };

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div className="space-y-2">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {isEditing
                                ? 'Tukar Jawatan / Penempatan'
                                : 'Tambah Penempatan Pekerja'}
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {isEditing
                                ? 'Rekod semasa akan dikekalkan dalam sejarah dan penempatan baharu akan diwujudkan.'
                                : 'Tetapkan jawatan, jabatan dan tarikh berkuat kuasa bagi pekerja.'}
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
                            isEditing && positionId !== undefined
                                ? `/jawatan/${positionId}`
                                : '/jawatan'
                        }
                    >
                        Batal
                    </Link>
                </Button>
            </div>

            <Alert>
                <BriefcaseBusiness />
                <AlertTitle>Sejarah jawatan dilindungi</AlertTitle>
                <AlertDescription>
                    Jika pekerja sudah mempunyai jawatan aktif, rekod tersebut
                    akan ditamatkan secara automatik dan disimpan sebagai
                    sejarah. Hanya penempatan baharu akan kekal aktif.
                </AlertDescription>
            </Alert>

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
                            <Building2 className="size-5 text-muted-foreground" />
                            Maklumat Penempatan
                        </CardTitle>
                        <CardDescription>
                            Medan bertanda * wajib dilengkapkan.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-5 md:grid-cols-2">
                        {isEditing ? (
                            <Field
                                htmlFor="employee"
                                label="Pekerja"
                                required
                                error={errors.id_pekerja}
                            >
                                <div className="relative">
                                    <UserRound className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        id="employee"
                                        value={
                                            employeeLabel ||
                                            `Pekerja #${data.id_pekerja}`
                                        }
                                        className="pl-9"
                                        disabled
                                    />
                                </div>
                            </Field>
                        ) : (
                            <ReferenceSelect
                                id="id_pekerja"
                                label="Pekerja"
                                value={data.id_pekerja}
                                options={options.employees ?? []}
                                onChange={(value) =>
                                    setData('id_pekerja', value)
                                }
                                error={errors.id_pekerja}
                                required
                                disabled={processing}
                            />
                        )}

                        <ReferenceSelect
                            id="id_department"
                            label="Jabatan / Unit"
                            value={data.id_department}
                            options={options.departments}
                            onChange={(value) =>
                                setData('id_department', value)
                            }
                            error={errors.id_department}
                            required
                            disabled={processing}
                        />

                        <Field
                            htmlFor="jawatan"
                            label="Nama Jawatan"
                            required
                            error={errors.jawatan}
                        >
                            <div className="relative">
                                <BriefcaseBusiness className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    id="jawatan"
                                    value={data.jawatan}
                                    onChange={(event) =>
                                        setData('jawatan', event.target.value)
                                    }
                                    className="pl-9"
                                    maxLength={100}
                                    required
                                    disabled={processing}
                                    placeholder="Contoh: Eksekutif Sumber Manusia"
                                    aria-invalid={Boolean(errors.jawatan)}
                                />
                            </div>
                        </Field>

                        <Field
                            htmlFor="date_lapordiri"
                            label="Tarikh Berkuat Kuasa / Lapor Diri"
                            required
                            error={errors.date_lapordiri}
                        >
                            <Input
                                id="date_lapordiri"
                                type="date"
                                value={data.date_lapordiri}
                                onChange={(event) =>
                                    setData(
                                        'date_lapordiri',
                                        event.target.value,
                                    )
                                }
                                required
                                disabled={processing}
                                aria-invalid={Boolean(errors.date_lapordiri)}
                            />
                        </Field>

                        <Field
                            htmlFor="date_tempohcubaan"
                            label="Tarikh Tamat Tempoh Percubaan"
                            error={errors.date_tempohcubaan}
                            description="Kosongkan jika tidak berkenaan."
                        >
                            <Input
                                id="date_tempohcubaan"
                                type="date"
                                value={data.date_tempohcubaan}
                                min={data.date_lapordiri || undefined}
                                onChange={(event) =>
                                    setData(
                                        'date_tempohcubaan',
                                        event.target.value,
                                    )
                                }
                                disabled={processing}
                                aria-invalid={Boolean(errors.date_tempohcubaan)}
                            />
                        </Field>

                        <Field
                            htmlFor="jumlahcuti"
                            label="Kelayakan Cuti Tahunan"
                            error={errors.jumlahcuti}
                            description="Masukkan bilangan hari, maksimum 365."
                        >
                            <div className="relative">
                                <CalendarDays className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    id="jumlahcuti"
                                    type="number"
                                    inputMode="numeric"
                                    min="0"
                                    max="365"
                                    value={data.jumlahcuti}
                                    onChange={(event) =>
                                        setData(
                                            'jumlahcuti',
                                            event.target.value,
                                        )
                                    }
                                    className="pl-9"
                                    disabled={processing}
                                    placeholder="Contoh: 20"
                                    aria-invalid={Boolean(errors.jumlahcuti)}
                                />
                            </div>
                        </Field>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Banknote className="size-5 text-muted-foreground" />
                            Gaji & Maklumat Caruman
                        </CardTitle>
                        <CardDescription>
                            Maklumat sensitif ini hanya boleh diakses oleh role
                            yang mempunyai kebenaran payroll.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-5 md:grid-cols-2">
                        <Field
                            htmlFor="salary"
                            label="Gaji Asas (RM)"
                            error={errors.salary}
                        >
                            <div className="relative">
                                <Banknote className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    id="salary"
                                    type="number"
                                    inputMode="decimal"
                                    min="0"
                                    max="99999999.99"
                                    step="0.01"
                                    value={data.salary}
                                    onChange={(event) =>
                                        setData('salary', event.target.value)
                                    }
                                    className="pl-9"
                                    disabled={processing}
                                    placeholder="Contoh: 3500.00"
                                    aria-invalid={Boolean(errors.salary)}
                                />
                            </div>
                        </Field>

                        <ReferenceSelect
                            id="id_bank"
                            label="Bank"
                            value={data.id_bank}
                            options={options.banks}
                            onChange={(value) => setData('id_bank', value)}
                            error={errors.id_bank}
                            disabled={processing}
                        />

                        <Field
                            htmlFor="noakaun"
                            label="No. Akaun Bank"
                            error={errors.noakaun}
                        >
                            <div className="relative">
                                <Landmark className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    id="noakaun"
                                    value={data.noakaun}
                                    onChange={(event) =>
                                        setData('noakaun', event.target.value)
                                    }
                                    className="pl-9"
                                    maxLength={20}
                                    disabled={processing}
                                    autoComplete="off"
                                    aria-invalid={Boolean(errors.noakaun)}
                                />
                            </div>
                        </Field>

                        <Field
                            htmlFor="noepf"
                            label="No. KWSP"
                            error={errors.noepf}
                        >
                            <Input
                                id="noepf"
                                value={data.noepf}
                                onChange={(event) =>
                                    setData('noepf', event.target.value)
                                }
                                maxLength={20}
                                disabled={processing}
                                autoComplete="off"
                                aria-invalid={Boolean(errors.noepf)}
                            />
                        </Field>

                        <Field
                            htmlFor="nosocso"
                            label="No. PERKESO"
                            error={errors.nosocso}
                        >
                            <Input
                                id="nosocso"
                                value={data.nosocso}
                                onChange={(event) =>
                                    setData('nosocso', event.target.value)
                                }
                                maxLength={20}
                                disabled={processing}
                                autoComplete="off"
                                aria-invalid={Boolean(errors.nosocso)}
                            />
                        </Field>
                    </CardContent>
                </Card>

                <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <Button asChild type="button" variant="outline">
                        <Link
                            href={
                                isEditing && positionId !== undefined
                                    ? `/jawatan/${positionId}`
                                    : '/jawatan'
                            }
                        >
                            Batal
                        </Link>
                    </Button>
                    <Button type="submit" disabled={processing}>
                        <Save />
                        {isEditing
                            ? 'Semak Pertukaran'
                            : 'Semak & Simpan Penempatan'}
                    </Button>
                </div>
            </form>

            <Dialog open={confirmationOpen} onOpenChange={setConfirmationOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {isEditing
                                ? 'Sahkan pertukaran jawatan?'
                                : 'Sahkan penempatan pekerja?'}
                        </DialogTitle>
                        <DialogDescription>
                            {isEditing
                                ? 'Rekod jawatan semasa akan dipindahkan ke sejarah dan satu rekod penempatan baharu akan diwujudkan.'
                                : 'Jika pekerja mempunyai jawatan aktif, rekod lama akan dipindahkan ke sejarah sebelum penempatan ini diaktifkan.'}{' '}
                            Tindakan ini akan direkodkan dalam Audit Trail.
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
                            {processing ? 'Sedang disimpan...' : 'Ya, Simpan'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
