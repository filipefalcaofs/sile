import { Head, Link } from '@inertiajs/react';
import type { ComponentType, SVGProps } from 'react';
import Logo from '@/components/app/logo';
import {
    ArrowRightIcon,
    CheckCircleIcon,
    EyeIcon,
    FileIcon,
    GroupIcon,
    MoonIcon,
    SunIcon,
} from '@/components/icons';
import { ThemeProvider, useTheme } from '@/contexts/theme-context';

const primaryCtaStyles =
    'inline-flex items-center justify-center gap-2 rounded-lg bg-brand-500 px-5 py-3 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600';

const outlineCtaStyles =
    'inline-flex items-center justify-center rounded-lg bg-white px-5 py-3 text-sm font-medium text-gray-700 ring-1 ring-inset ring-gray-300 transition hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-400 dark:ring-gray-700 dark:hover:bg-white/[0.03] dark:hover:text-gray-300';

const navLinks = [
    { label: 'Serviços', href: '#servicos' },
    { label: 'Como funciona', href: '#como-funciona' },
    { label: 'Base legal', href: '#base-legal' },
];

interface ServiceItem {
    icon: ComponentType<SVGProps<SVGSVGElement>>;
    title: string;
    description: string;
}

const services: ServiceItem[] = [
    {
        icon: EyeIcon,
        title: 'Consulta de viabilidade',
        description:
            'Verificação de que uma atividade econômica pode funcionar no endereço pretendido, aplicando as regras de uso e ocupação do solo da LOUOS.',
    },
    {
        icon: FileIcon,
        title: 'Solicitação de viabilidade',
        description:
            'Protocolo do pedido de viabilidade locacional e acompanhamento do andamento do processo diretamente pelo portal.',
    },
    {
        icon: CheckCircleIcon,
        title: 'Fluxo expresso',
        description:
            'Resposta automática para atividades de baixo risco, conforme a classificação de risco do Decreto nº 32.636/2020.',
    },
    {
        icon: GroupIcon,
        title: 'Procurações',
        description:
            'Vínculo de procurador para atuar em nome do interessado, com prazo de validade opcional e revogação a qualquer momento.',
    },
];

const steps = [
    {
        title: 'Crie sua conta',
        description: 'Cadastre-se no portal e acesse o ambiente do requerente.',
    },
    {
        title: 'Informe a atividade e o endereço',
        description: 'Indique o CNAE da atividade econômica e o endereço onde ela vai funcionar.',
    },
    {
        title: 'O sistema aplica as regras',
        description:
            'As regras da LOUOS e a classificação de risco municipal são aplicadas automaticamente, com fundamentação legal registrada.',
    },
    {
        title: 'Acompanhe o resultado',
        description: 'Acompanhe o andamento do processo e o resultado da análise pelo portal.',
    },
];

const legalBasis = [
    {
        title: 'LOUOS — Lei nº 9.148/2016',
        description: 'Lei de Ordenamento do Uso e da Ocupação do Solo do Município de Salvador.',
    },
    {
        title: 'Decreto nº 32.636/2020',
        description: 'Classificação de risco das atividades econômicas para fins de licenciamento.',
    },
    {
        title: 'CNAE-Subclasses 2.3',
        description: 'Classificação Nacional de Atividades Econômicas, mantida pelo IBGE/CONCLA.',
    },
];

function HeroGridPattern() {
    return (
        <svg
            className="absolute inset-0 -z-1 size-full text-gray-100 dark:text-white/[0.04]"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
        >
            <defs>
                <pattern id="sile-home-grid" width="52" height="52" patternUnits="userSpaceOnUse">
                    <path d="M52 0H0V52" fill="none" stroke="currentColor" />
                </pattern>
            </defs>
            <rect width="100%" height="100%" fill="url(#sile-home-grid)" />
        </svg>
    );
}

function ThemeToggleButton() {
    const { toggleTheme } = useTheme();

    return (
        <button
            type="button"
            onClick={toggleTheme}
            aria-label="Alternar tema"
            className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white"
        >
            <SunIcon className="hidden dark:block" />
            <MoonIcon className="dark:hidden" />
        </button>
    );
}

