import { Link } from '@inertiajs/react';
import {
    BadgeCheck,
    Building2,
    CalendarCheck2,
    ChartNoAxesCombined,
    ShieldCheck,
} from 'lucide-react';
import type { AuthLayoutProps } from '@/types';
import { home } from '@/routes';

const portalBenefits = [
    {
        icon: CalendarCheck2,
        title: 'Kehadiran & cuti',
        description: 'Semakan dan permohonan dalam satu aliran kerja.',
    },
    {
        icon: ChartNoAxesCombined,
        title: 'Prestasi & laporan',
        description: 'Maklumat operasi yang tersusun untuk tindakan pantas.',
    },
    {
        icon: ShieldCheck,
        title: 'Akses terkawal',
        description: 'Keselamatan berasaskan peranan untuk setiap pengguna.',
    },
];

export default function LoginLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="min-h-svh bg-[#f6f8fc] text-[#0f1d35] lg:grid lg:grid-cols-[minmax(360px,0.88fr)_minmax(0,1.12fr)]">
            <aside className="relative overflow-hidden bg-gradient-to-br from-[#0f1d35] via-[#12284b] to-[#1749b5] px-5 py-5 text-white sm:px-8 sm:py-6 lg:flex lg:min-h-svh lg:flex-col lg:justify-between lg:px-12 lg:py-10 xl:px-16">
                <div
                    className="pointer-events-none absolute -top-28 -right-24 size-72 rounded-full bg-[#60a5fa]/20 blur-3xl"
                    aria-hidden="true"
                />
                <div
                    className="pointer-events-none absolute -bottom-32 -left-24 size-80 rounded-full bg-[#f97316]/15 blur-3xl"
                    aria-hidden="true"
                />
                <div
                    className="pointer-events-none absolute top-1/3 -right-28 size-64 rounded-full border border-white/10"
                    aria-hidden="true"
                />

                <Link
                    href={home()}
                    className="relative z-10 inline-flex items-center gap-3"
                    aria-label="Kembali ke laman utama IBCO HR Solutions"
                >
                    <span className="grid size-11 place-items-center rounded-2xl bg-white/12 ring-1 ring-white/15 backdrop-blur">
                        <Building2 className="size-5" />
                    </span>
                    <span>
                        <span className="block text-lg leading-tight font-extrabold tracking-[-0.04em]">
                            IBCO{' '}
                            <span className="text-blue-300">Solutions</span>
                        </span>
                        <span className="block text-[10px] font-bold tracking-[0.18em] text-blue-100/70 uppercase">
                            HR Management Portal
                        </span>
                    </span>
                </Link>

                <div className="relative z-10 my-16 hidden max-w-xl lg:block">
                    <span className="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/8 px-3.5 py-2 text-xs font-bold tracking-[0.08em] text-blue-100 uppercase backdrop-blur">
                        <BadgeCheck className="size-3.5 text-[#fb923c]" />
                        Ruang kerja warga IBCO
                    </span>

                    <h2 className="mt-7 text-4xl leading-[1.08] font-extrabold tracking-[-0.055em] xl:text-5xl">
                        Urus tenaga kerja
                        <span className="mt-1 block text-blue-300">
                            dengan lebih yakin.
                        </span>
                    </h2>

                    <p className="mt-6 max-w-lg text-base leading-7 text-blue-100/75">
                        Akses pengurusan pekerja, kehadiran, cuti, payroll dan
                        prestasi melalui satu portal yang selamat dan tersusun.
                    </p>

                    <div className="mt-9 grid gap-4">
                        {portalBenefits.map((benefit) => {
                            const Icon = benefit.icon;

                            return (
                                <div
                                    key={benefit.title}
                                    className="flex items-start gap-4"
                                >
                                    <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-white/10 text-blue-200 ring-1 ring-white/10">
                                        <Icon className="size-4.5" />
                                    </span>
                                    <span>
                                        <span className="block text-sm font-bold">
                                            {benefit.title}
                                        </span>
                                        <span className="mt-1 block text-xs leading-5 text-blue-100/65">
                                            {benefit.description}
                                        </span>
                                    </span>
                                </div>
                            );
                        })}
                    </div>
                </div>

                <div className="relative z-10 hidden items-center justify-between gap-4 border-t border-white/10 pt-6 text-xs text-blue-100/60 lg:flex">
                    <span>Professional solutions. Built on trust.</span>
                    <span>Sejak 2020</span>
                </div>
            </aside>

            <main className="relative flex min-h-[calc(100svh-84px)] items-center justify-center overflow-hidden px-5 py-10 sm:px-8 sm:py-12 lg:min-h-svh lg:px-12 xl:px-20">
                <div
                    className="pointer-events-none absolute -top-40 -right-32 size-96 rounded-full bg-[#2563eb]/8 blur-3xl"
                    aria-hidden="true"
                />
                <div
                    className="pointer-events-none absolute -bottom-40 -left-32 size-96 rounded-full bg-[#f97316]/6 blur-3xl"
                    aria-hidden="true"
                />

                <div className="relative w-full max-w-[460px]">
                    <div className="mb-6 lg:hidden">
                        <span className="inline-flex items-center gap-2 rounded-full border border-[#2563eb]/15 bg-white px-3 py-2 text-[11px] font-extrabold tracking-[0.08em] text-[#1749b5] uppercase shadow-sm">
                            <ShieldCheck className="size-3.5 text-[#f97316]" />
                            Portal HR selamat
                        </span>
                    </div>

                    <section className="rounded-[1.75rem] border border-[#dfe7f1] bg-white p-6 shadow-[0_24px_70px_rgba(15,29,53,0.10)] sm:p-9">
                        <div className="mb-8">
                            <span className="grid size-12 place-items-center rounded-2xl bg-[#eef4ff] text-[#2563eb]">
                                <ShieldCheck className="size-5" />
                            </span>
                            <h1 className="mt-5 text-3xl font-extrabold tracking-[-0.045em] text-[#0f1d35]">
                                {title}
                            </h1>
                            <p className="mt-2 text-sm leading-6 text-[#66758b]">
                                {description}
                            </p>
                        </div>

                        {children}
                    </section>

                    <div className="mt-6 flex flex-col items-center justify-between gap-2 text-center text-xs text-[#7b8798] sm:flex-row sm:text-left">
                        <span>© {new Date().getFullYear()} IBCO Solutions</span>
                        <Link
                            href={home()}
                            className="font-bold text-[#1749b5] transition-colors hover:text-[#2563eb]"
                        >
                            Kembali ke laman utama
                        </Link>
                    </div>
                </div>
            </main>
        </div>
    );
}
