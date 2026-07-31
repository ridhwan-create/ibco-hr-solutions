import { Head, Link } from '@inertiajs/react';
import { Download, FileText, Landmark, ReceiptText } from 'lucide-react';
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
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

type Payslip = {
    id: number;
    period: string;
    period_label: string;
    finalized_at: string | null;
    gross_pay: number;
    total_deductions: number;
    net_pay: number;
    kwsp_employee: number;
    socso_employee: number;
    eis_employee: number;
    pcb: number;
};

type Props = {
    hasEmployeeLink: boolean;
    payslips: {
        data: Payslip[];
        links: { url: string | null; label: string; active: boolean }[];
    } | null;
};

function money(value: number): string {
    return new Intl.NumberFormat('ms-MY', {
        style: 'currency',
        currency: 'MYR',
    }).format(value);
}

function paginationLabel(label: string): string {
    return label
        .replace('&laquo; Previous', 'Sebelum')
        .replace('Next &raquo;', 'Seterusnya');
}

export default function Payslips({ hasEmployeeLink, payslips }: Props) {
    const records = payslips?.data ?? [];

    return (
        <>
            <Head title="Slip Gaji Saya" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="flex items-center gap-2 text-2xl font-semibold">
                        <ReceiptText className="size-6 text-primary" />
                        Slip Gaji Saya
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Slip hanya diterbitkan selepas payroll diluluskan,
                        dimuktamadkan dan dikunci.
                    </p>
                </div>

                {!hasEmployeeLink ? (
                    <Card className="border-amber-500/30">
                        <CardHeader>
                            <CardTitle>Pautan pekerja belum tersedia</CardTitle>
                            <CardDescription>
                                Hubungi HR untuk memautkan akaun ini kepada
                                rekod pekerja sebelum slip gaji boleh dilihat.
                            </CardDescription>
                        </CardHeader>
                    </Card>
                ) : records.length === 0 ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>Belum ada slip gaji</CardTitle>
                            <CardDescription>
                                Slip pertama akan muncul di sini selepas payroll
                                bulan berkenaan dimuktamadkan.
                            </CardDescription>
                        </CardHeader>
                    </Card>
                ) : (
                    <>
                        <div className="grid gap-4 sm:grid-cols-3">
                            <Card>
                                <CardHeader className="pb-2">
                                    <CardDescription>
                                        Slip Terkini
                                    </CardDescription>
                                    <CardTitle>
                                        {records[0].period_label}
                                    </CardTitle>
                                </CardHeader>
                            </Card>
                            <Card>
                                <CardHeader className="pb-2">
                                    <CardDescription>
                                        Gaji Bersih Terkini
                                    </CardDescription>
                                    <CardTitle className="text-emerald-700">
                                        {money(records[0].net_pay)}
                                    </CardTitle>
                                </CardHeader>
                            </Card>
                            <Card>
                                <CardHeader className="pb-2">
                                    <CardDescription>
                                        Jumlah Slip Dipaparkan
                                    </CardDescription>
                                    <CardTitle>{records.length}</CardTitle>
                                </CardHeader>
                            </Card>
                        </div>

                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <FileText className="size-5 text-primary" />
                                    Sejarah Slip Gaji
                                </CardTitle>
                                <CardDescription>
                                    PDF mengandungi pecahan pendapatan, potongan
                                    dan caruman statutori.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="overflow-x-auto rounded-lg border">
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>Bulan</TableHead>
                                                <TableHead className="text-right">
                                                    Pendapatan
                                                </TableHead>
                                                <TableHead className="text-right">
                                                    Potongan
                                                </TableHead>
                                                <TableHead>Statutori</TableHead>
                                                <TableHead className="text-right">
                                                    Gaji Bersih
                                                </TableHead>
                                                <TableHead>Tindakan</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {records.map((payslip) => (
                                                <TableRow key={payslip.id}>
                                                    <TableCell>
                                                        <p className="font-medium">
                                                            {
                                                                payslip.period_label
                                                            }
                                                        </p>
                                                        <Badge
                                                            variant="outline"
                                                            className="mt-1 border-emerald-500/30 text-emerald-700"
                                                        >
                                                            Dimuktamadkan
                                                        </Badge>
                                                    </TableCell>
                                                    <TableCell className="text-right tabular-nums">
                                                        {money(
                                                            payslip.gross_pay,
                                                        )}
                                                    </TableCell>
                                                    <TableCell className="text-right tabular-nums">
                                                        {money(
                                                            payslip.total_deductions,
                                                        )}
                                                    </TableCell>
                                                    <TableCell>
                                                        <div className="flex flex-wrap gap-1 text-xs">
                                                            <Badge variant="secondary">
                                                                KWSP{' '}
                                                                {money(
                                                                    payslip.kwsp_employee,
                                                                )}
                                                            </Badge>
                                                            <Badge variant="secondary">
                                                                PERKESO{' '}
                                                                {money(
                                                                    payslip.socso_employee,
                                                                )}
                                                            </Badge>
                                                            <Badge variant="secondary">
                                                                EIS{' '}
                                                                {money(
                                                                    payslip.eis_employee,
                                                                )}
                                                            </Badge>
                                                            <Badge variant="secondary">
                                                                PCB{' '}
                                                                {money(
                                                                    payslip.pcb,
                                                                )}
                                                            </Badge>
                                                        </div>
                                                    </TableCell>
                                                    <TableCell className="text-right font-semibold text-emerald-700 tabular-nums">
                                                        {money(payslip.net_pay)}
                                                    </TableCell>
                                                    <TableCell>
                                                        <Button
                                                            asChild
                                                            size="sm"
                                                        >
                                                            <a
                                                                href={`/slip-gaji-saya/${payslip.id}/pdf`}
                                                            >
                                                                <Download />
                                                                Muat Turun PDF
                                                            </a>
                                                        </Button>
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                </div>
                                {payslips && payslips.links.length > 3 && (
                                    <div className="flex flex-wrap gap-2">
                                        {payslips.links.map((link, index) => (
                                            <Button
                                                key={`${link.label}-${index}`}
                                                asChild={Boolean(link.url)}
                                                size="sm"
                                                variant={
                                                    link.active
                                                        ? 'default'
                                                        : 'outline'
                                                }
                                                disabled={!link.url}
                                            >
                                                {link.url ? (
                                                    <Link href={link.url}>
                                                        {paginationLabel(
                                                            link.label,
                                                        )}
                                                    </Link>
                                                ) : (
                                                    <span>
                                                        {paginationLabel(
                                                            link.label,
                                                        )}
                                                    </span>
                                                )}
                                            </Button>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        <Card className="border-primary/20 bg-primary/5">
                            <CardHeader className="flex-row items-center gap-3">
                                <Landmark className="size-5 text-primary" />
                                <div>
                                    <CardTitle className="text-base">
                                        Caruman Majikan
                                    </CardTitle>
                                    <CardDescription>
                                        Caruman majikan dipaparkan dalam PDF
                                        sebagai maklumat dan tidak ditolak
                                        daripada gaji bersih.
                                    </CardDescription>
                                </div>
                            </CardHeader>
                        </Card>
                    </>
                )}
            </div>
        </>
    );
}
