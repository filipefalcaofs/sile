import { Form, Head, Link } from '@inertiajs/react';
import Input from '@/components/form/input';
import Label from '@/components/form/label';
import Alert from '@/components/ui/alert';
import Button from '@/components/ui/button';
import AuthLayout from '@/layouts/auth-layout';

interface ForgotPasswordProps {
    status?: string;
}

export default function ForgotPassword({ status }: ForgotPasswordProps) {
    return (
        <AuthLayout subtitle="Recupere o acesso à sua conta">
            <Head title="Esqueci minha senha" />
            <p className="mb-6 text-sm text-gray-500 dark:text-gray-400">
                Informe seu e-mail e enviaremos um link para redefinir sua senha.
            </p>
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
                                error={!!errors.email}
                                hint={errors.email}
                            />
                        </div>

                        <Button type="submit" size="sm" className="w-full" disabled={processing}>
                            {processing ? 'Enviando...' : 'Enviar link de recuperação'}
                        </Button>

                        <p className="text-center text-sm font-normal text-gray-700 dark:text-gray-400">
                            <Link
                                href="/login"
                                className="text-brand-500 hover:text-brand-600 dark:text-brand-400"
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
