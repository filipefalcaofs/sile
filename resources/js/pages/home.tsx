import { Head, Link } from '@inertiajs/react';
import { MoonIcon, SunIcon } from '@/components/icons';
import { ThemeProvider, useTheme } from '@/contexts/theme-context';

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
            className="relative flex h-11 w-11 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white"
        >
            <SunIcon className="hidden dark:block" />
            <MoonIcon className="dark:hidden" />
        </button>
    );
}

export default function Home() {
    return (
        <ThemeProvider>
            <Head title="SILE — Sistema de Licenciamento Eletrônico" />
            <div className="relative z-1 flex min-h-screen flex-col bg-white dark:bg-gray-900">
                <HeroGridPattern />

                <header className="relative z-1">
                    <div className="mx-auto flex w-full max-w-(--breakpoint-xl) items-center justify-between px-4 py-5 sm:px-6">
                        <span className="text-lg font-semibold tracking-tight text-gray-800 dark:text-white/90">
                            SILE
                        </span>
                        <ThemeToggleButton />
                    </div>
                </header>

                <main className="relative z-1 flex flex-1 flex-col items-center justify-center gap-6 px-4 py-16 text-center">
                    <span className="inline-flex items-center rounded-full border border-brand-200 bg-brand-50 px-3 py-1 text-theme-xs font-medium text-brand-500 dark:border-brand-500/30 dark:bg-brand-500/15 dark:text-brand-400">
                        SEDUR — Salvador
                    </span>

                    <h1 className="max-w-3xl text-3xl font-semibold tracking-tight text-gray-800 dark:text-white/90 sm:text-title-lg">
                        SILE — Sistema de Licenciamento Eletrônico
                    </h1>

                    <p className="max-w-xl text-base text-gray-500 dark:text-gray-400 sm:text-lg">
                        Viabilidade locacional de atividades econômicas em Salvador
                    </p>

                    <div className="mt-2 flex flex-col gap-4 sm:flex-row">
                        <Link
                            href="/login"
                            className="inline-flex items-center justify-center rounded-lg bg-brand-500 px-6 py-3.5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600"
                        >
                            Entrar
                        </Link>
                        <Link
                            href="/register"
                            className="inline-flex items-center justify-center rounded-lg bg-white px-6 py-3.5 text-sm font-medium text-gray-700 ring-1 ring-inset ring-gray-300 transition hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-400 dark:ring-gray-700 dark:hover:bg-white/[0.03] dark:hover:text-gray-300"
                        >
                            Criar conta
                        </Link>
                    </div>
                </main>

                <footer className="relative z-1 py-6">
                    <p className="text-center text-sm text-gray-500 dark:text-gray-400">
                        Sistema de Licenciamento Eletrônico — SEDUR
                    </p>
                </footer>
            </div>
        </ThemeProvider>
    );
}
