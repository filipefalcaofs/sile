import { Head, Link } from '@inertiajs/react';

export default function Home() {
    return (
        <>
            <Head title="SILE — Sistema de Licenciamento Eletrônico" />
            <main className="flex min-h-screen flex-col items-center justify-center gap-6 bg-white px-4 text-center dark:bg-neutral-950">
                <h1 className="text-3xl font-semibold tracking-tight text-neutral-900 sm:text-4xl dark:text-neutral-100">
                    SILE — Sistema de Licenciamento Eletrônico
                </h1>
                <p className="max-w-xl text-base text-neutral-600 sm:text-lg dark:text-neutral-400">
                    Viabilidade locacional de atividades econômicas em Salvador
                </p>
                <div className="flex flex-col gap-4 sm:flex-row">
                    <Link
                        href="/login"
                        className="rounded-lg bg-blue-700 px-6 py-3 text-sm font-medium text-white transition hover:bg-blue-800 dark:bg-blue-600 dark:hover:bg-blue-500"
                    >
                        Entrar
                    </Link>
                    <Link
                        href="/register"
                        className="rounded-lg border border-neutral-300 px-6 py-3 text-sm font-medium text-neutral-900 transition hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-100 dark:hover:bg-neutral-800"
                    >
                        Criar conta
                    </Link>
                </div>
            </main>
        </>
    );
}
