import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    BellRing,
    CheckCircle2,
    Clock3,
    FileText,
    ReceiptText,
    Send,
    WalletCards,
    XCircle,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type ClaimType = {
    id: number;
    code: string;
    name: string;
    description: string | null;
    requires_receipt: boolean;
    requires_receipt_number: boolean;
    allow_payroll_reimbursement: boolean;
    max_per_claim: number | null;
    monthly_limit: number | null;
    annual_limit: number | null;
    source: string;
    month_used: number;
    year_used: number;
};

type Claim = {
    id: number;
    claim_type: string;
    expense_date: string;
    merchant_name: string | null;
    receipt_number: string | null;
    requested_amount: number;
    approved_amount: number | null;
    description: string;
    status: 'pending' | 'approved' | 'rejected' | 'cancelled';
    approval_stage: string;
    submitted_at: string;
    supervisor_name: string | null;
    supervisor_review_notes: string | null;
    reviewer_name: string | null;
    review_notes: string | null;
    scheduled_payroll_period: string | null;
    payroll_status: string | null;
    paid_at: string | null;
    attachments: {
        id: number;
        name: string;
        mime_type: string;
        size: number;
        download_url: string;
    }[];
};

type Props = {
    employee: {
        id: number;
        employee_id: string | null;
        name: string;
    } | null;
    claimTypes: ClaimType[];
    summary: {
        pending: number;
        approved: number;
        approved_amount: number;
        scheduled_amount: number;
        unread_notifications: number;
    };
    requests: Claim[];
    notifications: {
        id: number;
        title: string;
        message: string;
        read_at: string | null;
        created_at: string;
    }[];
};

