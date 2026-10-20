import Select from '@/components/form/select';

interface PerPageSelectProps {
    value: number;
    options: number[];
    onChange: (value: number) => void;
}

/**
 * Seletor de itens por página da listagem. Inclui o valor vigente nas
 * opções mesmo quando ele não pertence à whitelist (ex.: padrão
 * administrado via parâmetro).
 */
export default function PerPageSelect({ value, options, onChange }: PerPageSelectProps) {
    const allOptions = options.includes(value) ? options : [...options, value].sort((a, b) => a - b);

    return (
        <div className="flex items-center gap-2 text-theme-sm text-gray-500 dark:text-gray-400">
            <span>Exibir</span>
            <Select
                ariaLabel="Itens por página"
                className="w-24"
                value={String(value)}
                onChange={(valor) => onChange(Number(valor))}
                options={allOptions.map((opcao) => ({ value: String(opcao), label: String(opcao) }))}
            />
            <span>por página</span>
        </div>
    );
}
