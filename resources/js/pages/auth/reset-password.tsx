import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import Input from '@/components/form/input';
import Label from '@/components/form/label';
import { EyeCloseIcon, EyeIcon, LockIcon } from '@/components/icons';
import Button from '@/components/ui/button';
import AuthLayout from '@/layouts/auth-layout';

interface ResetPasswordProps {
    email: string;
    token: string;
}

interface PasswordFieldProps {
    id: string;
    name: string;
    label: string;
    autoFocus?: boolean;
    error?: string;
}

function PasswordField({ id, name, label, autoFocus = false, error }: PasswordFieldProps) {
    const [show, setShow] = useState(false);

    return (
        <div>
            <Label htmlFor={id}>{label}</Label>
            <div className="relative">
                <Input
                    id={id}
                    type={show ? 'text' : 'password'}
                    name={name}
                    autoComplete="new-password"
                    autoFocus={autoFocus}
                    required
                    error={!!error}
                    hint={error}
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

export default function ResetPassword({ email, token }: ResetPasswordProps) {
    return (
        <AuthLayout
            title="Redefinir senha"
            subtitle="Defina uma nova senha para voltar a acessar sua conta"
            icon={<LockIcon className="size-6" />}
        >
            <Head title="Redefinir senha" />
            <Form action="/reset-password" method="post">
                {({ errors, processing }) => (
                    <div className="space-y-6">
                        <input type="hidden" name="token" value={token} />

                        <div>
                            <Label htmlFor="email">E-mail</Label>
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                value={email}
                                readOnly
                                error={!!errors.email}
                                hint={errors.email}
                            />
                        </div>

                        <PasswordField
                            id="password"
                            name="password"
                            label="Nova senha"
                            autoFocus
                            error={errors.password}
                        />

                        <PasswordField
                            id="password_confirmation"
                            name="password_confirmation"
                            label="Confirmar nova senha"
                            error={errors.password_confirmation}
                        />

                        <Button type="submit" size="sm" className="w-full" disabled={processing}>
                            {processing ? 'Redefinindo...' : 'Redefinir senha'}
                        </Button>
                    </div>
                )}
            </Form>
        </AuthLayout>
    );
}
