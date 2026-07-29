import { Head, Link, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    CheckCircle2,
    ClipboardCopy,
    DatabaseZap,
    Download,
    Search,
    ShieldCheck,
    UserPlus,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import type { FormEvent } from 'react';
import HeadingSmall from '@/components/heading-small';
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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { UserRole } from '@/types';

type ImportStatus =
    | 'new_account'
    | 'existing_account'
    | 'invalid_email'
    | 'duplicate_email'
    | 'account_linked_elsewhere';

type ImportEmployee = {
    id: number;
    employee_id: string | null;
    name: string;
    email: string | null;
    status: ImportStatus;
    can_import: boolean;
    existing_user: {
        id: number;
        name: string;
        roles: UserRole[];
    } | null;
};

type OfficeOption = {
    id: number;
    name: string;
    radius_meters: number;
};

type Credential = {
    employee_id: string | null;
    name: string;
    email: string;
    temporary_password: string;
};

type LinkedAccount = {
    employee_id: string | null;
    name: string;
    email: string;
};

type ImportResult = {
    created_count: number;
    linked_count: number;
    office_name: string;
    credentials: Credential[];
    linked_accounts: LinkedAccount[];
};

type ImportEmployeesProps = {
    employees: ImportEmployee[];
    offices: OfficeOption[];
    statistics: {
        active_employees: number;
        already_registered: number;
        ready_to_import: number;
        requires_attention: number;
    };
    importResult: ImportResult | null;
};

type ImportForm = {
    employee_ids: number[];
    office_location_id: string;
};

const roleLabels: Record<UserRole, string> = {
    super_admin: 'Super Admin',
    hr_admin: 'HR Admin',
    supervisor: 'Penyelia / Ketua Jabatan',
    viewer: 'Viewer / Manager',
    employee: 'Employee',
};

const statusDetails: Record<
    ImportStatus,
    { label: string; description: string; className: string }
> = {
    new_account: {
        label: 'Akaun baharu',
        description: 'Akaun Employee baharu akan dicipta.',
        className:
            'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
    },
    existing_account: {
        label: 'Akaun sedia ada',
        description:
            'Role Employee dan pautan pekerja akan ditambah. Role serta kata laluan sedia ada dikekalkan.',
        className:
            'border-blue-500/30 bg-blue-500/10 text-blue-700 dark:text-blue-300',
    },
    invalid_email: {
        label: 'E-mel tidak sah',
        description:
            'Lengkapkan alamat e-mel pekerja sebelum menjalankan import.',
        className:
            'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300',
    },
    duplicate_email: {
        label: 'E-mel pendua',
        description:
            'Alamat e-mel ini digunakan oleh lebih daripada seorang pekerja aktif.',
        className:
            'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300',
    },
    account_linked_elsewhere: {
        label: 'Konflik pautan',
        description:
            'Akaun dengan e-mel ini telah dipautkan kepada pekerja lain.',
        className: 'border-destructive/30 bg-destructive/10 text-destructive',
    },
};

function csvCell(value: string | null): string {
    let safeValue = value ?? '';

    if (/^[=+\-@]/.test(safeValue)) {
        safeValue = `'${safeValue}`;
    }

    return `"${safeValue.replaceAll('"', '""')}"`;
}

function credentialText(credentials: Credential[]): string {
    return credentials
        .map(
            (credential) =>
                `${credential.name} | ${credential.email} | ${credential.temporary_password}`,
        )
        .join('\n');
}

export default function ImportEmployees({
    employees,
    offices,
    statistics,
    importResult,
}: ImportEmployeesProps) {
    const [search, setSearch] = useState('');
    const [credentialsCopied, setCredentialsCopied] = useState(false);
    const { data, setData, post, processing, errors } = useForm<ImportForm>({
        employee_ids: [],
        office_location_id: '',
    });
    const visibleEmployees = useMemo(() => {
        const term = search.trim().toLocaleLowerCase('ms-MY');

        if (!term) {
            return employees;
        }

        return employees.filter((employee) =>
            [
                employee.name,
                employee.employee_id ?? '',
                employee.email ?? '',
                statusDetails[employee.status].label,
            ].some((value) => value.toLocaleLowerCase('ms-MY').includes(term)),
        );
    }, [employees, search]);
    const visibleImportableIds = visibleEmployees
        .filter((employee) => employee.can_import)
        .map((employee) => employee.id);
    const allVisibleSelected =
        visibleImportableIds.length > 0 &&
        visibleImportableIds.every((id) => data.employee_ids.includes(id));

    const toggleEmployee = (employeeId: number, checked: boolean) => {
        setData(
            'employee_ids',
            checked
                ? [...new Set([...data.employee_ids, employeeId])]
                : data.employee_ids.filter((id) => id !== employeeId),
        );
    };

    const toggleVisible = (checked: boolean) => {
        if (!checked) {
            setData(
                'employee_ids',
                data.employee_ids.filter(
                    (id) => !visibleImportableIds.includes(id),
                ),
            );

            return;
        }

        setData('employee_ids', [
            ...new Set([...data.employee_ids, ...visibleImportableIds]),
        ]);
    };

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (
            !window.confirm(
                `Import ${data.employee_ids.length} pekerja sebagai pengguna sistem?`,
            )
        ) {
            return;
        }

        post('/pengguna/import-pekerja', {
            preserveScroll: true,
        });
    };

    const copyCredentials = async () => {
        if (!importResult?.credentials.length) {
            return;
        }

        await navigator.clipboard.writeText(
            credentialText(importResult.credentials),
        );
        setCredentialsCopied(true);
        window.setTimeout(() => setCredentialsCopied(false), 2500);
    };

    const downloadCredentials = () => {
        if (!importResult?.credentials.length) {
            return;
        }

        const rows = [
            ['ID Pekerja', 'Nama', 'E-mel', 'Kata Laluan Sementara'],
            ...importResult.credentials.map((credential) => [
                credential.employee_id ?? '',
                credential.name,
                credential.email,
                credential.temporary_password,
            ]),
        ];
        const csv = rows
            .map((row) => row.map((value) => csvCell(value)).join(','))
            .join('\r\n');
        const blob = new Blob([`\uFEFF${csv}`], {
            type: 'text/csv;charset=utf-8',
        });
        const url = URL.createObjectURL(blob);
        const anchor = document.createElement('a');

        anchor.href = url;
        anchor.download = `akaun-pekerja-${new Date()
            .toISOString()
            .slice(0, 10)}.csv`;
        document.body.appendChild(anchor);
        anchor.click();
        anchor.remove();
        URL.revokeObjectURL(url);
    };

    return (
        <>
            <Head title="Import Pekerja" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div className="space-y-2">
                        <HeadingSmall
                            title="Import Pekerja sebagai Pengguna"
                            description="Tarik pekerja aktif daripada db_spp dan daftarkan akaun Employee secara pukal."
                        />
                        <Badge
                            variant="secondary"
                            className="gap-1.5 font-normal"
                        >
                            <ShieldCheck className="size-3.5" />
                            db_spp dibaca sahaja
                        </Badge>
                    </div>

                    <Button asChild variant="outline">
                        <Link href="/pengguna">
                            <ArrowLeft />
                            Kembali
                        </Link>
                    </Button>
                </div>

                {importResult && (
                    <Card className="border-emerald-500/40">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-emerald-700 dark:text-emerald-300">
                                <CheckCircle2 className="size-5" />
                                Import Selesai
                            </CardTitle>
                            <CardDescription>
                                {importResult.created_count} akaun baharu
                                dicipta dan {importResult.linked_count} akaun
                                sedia ada dipautkan ke{' '}
                                {importResult.office_name}.
                            </CardDescription>
                        </CardHeader>

                        {importResult.credentials.length > 0 && (
                            <CardContent className="space-y-4">
                                <Alert>
                                    <AlertTriangle className="size-4" />
                                    <AlertTitle>
                                        Simpan kata laluan sekarang
                                    </AlertTitle>
                                    <AlertDescription>
                                        Kata laluan sementara ini hanya
                                        dipaparkan pada keputusan import ini.
                                        Jadual users hanya menyimpan kata laluan
                                        yang telah di-hash.
                                    </AlertDescription>
                                </Alert>

                                <div className="flex flex-wrap gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => void copyCredentials()}
                                    >
                                        <ClipboardCopy />
                                        {credentialsCopied
                                            ? 'Sudah Disalin'
                                            : 'Salin Akaun Baharu'}
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={downloadCredentials}
                                    >
                                        <Download />
                                        Muat Turun CSV
                                    </Button>
                                </div>

                                <div className="overflow-x-auto rounded-lg border">
                                    <Table>
                                        <TableHeader className="bg-muted/60">
                                            <TableRow>
                                                <TableHead>Pekerja</TableHead>
                                                <TableHead>E-mel</TableHead>
                                                <TableHead>
                                                    Kata Laluan Sementara
                                                </TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {importResult.credentials.map(
                                                (credential) => (
                                                    <TableRow
                                                        key={credential.email}
                                                    >
                                                        <TableCell>
                                                            <p className="font-medium">
                                                                {
                                                                    credential.name
                                                                }
                                                            </p>
                                                            <p className="text-xs text-muted-foreground">
                                                                {credential.employee_id ||
                                                                    'Tiada ID'}
                                                            </p>
                                                        </TableCell>
                                                        <TableCell>
                                                            {credential.email}
                                                        </TableCell>
                                                        <TableCell className="font-mono">
                                                            {
                                                                credential.temporary_password
                                                            }
                                                        </TableCell>
                                                    </TableRow>
                                                ),
                                            )}
                                        </TableBody>
                                    </Table>
                                </div>
                            </CardContent>
                        )}
                    </Card>
                )}

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <Card>
                        <CardHeader className="gap-1">
                            <CardDescription>Pekerja Aktif</CardDescription>
                            <CardTitle className="text-3xl">
                                {statistics.active_employees.toLocaleString(
                                    'ms-MY',
                                )}
                            </CardTitle>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader className="gap-1">
                            <CardDescription>Sudah Didaftarkan</CardDescription>
                            <CardTitle className="text-3xl">
                                {statistics.already_registered.toLocaleString(
                                    'ms-MY',
                                )}
                            </CardTitle>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader className="gap-1">
                            <CardDescription>Sedia Diimport</CardDescription>
                            <CardTitle className="text-3xl text-emerald-600">
                                {statistics.ready_to_import.toLocaleString(
                                    'ms-MY',
                                )}
                            </CardTitle>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader className="gap-1">
                            <CardDescription>Perlu Semakan</CardDescription>
                            <CardTitle className="text-3xl text-amber-600">
                                {statistics.requires_attention.toLocaleString(
                                    'ms-MY',
                                )}
                            </CardTitle>
                        </CardHeader>
                    </Card>
                </div>

                <form onSubmit={submit} className="space-y-5">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <DatabaseZap className="size-5 text-muted-foreground" />
                                Tetapan Import
                            </CardTitle>
                            <CardDescription>
                                Semua pekerja yang dipilih akan diberikan role
                                Employee dan lokasi geofence yang sama.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-5 md:grid-cols-2">
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
                                        {offices.map((office) => (
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
                                {offices.length === 0 && (
                                    <p className="text-xs text-amber-700 dark:text-amber-300">
                                        Tambah lokasi aktif dalam Tetapan
                                        Kehadiran sebelum menjalankan import.
                                    </p>
                                )}
                            </div>

                            <div className="rounded-lg bg-muted p-4 text-sm">
                                <p className="font-medium">
                                    {data.employee_ids.length.toLocaleString(
                                        'ms-MY',
                                    )}{' '}
                                    pekerja dipilih
                                </p>
                                <p className="mt-1 text-muted-foreground">
                                    Akaun baharu menerima kata laluan rawak yang
                                    dipaparkan sekali selepas import. Akaun
                                    sedia ada mengekalkan semua role dan kata
                                    laluannya.
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="gap-0 overflow-hidden">
                        <CardHeader className="border-b">
                            <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                                <div>
                                    <CardTitle>Senarai Pekerja</CardTitle>
                                    <CardDescription className="mt-1">
                                        Pekerja yang telah mempunyai pautan
                                        aktif tidak dipaparkan.
                                    </CardDescription>
                                </div>
                                <div className="flex w-full flex-col gap-2 lg:max-w-lg">
                                    <div className="flex flex-wrap justify-end gap-2">
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            onClick={() => toggleVisible(true)}
                                            disabled={
                                                processing ||
                                                visibleImportableIds.length ===
                                                    0
                                            }
                                        >
                                            Pilih Semua Yang Layak
                                        </Button>
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            onClick={() =>
                                                setData('employee_ids', [])
                                            }
                                            disabled={
                                                processing ||
                                                data.employee_ids.length === 0
                                            }
                                        >
                                            Kosongkan Pilihan
                                        </Button>
                                    </div>
                                    <div className="relative">
                                        <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                        <Input
                                            value={search}
                                            onChange={(event) =>
                                                setSearch(event.target.value)
                                            }
                                            placeholder="Cari nama, ID atau e-mel..."
                                            className="pl-9"
                                        />
                                    </div>
                                </div>
                            </div>
                        </CardHeader>

                        <div className="hidden overflow-x-auto md:block">
                            <Table>
                                <TableHeader className="bg-muted/60">
                                    <TableRow>
                                        <TableHead className="w-12">
                                            <Checkbox
                                                checked={allVisibleSelected}
                                                onCheckedChange={(value) =>
                                                    toggleVisible(
                                                        value === true,
                                                    )
                                                }
                                                disabled={
                                                    processing ||
                                                    visibleImportableIds.length ===
                                                        0
                                                }
                                                aria-label="Pilih semua pekerja yang layak"
                                            />
                                        </TableHead>
                                        <TableHead>Pekerja</TableHead>
                                        <TableHead>E-mel Login</TableHead>
                                        <TableHead>Status Import</TableHead>
                                        <TableHead>Akaun Sedia Ada</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {visibleEmployees.length > 0 ? (
                                        visibleEmployees.map((employee) => {
                                            const status =
                                                statusDetails[employee.status];

                                            return (
                                                <TableRow key={employee.id}>
                                                    <TableCell>
                                                        <Checkbox
                                                            checked={data.employee_ids.includes(
                                                                employee.id,
                                                            )}
                                                            onCheckedChange={(
                                                                value,
                                                            ) =>
                                                                toggleEmployee(
                                                                    employee.id,
                                                                    value ===
                                                                        true,
                                                                )
                                                            }
                                                            disabled={
                                                                processing ||
                                                                !employee.can_import
                                                            }
                                                            aria-label={`Pilih ${employee.name}`}
                                                        />
                                                    </TableCell>
                                                    <TableCell>
                                                        <p className="font-medium">
                                                            {employee.name}
                                                        </p>
                                                        <p className="text-xs text-muted-foreground">
                                                            {employee.employee_id ||
                                                                `#${employee.id}`}
                                                        </p>
                                                    </TableCell>
                                                    <TableCell>
                                                        {employee.email || (
                                                            <span className="text-muted-foreground">
                                                                Tiada e-mel
                                                            </span>
                                                        )}
                                                    </TableCell>
                                                    <TableCell>
                                                        <Badge
                                                            variant="outline"
                                                            className={
                                                                status.className
                                                            }
                                                        >
                                                            {status.label}
                                                        </Badge>
                                                        <p className="mt-1 max-w-sm text-xs text-muted-foreground">
                                                            {status.description}
                                                        </p>
                                                    </TableCell>
                                                    <TableCell>
                                                        {employee.existing_user ? (
                                                            <div>
                                                                <p className="font-medium">
                                                                    {
                                                                        employee
                                                                            .existing_user
                                                                            .name
                                                                    }
                                                                </p>
                                                                <div className="mt-1 flex flex-wrap gap-1">
                                                                    {employee.existing_user.roles.map(
                                                                        (
                                                                            role,
                                                                        ) => (
                                                                            <Badge
                                                                                key={
                                                                                    role
                                                                                }
                                                                                variant="secondary"
                                                                                className="font-normal"
                                                                            >
                                                                                {
                                                                                    roleLabels[
                                                                                        role
                                                                                    ]
                                                                                }
                                                                            </Badge>
                                                                        ),
                                                                    )}
                                                                </div>
                                                            </div>
                                                        ) : (
                                                            <span className="text-sm text-muted-foreground">
                                                                —
                                                            </span>
                                                        )}
                                                    </TableCell>
                                                </TableRow>
                                            );
                                        })
                                    ) : (
                                        <TableRow>
                                            <TableCell
                                                colSpan={5}
                                                className="h-28 text-center text-muted-foreground"
                                            >
                                                Tiada pekerja ditemui.
                                            </TableCell>
                                        </TableRow>
                                    )}
                                </TableBody>
                            </Table>
                        </div>

                        <CardContent className="space-y-3 py-4 md:hidden">
                            {visibleEmployees.length > 0 ? (
                                visibleEmployees.map((employee) => {
                                    const status =
                                        statusDetails[employee.status];

                                    return (
                                        <label
                                            key={employee.id}
                                            className={`flex items-start gap-3 rounded-lg border p-4 ${
                                                employee.can_import
                                                    ? 'cursor-pointer'
                                                    : 'opacity-70'
                                            }`}
                                        >
                                            <Checkbox
                                                checked={data.employee_ids.includes(
                                                    employee.id,
                                                )}
                                                onCheckedChange={(value) =>
                                                    toggleEmployee(
                                                        employee.id,
                                                        value === true,
                                                    )
                                                }
                                                disabled={
                                                    processing ||
                                                    !employee.can_import
                                                }
                                                aria-label={`Pilih ${employee.name}`}
                                            />
                                            <span className="min-w-0 space-y-2">
                                                <span>
                                                    <span className="block font-medium">
                                                        {employee.name}
                                                    </span>
                                                    <span className="block text-xs text-muted-foreground">
                                                        {employee.employee_id ||
                                                            `#${employee.id}`}
                                                        {employee.email
                                                            ? ` · ${employee.email}`
                                                            : ' · Tiada e-mel'}
                                                    </span>
                                                </span>
                                                <Badge
                                                    variant="outline"
                                                    className={status.className}
                                                >
                                                    {status.label}
                                                </Badge>
                                                <span className="block text-xs text-muted-foreground">
                                                    {status.description}
                                                </span>
                                            </span>
                                        </label>
                                    );
                                })
                            ) : (
                                <p className="py-8 text-center text-sm text-muted-foreground">
                                    Tiada pekerja ditemui.
                                </p>
                            )}
                        </CardContent>
                    </Card>

                    <InputError message={errors.employee_ids} />

                    <div className="flex flex-col-reverse gap-3 border-t pt-5 sm:flex-row sm:items-center sm:justify-between">
                        <p className="text-sm text-muted-foreground">
                            Maksimum 200 pekerja bagi setiap proses import.
                        </p>
                        <Button
                            type="submit"
                            disabled={
                                processing ||
                                data.employee_ids.length === 0 ||
                                !data.office_location_id ||
                                offices.length === 0
                            }
                        >
                            <UserPlus />
                            {processing
                                ? 'Mengimport...'
                                : `Import ${data.employee_ids.length} Pekerja`}
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

ImportEmployees.layout = {
    breadcrumbs: [
        { title: 'Roles & Permissions', href: '/pengguna' },
        { title: 'Import Pekerja', href: '/pengguna/import-pekerja' },
    ],
};
