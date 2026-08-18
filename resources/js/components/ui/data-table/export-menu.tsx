import { useState } from 'react';
import { ChevronDownIcon, FileIcon } from '@/components/icons';
import { Dropdown } from '@/components/ui/dropdown';
import type { ServerTableParams } from './use-server-table';

export type ExportFormat = 'csv' | 'xlsx' | 'pdf';

const FORMAT_LABELS: Record<ExportFormat, string> = {
    csv: 'Exportar CSV',
    xlsx: 'Exportar Excel',
    pdf: 'Exportar PDF',
};

const DEFAULT_FORMATS: ExportFormat[] = ['csv', 'xlsx', 'pdf'];

interface ExportMenuProps {
    /** URL do índice (rota da listagem) que responde a `?formato=`. */
    url: string;
    /** Filtros/ordenação/busca atuais — `currentParams` do useServerTable. */
    params: ServerTableParams;
    /** Formatos habilitados (relatorios.export.formatos_habilitados). */
    formatos?: ExportFormat[];
    label?: string;
    className?: string;
}

/**
 * Monta a URL de exportação preservando os parâmetros atuais da tabela e o
 * formato escolhido. O conjunto exportado é exatamente o conjunto filtrado da
 * tela (RN-004) — o backend recorta as colunas/limites no source.
 */
function buildHref(url: string, params: ServerTableParams, formato: ExportFormat): string {
    const query = new URLSearchParams();

    for (const [key, value] of Object.entries(params)) {
        query.set(key, String(value));
    }

    query.set('formato', formato);

    return `${url}?${query.toString()}`;
}

/**
 * Dropdown de exportação reusável por qualquer tela com useServerTable +
 * DataTable. Cada item é uma âncora nativa para `{url}?formato=...` (download
 * real, não visita Inertia); acima do limiar o backend processa em segundo
 * plano e a notificação leva ao arquivo (comportamento do ReportExporter).
 */
export function ExportMenu({
    url,
    params,
    formatos = DEFAULT_FORMATS,
    label = 'Exportar',
    className = '',
}: ExportMenuProps) {
    const [isOpen, setIsOpen] = useState(false);

    if (formatos.length === 0) {
        return null;
    }

    return (
        <div className={`relative inline-block ${className}`.trim()}>
            <button
                type="button"
                onClick={() => setIsOpen((open) => !open)}
                aria-haspopup="menu"
                aria-expanded={isOpen}
                className="dropdown-toggle inline-flex items-center justify-center gap-2 rounded-lg bg-white px-4 py-3 text-sm text-gray-700 shadow-theme-xs ring-1 ring-gray-300 ring-inset transition hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-400 dark:ring-gray-700 dark:hover:bg-white/[0.03]"
            >
                <FileIcon className="size-5" />
                {label}
                <ChevronDownIcon className="size-4" />
            </button>

            <Dropdown isOpen={isOpen} onClose={() => setIsOpen(false)} className="w-48 p-2">
                <ul role="menu" className="flex flex-col gap-1">
                    {formatos.map((formato) => (
                        <li key={formato} role="none">
                            <a
                                role="menuitem"
                                href={buildHref(url, params, formato)}
                                onClick={() => setIsOpen(false)}
                                className="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-gray-700 transition hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-white/[0.05] dark:hover:text-white"
                            >
                                <FileIcon className="size-4 text-gray-400" />
                                {FORMAT_LABELS[formato]}
                            </a>
                        </li>
                    ))}
                </ul>
            </Dropdown>
        </div>
    );
}

export default ExportMenu;
