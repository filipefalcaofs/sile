import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { LogoMark } from '@/components/app/logo';
import Checkbox from '@/components/form/checkbox';
import { ArrowRightIcon, EyeCloseIcon, EyeIcon, LockIcon } from '@/components/icons';

/**
 * Login interno da retaguarda (Gestão SEDUR) — direção "Salvador Urban
 * Intelligence": header e footer estruturais, display title com
 * micro-indicadores à esquerda, card glass à direita, navy profundo,
 * labels monospace e radius arquitetônico (4/8px). Sem cadastro:
 * contas internas são administradas pela SEDUR (HU-012). Toda
 * tentativa é registrada (RN-002).
 *
 * Superfícies navy específicas desta tela (#051424/#0d1c2d) — assinatura
 * do console; promover a tokens se a direção se estender ao app.
 */

const indicators = [
    { label: 'Auditoria', value: '100% rastreável' },
    { label: 'Protocolo', value: 'Digital seguro' },
];

const monoLabelClasses = 'font-mono text-theme-xs font-medium tracking-[0.08em] text-gray-400 uppercase';

function ConsoleGridPattern() {
    return (
        <div
            aria-hidden="true"
            className="pointer-events-none absolute inset-0 [background-image:linear-gradient(to_right,rgba(255,255,255,0.05)_1px,transparent_1px),linear-gradient(to_bottom,rgba(255,255,255,0.05)_1px,transparent_1px)] [background-size:44px_44px] [mask-image:radial-gradient(ellipse_at_center,black_30%,transparent_85%)]"
        />
    );
}

function ConsoleHeader() {
    return (
        <header className="relative z-10 border-b border-white/[0.06]">
            <div className="mx-auto flex w-full max-w-(--breakpoint-xl) items-center justify-between gap-4 px-4 py-4 sm:px-6">
                <span className="flex items-center gap-3">
                    <span className="flex size-10 items-center justify-center rounded bg-brand-500/15 text-brand-400 ring-1 ring-brand-500/20">
                        <LogoMark className="size-6" />
                    </span>
                    <span className="flex flex-col leading-tight">
                        <span className="text-lg font-semibold tracking-tight text-white">SILE</span>
                        <span className="font-mono text-theme-xs text-gray-500">Gestão SEDUR</span>
                    </span>
                </span>
                <span className="inline-flex items-center gap-1.5 rounded-full border border-white/10 bg-white/[0.04] px-3.5 py-1.5 font-mono text-theme-xs font-medium text-gray-300">
                    <LockIcon className="size-3.5 text-warning-400" />
                    Ambiente interno
                </span>
            </div>
        </header>
    );
}

function ConsoleFooter() {
    return (
        <footer className="relative z-10 border-t border-white/[0.06]">
            <div className="mx-auto flex w-full max-w-(--breakpoint-xl) flex-col gap-2 px-4 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <p className="flex max-w-2xl items-start gap-2.5 text-theme-xs leading-5 text-gray-500">
                    <LockIcon className="mt-0.5 size-4 shrink-0 text-gray-600" />
                    Acesso exclusivo de servidores autorizados pela SEDUR. Todas as tentativas de
                    acesso são registradas para auditoria.
                </p>
                <p className="font-mono text-theme-xs tracking-wide text-gray-600 sm:text-right">
                    Prefeitura de Salvador — SEDUR
                </p>
            </div>
        </footer>
    );
}

