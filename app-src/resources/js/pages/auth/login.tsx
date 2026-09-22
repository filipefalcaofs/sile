import { Form, Head, Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { KeyboardEvent } from 'react';
import Checkbox from '@/components/form/checkbox';
import Input from '@/components/form/input';
import Label from '@/components/form/label';
import MaskedInput from '@/components/form/masked-input';
import GovBrButton from '@/components/app/govbr-button';
import { EyeCloseIcon, EyeIcon } from '@/components/icons';
import Alert from '@/components/ui/alert';
import Button from '@/components/ui/button';
import AuthLayout from '@/layouts/auth-layout';
import type { SharedProps } from '@/types';

interface LoginProps {
    canResetPassword: boolean;
    canLoginWithGovBr: boolean;
    status?: string;
}

export default function Login({ canResetPassword, canLoginWithGovBr, status }: LoginProps) {
    const { flash } = usePage<SharedProps>().props;
    const [showPassword, setShowPassword] = useState(false);
    const [remember, setRemember] = useState(false);
    const [capsLockOn, setCapsLockOn] = useState(false);

    const handlePasswordKeyUp = (event: KeyboardEvent<HTMLInputElement>) => {
        if (typeof event.getModifierState === 'function') {
            setCapsLockOn(event.getModifierState('CapsLock'));
        }
    };

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
            {flash.error && (
                <div className="mb-6">
                    <Alert variant="error" title="Atenção" message={flash.error} />
                </div>
            )}
            <Form action="/portal/login" method="post">
                {({ errors, processing }) => (
                    <div className="space-y-6">
                        {(errors.cpf || errors.email) && (
                            <Alert
                                variant="error"
                                title="Não foi possível entrar"
                                message={errors.cpf ?? errors.email}
                            />
                        )}

                        <div>
                            <Label htmlFor="cpf" required>
                                CPF
                            </Label>
                            <MaskedInput
                                id="cpf"
                                mask="cpf"
                                name="cpf"
                                autoComplete="username"
                                autoFocus
                                required
                                placeholder="000.000.000-00"
                                error={!!errors.cpf}
                            />
                        </div>

                        <div>
                            <Label htmlFor="password" required>
                                Senha
                            </Label>
                            <div className="relative">
                                <Input
                                    id="password"
                                    type={showPassword ? 'text' : 'password'}
                                    name="password"
                                    autoComplete="current-password"
                                    required
                                    className="pr-12"
                                    onKeyUp={handlePasswordKeyUp}
                                    onBlur={() => setCapsLockOn(false)}
                                    error={!!errors.password}
                                    hint={errors.password}
                                />
                                <button
                                    type="button"
                                    onClick={() => setShowPassword((current) => !current)}
                                    aria-label={showPassword ? 'Ocultar senha' : 'Mostrar senha'}
                                    aria-pressed={showPassword}
                                    className="absolute top-[7px] right-2 z-30 grid size-[30px] cursor-pointer place-items-center rounded-md text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-700 focus-visible:ring-2 focus-visible:ring-brand-500/40 focus-visible:outline-none dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-200"
                                >
                                    {showPassword ? (
                                        <EyeIcon className="size-5" />
                                    ) : (
                                        <EyeCloseIcon className="size-5" />
                                    )}
                                </button>
                            </div>
                            {capsLockOn && (
                                <p className="mt-1.5 text-xs text-warning-600 dark:text-warning-400">
                                    Caps Lock está ativado.
                                </p>
                            )}
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

                        {canLoginWithGovBr && <GovBrButton />}

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
