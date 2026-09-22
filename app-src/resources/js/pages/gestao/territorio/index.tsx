import { Head, useHttp } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import PageHeader from '@/components/app/page-header';
import { MapaSection } from '@/components/geo/mapa-section';
import Input from '@/components/form/input';
import Label from '@/components/form/label';
import Alert from '@/components/ui/alert';
import Badge from '@/components/ui/badge';
import Button from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import GestaoLayout from '@/layouts/gestao-layout';

type DimensaoStatus = 'identificado' | 'nao_encontrado' | 'indisponivel';

interface Camada {
    type: string;
    type_label: string;
    status: string;
    status_label: string;
    version: string | null;
    feature_count: number;
}

interface MapaConfig {
    centro: { lat: number; lng: number };
    zoom: number;
}

interface TerritorioProps {
    camadas: Camada[];
    sobreposicaoMinima: number;
    geocodingEnabled: boolean;
    mapa: MapaConfig;
}

/** Contrato JSON de gestao.territorio.geocodificar (04-03). */
interface GeocodeResponse {
    latitude: number;
    longitude: number;
    display_name: string;
    confidence: number | null;
    address: Record<string, unknown>;
}

/** Uma dimensão do TerritoryResult (04-05/04-06). */
interface Dimensao {
    status: DimensaoStatus;
    nome?: string | null;
    propriedades?: Record<string, unknown> | null;
    motivo?: string | null;
    versao_camada?: string | null;
    distancia_m?: number | null;
}

interface Restricoes {
    status: DimensaoStatus;
    itens?: Array<{ nome?: string | null; propriedades?: Record<string, unknown> | null }>;
    motivo?: string | null;
    versao_camada?: string | null;
}

/** Contrato JSON de gestao.territorio.identificar (04-06). */
interface Identificacao {
    bairro: Dimensao;
    via: Dimensao;
    zona: Dimensao;
    lote: Dimensao;
    restricoes: Restricoes;
}

/** Contrato JSON de gestao.territorio.validar-localizacao (04-06). */
interface Validacao {
    status: 'validado' | 'alerta_sobreposicao' | 'indisponivel';
    sobreposicao_percentual: number | null;
    limiar: number;
    motivo: string | null;
    alerta: boolean;
}

type PoligonoGeoJson = { type: 'Polygon'; coordinates: number[][][] };

interface Ponto {
    lat: number;
    lng: number;
}

/** Extrai a mensagem do backend (bag `{message}` ou erro por campo). */
function mensagemDoErro(errors: Record<string, unknown>): string | null {
    const direta = errors?.message;
    if (typeof direta === 'string') {
        return direta;
    }
    const primeiro = Object.values(errors ?? {})[0];
    if (typeof primeiro === 'string') {
        return primeiro;
    }
    if (Array.isArray(primeiro) && typeof primeiro[0] === 'string') {
        return primeiro[0];
    }
    return null;
}

/** Mensagem do backend para erros HTTP (404/503/429), com fallback por status. */
function mensagemDaExcecao(response: { status: number; data: unknown }, fallback: string): string {
    let corpo: unknown = response.data;
    if (typeof corpo === 'string') {
        try {
            corpo = JSON.parse(corpo);
        } catch {
            corpo = null;
        }
    }
    if (corpo && typeof corpo === 'object' && typeof (corpo as Record<string, unknown>).message === 'string') {
        return (corpo as Record<string, string>).message;
    }
    return fallback;
}

/**
 * Polígono mínimo (quadrado ~33 m) ao redor do ponto informado — placeholder do
 * "polígono do imóvel" nesta fase (o perímetro real chega com a base cadastral).
 * GeoJSON usa [lng, lat] (Pitfall 3).
 */
function quadradoAoRedor(lat: number, lng: number, delta = 0.0003): PoligonoGeoJson {
    return {
        type: 'Polygon',
        coordinates: [
            [
                [lng - delta, lat - delta],
                [lng + delta, lat - delta],
                [lng + delta, lat + delta],
                [lng - delta, lat + delta],
                [lng - delta, lat - delta],
            ],
        ],
    };
}

