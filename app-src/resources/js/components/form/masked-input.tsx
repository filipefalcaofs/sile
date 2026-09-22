import { useState, type InputHTMLAttributes } from 'react';
import Input from './input';

type MaskPattern = 'cpf' | 'phone' | 'cnpj' | 'cep';

const MASKS: Record<MaskPattern, string> = {
    cpf: '999.999.999-99',
    phone: '(99) 99999-9999',
    cnpj: '99.999.999/9999-99',
    cep: '99999-999',
};

function applyMask(value: string, pattern: string): string {
    const digits = value.replace(/\D/g, '');
    let result = '';
    let digitIndex = 0;

    for (let i = 0; i < pattern.length && digitIndex < digits.length; i++) {
        if (pattern[i] === '9') {
            result += digits[digitIndex++];
        } else {
            result += pattern[i];
        }
    }

    return result;
}

interface MaskedInputProps extends Omit<InputHTMLAttributes<HTMLInputElement>, 'onChange'> {
    mask: MaskPattern;
    success?: boolean;
    error?: boolean;
    hint?: string;
    onChange?: (value: string) => void;
}

export default function MaskedInput({
    mask,
    value: externalValue,
    defaultValue,
    onChange,
    ...rest
}: MaskedInputProps) {
    const pattern = MASKS[mask];

    const [internalValue, setInternalValue] = useState(() => {
        const initial = (externalValue ?? defaultValue ?? '') as string;
        return applyMask(initial, pattern);
    });

    const displayValue = externalValue !== undefined ? applyMask(externalValue as string, pattern) : internalValue;

    function handleChange(e: React.ChangeEvent<HTMLInputElement>) {
        const masked = applyMask(e.target.value, pattern);
        setInternalValue(masked);
        e.target.value = masked;
        onChange?.(masked);
    }

    return (
        <Input
            {...rest}
            value={displayValue}
            onChange={handleChange}
            inputMode="numeric"
        />
    );
}
