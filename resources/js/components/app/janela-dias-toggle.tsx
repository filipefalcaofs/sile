interface JanelaDiasToggleProps {
    janelas: number[];
    ativa: number;
    onSelecionar: (dias: number) => void;
}

/** Seletor de janela em dias (7, 30, 90), o mesmo do Painel da operação. */
export default function JanelaDiasToggle({ janelas, ativa, onSelecionar }: JanelaDiasToggleProps) {
    return (
        <div className="inline-flex items-center gap-0.5 rounded-[10px] border border-gray-200 bg-white p-0.5 dark:border-gray-800 dark:bg-gray-900">
            {janelas.map((dias) => {
                const ativo = dias === ativa;

                return (
                    <button
                        key={dias}
                        type="button"
                        aria-pressed={ativo}
                        onClick={() => onSelecionar(dias)}
                        className={`rounded-lg px-3.5 py-2 text-sm font-medium transition ${
                            ativo
                                ? 'bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-300'
                                : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'
                        }`}
                    >
                        {dias} dias
                    </button>
                );
            })}
        </div>
    );
}
