import { Head, Link, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    CheckCircle2,
    ClipboardCopy,
    Download,
    KeyRound,
    Search,
    ShieldCheck,
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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { UserRole } from '@/types';

type ResettableUser = {
    id: number;
    name: string;
    email: string;
    roles: UserRole[];
    role_labels: string[];
    employee_id: number | null;
    office: string | null;
};

type Credential = {
    user_id: number;
    name: string;
    email: string;
    temporary_password: string;
};

type ResetResult = {
    reset_count: number;
    credentials: Credential[];
};

type ResetPasswordsProps = {
    users: ResettableUser[];
    resetResult: ResetResult | null;
};

type ResetForm = {
    user_ids: number[];
};

const roleStyles: Record<UserRole, string> = {
    super_admin:
        'border-violet-500/30 bg-violet-500/10 text-violet-700 dark:text-violet-300',
    hr_manager:
        'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300',
    hr_admin:
        'border-blue-500/30 bg-blue-500/10 text-blue-700 dark:text-blue-300',
    supervisor:
        'border-cyan-500/30 bg-cyan-500/10 text-cyan-700 dark:text-cyan-300',
    viewer: 'border-slate-500/30 bg-slate-500/10 text-slate-700 dark:text-slate-300',
    employee:
        'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
};

function csvCell(value: string | number): string {
    let safeValue = String(value);

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

function RoleBadges({ user }: { user: ResettableUser }) {
    return (
        <div className="flex flex-wrap gap-1.5">
            {user.roles.map((role, index) => (
                <Badge
                    key={role}
                    variant="outline"
                    className={roleStyles[role]}
                >
                    {user.role_labels[index]}
                </Badge>
            ))}
        </div>
    );
}

export default function ResetPasswords({
    users,
    resetResult,
}: ResetPasswordsProps) {
    const [search, setSearch] = useState('');
    const [credentialsCopied, setCredentialsCopied] = useState(false);
    const { data, setData, post, processing, errors } = useForm<ResetForm>({
        user_ids: [],
    });
    const visibleUsers = useMemo(() => {
        const term = search.trim().toLocaleLowerCase('ms-MY');

        if (!term) {
            return users;
        }

        return users.filter((user) =>
            [
                user.name,
                user.email,
                user.office ?? '',
                ...user.role_labels,
            ].some((value) => value.toLocaleLowerCase('ms-MY').includes(term)),
        );
    }, [search, users]);
    const visibleUserIds = visibleUsers.map((user) => user.id);
    const allVisibleSelected =
        visibleUserIds.length > 0 &&
        visibleUserIds.every((id) => data.user_ids.includes(id));

    const toggleUser = (userId: number, checked: boolean) => {
        if (checked && data.user_ids.length >= 200) {
            return;
        }

        setData(
            'user_ids',
            checked
                ? [...new Set([...data.user_ids, userId])]
                : data.user_ids.filter((id) => id !== userId),
        );
    };

    const toggleVisible = (checked: boolean) => {
        if (!checked) {
            setData(
                'user_ids',
                data.user_ids.filter((id) => !visibleUserIds.includes(id)),
            );

            return;
        }

        setData(
            'user_ids',
            [...new Set([...data.user_ids, ...visibleUserIds])].slice(0, 200),
        );
    };

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (
            !window.confirm(
                `Tetapkan semula kata laluan untuk ${data.user_ids.length} pengguna? Kata laluan lama mereka tidak lagi boleh digunakan.`,
            )
        ) {
            return;
        }

        post('/pengguna/reset-kata-laluan', {
            preserveScroll: true,
        });
    };

    const copyCredentials = async () => {
        if (!resetResult?.credentials.length) {
            return;
        }

        await navigator.clipboard.writeText(
            credentialText(resetResult.credentials),
        );
        setCredentialsCopied(true);
        window.setTimeout(() => setCredentialsCopied(false), 2500);
    };

    const downloadCredentials = () => {
        if (!resetResult?.credentials.length) {
            return;
        }

        const rows = [
            ['ID Pengguna', 'Nama', 'E-mel', 'Kata Laluan Sementara'],
            ...resetResult.credentials.map((credential) => [
                credential.user_id,
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
        anchor.download = `reset-kata-laluan-${new Date()
            .toISOString()
            .slice(0, 10)}.csv`;
        document.body.appendChild(anchor);
        anchor.click();
        anchor.remove();
        URL.revokeObjectURL(url);
    };

    return (
        <>
            <Head title="Reset Kata Laluan Pukal" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div className="space-y-2">
                        <HeadingSmall
                            title="Reset Kata Laluan Pukal"
                            description="Jana kata laluan sementara baharu untuk pengguna Employee yang dipautkan."
                        />
                        <Badge
                            variant="secondary"
                            className="gap-1.5 font-normal"
                        >
                            <ShieldCheck className="size-3.5" />
                            Tiada perubahan pada role, pautan atau db_spp
                        </Badge>
                    </div>

                    <Button asChild variant="outline">
                        <Link href="/pengguna">
                            <ArrowLeft />
                            Kembali ke Pengguna
                        </Link>
                    </Button>
                </div>

                {resetResult && resetResult.credentials.length > 0 && (
                    <Card className="border-emerald-500/30 bg-emerald-500/5">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-emerald-700 dark:text-emerald-300">
                                <CheckCircle2 className="size-5" />
                                {resetResult.reset_count} Kata Laluan Berjaya
                                Ditetapkan Semula
                            </CardTitle>
                            <CardDescription>
                                Muat turun CSV sekarang. Kata laluan sementara
                                ini hanya dipaparkan pada halaman ini dan tidak
                                boleh dipulihkan selepas halaman dimuat semula.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <Alert>
                                <AlertTriangle className="size-4" />
                                <AlertTitle>Simpan sebelum keluar</AlertTitle>
                                <AlertDescription>
                                    Fail CSV mengandungi maklumat sulit. Simpan
                                    di lokasi selamat dan serahkan kata laluan
                                    kepada pengguna masing-masing.
                                </AlertDescription>
                            </Alert>

                            <div className="flex flex-wrap gap-2">
                                <Button
                                    type="button"
                                    onClick={downloadCredentials}
                                >
                                    <Download />
                                    Muat Turun CSV
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={copyCredentials}
                                >
                                    <ClipboardCopy />
                                    {credentialsCopied
                                        ? 'Sudah Disalin'
                                        : 'Salin Senarai'}
                                </Button>
                            </div>

                            <div className="max-h-64 overflow-auto rounded-lg border bg-background">
                                <Table>
                                    <TableHeader className="bg-muted/60">
                                        <TableRow>
                                            <TableHead>Nama</TableHead>
                                            <TableHead>E-mel</TableHead>
                                            <TableHead>
                                                Kata Laluan Sementara
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {resetResult.credentials.map(
                                            (credential) => (
                                                <TableRow
                                                    key={credential.user_id}
                                                >
                                                    <TableCell className="font-medium">
                                                        {credential.name}
                                                    </TableCell>
                                                    <TableCell>
                                                        {credential.email}
                                                    </TableCell>
                                                    <TableCell>
                                                        <code className="rounded bg-muted px-2 py-1 text-xs">
                                                            {
                                                                credential.temporary_password
                                                            }
                                                        </code>
                                                    </TableCell>
                                                </TableRow>
                                            ),
                                        )}
                                    </TableBody>
                                </Table>
                            </div>
                        </CardContent>
                    </Card>
                )}

                <Alert>
                    <KeyRound className="size-4" />
                    <AlertTitle>Reset terkawal</AlertTitle>
                    <AlertDescription>
                        Hanya pengguna yang mempunyai role Employee dan pautan
                        pekerja aktif disenaraikan. Akaun anda sendiri
                        dikecualikan untuk mengelakkan kehilangan akses Super
                        Admin. Maksimum 200 pengguna bagi setiap proses.
                    </AlertDescription>
                </Alert>

                <form onSubmit={submit} className="space-y-4">
                    <Card className="gap-0 overflow-hidden">
                        <CardHeader className="border-b">
                            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <CardTitle>
                                        Pilih Pengguna Employee
                                    </CardTitle>
                                    <CardDescription>
                                        {data.user_ids.length} daripada{' '}
                                        {users.length} pengguna dipilih
                                    </CardDescription>
                                </div>
                                <div className="relative w-full sm:max-w-sm">
                                    <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        value={search}
                                        onChange={(event) =>
                                            setSearch(event.target.value)
                                        }
                                        placeholder="Cari nama, e-mel, role atau pejabat..."
                                        className="pl-9"
                                        aria-label="Cari pengguna untuk reset"
                                    />
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
                                                onCheckedChange={(checked) =>
                                                    toggleVisible(
                                                        checked === true,
                                                    )
                                                }
                                                aria-label="Pilih semua pengguna dipaparkan"
                                            />
                                        </TableHead>
                                        <TableHead>Pengguna</TableHead>
                                        <TableHead>Role</TableHead>
                                        <TableHead>Lokasi Pejabat</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {visibleUsers.length > 0 ? (
                                        visibleUsers.map((user) => (
                                            <TableRow key={user.id}>
                                                <TableCell>
                                                    <Checkbox
                                                        checked={data.user_ids.includes(
                                                            user.id,
                                                        )}
                                                        disabled={
                                                            !data.user_ids.includes(
                                                                user.id,
                                                            ) &&
                                                            data.user_ids
                                                                .length >= 200
                                                        }
                                                        onCheckedChange={(
                                                            checked,
                                                        ) =>
                                                            toggleUser(
                                                                user.id,
                                                                checked ===
                                                                    true,
                                                            )
                                                        }
                                                        aria-label={`Pilih ${user.name}`}
                                                    />
                                                </TableCell>
                                                <TableCell>
                                                    <p className="font-medium">
                                                        {user.name}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {user.email}
                                                    </p>
                                                </TableCell>
                                                <TableCell>
                                                    <RoleBadges user={user} />
                                                </TableCell>
                                                <TableCell>
                                                    {user.office ??
                                                        'Belum ditetapkan'}
                                                </TableCell>
                                            </TableRow>
                                        ))
                                    ) : (
                                        <TableRow>
                                            <TableCell
                                                colSpan={4}
                                                className="h-28 text-center text-muted-foreground"
                                            >
                                                Tiada pengguna ditemui.
                                            </TableCell>
                                        </TableRow>
                                    )}
                                </TableBody>
                            </Table>
                        </div>

                        <CardContent className="space-y-3 py-4 md:hidden">
                            <div className="flex items-center gap-2 rounded-lg border p-3">
                                <Checkbox
                                    checked={allVisibleSelected}
                                    onCheckedChange={(checked) =>
                                        toggleVisible(checked === true)
                                    }
                                    aria-label="Pilih semua pengguna dipaparkan"
                                />
                                <span className="text-sm font-medium">
                                    Pilih semua yang dipaparkan
                                </span>
                            </div>

                            {visibleUsers.length > 0 ? (
                                visibleUsers.map((user) => (
                                    <label
                                        key={user.id}
                                        className="flex gap-3 rounded-lg border p-4"
                                    >
                                        <Checkbox
                                            checked={data.user_ids.includes(
                                                user.id,
                                            )}
                                            disabled={
                                                !data.user_ids.includes(
                                                    user.id,
                                                ) && data.user_ids.length >= 200
                                            }
                                            onCheckedChange={(checked) =>
                                                toggleUser(
                                                    user.id,
                                                    checked === true,
                                                )
                                            }
                                            aria-label={`Pilih ${user.name}`}
                                        />
                                        <span className="min-w-0 space-y-2">
                                            <span className="block">
                                                <span className="block font-medium">
                                                    {user.name}
                                                </span>
                                                <span className="block text-sm break-all text-muted-foreground">
                                                    {user.email}
                                                </span>
                                            </span>
                                            <RoleBadges user={user} />
                                            <span className="block text-xs text-muted-foreground">
                                                {user.office ??
                                                    'Lokasi belum ditetapkan'}
                                            </span>
                                        </span>
                                    </label>
                                ))
                            ) : (
                                <p className="py-8 text-center text-sm text-muted-foreground">
                                    Tiada pengguna ditemui.
                                </p>
                            )}
                        </CardContent>
                    </Card>

                    <InputError message={errors.user_ids} />

                    <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                        <Button asChild type="button" variant="outline">
                            <Link href="/pengguna">Batal</Link>
                        </Button>
                        <Button
                            type="submit"
                            disabled={processing || data.user_ids.length === 0}
                        >
                            <KeyRound />
                            {processing
                                ? 'Sedang Menetapkan Semula...'
                                : `Reset ${data.user_ids.length} Kata Laluan`}
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

ResetPasswords.layout = {
    breadcrumbs: [
        {
            title: 'Roles & Permissions',
            href: '/pengguna',
        },
        {
            title: 'Reset Kata Laluan Pukal',
            href: '/pengguna/reset-kata-laluan',
        },
    ],
};