function SiteHeader() {
    return (
        <header className="sticky top-0 z-50 border-b border-gray-200/70 bg-white/80 backdrop-blur-md dark:border-gray-800/70 dark:bg-gray-900/80">
            <div className="mx-auto flex w-full max-w-(--breakpoint-xl) items-center justify-between gap-4 px-4 py-4 sm:px-6">
                <Link href="/" className="flex items-center">
                    <Logo
                        markClassName="size-8"
                        textClassName="text-lg font-semibold tracking-tight text-gray-800 dark:text-white/90"
                        subtitle="SEDUR — Salvador"
                    />
                </Link>

                <nav aria-label="Navegação principal" className="hidden md:block">
                    <ul className="flex items-center gap-8">
                        {navLinks.map((link) => (
                            <li key={link.href}>
                                <a
                                    href={link.href}
                                    className="text-sm font-medium text-gray-500 transition-colors hover:text-gray-800 dark:text-gray-400 dark:hover:text-white/90"
                                >
                                    {link.label}
                                </a>
                            </li>
                        ))}
                    </ul>
                </nav>

                <div className="flex items-center gap-3">
                    <ThemeToggleButton />
                    <Link
                        href="/portal/login"
                        className={`${outlineCtaStyles} hidden px-4 py-2.5 sm:inline-flex`}
                    >
                        Entrar
                    </Link>
                    <Link href="/portal/register" className={`${primaryCtaStyles} px-4 py-2.5`}>
                        Criar conta
                    </Link>
                </div>
            </div>
        </header>
    );
}

function HeroSection() {
    return (
        <section className="relative overflow-hidden">
            <HeroGridPattern />
            <div
                aria-hidden="true"
                className="absolute top-0 left-1/2 -z-1 h-105 w-180 max-w-full -translate-x-1/2 -translate-y-1/3 rounded-full bg-brand-500/10 blur-3xl"
            />
            <div
                aria-hidden="true"
                className="absolute inset-0 -z-1 bg-linear-to-b from-transparent via-transparent to-white dark:to-gray-900"
            />

            <div className="mx-auto flex w-full max-w-(--breakpoint-xl) flex-col items-center px-4 pt-16 pb-20 text-center sm:px-6 sm:pt-24 sm:pb-28">
                <span className="inline-flex items-center rounded-full border border-brand-200 bg-brand-50 px-3 py-1 text-theme-xs font-medium text-brand-500 dark:border-brand-500/30 dark:bg-brand-500/15 dark:text-brand-400">
                    Secretaria Municipal de Desenvolvimento Urbano — SEDUR
                </span>

                <h1 className="mt-6 max-w-3xl text-title-sm font-semibold tracking-tight text-gray-800 dark:text-white/90 sm:text-title-md lg:text-title-lg">
                    Licenciamento de atividades econômicas em Salvador
                </h1>

                <p className="mt-5 max-w-2xl text-base leading-7 text-gray-500 dark:text-gray-400 sm:text-lg sm:leading-8">
                    O SILE é o Sistema de Licenciamento Eletrônico da SEDUR. Ele responde à
                    viabilidade locacional de forma automatizada, aplicando as regras da LOUOS e a
                    classificação de risco municipal, com fundamentação legal registrada em cada
                    decisão.
                </p>

                <div className="mt-8 flex flex-col gap-4 sm:flex-row">
                    <Link href="/portal/register" className={`${primaryCtaStyles} px-6 py-3.5`}>
                        Criar conta
                        <ArrowRightIcon className="size-5" aria-hidden="true" />
                    </Link>
                    <Link href="/portal/login" className={`${outlineCtaStyles} px-6 py-3.5`}>
                        Entrar
                    </Link>
                </div>
            </div>
        </section>
    );
}

