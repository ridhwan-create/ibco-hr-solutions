import { Form, Head } from '@inertiajs/react';
import { LockKeyhole, LogIn, Mail, ShieldCheck } from 'lucide-react';
import InputError from '@/components/input-error';
import PasskeyVerify from '@/components/passkey-verify';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

type Props = {
    status?: string;
    canResetPassword: boolean;
};

export default function Login({ status, canResetPassword }: Props) {
    return (
        <>
            <Head title="Log Masuk">
                <meta
                    name="description"
                    content="Log masuk ke portal pengurusan sumber manusia IBCO Solutions."
                />
            </Head>

            <PasskeyVerify
                label="Log masuk dengan passkey"
                loadingLabel="Mengesahkan identiti..."
                separator="Atau teruskan dengan e-mel"
            />

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="flex flex-col gap-5"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-5">
                            <div className="grid gap-2.5">
                                <Label
                                    htmlFor="email"
                                    className="text-sm font-bold text-[#22334c]"
                                >
                                    Alamat e-mel
                                </Label>
                                <div className="relative">
                                    <Mail
                                        className="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-[#8794a8]"
                                        aria-hidden="true"
                                    />
                                    <Input
                                        id="email"
                                        type="email"
                                        name="email"
                                        required
                                        autoFocus
                                        tabIndex={1}
                                        autoComplete="email"
                                        placeholder="nama@ibcogroup.org"
                                        className="h-12 rounded-xl border-[#dce5f0] bg-white pl-10 text-[#0f1d35] placeholder:text-[#9aa6b6] focus-visible:border-[#2563eb] focus-visible:ring-[#2563eb]/15"
                                    />
                                </div>
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2.5">
                                <div className="flex items-center">
                                    <Label
                                        htmlFor="password"
                                        className="text-sm font-bold text-[#22334c]"
                                    >
                                        Kata laluan
                                    </Label>
                                    {canResetPassword && (
                                        <TextLink
                                            href={request()}
                                            className="ml-auto text-xs font-bold text-[#2563eb] no-underline hover:text-[#1749b5]"
                                            tabIndex={5}
                                        >
                                            Lupa kata laluan?
                                        </TextLink>
                                    )}
                                </div>
                                <div className="relative">
                                    <LockKeyhole
                                        className="pointer-events-none absolute top-1/2 left-3.5 z-10 size-4 -translate-y-1/2 text-[#8794a8]"
                                        aria-hidden="true"
                                    />
                                    <PasswordInput
                                        id="password"
                                        name="password"
                                        required
                                        tabIndex={2}
                                        autoComplete="current-password"
                                        placeholder="Masukkan kata laluan"
                                        className="h-12 rounded-xl border-[#dce5f0] bg-white pr-11 pl-10 text-[#0f1d35] placeholder:text-[#9aa6b6] focus-visible:border-[#2563eb] focus-visible:ring-[#2563eb]/15"
                                    />
                                </div>
                                <InputError message={errors.password} />
                            </div>

                            <div className="flex items-center space-x-3 pt-0.5">
                                <Checkbox
                                    id="remember"
                                    name="remember"
                                    tabIndex={3}
                                    className="border-[#cbd6e4] data-[state=checked]:border-[#2563eb] data-[state=checked]:bg-[#2563eb]"
                                />
                                <Label
                                    htmlFor="remember"
                                    className="cursor-pointer text-sm font-medium text-[#5f6f84]"
                                >
                                    Ingat saya
                                </Label>
                            </div>

                            <Button
                                type="submit"
                                className="mt-2 h-12 w-full rounded-xl bg-[#2563eb] text-sm font-extrabold text-white shadow-[0_12px_28px_rgba(37,99,235,0.22)] transition hover:-translate-y-0.5 hover:bg-[#1749b5]"
                                tabIndex={4}
                                disabled={processing}
                                data-test="login-button"
                            >
                                {processing ? (
                                    <Spinner />
                                ) : (
                                    <LogIn className="size-4" />
                                )}
                                {processing
                                    ? 'Sedang log masuk...'
                                    : 'Log Masuk'}
                            </Button>
                        </div>

                        <div className="border-t border-[#e6ecf3] pt-5 text-center text-sm text-[#66758b]">
                            Belum mempunyai akaun?{' '}
                            <TextLink
                                href={register()}
                                tabIndex={5}
                                className="font-extrabold text-[#2563eb] no-underline hover:text-[#1749b5]"
                            >
                                Daftar akaun
                            </TextLink>
                        </div>
                    </>
                )}
            </Form>

            {status && (
                <div className="mt-5 flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
                    <ShieldCheck className="mt-0.5 size-4 shrink-0" />
                    <span>{status}</span>
                </div>
            )}
        </>
    );
}

Login.layout = {
    title: 'Selamat kembali',
    description:
        'Masukkan e-mel dan kata laluan anda untuk mengakses IBCO HR Solutions.',
};