const statusLabels: Record<Claim['status'], string> = {
    pending: 'Menunggu',
    approved: 'Diluluskan',
    rejected: 'Ditolak',
    cancelled: 'Dibatalkan',
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

function statusVariant(status: Claim['status']) {
    if (status === 'approved') {
        return 'default';
    }

    if (status === 'pending') {
        return 'secondary';
    }

    return 'destructive';
}

export default function Claims({
    employee,
    claimTypes,
    summary,
    requests,
    notifications,
}: Props) {
    const form = useForm<{
        claim_type_id: string;
        expense_date: string;
        merchant_name: string;
        receipt_number: string;
        requested_amount: string;
        description: string;
        receipts: File[];
    }>({
        claim_type_id: '',
        expense_date: new Date().toISOString().slice(0, 10),
        merchant_name: '',
        receipt_number: '',
        requested_amount: '',
        description: '',
        receipts: [],
    });
    const selectedType = claimTypes.find(
        (type) => String(type.id) === form.data.claim_type_id,
    );
    const summaryCards: {
        label: string;
        value: number | string;
        icon: LucideIcon;
    }[] = [
        { label: 'Menunggu', value: summary.pending, icon: Clock3 },
        {
            label: 'Diluluskan',
            value: summary.approved,
            icon: CheckCircle2,
        },
        {
            label: 'Jumlah Diluluskan',
            value: money(summary.approved_amount),
            icon: WalletCards,
        },
        {
            label: 'Menunggu Payroll',
            value: money(summary.scheduled_amount),
            icon: ReceiptText,
        },
    ];

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post('/tuntutan-saya', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () =>
                form.reset(
                    'claim_type_id',
                    'merchant_name',
                    'receipt_number',
                    'requested_amount',
                    'description',
                    'receipts',
                ),
        });
    };

    const cancelClaim = (claim: Claim) => {
        if (!window.confirm(`Batalkan tuntutan ${claim.claim_type}?`)) {
            return;
        }

        router.patch(
            `/tuntutan-saya/${claim.id}/batal`,
            {},
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Tuntutan Saya" />
            <div className="space-y-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Tuntutan Saya
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Mohon bayaran balik, muat naik resit dan semak status
                        kelulusan.
                    </p>
                </div>

                {!employee ? (
                    <Card className="border-amber-500/40 bg-amber-500/5">
                        <CardContent className="pt-6">
                            Akaun anda belum dipautkan kepada rekod pekerja
                            aktif. Hubungi HR untuk melengkapkan pautan.
                        </CardContent>
                    </Card>
                ) : (
                    <>
                        <div className="grid gap-4 md:grid-cols-4">
                            {summaryCards.map((card) => {
                                const Icon = card.icon;

                                return (
                                    <Card key={card.label}>
                                        <CardContent className="flex items-center gap-3 pt-6">
                                            <span className="rounded-full bg-primary/10 p-2">
                                                <Icon className="size-5 text-primary" />
                                            </span>
                                            <div>
                                                <p className="text-xs text-muted-foreground">
                                                    {card.label}
                                                </p>
                                                <p className="text-xl font-semibold">
                                                    {card.value}
                                                </p>
                                            </div>
                                        </CardContent>
                                    </Card>
                                );
                            })}
                        </div>

                        <div className="grid gap-6 xl:grid-cols-[minmax(0,1.05fr)_minmax(0,0.95fr)]">
                            <Card>
                                <CardHeader>
                                    <CardTitle>Permohonan Baharu</CardTitle>
                                    <CardDescription>
                                        Maksimum lima resit PDF/JPG/PNG, 5 MB
                                        setiap fail.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <form
                                        onSubmit={submit}
                                        className="space-y-4"
                                    >
                                        <div className="grid gap-4 md:grid-cols-2">
                                            <div className="space-y-2">
                                                <Label>Jenis tuntutan</Label>
                                                <Select
                                                    value={
                                                        form.data.claim_type_id
                                                    }
                                                    onValueChange={(value) =>
                                                        form.setData(
                                                            'claim_type_id',
                                                            value,
                                                        )
                                                    }
                                                >
                                                    <SelectTrigger>
                                                        <SelectValue placeholder="Pilih jenis" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {claimTypes.map(
                                                            (type) => (
                                                                <SelectItem
                                                                    key={
                                                                        type.id
                                                                    }
                                                                    value={String(
                                                                        type.id,
                                                                    )}
                                                                >
                                                                    {type.name}
                                                                </SelectItem>
                                                            ),
                                                        )}
                                                    </SelectContent>
                                                </Select>
                                                <InputError
                                                    message={
                                                        form.errors
                                                            .claim_type_id
                                                    }
                                                />
                                            </div>
                                            <div className="space-y-2">
                                                <Label>
                                                    Tarikh perbelanjaan
                                                </Label>
                                                <Input
                                                    type="date"
                                                    value={
                                                        form.data.expense_date
                                                    }
                                                    onChange={(event) =>
                                                        form.setData(
                                                            'expense_date',
                                                            event.target.value,
                                                        )
                                                    }
                                                />
                                                <InputError
                                                    message={
                                                        form.errors.expense_date
                                                    }
                                                />
                                            </div>
                                        </div>

                                        {selectedType && (
                                            <div className="rounded-lg bg-muted/60 p-3 text-xs">
                                                Had setiap tuntutan:{' '}
                                                <strong>
                                                    {selectedType.max_per_claim
                                                        ? money(
                                                              selectedType.max_per_claim,
                                                          )
                                                        : 'Tiada had'}
                                                </strong>{' '}
                                                · Bulan ini{' '}
                                                {money(selectedType.month_used)}
                                                {selectedType.monthly_limit
                                                    ? ` / ${money(selectedType.monthly_limit)}`
                                                    : ''}{' '}
                                                · Tahun ini{' '}
                                                {money(selectedType.year_used)}
                                                {selectedType.annual_limit
                                                    ? ` / ${money(selectedType.annual_limit)}`
                                                    : ''}
                                            </div>
                                        )}

                                        <div className="grid gap-4 md:grid-cols-2">
                                            <div className="space-y-2">
                                                <Label>
                                                    Peniaga / penyedia
                                                </Label>
                                                <Input
                                                    value={
                                                        form.data.merchant_name
                                                    }
                                                    onChange={(event) =>
                                                        form.setData(
                                                            'merchant_name',
                                                            event.target.value,
                                                        )
                                                    }
                                                    placeholder="Contoh: Klinik ABC"
                                                />
                                                <InputError
                                                    message={
                                                        form.errors
                                                            .merchant_name
                                                    }
                                                />
                                            </div>
                                            <div className="space-y-2">
                                                <Label>
                                                    Nombor resit
                                                    {selectedType?.requires_receipt_number &&
                                                        ' *'}
                                                </Label>
                                                <Input
                                                    value={
                                                        form.data.receipt_number
                                                    }
                                                    onChange={(event) =>
                                                        form.setData(
                                                            'receipt_number',
                                                            event.target.value,
                                                        )
                                                    }
                                                />
                                                <InputError
                                                    message={
                                                        form.errors
                                                            .receipt_number
                                                    }
                                                />
                                            </div>
                                        </div>

                                        <div className="space-y-2">
                                            <Label>Amaun tuntutan (RM)</Label>
                                            <Input
                                                type="number"
                                                min="0.01"
                                                step="0.01"
                                                value={
                                                    form.data.requested_amount
                                                }
                                                onChange={(event) =>
                                                    form.setData(
                                                        'requested_amount',
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                            <InputError
                                                message={
                                                    form.errors.requested_amount
                                                }
                                            />
                                        </div>

                                        <div className="space-y-2">
                                            <Label>Tujuan / penerangan</Label>
                                            <textarea
                                                className="min-h-24 w-full rounded-md border bg-background px-3 py-2 text-sm"
                                                value={form.data.description}
                                                onChange={(event) =>
                                                    form.setData(
                                                        'description',
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                            <InputError
                                                message={
                                                    form.errors.description
                                                }
                                            />
                                        </div>

                                        <div className="space-y-2">
                                            <Label>
                                                Resit
                                                {selectedType?.requires_receipt &&
                                                    ' *'}
                                            </Label>
                                            <Input
                                                type="file"
                                                multiple
                                                accept=".pdf,.jpg,.jpeg,.png"
                                                onChange={(event) =>
                                                    form.setData(
                                                        'receipts',
                                                        Array.from(
                                                            event.target
                                                                .files ?? [],
                                                        ),
                                                    )
                                                }
                                            />
                                            <InputError
                                                message={
                                                    form.errors.receipts ??
                                                    form.errors['receipts.0']
                                                }
                                            />
                                        </div>

                                        <Button
                                            type="submit"
                                            disabled={form.processing}
                                        >
                                            <Send />
                                            Hantar Tuntutan
                                        </Button>
                                    </form>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader className="flex-row items-start justify-between">
                                    <div>
                                        <CardTitle>Notifikasi</CardTitle>
                                        <CardDescription>
                                            Keputusan dan kemas kini terkini.
                                        </CardDescription>
                                    </div>
                                    {summary.unread_notifications > 0 && (
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() =>
                                                router.patch(
                                                    '/tuntutan-saya/notifikasi/dibaca',
                                                    {},
                                                    {
                                                        preserveScroll: true,
                                                    },
                                                )
                                            }
                                        >
                                            <BellRing />
                                            Tandakan Dibaca
                                        </Button>
                                    )}
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    {notifications.length === 0 && (
                                        <p className="text-sm text-muted-foreground">
                                            Belum ada notifikasi tuntutan.
                                        </p>
                                    )}
                                    {notifications.map((notification) => (
                                        <div
                                            key={notification.id}
                                            className={`rounded-lg border p-3 text-sm ${
                                                notification.read_at
                                                    ? ''
                                                    : 'border-primary/40 bg-primary/5'
                                            }`}
                                        >
                                            <p className="font-medium">
                                                {notification.title}
                                            </p>
                                            <p className="mt-1 text-muted-foreground">
                                                {notification.message}
                                            </p>
                                        </div>
                                    ))}
                                </CardContent>
                            </Card>
                        </div>

                        <Card>
                            <CardHeader>
                                <CardTitle>Sejarah Tuntutan</CardTitle>
                                <CardDescription>
                                    Rekod permohonan, resit dan status bayaran.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                {requests.length === 0 && (
                                    <p className="text-sm text-muted-foreground">
                                        Belum ada tuntutan.
                                    </p>
                                )}
                                {requests.map((claim) => (
                                    <div
                                        key={claim.id}
                                        className="rounded-xl border p-4"
                                    >
                                        <div className="flex flex-wrap items-start justify-between gap-3">
                                            <div>
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <p className="font-semibold">
                                                        {claim.claim_type}
                                                    </p>
                                                    <Badge
                                                        variant={statusVariant(
                                                            claim.status,
                                                        )}
                                                    >
                                                        {
                                                            statusLabels[
                                                                claim.status
                                                            ]
                                                        }
                                                    </Badge>
                                                </div>
                                                <p className="mt-1 text-sm text-muted-foreground">
                                                    {date(claim.expense_date)} ·{' '}
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
                                                {claim.approved_amount !==
                                                    null && (
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
                                        <div className="mt-3 flex flex-wrap gap-2">
                                            {claim.attachments.map(
                                                (attachment) => (
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
                                                ),
                                            )}
                                            {claim.status === 'pending' && (
                                                <Button
                                                    size="sm"
                                                    variant="destructive"
                                                    onClick={() =>
                                                        cancelClaim(claim)
                                                    }
                                                >
                                                    <XCircle />
                                                    Batal
                                                </Button>
                                            )}
                                        </div>
                                        {(claim.review_notes ||
                                            claim.supervisor_review_notes) && (
                                            <p className="mt-3 rounded-lg bg-muted p-3 text-xs">
                                                {claim.supervisor_review_notes &&
                                                    `Penyelia: ${claim.supervisor_review_notes}`}
                                                {claim.supervisor_review_notes &&
                                                    claim.review_notes &&
                                                    ' · '}
                                                {claim.review_notes &&
                                                    `HR/Kewangan: ${claim.review_notes}`}
                                            </p>
                                        )}
                                        {claim.scheduled_payroll_period && (
                                            <p className="mt-3 text-xs text-muted-foreground">
                                                Payroll:{' '}
                                                {claim.scheduled_payroll_period}{' '}
                                                ·{' '}
                                                {claim.paid_at
                                                    ? 'Dibayar'
                                                    : 'Dijadualkan'}
                                            </p>
                                        )}
                                    </div>
                                ))}
                            </CardContent>
                        </Card>
                    </>
                )}
            </div>
        </>
    );
}
