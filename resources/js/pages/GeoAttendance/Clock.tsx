import { Head, router } from '@inertiajs/react';
import {
    AlertCircle,
    Building2,
    CheckCircle2,
    Clock3,
    Crosshair,
    LocateFixed,
    LogIn,
    LogOut,
    MapPin,
    Navigation,
    ShieldCheck,
    Smartphone,
} from 'lucide-react';
import { useMemo, useState } from 'react';
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

type Employee = {
    id: number;
    employee_id: string | null;
    name: string | null;
};

type Office = {
    id: number;
    name: string;
    address: string | null;
    latitude: string;
    longitude: string;
    radius_meters: number;
    accuracy_limit_meters: number;
    is_active: boolean;
};

type AttendanceRecord = {
    id: number;
    attendance_date: string;
    scheduled_start_at: string | null;
    scheduled_end_at: string | null;
    late_minutes: number;
    early_departure_minutes: number;
    attendance_day_type: string | null;
    clock_in_at: string;
    clock_out_at: string | null;
    clock_in_accuracy_meters: string | null;
    clock_in_distance_meters: string | null;
    clock_out_accuracy_meters: string | null;
    clock_out_distance_meters: string | null;
    source: 'geolocation' | 'manual';
    status: 'active' | 'cancelled';
    notes: string | null;
    office: { id: number; name: string } | null;
};

type ClockProps = {
    isSecureContextRequired: boolean;
    serverTime: string;
    employee: Employee | null;
    office: Office | null;
    todayRecord: AttendanceRecord | null;
    history: AttendanceRecord[];
};

type PositionPreview = {
    accuracy: number;
};

type ActionState = 'idle' | 'locating' | 'submitting';

function formatDate(value: string): string {
    return new Intl.DateTimeFormat('ms-MY', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(new Date(`${value}T00:00:00`));
}

function formatTime(value: string | null): string {
    if (!value) {
        return '-';
    }

    return new Intl.DateTimeFormat('ms-MY', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    }).format(new Date(value));
}

function distanceLabel(value: string | null): string {
    return value === null ? '-' : `${Math.round(Number(value))} m`;
}

