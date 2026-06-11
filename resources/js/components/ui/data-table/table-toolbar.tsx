import type { ReactNode } from 'react';
import { SearchIcon } from '@/components/icons';

interface TableToolbarProps {
    search?: {
        value: string;
        onChange: (value: string) => void;
        placeholder?: string;
        label?: string;
    };
    /** Filtros adicionais (selects, toggles) alinhados à direita. */
    filters?: ReactNode;
    /** Ações da listagem (ex.: seletor de itens por página). */
    actions?: ReactNode;
}

/**
 * Barra de controles da listagem: busca à esquerda, filtros e ações à
 * direita — empilha no mobile.
 */
export default function TableToolbar({ search, filters, actions }: TableToolbarProps) {
    return (
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            {search ? (
                <div className="relative w-full sm:max-w-xs">
                    <label htmlFor="table-search" className="sr-only">
                        {search.label ?? 'Buscar'}
                    </label>
                    <span className="pointer-events-none absolute top-1/2 left-4 -translate-y-1/2 text-gray-400 dark:text-gray-500">
                        <SearchIcon className="size-5" />
                    </span>
                    <input
                        id="table-search"
                        type="search"
                        value={search.value}
                        onChange={(event) => search.onChange(event.target.value)}
                        placeholder={search.placeholder ?? 'Buscar...'}
                        className="h-11 w-full appearance-none rounded-lg border border-gray-300 bg-transparent py-2.5 pr-4 pl-11 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/20 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                    />
                </div>
            ) : (
                <span />
            )}
            {(filters || actions) && (
                <div className="flex flex-wrap items-center gap-3">
                    {filters}
                    {actions}
                </div>
            )}
        </div>
    );
}