export default function GestaoLogin({ status }: { status?: string }) {
    const [showPassword, setShowPassword] = useState(false);
    const [remember, setRemember] = useState(false);

    const inputClasses =
        'h-12 w-full rounded border border-white/10 bg-white/[0.04] px-4 text-sm text-white placeholder:text-gray-600 transition focus:border-brand-400 focus:bg-white/[0.06] focus:ring-3 focus:ring-brand-500/20 focus:outline-hidden';

    return (
        <div className="flex min-h-screen flex-col bg-[#051424]">
            <Head title="Gestão SEDUR — Acesso interno" />

            <ConsoleHeader />

            <main className="relative flex flex-1 overflow-hidden">
                <img
                    src="/images/salvador-hero.jpg"
                    alt=""
                    aria-hidden="true"
                    className="absolute inset-0 size-full object-cover object-[55%_35%] opacity-65"
                />
                <div
                    aria-hidden="true"
                    className="absolute inset-0 bg-linear-to-r from-[#051424]/90 via-[#051424]/45 to-[#051424]/80"
                />
                <div
                    aria-hidden="true"
                    className="absolute inset-x-0 bottom-0 h-1/2 bg-linear-to-t from-[#051424] to-transparent"
                />
                <ConsoleGridPattern />

                <div className="relative z-1 mx-auto grid w-full max-w-(--breakpoint-xl) items-center gap-12 px-4 py-12 sm:px-6 sm:py-16 lg:grid-cols-2 lg:gap-16 lg:py-20">
                    <div className="max-w-xl">
                        <p className="font-mono text-theme-xs font-medium tracking-[0.2em] text-brand-300 uppercase">
                            Console interno
                        </p>
                        <h1 className="mt-4 text-title-md font-bold tracking-tight text-white lg:text-title-lg">
                            Licenciamento eletrônico de Salvador
                        </h1>
                        <p className="mt-5 max-w-md text-base/7 text-gray-400">
                            Análise e administração da viabilidade locacional de atividades
                            econômicas, com fundamentação legal registrada em cada decisão.
                        </p>

                        <dl className="mt-10 grid max-w-md grid-cols-2 gap-6 border-t border-white/10 pt-8">
                            {indicators.map((indicator) => (
                                <div key={indicator.label}>
                                    <dt className={monoLabelClasses}>{indicator.label}</dt>
                                    <dd className="mt-1.5 text-sm font-semibold tracking-wide text-white uppercase">
                                        {indicator.value}
                                    </dd>
                                </div>
                            ))}
                        </dl>
                    </div>

                    <div className="w-full lg:justify-self-end">
                        <div className="mx-auto w-full max-w-105 rounded-lg bg-[#0d1c2d]/75 p-6 ring-1 ring-white/10 backdrop-blur-xl sm:p-8">
                            <h2 className="text-2xl font-semibold tracking-tight text-white">
                                Acesso restrito
                            </h2>
                            <p className="mt-1.5 text-theme-sm text-gray-400">
                                Console de análise e administração do licenciamento.
                            </p>

                            {status && (
                                <p className="mt-5 rounded border border-success-500/30 bg-success-500/10 px-4 py-3 text-theme-sm text-success-400">
                                    {status}
                                </p>
                            )}

                            <Form action="/gestao/login" method="post">
                                {({ errors, processing }) => (
                                    <div className="mt-7 space-y-5">
                                        {errors.email && (
                                            <p className="rounded border border-error-500/30 bg-error-500/10 px-4 py-3 text-theme-sm text-error-400">
                                                {errors.email}
                                            </p>
                                        )}

                                        <div>
                                            <label htmlFor="email" className={`${monoLabelClasses} mb-2 block`}>
                                                E-mail institucional
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
                                            <div className="mb-2 flex items-center justify-between gap-3">
                                                <label htmlFor="password" className={monoLabelClasses}>
                                                    Senha
                                                </label>
                                                <Link
                                                    href="/portal/forgot-password"
                                                    className="text-theme-xs font-medium text-brand-300 transition hover:text-brand-200"
                                                >
                                                    Esqueceu?
                                                </Link>
                                            </div>
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
                                                    className="absolute top-3.5 right-4 z-30 cursor-pointer text-gray-500 transition hover:text-gray-300"
                                                >
                                                    {showPassword ? (
                                                        <EyeIcon className="size-5" />
                                                    ) : (
                                                        <EyeCloseIcon className="size-5" />
                                                    )}
                                                </button>
                                            </div>
                                            {errors.password && (
                                                <p className="mt-1.5 text-theme-xs text-error-400">
                                                    {errors.password}
                                                </p>
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
                                            className="inline-flex h-12 w-full items-center justify-center gap-2 rounded bg-brand-500 text-sm font-semibold text-white transition hover:bg-brand-600 disabled:bg-brand-500/50"
                                        >
                                            {processing ? 'Verificando...' : 'Entrar no console'}
                                            {!processing && <ArrowRightIcon className="size-5" aria-hidden="true" />}
                                        </button>
                                    </div>
                                )}
                            </Form>
                        </div>
                    </div>
                </div>
            </main>

            <ConsoleFooter />
        </div>
    );
}
