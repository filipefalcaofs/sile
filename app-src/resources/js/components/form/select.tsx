import OptionSelect from './option-select';

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
 * Lista suspensa controlada (value + onChange) com campo de busca.
 * Padrão para todas as telas; substitui o select nativo.
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
    ariaLabel,
}: SelectProps) {
    return (
        <OptionSelect
            options={options}
            placeholder={placeholder}
            value={value}
            onChange={onChange}
            className={className}
            name={name}
            id={id}
            disabled={disabled}
            error={error}
            hint={hint}
            ariaLabel={ariaLabel}
            panelMinWidth={256}
        />
    );
}
