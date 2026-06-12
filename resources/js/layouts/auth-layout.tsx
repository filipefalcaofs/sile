import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import Logo, { LogoMark } from '@/components/app/logo';
import { CheckCircleIcon } from '@/components/icons';
import { ThemeProvider } from '@/contexts/theme-context';

interface AuthLayoutProps {
    title: string;
    subtitle: string;
    icon?: ReactNode;
    children: ReactNode;
}

const institutionalHighlights = [
    'Consulta de viabilidade locacional pela LOUOS (Lei nº 9.148/2016)',
    'Classificação de risco conforme o Decreto nº 32.636/2020',
    'Acompanhamento do processo com transparência e auditoria',
];

function BrandGridPattern() {
    return (
        <svg
            className="absolute inset-0 size-full [mask-image:radial-gradient(ellipse_at_center,black_35%,transparent_80%)]"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
        >
            <defs>
                <pattern id="sile-auth-grid" width="52" height="52" patternUnits="userSpaceOnUse">
                    <path d="M52 0H0V52" fill="none" stroke="white" strokeOpacity="0.07" />
                </pattern>
            </defs>
            <rect width="100%" height="100%" fill="url(#sile-auth-grid)" />
        </svg>
    );
}

function BrandPanel() {
    return (
        <div className="relative hidden w-full overflow-hidden bg-brand-950 lg:flex lg:w-1/2">
            <img
                src="/images/salvador-hero.jpg"
                alt=""
                aria-hidden="true"
                className="absolute inset-0 size-full object-cover object-[68%_35%]"
            />
            <div
                aria-hidden="true"
                className="absolute inset-0 bg-linear-to-t from-brand-950/95 via-brand-950/80 to-brand-950/55"
            />
            <BrandGridPattern />
            <div className="relative z-1 flex w-full flex-col px-12 py-10 xl:px-20">
                <div className="flex flex-1 items-center">
                    <div className="mx-auto w-full max-w-md">
                        <span className="flex items-center gap-4">
                            <span className="flex size-16 items-center justify-center rounded-2xl bg-white/10 text-white ring-1 ring-white/15">
                                <LogoMark className="size-10" />
                            </span>
                            <span className="block text-4xl font-semibold tracking-tight text-white">SIMPLIFICA</span>
                        </span>
                        <p className="mt-4 text-lg font-medium text-white/90">
                            Sistema de Licenciamento Eletrônico
                        </p>
                        <p className="mt-2 text-sm/6 text-white/60">
                            Viabilidade locacional e licenciamento de atividades econômicas no
                            município de Salvador.
                        </p>
                        <ul className="mt-10 space-y-5 border-t border-white/10 pt-10">
                            {institutionalHighlights.map((highlight) => (
                                <li key={highlight} className="flex items-start gap-3">
                                    <CheckCircleIcon className="mt-0.5 size-5 shrink-0 text-brand-300" />
                                    <span className="text-sm/6 text-white/80">{highlight}</span>
                                </li>
                            ))}
                        </ul>
                    </div>
                </div>
                <div className="mx-auto flex w-full max-w-md items-center gap-5">
                    <img
                        src="/images/logo_prefeitura.png"
                        alt="Prefeitura de Salvador"
                        className="h-10 w-auto opacity-90"
                    />
                    <span className="h-8 w-px bg-white/20" aria-hidden="true" />
                    <img
                        src="/images/01JW9M9BYJ76Y0M06Z1XHJDKH8.png"
                        alt="SEDUR — Secretaria de Desenvolvimento Urbano"
                        className="h-7 w-auto opacity-90"
                    />
                </div>
            </div>
        </div>
    );
}

/**
 * Layout de autenticação: coluna do formulário (card com título e
 * subtítulo por página, ícone opcional) e painel institucional brand
 * à direita em telas lg+, com bullets sobre o Simplifica.
 */
export default function AuthLayout({ title, subtitle, icon, children }: AuthLayoutProps) {
    return (
        <ThemeProvider>
            <div className="relative z-1 bg-gray-50 p-4 dark:bg-gray-900 sm:p-0">
                <div className="relative flex min-h-screen w-full flex-col justify-center lg:flex-row">
                    <div className="flex w-full flex-1 flex-col lg:w-1/2">
                        <div className="mx-auto w-full max-w-lg pt-6 sm:pt-8">
                            <Link
                                href="/"
                                aria-label="Ir para a página inicial do Simplifica"
                                className="inline-flex items-center"
                            >
                                <Logo
                                    markClassName="size-8"
                                    textClassName="text-xl font-bold tracking-tight text-gray-800 dark:text-white/90"
                                    subtitle="SEDUR"
                                />
                            </Link>
                        </div>
                        <div className="mx-auto flex w-full max-w-lg flex-1 flex-col justify-center py-6 sm:py-8">
                            <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-theme-sm sm:p-8 dark:border-gray-800 dark:bg-white/[0.03]">
                                <div className="mb-5 sm:mb-6">
                                    {icon && (
                                        <div className="mb-5 flex size-12 items-center justify-center rounded-xl border border-brand-100 bg-brand-50 text-brand-600 dark:border-brand-500/20 dark:bg-brand-500/10 dark:text-brand-400">
                                            {icon}
                                        </div>
                                    )}
                                    <h1 className="mb-2 text-2xl font-semibold tracking-tight text-gray-800 sm:text-title-sm dark:text-white/90">
                                        {title}
                                    </h1>
                                    <p className="text-sm text-gray-500 dark:text-gray-400">
                                        {subtitle}
                                    </p>
                                </div>
                                {children}
                            </div>
                        </div>
                    </div>
                    <BrandPanel />
                </div>
            </div>
        </ThemeProvider>
    );
}
