import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import Checkbox from '@/components/form/checkbox';
import Input from '@/components/form/input';
import Label from '@/components/form/label';
import { EyeCloseIcon, EyeIcon } from '@/components/icons';
import Alert from '@/components/ui/alert';
import Button from '@/components/ui/button';
import AuthLayout from '@/layouts/auth-layout';

interface LoginProps {
    canResetPassword: boolean;
    status?: string;
}

export default function Login({ canResetPassword, status }: LoginProps) {
    const [showPassword, setShowPassword] = useState(false);
    const [remember, setRemember] = useState(false);

    return (
        <AuthLayout
            title="Bem-vindo de volta"
            subtitle="Acesse sua conta para consultar a viabilidade do seu negócio e acompanhar seus processos"
        >
            <Head title="Entrar" />
            {status && (
                <div className="mb-6">
                    <Alert variant="success" title="Sucesso" message={status} />
                </div>
            )}
            <Form action="/portal/login" method="post">
                {({ errors, processing }) => (
                    <div className="space-y-6">
                        {errors.email && (
                            <Alert
                                variant="error"
                                title="Não foi possível entrar"
                                message={errors.email}
                            />
                        )}

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
                            />
                        </div>

                        <div>
                            <Label htmlFor="password">Senha</Label>
                            <div className="relative">
                                <Input
                                    id="password"
                                    type={showPassword ? 'text' : 'password'}
                                    name="password"
                                    autoComplete="current-password"
                                    required
                                    error={!!errors.password}
                                    hint={errors.password}
                                />
                                <button
                                    type="button"
                                    onClick={() => setShowPassword((current) => !current)}
                                    aria-label={showPassword ? 'Ocultar senha' : 'Mostrar senha'}
                                    className="absolute top-3 right-4 z-30 cursor-pointer text-gray-500 dark:text-gray-400"
                                >
                                    {showPassword ? (
                                        <EyeIcon className="size-5" />
                                    ) : (
                                        <EyeCloseIcon className="size-5" />
                                    )}
                                </button>
                            </div>
                        </div>

                        <div className="flex items-center justify-between gap-4">
                            <Checkbox
                                id="remember"
                                name="remember"
                                label="Manter conectado"
                                checked={remember}
                                onChange={setRemember}
                            />
                            {canResetPassword && (
                                <Link
                                    href="/portal/forgot-password"
                                    className="text-sm font-medium text-brand-500 hover:text-brand-600 dark:text-brand-400"
                                >
                                    Esqueceu a senha?
                                </Link>
                            )}
                        </div>

                        <Button type="submit" size="sm" className="w-full" disabled={processing}>
                            {processing ? 'Entrando...' : 'Entrar'}
                        </Button>

                        <p className="border-t border-gray-100 pt-5 text-center text-sm font-normal text-gray-700 dark:border-gray-800 dark:text-gray-400">
                            Ainda não tem conta?{' '}
                            <Link
                                href="/portal/register"
                                className="font-medium text-brand-500 hover:text-brand-600 dark:text-brand-400"
                            >
                                Criar conta
                            </Link>
                        </p>
                    </div>
                )}
            </Form>
        </AuthLayout>
    );
}
