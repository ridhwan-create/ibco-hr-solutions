import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Banknote,
    BriefcaseBusiness,
    Building2,
    CalendarDays,
    CircleOff,
    CreditCard,
    History,
    IdCard,
    Landmark,
    Pencil,
    ShieldCheck,
    UserRound,
} from 'lucide-react';
import { useState } from 'react';
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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

type Position = {
    id: number;
    id_pekerja: number;
    employee_id: string | null;
    nama_pekerja: string | null;
    jawatan: string | null;
    id_department: number | null;
    jabatan: string | null;
    tarikh_berkuat_kuasa: string | null;
    tarikh_tamat_tempoh_cubaan: string | null;
    kelayakan_cuti: string | null;
    aktif: number;
    tarikh_dicipta: string | null;
    dicipta_oleh: string | null;
    tarikh_tamat: string | null;
    ditamatkan_oleh: string | null;
    gaji_asas?: string | number | null;
    id_bank?: number | null;
    bank?: string | null;
    no_akaun?: string | null;
    no_kwsp?: string | null;
    no_perkeso?: string | null;
};

type PositionShowProps = {
    jawatan: Position;
    history: Position[];
    canManage: boolean;
    canViewPayroll: boolean;
};

function displayValue(value: string | number | null | undefined): string {
    return value === null || value === undefined || value === ''
        ? '-'
        : String(value);
}

function formatDate(value: string | null): string {
    if (!value) {
        return '-';
    }

    const parts = value.slice(0, 10).split('-');

    return parts.length === 3 ? `${parts[2]}/${parts[1]}/${parts[0]}` : value;
}

function formatCurrency(value: string | number | null | undefined): string {
    if (value === null || value === undefined || value === '') {
        return '-';
    }

    return new Intl.NumberFormat('ms-MY', {
        style: 'currency',
        currency: 'MYR',
    }).format(Number(value));
}

function InformationItem({
    label,
    value,
    icon: Icon,
    type = 'text',
}: {
    label: string;
    value: string | number | null | undefined;
    icon: typeof BriefcaseBusiness;
    type?: 'text' | 'date' | 'currency';
}) {
    const formatted =
        type === 'date'
            ? formatDate(value ? String(value) : null)
            : type === 'currency'
              ? formatCurrency(value)
              : displayValue(value);

    return (
        <div className="flex gap-3 rounded-lg border bg-muted/20 p-3">
            <div className="mt-0.5 rounded-md bg-background p-2 text-muted-foreground shadow-sm">
                <Icon className="size-4" />
            </div>
            <div className="min-w-0">
                <p className="text-xs text-muted-foreground">{label}</p>
                <p className="mt-1 text-sm font-medium break-words">
                    {formatted}
                </p>
            </div>
        </div>
    );
}

