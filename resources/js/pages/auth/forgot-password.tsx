import { Form, Head, Link } from '@inertiajs/react';
import AuthLayout from '@/layouts/auth-layout';

interface ForgotPasswordProps {
    status?: string;
}

const inputClasses =
    'w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-neutral-900 placeholder:text-neutral-400 focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-600/20 dark:border-neutral-700 dark:bg-neutral-950 dark:text-neutral-100 dark:placeholder:text-neutral-500';

const labelClasses = 'mb-1 block text-sm font-medium text-neutral-700 dark:text-neutral-300';

const errorClasses = 'mt-1 text-sm text-red-600 dark:text-red-400';

export default function ForgotPassword({ status }: ForgotPasswordProps) {
    return (
        <AuthLayout subtitle="Recupere o acesso à sua conta">
            <Head title="Esqueci minha senha" />
            <p className="mb-4 text-sm text-neutral-600 dark:text-neutral-400">
                Informe seu e-mail e enviaremos um link para redefinir sua senha.
            </p>
            {status && (
                <p className="mb-4 rounded-lg bg-green-100 p-3 text-sm text-green-800 dark:bg-green-900 dark:text-green-100">
                    {status}
                </p>
            )}
            <Form action="/forgot-password" method="post">
                {({ errors, processing }) => (
                    <div className="flex flex-col gap-4">
                        <div>
                            <label htmlFor="email" className={labelClasses}>
                                E-mail
                            </label>
                            <input
                                id="email"
                                type="email"
                                name="email"
                                autoComplete="email"
                                autoFocus
                                required
                                className={inputClasses}
                            />
                            {errors.email && <p className={errorClasses}>{errors.email}</p>}
                        </div>

                        <button
                            type="submit"
                            disabled={processing}
                            className="rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-blue-600 dark:hover:bg-blue-500"
                        >
                            {processing ? 'Enviando...' : 'Enviar link de recuperação'}
                        </button>

                        <p className="text-center text-sm text-neutral-600 dark:text-neutral-400">
                            <Link
                                href="/login"
                                className="font-medium text-blue-700 hover:underline dark:text-blue-400"
                            >
                                Voltar ao login
                            </Link>
                        </p>
                    </div>
                )}
            </Form>
        </AuthLayout>
    );
}
