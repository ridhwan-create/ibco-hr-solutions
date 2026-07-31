import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    Check,
    Download,
    FileText,
    ReceiptText,
    Search,
    WalletCards,
    X,
} from 'lucide-react';
import type { FormEvent } from 'react';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type Claim = {
    id: number;
    employee_number: string | null;
    employee_name: string;
    department_id: number | null;
    claim_type: string;
    expense_date: string;
    merchant_name: string | null;
    receipt_number: string | null;
    requested_amount: number;
    approved_amount: number | null;
    description: string;
    status: 'pending' | 'approved' | 'rejected' | 'cancelled';
    approval_stage: 'supervisor' | 'finance' | 'completed';
    supervisor_name: string | null;
    supervisor_review_notes: string | null;
    reviewer_name: string | null;
    review_notes: string | null;
    scheduled_payroll_period: string | null;
    payroll_status: string | null;
    attachments: {
        id: number;
        name: string;
        size: number;
        download_url: string;
    }[];
};

type Props = {
    requests: {
        data: Claim[];
        links: { url: string | null; label: string; active: boolean }[];
        from: number | null;
        to: number | null;
        total: number;
    };
    filters: {
        month: string;
        status: string;
        stage: string;
        claim_type_id: string;
        department_id: string;
        search: string;
    };
    statistics: {
        total: number;
        pending_supervisor: number;
        pending_finance: number;
        approved: number;
        approved_amount: number;
        scheduled_amount: number;
    };
    claimTypes: { id: number; name: string }[];
    departments: { id: number; name: string }[];
    reportByType: {
        claim_type: string;
        status: string;
        total: number;
        requested_amount: number;
        approved_amount: number;
    }[];
    payrollPeriods: { id: number; period: string; label: string }[];
    permissions: { can_supervise: boolean; can_manage: boolean };
};

function money(value: number): string {
    return new Intl.NumberFormat('ms-MY', {
        style: 'currency',
        currency: 'MYR',
    }).format(value);
}

function date(value: string): string {
    return new Intl.DateTimeFormat('ms-MY', { dateStyle: 'medium' }).format(
        new Date(value),
    );
}

function paginationLabel(label: string): string {
    return label
        .replace('&laquo; Previous', 'Sebelum')
        .replace('Next &raquo;', 'Seterusnya');
}

