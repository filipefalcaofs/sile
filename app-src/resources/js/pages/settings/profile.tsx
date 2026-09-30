import { Form, Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import Input from '@/components/form/input';
import MaskedInput from '@/components/form/masked-input';
import { applyMask, MASK_PATTERNS } from '@/lib/masks';
import Label from '@/components/form/label';
import Button from '@/components/ui/button';
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

export default function Profile({ user }: ProfileProps) {
    return (
        <>
            <Head title="Meu perfil" />
            <div className="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <div className="mb-6">
                    <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90">
                        Meu perfil
                    </h4>
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Mantenha seus dados pessoais atualizados.
                    </p>
                </div>

                <Form action="/portal/conta/perfil" method="patch">
                    {({ errors, processing, recentlySuccessful }) => (
                        <div className="flex flex-col gap-6">
                            <div className="grid grid-cols-1 gap-x-6 gap-y-5 lg:grid-cols-2">
                                <div>
                                    <Label htmlFor="name">Nome completo</Label>
                                    <Input
                                        id="name"
                                        type="text"
                                        name="name"
                                        defaultValue={user.name}
                                        autoComplete="name"
                                        required
                                        error={!!errors.name}
                                        hint={errors.name}
                                    />
                                </div>

                                <div>
                                    <Label htmlFor="email">E-mail</Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        name="email"
                                        defaultValue={user.email}
                                        autoComplete="email"
                                        required
                                        error={!!errors.email}
                                        hint={
                                            errors.email ??
                                            'Ao alterar o e-mail, você precisará confirmá-lo novamente.'
                                        }
                                    />
                                </div>

                                <div>
                                    <Label htmlFor="phone">Telefone</Label>
                                    <MaskedInput
                                        id="phone"
                                        mask="phone"
                                        name="phone"
                                        defaultValue={user.phone ?? ''}
                                        autoComplete="tel"
                                        error={!!errors.phone}
                                        hint={errors.phone}
                                    />
                                </div>

                                <div>
                                    <Label htmlFor="cpf">CPF</Label>
                                    <Input
                                        id="cpf"
                                        type="text"
                                        value={applyMask(user.cpf, MASK_PATTERNS.cpf)}
                                        readOnly
                                        disabled
                                        hint="O CPF não pode ser alterado."
                                    />
                                </div>
                            </div>

                            <div className="flex items-center gap-4">
                                <Button type="submit" size="sm" disabled={processing}>
                                    {processing ? 'Salvando...' : 'Salvar alterações'}
                                </Button>
                                {recentlySuccessful && (
                                    <p className="text-sm text-success-600 dark:text-success-500">
                                        Perfil atualizado com sucesso.
                                    </p>
                                )}
                            </div>
                        </div>
                    )}
                </Form>
            </div>
        </>
    );
}

Profile.layout = (page: ReactNode) => <SettingsLayout>{page}</SettingsLayout>;
