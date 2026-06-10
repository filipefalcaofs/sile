import { Form, Head } from '@inertiajs/react';
import AuthLayout from '@/layouts/auth-layout';

interface ResetPasswordProps {
    email: string;
    token: string;
}

const inputClasses =
    'w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-neutral-900 placeholder:text-neutral-400 focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-600/20 dark:border-neutral-700 dark:bg-neutral-950 dark:text-neutral-100 dark:placeholder:text-neutral-500';

const labelClasses = 'mb-1 block text-sm font-medium text-neutral-700 dark:text-neutral-300';

const errorClasses = 'mt-1 text-sm text-red-600 dark:text-red-400';

export default function ResetPassword({ email, token }: ResetPasswordProps) {
    return (
        <AuthLayout subtitle="Defina uma nova senha para sua conta">
            <Head title="Redefinir senha" />
            <Form action="/reset-password" method="post">
                {({ errors, processing }) => (
                    <div className="flex flex-col gap-4">
                        <input type="hidden" name="token" value={token} />

                        <div>
                            <label htmlFor="email" className={labelClasses}>
                                E-mail
                            </label>
                            <input
                                id="email"
                                type="email"
                                name="email"
                                value={email}
                                readOnly
                                className={`${inputClasses} bg-neutral-100 dark:bg-neutral-900`}
                            />
                            {errors.email && <p className={errorClasses}>{errors.email}</p>}
                        </div>

                        <div>
                            <label htmlFor="password" className={labelClasses}>
                                Nova senha
                            </label>
                            <input
                                id="password"
                                type="password"
                                name="password"
                                autoComplete="new-password"
                                autoFocus
                                required
                                className={inputClasses}
                            />
                            {errors.password && <p className={errorClasses}>{errors.password}</p>}
                        </div>

                        <div>
                            <label htmlFor="password_confirmation" className={labelClasses}>
                                Confirmar nova senha
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
                            {processing ? 'Redefinindo...' : 'Redefinir senha'}
                        </button>
                    </div>
                )}
            </Form>
        </AuthLayout>
    );
}
