import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import { LogoMark } from '@/components/app/logo';
import Checkbox from '@/components/form/checkbox';
import { EyeCloseIcon, EyeIcon, LockIcon } from '@/components/icons';

/**
 * Login interno da retaguarda (Gestão SEDUR) — visual de console
 * administrativo: escuro, denso e funcional, deliberadamente distinto
 * do portal público. Sem cadastro: contas internas são administradas
 * pela própria SEDUR (HU-012). Toda tentativa é registrada (RN-002).
 */
export default function GestaoLogin({ status }: { status?: string }) {
    const [showPassword, setShowPassword] = useState(false);
    const [remember, setRemember] = useState(false);

    const inputClasses =
        'h-11 w-full rounded-lg border border-white/15 bg-gray-950/50 px-4 py-2.5 text-sm text-white placeholder:text-gray-500 focus:border-brand-500 focus:ring-3 focus:ring-brand-500/20 focus:outline-hidden';

    return (
        <div className="relative flex min-h-screen flex-col items-center justify-center overflow-hidden bg-gray-950 px-4 py-10">
            <Head title="Gestão SEDUR — Acesso interno" />

            {/* Cidade como atmosfera: foto muito escurecida, console em primeiro plano */}
            <img
                src="/images/salvador-hero.jpg"
                alt=""
                aria-hidden="true"
                className="pointer-events-none absolute inset-0 size-full object-cover object-[60%_40%] opacity-40"
            />
            <div
                aria-hidden="true"
                className="pointer-events-none absolute inset-0 bg-linear-to-b from-gray-950/80 via-gray-950/70 to-gray-950"
            />

            {/* Grade técnica de fundo, sutil */}
            <div
                aria-hidden="true"
                className="pointer-events-none absolute inset-0 [background-image:linear-gradient(to_right,rgba(255,255,255,0.04)_1px,transparent_1px),linear-gradient(to_bottom,rgba(255,255,255,0.04)_1px,transparent_1px)] [background-size:36px_36px]"
            />
            <div
                aria-hidden="true"
                className="pointer-events-none absolute top-0 left-1/2 h-64 w-[36rem] -translate-x-1/2 rounded-full bg-brand-500/10 blur-3xl"
            />

            <div className="relative w-full max-w-105">
                <div className="mb-6 flex items-center justify-between">
                    <span className="flex items-center gap-3">
                        <span className="text-brand-400">
                            <LogoMark className="size-9" />
                        </span>
                        <span className="flex flex-col leading-tight">
                            <span className="text-lg font-semibold tracking-tight text-white">SILE</span>
                            <span className="text-theme-xs text-gray-500">Gestão SEDUR</span>
                        </span>
                    </span>
                    <span className="inline-flex items-center gap-1.5 rounded-full border border-warning-500/30 bg-warning-500/10 px-3 py-1 text-theme-xs font-medium text-warning-500">
                        <LockIcon className="size-3.5" />
                        Ambiente interno
                    </span>
                </div>

                <div className="rounded-2xl bg-gray-900/70 p-6 shadow-theme-lg ring-1 ring-white/10 backdrop-blur-md sm:p-8">
                    <h1 className="text-xl font-semibold tracking-tight text-white">Acesso restrito</h1>
                    <p className="mt-1 text-theme-sm text-gray-400">
                        Console de análise e administração do licenciamento.
                    </p>

                    {status && (
                        <p className="mt-4 rounded-lg border border-success-500/30 bg-success-500/10 px-4 py-3 text-theme-sm text-success-400">
                            {status}
                        </p>
                    )}

                    <Form action="/gestao/login" method="post">
                        {({ errors, processing }) => (
                            <div className="mt-6 space-y-5">
                                {errors.email && (
                                    <p className="rounded-lg border border-error-500/30 bg-error-500/10 px-4 py-3 text-theme-sm text-error-400">
                                        {errors.email}
                                    </p>
                                )}

                                <div>
                                    <label htmlFor="email" className="mb-1.5 block text-sm font-medium text-gray-300">                                        E-mail institucional
                                    </label>
                                    <input
                                        id="email"
                                        type="email"
                                        name="email"
                                        autoComplete="email"
                                        autoFocus
                                        required
                                        placeholder="nome@salvador.ba.gov.br"
                                        className={inputClasses}
                                    />
                                </div>

                                <div>
                                    <label htmlFor="password" className="mb-1.5 block text-sm font-medium text-gray-300">                                        Senha
                                    </label>
                                    <div className="relative">
                                        <input
                                            id="password"
                                            type={showPassword ? 'text' : 'password'}
                                            name="password"
                                            autoComplete="current-password"
                                            required
                                            className={inputClasses}
                                        />
                                        <button
                                            type="button"
                                            onClick={() => setShowPassword((current) => !current)}
                                            aria-label={showPassword ? 'Ocultar senha' : 'Mostrar senha'}
                                            className="absolute top-3 right-4 z-30 cursor-pointer text-gray-500 hover:text-gray-300"
                                        >
                                            {showPassword ? (
                                                <EyeIcon className="size-5" />
                                            ) : (
                                                <EyeCloseIcon className="size-5" />
                                            )}
                                        </button>
                                    </div>
                                    {errors.password && (
                                        <p className="mt-1.5 text-theme-xs text-error-400">{errors.password}</p>
                                    )}
                                </div>

                                <div className="dark">
                                    <Checkbox
                                        id="remember"
                                        name="remember"
                                        label="Manter conectado"
                                        checked={remember}
                                        onChange={setRemember}
                                    />
                                </div>

                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="inline-flex h-11 w-full items-center justify-center rounded-lg bg-brand-500 text-sm font-medium text-white transition hover:bg-brand-600 disabled:bg-brand-500/50"
                                >
                                    {processing ? 'Verificando...' : 'Entrar no console'}
                                </button>
                            </div>
                        )}
                    </Form>
                </div>

                <p className="mt-5 text-center text-theme-xs leading-5 text-gray-500">
                    Acesso exclusivo de servidores autorizados pela SEDUR.
                    <br />
                    Todas as tentativas de acesso são registradas para auditoria.
                </p>
            </div>
        </div>
    );
}
