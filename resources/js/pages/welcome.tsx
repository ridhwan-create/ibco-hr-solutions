import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    BarChart3,
    Building2,
    CalendarCheck2,
    Check,
    Clock3,
    ExternalLink,
    FileCheck2,
    Fingerprint,
    Gauge,
    LayoutDashboard,
    ShieldCheck,
    Sparkles,
    UsersRound,
    WalletCards,
} from 'lucide-react';
import { dashboard, login, register } from '@/routes';

const companyHighlights = [
    { value: '2020', label: 'Ditubuhkan' },
    { value: 'MOF', label: 'Berdaftar' },
    { value: 'G5', label: 'Gred CIDB' },
    { value: '100%', label: 'Bumiputera' },
];

const modules = [
    {
        icon: UsersRound,
        title: 'Pengurusan Pekerja',
        description:
            'Maklumat kakitangan, jawatan, pengguna dan proses onboarding dalam satu rekod tersusun.',
        accent: 'bg-blue-50 text-[#2563eb]',
    },
    {
        icon: CalendarCheck2,
        title: 'Masa, Cuti & Kehadiran',
        description:
            'Pantau kehadiran geolokasi, jadual kerja, permohonan cuti dan kerja lebih masa dengan mudah.',
        accent: 'bg-orange-50 text-[#f97316]',
    },
    {
        icon: WalletCards,
        title: 'Gaji & Statutori',
        description:
            'Urus payroll, slip gaji serta caruman KWSP, PERKESO, EIS dan PCB dengan lebih teratur.',
        accent: 'bg-emerald-50 text-emerald-600',
    },
    {
        icon: BarChart3,
        title: 'Prestasi & Laporan',
        description:
            'Nilai KPI, semak trend bulanan dan dapatkan gambaran pengurusan melalui laporan eksekutif.',
        accent: 'bg-violet-50 text-violet-600',
    },
];

const activityItems = [
    {
        icon: Check,
        title: 'Kehadiran berjaya direkodkan',
        detail: 'Ibu Pejabat · 8:42 pagi',
        color: 'bg-emerald-500',
    },
    {
        icon: FileCheck2,
        title: 'Permohonan cuti diluluskan',
        detail: 'Cuti tahunan · 2 hari',
        color: 'bg-[#2563eb]',
    },
    {
        icon: Gauge,
        title: 'Penilaian prestasi dikemas kini',
        detail: 'Kitaran semasa · 86%',
        color: 'bg-[#f97316]',
    },
];

