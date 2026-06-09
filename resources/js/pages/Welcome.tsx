import { Head } from '@inertiajs/react';

interface WelcomeProps {
    appName: string;
    laravelVersion: string;
    phpVersion: string;
}

export default function Welcome({ appName, laravelVersion, phpVersion }: WelcomeProps) {
    return (
        <>
            <Head title="Bem-vindo" />
            <main className="flex min-h-screen flex-col items-center justify-center gap-4 bg-neutral-950 text-neutral-100">
                <h1 className="text-4xl font-semibold tracking-tight">{appName}</h1>
                <p className="text-neutral-400">
                    Sistema Integrado de Licenciamento Empresarial
                </p>
                <p className="text-sm text-neutral-500">
                    Laravel {laravelVersion} · PHP {phpVersion} · Inertia 3 · React 19
                </p>
            </main>
        </>
    );
}
