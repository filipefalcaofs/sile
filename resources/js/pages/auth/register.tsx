import { Form, Head, Link } from '@inertiajs/react';
import AuthLayout from '@/layouts/auth-layout';

interface RegisterProps {
    passwordRules: string;
}

const inputClasses =
    'w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-neutral-900 placeholder:text-neutral-400 focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-600/20 dark:border-neutral-700 dark:bg-neutral-950 dark:text-neutral-100 dark:placeholder:text-neutral-500';

const labelClasses = 'mb-1 block text-sm font-medium text-neutral-700 dark:text-neutral-300';

const errorClasses = 'mt-1 text-sm text-red-600 dark:text-red-400';

export default function Register({ passwordRules }: RegisterProps) {
    return (
        <AuthLayout subtitle="Crie sua conta para acessar os serviços do SILE">
            <Head title="Criar conta" />
            <Form action="/register" method="post">
                {({ errors, processing }) => (
                    <div className="flex flex-col gap-4">
                        <div>
                            <label htmlFor="name" className={labelClasses}>
                                Nome completo
                            </label>
                            <input
                                id="name"
                                type="text"
                                name="name"
                                autoComplete="name"
                                required
                                className={inputClasses}
                            />
                            {errors.name && <p className={errorClasses}>{errors.name}</p>}
                        </div>

                        <div>
                            <label htmlFor="email" className={labelClasses}>
                                E-mail
                            </label>
                            <input
                                id="email"
                                type="email"
                                name="email"
                                autoComplete="email"
                                required
                                className={inputClasses}
                            />
                            {errors.email && <p className={errorClasses}>{errors.email}</p>}
                        </div>

                        <div>
                            <label htmlFor="cpf" className={labelClasses}>
                                CPF
                            </label>
                            <input
                                id="cpf"
                                type="text"
                                name="cpf"
                                inputMode="numeric"
                                placeholder="000.000.000-00"
                                required
                                className={inputClasses}
                            />
                            {errors.cpf && <p className={errorClasses}>{errors.cpf}</p>}
                        </div>

                        <div>
                            <label htmlFor="phone" className={labelClasses}>
                                Telefone (opcional)
                            </label>
                            <input
                                id="phone"
                                type="tel"
                                name="phone"
                                autoComplete="tel"
                                placeholder="(71) 90000-0000"
                                className={inputClasses}
                            />
                            {errors.phone && <p className={errorClasses}>{errors.phone}</p>}
                        </div>

                        <div>
                            <label htmlFor="password" className={labelClasses}>
                                Senha
                            </label>
                            <input
                                id="password"
                                type="password"
                                name="password"
                                autoComplete="new-password"
                                required
                                className={inputClasses}
                            />
                            <p className="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                                Requisitos da senha: {passwordRules}
                            </p>
                            {errors.password && <p className={errorClasses}>{errors.password}</p>}
                        </div>

                        <div>
                            <label htmlFor="password_confirmation" className={labelClasses}>
                                Confirmar senha
                            </label>
                            <input
                                id="password_confirmation"
                                type="password"
                                name="password_confirmation"
                                autoComplete="new-password"
                                required
                                className={inputClasses}
                            />
                            {errors.password_confirmation && (
                                <p className={errorClasses}>{errors.password_confirmation}</p>
                            )}
                        </div>

                        <button
                            type="submit"
                            disabled={processing}
                            className="rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-blue-600 dark:hover:bg-blue-500"
                        >
                            {processing ? 'Criando conta...' : 'Criar conta'}
                        </button>

                        <p className="text-center text-sm text-neutral-600 dark:text-neutral-400">
                            Já tenho conta —{' '}
                            <Link
                                href="/login"
                                className="font-medium text-blue-700 hover:underline dark:text-blue-400"
                            >
                                Entrar
                            </Link>
                        </p>
                    </div>
                )}
            </Form>
        </AuthLayout>
    );
}