export default function Welcome() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="IBCO HR Solutions">
                <meta
                    name="description"
                    content="Portal pengurusan sumber manusia bersepadu untuk warga kerja IBCO Solutions."
                />
            </Head>

            <div className="relative min-h-screen overflow-hidden bg-[#f7f9fc] text-[#0f1d35]">
                <div
                    className="pointer-events-none absolute -top-52 right-[-10rem] h-[34rem] w-[34rem] rounded-full bg-[#2563eb]/10 blur-[110px]"
                    aria-hidden="true"
                />
                <div
                    className="pointer-events-none absolute top-[42rem] -left-72 h-[34rem] w-[34rem] rounded-full bg-[#f97316]/8 blur-[110px]"
                    aria-hidden="true"
                />

                <header className="relative z-20 border-b border-[#2563eb]/10 bg-[#f7f9fc]/90 backdrop-blur-xl">
                    <div className="mx-auto flex min-h-20 w-full max-w-7xl items-center justify-between gap-5 px-5 sm:px-8 lg:px-10">
                        <a
                            href="https://www.ibcogroup.org"
                            target="_blank"
                            rel="noreferrer"
                            className="group flex min-w-0 items-center gap-3"
                            aria-label="Buka laman rasmi IBCO Solutions"
                        >
                            <span className="grid size-11 shrink-0 place-items-center rounded-2xl bg-gradient-to-br from-[#2563eb] to-[#1749b5] text-white shadow-[0_12px_28px_rgba(37,99,235,0.22)] transition-transform duration-300 group-hover:-translate-y-0.5">
                                <Building2 className="size-5" />
                            </span>
                            <span className="min-w-0">
                                <span className="block truncate text-lg leading-tight font-extrabold tracking-[-0.04em]">
                                    IBCO{' '}
                                    <span className="text-[#2563eb]">
                                        Solutions
                                    </span>
                                </span>
                                <span className="block truncate text-[10px] font-bold tracking-[0.18em] text-[#5d6b80] uppercase">
                                    HR Management Portal
                                </span>
                            </span>
                        </a>

                        <nav className="flex shrink-0 items-center gap-2 sm:gap-3">
                            <a
                                href="https://www.ibcogroup.org"
                                target="_blank"
                                rel="noreferrer"
                                className="hidden items-center gap-2 rounded-xl px-3 py-2 text-sm font-semibold text-[#5d6b80] transition-colors hover:bg-white hover:text-[#2563eb] md:inline-flex"
                            >
                                Laman Korporat
                                <ExternalLink className="size-3.5" />
                            </a>

                            {auth.user ? (
                                <Link
                                    href={dashboard()}
                                    className="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-[#2563eb] px-4 text-sm font-bold text-white shadow-[0_10px_24px_rgba(37,99,235,0.22)] transition hover:-translate-y-0.5 hover:bg-[#1749b5] sm:px-5"
                                >
                                    <LayoutDashboard className="size-4" />
                                    <span className="hidden sm:inline">
                                        Buka Dashboard
                                    </span>
                                    <span className="sm:hidden">Dashboard</span>
                                </Link>
                            ) : (
                                <>
                                    <Link
                                        href={login()}
                                        className="inline-flex min-h-11 items-center justify-center rounded-xl border border-[#2563eb]/15 bg-white px-4 text-sm font-bold text-[#1749b5] shadow-sm transition hover:-translate-y-0.5 hover:border-[#2563eb]/35 sm:px-5"
                                    >
                                        Log Masuk
                                    </Link>
                                    <Link
                                        href={register()}
                                        className="hidden min-h-11 items-center justify-center rounded-xl bg-[#2563eb] px-5 text-sm font-bold text-white shadow-[0_10px_24px_rgba(37,99,235,0.22)] transition hover:-translate-y-0.5 hover:bg-[#1749b5] sm:inline-flex"
                                    >
                                        Daftar Akaun
                                    </Link>
                                </>
                            )}
                        </nav>
                    </div>
                </header>

                <main className="relative z-10">
                    <section className="mx-auto grid w-full max-w-7xl items-center gap-14 px-5 py-14 sm:px-8 sm:py-20 lg:grid-cols-[minmax(0,1.02fr)_minmax(420px,0.98fr)] lg:px-10 lg:py-24">
                        <div>
                            <div className="mb-6 inline-flex items-center gap-2 rounded-full border border-[#2563eb]/15 bg-white/85 px-3.5 py-2 text-xs font-extrabold tracking-[0.08em] text-[#1749b5] uppercase shadow-sm">
                                <Sparkles className="size-3.5 text-[#f97316]" />
                                Portal Sumber Manusia Bersepadu
                            </div>

                            <h1 className="max-w-3xl text-[clamp(2.8rem,7vw,5.8rem)] leading-[0.96] font-extrabold tracking-[-0.067em]">
                                Pengurusan HR.
                                <span className="mt-2 block text-[#2563eb]">
                                    Lebih tersusun.
                                </span>
                            </h1>

                            <p className="mt-7 max-w-2xl text-base leading-8 text-[#5d6b80] sm:text-lg">
                                Satu platform untuk mengurus pekerja, kehadiran,
                                cuti, tuntutan, gaji dan prestasi—dibangunkan
                                untuk membantu pasukan IBCO bekerja dengan lebih
                                pantas, tepat dan telus.
                            </p>

                            <div className="mt-9 flex flex-col gap-3 sm:flex-row">
                                <Link
                                    href={auth.user ? dashboard() : login()}
                                    className="group inline-flex min-h-13 items-center justify-center gap-3 rounded-2xl bg-[#2563eb] px-6 text-sm font-extrabold text-white shadow-[0_16px_36px_rgba(37,99,235,0.24)] transition hover:-translate-y-1 hover:bg-[#1749b5]"
                                >
                                    {auth.user
                                        ? 'Terus ke Dashboard'
                                        : 'Akses Portal HR'}
                                    <ArrowRight className="size-4 transition-transform group-hover:translate-x-1" />
                                </Link>
                                <a
                                    href="#fungsi"
                                    className="inline-flex min-h-13 items-center justify-center rounded-2xl border border-[#2563eb]/15 bg-white px-6 text-sm font-extrabold text-[#0f1d35] shadow-sm transition hover:-translate-y-1 hover:border-[#2563eb]/35 hover:text-[#2563eb]"
                                >
                                    Lihat Fungsi Utama
                                </a>
                            </div>

                            <div className="mt-8 flex items-center gap-3 text-sm text-[#5d6b80]">
                                <span className="grid size-9 shrink-0 place-items-center rounded-xl bg-emerald-50 text-emerald-600">
                                    <ShieldCheck className="size-4.5" />
                                </span>
                                <span>
                                    Akses selamat untuk warga kerja IBCO
                                    Solutions.
                                </span>
                            </div>
                        </div>

                        <div className="relative mx-auto w-full max-w-[620px] lg:mx-0">
                            <div
                                className="absolute -inset-5 rounded-[2.5rem] bg-gradient-to-br from-[#2563eb]/20 via-transparent to-[#f97316]/20 blur-2xl"
                                aria-hidden="true"
                            />
                            <div className="relative overflow-hidden rounded-[2rem] border border-white/15 bg-gradient-to-br from-[#0f1d35] via-[#12284b] to-[#1749b5] p-4 shadow-[0_32px_80px_rgba(15,29,53,0.26)] sm:p-6">
                                <div
                                    className="pointer-events-none absolute -top-28 -right-24 size-72 rounded-full bg-[#60a5fa]/20 blur-3xl"
                                    aria-hidden="true"
                                />
                                <div
                                    className="pointer-events-none absolute -bottom-24 -left-16 size-60 rounded-full bg-[#f97316]/15 blur-3xl"
                                    aria-hidden="true"
                                />

                                <div className="relative mb-5 flex items-center justify-between text-white">
                                    <div>
                                        <p className="text-xs font-bold tracking-[0.16em] text-blue-200 uppercase">
                                            Ringkasan HR
                                        </p>
                                        <p className="mt-1 text-lg font-bold">
                                            Selamat datang ke IBCO
                                        </p>
                                    </div>
                                    <span className="grid size-11 place-items-center rounded-2xl border border-white/15 bg-white/10 backdrop-blur">
                                        <Fingerprint className="size-5" />
                                    </span>
                                </div>

                                <div className="relative grid grid-cols-2 gap-3">
                                    <div className="rounded-2xl bg-white p-4 shadow-sm">
                                        <div className="flex items-center justify-between gap-3">
                                            <span className="text-[11px] font-bold text-[#5d6b80]">
                                                Kehadiran Hari Ini
                                            </span>
                                            <span className="size-2 rounded-full bg-emerald-500 shadow-[0_0_0_4px_rgba(16,185,129,0.12)]" />
                                        </div>
                                        <p className="mt-5 text-2xl font-extrabold tracking-[-0.05em] text-[#0f1d35]">
                                            Masa Nyata
                                        </p>
                                        <p className="mt-1 flex items-center gap-1.5 text-[11px] font-semibold text-emerald-600">
                                            <Clock3 className="size-3.5" />
                                            Geolokasi aktif
                                        </p>
                                    </div>

                                    <div className="rounded-2xl bg-[#eef4ff] p-4 shadow-sm">
                                        <div className="flex items-center justify-between gap-3">
                                            <span className="text-[11px] font-bold text-[#5d6b80]">
                                                Proses Bersepadu
                                            </span>
                                            <span className="rounded-full bg-[#2563eb]/10 px-2 py-1 text-[9px] font-extrabold text-[#2563eb]">
                                                LIVE
                                            </span>
                                        </div>
                                        <p className="mt-5 text-2xl font-extrabold tracking-[-0.05em] text-[#0f1d35]">
                                            Satu Portal
                                        </p>
                                        <p className="mt-1 text-[11px] font-semibold text-[#2563eb]">
                                            HR · Payroll · Prestasi
                                        </p>
                                    </div>
                                </div>

                                <div className="relative mt-3 rounded-2xl bg-white p-4 shadow-sm sm:p-5">
                                    <div className="mb-4 flex items-center justify-between">
                                        <div>
                                            <p className="text-sm font-extrabold text-[#0f1d35]">
                                                Aktiviti Terkini
                                            </p>
                                            <p className="mt-0.5 text-[10px] text-[#5d6b80]">
                                                Kemas kini automatik sistem
                                            </p>
                                        </div>
                                        <span className="rounded-full bg-emerald-50 px-2.5 py-1 text-[9px] font-extrabold text-emerald-600 uppercase">
                                            Beroperasi
                                        </span>
                                    </div>

                                    <div className="space-y-2.5">
                                        {activityItems.map((item) => {
                                            const Icon = item.icon;

                                            return (
                                                <div
                                                    key={item.title}
                                                    className="flex items-center gap-3 rounded-xl border border-[#2563eb]/8 bg-[#f7f9fc] p-3"
                                                >
                                                    <span
                                                        className={`grid size-8 shrink-0 place-items-center rounded-lg text-white ${item.color}`}
                                                    >
                                                        <Icon className="size-3.5" />
                                                    </span>
                                                    <span className="min-w-0">
                                                        <span className="block truncate text-[11px] font-extrabold text-[#0f1d35]">
                                                            {item.title}
                                                        </span>
                                                        <span className="mt-0.5 block truncate text-[10px] text-[#5d6b80]">
                                                            {item.detail}
                                                        </span>
                                                    </span>
                                                </div>
                                            );
                                        })}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section
                        className="border-y border-[#2563eb]/10 bg-white/65"
                        aria-label="Maklumat korporat IBCO Solutions"
                    >
                        <div className="mx-auto grid w-full max-w-7xl grid-cols-2 divide-x divide-y divide-[#2563eb]/10 px-5 sm:px-8 md:grid-cols-4 md:divide-y-0 lg:px-10">
                            {companyHighlights.map((item) => (
                                <div
                                    key={item.label}
                                    className="px-4 py-7 text-center sm:py-8"
                                >
                                    <p className="text-2xl font-extrabold tracking-[-0.05em] text-[#0f1d35] sm:text-3xl">
                                        {item.value}
                                    </p>
                                    <p className="mt-1 text-[11px] font-bold tracking-[0.08em] text-[#5d6b80] uppercase">
                                        {item.label}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </section>

                    <section
                        id="fungsi"
                        className="mx-auto w-full max-w-7xl scroll-mt-24 px-5 py-20 sm:px-8 sm:py-24 lg:px-10"
                    >
                        <div className="max-w-3xl">
                            <p className="text-xs font-extrabold tracking-[0.18em] text-[#f97316] uppercase">
                                Ekosistem IBCO HR
                            </p>
                            <h2 className="mt-4 text-4xl leading-[1.05] font-extrabold tracking-[-0.055em] text-[#0f1d35] sm:text-5xl">
                                Satu ruang kerja.
                                <span className="text-[#2563eb]">
                                    {' '}
                                    Proses HR lebih lancar.
                                </span>
                            </h2>
                            <p className="mt-5 max-w-2xl leading-7 text-[#5d6b80]">
                                Modul utama disusun mengikut aliran kerja harian
                                supaya maklumat mudah dicapai, tindakan mudah
                                dijejaki dan keputusan dapat dibuat dengan lebih
                                yakin.
                            </p>
                        </div>

                        <div className="mt-12 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                            {modules.map((module) => {
                                const Icon = module.icon;

                                return (
                                    <article
                                        key={module.title}
                                        className="group rounded-[1.5rem] border border-[#2563eb]/10 bg-white p-6 shadow-[0_16px_38px_rgba(15,29,53,0.06)] transition duration-300 hover:-translate-y-1.5 hover:border-[#2563eb]/30 hover:shadow-[0_22px_50px_rgba(37,99,235,0.10)]"
                                    >
                                        <span
                                            className={`grid size-12 place-items-center rounded-2xl ${module.accent}`}
                                        >
                                            <Icon className="size-5" />
                                        </span>
                                        <h3 className="mt-7 text-lg font-extrabold tracking-[-0.03em] text-[#0f1d35]">
                                            {module.title}
                                        </h3>
                                        <p className="mt-3 text-sm leading-6 text-[#5d6b80]">
                                            {module.description}
                                        </p>
                                    </article>
                                );
                            })}
                        </div>
                    </section>

                    <section className="mx-auto w-full max-w-7xl px-5 pb-20 sm:px-8 sm:pb-24 lg:px-10">
                        <div className="relative overflow-hidden rounded-[2rem] bg-[#0f1d35] px-6 py-10 text-white shadow-[0_28px_70px_rgba(15,29,53,0.18)] sm:px-10 sm:py-12 lg:flex lg:items-center lg:justify-between lg:gap-10">
                            <div
                                className="pointer-events-none absolute -top-28 right-0 size-80 rounded-full bg-[#2563eb]/30 blur-3xl"
                                aria-hidden="true"
                            />
                            <div className="relative max-w-2xl">
                                <p className="text-xs font-extrabold tracking-[0.16em] text-orange-300 uppercase">
                                    Professional solutions. Built on trust.
                                </p>
                                <h2 className="mt-3 text-3xl font-extrabold tracking-[-0.045em] sm:text-4xl">
                                    Bersedia untuk memulakan kerja?
                                </h2>
                                <p className="mt-3 leading-7 text-blue-100">
                                    Log masuk menggunakan akaun yang dibenarkan
                                    untuk mengakses modul dan perkhidmatan HR
                                    IBCO Solutions.
                                </p>
                            </div>
                            <Link
                                href={auth.user ? dashboard() : login()}
                                className="group relative mt-7 inline-flex min-h-13 shrink-0 items-center justify-center gap-3 rounded-2xl bg-white px-6 text-sm font-extrabold text-[#1749b5] shadow-lg transition hover:-translate-y-1 lg:mt-0"
                            >
                                {auth.user
                                    ? 'Buka Dashboard'
                                    : 'Log Masuk Sekarang'}
                                <ArrowRight className="size-4 transition-transform group-hover:translate-x-1" />
                            </Link>
                        </div>
                    </section>
                </main>

                <footer className="relative z-10 border-t border-[#2563eb]/10 bg-white/55">
                    <div className="mx-auto flex w-full max-w-7xl flex-col gap-5 px-5 py-8 text-sm text-[#5d6b80] sm:flex-row sm:items-center sm:justify-between sm:px-8 lg:px-10">
                        <div>
                            <p className="font-extrabold text-[#0f1d35]">
                                IBCO Solutions
                            </p>
                            <p className="mt-1 text-xs">
                                Professional Corporate Gateway
                            </p>
                        </div>
                        <div className="flex flex-col gap-2 text-xs sm:items-end">
                            <span>
                                © {new Date().getFullYear()} IBCO Solutions. Hak
                                Cipta Terpelihara.
                            </span>
                            <a
                                href="https://www.ibcogroup.org"
                                target="_blank"
                                rel="noreferrer"
                                className="inline-flex items-center gap-1.5 font-bold text-[#2563eb] hover:underline"
                            >
                                ibcogroup.org
                                <ExternalLink className="size-3" />
                            </a>
                        </div>
                    </div>
                </footer>
            </div>
        </>
    );
}