function TerminatePositionButton({ position }: { position: Position }) {
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button type="button" variant="destructive" size="sm">
                    <CircleOff />
                    Tamatkan Jawatan
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Tamatkan jawatan aktif?</DialogTitle>
                    <DialogDescription>
                        Rekod ini akan dipindahkan ke sejarah dan tidak dipadam
                        secara kekal. Pekerja akan berada tanpa jawatan aktif
                        sehingga penempatan baharu dibuat.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <DialogClose asChild>
                        <Button variant="outline" disabled={processing}>
                            Batal
                        </Button>
                    </DialogClose>
                    <Button
                        type="button"
                        variant="destructive"
                        disabled={processing}
                        onClick={() =>
                            router.delete(`/jawatan/${position.id}`, {
                                onStart: () => setProcessing(true),
                                onFinish: () => {
                                    setProcessing(false);
                                    setOpen(false);
                                },
                            })
                        }
                    >
                        <CircleOff />
                        {processing
                            ? 'Sedang diproses...'
                            : 'Ya, Tamatkan Jawatan'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

export default function PositionShow({
    jawatan,
    history,
    canManage,
    canViewPayroll,
}: PositionShowProps) {
    const active = Number(jawatan.aktif) === 1;

    return (
        <>
            <Head
                title={`Jawatan ${jawatan.nama_pekerja ?? jawatan.employee_id ?? ''}`}
            />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <Button asChild variant="outline" size="sm">
                        <Link href="/jawatan">
                            <ArrowLeft />
                            Kembali ke Senarai Jawatan
                        </Link>
                    </Button>
                    {active && canManage && (
                        <div className="flex flex-col gap-2 sm:flex-row">
                            <Button asChild variant="outline" size="sm">
                                <Link href={`/jawatan/${jawatan.id}/edit`}>
                                    <Pencil />
                                    Tukar Jawatan
                                </Link>
                            </Button>
                            <TerminatePositionButton position={jawatan} />
                        </div>
                    )}
                </div>

                <Card className="overflow-hidden">
                    <div className="h-2 bg-gradient-to-r from-blue-600 via-cyan-500 to-emerald-500" />
                    <CardContent className="flex flex-col gap-5 pt-1 sm:flex-row sm:items-center sm:justify-between">
                        <div className="min-w-0">
                            <div className="flex items-center gap-2">
                                <BriefcaseBusiness className="size-6 text-muted-foreground" />
                                <h1 className="text-2xl font-bold tracking-tight md:text-3xl">
                                    {displayValue(jawatan.jawatan)}
                                </h1>
                            </div>
                            <p className="mt-2 text-sm text-muted-foreground">
                                {displayValue(jawatan.nama_pekerja)}
                                {jawatan.employee_id
                                    ? ` · ${jawatan.employee_id}`
                                    : ''}
                            </p>
                            <div className="mt-3 flex flex-wrap gap-2">
                                <Badge
                                    variant={active ? 'secondary' : 'outline'}
                                >
                                    {active ? 'Jawatan Aktif' : 'Rekod Sejarah'}
                                </Badge>
                                <Badge variant="outline">
                                    {displayValue(jawatan.jabatan)}
                                </Badge>
                            </div>
                        </div>
                        <Button asChild variant="outline">
                            <Link href={`/pekerja/${jawatan.id_pekerja}`}>
                                <UserRound />
                                Papar Profil Pekerja
                            </Link>
                        </Button>
                    </CardContent>
                </Card>

                <div
                    className={
                        canViewPayroll ? 'grid gap-6 xl:grid-cols-2' : undefined
                    }
                >
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Building2 className="size-5 text-muted-foreground" />
                                Butiran Penempatan
                            </CardTitle>
                            <CardDescription>
                                Maklumat jawatan dan tempoh penempatan pekerja.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-3 sm:grid-cols-2">
                            <InformationItem
                                icon={BriefcaseBusiness}
                                label="Jawatan"
                                value={jawatan.jawatan}
                            />
                            <InformationItem
                                icon={Building2}
                                label="Jabatan / Unit"
                                value={jawatan.jabatan}
                            />
                            <InformationItem
                                icon={CalendarDays}
                                label="Tarikh Berkuat Kuasa"
                                value={jawatan.tarikh_berkuat_kuasa}
                                type="date"
                            />
                            <InformationItem
                                icon={CalendarDays}
                                label="Tamat Tempoh Percubaan"
                                value={jawatan.tarikh_tamat_tempoh_cubaan}
                                type="date"
                            />
                            <InformationItem
                                icon={CalendarDays}
                                label="Kelayakan Cuti"
                                value={jawatan.kelayakan_cuti}
                            />
                            {!active && (
                                <InformationItem
                                    icon={CircleOff}
                                    label="Tarikh Tamat"
                                    value={jawatan.tarikh_tamat}
                                    type="date"
                                />
                            )}
                        </CardContent>
                    </Card>

                    {canViewPayroll && (
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Banknote className="size-5 text-muted-foreground" />
                                    Gaji & Caruman
                                </CardTitle>
                                <CardDescription>
                                    Maklumat sensitif untuk pengguna dengan
                                    akses payroll sahaja.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-3 sm:grid-cols-2">
                                <InformationItem
                                    icon={Banknote}
                                    label="Gaji Asas"
                                    value={jawatan.gaji_asas}
                                    type="currency"
                                />
                                <InformationItem
                                    icon={Landmark}
                                    label="Bank"
                                    value={jawatan.bank}
                                />
                                <InformationItem
                                    icon={CreditCard}
                                    label="No. Akaun"
                                    value={jawatan.no_akaun}
                                />
                                <InformationItem
                                    icon={IdCard}
                                    label="No. KWSP"
                                    value={jawatan.no_kwsp}
                                />
                                <InformationItem
                                    icon={ShieldCheck}
                                    label="No. PERKESO"
                                    value={jawatan.no_perkeso}
                                />
                            </CardContent>
                        </Card>
                    )}
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <History className="size-5 text-muted-foreground" />
                            Sejarah Jawatan Pekerja
                        </CardTitle>
                        <CardDescription>
                            Semua penempatan direkodkan tanpa menimpa rekod
                            terdahulu.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="hidden overflow-x-auto md:block">
                            <Table>
                                <TableHeader className="bg-muted/60">
                                    <TableRow>
                                        <TableHead>Jawatan</TableHead>
                                        <TableHead>Jabatan</TableHead>
                                        <TableHead>Berkuat Kuasa</TableHead>
                                        <TableHead>Tamat</TableHead>
                                        {canViewPayroll && (
                                            <TableHead className="text-right">
                                                Gaji Asas
                                            </TableHead>
                                        )}
                                        <TableHead>Status</TableHead>
                                        <TableHead className="text-right">
                                            Papar
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {history.map((position) => (
                                        <TableRow key={position.id}>
                                            <TableCell className="font-medium">
                                                {displayValue(position.jawatan)}
                                            </TableCell>
                                            <TableCell>
                                                {displayValue(position.jabatan)}
                                            </TableCell>
                                            <TableCell>
                                                {formatDate(
                                                    position.tarikh_berkuat_kuasa,
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                {Number(position.aktif) === 1
                                                    ? '-'
                                                    : formatDate(
                                                          position.tarikh_tamat,
                                                      )}
                                            </TableCell>
                                            {canViewPayroll && (
                                                <TableCell className="text-right tabular-nums">
                                                    {formatCurrency(
                                                        position.gaji_asas,
                                                    )}
                                                </TableCell>
                                            )}
                                            <TableCell>
                                                <Badge
                                                    variant={
                                                        Number(
                                                            position.aktif,
                                                        ) === 1
                                                            ? 'secondary'
                                                            : 'outline'
                                                    }
                                                >
                                                    {Number(position.aktif) ===
                                                    1
                                                        ? 'Aktif'
                                                        : 'Sejarah'}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <Button
                                                    asChild
                                                    variant="ghost"
                                                    size="sm"
                                                >
                                                    <Link
                                                        href={`/jawatan/${position.id}`}
                                                    >
                                                        Papar
                                                    </Link>
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>

                        <div className="grid gap-3 p-4 md:hidden">
                            {history.map((position) => (
                                <div
                                    key={position.id}
                                    className="rounded-lg border p-4"
                                >
                                    <div className="flex items-start justify-between gap-3">
                                        <div>
                                            <p className="font-medium">
                                                {displayValue(position.jawatan)}
                                            </p>
                                            <p className="text-sm text-muted-foreground">
                                                {displayValue(position.jabatan)}
                                            </p>
                                        </div>
                                        <Badge
                                            variant={
                                                Number(position.aktif) === 1
                                                    ? 'secondary'
                                                    : 'outline'
                                            }
                                        >
                                            {Number(position.aktif) === 1
                                                ? 'Aktif'
                                                : 'Sejarah'}
                                        </Badge>
                                    </div>
                                    <div className="mt-3 grid grid-cols-2 gap-3 text-sm">
                                        <div>
                                            <p className="text-muted-foreground">
                                                Berkuat Kuasa
                                            </p>
                                            <p>
                                                {formatDate(
                                                    position.tarikh_berkuat_kuasa,
                                                )}
                                            </p>
                                        </div>
                                        <div>
                                            <p className="text-muted-foreground">
                                                Tamat
                                            </p>
                                            <p>
                                                {Number(position.aktif) === 1
                                                    ? '-'
                                                    : formatDate(
                                                          position.tarikh_tamat,
                                                      )}
                                            </p>
                                        </div>
                                    </div>
                                    <Button
                                        asChild
                                        variant="outline"
                                        size="sm"
                                        className="mt-3 w-full"
                                    >
                                        <Link href={`/jawatan/${position.id}`}>
                                            Papar Rekod
                                        </Link>
                                    </Button>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

PositionShow.layout = {
    breadcrumbs: [
        { title: 'Jawatan', href: '/jawatan' },
        { title: 'Butiran Jawatan', href: '#' },
    ],
};
