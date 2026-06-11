import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import { LogoMark } from '@/components/app/logo';
import Checkbox from '@/components/form/checkbox';
import { EyeCloseIcon, EyeIcon, LockIcon } from '@/components/icons';

/**
 * Login interno da retaguarda (Gestão SEDUR) — console administrativo:
 * split assimétrico com Salvador como painel visual à esquerda e
 * coluna de formulário escura à direita, deliberadamente distinto do
 * portal público. Sem cadastro: contas internas são administradas pela
 * própria SEDUR (HU-012). Toda tentativa é registrada (RN-002).
 */

const operationalAreas = ['Cadastros estruturantes', 'Parâmetros do sistema', 'Trilha de auditoria'];

function ConsoleGridPattern() {
    return (
        <div
            aria-hidden="true"
            className="pointer-events-none absolute inset-0 [background-image:linear-gradient(to_right,rgba(255,255,255,0.05)_1px,transparent_1px),linear-gradient(to_bottom,rgba(255,255,255,0.05)_1px,transparent_1px)] [background-size:44px_44px]"
        />
    );
}

function CityPanel() {
    return (
        <div className="relative hidden overflow-hidden lg:flex lg:w-[55%]">
            <img
                src="/images/salvador-hero.jpg"
                alt=""
                aria-hidden="true"
                className="absolute inset-0 size-full object-cover object-[72%_center]"
            />
            <div
                aria-hidden="true"
                className="absolute inset-0 bg-linear-to-r from-gray-950/60 via-gray-950/30 to-gray-950"
            />
            <div
                aria-hidden="true"
                className="absolute inset-x-0 bottom-0 h-2/3 bg-linear-to-t from-gray-950/95 via-gray-950/40 to-transparent"
            />
            <ConsoleGridPattern />

            <div className="relative z-1 flex w-full flex-col justify-between px-12 py-10 xl:px-16">
                <span className="flex items-center gap-3">
                    <span className="text-brand-400">
                        <LogoMark className="size-10" />
                    </span>
                    <span className="flex flex-col leading-tight">
                        <span className="text-xl font-semibold tracking-tight text-white">SILE</span>
                        <span className="text-theme-xs text-white/50">Gestão SEDUR</span>
                    </span>
                </span>

                <div className="max-w-lg">
                    <p className="text-theme-xs font-medium tracking-[0.2em] text-brand-300 uppercase">
                        Console interno
                    </p>
                    <h2 className="mt-3 text-title-sm font-semibold tracking-tight text-white">
                        Licenciamento eletrônico de Salvador
                    </h2>
                    <p className="mt-3 text-sm/6 text-white/60">
                        Análise e administração da viabilidade locacional de atividades econômicas,
                        com fundamentação legal registrada em cada decisão.
                    </p>
                    <ul className="mt-8 flex flex-wrap gap-x-6 gap-y-2 border-t border-white/10 pt-6">
                        {operationalAreas.map((area) => (
                            <li
                                key={area}
                                className="font-mono text-theme-xs tracking-wide text-white/40 uppercase"
                            >
                                {area}
                            </li>
                        ))}
                    </ul>
                </div>
            </div>
        </div>
    );
}

export default function GestaoLogin({ status }: { status?: string }) {
    const [showPassword, setShowPassword] = useState(false);
    const [remember, setRemember] = useState(false);

    const labelClasses = 'mb-2 block text-theme-xs font-medium tracking-[0.14em] text-gray-400 uppercase';

    const inputClasses =
        'h-12 w-full rounded-lg border border-white/10 bg-white/[0.04] px-4 text-sm text-white placeholder:text-gray-600 transition focus:border-brand-500 focus:bg-white/[0.06] focus:ring-3 focus:ring-brand-500/20 focus:outline-hidden';

    return (
        <div className="flex min-h-screen bg-gray-950">
            <Head title="Gestão SEDUR — Acesso interno" />

            <CityPanel />

            <div className="relative flex w-full flex-col overflow-hidden lg:w-[45%]">
                <div
                    aria-hidden="true"
                    className="pointer-events-none absolute -top-32 left-1/2 h-72 w-[34rem] -translate-x-1/2 rounded-full bg-brand-500/10 blur-3xl"
                />
                <ConsoleGridPattern />

                <div className="relative z-1 flex flex-1 flex-col px-6 py-8 sm:px-12 lg:px-16 xl:px-20">
                    <div className="flex items-center justify-between gap-4">
                        <span className="flex items-center gap-3 lg:invisible">
                            <span className="text-brand-400">
                                <LogoMark className="size-9" />
                            </span>
                            <span className="flex flex-col leading-tight">
                                <span className="text-lg font-semibold tracking-tight text-white">SILE</span>
                                <span className="text-theme-xs text-gray-500">Gestão SEDUR</span>
                            </span>
                        </span>
                        <span className="inline-flex items-center gap-1.5 rounded-full border border-warning-500/25 bg-warning-500/10 px-3 py-1 text-theme-xs font-medium text-warning-400">
                            <LockIcon className="size-3.5" />
                            Ambiente interno
                        </span>
                    </div>

                    <div className="mx-auto flex w-full max-w-100 flex-1 flex-col justify-center py-10">
                        <h1 className="text-2xl font-semibold tracking-tight text-white sm:text-title-sm">
                            Acesso restrito
                        </h1>
                        <p className="mt-2 text-theme-sm text-gray-400">
                            Console de análise e administração do licenciamento.
                        </p>

                        {status && (
                            <p className="mt-6 rounded-lg border border-success-500/30 bg-success-500/10 px-4 py-3 text-theme-sm text-success-400">
                                {status}
                            </p>
                        )}

                        <Form action="/gestao/login" method="post">
                            {({ errors, processing }) => (
                                <div className="mt-8 space-y-6">
                                    {errors.email && (
                                        <p className="rounded-lg border border-error-500/30 bg-error-500/10 px-4 py-3 text-theme-sm text-error-400">
                                            {errors.email}
                                        </p>
                                    )}

                                    <div>
                                        <label htmlFor="email" className={labelClasses}>
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
                                        <label htmlFor="password" className={labelClasses}>
                                            Senha
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
                                        className="inline-flex h-12 w-full items-center justify-center rounded-lg bg-brand-500 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600 disabled:bg-brand-500/50"
                                    >
                                        {processing ? 'Verificando...' : 'Entrar no console'}
                                    </button>
                                </div>
                            )}
                        </Form>
                    </div>

                    <p className="mx-auto w-full max-w-100 border-t border-white/[0.06] pt-5 text-theme-xs leading-5 text-gray-500">
                        Acesso exclusivo de servidores autorizados pela SEDUR. Todas as tentativas
                        de acesso são registradas para auditoria.
                    </p>
                </div>
            </div>
        </div>
    );
}
