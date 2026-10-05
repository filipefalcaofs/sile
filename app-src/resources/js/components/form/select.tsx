import { useId, type ChangeEvent } from 'react';

interface Option {
    value: string;
    label: string;
}

interface SelectProps {
    options: Option[];
    placeholder?: string;
    value: string;
    onChange: (value: string) => void;
    className?: string;
    name?: string;
    id?: string;
    disabled?: boolean;
    error?: boolean;
    hint?: string;
    hidePlaceholder?: boolean;
    ariaLabel?: string;
}

/**
 * Select do TailAdmin adaptado para componente controlado (value +
 * onChange), padrão usado com useForm/<Form> do Inertia.
 */
export default function Select({
    options,
    placeholder = 'Selecione uma opção',
    value,
    onChange,
    className = '',
    name,
    id,
    disabled = false,
    error = false,
    hint,
    hidePlaceholder = false,
    ariaLabel,
}: SelectProps) {
    const hintId = useId();

    const handleChange = (e: ChangeEvent<HTMLSelectElement>) => {
        onChange(e.target.value);
    };

    const borda = error
        ? 'border-error-500 focus:border-error-300 focus:ring-error-500/20 dark:border-error-500 dark:focus:border-error-800'
        : 'border-gray-300 focus:border-brand-300 focus:ring-brand-500/10 dark:border-gray-700 dark:focus:border-brand-800';

    return (
        <div className="relative">
            <select
                name={name}
                id={id}
                disabled={disabled}
                aria-label={ariaLabel}
                aria-invalid={error || undefined}
                aria-describedby={hint ? hintId : undefined}
                className={`h-11 w-full appearance-none rounded-lg border bg-transparent px-4 py-2.5 pr-11 text-sm shadow-theme-xs placeholder:text-gray-400 focus:outline-hidden focus:ring-3 dark:bg-gray-900 dark:placeholder:text-white/30 ${borda} ${
                    value ? 'text-gray-800 dark:text-white/90' : 'text-gray-400 dark:text-gray-400'
                } ${className}`}
                value={value}
                onChange={handleChange}
            >
                {!hidePlaceholder && (
                    <option value="" disabled className="text-gray-700 dark:bg-gray-900 dark:text-gray-400">
                        {placeholder}
                    </option>
                )}
                {options.map((option) => (
                    <option
                        key={option.value}
                        value={option.value}
                        className="text-gray-700 dark:bg-gray-900 dark:text-gray-400"
                    >
                        {option.label}
                    </option>
                ))}
            </select>
            {hint && (
                <p id={hintId} role={error ? 'alert' : undefined} className={`pt-1.5 text-xs ${error ? 'text-error-500' : 'text-gray-500'}`}>
                    {hint}
                </p>
            )}
        </div>
    );
}
