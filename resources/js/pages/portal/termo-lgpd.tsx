import { Form, Head, Link } from '@inertiajs/react';

interface TermoLgpdProps {
    term: {
        id: number;
        version: number;
        title: string;
        content: string;
    };
}

export default function TermoLgpd({ term }: TermoLgpdProps) {
    return (
        <main className="flex min-h-screen items-center justify-center bg-neutral-100 px-4 py-8 dark:bg-neutral-950">
            <Head title="Termo de Consentimento LGPD" />
            <div className="w-full max-w-2xl rounded-xl bg-white p-6 shadow-md sm:p-8 dark:bg-neutral-900">
                <header className="mb-6">
                    <h1 className="text-xl font-semibold tracking-tight text-neutral-900 dark:text-neutral-100">
                        {term.title}
                    </h1>
                    <p className="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                        Versão {term.version} — leia com atenção antes de continuar.
                    </p>
                </header>

                <div className="max-h-96 overflow-y-auto rounded-lg border border-neutral-200 bg-neutral-50 p-4 text-sm whitespace-pre-line text-neutral-700 dark:border-neutral-800 dark:bg-neutral-950 dark:text-neutral-300">
                    {term.content}
                </div>

                <Form action="/portal/termo-lgpd" method="post" className="mt-6">
                    {({ errors, processing }) => (
                        <div className="flex flex-col gap-4">
                            <label className="flex items-start gap-3 text-sm text-neutral-700 dark:text-neutral-300">
                                <input
                                    type="checkbox"
                                    name="accepted"
                                    value="1"
                                    className="mt-0.5 size-4 rounded border-neutral-300 text-blue-700 focus:ring-2 focus:ring-blue-600/20 dark:border-neutral-700"
                                />
                                <span>
                                    Li e concordo com o tratamento dos meus dados pessoais conforme
                                    descrito
                                </span>
                            </label>
                            {errors.accepted && (
                                <p className="text-sm text-red-600 dark:text-red-400">
                                    {errors.accepted}
                                </p>
                            )}

                            <button
                                type="submit"
                                disabled={processing}
                                className="rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-blue-600 dark:hover:bg-blue-500"
                            >
                                {processing ? 'Registrando aceite...' : 'Aceitar e continuar'}
                            </button>

                            <p className="text-center text-sm">
                                <Link
                                    href="/logout"
                                    method="post"
                                    as="button"
                                    className="font-medium text-neutral-600 hover:underline dark:text-neutral-400"
                                >
                                    Sair
                                </Link>
                            </p>
                        </div>
                    )}
                </Form>
            </div>
        </main>
    );
}
