import { useHttp } from '@inertiajs/react';
import { useEffect, useId, useRef, useState } from 'react';
import { SearchIcon } from '@/components/icons';
import Alert from '@/components/ui/alert';
import { Skeleton } from '@/components/ui/skeleton';

export interface CnaeOption {
    id: number;
    formatted_code: string;
    description: string;
}

interface CnaePickerProps {
    onSelect: (cnae: CnaeOption) => void;
    /** CNAEs que não devem aparecer nos resultados (ex.: principal atual e já selecionados). */
    excludeIds?: number[];
    placeholder?: string;
}

const SEARCH_DEBOUNCE_MS = 350;
const MIN_SEARCH_LENGTH = 2;

/**
 * Busca incremental de CNAEs ativos na tabela oficial via
 * GET /portal/cnaes (somente dados reais do endpoint — máx. 20 itens,
 * [03-06]). Componente local às páginas de empresas.
 */
export default function CnaePicker({
    onSelect,
    excludeIds = [],
    placeholder = 'Buscar CNAE por código ou descrição...',
}: CnaePickerProps) {
    const inputId = useId();
    const [term, setTerm] = useState('');
    const [results, setResults] = useState<CnaeOption[]>([]);
    const [searched, setSearched] = useState(false);
    const [failed, setFailed] = useState(false);

    const http = useHttp<Record<string, never>, CnaeOption[]>({});
    const httpRef = useRef(http);
    httpRef.current = http;

    /** Sequência da última busca disparada — respostas antigas são ignoradas. */
    const requestSeq = useRef(0);

    useEffect(() => {
        const trimmed = term.trim();

        if (trimmed.length < MIN_SEARCH_LENGTH) {
            requestSeq.current += 1;
            setResults([]);
            setSearched(false);
            setFailed(false);

            return;
        }

        const timeout = setTimeout(() => {
            const seq = ++requestSeq.current;

            void httpRef.current.get(`/portal/cnaes?search=${encodeURIComponent(trimmed)}`, {
                onSuccess: (response) => {
                    if (seq !== requestSeq.current) {
                        return;
                    }

                    setResults(Array.isArray(response) ? response : []);
                    setSearched(true);
                    setFailed(false);
                },
                onError: () => {
                    if (seq !== requestSeq.current) {
                        return;
                    }

                    setResults([]);
                    setSearched(false);
                    setFailed(true);
                },
            });
        }, SEARCH_DEBOUNCE_MS);

        return () => clearTimeout(timeout);
    }, [term]);

    function select(cnae: CnaeOption) {
        onSelect(cnae);
        requestSeq.current += 1;
        setTerm('');
        setResults([]);
        setSearched(false);
        setFailed(false);
    }

    const visibleResults = results.filter((cnae) => !excludeIds.includes(cnae.id));
    const searching = http.processing;

    return (
        <div className="flex flex-col gap-3">
            <div className="relative">
                <label htmlFor={inputId} className="sr-only">
                    Buscar CNAE
                </label>
                <span className="pointer-events-none absolute top-1/2 left-4 -translate-y-1/2 text-gray-400 dark:text-gray-500">
                    <SearchIcon className="size-5" />
                </span>
                <input
                    id={inputId}
                    type="search"
                    value={term}
                    onChange={(event) => setTerm(event.target.value)}
                    placeholder={placeholder}
                    autoComplete="off"
                    className="h-11 w-full appearance-none rounded-lg border border-gray-300 bg-transparent py-2.5 pr-4 pl-11 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/20 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                />
            </div>

            {failed && (
                <Alert
                    variant="error"
                    title="Busca de CNAEs indisponível"
                    message="Não foi possível consultar a tabela oficial de CNAEs. Tente novamente."
                />
            )}

            {searching && (
                <div className="flex flex-col gap-2" aria-label="Buscando CNAEs">
                    <Skeleton className="h-10 w-full" />
                    <Skeleton className="h-10 w-2/3" />
                </div>
            )}

            {!searching && searched && visibleResults.length === 0 && (
                <p className="text-sm text-gray-500 dark:text-gray-400">Nenhum CNAE ativo encontrado.</p>
            )}

            {!searching && visibleResults.length > 0 && (
                <ul className="max-h-64 divide-y divide-gray-100 overflow-y-auto rounded-xl border border-gray-200 dark:divide-white/[0.05] dark:border-gray-800">
                    {visibleResults.map((cnae) => (
                        <li key={cnae.id}>
                            <button
                                type="button"
                                onClick={() => select(cnae)}
                                className="flex w-full flex-col items-start gap-0.5 px-4 py-3 text-start transition hover:bg-gray-50 focus:bg-gray-50 focus:outline-hidden dark:hover:bg-white/[0.03] dark:focus:bg-white/[0.03]"
                            >
                                <span className="text-sm font-medium text-gray-800 dark:text-white/90">
                                    {cnae.formatted_code}
                                </span>
                                <span className="text-theme-xs text-gray-500 dark:text-gray-400">
                                    {cnae.description}
                                </span>
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
