import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import GovBrButton from '@/components/app/govbr-button';
import Input from '@/components/form/input';
import MaskedInput from '@/components/form/masked-input';
import Label from '@/components/form/label';
import { EyeCloseIcon, EyeIcon } from '@/components/icons';
import Button from '@/components/ui/button';
import AuthLayout from '@/layouts/auth-layout';

interface RegisterProps {
    passwordRules: string;
    canLoginWithGovBr: boolean;
}

interface PasswordFieldProps {
    id: string;
    name: string;
    label: string;
    autoComplete: string;
    error?: string;
    hint?: string;
    onInput?: () => void;
}

function PasswordField({ id, name, label, autoComplete, error, hint, onInput }: PasswordFieldProps) {
    const [show, setShow] = useState(false);

    return (
        <div>
            <Label htmlFor={id} required>
                {label}
            </Label>
            <div className="relative">
                <Input
                    id={id}
                    type={show ? 'text' : 'password'}
                    name={name}
                    autoComplete={autoComplete}
                    required
                    error={!!error}
                    hint={error ?? hint}
                    onInput={onInput}
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

export default function Register({ passwordRules, canLoginWithGovBr }: RegisterProps) {
    return (
        <AuthLayout
            title="Crie sua conta gratuita"
            subtitle="Em poucos minutos você consulta a viabilidade do seu negócio e acompanha tudo pelo portal"
        >
            <Head title="Criar conta" />
            <Form action="/portal/register" method="post">
                {({ errors, processing, clearErrors }) => (
                    <div className="space-y-5">
                        <div>
                            <Label htmlFor="name" required>
                                Nome completo
                            </Label>
                            <Input
                                id="name"
                                type="text"
                                name="name"
                                autoComplete="name"
                                required
                                error={!!errors.name}
                                hint={errors.name}
                            />
                        </div>

                        <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
                            <div>
                                <Label htmlFor="cpf" required>
                                    CPF
                                </Label>
                                <MaskedInput
                                    id="cpf"
                                    mask="cpf"
                                    name="cpf"
                                    placeholder="000.000.000-00"
                                    required
                                    error={!!errors.cpf}
                                    hint={errors.cpf}
                                />
                            </div>
                            <div>
                                <Label htmlFor="phone" required>
                                    Telefone
                                </Label>
                                <MaskedInput
                                    id="phone"
                                    mask="phone"
                                    name="phone"
                                    autoComplete="tel"
                                    placeholder="(71) 90000-0000"
                                    required
                                    error={!!errors.phone}
                                    hint={errors.phone}
                                />
                            </div>
                        </div>

                        <div>
                            <Label htmlFor="email" required>
                                E-mail
                            </Label>
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                autoComplete="email"
                                required
                                error={!!errors.email}
                                hint={errors.email}
                            />
                        </div>

                        <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
                            <PasswordField
                                id="password"
                                name="password"
                                label="Senha"
                                autoComplete="new-password"
                                error={errors.password}
                                onInput={() => clearErrors('password')}
                            />

                            <PasswordField
                                id="password_confirmation"
                                name="password_confirmation"
                                label="Confirmar senha"
                                autoComplete="new-password"
                                error={errors.password_confirmation}
                                onInput={() => clearErrors('password_confirmation')}
                            />
                        </div>

                        <p className="text-xs text-gray-500 dark:text-gray-400">
                            Requisitos da senha: {passwordRules}
                        </p>

                        <Button type="submit" size="sm" className="w-full" disabled={processing}>
                            {processing ? 'Criando conta...' : 'Criar conta'}
                        </Button>

                        {canLoginWithGovBr && <GovBrButton />}

                        <p className="border-t border-gray-100 pt-5 text-center text-sm font-normal text-gray-700 dark:border-gray-800 dark:text-gray-400">
                            Já tem conta?{' '}
                            <Link
                                href="/portal/login"
                                className="font-medium text-brand-500 hover:text-brand-600 dark:text-brand-400"
                            >
                                Entrar
                            </Link>
                        </p>
                    </div>
                )}
            </Form>
        </AuthLayout>
    );
}
