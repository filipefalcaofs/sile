import { Form, Head } from '@inertiajs/react';
import Input from '@/components/form/input';
import Label from '@/components/form/label';
import PasswordField from '@/components/form/password-field';
import { LockIcon } from '@/components/icons';
import Button from '@/components/ui/button';
import AuthLayout from '@/layouts/auth-layout';

interface ResetPasswordProps {
    email: string;
    token: string;
}


export default function ResetPassword({ email, token }: ResetPasswordProps) {
    return (
        <AuthLayout
            title="Redefinir senha"
            subtitle="Defina uma nova senha para voltar a acessar sua conta"
            icon={<LockIcon className="size-6" />}
        >
            <Head title="Redefinir senha" />
            <Form action="/portal/reset-password" method="post">
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
