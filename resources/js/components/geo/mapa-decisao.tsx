/// <reference types="vite/client" />
import 'leaflet/dist/leaflet.css';
import L from 'leaflet';
import type { Geometry } from 'geojson';
import type { PathOptions } from 'leaflet';
import { Fragment, useEffect, useId, useMemo, useRef, useState, type ReactNode } from 'react';
import { CircleMarker, GeoJSON, MapContainer, Pane, TileLayer, Tooltip, useMap } from 'react-leaflet';

import { chaveDaGeometria } from './geometria';
import {
    type DadosMapaDecisao,
    loteDoMapa,
    papelDaPorcao,
    rotuloDaLinha,
    rotuloDaPorcao,
    rotuloDaZonaDoLote,
    formatarArea,
} from './mapa-decisao-dados';

type Fundo = 'claro' | 'satelite' | 'nenhum';

const FUNDOS: { id: Fundo; rotulo: string }[] = [
    { id: 'claro', rotulo: 'Mapa claro' },
    { id: 'satelite', rotulo: 'Satélite' },
    { id: 'nenhum', rotulo: 'Sem mapa de fundo' },
];

const TILES: Record<Exclude<Fundo, 'nenhum'>, { url: string; maxNativeZoom: number }> = {
    claro: {
        url: 'https://server.arcgisonline.com/ArcGIS/rest/services/Canvas/World_Light_Gray_Base/MapServer/tile/{z}/{y}/{x}',
        maxNativeZoom: 16,
    },
    satelite: {
        url: 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
        maxNativeZoom: 19,
    },
};

const COR = {
    lote: '#175cd3',
    zona: '#6941c6',
    menor: '#039855',
    via: '#f79009',
    zclmu: '#d92d20',
    zclme: '#7a271a',
};

interface Camadas {
    lote: boolean;
    zonaDoLote: boolean;
    outrasZonas: boolean;
    vias: boolean;
    linhas: boolean;
}

const ESTILO_ROTULOS = `
.mapa-decisao svg { max-width:none !important; max-height:none !important; }
.mapa-decisao-rotulo { background:#fff; border:0; border-radius:6px; box-shadow:0 1px 4px rgba(16,24,40,.25); color:#1d2939; font-size:11px; font-weight:600; line-height:1.2; padding:3px 7px; white-space:nowrap; max-width:240px; overflow:hidden; text-overflow:ellipsis; }
.mapa-decisao-rotulo::before { display:none; }
.mapa-decisao-rotulo-lote { color:#175cd3; }
.mapa-decisao-rotulo-zona { color:#53389e; }
.mapa-decisao-rotulo-menor { color:#027a48; }
.mapa-decisao-rotulo-medida { color:#b42318; }
.mapa-decisao-rotulo-via { color:#b54708; }
`;

function centroDe(geometria: Geometry, ancora: 'centro' | 'sul' = 'centro'): [number, number] | null {
    const limites = L.geoJSON(geometria as never).getBounds();

    if (!limites.isValid()) {
        return null;
    }

    const centro = limites.getCenter();

    return ancora === 'sul' ? [limites.getSouth(), centro.lng] : [centro.lat, centro.lng];
}

/** Texto permanente sobre um ponto da geometria (um círculo invisível carrega o rótulo). */
function Rotulo({
    geometria,
    texto,
    classe,
    direcao = 'center',
    deslocamento,
    ancora = 'centro',
}: {
    geometria: Geometry;
    texto: string;
    classe: string;
    direcao?: 'center' | 'top' | 'bottom';
    deslocamento?: [number, number];
    ancora?: 'centro' | 'sul';
}) {
    const centro = useMemo(() => centroDe(geometria, ancora), [geometria, ancora]);

    if (!centro) {
        return null;
    }

    return (
        <CircleMarker center={centro} radius={1} pathOptions={{ opacity: 0, fillOpacity: 0 }} interactive={false}>
            <Tooltip permanent direction={direcao} offset={deslocamento ?? [0, 0]} className={`mapa-decisao-rotulo ${classe}`} opacity={1}>
                {texto}
            </Tooltip>
        </CircleMarker>
    );
}

function Camada({ geometria, estilo, nome }: { geometria: Geometry; estilo: PathOptions; nome: string }) {
    return <GeoJSON key={`${nome}:${chaveDaGeometria(geometria)}`} data={geometria as never} style={() => estilo} interactive={false} />;
}