function HrReview({
    claim,
    payrollPeriods,
}: {
    claim: Claim;
    payrollPeriods: Props['payrollPeriods'];
}) {
    const form = useForm({
        status: 'approved',
        approved_amount: String(claim.requested_amount),
        scheduled_payroll_period: '',
        review_notes: '',
    });

    const submit = (status: 'approved' | 'rejected') => {
        form.transform((data) => ({ ...data, status }));
        form.patch(`/permohonan-tuntutan/${claim.id}/semakan`, {
            preserveScroll: true,
        });
    };

    return (
        <div className="mt-4 grid gap-3 rounded-lg border bg-muted/20 p-3 md:grid-cols-4">
            <div className="space-y-1">
                <Label>Amaun diluluskan</Label>
                <Input
                    type="number"
                    min="0.01"
                    step="0.01"
                    value={form.data.approved_amount}
                    onChange={(event) =>
                        form.setData('approved_amount', event.target.value)
                    }
                />
            </div>
            <div className="space-y-1">
                <Label>Payroll (pilihan)</Label>
                <Select
                    value={form.data.scheduled_payroll_period || 'none'}
                    onValueChange={(value) =>
                        form.setData(
                            'scheduled_payroll_period',
                            value === 'none' ? '' : value,
                        )
                    }
                >
                    <SelectTrigger>
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="none">Bayar berasingan</SelectItem>
                        {payrollPeriods.map((period) => (
                            <SelectItem key={period.id} value={period.period}>
                                {period.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>
            <div className="space-y-1 md:col-span-2">
                <Label>Catatan keputusan</Label>
                <Input
                    value={form.data.review_notes}
                    onChange={(event) =>
                        form.setData('review_notes', event.target.value)
                    }
                    placeholder="Wajib jika ditolak"
                />
            </div>
            <div className="flex flex-wrap gap-2 md:col-span-4">
                <Button
                    size="sm"
                    onClick={() => submit('approved')}
                    disabled={form.processing}
                >
                    <Check />
                    Luluskan
                </Button>
                <Button
                    size="sm"
                    variant="destructive"
                    onClick={() => submit('rejected')}
                    disabled={form.processing}
                >
                    <X />
                    Tolak
                </Button>
                {Object.values(form.errors).map((error) => (
                    <p key={error} className="text-xs text-destructive">
                        {error}
                    </p>
                ))}
            </div>
        </div>
    );
}

function PayrollSchedule({
    claim,
    payrollPeriods,
}: {
    claim: Claim;
    payrollPeriods: Props['payrollPeriods'];
}) {
    const form = useForm({
        scheduled_payroll_period: claim.scheduled_payroll_period ?? '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.patch(`/permohonan-tuntutan/${claim.id}/jadual-payroll`, {
            preserveScroll: true,
        });
    };

    return (
        <form onSubmit={submit} className="mt-3 flex max-w-xl gap-2">
            <Select
                value={form.data.scheduled_payroll_period || 'none'}
                onValueChange={(value) =>
                    form.setData(
                        'scheduled_payroll_period',
                        value === 'none' ? '' : value,
                    )
                }
            >
                <SelectTrigger className="w-56">
                    <SelectValue placeholder="Pilih payroll" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="none">Bayar berasingan</SelectItem>
                    {payrollPeriods.map((period) => (
                        <SelectItem key={period.id} value={period.period}>
                            {period.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <Button type="submit" size="sm" variant="outline">
                <WalletCards />
                Simpan Jadual
            </Button>
        </form>
    );
}

export default function Index({
    requests,
    filters,
    statistics,
    claimTypes,
    departments,
    reportByType,
    payrollPeriods,
    permissions,
}: Props) {
    const filterForm = useForm(filters);

    const applyFilters = (event: FormEvent) => {
        event.preventDefault();
        router.get('/permohonan-tuntutan', filterForm.data, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const supervisorReview = (
        claim: Claim,
        status: 'approved' | 'rejected',
    ) => {
        const notes =
            status === 'rejected'
                ? window.prompt('Nyatakan sebab penolakan:')
                : window.prompt('Catatan penyelia (pilihan):', '');

        if (notes === null || (status === 'rejected' && notes.trim() === '')) {
            return;
        }

        router.patch(
            `/permohonan-tuntutan/${claim.id}/semakan-penyelia`,
            { status, review_notes: notes },
            { preserveScroll: true },
        );
    };

    const cancelApproved = (claim: Claim) => {
        const notes = window.prompt('Nyatakan sebab pembatalan kelulusan:');

        if (!notes || notes.trim().length < 5) {
            return;
        }

        router.patch(
            `/permohonan-tuntutan/${claim.id}/batal-kelulusan`,
            { cancellation_notes: notes },
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Permohonan Tuntutan" />
            <div className="space-y-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Permohonan Tuntutan
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Kelulusan Penyelia → HR/Kewangan dan penjadualan
                            bayaran melalui payroll.
                        </p>
                    </div>
                    <Button asChild variant="outline">
                        <a
                            href={`/permohonan-tuntutan/laporan.csv?${new URLSearchParams(filters)}`}
                        >
                            <Download />
                            Eksport CSV
                        </a>
                    </Button>
                </div>

                <div className="grid gap-4 md:grid-cols-3 xl:grid-cols-6">
                    {[
                        ['Jumlah', statistics.total],
                        ['Menunggu Penyelia', statistics.pending_supervisor],
                        ['Menunggu HR/Kewangan', statistics.pending_finance],
                        ['Diluluskan', statistics.approved],
                        ['Amaun Diluluskan', money(statistics.approved_amount)],
                        [
                            'Menunggu Payroll',
                            money(statistics.scheduled_amount),
                        ],
                    ].map(([label, value]) => (
                        <Card key={String(label)}>
                            <CardContent className="pt-6">
                                <p className="text-xs text-muted-foreground">
                                    {label}
                                </p>
                                <p className="mt-1 text-xl font-semibold">
                                    {value}
                                </p>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Tapisan</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form
                            onSubmit={applyFilters}
                            className="grid gap-3 md:grid-cols-3 xl:grid-cols-6"
                        >
                            <Input
                                type="month"
                                value={filterForm.data.month}
                                onChange={(event) =>
                                    filterForm.setData(
                                        'month',
                                        event.target.value,
                                    )
                                }
                            />
                            <Select
                                value={filterForm.data.status || 'all'}
                                onValueChange={(value) =>
                                    filterForm.setData(
                                        'status',
                                        value === 'all' ? '' : value,
                                    )
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        Semua status
                                    </SelectItem>
                                    <SelectItem value="pending">
                                        Menunggu
                                    </SelectItem>
                                    <SelectItem value="approved">
                                        Diluluskan
                                    </SelectItem>
                                    <SelectItem value="rejected">
                                        Ditolak
                                    </SelectItem>
                                    <SelectItem value="cancelled">
                                        Dibatalkan
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <Select
                                value={filterForm.data.claim_type_id || 'all'}
                                onValueChange={(value) =>
                                    filterForm.setData(
                                        'claim_type_id',
                                        value === 'all' ? '' : value,
                                    )
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Jenis" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        Semua jenis
                                    </SelectItem>
                                    {claimTypes.map((type) => (
                                        <SelectItem
                                            key={type.id}
                                            value={String(type.id)}
                                        >
                                            {type.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Select
                                value={filterForm.data.department_id || 'all'}
                                onValueChange={(value) =>
                                    filterForm.setData(
                                        'department_id',
                                        value === 'all' ? '' : value,
                                    )
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Jabatan" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        Semua jabatan
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
                            <Input
                                value={filterForm.data.search}
                                onChange={(event) =>
                                    filterForm.setData(
                                        'search',
                                        event.target.value,
                                    )
                                }
                                placeholder="Nama, ID atau resit"
                            />
                            <Button type="submit">
                                <Search />
                                Tapis
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <div className="grid gap-6 xl:grid-cols-[minmax(0,1.5fr)_minmax(18rem,0.5fr)]">
                    <Card>
                        <CardHeader>
                            <CardTitle>Senarai Permohonan</CardTitle>
                            <CardDescription>
                                {requests.from ?? 0}–{requests.to ?? 0} daripada{' '}
                                {requests.total} rekod.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {requests.data.length === 0 && (
                                <p className="text-sm text-muted-foreground">
                                    Tiada tuntutan bagi tapisan ini.
                                </p>
                            )}
                            {requests.data.map((claim) => (
                                <div
                                    key={claim.id}
                                    className="rounded-xl border p-4"
                                >
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <div className="flex flex-wrap items-center gap-2">
                                                <p className="font-semibold">
                                                    {claim.employee_name}
                                                </p>
                                                <Badge variant="outline">
                                                    {claim.claim_type}
                                                </Badge>
                                                <Badge
                                                    variant={
                                                        claim.status ===
                                                        'approved'
                                                            ? 'default'
                                                            : claim.status ===
                                                                'pending'
                                                              ? 'secondary'
                                                              : 'destructive'
                                                    }
                                                >
                                                    {claim.status}
                                                </Badge>
                                            </div>
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                {claim.employee_number ??
                                                    `#${claim.id}`}{' '}
                                                · {date(claim.expense_date)} ·{' '}
                                                {claim.merchant_name ??
                                                    'Tiada peniaga'}
                                            </p>
                                        </div>
                                        <div className="text-right">
                                            <p className="font-semibold">
                                                {money(
                                                    claim.approved_amount ??
                                                        claim.requested_amount,
                                                )}
                                            </p>
                                            {claim.approved_amount !== null && (
                                                <p className="text-xs text-muted-foreground">
                                                    Dipohon{' '}
                                                    {money(
                                                        claim.requested_amount,
                                                    )}
                                                </p>
                                            )}
                                        </div>
                                    </div>
                                    <p className="mt-3 text-sm">
                                        {claim.description}
                                    </p>
                                    <p className="mt-2 text-xs text-muted-foreground">
                                        Resit:{' '}
                                        {claim.receipt_number ??
                                            'Tidak dinyatakan'}
                                    </p>
                                    <div className="mt-3 flex flex-wrap gap-2">
                                        {claim.attachments.map((attachment) => (
                                            <Button
                                                key={attachment.id}
                                                asChild
                                                size="sm"
                                                variant="outline"
                                            >
                                                <Link
                                                    href={
                                                        attachment.download_url
                                                    }
                                                >
                                                    <FileText />
                                                    {attachment.name}
                                                </Link>
                                            </Button>
                                        ))}
                                    </div>

                                    {claim.status === 'pending' &&
                                        claim.approval_stage === 'supervisor' &&
                                        permissions.can_supervise && (
                                            <div className="mt-4 flex gap-2">
                                                <Button
                                                    size="sm"
                                                    onClick={() =>
                                                        supervisorReview(
                                                            claim,
                                                            'approved',
                                                        )
                                                    }
                                                >
                                                    <Check />
                                                    Sokong
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="destructive"
                                                    onClick={() =>
                                                        supervisorReview(
                                                            claim,
                                                            'rejected',
                                                        )
                                                    }
                                                >
                                                    <X />
                                                    Tolak
                                                </Button>
                                            </div>
                                        )}

                                    {claim.status === 'pending' &&
                                        claim.approval_stage === 'finance' &&
                                        permissions.can_manage && (
                                            <HrReview
                                                claim={claim}
                                                payrollPeriods={payrollPeriods}
                                            />
                                        )}

                                    {claim.status === 'approved' &&
                                        permissions.can_manage && (
                                            <div>
                                                <PayrollSchedule
                                                    claim={claim}
                                                    payrollPeriods={
                                                        payrollPeriods
                                                    }
                                                />
                                                {!claim.payroll_status ||
                                                claim.payroll_status ===
                                                    'scheduled' ||
                                                claim.payroll_status ===
                                                    'draft' ? (
                                                    <Button
                                                        className="mt-2"
                                                        size="sm"
                                                        variant="destructive"
                                                        onClick={() =>
                                                            cancelApproved(
                                                                claim,
                                                            )
                                                        }
                                                    >
                                                        Batalkan Kelulusan
                                                    </Button>
                                                ) : null}
                                            </div>
                                        )}
                                </div>
                            ))}

                            <div className="flex flex-wrap gap-2">
                                {requests.links.map((link, index) => (
                                    <Button
                                        key={`${link.label}-${index}`}
                                        asChild={Boolean(link.url)}
                                        disabled={!link.url}
                                        size="sm"
                                        variant={
                                            link.active ? 'default' : 'outline'
                                        }
                                    >
                                        {link.url ? (
                                            <Link href={link.url}>
                                                {paginationLabel(link.label)}
                                            </Link>
                                        ) : (
                                            <span>
                                                {paginationLabel(link.label)}
                                            </span>
                                        )}
                                    </Button>
                                ))}
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="h-fit">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <ReceiptText className="size-5" />
                                Ringkasan Jenis
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {reportByType.map((row, index) => (
                                <div
                                    key={`${row.claim_type}-${row.status}-${index}`}
                                    className="rounded-lg border p-3 text-sm"
                                >
                                    <div className="flex justify-between gap-2">
                                        <span className="font-medium">
                                            {row.claim_type}
                                        </span>
                                        <Badge variant="outline">
                                            {row.status}
                                        </Badge>
                                    </div>
                                    <p className="mt-2 text-xs text-muted-foreground">
                                        {row.total} rekod · Dipohon{' '}
                                        {money(row.requested_amount)} ·
                                        Diluluskan {money(row.approved_amount)}
                                    </p>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}
