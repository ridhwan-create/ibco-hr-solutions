import { Link, router, usePage } from '@inertiajs/react';
import {
    BellRing,
    CheckCircle2,
    ChevronRight,
    ShieldCheck,
    UserRoundCheck,
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

export function LeaveApprovalNotifications() {
    const { leaveApprovalAlerts } = usePage<{
        leaveApprovalAlerts?: LeaveApprovalAlerts;
    }>().props;
    const alerts = leaveApprovalAlerts ?? {
        enabled: false,
        total: 0,
        supervisor: 0,
        hr: 0,
        polling_seconds: 60,
    };

    useEffect(() => {
        if (!alerts.enabled) {
            return;
        }

        const interval = window.setInterval(
            () =>
                router.reload({
                    only: ['leaveApprovalAlerts'],
                }),
            Math.max(alerts.polling_seconds, 30) * 1000,
        );

        return () => window.clearInterval(interval);
    }, [alerts.enabled, alerts.polling_seconds]);

    if (!alerts.enabled) {
        return null;
    }

    const triggerLabel =
        alerts.total > 0
            ? `${alerts.total} permohonan cuti menunggu tindakan`
            : 'Tiada permohonan cuti menunggu tindakan';

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
                className="w-[min(22rem,calc(100vw-2rem))] p-2"
            >
                <DropdownMenuLabel className="flex items-start justify-between gap-4 px-2 py-2">
                    <span>
                        <span className="block font-semibold">
                            Kelulusan Cuti
                        </span>
                        <span className="mt-0.5 block text-xs font-normal text-muted-foreground">
                            Dikemas kini secara automatik setiap minit
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
                        {alerts.supervisor > 0 && (
                            <DropdownMenuItem asChild className="p-0">
                                <Link
                                    href="/permohonan-cuti?status=pending&stage=supervisor"
                                    className="flex cursor-pointer items-center gap-3 rounded-md px-2 py-3"
                                >
                                    <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-amber-500/10">
                                        <UserRoundCheck className="size-5 text-amber-600" />
                                    </span>
                                    <span className="min-w-0 flex-1">
                                        <span className="block text-sm font-medium">
                                            Menunggu Penyelia
                                        </span>
                                        <span className="block text-xs text-muted-foreground">
                                            Sokong atau tolak permohonan
                                        </span>
                                    </span>
                                    <span className="rounded-full bg-amber-500/15 px-2 py-0.5 text-xs font-bold text-amber-700 dark:text-amber-300">
                                        {countLabel(alerts.supervisor)}
                                    </span>
                                    <ChevronRight className="size-4 text-muted-foreground" />
                                </Link>
                            </DropdownMenuItem>
                        )}

                        {alerts.hr > 0 && (
                            <DropdownMenuItem asChild className="p-0">
                                <Link
                                    href="/permohonan-cuti?status=pending&stage=hr"
                                    className="flex cursor-pointer items-center gap-3 rounded-md px-2 py-3"
                                >
                                    <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-sky-500/10">
                                        <ShieldCheck className="size-5 text-sky-600" />
                                    </span>
                                    <span className="min-w-0 flex-1">
                                        <span className="block text-sm font-medium">
                                            Menunggu HR
                                        </span>
                                        <span className="block text-xs text-muted-foreground">
                                            Kelulusan akhir diperlukan
                                        </span>
                                    </span>
                                    <span className="rounded-full bg-sky-500/15 px-2 py-0.5 text-xs font-bold text-sky-700 dark:text-sky-300">
                                        {countLabel(alerts.hr)}
                                    </span>
                                    <ChevronRight className="size-4 text-muted-foreground" />
                                </Link>
                            </DropdownMenuItem>
                        )}
                    </div>
                )}

                <DropdownMenuSeparator />
                <DropdownMenuItem asChild>
                    <Link
                        href="/permohonan-cuti"
                        className="flex cursor-pointer items-center justify-between font-medium"
                    >
                        Buka Permohonan Cuti
                        <ChevronRight />
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
