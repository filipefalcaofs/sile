import type { ReactNode } from 'react';

export type SortDirection = 'asc' | 'desc';

export interface SortState {
    column: string;
    direction: SortDirection;
}

export interface ColumnDef<T> {
    /** Identificador estável — para colunas ordenáveis, é o valor enviado em `sort`. */
    id: string;
    header: ReactNode;
    sortable?: boolean;
    align?: 'start' | 'center' | 'end';
    headerClassName?: string;
    cellClassName?: string;
    /** Mantém a coluna visível na borda direita durante a rolagem horizontal. */
    sticky?: 'end';
    cell: (row: T) => ReactNode;
}
