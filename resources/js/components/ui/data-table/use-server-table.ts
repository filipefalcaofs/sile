import { router } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import type { SortState } from './types';

interface ServerTableState {
    search: string;
    sort: SortState;
    perPage: number;
    filters: Record<string, string>;
}

/**
 * Busca por código com máscara (ex.: 4711-3/02, 47.11-3-02): envia só os dígitos.
 * A máscara não muda o resultado no servidor, que compara o código por dígitos, e
 * `-`, `/` e `.` na query são barrados por firewalls da rede da Prefeitura.
 * Texto com letras segue como o usuário digitou.
 */
export function buscaSemMascaraDeCodigo(termo: string): string {
    return /^[\d.\-/\s]+$/.test(termo) && /\d/.test(termo) ? termo.replace(/\D/g, '') : termo;
}

/** Snapshot de query enviado na navegação e reusado na exportação (?formato=). */
export type ServerTableParams = Record<string, string | number>;

/**
 * Monta os parâmetros da listagem a partir do estado: ordenação, itens por
 * página, busca (quando preenchida) e filtros não-vazios. Fonte única usada
 * tanto pela navegação (`visit`) quanto pelo `currentParams` da exportação.
 */
function buildParams(state: ServerTableState, transformSearch: (termo: string) => string = (termo) => termo): ServerTableParams {
    const params: ServerTableParams = {
        sort: state.sort.column,
        direction: state.sort.direction,
        per_page: state.perPage,
    };

    if (state.search.trim() !== '') {
        params.search = transformSearch(state.search);
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
    /** Ajusta o termo antes de ir para a query (ex.: tirar a máscara de código). */
    transformSearch?: (termo: string) => string;
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
    transformSearch,
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
            const params = buildParams(state, transformSearch);

            router.get(url, params, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
            });
        },
        [url, transformSearch],
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

    /** Troca vários filtros de uma vez, com uma só navegação (formulários com botão Pesquisar). */
    const setFilters = useCallback(
        (changes: Record<string, string>) => {
            const next = { ...stateRef.current.filters, ...changes };
            setFiltersState(next);
            visit({ ...stateRef.current, filters: next });
        },
        [visit],
    );

    // Mesmo snapshot enviado na navegação — exposto para o ExportMenu montar a
    // URL de exportação (?formato=...) preservando os filtros atuais (RN-004).
    const currentParams = useMemo(
        () => buildParams({ search, sort, perPage, filters }, transformSearch),
        [search, sort, perPage, filters, transformSearch],
    );

    return { search, setSearch, sort, setSort, perPage, setPerPage, filters, setFilter, setFilters, processing, currentParams };
}
