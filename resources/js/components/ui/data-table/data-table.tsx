import type { ReactNode } from 'react';
import { ChevronDownIcon, SortIcon } from '@/components/icons';
import { Skeleton } from '@/components/ui/skeleton';
import type { ColumnDef, SortState } from './types';

interface DataTableProps<T> {
    columns: ColumnDef<T>[];
    rows: T[];
    rowKey: (row: T) => string | number;
    /** Estado de ordenação controlado — exigido quando há coluna sortable. */
    sort?: SortState;
    onSortChange?: (sort: SortState) => void;
    /** Exibe linhas de skeleton no lugar dos dados durante visitas. */
    loading?: boolean;
    skeletonRows?: number;
    /** Conteúdo exibido quando não há linhas (ex.: <EmptyState />). */
    emptyState?: ReactNode;
    /** Densidade das células: padrão py-4, compacta py-3. */
    density?: 'default' | 'compact';
}

const alignStyles = {
    start: 'text-start',
    center: 'text-center',
    end: 'text-end',
} as const;

function ariaSortOf(column: string, sort?: SortState): 'ascending' | 'descending' | undefined {
    if (!sort || sort.column !== column) {
        return undefined;
    }

    return sort.direction === 'asc' ? 'ascending' : 'descending';
}

/**
 * Tabela de dados do design system: colunas tipadas, ordenação
 * controlada (server-driven via use-server-table), skeleton de
 * carregamento e estado vazio integrados. Responsiva por rolagem
 * horizontal dentro do contêiner.
 */
export default function DataTable<T>({
    columns,
    rows,
    rowKey,
    sort,
    onSortChange,
    loading = false,
    skeletonRows = 5,
    emptyState,
    density = 'default',
}: DataTableProps<T>) {
    const cellPadding = density === 'compact' ? 'px-5 py-3' : 'px-5 py-4';

    function toggleSort(column: ColumnDef<T>) {
        if (!column.sortable || !onSortChange) {
            return;
        }

        const direction = sort?.column === column.id && sort.direction === 'asc' ? 'desc' : 'asc';

        onSortChange({ column: column.id, direction });
    }

    const showEmpty = !loading && rows.length === 0;

    return (
        <div className="overflow-hidden rounded-xl border border-gray-200 dark:border-white/[0.05]">
            <div className="max-w-full overflow-x-auto">
                <table className="min-w-full">
                    <thead className="border-b border-gray-100 dark:border-white/[0.05]">
                        <tr>
                            {columns.map((column) => {
                                const isSorted = sort?.column === column.id;

                                return (
                                    <th
                                        key={column.id}
                                        scope="col"
                                        aria-sort={ariaSortOf(column.id, sort)}
                                        className={`${cellPadding} text-theme-xs font-medium text-gray-500 dark:text-gray-400 ${alignStyles[column.align ?? 'start']} ${column.headerClassName ?? ''}`}
                                    >
                                        {column.sortable && onSortChange ? (
                                            <button
                                                type="button"
                                                onClick={() => toggleSort(column)}
                                                className={`group inline-flex items-center gap-1 rounded transition hover:text-gray-700 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-brand-500/40 dark:hover:text-gray-300 ${
                                                    isSorted ? 'text-gray-700 dark:text-gray-300' : ''
                                                }`}
                                            >
                                                {column.header}
                                                {isSorted ? (
                                                    <ChevronDownIcon
                                                        aria-hidden="true"
                                                        className={`size-3.5 text-brand-500 transition dark:text-brand-400 ${
                                                            sort?.direction === 'asc' ? 'rotate-180' : ''
                                                        }`}
                                                    />
                                                ) : (
                                                    <SortIcon
                                                        aria-hidden="true"
                                                        className="size-3.5 text-gray-300 transition group-hover:text-gray-400 dark:text-gray-600 dark:group-hover:text-gray-500"
                                                    />
                                                )}
                                            </button>
                                        ) : (
                                            column.header
                                        )}
                                    </th>
                                );
                            })}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100 dark:divide-white/[0.05]">
                        {loading
                            ? Array.from({ length: skeletonRows }, (_, index) => (
                                  <tr key={index}>
                                      {columns.map((column) => (
                                          <td key={column.id} className={cellPadding}>
                                              <Skeleton className="h-4 w-full max-w-40" />
                                          </td>
                                      ))}
                                  </tr>
                              ))
                            : rows.map((row) => (
                                  <tr
                                      key={rowKey(row)}
                                      className="transition hover:bg-gray-50 dark:hover:bg-white/[0.03]"
                                  >
                                      {columns.map((column) => (
                                          <td
                                              key={column.id}
                                              className={`${cellPadding} text-theme-sm text-gray-500 dark:text-gray-400 ${alignStyles[column.align ?? 'start']} ${column.cellClassName ?? ''}`}
                                          >
                                              {column.cell(row)}
                                          </td>
                                      ))}
                                  </tr>
                              ))}
                        {showEmpty && (
                            <tr>
                                <td colSpan={columns.length}>{emptyState}</td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
