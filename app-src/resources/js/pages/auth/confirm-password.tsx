import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import Input from '@/components/form/input';
import Label from '@/components/form/label';
import { EyeCloseIcon, EyeIcon, LockIcon } from '@/components/icons';
import Button from '@/components/ui/button';
import AuthLayout from '@/layouts/auth-layout';

export default function ConfirmPassword() {
    const [showPassword, setShowPassword] = useState(false);

    return (
        <AuthLayout
            title="Confirme sua senha"
            subtitle="Por segurança, confirme sua senha antes de continuar com esta ação"
            icon={<LockIcon className="size-6" />}
        >
            <Head title="Confirmar senha" />
            <Form action="/portal/user/confirm-password" method="post">
                {({ errors, processing }) => (
                    <div className="space-y-6">
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
                                    autoFocus
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

                        <Button type="submit" size="sm" className="w-full" disabled={processing}>
                            {processing ? 'Confirmando...' : 'Confirmar senha'}
                        </Button>
                    </div>
                )}
            </Form>
        </AuthLayout>
    );
}
