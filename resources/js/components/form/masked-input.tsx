import { useState, type InputHTMLAttributes } from 'react';
import { applyDigitsOnly, applyMask, MASK_PATTERNS, type MaskPattern } from '@/lib/masks';
import Input from './input';

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
    const pattern = mask === 'inscricao' ? '' : MASK_PATTERNS[mask];

    const [internalValue, setInternalValue] = useState(() => {
        const initial = (externalValue ?? defaultValue ?? '') as string;
        return mask === 'inscricao' ? applyDigitsOnly(initial) : applyMask(initial, pattern);
    });

    const displayValue =
        externalValue !== undefined
            ? mask === 'inscricao'
                ? applyDigitsOnly(externalValue as string)
                : applyMask(externalValue as string, pattern)
            : internalValue;

    function handleChange(e: React.ChangeEvent<HTMLInputElement>) {
        const masked =
            mask === 'inscricao' ? applyDigitsOnly(e.target.value) : applyMask(e.target.value, pattern);
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
