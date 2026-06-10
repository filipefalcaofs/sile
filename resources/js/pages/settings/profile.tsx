import { Form, Head } from '@inertiajs/react';
import SettingsLayout from '@/layouts/settings-layout';

interface ProfileProps {
    user: {
        id: number;
        name: string;
        email: string;
        cpf: string;
        phone: string | null;
    };
}

const inputClasses =
    'w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-neutral-900 placeholder:text-neutral-400 focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-600/20 dark:border-neutral-700 dark:bg-neutral-950 dark:text-neutral-100 dark:placeholder:text-neutral-500';

const labelClasses = 'mb-1 block text-sm font-medium text-neutral-700 dark:text-neutral-300';

const errorClasses = 'mt-1 text-sm text-red-600 dark:text-red-400';

function formatCpf(cpf: string): string {
    return cpf.replace(/^(\d{3})(\d{3})(\d{3})(\d{2})$/, '$1.$2.$3-$4');
}

export default function Profile({ user }: ProfileProps) {
    return (
        <SettingsLayout>
            <Head title="Meu perfil" />
            <section className="rounded-xl bg-white p-6 shadow-sm dark:bg-neutral-900">
                <h2 className="text-base font-semibold text-neutral-900 dark:text-neutral-100">
                    Meu perfil
                </h2>
                <p className="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                    Mantenha seus dados pessoais atualizados.
                </p>

                <Form action="/settings/profile" method="patch">
                    {({ errors, processing, recentlySuccessful }) => (
                        <div className="mt-6 flex max-w-md flex-col gap-4">
                            <div>
                                <label htmlFor="name" className={labelClasses}>
                                    Nome completo
                                </label>
                                <input
                                    id="name"
                                    type="text"
                                    name="name"
                                    defaultValue={user.name}
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
                                    defaultValue={user.email}
                                    autoComplete="email"
                                    required
                                    className={inputClasses}
                                />
                                <p className="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                                    Ao alterar o e-mail, você precisará confirmá-lo novamente.
                                </p>
                                {errors.email && <p className={errorClasses}>{errors.email}</p>}
                            </div>

                            <div>
                                <label htmlFor="phone" className={labelClasses}>
                                    Telefone
                                </label>
                                <input
                                    id="phone"
                                    type="text"
                                    name="phone"
                                    defaultValue={user.phone ?? ''}
                                    autoComplete="tel"
                                    className={inputClasses}
                                />
                                {errors.phone && <p className={errorClasses}>{errors.phone}</p>}
                            </div>

                            <div>
                                <label htmlFor="cpf" className={labelClasses}>
                                    CPF
                                </label>
                                <input
                                    id="cpf"
                                    type="text"
                                    value={formatCpf(user.cpf)}
                                    readOnly
                                    disabled
                                    className={`${inputClasses} cursor-not-allowed bg-neutral-100 text-neutral-500 dark:bg-neutral-900 dark:text-neutral-400`}
                                />
                                <p className="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                                    O CPF não pode ser alterado.
                                </p>
                            </div>

                            <div className="flex items-center gap-4">
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-blue-600 dark:hover:bg-blue-500"
                                >
                                    {processing ? 'Salvando...' : 'Salvar alterações'}
                                </button>
                                {recentlySuccessful && (
                                    <p className="text-sm text-green-700 dark:text-green-400">
                                        Perfil atualizado com sucesso.
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
