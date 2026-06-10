import { Form, Head } from '@inertiajs/react';
import SettingsLayout from '@/layouts/settings-layout';

interface PasswordProps {
    passwordRules: string;
}

const inputClasses =
    'w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-neutral-900 placeholder:text-neutral-400 focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-600/20 dark:border-neutral-700 dark:bg-neutral-950 dark:text-neutral-100 dark:placeholder:text-neutral-500';

const labelClasses = 'mb-1 block text-sm font-medium text-neutral-700 dark:text-neutral-300';

const errorClasses = 'mt-1 text-sm text-red-600 dark:text-red-400';

export default function Password({ passwordRules }: PasswordProps) {
    return (
        <SettingsLayout>
            <Head title="Alterar senha" />
            <section className="rounded-xl bg-white p-6 shadow-sm dark:bg-neutral-900">
                <h2 className="text-base font-semibold text-neutral-900 dark:text-neutral-100">
                    Alterar senha
                </h2>
                <p className="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                    Para sua segurança, informe a senha atual antes de definir uma nova.
                </p>

                <Form
                    action="/user/password"
                    method="put"
                    errorBag="updatePassword"
                    resetOnSuccess
                >
                    {({ errors, processing, recentlySuccessful }) => (
                        <div className="mt-6 flex max-w-md flex-col gap-4">
                            <div>
                                <label htmlFor="current_password" className={labelClasses}>
                                    Senha atual
                                </label>
                                <input
                                    id="current_password"
                                    type="password"
                                    name="current_password"
                                    autoComplete="current-password"
                                    required
                                    className={inputClasses}
                                />
                                {errors.current_password && (
                                    <p className={errorClasses}>{errors.current_password}</p>
                                )}
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

                            <div className="flex items-center gap-4">
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-blue-600 dark:hover:bg-blue-500"
                                >
                                    {processing ? 'Alterando...' : 'Alterar senha'}
                                </button>
                                {recentlySuccessful && (
                                    <p className="text-sm text-green-700 dark:text-green-400">
                                        Senha alterada com sucesso.
                                    </p>
                                )}
                            </div>
                        </div>
                    )}
                </Form>
            </section>
        </SettingsLayout>
    );
}
