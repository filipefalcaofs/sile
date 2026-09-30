import { useEffect, useState, type InputHTMLAttributes } from 'react';
import { brDateToIso, isoToBrDate } from '@/lib/masks';
import MaskedInput from './masked-input';

interface IsoDateInputProps extends Omit<InputHTMLAttributes<HTMLInputElement>, 'onChange' | 'value' | 'defaultValue' | 'type'> {
    value?: string;
    defaultValue?: string;
    onChange?: (iso: string) => void;
    error?: boolean;
    hint?: string;
}

export default function IsoDateInput({
    value,
    defaultValue,
    onChange,
    name,
    ...rest
}: IsoDateInputProps) {
    const controlled = value !== undefined;
    const [isoValue, setIsoValue] = useState(defaultValue ?? '');
    const [brValue, setBrValue] = useState(() => isoToBrDate(defaultValue ?? ''));

    useEffect(() => {
        if (controlled) {
            setBrValue(isoToBrDate(value ?? ''));
        }
    }, [controlled, value]);

    function handleChange(masked: string) {
        setBrValue(masked);
        const iso = brDateToIso(masked) ?? '';
        if (!controlled) {
            setIsoValue(iso);
        }
        onChange?.(iso);
    }

    return (
        <>
            {name !== undefined && <input type="hidden" name={name} value={brDateToIso(brValue) ?? ''} />}
            <MaskedInput
                {...rest}
                mask="date"
                value={brValue}
                onChange={handleChange}
                placeholder={rest.placeholder ?? 'dd/mm/aaaa'}
                autoComplete="off"
            />
        </>
    );
}
