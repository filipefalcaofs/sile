import { Form, Head, Link } from '@inertiajs/react';
import AuthLayout from '@/layouts/auth-layout';

interface LoginProps {
    canResetPassword: boolean;
    status?: string;
}

const inputClasses =
    'w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-neutral-900 placeholder:text-neutral-400 focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-600/20 dark:border-neutral-700 dark:bg-neutral-950 dark:text-neutral-100 dark:placeholder:text-neutral-500';

const labelClasses = 'mb-1 block text-sm font-medium text-neutral-700 dark:text-neutral-300';

const errorClasses = 'mt-1 text-sm text-red-600 dark:text-red-400';

export default function Login({ canResetPassword, status }: LoginProps) {
    return (
        <AuthLayout subtitle="Acesse sua conta para acompanhar seus processos">
            <Head title="Entrar" />
            {status && (
                <p className="mb-4 rounded-lg bg-green-100 p-3 text-sm text-green-800 dark:bg-green-900 dark:text-green-100">
                    {status}
                </p>
            )}
            <Form action="/login" method="post">
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

                        <div>
                            <label htmlFor="password" className={labelClasses}>
                                Senha
                            </label>
                            <input
                                id="password"
                                type="password"
                                name="password"
                                autoComplete="current-password"
                                required
                                className={inputClasses}
                            />
                            {errors.password && <p className={errorClasses}>{errors.password}</p>}
                        </div>

                        <div className="flex items-center justify-between gap-4">
                            <label className="flex items-center gap-2 text-sm text-neutral-700 dark:text-neutral-300">
                                <input
                                    type="checkbox"
                                    name="remember"
                                    className="h-4 w-4 rounded border-neutral-300 text-blue-700 focus:ring-blue-600/20 dark:border-neutral-700"
                                />
                                Manter conectado
                            </label>
                            {canResetPassword && (
                                <Link
                                    href="/forgot-password"
                                    className="text-sm font-medium text-blue-700 hover:underline dark:text-blue-400"
                                >
                                    Esqueci minha senha
                                </Link>
                            )}
                        </div>

                        <button
                            type="submit"
                            disabled={processing}
                            className="rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-blue-600 dark:hover:bg-blue-500"
                        >
                            {processing ? 'Entrando...' : 'Entrar'}
                        </button>

                        <p className="text-center text-sm text-neutral-600 dark:text-neutral-400">
                            Ainda não tenho conta —{' '}
                            <Link
                                href="/register"
                                className="font-medium text-blue-700 hover:underline dark:text-blue-400"
                            >
                                Criar conta
                            </Link>
                        </p>
                    </div>
                )}
            </Form>
        </AuthLayout>
    );
}
