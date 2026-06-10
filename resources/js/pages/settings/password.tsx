import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import Input from '@/components/form/input';
import Label from '@/components/form/label';
import { EyeCloseIcon, EyeIcon } from '@/components/icons';
import Button from '@/components/ui/button';
import SettingsLayout from '@/layouts/settings-layout';

interface PasswordProps {
    passwordRules: string;
}

interface PasswordFieldProps {
    id: string;
    name: string;
    label: string;
    autoComplete: string;
    error?: string;
    hint?: string;
}

function PasswordField({ id, name, label, autoComplete, error, hint }: PasswordFieldProps) {
    const [show, setShow] = useState(false);

    return (
        <div>
            <Label htmlFor={id}>{label}</Label>
            <div className="relative">
                <Input
                    id={id}
                    type={show ? 'text' : 'password'}
                    name={name}
                    autoComplete={autoComplete}
                    required
                    error={!!error}
                    hint={error ?? hint}
                />
                <button
                    type="button"
                    onClick={() => setShow((current) => !current)}
                    aria-label={show ? 'Ocultar senha' : 'Mostrar senha'}
                    className="absolute top-3 right-4 z-30 cursor-pointer text-gray-500 dark:text-gray-400"
                >
                    {show ? <EyeIcon className="size-5" /> : <EyeCloseIcon className="size-5" />}
                </button>
            </div>
        </div>
    );
}

export default function Password({ passwordRules }: PasswordProps) {
    return (
        <SettingsLayout>
            <Head title="Alterar senha" />
            <div className="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
                <div className="mb-6">
                    <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90">
                        Alterar senha
                    </h4>
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Para sua segurança, informe a senha atual antes de definir uma nova.
                    </p>
                </div>

                <Form action="/portal/user/password" method="put" errorBag="updatePassword" resetOnSuccess>
                    {({ errors, processing, recentlySuccessful }) => (
                        <div className="flex max-w-md flex-col gap-5">
                            <PasswordField
                                id="current_password"
                                name="current_password"
                                label="Senha atual"
                                autoComplete="current-password"
                                error={errors.current_password}
                            />

                            <PasswordField
                                id="password"
                                name="password"
                                label="Nova senha"
                                autoComplete="new-password"
                                error={errors.password}
                                hint={`Requisitos da senha: ${passwordRules}`}
                            />

                            <PasswordField
                                id="password_confirmation"
                                name="password_confirmation"
                                label="Confirmar nova senha"
                                autoComplete="new-password"
                                error={errors.password_confirmation}
                            />

                            <div className="flex items-center gap-4">
                                <Button type="submit" size="sm" disabled={processing}>
                                    {processing ? 'Alterando...' : 'Alterar senha'}
                                </Button>
                                {recentlySuccessful && (
                                    <p className="text-sm text-success-600 dark:text-success-500">
                                        Senha alterada com sucesso.
                                    </p>
                                )}
                            </div>
                        </div>
                    )}
                </Form>
            </div>
        </SettingsLayout>
    );
}
