import { Link, router, usePage } from '@inertiajs/react';
import {
    BellRing,
    CalendarDays,
    CheckCircle2,
    ChevronRight,
    ClipboardCheck,
    Clock3,
    Files,
    GraduationCap,
    ListChecks,
    ReceiptText,
    ShieldAlert,
    Target,
    UserPlus,
} from 'lucide-react';
import { useEffect } from 'react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { LeaveApprovalAlerts } from '@/types';

function countLabel(count: number): string {
    return count > 99 ? '99+' : String(count);
}

function AlertLink({
    href,
    icon,
    title,
    description,
    count,
}: {
    href: string;
    icon: React.ReactNode;
    title: string;
    description: string;
    count: number;
}) {
    if (count < 1) {
        return null;
    }

    return (
        <DropdownMenuItem asChild className="p-0">
            <Link
                href={href}
                className="flex cursor-pointer items-center gap-3 rounded-md px-2 py-3"
            >
                <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-amber-500/10">
                    {icon}
                </span>
                <span className="min-w-0 flex-1">
                    <span className="block text-sm font-medium">{title}</span>
                    <span className="block text-xs text-muted-foreground">
                        {description}
                    </span>
                </span>
                <span className="rounded-full bg-red-500/15 px-2 py-0.5 text-xs font-bold text-red-700 dark:text-red-300">
                    {countLabel(count)}
                </span>
                <ChevronRight className="size-4 text-muted-foreground" />
            </Link>
        </DropdownMenuItem>
    );
}

