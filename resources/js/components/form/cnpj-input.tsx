import { useState, type InputHTMLAttributes } from 'react';
import { applyAlphanumericCnpjMask } from '@/lib/masks';
import Input from './input';

interface CnpjInputProps extends Omit<InputHTMLAttributes<HTMLInputElement>, 'onChange'> {
    success?: boolean;
    error?: boolean;
    hint?: string;
    onChange?: (value: string) => void;
}

/** CNPJ numérico ou alfanumérico no formato 00.000.000/0000-00. */
export default function CnpjInput({ value: externalValue, defaultValue, onChange, ...rest }: CnpjInputProps) {
    const [internalValue, setInternalValue] = useState(() =>
        applyAlphanumericCnpjMask(String(externalValue ?? defaultValue ?? '')),
    );
    const displayValue =
        externalValue !== undefined ? applyAlphanumericCnpjMask(String(externalValue)) : internalValue;

    function handleChange(event: React.ChangeEvent<HTMLInputElement>) {
        const masked = applyAlphanumericCnpjMask(event.target.value);
        setInternalValue(masked);
        onChange?.(masked);
    }

    return (
        <Input
            {...rest}
            value={displayValue}
            onChange={handleChange}
            autoCapitalize="characters"
            autoComplete="off"
            spellCheck={false}
            placeholder={rest.placeholder ?? '00.000.000/0000-00'}
        />
    );
}
