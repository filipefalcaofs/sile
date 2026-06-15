import { router, useHttp, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { SearchIcon } from '@/components/icons';
import { Modal } from '@/components/ui/modal';
import type { SharedProps } from '@/types';

interface ResultadoBusca {
    id: number;
    protocolo: string | null;
    empresa: string | null;
    link: string;
}

/**
 * Busca global da retaguarda (HU-082 RN-009): atalho Cmd/Ctrl+K + gatilho
 * flutuante (mobile/descoberta) que abre um modal e consulta o endpoint leve
 * `gestao.processos.busca` (10-14, debounce) para levar direto ao processo.
 * Montado no gestao-layout; visível apenas para quem tem consultar-solicitacoes
 * (degradação controlada — quem não consulta não vê o atalho).
 */
export default function CommandSearch() {
    const { auth } = usePage<SharedProps>().props;
    const podeConsultar = auth.permissions.includes('consultar-solicitacoes');

    const [open, setOpen] = useState(false);
    const [termo, setTermo] = useState('');
    const [resultados, setResultados] = useState<ResultadoBusca[]>([]);
    const inputRef = useRef<HTMLInputElement>(null);

    const busca = useHttp<Record<string, never>, { resultados: ResultadoBusca[] }>({});

    useEffect(() => {
        if (!podeConsultar) {
            return;
        }

        function aoTeclar(evento: KeyboardEvent) {
            if ((evento.metaKey || evento.ctrlKey) && evento.key.toLowerCase() === 'k') {
                evento.preventDefault();
                setOpen((atual) => !atual);
            }
        }

        window.addEventListener('keydown', aoTeclar);

        return () => window.removeEventListener('keydown', aoTeclar);
    }, [podeConsultar]);

    useEffect(() => {
        if (!open) {
            setTermo('');
            setResultados([]);
        }
    }, [open]);

    useEffect(() => {
        if (!open) {
            return;
        }

        const limpo = termo.trim();

        if (limpo === '') {
            setResultados([]);

            return;
        }

        const timer = window.setTimeout(() => {
            busca.get(`/gestao/processos/busca?q=${encodeURIComponent(limpo)}`, {
                onSuccess: (resposta) => setResultados(resposta?.resultados ?? []),
                onHttpException: () => {
                    setResultados([]);

                    return false;
                },
            });
        }, 250);

        return () => window.clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [termo, open]);

    function abrirProcesso(link: string) {
        setOpen(false);
        router.visit(link);
    }

    if (!podeConsultar) {
        return null;
    }

    return (
        <>
            <button
                type="button"
                onClick={() => setOpen(true)}
                title="Buscar processo (⌘K)"
                aria-label="Buscar processo"
                className="fixed bottom-6 right-6 z-40 inline-flex items-center gap-2 rounded-full border border-gray-200 bg-white px-4 py-3 text-sm text-gray-600 shadow-theme-lg transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-white/[0.05]"
            >
                <SearchIcon className="size-5" />
                <span className="hidden sm:inline">Buscar</span>
                <kbd className="hidden rounded bg-gray-100 px-1.5 py-0.5 text-theme-xs font-medium text-gray-500 sm:inline dark:bg-white/10 dark:text-gray-400">
                    ⌘K
                </kbd>
            </button>

            {open && (
                <Modal
                    isOpen
                    onClose={() => setOpen(false)}
                    showCloseButton={false}
                    className="m-4 mt-[10vh] max-h-[80vh] max-w-[640px] self-start overflow-hidden p-0"
                >
                    <div className="flex items-center gap-3 border-b border-gray-200 px-4 py-3 dark:border-gray-800">
                        <SearchIcon className="size-5 shrink-0 text-gray-400" />
                        <input
                            ref={inputRef}
                            type="search"
                            value={termo}
                            onChange={(evento) => setTermo(evento.target.value)}
                            placeholder="Buscar por protocolo, BAP, TVL, CNPJ ou empresa…"
                            className="w-full bg-transparent text-sm text-gray-800 placeholder:text-gray-400 focus:outline-hidden dark:text-white/90 dark:placeholder:text-white/30"
                        />
                        {busca.processing && <span className="text-theme-xs text-gray-400">Buscando…</span>}
                    </div>

                    <div className="max-h-[60vh] overflow-y-auto p-2">
                        {termo.trim() === '' ? (
                            <p className="px-3 py-6 text-center text-theme-sm text-gray-500 dark:text-gray-400">
                                Digite para buscar processos.
                            </p>
                        ) : resultados.length === 0 && !busca.processing ? (
                            <p className="px-3 py-6 text-center text-theme-sm text-gray-500 dark:text-gray-400">
                                Nenhum processo encontrado.
                            </p>
                        ) : (
                            <ul className="flex flex-col">
                                {resultados.map((resultado) => (
                                    <li key={resultado.id}>
                                        <button
                                            type="button"
                                            onClick={() => abrirProcesso(resultado.link)}
                                            className="flex w-full items-center justify-between gap-3 rounded-lg px-3 py-2.5 text-left transition hover:bg-gray-50 dark:hover:bg-white/[0.05]"
                                        >
                                            <span className="text-theme-sm font-medium text-gray-800 dark:text-white/90">
                                                {resultado.protocolo ?? `#${resultado.id}`}
                                            </span>
                                            {resultado.empresa && (
                                                <span className="truncate text-theme-xs text-gray-500 dark:text-gray-400">
                                                    {resultado.empresa}
                                                </span>
                                            )}
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                </Modal>
            )}
        </>
    );
}