/** Enquadra o lote e, junto, a ligação até a linha de centralidade mais próxima. */
function Enquadrar({ geometrias, ponto }: { geometrias: Geometry[]; ponto: { lat: number; lng: number } | null }) {
    const map = useMap();
    const chave = geometrias.map(chaveDaGeometria).join('|') + JSON.stringify(ponto);

    useEffect(() => {
        const limites = L.latLngBounds([]);

        geometrias.forEach((geometria) => limites.extend(L.geoJSON(geometria as never).getBounds()));

        if (!limites.isValid() && ponto) {
            map.setView([ponto.lat, ponto.lng], 18);

            return;
        }

        if (limites.isValid()) {
            // Contexto mínimo (~90 m em volta): lote pequeno não pode encher a tela e esconder zonas e linhas.
            const centro = limites.getCenter();
            limites.extend([centro.lat - 0.0008, centro.lng - 0.0008]);
            limites.extend([centro.lat + 0.0008, centro.lng + 0.0008]);
            map.fitBounds(limites, { padding: [40, 40], maxZoom: 19 });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [map, chave]);

    return null;
}

function Escala() {
    const map = useMap();

    useEffect(() => {
        const controle = L.control.scale({ imperial: false, position: 'bottomleft' }).addTo(map);

        return () => {
            controle.remove();
        };
    }, [map]);

    return null;
}

function Redimensionar() {
    const map = useMap();

    useEffect(() => {
        const observador = new ResizeObserver(() => map.invalidateSize());
        observador.observe(map.getContainer());

        return () => observador.disconnect();
    }, [map]);

    return null;
}

function Caixa({ children, className = '' }: { children: ReactNode; className?: string }) {
    return (
        <div
            className={`rounded-lg border border-gray-200 bg-white/95 p-3 text-xs text-gray-700 shadow-sm dark:border-gray-700 dark:bg-gray-900/95 dark:text-gray-200 ${className}`}
        >
            {children}
        </div>
    );
}

function Amostra({ cor, tipo }: { cor: string; tipo: 'area' | 'linha' | 'tracejada' | 'pontilhada' }) {
    if (tipo === 'area') {
        return <span className="inline-block h-3 w-5 rounded-sm border-2" style={{ borderColor: cor, backgroundColor: `${cor}33` }} />;
    }

    if (tipo === 'pontilhada') {
        return <span className="inline-block h-3 w-5 rounded-sm border border-dotted border-gray-400" />;
    }

    return (
        <span
            className="inline-block w-5 border-t-[3px]"
            style={{ borderColor: cor, borderTopStyle: tipo === 'tracejada' ? 'dashed' : 'solid' }}
        />
    );
}

export interface MapaDecisaoProps {
    dados: DadosMapaDecisao;
    /** Endereço do lote, para o rótulo sobre o polígono. */
    rotuloLote?: string | null;
    altura?: number;
}

/**
 * Mapa que justifica a zona e a via do processo: o lote, as zonas em volta (com a
 * parte do lote em cada uma), as linhas de centralidade (ZCLMu e ZCLMe com a
 * distância até o lote) e as vias com a escolhida em destaque. Só desenha o que o
 * servidor já usa na decisão. Montar apenas no cliente (ver `MapaDecisaoSection`).
 */
export function MapaDecisao({ dados, rotuloLote = null, altura = 480 }: MapaDecisaoProps) {
    const grupoFundo = useId();
    const [fundo, setFundo] = useState<Fundo>('claro');
    const raiz = useRef<HTMLDivElement>(null);
    const [painel, setPainel] = useState(true);
    const [camadas, setCamadas] = useState<Camadas>({ lote: true, zonaDoLote: true, outrasZonas: true, vias: true, linhas: true });

    const lote = loteDoMapa(dados);
    const maisProxima = dados.linhas
        .filter((linha) => linha.ligacao)
        .sort((a, b) => (a.distancia_m ?? Infinity) - (b.distancia_m ?? Infinity))[0];

    const codigosDoLote = new Set([...dados.porcoes.map((porcao) => porcao.zona), ...(dados.zona ? [dados.zona.codigo] : [])]);
    const zonasDoLote = dados.zonas.filter((zona) => codigosDoLote.has(zona.codigo));
    const outrasZonas = dados.zonas.filter((zona) => !codigosDoLote.has(zona.codigo));
    const menor = dados.porcoes.find((porcao) => papelDaPorcao(dados.porcoes, porcao) === 'menor');
    const codigoMenor = dados.porcoes.length > 1 ? dados.porcoes.filter((porcao) => papelDaPorcao(dados.porcoes, porcao) === 'menor').map((porcao) => porcao.zona) : [];
    const viaDaDecisao = dados.vias.find((via) => via.da_decisao);
    const maior = dados.porcoes.find((porcao) => papelDaPorcao(dados.porcoes, porcao) === 'maior');
    const rotuloZona = rotuloDaZonaDoLote(dados);
    const tituloZona = dados.porcoes.length > 1 ? `Zonas do lote (${dados.porcoes.map((p) => p.zona).join(' e ')})` : `Zona do lote${dados.zona ? ` (${dados.zona.codigo})` : ''}`;

    useEffect(() => {
        // Em coluna estreita o painel e a legenda cobririam o lote: começam recolhidos.
        if (raiz.current && raiz.current.clientWidth < 720) {
            setPainel(false);
        }
    }, []);

    const alternar = (chave: keyof Camadas) => setCamadas((atual) => ({ ...atual, [chave]: !atual[chave] }));

    const corDaZona = (codigo: string) => (codigoMenor.includes(codigo) ? COR.menor : COR.zona);
    const ordenadas = (lista: typeof dados.zonas) => [...lista].sort((a, b) => Number(a.da_decisao) - Number(b.da_decisao));
    const centroLote = lote ? centroDe(lote) : null;
    const enderecoCurto = rotuloLote && rotuloLote.length > 36 ? `${rotuloLote.slice(0, 35).trimEnd()}…` : rotuloLote;
    const rotuloDoLote = [enderecoCurto, dados.area_lote_m2 ? formatarArea(dados.area_lote_m2) : null].filter(Boolean).join(' · ') || 'Lote do processo';

    return (
        <div ref={raiz} className="mapa-decisao relative">
            <style>{ESTILO_ROTULOS}</style>
            <MapContainer
                center={centroLote ?? (dados.ponto ? [dados.ponto.lat, dados.ponto.lng] : [-12.97, -38.5])}
                zoom={18}
                maxZoom={20}
                scrollWheelZoom
                className="relative z-0 w-full overflow-hidden rounded-2xl border border-gray-200 dark:border-gray-800"
                style={{ height: altura }}
            >
                {fundo !== 'nenhum' && (
                    <TileLayer
                        key={fundo}
                        url={TILES[fundo].url}
                        maxNativeZoom={TILES[fundo].maxNativeZoom}
                        maxZoom={20}
                        attribution="Mapa &copy; Esri"
                    />
                )}
                <Enquadrar
                    geometrias={[...(lote ? [lote] : []), ...(maisProxima?.ligacao ? [maisProxima.ligacao] : [])]}
                    ponto={dados.ponto}
                />
                <Redimensionar />
                <Escala />

                {camadas.outrasZonas && (
                    <Pane name="mapa-outras-zonas" style={{ zIndex: 410 }}>
                        {outrasZonas.map((zona, indice) => (
                            <Camada
                                key={`${zona.codigo}-${indice}`}
                                nome={`outra-${zona.codigo}-${indice}`}
                                geometria={zona.geojson}
                                estilo={{ color: '#98a2b3', weight: 1, dashArray: '2 4', fillColor: '#d0d5dd', fillOpacity: 0.12 }}
                            />
                        ))}
                        {outrasZonas.map((zona, indice) => (
                            <Rotulo key={`r-${zona.codigo}-${indice}`} geometria={zona.geojson} texto={zona.codigo} classe="" />
                        ))}
                    </Pane>
                )}

                {camadas.zonaDoLote && (
                    <Pane name="mapa-zona-lote" style={{ zIndex: 420 }}>
                        {ordenadas(zonasDoLote).map((zona, indice) => (
                            <Camada
                                key={`${zona.codigo}-${indice}`}
                                nome={`zona-${zona.codigo}-${indice}`}
                                geometria={zona.geojson}
                                estilo={{ color: corDaZona(zona.codigo), weight: 2, fillColor: corDaZona(zona.codigo), fillOpacity: 0.1 }}
                            />
                        ))}
                        {dados.porcoes.length > 1 &&
                            dados.porcoes.map((porcao) => (
                                <Camada
                                    key={`porcao-${porcao.zona}`}
                                    nome={`porcao-${porcao.zona}`}
                                    geometria={porcao.geojson}
                                    estilo={{
                                        color: corDaZona(porcao.zona),
                                        weight: 2,
                                        fillColor: corDaZona(porcao.zona),
                                        fillOpacity: 0.45,
                                    }}
                                />
                            ))}
                        {dados.porcoes.length > 1
                            ? dados.porcoes.map((porcao) => (
                                  <Rotulo
                                      key={`rp-${porcao.zona}`}
                                      geometria={porcao.geojson}
                                      texto={rotuloDaPorcao(dados.porcoes, porcao)}
                                      classe={codigoMenor.includes(porcao.zona) ? 'mapa-decisao-rotulo-menor' : 'mapa-decisao-rotulo-zona'}
                                  />
                              ))
                            : null}
                        {rotuloZona && zonasDoLote.find((zona) => zona.da_decisao) && (
                            <Rotulo
                                geometria={zonasDoLote.find((zona) => zona.da_decisao)!.geojson}
                                texto={rotuloZona}
                                classe="mapa-decisao-rotulo-zona"
                                direcao="top"
                                deslocamento={[0, -22]}
                            />
                        )}
                        {menor && zonasDoLote.find((zona) => zona.codigo === menor.zona) && (
                            <Rotulo
                                geometria={zonasDoLote.find((zona) => zona.codigo === menor.zona)!.geojson}
                                texto={`${menor.zona} · menor porção`}
                                classe="mapa-decisao-rotulo-menor"
                                direcao="top"
                                deslocamento={[0, -22]}
                            />
                        )}
                    </Pane>
                )}

                {camadas.vias && (
                    <Pane name="mapa-vias" style={{ zIndex: 430 }}>
                        {dados.vias.map((via, indice) => (
                            <Camada
                                key={`${via.cod_log}-${indice}`}
                                nome={`via-${via.cod_log}-${indice}`}
                                geometria={via.geojson}
                                estilo={{ color: COR.via, weight: via.da_decisao ? 5 : 3, opacity: via.da_decisao ? 1 : 0.7 }}
                            />
                        ))}
                        {viaDaDecisao && (
                            <Rotulo
                                geometria={viaDaDecisao.geojson}
                                texto={[viaDaDecisao.nome ?? 'Via da decisão', viaDaDecisao.classe].filter(Boolean).join(' · ')}
                                classe="mapa-decisao-rotulo-via"
                                direcao="top"
                                deslocamento={[0, -4]}
                            />
                        )}
                    </Pane>
                )}

                {camadas.linhas && (
                    <Pane name="mapa-linhas" style={{ zIndex: 440 }}>
                        {dados.linhas.map((linha, indice) => (
                            <Camada
                                key={`${linha.tipo}-${indice}`}
                                nome={`linha-${linha.tipo}-${indice}`}
                                geometria={linha.geojson}
                                estilo={{
                                    color: linha.tipo === 'ZCLMu' ? COR.zclmu : COR.zclme,
                                    weight: 4,
                                    dashArray: linha.tipo === 'ZCLMe' ? '8 6' : undefined,
                                }}
                            />
                        ))}
                        {dados.linhas.map((linha, indice) => (
                            <Rotulo
                                key={`rl-${indice}`}
                                geometria={linha.geojson}
                                texto={`${linha.tipo}${linha.nome ? ` · ${linha.nome}` : ''}`}
                                classe="mapa-decisao-rotulo-medida"
                                direcao="bottom"
                                deslocamento={[0, 4]}
                            />
                        ))}
                        {dados.linhas.map((linha, indice) => {
                            const texto = rotuloDaLinha(linha);

                            if (!texto) {
                                return null;
                            }

                            return (
                                <Fragment key={`m-${indice}`}>
                                    {linha.ligacao && !linha.passa_sobre_lote && (
                                        <Camada
                                            nome={`ligacao-${indice}`}
                                            geometria={linha.ligacao}
                                            estilo={{ color: COR.zclmu, weight: 2, dashArray: '3 4' }}
                                        />
                                    )}
                                    <Rotulo
                                        geometria={linha.passa_sobre_lote || !linha.ligacao ? (lote ?? linha.geojson) : linha.ligacao}
                                        texto={texto}
                                        classe="mapa-decisao-rotulo-medida"
                                        ancora={linha.passa_sobre_lote || !linha.ligacao ? 'sul' : 'centro'}
                                        direcao={linha.passa_sobre_lote || !linha.ligacao ? 'bottom' : 'center'}
                                        deslocamento={linha.passa_sobre_lote || !linha.ligacao ? [0, 32] : undefined}
                                    />
                                </Fragment>
                            );
                        })}
                    </Pane>
                )}

                {camadas.lote && lote && (
                    <Pane name="mapa-lote" style={{ zIndex: 450 }}>
                        <Camada
                            nome="lote"
                            geometria={lote}
                            estilo={{ color: COR.lote, weight: 3, fillColor: COR.lote, fillOpacity: 0.25 }}
                        />
                        <Rotulo geometria={lote} texto={rotuloDoLote} classe="mapa-decisao-rotulo-lote" ancora="sul" direcao="bottom" deslocamento={[0, 6]} />
                    </Pane>
                )}
            </MapContainer>

            <button
                type="button"
                onClick={() => setPainel((aberto) => !aberto)}
                className="absolute right-3 top-3 z-[1000] rounded-lg border border-gray-200 bg-white/95 px-3 py-1.5 text-xs font-medium text-gray-700 shadow-sm dark:border-gray-700 dark:bg-gray-900/95 dark:text-gray-200"
            >
                {painel ? 'Ocultar camadas' : 'Camadas e legenda'}
            </button>

            {painel && (
                <>
                <Caixa className="absolute right-3 top-12 z-[1000] w-56 space-y-1">
                    {FUNDOS.map((opcao) => (
                        <label key={opcao.id} className="flex cursor-pointer items-center gap-2">
                            <input type="radio" name={grupoFundo} checked={fundo === opcao.id} onChange={() => setFundo(opcao.id)} />
                            {opcao.rotulo}
                        </label>
                    ))}
                    <hr className="my-2 border-gray-200 dark:border-gray-700" />
                    {lote && (
                        <label className="flex cursor-pointer items-center gap-2">
                            <input type="checkbox" checked={camadas.lote} onChange={() => alternar('lote')} />
                            Lote do processo
                        </label>
                    )}
                    {zonasDoLote.length > 0 && (
                        <label className="flex cursor-pointer items-center gap-2">
                            <input type="checkbox" checked={camadas.zonaDoLote} onChange={() => alternar('zonaDoLote')} />
                            {tituloZona}
                        </label>
                    )}
                    {outrasZonas.length > 0 && (
                        <label className="flex cursor-pointer items-center gap-2">
                            <input type="checkbox" checked={camadas.outrasZonas} onChange={() => alternar('outrasZonas')} />
                            Outras zonas
                        </label>
                    )}
                    {dados.vias.length > 0 && (
                        <label className="flex cursor-pointer items-center gap-2">
                            <input type="checkbox" checked={camadas.vias} onChange={() => alternar('vias')} />
                            Vias
                        </label>
                    )}
                    {dados.linhas.length > 0 && (
                        <label className="flex cursor-pointer items-center gap-2">
                            <input type="checkbox" checked={camadas.linhas} onChange={() => alternar('linhas')} />
                            ZCLMu e ZCLMe (linhas)
                        </label>
                    )}
                </Caixa>

                <Caixa className="absolute bottom-10 left-3 z-[1000] space-y-1">
                    {lote && (
                        <div className="flex items-center gap-2">
                            <Amostra cor={COR.lote} tipo="area" /> Lote
                        </div>
                    )}
                    {dados.porcoes.length > 1 ? (
                        <>
                            {maior && (
                                <div className="flex items-center gap-2">
                                    <Amostra cor={COR.zona} tipo="area" /> {maior.zona} (maior porção)
                                </div>
                            )}
                            {menor && (
                                <div className="flex items-center gap-2">
                                    <Amostra cor={COR.menor} tipo="area" /> {menor.zona} (menor porção)
                                </div>
                            )}
                        </>
                    ) : (
                        zonasDoLote.length > 0 && (
                            <div className="flex items-center gap-2">
                                <Amostra cor={COR.zona} tipo="area" /> Zona do lote{dados.zona ? ` (${dados.zona.codigo})` : ''}
                            </div>
                        )
                    )}
                    {outrasZonas.length > 0 && (
                        <div className="flex items-center gap-2">
                            <Amostra cor="#98a2b3" tipo="pontilhada" /> Outras zonas
                        </div>
                    )}
                    {dados.vias.length > 0 && (
                        <div className="flex items-center gap-2">
                            <Amostra cor={COR.via} tipo="linha" /> Via
                        </div>
                    )}
                    {dados.linhas.some((linha) => linha.tipo === 'ZCLMu') && (
                        <div className="flex items-center gap-2">
                            <Amostra cor={COR.zclmu} tipo="linha" /> ZCLMu (linha)
                        </div>
                    )}
                    {dados.linhas.some((linha) => linha.tipo === 'ZCLMe') && (
                        <div className="flex items-center gap-2">
                            <Amostra cor={COR.zclme} tipo="tracejada" /> ZCLMe (linha)
                        </div>
                    )}
                </Caixa>
                </>
            )}
        </div>
    );
}