function ServicesSection() {
    return (
        <section id="servicos" className="scroll-mt-24">
            <div className="mx-auto w-full max-w-(--breakpoint-xl) px-4 py-16 sm:px-6 sm:py-20">
                <div className="max-w-2xl">
                    <h2 className="text-2xl font-semibold tracking-tight text-gray-800 dark:text-white/90 sm:text-title-sm">
                        Serviços do portal
                    </h2>
                    <p className="mt-3 text-base text-gray-500 dark:text-gray-400">
                        Recursos do SILE para requerentes, procuradores e responsáveis por
                        atividades econômicas no Município de Salvador.
                    </p>
                </div>

                <div className="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    {services.map((service) => (
                        <div
                            key={service.title}
                            className="rounded-2xl border border-gray-200 bg-white p-6 transition hover:border-brand-200 hover:shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03] dark:hover:border-brand-500/30"
                        >
                            <span className="flex h-12 w-12 items-center justify-center rounded-xl bg-brand-50 text-brand-500 dark:bg-brand-500/10 dark:text-brand-400">
                                <service.icon className="size-6" aria-hidden="true" />
                            </span>
                            <h3 className="mt-5 text-base font-semibold text-gray-800 dark:text-white/90">
                                {service.title}
                            </h3>
                            <p className="mt-2 text-theme-sm leading-6 text-gray-500 dark:text-gray-400">
                                {service.description}
                            </p>
                        </div>
                    ))}
                </div>
            </div>
        </section>
    );
}

function HowItWorksSection() {
    return (
        <section
            id="como-funciona"
            className="scroll-mt-24 border-y border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-white/[0.02]"
        >
            <div className="mx-auto w-full max-w-(--breakpoint-xl) px-4 py-16 sm:px-6 sm:py-20">
                <div className="max-w-2xl">
                    <h2 className="text-2xl font-semibold tracking-tight text-gray-800 dark:text-white/90 sm:text-title-sm">
                        Como funciona
                    </h2>
                    <p className="mt-3 text-base text-gray-500 dark:text-gray-400">
                        Da criação da conta ao resultado da análise, o processo acontece no portal.
                    </p>
                </div>

                <ol className="mt-10 grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
                    {steps.map((step, index) => (
                        <li
                            key={step.title}
                            className="relative lg:after:absolute lg:after:top-5.5 lg:after:right-0 lg:after:left-14 lg:after:h-px lg:after:bg-gray-200 lg:last:after:hidden dark:lg:after:bg-gray-800"
                        >
                            <span className="flex h-11 w-11 items-center justify-center rounded-full bg-brand-500 text-sm font-semibold text-white shadow-theme-xs">
                                {index + 1}
                            </span>
                            <h3 className="mt-4 text-base font-semibold text-gray-800 dark:text-white/90">
                                {step.title}
                            </h3>
                            <p className="mt-2 text-theme-sm leading-6 text-gray-500 dark:text-gray-400">
                                {step.description}
                            </p>
                        </li>
                    ))}
                </ol>
            </div>
        </section>
    );
}

function LegalBasisSection() {
    return (
        <section id="base-legal" className="scroll-mt-24">
            <div className="mx-auto w-full max-w-(--breakpoint-xl) px-4 py-16 sm:px-6 sm:py-20">
                <div className="max-w-2xl">
                    <h2 className="text-2xl font-semibold tracking-tight text-gray-800 dark:text-white/90 sm:text-title-sm">
                        Base legal
                    </h2>
                    <p className="mt-3 text-base text-gray-500 dark:text-gray-400">
                        As verificações do SILE são fundamentadas na legislação municipal e nas
                        classificações oficiais.
                    </p>
                </div>

                <div className="mt-10 grid gap-8 lg:grid-cols-3">
                    {legalBasis.map((item) => (
                        <div key={item.title} className="border-l-2 border-brand-500 pl-5">
                            <h3 className="text-base font-semibold text-gray-800 dark:text-white/90">
                                {item.title}
                            </h3>
                            <p className="mt-2 text-theme-sm leading-6 text-gray-500 dark:text-gray-400">
                                {item.description}
                            </p>
                        </div>
                    ))}
                </div>
            </div>
        </section>
    );
}

