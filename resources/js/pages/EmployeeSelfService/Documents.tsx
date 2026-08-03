import { Head, Link, router } from '@inertiajs/react';
import {
    AlertTriangle,
    Bell,
    CheckCircle2,
    Download,
    FileCheck2,
    Files,
    Paperclip,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

type Attachment = {
    id: number;
    attachment_type: string;
    original_name: string;
    mime_type: string;
    size: number;
};

type Document = {
    id: number;
    reference_number: string;
    template_name: string;
    category: string;
    rendered_subject: string;
    status: string;
    display_status: string;
    issued_at: string;
    effective_date: string | null;
    expiry_date: string | null;
    days_to_expiry: number | null;
    acknowledgement_required: boolean;
    acknowledged_at: string | null;
    confidentiality: string;
    attachments: Attachment[];
};

type Notification = {
    id: number;
    document_id: number | null;
    title: string;
    message: string;
    read_at: string | null;
    created_at: string;
};

type Props = {
    documents: Document[];
    notifications: Notification[];
    unreadNotifications: number;
    statistics: {
        total: number;
        acknowledgement_pending: number;
        expiring: number;
        expired: number;
    };
};

const statusLabel: Record<string, string> = {
    issued: 'Dikeluarkan',
    acknowledged: 'Diperakui',
    expired: 'Tamat Tempoh',
};

const categoryLabel: Record<string, string> = {
    contract: 'Kontrak',
    confirmation: 'Pengesahan Jawatan',
    salary_revision: 'Pelarasan Gaji',
    promotion: 'Kenaikan Pangkat',
    transfer: 'Pertukaran',
    warning: 'Amaran',
    show_cause: 'Tunjuk Sebab',
    memo: 'Memo',
    termination: 'Penamatan',
    resignation: 'Berhenti Kerja',
    custom: 'Lain-lain',
};

function formatDate(value: string | null) {
    if (!value) {
        return '-';
    }

    return new Intl.DateTimeFormat('ms-MY', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(new Date(value));
}

function formatSize(bytes: number) {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

function StatusBadge({ status }: { status: string }) {
    const variant =
        status === 'acknowledged'
            ? 'default'
            : status === 'expired'
              ? 'destructive'
              : 'secondary';

    return <Badge variant={variant}>{statusLabel[status] ?? status}</Badge>;
}

export default function Documents({
    documents,
    notifications,
    unreadNotifications,
    statistics,
}: Props) {
    const acknowledge = (document: Document) => {
        if (
            !window.confirm(
                `Saya mengesahkan telah menerima dan membaca ${document.reference_number}. Teruskan?`,
            )
        ) {
            return;
        }

        router.patch(
            `/dokumen-saya/${document.id}/perakuan`,
            { confirmed: true },
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Dokumen Saya" />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Dokumen Saya
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Arkib surat HR peribadi, lampiran rasmi dan rekod
                            perakuan penerimaan.
                        </p>
                    </div>
                    {unreadNotifications > 0 && (
                        <Button
                            variant="outline"
                            onClick={() =>
                                router.patch(
                                    '/dokumen-saya/notifikasi/dibaca',
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            <Bell />
                            Tandakan {unreadNotifications} Dibaca
                        </Button>
                    )}
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        title="Jumlah Dokumen"
                        value={statistics.total}
                        icon={<Files className="size-5 text-rose-600" />}
                    />
                    <StatCard
                        title="Perlu Diperakui"
                        value={statistics.acknowledgement_pending}
                        icon={<FileCheck2 className="size-5 text-amber-600" />}
                    />
                    <StatCard
                        title="Tamat ≤ 30 Hari"
                        value={statistics.expiring}
                        icon={
                            <AlertTriangle className="size-5 text-orange-600" />
                        }
                    />
                    <StatCard
                        title="Tamat Tempoh"
                        value={statistics.expired}
                        icon={<AlertTriangle className="size-5 text-red-600" />}
                    />
                </div>

                {notifications.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Bell className="size-4" /> Notifikasi Dokumen
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-2 md:grid-cols-2">
                            {notifications.slice(0, 6).map((notification) => (
                                <div
                                    key={notification.id}
                                    className={`rounded-lg border p-3 text-sm ${notification.read_at ? 'bg-muted/20' : 'border-rose-200 bg-rose-50/60 dark:border-rose-900 dark:bg-rose-950/20'}`}
                                >
                                    <div className="font-medium">
                                        {notification.title}
                                    </div>
                                    <div className="text-muted-foreground">
                                        {notification.message}
                                    </div>
                                    <div className="mt-1 text-xs text-muted-foreground">
                                        {formatDate(notification.created_at)}
                                    </div>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                )}

                <div className="grid gap-4 xl:grid-cols-2">
                    {documents.map((document) => {
                        const pendingAcknowledgement =
                            document.acknowledgement_required &&
                            !document.acknowledged_at &&
                            document.status === 'issued';

                        return (
                            <Card
                                key={document.id}
                                className={
                                    pendingAcknowledgement
                                        ? 'border-amber-300 dark:border-amber-800'
                                        : undefined
                                }
                            >
                                <CardHeader>
                                    <div className="flex flex-wrap items-start justify-between gap-2">
                                        <div className="space-y-1">
                                            <CardTitle className="text-base">
                                                {document.rendered_subject}
                                            </CardTitle>
                                            <CardDescription>
                                                {document.reference_number} ·{' '}
                                                {categoryLabel[
                                                    document.category
                                                ] ?? document.category}
                                            </CardDescription>
                                        </div>
                                        <StatusBadge
                                            status={document.display_status}
                                        />
                                    </div>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <dl className="grid grid-cols-2 gap-3 text-sm sm:grid-cols-3">
                                        <div>
                                            <dt className="text-muted-foreground">
                                                Dikeluarkan
                                            </dt>
                                            <dd className="font-medium">
                                                {formatDate(document.issued_at)}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt className="text-muted-foreground">
                                                Kuat Kuasa
                                            </dt>
                                            <dd className="font-medium">
                                                {formatDate(
                                                    document.effective_date,
                                                )}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt className="text-muted-foreground">
                                                Tamat Tempoh
                                            </dt>
                                            <dd className="font-medium">
                                                {formatDate(
                                                    document.expiry_date,
                                                )}
                                            </dd>
                                        </div>
                                    </dl>

                                    {pendingAcknowledgement && (
                                        <div className="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200">
                                            Dokumen ini memerlukan perakuan
                                            penerimaan selepas dibaca.
                                        </div>
                                    )}

                                    {document.attachments.length > 0 && (
                                        <div className="space-y-2">
                                            <div className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                                Lampiran Rasmi
                                            </div>
                                            {document.attachments.map(
                                                (attachment) => (
                                                    <Link
                                                        key={attachment.id}
                                                        href={`/dokumen-saya/${document.id}/lampiran/${attachment.id}`}
                                                        className="flex items-center justify-between rounded-md border px-3 py-2 text-sm hover:bg-muted"
                                                    >
                                                        <span className="flex min-w-0 items-center gap-2">
                                                            <Paperclip className="size-4 shrink-0" />
                                                            <span className="truncate">
                                                                {
                                                                    attachment.original_name
                                                                }
                                                            </span>
                                                        </span>
                                                        <span className="text-xs text-muted-foreground">
                                                            {formatSize(
                                                                attachment.size,
                                                            )}
                                                        </span>
                                                    </Link>
                                                ),
                                            )}
                                        </div>
                                    )}

                                    <div className="flex flex-wrap gap-2">
                                        <Button variant="outline" asChild>
                                            <Link
                                                href={`/dokumen-saya/${document.id}/pdf`}
                                            >
                                                <Download /> PDF
                                            </Link>
                                        </Button>
                                        {pendingAcknowledgement && (
                                            <Button
                                                onClick={() =>
                                                    acknowledge(document)
                                                }
                                            >
                                                <CheckCircle2 /> Perakui
                                                Penerimaan
                                            </Button>
                                        )}
                                    </div>
                                </CardContent>
                            </Card>
                        );
                    })}
                </div>

                {documents.length === 0 && (
                    <Card>
                        <CardContent className="flex min-h-44 flex-col items-center justify-center gap-2 text-center text-muted-foreground">
                            <Files className="size-9" />
                            <p>Belum ada dokumen HR yang dikeluarkan.</p>
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

Documents.layout = {
    breadcrumbs: [{ title: 'Dokumen Saya', href: '/dokumen-saya' }],
};

function StatCard({
    title,
    value,
    icon,
}: {
    title: string;
    value: number;
    icon: React.ReactNode;
}) {
    return (
        <Card>
            <CardContent className="flex items-center justify-between p-5">
                <div>
                    <div className="text-sm text-muted-foreground">{title}</div>
                    <div className="text-2xl font-semibold">{value}</div>
                </div>
                {icon}
            </CardContent>
        </Card>
    );
}