export default function GeoAttendanceClock({
    isSecureContextRequired,
    serverTime,
    employee,
    office,
    todayRecord,
    history,
}: ClockProps) {
    const [actionState, setActionState] = useState<ActionState>('idle');
    const [positionPreview, setPositionPreview] =
        useState<PositionPreview | null>(null);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);

    const secureContextAvailable =
        typeof window === 'undefined' ||
        window.isSecureContext ||
        window.location.hostname === 'localhost' ||
        window.location.hostname === '127.0.0.1';
    const canClockIn = !todayRecord;
    const canClockOut =
        todayRecord?.status === 'active' && !todayRecord.clock_out_at;
    const accountReady = Boolean(employee && office);

    const todayStatus = useMemo(() => {
        if (!todayRecord) {
            return {
                label: 'Belum Rakam Masuk',
                className:
                    'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300',
            };
        }

        if (todayRecord.status === 'cancelled') {
            return {
                label: 'Dibatalkan HR',
                className:
                    'border-red-500/30 bg-red-500/10 text-red-700 dark:text-red-300',
            };
        }

        if (todayRecord.clock_out_at) {
            return {
                label: 'Selesai',
                className:
                    'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
            };
        }

        return {
            label: 'Sedang Bekerja',
            className:
                'border-blue-500/30 bg-blue-500/10 text-blue-700 dark:text-blue-300',
        };
    }, [todayRecord]);

    const recordAttendance = (action: 'in' | 'out') => {
        setErrorMessage(null);
        setPositionPreview(null);

        if (!accountReady) {
            setErrorMessage(
                'Akaun anda belum dipautkan kepada pekerja dan lokasi pejabat.',
            );

            return;
        }

        if (!secureContextAvailable && isSecureContextRequired) {
            setErrorMessage(
                'Geolocation memerlukan sambungan HTTPS yang selamat.',
            );

            return;
        }

        if (!navigator.geolocation) {
            setErrorMessage(
                'Peranti atau browser ini tidak menyokong geolocation.',
            );

            return;
        }

        setActionState('locating');

        navigator.geolocation.getCurrentPosition(
            (position) => {
                const payload = {
                    latitude: position.coords.latitude,
                    longitude: position.coords.longitude,
                    accuracy: position.coords.accuracy,
                };

                setPositionPreview({ accuracy: position.coords.accuracy });
                setActionState('submitting');

                router.post(
                    action === 'in'
                        ? '/kehadiran/rakam-masuk'
                        : '/kehadiran/rakam-keluar',
                    payload,
                    {
                        preserveScroll: true,
                        onError: (errors) => {
                            const firstError = Object.values(errors).find(
                                (value) => typeof value === 'string',
                            );
                            setErrorMessage(
                                firstError ||
                                    'Rakaman tidak berjaya. Sila cuba lagi.',
                            );
                        },
                        onFinish: () => setActionState('idle'),
                    },
                );
            },
            (error) => {
                const messages: Record<number, string> = {
                    1: 'Kebenaran lokasi ditolak. Benarkan akses lokasi dalam tetapan browser.',
                    2: 'Lokasi semasa tidak dapat dikenal pasti. Pastikan GPS dihidupkan.',
                    3: 'Masa mendapatkan lokasi telah tamat. Cuba di kawasan dengan isyarat GPS lebih baik.',
                };

                setErrorMessage(
                    messages[error.code] ||
                        'Lokasi semasa tidak dapat diperoleh.',
                );
                setActionState('idle');
            },
            {
                enableHighAccuracy: true,
                timeout: 20000,
                maximumAge: 0,
            },
        );
    };

    return (
        <>
            <Head title="Rakam Kehadiran" />

            <div className="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-5 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div className="space-y-1">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Rakam Kehadiran
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Lokasi hanya diambil ketika butang rakaman ditekan.
                        </p>
                    </div>
                    <Badge variant="outline" className={todayStatus.className}>
                        {todayStatus.label}
                    </Badge>
                </div>

                {!accountReady && (
                    <Alert variant="destructive">
                        <AlertCircle />
                        <AlertTitle>Akaun belum lengkap</AlertTitle>
                        <AlertDescription>
                            Hubungi Super Admin untuk memautkan akaun anda
                            kepada rekod pekerja dan lokasi pejabat.
                        </AlertDescription>
                    </Alert>
                )}

                {!secureContextAvailable && isSecureContextRequired && (
                    <Alert variant="destructive">
                        <ShieldCheck />
                        <AlertTitle>HTTPS diperlukan</AlertTitle>
                        <AlertDescription>
                            Buka sistem melalui alamat HTTPS supaya browser
                            membenarkan akses geolocation.
                        </AlertDescription>
                    </Alert>
                )}

                {errorMessage && (
                    <Alert variant="destructive">
                        <AlertCircle />
                        <AlertTitle>Rakaman tidak berjaya</AlertTitle>
                        <AlertDescription>{errorMessage}</AlertDescription>
                    </Alert>
                )}

                <div className="grid gap-4 lg:grid-cols-[1.3fr_0.7fr]">
                    <Card className="overflow-hidden border-primary/20">
                        <CardHeader className="bg-gradient-to-br from-primary/10 via-background to-background">
                            <div className="flex items-start justify-between gap-4">
                                <div className="space-y-1">
                                    <CardTitle>
                                        {employee?.name || 'Employee'}
                                    </CardTitle>
                                    <CardDescription>
                                        {employee?.employee_id ||
                                            'ID pekerja belum dipautkan'}
                                    </CardDescription>
                                </div>
                                <div className="rounded-2xl bg-primary p-3 text-primary-foreground shadow-sm">
                                    <Smartphone className="size-6" />
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-5 pt-6">
                            <div className="grid grid-cols-2 gap-3">
                                <div className="rounded-xl border bg-muted/30 p-4">
                                    <p className="text-xs text-muted-foreground">
                                        Waktu Masuk
                                    </p>
                                    <p className="mt-1 text-xl font-semibold tabular-nums">
                                        {formatTime(
                                            todayRecord?.clock_in_at ?? null,
                                        )}
                                    </p>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        Jarak:{' '}
                                        {distanceLabel(
                                            todayRecord?.clock_in_distance_meters ??
                                                null,
                                        )}
                                    </p>
                                </div>
                                <div className="rounded-xl border bg-muted/30 p-4">
                                    <p className="text-xs text-muted-foreground">
                                        Waktu Keluar
                                    </p>
                                    <p className="mt-1 text-xl font-semibold tabular-nums">
                                        {formatTime(
                                            todayRecord?.clock_out_at ?? null,
                                        )}
                                    </p>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        Jarak:{' '}
                                        {distanceLabel(
                                            todayRecord?.clock_out_distance_meters ??
                                                null,
                                        )}
                                    </p>
                                </div>
                            </div>

                            {todayRecord && (
                                <div className="grid gap-3 rounded-xl border bg-muted/20 p-4 sm:grid-cols-3">
                                    <div>
                                        <p className="text-xs text-muted-foreground">
                                            Jadual Roster
                                        </p>
                                        <p className="mt-1 font-medium">
                                            {todayRecord.scheduled_start_at
                                                ? `${formatTime(todayRecord.scheduled_start_at)} – ${formatTime(todayRecord.scheduled_end_at)}`
                                                : todayRecord.attendance_day_type ||
                                                  'Tiada roster'}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-xs text-muted-foreground">
                                            Lewat
                                        </p>
                                        <p className="mt-1 font-medium">
                                            {todayRecord.late_minutes} minit
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-xs text-muted-foreground">
                                            Pulang Awal
                                        </p>
                                        <p className="mt-1 font-medium">
                                            {
                                                todayRecord.early_departure_minutes
                                            }{' '}
                                            minit
                                        </p>
                                    </div>
                                </div>
                            )}

                            {positionPreview && (
                                <div className="flex items-center gap-2 rounded-lg bg-muted px-3 py-2 text-sm">
                                    <Crosshair className="size-4 text-primary" />
                                    Ketepatan GPS terakhir: ±
                                    {Math.round(positionPreview.accuracy)} meter
                                </div>
                            )}

                            <div className="grid gap-3 sm:grid-cols-2">
                                <Button
                                    size="lg"
                                    className="h-14 text-base"
                                    disabled={
                                        !accountReady ||
                                        !canClockIn ||
                                        actionState !== 'idle'
                                    }
                                    onClick={() => recordAttendance('in')}
                                >
                                    {actionState === 'locating' ? (
                                        <LocateFixed className="animate-pulse" />
                                    ) : (
                                        <LogIn />
                                    )}
                                    {actionState === 'locating'
                                        ? 'Mendapatkan GPS...'
                                        : actionState === 'submitting'
                                          ? 'Merekod...'
                                          : 'Rakam Masuk'}
                                </Button>
                                <Button
                                    size="lg"
                                    variant="outline"
                                    className="h-14 text-base"
                                    disabled={
                                        !accountReady ||
                                        !canClockOut ||
                                        actionState !== 'idle'
                                    }
                                    onClick={() => recordAttendance('out')}
                                >
                                    <LogOut />
                                    Rakam Keluar
                                </Button>
                            </div>

                            <p className="text-center text-xs text-muted-foreground">
                                Waktu rasmi ditentukan oleh server. Waktu
                                server: {formatTime(serverTime)}
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Building2 className="size-5 text-primary" />
                                Lokasi Ditugaskan
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {office ? (
                                <>
                                    <div>
                                        <p className="font-medium">
                                            {office.name}
                                        </p>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            {office.address ||
                                                'Alamat tidak dinyatakan'}
                                        </p>
                                    </div>
                                    <div className="grid grid-cols-2 gap-3 text-sm">
                                        <div className="rounded-lg border p-3">
                                            <MapPin className="mb-2 size-4 text-primary" />
                                            <p className="text-xs text-muted-foreground">
                                                Radius
                                            </p>
                                            <p className="font-semibold">
                                                {office.radius_meters} m
                                            </p>
                                        </div>
                                        <div className="rounded-lg border p-3">
                                            <Navigation className="mb-2 size-4 text-primary" />
                                            <p className="text-xs text-muted-foreground">
                                                Had Ketepatan
                                            </p>
                                            <p className="font-semibold">
                                                ±{office.accuracy_limit_meters}{' '}
                                                m
                                            </p>
                                        </div>
                                    </div>
                                    <div className="flex items-start gap-2 rounded-lg bg-emerald-500/10 p-3 text-sm text-emerald-700 dark:text-emerald-300">
                                        <CheckCircle2 className="mt-0.5 size-4 shrink-0" />
                                        Rakaman diterima apabila lokasi berada
                                        dalam radius pejabat dan bacaan GPS
                                        memenuhi had ketepatan.
                                    </div>
                                </>
                            ) : (
                                <p className="py-8 text-center text-sm text-muted-foreground">
                                    Tiada lokasi pejabat ditugaskan.
                                </p>
                            )}
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Clock3 className="size-5 text-muted-foreground" />
                            Sejarah Kehadiran Sendiri
                        </CardTitle>
                        <CardDescription>
                            Empat belas rekod kehadiran terkini.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {history.length > 0 ? (
                            <div className="divide-y rounded-xl border">
                                {history.map((record) => (
                                    <div
                                        key={record.id}
                                        className="grid gap-3 p-4 sm:grid-cols-[1fr_auto_auto] sm:items-center"
                                    >
                                        <div>
                                            <p className="font-medium">
                                                {formatDate(
                                                    record.attendance_date,
                                                )}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {record.office?.name ||
                                                    'Lokasi tidak tersedia'}{' '}
                                                ·{' '}
                                                {record.source === 'geolocation'
                                                    ? 'Geolocation'
                                                    : 'Rekod manual HR'}
                                            </p>
                                        </div>
                                        <div className="text-sm">
                                            <span className="text-muted-foreground">
                                                Masuk:{' '}
                                            </span>
                                            {formatTime(record.clock_in_at)}
                                        </div>
                                        <div className="flex items-center justify-between gap-3 text-sm sm:justify-end">
                                            <span>
                                                <span className="text-muted-foreground">
                                                    Keluar:{' '}
                                                </span>
                                                {formatTime(
                                                    record.clock_out_at,
                                                )}
                                            </span>
                                            {record.status === 'cancelled' && (
                                                <Badge variant="destructive">
                                                    Dibatalkan
                                                </Badge>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="py-10 text-center text-sm text-muted-foreground">
                                Belum ada rekod kehadiran geolocation.
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

GeoAttendanceClock.layout = {
    breadcrumbs: [
        { title: 'Kehadiran', href: '/kehadiran/rakam' },
        { title: 'Rakam Kehadiran', href: '/kehadiran/rakam' },
    ],
};
