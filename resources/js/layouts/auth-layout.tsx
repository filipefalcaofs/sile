import type { ReactNode } from 'react';

interface AuthLayoutProps {
    subtitle: string;
    children: ReactNode;
}

export default function AuthLayout({ subtitle, children }: AuthLayoutProps) {
    return (
        <main className="flex min-h-screen items-center justify-center bg-neutral-100 px-4 dark:bg-neutral-950">
            <div className="w-full max-w-md rounded-xl bg-white p-6 shadow-md sm:p-8 dark:bg-neutral-900">
                <header className="mb-6 text-center">
                    <h1 className="text-2xl font-semibold tracking-tight text-neutral-900 dark:text-neutral-100">
                        SILE
                    </h1>
                    <p className="mt-1 text-sm text-neutral-600 dark:text-neutral-400">{subtitle}</p>
                </header>
                {children}
            </div>
        </main>
    );
}
