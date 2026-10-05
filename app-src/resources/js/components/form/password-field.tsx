import { useState, type InputHTMLAttributes } from 'react';
import { EyeCloseIcon, EyeIcon } from '@/components/icons';
import Input from './input';
import Label from './label';

interface PasswordFieldProps extends Omit<InputHTMLAttributes<HTMLInputElement>, 'type'> {
    label: string;
    error?: string;
    hint?: string;
    required?: boolean;
}

/** Senha com rótulo, erro e botão de mostrar/ocultar. */
export default function PasswordField({
    id,
    label,
    error,
    hint,
    required = true,
    className = '',
    ...rest
}: PasswordFieldProps) {
    const [show, setShow] = useState(false);

    return (
        <div>
            <Label htmlFor={id} required={required}>
                {label}
            </Label>
            <div className="relative">
                <Input
                    id={id}
                    type={show ? 'text' : 'password'}
                    required={required}
                    error={Boolean(error)}
                    hint={error ?? hint}
                    className={`pr-12 ${className}`}
                    {...rest}
                />
                <button
                    type="button"
                    onClick={() => setShow((current) => !current)}
                    aria-label={show ? 'Ocultar senha' : 'Mostrar senha'}
                    className="absolute top-0 right-0 z-30 flex h-11 w-11 cursor-pointer items-center justify-center text-gray-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500 dark:text-gray-400"
                >
                    {show ? <EyeIcon className="size-5" /> : <EyeCloseIcon className="size-5" />}
                </button>
            </div>
        </div>
    );
}
