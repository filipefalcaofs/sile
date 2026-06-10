import { Form, Head, Link } from '@inertiajs/react';
import Input from '@/components/form/input';
import Label from '@/components/form/label';
import { LockIcon } from '@/components/icons';
import Alert from '@/components/ui/alert';
import Button from '@/components/ui/button';
import AuthLayout from '@/layouts/auth-layout';

interface ForgotPasswordProps {
    status?: string;
}

export default function ForgotPassword({ status }: ForgotPasswordProps) {
    return (
        <AuthLayout
            title="Recuperar senha"
            subtitle="Informe seu e-mail e enviaremos um link para redefinir sua senha"
            icon={<LockIcon className="size-6" />}
        >
            <Head title="Esqueci minha senha" />
            {status && (
                <div className="mb-6">
                    <Alert variant="success" title="Sucesso" message={status} />
                </div>
            )}
            <Form action="/forgot-password" method="post">
                {({ errors, processing }) => (
                    <div className="space-y-6">
                        <div>
                            <Label htmlFor="email">E-mail</Label>
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                autoComplete="email"
                                autoFocus
                                required
                                placeholder="nome@exemplo.com"
                                error={!!errors.email}
                                hint={errors.email}
                            />
                        </div>

                        <Button type="submit" size="sm" className="w-full" disabled={processing}>
                            {processing ? 'Enviando...' : 'Enviar link de recuperação'}
                        </Button>

                        <p className="border-t border-gray-100 pt-5 text-center text-sm font-normal text-gray-700 dark:border-gray-800 dark:text-gray-400">
                            Lembrou a senha?{' '}
                            <Link
                                href="/login"
                                className="font-medium text-brand-500 hover:text-brand-600 dark:text-brand-400"
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
