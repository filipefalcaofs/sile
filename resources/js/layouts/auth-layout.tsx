import type { ReactNode } from 'react';
import { ThemeProvider } from '@/contexts/theme-context';

interface AuthLayoutProps {
    subtitle: string;
    children: ReactNode;
}

function BrandGridPattern() {
    return (
        <svg className="absolute inset-0 -z-1 size-full" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <defs>
                <pattern id="sile-auth-grid" width="52" height="52" patternUnits="userSpaceOnUse">
                    <path d="M52 0H0V52" fill="none" stroke="white" strokeOpacity="0.08" />
                </pattern>
            </defs>
            <rect width="100%" height="100%" fill="url(#sile-auth-grid)" />
        </svg>
    );
}

/**
 * Layout de autenticação do TailAdmin: formulário à esquerda e painel
 * decorativo brand à direita (apenas em lg). Mantém o contrato
 * original (subtitle + children).
 */
export default function AuthLayout({ subtitle, children }: AuthLayoutProps) {
    return (
        <ThemeProvider>
            <div className="relative z-1 bg-white p-6 dark:bg-gray-900 sm:p-0">
                <div className="relative flex min-h-screen w-full flex-col justify-center dark:bg-gray-900 sm:p-0 lg:flex-row">
                    <div className="flex w-full flex-1 flex-col lg:w-1/2">
                        <div className="mx-auto flex w-full max-w-md flex-1 flex-col justify-center py-10">
                            <div className="mb-5 sm:mb-8">
                                <h1 className="mb-2 text-2xl font-semibold tracking-tight text-gray-800 dark:text-white/90 sm:text-title-sm">
                                    SILE
                                </h1>
                                <p className="text-sm text-gray-500 dark:text-gray-400">{subtitle}</p>
                            </div>
                            {children}
                        </div>
                    </div>
                    <div className="relative hidden h-auto w-full items-center bg-brand-950 dark:bg-white/5 lg:grid lg:w-1/2">
                        <BrandGridPattern />
                        <div className="relative z-1 flex items-center justify-center">
                            <div className="flex max-w-xs flex-col items-center">
                                <span className="mb-4 block text-4xl font-semibold tracking-tight text-white">
                                    SILE
                                </span>
                                <p className="text-center text-gray-400 dark:text-white/60">
                                    Sistema de Licenciamento Eletrônico — SEDUR
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </ThemeProvider>
    );
}
