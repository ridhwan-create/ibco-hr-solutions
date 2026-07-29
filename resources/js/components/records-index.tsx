import { Head, Link, router } from '@inertiajs/react';
import { Database, Eye, Pencil, Plus, Search, UserMinus } from 'lucide-react';
import { useEffect, useState } from 'react';
import HeadingSmall from '@/components/heading-small';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

type RecordValue = string | number | boolean | null;

export type RecordColumn = {
    key: string;
    label: string;
    type?: 'text' | 'date' | 'datetime' | 'time' | 'currency' | 'badge';
    align?: 'left' | 'center' | 'right';
};

export type RecordPageConfig = {
    title: string;
    description: string;
    routePath: string;
    detailRoutePath?: string;
    createRoutePath?: string;
    editRoutePath?: string;
    deleteRoutePath?: string;
    entityLabel?: string;
    searchPlaceholder: string;
    columns: RecordColumn[];
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type RecordsIndexPageProps = {
    records: {
        data: Array<Record<string, RecordValue>>;
        current_page: number;
        last_page: number;
        from: number | null;
        to: number | null;
        total: number;
        links: PaginationLink[];
    };
    filters: {
        search?: string;
    };
    canManage?: boolean;
};

function formatDate(value: string): string {
    const date = value.slice(0, 10).split('-');

    if (date.length !== 3) {
        return value;
    }

    return `${date[2]}/${date[1]}/${date[0]}`;
}

function formatValue(
    value: RecordValue | undefined,
    type: RecordColumn['type'],
) {
    if (value === null || value === undefined || value === '') {
        return '-';
    }

    const text = String(value);

    switch (type) {
        case 'currency':
            return new Intl.NumberFormat('ms-MY', {
                style: 'currency',
                currency: 'MYR',
            }).format(Number(value));
        case 'date':
            return formatDate(text);
        case 'datetime': {
            const date = formatDate(text);
            const time = text.slice(11, 16);

            return time ? `${date}, ${time}` : date;
        }
        case 'time':
            return text.slice(0, 5);
        default:
            return text;
    }
}

function paginationLabel(label: string): string {
    return label
        .replace('&laquo; Previous', 'Sebelum')
        .replace('Next &raquo;', 'Seterusnya');
}

function RecordActions({
    record,
    config,
    canManage,
    mobile = false,
}: {
    record: Record<string, RecordValue>;
    config: RecordPageConfig;
    canManage: boolean;
    mobile?: boolean;
}) {
    const [confirmationOpen, setConfirmationOpen] = useState(false);
    const [deactivating, setDeactivating] = useState(false);
    const recordId = String(record.id);
    const recordName = String(
        record.nama ?? record.employee_id ?? `${config.entityLabel} ini`,
    );

    const deactivate = () => {
        if (!config.deleteRoutePath) {
            return;
        }

        router.delete(`${config.deleteRoutePath}/${recordId}`, {
            preserveScroll: true,
            onStart: () => setDeactivating(true),
            onFinish: () => {
                setDeactivating(false);
                setConfirmationOpen(false);
            },
        });
    };

    return (
        <div
            className={
                mobile ? 'grid grid-cols-2 gap-2' : 'flex justify-end gap-2'
            }
        >
            {config.detailRoutePath && (
                <Button
                    asChild
                    variant="outline"
                    size="sm"
                    className={mobile ? 'col-span-2' : undefined}
                >
                    <Link href={`${config.detailRoutePath}/${recordId}`}>
                        <Eye />
                        {mobile ? 'Papar Profil' : 'Papar'}
                    </Link>
                </Button>
            )}

            {canManage && config.editRoutePath && (
                <Button asChild variant="outline" size="sm">
                    <Link href={`${config.editRoutePath}/${recordId}/edit`}>
                        <Pencil />
                        Edit
                    </Link>
                </Button>
            )}

            {canManage && config.deleteRoutePath && (
                <>
                    <Button
                        type="button"
                        variant="destructive"
                        size="sm"
                        onClick={() => setConfirmationOpen(true)}
                    >
                        <UserMinus />
                        Nyahaktif
                    </Button>

                    <Dialog
                        open={confirmationOpen}
                        onOpenChange={setConfirmationOpen}
                    >
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>
                                    Nyahaktifkan {config.entityLabel ?? 'rekod'}
                                    ?
                                </DialogTitle>
                                <DialogDescription>
                                    {recordName} akan disembunyikan daripada
                                    senarai aktif. Rekod tidak dipadam secara
                                    kekal dan tindakan ini akan direkodkan dalam
                                    audit log.
                                </DialogDescription>
                            </DialogHeader>
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button
                                        variant="outline"
                                        disabled={deactivating}
                                    >
                                        Batal
                                    </Button>
                                </DialogClose>
                                <Button
                                    type="button"
                                    variant="destructive"
                                    onClick={deactivate}
                                    disabled={deactivating}
                                >
                                    <UserMinus />
                                    {deactivating
                                        ? 'Sedang diproses...'
                                        : 'Ya, Nyahaktifkan'}
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                </>
            )}
        </div>
    );
}

export default function RecordsIndex({
    records,
    filters,
    config,
    canManage = false,
}: RecordsIndexPageProps & { config: RecordPageConfig }) {
    const [search, setSearch] = useState(filters.search ?? '');
    const hasActions = Boolean(
        config.detailRoutePath ||
        (canManage && (config.editRoutePath || config.deleteRoutePath)),
    );

    useEffect(() => {
        if (search === (filters.search ?? '')) {
            return;
        }

        const timer = window.setTimeout(() => {
            router.get(
                config.routePath,
                { search },
                {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                },
            );
        }, 450);

        return () => window.clearTimeout(timer);
    }, [config.routePath, filters.search, search]);

    const goToPage = (url: string | null) => {
        if (!url) {
            return;
        }

        const page = new URL(url, window.location.origin).searchParams.get(
            'page',
        );

        router.get(
            config.routePath,
            { search, page },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    return (
        <>
            <Head title={config.title} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div className="space-y-2">
                        <HeadingSmall
                            title={config.title}
                            description={config.description}
                        />
                        <Badge
                            variant="secondary"
                            className="gap-1.5 font-normal"
                        >
                            <Database className="size-3.5" />
                            Sumber data: db_spp
                        </Badge>
                    </div>

                    <div className="flex w-full flex-col gap-3 sm:flex-row lg:max-w-xl lg:justify-end">
                        {canManage && config.createRoutePath && (
                            <Button asChild>
                                <Link href={config.createRoutePath}>
                                    <Plus />
                                    Tambah Pekerja
                                </Link>
                            </Button>
                        )}
                        <div className="relative w-full lg:max-w-sm">
                            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder={config.searchPlaceholder}
                                className="pl-9"
                                aria-label={`Cari ${config.title.toLowerCase()}`}
                            />
                        </div>
                    </div>
                </div>

                <div className="hidden overflow-hidden rounded-xl border bg-card md:block">
                    <div className="overflow-x-auto">
                        <Table>
                            <TableHeader className="bg-muted/60">
                                <TableRow>
                                    <TableHead className="w-16">No.</TableHead>
                                    {config.columns.map((column) => (
                                        <TableHead
                                            key={column.key}
                                            className={
                                                column.align === 'right'
                                                    ? 'text-right'
                                                    : column.align === 'center'
                                                      ? 'text-center'
                                                      : undefined
                                            }
                                        >
                                            {column.label}
                                        </TableHead>
                                    ))}
                                    {hasActions && (
                                        <TableHead className="min-w-72 text-right">
                                            Tindakan
                                        </TableHead>
                                    )}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {records.data.length > 0 ? (
                                    records.data.map((record, index) => (
                                        <TableRow key={String(record.id)}>
                                            <TableCell className="text-muted-foreground">
                                                {(records.from ?? 1) + index}
                                            </TableCell>
                                            {config.columns.map((column) => (
                                                <TableCell
                                                    key={column.key}
                                                    className={
                                                        column.align === 'right'
                                                            ? 'text-right'
                                                            : column.align ===
                                                                'center'
                                                              ? 'text-center'
                                                              : undefined
                                                    }
                                                >
                                                    {column.type === 'badge' ? (
                                                        <Badge variant="outline">
                                                            {formatValue(
                                                                record[
                                                                    column.key
                                                                ],
                                                                column.type,
                                                            )}
                                                        </Badge>
                                                    ) : (
                                                        <span
                                                            className={
                                                                column.type ===
                                                                'currency'
                                                                    ? 'font-medium tabular-nums'
                                                                    : undefined
                                                            }
                                                            title={String(
                                                                record[
                                                                    column.key
                                                                ] ?? '',
                                                            )}
                                                        >
                                                            {formatValue(
                                                                record[
                                                                    column.key
                                                                ],
                                                                column.type,
                                                            )}
                                                        </span>
                                                    )}
                                                </TableCell>
                                            ))}
                                            {hasActions && (
                                                <TableCell className="text-right">
                                                    <RecordActions
                                                        record={record}
                                                        config={config}
                                                        canManage={canManage}
                                                    />
                                                </TableCell>
                                            )}
                                        </TableRow>
                                    ))
                                ) : (
                                    <TableRow>
                                        <TableCell
                                            colSpan={
                                                config.columns.length +
                                                (hasActions ? 2 : 1)
                                            }
                                            className="h-28 text-center text-muted-foreground"
                                        >
                                            Tiada rekod ditemui.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </div>
                </div>

                <div className="space-y-3 md:hidden">
                    {records.data.length > 0 ? (
                        records.data.map((record, index) => (
                            <Card key={String(record.id)}>
                                <CardHeader className="pb-3">
                                    <CardTitle className="text-sm">
                                        Rekod {(records.from ?? 1) + index}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="grid gap-3">
                                    {config.columns.map((column) => (
                                        <div
                                            key={column.key}
                                            className="grid grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)] gap-3 text-sm"
                                        >
                                            <span className="text-muted-foreground">
                                                {column.label}
                                            </span>
                                            <span className="font-medium break-words">
                                                {formatValue(
                                                    record[column.key],
                                                    column.type,
                                                )}
                                            </span>
                                        </div>
                                    ))}
                                    {hasActions && (
                                        <div className="mt-1">
                                            <RecordActions
                                                record={record}
                                                config={config}
                                                canManage={canManage}
                                                mobile
                                            />
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        ))
                    ) : (
                        <Card>
                            <CardContent className="py-10 text-center text-sm text-muted-foreground">
                                Tiada rekod ditemui.
                            </CardContent>
                        </Card>
                    )}
                </div>

                <div className="flex flex-col gap-3 rounded-xl border bg-card px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <p className="text-sm text-muted-foreground">
                        Menunjukkan {records.from ?? 0} hingga {records.to ?? 0}{' '}
                        daripada {records.total} rekod
                    </p>

                    {records.last_page > 1 && (
                        <div className="flex flex-wrap gap-1">
                            {records.links.map((link, index) => (
                                <button
                                    key={`${link.label}-${index}`}
                                    type="button"
                                    disabled={!link.url}
                                    onClick={() => goToPage(link.url)}
                                    className={`min-w-9 rounded-md border px-3 py-1.5 text-sm transition-colors ${
                                        link.active
                                            ? 'border-primary bg-primary text-primary-foreground'
                                            : 'border-input bg-background hover:bg-accent'
                                    } disabled:pointer-events-none disabled:opacity-40`}
                                >
                                    {paginationLabel(link.label)}
                                </button>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}
