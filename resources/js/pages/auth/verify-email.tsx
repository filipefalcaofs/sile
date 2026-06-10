import { Form, Head, Link } from '@inertiajs/react';
import type { SVGProps } from 'react';
import Alert from '@/components/ui/alert';
import Button from '@/components/ui/button';
import AuthLayout from '@/layouts/auth-layout';

interface VerifyEmailProps {
    status?: string;
}

function MailIcon(props: SVGProps<SVGSVGElement>) {
    return (
        <svg
            width="24"
            height="24"
            viewBox="0 0 24 24"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
            {...props}
        >
            <path
                fillRule="evenodd"
                clipRule="evenodd"
                d="M3.25 7.5C3.25 6.25736 4.25736 5.25 5.5 5.25H18.5C19.7426 5.25 20.75 6.25736 20.75 7.5V16.5C20.75 17.7426 19.7426 18.75 18.5 18.75H5.5C4.25736 18.75 3.25 17.7426 3.25 16.5V7.5ZM5.5 6.75C5.08579 6.75 4.75 7.08579 4.75 7.5V7.82953L12 12.3704L19.25 7.82953V7.5C19.25 7.08579 18.9142 6.75 18.5 6.75H5.5ZM19.25 9.59951L12.3982 13.8909C12.1546 14.0434 11.8454 14.0434 11.6018 13.8909L4.75 9.59951V16.5C4.75 16.9142 5.08579 17.25 5.5 17.25H18.5C18.9142 17.25 19.25 16.9142 19.25 16.5V9.59951Z"
                fill="currentColor"
            />
        </svg>
    );
}

export default function VerifyEmail({ status }: VerifyEmailProps) {
    return (
        <AuthLayout
            title="Confirme seu e-mail"
            subtitle="Enviamos um link de confirmação para o seu e-mail. Verifique sua caixa de entrada"
            icon={<MailIcon className="size-6" />}
        >
            <Head title="Confirme seu e-mail" />
            <div className="space-y-6">
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

                <p className="border-t border-gray-100 pt-5 text-center text-sm dark:border-gray-800">
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
