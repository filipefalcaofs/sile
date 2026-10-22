import { lazy, Suspense, useEffect, useState, type ReactNode } from 'react';

import { temCamadasDeContexto, type DadosMapaDecisao } from '@/components/geo/mapa-decisao-dados';

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
    /** Mapa simples mostrado enquanto carrega e quando não há camadas para justificar a decisão. */
    fallback: ReactNode;
}

type Estado = { fase: 'carregando' } | { fase: 'pronto'; dados: DadosMapaDecisao } | { fase: 'indisponivel' };

/**
 * Mapa do processo com zonas, vias e linhas de centralidade. Busca as camadas ao
 * montar, sem travar o detalhe: enquanto não chegam, ou se falharem, o mapa
 * simples do lote continua na tela.
 */
export function MapaDecisaoSection({ url, rotuloLote = null, fallback }: MapaDecisaoSectionProps) {
    const [montado, setMontado] = useState(false);
    const [estado, setEstado] = useState<Estado>({ fase: 'carregando' });

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

    if (!montado || estado.fase === 'indisponivel') {
        return <>{fallback}</>;
    }

    if (estado.fase === 'carregando') {
        return (
            <div className="space-y-2">
                {fallback}
                <p className="text-sm text-gray-500 dark:text-gray-400">Carregando zonas, vias e linhas de centralidade…</p>
            </div>
        );
    }

    return (
        <div className="space-y-2">
            <Suspense fallback={<MapaSkeleton />}>
                <MapaDecisao dados={estado.dados} rotuloLote={rotuloLote} altura={ALTURA} />
            </Suspense>
            {estado.dados.indisponiveis.length > 0 && (
                <p className="text-sm text-warning-600 dark:text-warning-500">
                    {estado.dados.indisponiveis.length === 1 ? 'Uma camada' : `${estado.dados.indisponiveis.length} camadas`} do GeoServer da SEDUR não respondeu
                    {' '}e pode faltar no mapa.
                </p>
            )}
        </div>
    );
}
