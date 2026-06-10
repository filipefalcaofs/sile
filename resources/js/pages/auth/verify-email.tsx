import { Form, Head, Link } from '@inertiajs/react';
import AuthLayout from '@/layouts/auth-layout';

interface VerifyEmailProps {
    status?: string;
}

export default function VerifyEmail({ status }: VerifyEmailProps) {
    return (
        <AuthLayout subtitle="Confirme seu e-mail">
            <Head title="Confirme seu e-mail" />
            <div className="flex flex-col gap-4">
                <p className="text-sm text-neutral-600 dark:text-neutral-400">
                    Enviamos um link de confirmação para o seu e-mail. Verifique sua caixa de
                    entrada.
                </p>

                {status === 'verification-link-sent' && (
                    <p className="rounded-lg bg-green-50 px-3 py-2 text-sm text-green-700 dark:bg-green-950 dark:text-green-400">
                        Um novo link foi enviado.
                    </p>
                )}

                <Form action="/email/verification-notification" method="post">
                    {({ processing }) => (
                        <button
                            type="submit"
                            disabled={processing}
                            className="w-full rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-blue-600 dark:hover:bg-blue-500"
                        >
                            {processing ? 'Enviando...' : 'Reenviar e-mail de confirmação'}
                        </button>
                    )}
                </Form>

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
        </AuthLayout>
    );
}
