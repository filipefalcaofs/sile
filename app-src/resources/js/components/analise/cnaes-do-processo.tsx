import { useState } from 'react';

export interface CnaeDoProcesso {
    codigo: string;
    descricao: string;
    principal: boolean;
}

const CNAES_VISIVEIS = 3;

/** Lista de CNAEs da linha: principal primeiro; acima de 3, o restante expande sob demanda (ou todos de uma vez, com `exibirTodos`). */
export default function CnaesDoProcesso({ cnaes, exibirTodos = false }: { cnaes: CnaeDoProcesso[]; exibirTodos?: boolean }) {
    const [expandido, setExpandido] = useState(false);

    if (cnaes.length === 0) {
        return <span className="text-gray-400 dark:text-gray-500">—</span>;
    }

    const excedente = exibirTodos ? 0 : cnaes.length - CNAES_VISIVEIS;
    const visiveis = expandido || excedente <= 0 ? cnaes : cnaes.slice(0, CNAES_VISIVEIS);

    return (
        <ul className="flex flex-col gap-1.5">
            {visiveis.map((cnae) => (
                <li key={cnae.codigo} className="flex flex-col leading-snug">
                    <span className="flex items-center gap-1.5">
                        <span className="font-medium tabular-nums text-gray-800 dark:text-white/90">{cnae.codigo}</span>
                        {cnae.principal && (
                            <span className="rounded-full bg-brand-50 px-1.5 py-px text-[10px] font-medium text-brand-600 dark:bg-brand-500/15 dark:text-brand-400">
                                Principal
                            </span>
                        )}
                    </span>
                    <span className="line-clamp-2 text-theme-xs text-gray-500 dark:text-gray-400" title={cnae.descricao}>
                        {cnae.descricao}
                    </span>
                </li>
            ))}
            {excedente > 0 && (
                <li>
                    <button
                        type="button"
                        onClick={() => setExpandido((atual) => !atual)}
                        aria-expanded={expandido}
                        className="text-theme-xs font-medium text-brand-600 hover:underline dark:text-brand-400"
                    >
                        {expandido ? 'Mostrar menos' : `+${excedente} ${excedente === 1 ? 'atividade' : 'atividades'}`}
                    </button>
                </li>
            )}
        </ul>
    );
}