export function LeaveApprovalNotifications() {
    const { leaveApprovalAlerts } = usePage<{
        leaveApprovalAlerts?: LeaveApprovalAlerts;
    }>().props;
    const alerts: LeaveApprovalAlerts = leaveApprovalAlerts ?? {
        enabled: false,
        total: 0,
        supervisor: 0,
        hr: 0,
        polling_seconds: 60,
        leave_total: 0,
        leave_supervisor: 0,
        leave_hr: 0,
        overtime_total: 0,
        overtime_supervisor: 0,
        overtime_hr: 0,
        claim_total: 0,
        claim_supervisor: 0,
        claim_finance: 0,
        performance_total: 0,
        performance_supervisor: 0,
        performance_hr: 0,
        recruitment_total: 0,
        recruitment_approval: 0,
        recruitment_interview: 0,
        onboarding_total: 0,
        onboarding_registration: 0,
        onboarding_overdue: 0,
        training_total: 0,
        training_supervisor: 0,
        training_hr: 0,
        document_total: 0,
        document_approval: 0,
        document_expiring: 0,
        discipline_total: 0,
        discipline_triage: 0,
        discipline_investigation: 0,
        discipline_decision: 0,
        separation_total: 0,
        separation_supervisor: 0,
        separation_hr: 0,
        separation_clearance: 0,
        separation_final_review: 0,
    };

    useEffect(() => {
        if (!alerts.enabled) {
            return;
        }

        const interval = window.setInterval(
            () => router.reload({ only: ['leaveApprovalAlerts'] }),
            Math.max(alerts.polling_seconds, 30) * 1000,
        );

        return () => window.clearInterval(interval);
    }, [alerts.enabled, alerts.polling_seconds]);

    if (!alerts.enabled) {
        return null;
    }

    const triggerLabel =
        alerts.total > 0
            ? `${alerts.total} rekod menunggu tindakan`
            : 'Tiada rekod menunggu tindakan';

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="relative rounded-full"
                    aria-label={triggerLabel}
                    title={triggerLabel}
                >
                    <BellRing
                        className={
                            alerts.total > 0
                                ? 'text-amber-600 dark:text-amber-400'
                                : 'text-muted-foreground'
                        }
                    />
                    {alerts.total > 0 && (
                        <>
                            <span className="absolute top-1.5 right-1.5 size-2 rounded-full bg-red-600 ring-2 ring-background" />
                            <span className="absolute -top-1 -right-1 flex min-w-5 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] leading-5 font-bold text-white shadow-sm">
                                {countLabel(alerts.total)}
                            </span>
                        </>
                    )}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="end"
                className="w-[min(24rem,calc(100vw-2rem))] p-2"
            >
                <DropdownMenuLabel className="flex items-start justify-between gap-4 px-2 py-2">
                    <span>
                        <span className="block font-semibold">
                            Pusat Kelulusan
                        </span>
                        <span className="mt-0.5 block text-xs font-normal text-muted-foreground">
                            Cuti, OT, tuntutan, prestasi, pengambilan,
                            onboarding, latihan, dokumen, disiplin dan clearance
                            · dikemas kini setiap minit
                        </span>
                    </span>
                    {alerts.total > 0 && (
                        <span className="rounded-full bg-red-600 px-2 py-0.5 text-xs font-bold text-white">
                            {countLabel(alerts.total)}
                        </span>
                    )}
                </DropdownMenuLabel>
                <DropdownMenuSeparator />

                {alerts.total === 0 ? (
                    <div className="flex items-center gap-3 px-2 py-4">
                        <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-emerald-500/10">
                            <CheckCircle2 className="size-5 text-emerald-600" />
                        </span>
                        <div>
                            <p className="text-sm font-medium">
                                Semua permohonan telah disemak
                            </p>
                            <p className="text-xs text-muted-foreground">
                                Tiada tindakan kelulusan diperlukan.
                            </p>
                        </div>
                    </div>
                ) : (
                    <div className="space-y-1">
                        <AlertLink
                            href="/permohonan-cuti?status=pending"
                            icon={
                                <CalendarDays className="size-5 text-amber-600" />
                            }
                            title="Permohonan Cuti"
                            description={`${alerts.leave_supervisor} penyelia · ${alerts.leave_hr} HR`}
                            count={alerts.leave_total}
                        />
                        <AlertLink
                            href="/permohonan-ot?status=pending&all_months=1"
                            icon={<Clock3 className="size-5 text-sky-600" />}
                            title="Permohonan OT"
                            description={`${alerts.overtime_supervisor} penyelia · ${alerts.overtime_hr} HR`}
                            count={alerts.overtime_total}
                        />
                        <AlertLink
                            href="/permohonan-tuntutan?status=pending"
                            icon={
                                <ReceiptText className="size-5 text-violet-600" />
                            }
                            title="Permohonan Tuntutan"
                            description={`${alerts.claim_supervisor} penyelia · ${alerts.claim_finance} HR/Kewangan`}
                            count={alerts.claim_total}
                        />
                        <AlertLink
                            href="/prestasi"
                            icon={
                                <Target className="size-5 text-emerald-600" />
                            }
                            title="Penilaian Prestasi"
                            description={`${alerts.performance_supervisor} penyelia · ${alerts.performance_hr} HR`}
                            count={alerts.performance_total}
                        />
                        <AlertLink
                            href="/pengambilan"
                            icon={<UserPlus className="size-5 text-pink-600" />}
                            title="Pengambilan & Temu Duga"
                            description={`${alerts.recruitment_approval} kelulusan · ${alerts.recruitment_interview} scorecard`}
                            count={alerts.recruitment_total}
                        />
                        <AlertLink
                            href="/onboarding"
                            icon={
                                <ListChecks className="size-5 text-cyan-600" />
                            }
                            title="Onboarding"
                            description={`${alerts.onboarding_registration} pendaftaran pekerja · ${alerts.onboarding_overdue} tugasan lewat`}
                            count={alerts.onboarding_total}
                        />
                        <AlertLink
                            href="/latihan-kompetensi?status=pending"
                            icon={
                                <GraduationCap className="size-5 text-indigo-600" />
                            }
                            title="Latihan & Kompetensi"
                            description={`${alerts.training_supervisor} penyelia · ${alerts.training_hr} HR`}
                            count={alerts.training_total}
                        />
                        <AlertLink
                            href="/dokumen-hr"
                            icon={<Files className="size-5 text-rose-600" />}
                            title="Dokumen & Surat HR"
                            description={`${alerts.document_approval} kelulusan · ${alerts.document_expiring} hampir tamat`}
                            count={alerts.document_total}
                        />
                        <AlertLink
                            href="/disiplin-aduan"
                            icon={
                                <ShieldAlert className="size-5 text-red-600" />
                            }
                            title="Disiplin & Aduan"
                            description={`${alerts.discipline_triage} triage · ${alerts.discipline_investigation} siasatan · ${alerts.discipline_decision} keputusan`}
                            count={alerts.discipline_total}
                        />
                        <AlertLink
                            href="/berhenti-clearance"
                            icon={
                                <ClipboardCheck className="size-5 text-orange-600" />
                            }
                            title="Berhenti & Clearance"
                            description={`${alerts.separation_supervisor} penyelia · ${alerts.separation_hr} HR · ${alerts.separation_clearance} tugasan · ${alerts.separation_final_review} akhir`}
                            count={alerts.separation_total}
                        />
                    </div>
                )}

                <DropdownMenuSeparator />
                <div className="grid grid-cols-2 gap-1 sm:grid-cols-3">
                    <DropdownMenuItem asChild>
                        <Link
                            href="/permohonan-cuti"
                            className="flex cursor-pointer justify-center text-xs font-medium"
                        >
                            Buka Cuti
                        </Link>
                    </DropdownMenuItem>
                    <DropdownMenuItem asChild>
                        <Link
                            href="/permohonan-ot"
                            className="flex cursor-pointer justify-center text-xs font-medium"
                        >
                            Buka OT
                        </Link>
                    </DropdownMenuItem>
                    <DropdownMenuItem asChild>
                        <Link
                            href="/permohonan-tuntutan"
                            className="flex cursor-pointer justify-center text-xs font-medium"
                        >
                            Tuntutan
                        </Link>
                    </DropdownMenuItem>
                    <DropdownMenuItem asChild>
                        <Link
                            href="/prestasi"
                            className="flex cursor-pointer justify-center text-xs font-medium"
                        >
                            Prestasi
                        </Link>
                    </DropdownMenuItem>
                    <DropdownMenuItem asChild>
                        <Link
                            href="/pengambilan"
                            className="flex cursor-pointer justify-center text-xs font-medium"
                        >
                            Pengambilan
                        </Link>
                    </DropdownMenuItem>
                    <DropdownMenuItem asChild>
                        <Link
                            href="/onboarding"
                            className="flex cursor-pointer justify-center text-xs font-medium"
                        >
                            Onboarding
                        </Link>
                    </DropdownMenuItem>
                    <DropdownMenuItem asChild>
                        <Link
                            href="/latihan-kompetensi"
                            className="flex cursor-pointer justify-center text-xs font-medium"
                        >
                            Latihan
                        </Link>
                    </DropdownMenuItem>
                    <DropdownMenuItem asChild>
                        <Link
                            href="/disiplin-aduan"
                            className="flex cursor-pointer justify-center text-xs font-medium"
                        >
                            Disiplin
                        </Link>
                    </DropdownMenuItem>
                    <DropdownMenuItem asChild>
                        <Link
                            href="/berhenti-clearance"
                            className="flex cursor-pointer justify-center text-xs font-medium"
                        >
                            Clearance
                        </Link>
                    </DropdownMenuItem>
                </div>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