function FinalCtaSection() {
    return (
        <section>
            <div className="mx-auto w-full max-w-(--breakpoint-xl) px-4 pb-16 sm:px-6 sm:pb-20">
                <div className="relative overflow-hidden rounded-3xl bg-gray-900 px-6 py-14 text-center dark:border dark:border-gray-800 sm:px-12">
                    <div
                        aria-hidden="true"
                        className="absolute -top-24 left-1/2 h-64 w-160 max-w-full -translate-x-1/2 rounded-full bg-brand-500/20 blur-3xl"
                    />
                    <h2 className="relative text-2xl font-semibold tracking-tight text-white sm:text-title-sm">
                        Comece pelo portal
                    </h2>
                    <p className="relative mx-auto mt-3 max-w-xl text-base text-gray-400">
                        Crie sua conta para acessar os serviços do SILE ou entre com seu cadastro
                        existente.
                    </p>
                    <div className="relative mt-8 flex flex-col justify-center gap-4 sm:flex-row">
                        <Link
                            href="/portal/register"
                            className="inline-flex items-center justify-center gap-2 rounded-lg bg-white px-6 py-3.5 text-sm font-medium text-gray-800 shadow-theme-xs transition hover:bg-gray-100"
                        >
                            Criar conta
                            <ArrowRightIcon className="size-5" aria-hidden="true" />
                        </Link>
                        <Link
                            href="/portal/login"
                            className="inline-flex items-center justify-center rounded-lg px-6 py-3.5 text-sm font-medium text-gray-300 ring-1 ring-inset ring-gray-700 transition hover:bg-white/[0.05] hover:text-white"
                        >
                            Entrar
                        </Link>
                    </div>
                </div>
            </div>
        </section>
    );
}

function SiteFooter() {
    return (
        <footer className="border-t border-gray-200 dark:border-gray-800">
            <div className="mx-auto flex w-full max-w-(--breakpoint-xl) flex-col gap-10 px-4 py-12 sm:px-6 lg:flex-row lg:items-start lg:justify-between">
                <div className="max-w-md">
                    <Logo
                        markClassName="size-8"
                        textClassName="text-lg font-semibold tracking-tight text-gray-800 dark:text-white/90"
                    />
                    <p className="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">
                        Sistema de Licenciamento Eletrônico
                        <br />
                        SEDUR — Secretaria Municipal de Desenvolvimento Urbano
                        <br />
                        Prefeitura de Salvador
                    </p>
                </div>

                <div className="flex flex-col gap-10 sm:flex-row sm:gap-16">
                    <div>
                        <p className="text-sm font-semibold text-gray-800 dark:text-white/90">
                            Portal
                        </p>
                        <ul className="mt-3 space-y-2">
                            {navLinks.map((link) => (
                                <li key={link.href}>
                                    <a
                                        href={link.href}
                                        className="text-sm text-gray-500 transition-colors hover:text-gray-800 dark:text-gray-400 dark:hover:text-white/90"
                                    >
                                        {link.label}
                                    </a>
                                </li>
                            ))}
                        </ul>
                    </div>
                    <div>
                        <p className="text-sm font-semibold text-gray-800 dark:text-white/90">
                            Acesso
                        </p>
                        <ul className="mt-3 space-y-2">
                            <li>
                                <Link
                                    href="/portal/login"
                                    className="text-sm text-gray-500 transition-colors hover:text-gray-800 dark:text-gray-400 dark:hover:text-white/90"
                                >
                                    Entrar
                                </Link>
                            </li>
                            <li>
                                <Link
                                    href="/portal/register"
                                    className="text-sm text-gray-500 transition-colors hover:text-gray-800 dark:text-gray-400 dark:hover:text-white/90"
                                >
                                    Criar conta
                                </Link>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <div className="border-t border-gray-200 dark:border-gray-800">
                <p className="mx-auto w-full max-w-(--breakpoint-xl) px-4 py-6 text-sm text-gray-500 dark:text-gray-400 sm:px-6">
                    © {new Date().getFullYear()} SILE — Sistema de Licenciamento Eletrônico ·
                    SEDUR · Prefeitura de Salvador
                </p>
            </div>
        </footer>
    );
}

export default function Home() {
    return (
        <ThemeProvider>
            <Head title="SILE — Sistema de Licenciamento Eletrônico" />
            <div className="flex min-h-screen flex-col bg-white dark:bg-gray-900">
                <SiteHeader />
                <main className="flex-1">
                    <HeroSection />
                    <ServicesSection />
                    <HowItWorksSection />
                    <LegalBasisSection />
                    <FinalCtaSection />
                </main>
                <SiteFooter />
            </div>
        </ThemeProvider>
    );
}
