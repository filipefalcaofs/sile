import { router } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import type { SortState } from './types';

interface ServerTableState {
    search: string;
    sort: SortState;
    perPage: number;
    filters: Record<string, string>;
}

/** Snapshot de query enviado na navegação e reusado na exportação (?formato=). */
export type ServerTableParams = Record<string, string | number>;

/**
 * Monta os parâmetros da listagem a partir do estado: ordenação, itens por
 * página, busca (quando preenchida) e filtros não-vazios. Fonte única usada
 * tanto pela navegação (`visit`) quanto pelo `currentParams` da exportação.
 */
function buildParams(state: ServerTableState): ServerTableParams {
    const params: ServerTableParams = {
        sort: state.sort.column,
        direction: state.sort.direction,
        per_page: state.perPage,
    };

    if (state.search.trim() !== '') {
        params.search = state.search;
    }

    for (const [key, value] of Object.entries(state.filters)) {
        if (value !== '') {
            params[key] = value;
        }
    }

    return params;
}

interface UseServerTableOptions {
    /** URL da listagem (rota do índice). */
    url: string;
    initialSearch?: string;
    initialSort: SortState;
    initialPerPage: number;
    /** Filtros adicionais ecoados pelo backend (ex.: { active: '1' }). */
    initialFilters?: Record<string, string>;
    debounceMs?: number;
}

/**
 * Estado de tabela server-driven via Inertia: busca com debounce,
 * ordenação, filtros e itens por página viram query string com
 * preserveState — qualquer mudança volta à página 1. Filtros vazios
 * são omitidos da URL.
 */
export function useServerTable({
    url,
    initialSearch = '',
    initialSort,
    initialPerPage,
    initialFilters = {},
    debounceMs = 350,
}: UseServerTableOptions) {
    const [search, setSearch] = useState(initialSearch);
    const [sort, setSortState] = useState<SortState>(initialSort);
    const [perPage, setPerPageState] = useState(initialPerPage);
    const [filters, setFiltersState] = useState<Record<string, string>>(initialFilters);
    const [processing, setProcessing] = useState(false);

    const hasMounted = useRef(false);
    const stateRef = useRef<ServerTableState>({ search, sort, perPage, filters });
    stateRef.current = { search, sort, perPage, filters };

    const visit = useCallback(
        (state: ServerTableState) => {
            const params = buildParams(state);

            router.get(url, params, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
            });
        },
        [url],
    );

    useEffect(() => {
        if (!hasMounted.current) {
            return;
        }

        const timeout = setTimeout(() => visit(stateRef.current), debounceMs);

        return () => clearTimeout(timeout);
    }, [search, debounceMs, visit]);

    useEffect(() => {
        hasMounted.current = true;

        return () => {
            hasMounted.current = false;
        };
    }, []);

    const setSort = useCallback(
        (next: SortState) => {
            setSortState(next);
            visit({ ...stateRef.current, sort: next });
        },
        [visit],
    );

    const setPerPage = useCallback(
        (next: number) => {
            setPerPageState(next);
            visit({ ...stateRef.current, perPage: next });
        },
        [visit],
    );

    const setFilter = useCallback(
        (key: string, value: string) => {
            const next = { ...stateRef.current.filters, [key]: value };
            setFiltersState(next);
            visit({ ...stateRef.current, filters: next });
        },
        [visit],
    );

    // Mesmo snapshot enviado na navegação — exposto para o ExportMenu montar a
    // URL de exportação (?formato=...) preservando os filtros atuais (RN-004).
    const currentParams = useMemo(
        () => buildParams({ search, sort, perPage, filters }),
        [search, sort, perPage, filters],
    );

    return { search, setSearch, sort, setSort, perPage, setPerPage, filters, setFilter, processing, currentParams };
}
