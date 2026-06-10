import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import Checkbox from '@/components/form/checkbox';
import Button from '@/components/ui/button';
import { ThemeProvider } from '@/contexts/theme-context';

interface TermoLgpdProps {
    term: {
        id: number;
        version: number;
        title: string;
        content: string;
    };
}

export default function TermoLgpd({ term }: TermoLgpdProps) {
    const [accepted, setAccepted] = useState(false);

    return (
        <ThemeProvider>
            <main className="flex min-h-screen items-center justify-center bg-gray-50 px-4 py-8 dark:bg-gray-900">
                <Head title="Termo de Consentimento LGPD" />
                <div className="w-full max-w-2xl rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/[0.03] sm:p-8">
                    <header className="mb-6">
                        <h1 className="text-xl font-semibold text-gray-800 dark:text-white/90">
                            {term.title}
                        </h1>
                        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Versão {term.version} — leia com atenção antes de continuar.
                        </p>
                    </header>

                    <div className="custom-scrollbar max-h-96 overflow-y-auto rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm whitespace-pre-line text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                        {term.content}
                    </div>

                    <Form action="/portal/termo-lgpd" method="post" className="mt-6">
                        {({ errors, processing }) => (
                            <div className="flex flex-col gap-5">
                                <Checkbox
                                    id="accepted"
                                    name="accepted"
                                    label="Li e concordo com o tratamento dos meus dados pessoais conforme descrito"
                                    checked={accepted}
                                    onChange={setAccepted}
                                />
                                {errors.accepted && (
                                    <p className="text-sm text-error-500">{errors.accepted}</p>
                                )}

                                <Button type="submit" size="sm" className="w-full" disabled={processing}>
                                    {processing ? 'Registrando aceite...' : 'Aceitar e continuar'}
                                </Button>

                                <p className="text-center text-sm">
                                    <Link
                                        href="/logout"
                                        method="post"
                                        as="button"
                                        className="text-sm font-medium text-gray-700 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-300"
                                    >
                                        Sair
                                    </Link>
                                </p>
                            </div>
                        )}
                    </Form>
                </div>
            </main>
        </ThemeProvider>
    );
}