/** Linha de uma dimensão territorial: real quando identificada, honesta quando indisponível. */
function DimensaoItem({ rotulo, dimensao }: { rotulo: string; dimensao: Dimensao }) {
    return (
        <div className="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 py-3 last:border-0 dark:border-gray-800">
            <span className="text-sm font-medium text-gray-700 dark:text-gray-300">{rotulo}</span>
            <div className="text-right">
                {dimensao.status === 'identificado' && (
                    <span className="text-sm text-gray-800 dark:text-white/90">
                        {dimensao.nome ?? 'Identificado'}
                        {typeof dimensao.distancia_m === 'number' && (
                            <span className="ml-1 text-gray-500 dark:text-gray-400">
                                (a {Math.round(dimensao.distancia_m)} m)
                            </span>
                        )}
                    </span>
                )}
                {dimensao.status === 'nao_encontrado' && (
                    <span className="text-sm text-gray-500 dark:text-gray-400">Não encontrado neste ponto</span>
                )}
                {dimensao.status === 'indisponivel' && (
                    <div className="flex flex-col items-end gap-1">
                        <Badge color="warning">Indisponível</Badge>
                        <span className="max-w-xs text-xs text-gray-500 dark:text-gray-400">
                            {dimensao.motivo ?? 'Base pendente SEDUR.'}
                        </span>
                    </div>
                )}
            </div>
        </div>
    );
}

/**
 * Consulta territorial (HU-030/HU-036/HU-037) no console SEDUR: geocodifica um
 * endereço, posiciona o imóvel no mapa Leaflet (marcador arrastável), identifica
 * bairro/via/restrições reais, comunica zona/lote como pendentes SEDUR (sem valor
 * falso) e valida a sobreposição com o lote pelo limiar parametrizado. Consome os
 * endpoints reais via useHttp — nenhum resultado é simulado no front.
 */
