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
        <label className="flex items-center gap-2 text-theme-sm text-gray-500 dark:text-gray-400">
            Exibir
            <select
                value={value}
                onChange={(event) => onChange(Number(event.target.value))}
                className="h-11 appearance-none rounded-lg border border-gray-300 bg-transparent bg-[url('data:image/svg+xml;charset=utf-8,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%2220%22%20height%3D%2220%22%20viewBox%3D%220%200%2020%2020%22%20fill%3D%22none%22%3E%3Cpath%20d%3D%22M4.79175%207.396L10.0001%2012.6043L15.2084%207.396%22%20stroke%3D%22%2398a2b3%22%20stroke-width%3D%221.5%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%2F%3E%3C%2Fsvg%3E')] bg-[position:right_0.75rem_center] bg-no-repeat py-2.5 pr-10 pl-4 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:ring-3 focus:ring-brand-500/20 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
            >
                {allOptions.map((option) => (
                    <option key={option} value={option} className="text-gray-700 dark:bg-gray-900 dark:text-gray-400">
                        {option}
                    </option>
                ))}
            </select>
            por página
        </label>
    );
}
