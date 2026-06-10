import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import Checkbox from '@/components/form/checkbox';
import Input from '@/components/form/input';
import Label from '@/components/form/label';
import { EyeCloseIcon, EyeIcon, LockIcon } from '@/components/icons';
import Alert from '@/components/ui/alert';
import Button from '@/components/ui/button';
import AuthLayout from '@/layouts/auth-layout';

interface GestaoLoginProps {
    status?: string;
}

/**
 * Login interno da retaguarda (Gestão SEDUR). Não é divulgado no portal
 * público: sem link de cadastro — contas internas são administradas
 * pela própria SEDUR (HU-012).
 */
export default function GestaoLogin({ status }: GestaoLoginProps) {
    const [showPassword, setShowPassword] = useState(false);
    const [remember, setRemember] = useState(false);

    return (
        <AuthLayout
            title="Gestão SEDUR"
            subtitle="Ambiente interno de análise e administração do SILE"
        >
            <Head title="Entrar — Gestão" />
            <div className="mb-6 flex items-center gap-3 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-800 dark:bg-white/[0.03]">
                <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-500 dark:bg-brand-500/15 dark:text-brand-400">
                    <LockIcon className="size-5" />
                </span>
                <p className="text-theme-sm text-gray-600 dark:text-gray-400">
                    Acesso restrito a servidores autorizados. Toda tentativa de acesso é registrada.
                </p>
            </div>
            {status && (
                <div className="mb-6">
                    <Alert variant="success" title="Sucesso" message={status} />
                </div>
            )}
            <Form action="/gestao/login" method="post">
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
                            <Label htmlFor="email">E-mail institucional</Label>
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                autoComplete="email"
                                autoFocus
                                required
                                placeholder="nome@salvador.ba.gov.br"
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

                        <Checkbox
                            id="remember"
                            name="remember"
                            label="Manter conectado"
                            checked={remember}
                            onChange={setRemember}
                        />

                        <Button type="submit" size="sm" className="w-full" disabled={processing}>
                            {processing ? 'Entrando...' : 'Entrar na gestão'}
                        </Button>
                    </div>
                )}
            </Form>
        </AuthLayout>
    );
}
