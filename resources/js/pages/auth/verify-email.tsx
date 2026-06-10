import { Form, Head, Link } from '@inertiajs/react';
import Alert from '@/components/ui/alert';
import Button from '@/components/ui/button';
import AuthLayout from '@/layouts/auth-layout';

interface VerifyEmailProps {
    status?: string;
}

export default function VerifyEmail({ status }: VerifyEmailProps) {
    return (
        <AuthLayout subtitle="Confirme seu e-mail">
            <Head title="Confirme seu e-mail" />
            <div className="space-y-6">
                <p className="text-sm text-gray-500 dark:text-gray-400">
                    Enviamos um link de confirmação para o seu e-mail. Verifique sua caixa de
                    entrada.
                </p>

                {status === 'verification-link-sent' && (
                    <Alert variant="success" title="Sucesso" message="Um novo link foi enviado." />
                )}

                <Form action="/email/verification-notification" method="post">
                    {({ processing }) => (
                        <Button type="submit" size="sm" className="w-full" disabled={processing}>
                            {processing ? 'Enviando...' : 'Reenviar e-mail de confirmação'}
                        </Button>
                    )}
                </Form>

                <p className="text-center text-sm">
                    <Link
                        href="/logout"
                        method="post"
                        as="button"
                        className="text-sm font-medium text-gray-700 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-300"
                    >
                        Sair
                    </Link>
                </p>
            </div>
        </AuthLayout>
    );
}
