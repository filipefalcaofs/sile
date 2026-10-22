import { lazy, Suspense, useEffect, useState, type ReactNode } from 'react';

import { explicacaoDaDecisao, temCamadasDeContexto, type DadosMapaDecisao } from '@/components/geo/mapa-decisao-dados';
import { Modal } from '@/components/ui/modal';

// Mesmo pitfall do MapaSection: o Leaflet acessa `window` na importação e quebra
// o SSR do Inertia v3; o import dinâmico só resolve no cliente.
const MapaDecisao = lazy(() => import('@/components/geo/mapa-decisao').then((module) => ({ default: module.MapaDecisao })));

const ALTURA = 480;

function MapaSkeleton() {
    return <div className="animate-pulse rounded-2xl bg-gray-100 dark:bg-white/[0.03]" style={{ height: ALTURA }} />;
}

interface MapaDecisaoSectionProps {
    /** Endpoint JSON das camadas do processo (consulta o GeoServer da SEDUR). */
    url: string;
    /** Endereço do lote, para o rótulo do polígono. */
    rotuloLote?: string | null;
    /** Mapa simples mostrado quando as camadas falham ou não há o que justificar. */
    fallback: ReactNode;
}

type Estado = { fase: 'carregando' } | { fase: 'pronto'; dados: DadosMapaDecisao } | { fase: 'indisponivel' };

/**
 * Mapa do processo com zonas, vias e linhas de centralidade. Busca as camadas ao
 * montar, sem travar o detalhe: enquanto não chegam, um esqueleto do mesmo tamanho
 * ocupa o lugar; se falharem, entra o mapa simples do lote.
 */
export function MapaDecisaoSection({ url, rotuloLote = null, fallback }: MapaDecisaoSectionProps) {
    const [montado, setMontado] = useState(false);
    const [estado, setEstado] = useState<Estado>({ fase: 'carregando' });
    const [telaCheia, setTelaCheia] = useState(false);

    useEffect(() => {
        setMontado(true);
    }, []);

    useEffect(() => {
        const controle = new AbortController();

        setEstado({ fase: 'carregando' });

        fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', signal: controle.signal })
            .then((resposta) => (resposta.ok ? (resposta.json() as Promise<DadosMapaDecisao>) : Promise.reject(new Error(String(resposta.status)))))
            .then((dados) => setEstado(temCamadasDeContexto(dados) ? { fase: 'pronto', dados } : { fase: 'indisponivel' }))
            .catch((erro: unknown) => {
                if ((erro as { name?: string }).name !== 'AbortError') {
                    setEstado({ fase: 'indisponivel' });
                }
            });

        return () => controle.abort();
    }, [url]);

    if (estado.fase === 'indisponivel') {
        return <>{fallback}</>;
    }

    if (!montado || estado.fase === 'carregando') {
        return (
            <div className="relative" aria-busy="true">
                <MapaSkeleton />
                <p className="absolute inset-0 flex items-center justify-center text-sm text-gray-500 dark:text-gray-400">
                    Carregando zonas, vias e linhas de centralidade…
                </p>
            </div>
        );
    }

    const explicacao = explicacaoDaDecisao(estado.dados);

    return (
        <div className="space-y-3">
            <div className="relative">
                <Suspense fallback={<MapaSkeleton />}>
                    <MapaDecisao dados={estado.dados} rotuloLote={rotuloLote} altura={ALTURA} />
                </Suspense>
                <button
                    type="button"
                    onClick={() => setTelaCheia(true)}
                    className="absolute left-14 top-3 z-[1000] rounded-lg border border-gray-200 bg-white/95 px-3 py-1.5 text-xs font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900/95 dark:text-gray-200"
                >
                    Expandir mapa
                </button>
            </div>
            <div className="rounded-xl border border-gray-200 bg-gray-50 p-3 dark:border-gray-800 dark:bg-white/[0.03]">
                <p className="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Por que esta zona e esta via</p>
                <ul className="mt-2 list-disc space-y-1 pl-5 text-sm text-gray-700 dark:text-gray-300">
                    {explicacao.map((frase) => (
                        <li key={frase}>{frase}</li>
                    ))}
                </ul>
            </div>
            {estado.dados.indisponiveis.length > 0 && (
                <p className="text-sm text-warning-600 dark:text-warning-500">
                    {estado.dados.indisponiveis.length === 1 ? 'Uma camada' : `${estado.dados.indisponiveis.length} camadas`} do GeoServer da SEDUR não respondeu
                    {' '}e pode faltar no mapa.
                </p>
            )}
            <Modal isOpen={telaCheia} onClose={() => setTelaCheia(false)} className="max-w-[min(1400px,95vw)] p-5" ariaLabel="Mapa do processo">
                <div className="grid gap-4 lg:grid-cols-[1fr_320px]">
                    <Suspense fallback={<MapaSkeleton />}>
                        {telaCheia && <MapaDecisao dados={estado.dados} rotuloLote={rotuloLote} altura={Math.round(window.innerHeight * 0.78)} />}
                    </Suspense>
                    <ul className="list-disc space-y-2 pl-5 text-sm text-gray-700 lg:pt-10 dark:text-gray-300">
                        {explicacao.map((frase) => (
                            <li key={frase}>{frase}</li>
                        ))}
                    </ul>
                </div>
            </Modal>
        </div>
    );
}
