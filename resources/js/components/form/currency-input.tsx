import { useState, type InputHTMLAttributes } from 'react';
import { brlToDecimal, decimalToBrl } from '@/lib/masks';
import Input from './input';

interface CurrencyInputProps extends Omit<InputHTMLAttributes<HTMLInputElement>, 'onChange' | 'value' | 'defaultValue'> {
    value?: string;
    defaultValue?: string;
    success?: boolean;
    error?: boolean;
    hint?: string;
    /** Recebe o decimal com ponto (`1111.78`), que é o valor enviado no formulário. */
    onChange?: (value: string) => void;
}

/**
 * Valor em reais. A tela mostra `1.111,78`; o campo enviado guarda `1111.78`.
 */
export default function CurrencyInput({
    value: externalValue,
    defaultValue,
    onChange,
    name,
    id,
    ...rest
}: CurrencyInputProps) {
    const [internalValue, setInternalValue] = useState(() => String(externalValue ?? defaultValue ?? ''));
    const canonical = externalValue !== undefined ? String(externalValue) : internalValue;

    function handleChange(event: React.ChangeEvent<HTMLInputElement>) {
        const decimal = brlToDecimal(event.target.value);
        setInternalValue(decimal);
        onChange?.(decimal);
    }

    return (
        <>
            {name && <input type="hidden" name={name} value={canonical} />}
            <Input
                {...rest}
                id={id}
                inputMode="decimal"
                value={decimalToBrl(canonical)}
                onChange={handleChange}
                placeholder={rest.placeholder ?? '0,00'}
            />
        </>
    );
}