export default function ConsultaTerritorial({ camadas, sobreposicaoMinima, geocodingEnabled, mapa }: TerritorioProps) {
    const [endereco, setEndereco] = useState('');
    const [ponto, setPonto] = useState<Ponto | null>(null);
    const [enderecoNormalizado, setEnderecoNormalizado] = useState<string | null>(null);
    const [geocodeErro, setGeocodeErro] = useState<string | null>(null);
    const [identificacao, setIdentificacao] = useState<Identificacao | null>(null);
    const [identificacaoErro, setIdentificacaoErro] = useState<string | null>(null);
    const [validacao, setValidacao] = useState<Validacao | null>(null);
    const [validacaoErro, setValidacaoErro] = useState<string | null>(null);

    const geocode = useHttp<{ address: string }, GeocodeResponse>({ address: '' });
    const identify = useHttp<{ lat: number; lng: number }, Identificacao>({ lat: 0, lng: 0 });
    const validate = useHttp<{ polygon: PoligonoGeoJson }, Validacao>({
        polygon: { type: 'Polygon', coordinates: [] },
    });

    function identificar(lat: number, lng: number) {
        setIdentificacaoErro(null);
        // transform() é síncrono: garante o envio do ponto fresco (o dataRef do
        // useHttp só atualiza no efeito pós-render).
        identify.transform(() => ({ lat, lng }));
        identify.post('/gestao/territorio/identificar', {
            onSuccess: (response) => setIdentificacao(response),
            onError: (errors) =>
                setIdentificacaoErro(mensagemDoErro(errors) ?? 'Não foi possível identificar o ponto.'),
            onHttpException: (response) => {
                setIdentificacaoErro(mensagemDaExcecao(response, 'Não foi possível identificar o ponto.'));
                return false;
            },
        });
    }

    function moverMarcador(latlng: Ponto) {
        setPonto(latlng);
        setValidacao(null);
        setValidacaoErro(null);
        identificar(latlng.lat, latlng.lng);
    }

    function localizar() {
        if (!geocodingEnabled) {
            return;
        }
        setGeocodeErro(null);
        geocode.transform(() => ({ address: endereco }));
        geocode.post('/gestao/territorio/geocodificar', {
            onSuccess: (response) => {
                const novoPonto = { lat: response.latitude, lng: response.longitude };
                setPonto(novoPonto);
                setEnderecoNormalizado(response.display_name);
                setValidacao(null);
                setValidacaoErro(null);
                identificar(novoPonto.lat, novoPonto.lng);
            },
            onError: (errors) =>
                setGeocodeErro(
                    mensagemDoErro(errors) ?? 'Não foi possível geocodificar. Posicione o ponto manualmente no mapa.',
                ),
            onHttpException: (response) => {
                setGeocodeErro(
                    mensagemDaExcecao(
                        response,
                        'Não foi possível geocodificar. Posicione o ponto manualmente no mapa.',
                    ),
                );
                return false;
            },
        });
    }

    function validar() {
        if (!ponto) {
            return;
        }
        setValidacaoErro(null);
        const polygon = quadradoAoRedor(ponto.lat, ponto.lng);
        validate.transform(() => ({ polygon }));
        validate.post('/gestao/territorio/validar-localizacao', {
            onSuccess: (response) => setValidacao(response),
            onError: (errors) =>
                setValidacaoErro(mensagemDoErro(errors) ?? 'Não foi possível validar a localização.'),
            onHttpException: (response) => {
                setValidacaoErro(mensagemDaExcecao(response, 'Não foi possível validar a localização.'));
                return false;
            },
        });
    }

    const centro = ponto ?? mapa.centro;

    return (
        <>
            <Head title="Consulta territorial" />
            <PageHeader title="Consulta territorial" breadcrumbs={[{ label: 'Painel', href: '/gestao' }]} />

            <div className="grid gap-4 md:gap-6 lg:grid-cols-3">
                <div className="flex flex-col gap-4 md:gap-6 lg:col-span-2">
                    <Card>
                        <CardHeader
                            title="Localizar imóvel"
                            description="Informe o endereço para geocodificar, ou posicione o ponto manualmente no mapa."
                        />
                        <CardContent>
                            <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
                                <div className="flex-1">
                                    <Label htmlFor="endereco">Endereço</Label>
                                    <Input
                                        id="endereco"
                                        type="text"
                                        name="endereco"
                                        value={endereco}
                                        onChange={(event) => setEndereco(event.target.value)}
                                        placeholder="Ex.: Praça da Sé, Salvador"
                                        disabled={!geocodingEnabled}
                                        hint={
                                            geocodingEnabled
                                                ? undefined
                                                : 'Geocodificação desativada — posicione o ponto manualmente no mapa.'
                                        }
                                        onKeyDown={(event) => {
                                            if (event.key === 'Enter') {
                                                event.preventDefault();
                                                localizar();
                                            }
                                        }}
                                    />
                                </div>
                                <div>
                                    <Button
                                        type="button"
                                        variant="primary"
                                        size="sm"
                                        onClick={localizar}
                                        disabled={!geocodingEnabled || geocode.processing || endereco.trim().length < 3}
                                        loading={geocode.processing}
                                    >
                                        Localizar
                                    </Button>
                                </div>
                            </div>

                            {geocodeErro && (
                                <div className="mt-4">
                                    <Alert variant="warning" title="Geocodificação" message={geocodeErro} />
                                </div>
                            )}

                            {enderecoNormalizado && !geocodeErro && (
                                <p className="mt-3 text-sm text-gray-500 dark:text-gray-400">
                                    Endereço localizado: <span className="text-gray-700 dark:text-gray-300">{enderecoNormalizado}</span>
                                </p>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader
                            title="Mapa do imóvel"
                            description="Arraste o marcador ou clique no mapa para ajustar a localização — a identificação roda automaticamente."
                        />
                        <CardContent>
                            <MapaSection lat={centro.lat} lng={centro.lng} zoom={mapa.zoom} onMove={moverMarcador} />
                            <p className="mt-3 text-xs text-gray-500 dark:text-gray-400">
                                Mapa &copy; OpenStreetMap, dados sob licença ODbL.
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader
                            title="Identificação"
                            description="Bairro, via e restrições vêm das camadas oficiais carregadas. Zona e lote ficam indisponíveis enquanto a base estiver pendente SEDUR."
                        />
                        <CardContent>
                            {identify.processing && (
                                <div className="flex flex-col gap-2">
                                    <div className="h-4 w-3/4 animate-pulse rounded bg-gray-100 dark:bg-white/[0.06]" />
                                    <div className="h-4 w-1/2 animate-pulse rounded bg-gray-100 dark:bg-white/[0.06]" />
                                </div>
                            )}

                            {!identify.processing && identificacaoErro && (
                                <Alert variant="error" title="Identificação" message={identificacaoErro} />
                            )}

                            {!identify.processing && !identificacaoErro && !identificacao && (
                                <p className="text-sm text-gray-500 dark:text-gray-400">
                                    Localize um endereço ou posicione o marcador no mapa para identificar o território.
                                </p>
                            )}

                            {!identify.processing && !identificacaoErro && identificacao && (
                                <div className="flex flex-col">
                                    <DimensaoItem rotulo="Bairro" dimensao={identificacao.bairro} />
                                    <DimensaoItem rotulo="Via mais próxima" dimensao={identificacao.via} />
                                    <DimensaoItem rotulo="Zona urbanística" dimensao={identificacao.zona} />
                                    <DimensaoItem rotulo="Lote" dimensao={identificacao.lote} />
                                    <div className="flex flex-wrap items-start justify-between gap-2 py-3">
                                        <span className="text-sm font-medium text-gray-700 dark:text-gray-300">
                                            Restrições
                                        </span>
                                        <div className="text-right">
                                            {identificacao.restricoes.status === 'identificado' &&
                                            identificacao.restricoes.itens &&
                                            identificacao.restricoes.itens.length > 0 ? (
                                                <ul className="flex flex-col items-end gap-1">
                                                    {identificacao.restricoes.itens.map((item, indice) => (
                                                        <li
                                                            key={item.nome ?? indice}
                                                            className="text-sm text-gray-800 dark:text-white/90"
                                                        >
                                                            {item.nome ?? 'Restrição'}
                                                        </li>
                                                    ))}
                                                </ul>
                                            ) : identificacao.restricoes.status === 'indisponivel' ? (
                                                <div className="flex flex-col items-end gap-1">
                                                    <Badge color="warning">Indisponível</Badge>
                                                    <span className="max-w-xs text-xs text-gray-500 dark:text-gray-400">
                                                        {identificacao.restricoes.motivo ?? 'Base pendente SEDUR.'}
                                                    </span>
                                                </div>
                                            ) : (
                                                <span className="text-sm text-gray-500 dark:text-gray-400">
                                                    Nenhuma restrição neste ponto
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader
                            title="Validar localização"
                            description={`Compara o perímetro do imóvel com o lote oficial; alerta abaixo de ${sobreposicaoMinima}% de sobreposição (limiar parametrizado).`}
                            actions={
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={validar}
                                    disabled={!ponto || validate.processing}
                                    loading={validate.processing}
                                >
                                    Validar localização
                                </Button>
                            }
                        />
                        <CardContent>
                            {!ponto && (
                                <p className="text-sm text-gray-500 dark:text-gray-400">
                                    Posicione o imóvel no mapa para validar a sobreposição com o lote.
                                </p>
                            )}

                            {validacaoErro && (
                                <Alert variant="error" title="Validação" message={validacaoErro} />
                            )}

                            {validacao && !validacaoErro && validacao.status === 'validado' && (
                                <Alert
                                    variant="success"
                                    title="Localização validada"
                                    message={`Sobreposição de ${validacao.sobreposicao_percentual ?? 0}% com o lote (limiar de ${validacao.limiar}%).`}
                                />
                            )}

                            {validacao && !validacaoErro && validacao.status === 'alerta_sobreposicao' && (
                                <Alert
                                    variant="warning"
                                    title="Baixa sobreposição com o lote"
                                    message={`Sobreposição de ${validacao.sobreposicao_percentual ?? 0}%, abaixo do limiar de ${validacao.limiar}%. Confira a localização do imóvel.`}
                                />
                            )}

                            {validacao && !validacaoErro && validacao.status === 'indisponivel' && (
                                <Alert
                                    variant="info"
                                    title="Validação indisponível"
                                    message={validacao.motivo ?? 'Base de lotes pendente SEDUR — validação de sobreposição indisponível.'}
                                />
                            )}
                        </CardContent>
                    </Card>
                </div>

                <div className="lg:col-span-1">
                    <Card>
                        <CardHeader title="Camadas" description="Camadas territoriais vigentes e sua situação." />
                        <CardContent>
                            {camadas.length === 0 ? (
                                <p className="text-sm text-gray-500 dark:text-gray-400">
                                    Nenhuma camada vigente carregada.
                                </p>
                            ) : (
                                <ul className="flex flex-col gap-3">
                                    {camadas.map((camada) => {
                                        const pendente = camada.status === 'pendente_fonte';
                                        return (
                                            <li
                                                key={`${camada.type}-${camada.version ?? 'sem-versao'}`}
                                                className="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 pb-3 last:border-0 last:pb-0 dark:border-gray-800"
                                            >
                                                <div>
                                                    <p className="text-sm font-medium text-gray-800 dark:text-white/90">
                                                        {camada.type_label}
                                                    </p>
                                                    <p className="text-xs text-gray-500 dark:text-gray-400">
                                                        {pendente
                                                            ? 'Sem base pública — aguardando a SEDUR.'
                                                            : `${camada.feature_count} feição(ões)${camada.version ? ` · ${camada.version}` : ''}`}
                                                    </p>
                                                </div>
                                                {pendente ? (
                                                    <Badge color="warning">Pendente SEDUR</Badge>
                                                ) : (
                                                    <Badge color="success">{camada.status_label}</Badge>
                                                )}
                                            </li>
                                        );
                                    })}
                                </ul>
                            )}
                            <p className="mt-4 text-xs text-gray-500 dark:text-gray-400">
                                Zona urbanística e lote cadastral seguem indisponíveis enquanto a base estiver pendente
                                SEDUR — nenhum valor é exibido sem dado oficial.
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

ConsultaTerritorial.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
