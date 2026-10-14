import { Head, Link, router, usePage } from '@inertiajs/react';
import type { MultiPolygon, Polygon } from 'geojson';
import type { ReactNode } from 'react';
import { useState } from 'react';
import ConclusaoAnaliseForm from '@/components/analise/conclusao-analise-form';
import BaixarTvl from '@/components/analise/baixar-tvl';
import {
    CategoriaBadges,
    formatarDataHora,
    type ProcessoItem,
    RecepcaoRecebida,
    SemaforoBadge,
} from '@/components/analise/processo-ui';
import DecisionExplanation, { type DecisionExplanationData } from '@/components/auditoria/decision-explanation';
import PageHeader from '@/components/app/page-header';
import ParecerCard, { type ParecerDecisao } from '@/components/decisao/parecer-card';
import CamadasImovelLista, { type CamadasImovel } from '@/components/geo/camadas-imovel';
import { centroDaGeometria, ESTILO_ENDERECAMENTO } from '@/components/geo/geometria';
import { MapaSection } from '@/components/geo/mapa-section';
import OptionSelect from '@/components/form/option-select';
import SearchableSelect from '@/components/form/searchable-select';
import Select from '@/components/form/select';
import { AlertIcon, ArrowRightIcon, MapPinIcon } from '@/components/icons';
import Alert from '@/components/ui/alert';
import Badge from '@/components/ui/badge';
import Button from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import EmptyState from '@/components/ui/empty-state';
import GestaoLayout from '@/layouts/gestao-layout';
import type { SharedProps } from '@/types';

interface TimelineItem {
    from: string | null;
    from_label: string | null;
    to: string;
    to_label: string;
    reason: string | null;
    em: string | null;
    ator?: string | null;
    atribuido?: string | null;
    distribuidor?: string | null;
}

interface SetorEncaminhamento {
    id: number;
    name: string;
}

interface AnalistaEncaminhamento {
    id: number;
    name: string;
    setores: number[];
}

interface ShowProps {
    processo: ProcessoItem;
    /** Parecer da decisão (resultado, motivo e fundamentação); null sem decisão. */
    parecer: ParecerDecisao | null;
    timeline: TimelineItem[];
    geo: {
        poligono: Polygon | null;
        poligono_enderecamento?: Polygon | MultiPolygon | null;
        ponto: { lat: number; lng: number } | null;
        origem: { value: string; label: string } | null;
        camadas: CamadasImovel | null;
    };
    /** Logradouro declarado × logradouro da inscrição (SEFAZ); null quando coincidem ou falta dado. */
    divergencia_endereco?: { declarado: string; sefaz: string } | null;
    /** Explicabilidade passo a passo (HU-099); null quando não há decisão. */
    explicacao?: DecisionExplanationData | null;
    /** Próximas transições manuais oferecidas ao analista (Tarefa 10). */
    analysisStatusProximas: { value: string; label: string }[];
    conclusaoOpcoes: { value: string; label: string }[];
}

type Aba = 'informacoes' | 'tramitacao' | 'explicabilidade';

/** Centroide do anel exterior do polígono (média dos vértices em [lng, lat]). */
function centroide(poligono: Polygon | null): { lat: number; lng: number } | null {
    const anel = poligono?.coordinates?.[0];

    if (!anel || anel.length < 3) {
        return null;
    }

    const fechado =
        anel[0][0] === anel[anel.length - 1][0] && anel[0][1] === anel[anel.length - 1][1];
    const pontos = fechado ? anel.slice(0, -1) : anel;

    if (pontos.some(([lng, lat]) => !Number.isFinite(lng) || !Number.isFinite(lat))) {
        return null;
    }

    const soma = pontos.reduce(
        (acumulado, [lng, lat]) => ({ lng: acumulado.lng + lng, lat: acumulado.lat + lat }),
        { lng: 0, lat: 0 },
    );

    return { lat: soma.lat / pontos.length, lng: soma.lng / pontos.length };
}

/** Duração humana entre dois instantes (etapas da timeline). */
function formatarDuracao(inicioIso: string | null, fimIso: string | null): string | null {
    if (!inicioIso) {
        return null;
    }

    const inicio = new Date(inicioIso).getTime();
    const fim = fimIso ? new Date(fimIso).getTime() : Date.now();

    if (Number.isNaN(inicio) || Number.isNaN(fim) || fim < inicio) {
        return null;
    }

    const minutos = Math.round((fim - inicio) / 60000);

    if (minutos < 60) {
        return `${minutos} min`;
    }

    const horas = Math.round(minutos / 60);

    if (horas < 48) {
        return `${horas} h`;
    }

    return `${Math.round(horas / 24)} dias`;
}

function DescItem({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div>
            <dt className="text-theme-xs font-medium tracking-wide text-gray-400 uppercase dark:text-gray-500">
                {label}
            </dt>
            <dd className="mt-1 text-theme-sm text-gray-800 dark:text-white/90">{children}</dd>
        </div>
    );
}

export default function Show({
    processo,
    parecer,
    timeline,
    geo,
    divergencia_endereco = null,
    explicacao,
    analysisStatusProximas,
    conclusaoOpcoes,
    setoresEncaminhamento = [],
    analistasEncaminhamento = [],
}: ShowProps & {
    setoresEncaminhamento?: SetorEncaminhamento[];
    analistasEncaminhamento?: AnalistaEncaminhamento[];
}) {
    const { auth } = usePage<SharedProps>().props;
    const podeAnalisar = auth.permissions.includes('analisar-processos');
    const fichaExpresso = processo.fluxo === 'expresso';
    const podeAbrirFicha = podeAnalisar || fichaExpresso;
    const podeConcluirAnalise = processo.status === 'em_analise' || processo.status === 'deferida';

    const [aba, setAba] = useState<Aba>('informacoes');
    const [resultadoConclusao, setResultadoConclusao] = useState('');
    const [conclusaoProcessing, setConclusaoProcessing] = useState(false);
    const [erroConclusao, setErroConclusao] = useState<string | null>(null);
    const [situacaoAnalise, setSituacaoAnalise] = useState('');
    const [setorEncaminhamento, setSetorEncaminhamento] = useState('');
    const [analistaEncaminhamento, setAnalistaEncaminhamento] = useState('');

    function concluirAnalise() {
        setErroConclusao(null);
        router.post(
            `/gestao/processos/${processo.id}/conclusao-analise`,
            { resultado: resultadoConclusao },
            {
                preserveScroll: true,
                onStart: () => setConclusaoProcessing(true),
                onFinish: () => setConclusaoProcessing(false),
                onSuccess: () => setResultadoConclusao(''),
                onError: (erros) => {
                    const mapa = erros as Record<string, string>;
                    setErroConclusao(mapa.conclusao ?? mapa.resultado ?? 'Não foi possível concluir a análise.');
                },
            },
        );
    }

    // A aba de explicabilidade só existe quando há decisão (prop presente).
    const abas: { value: Aba; label: string }[] = [
        { value: 'informacoes', label: 'Informações do processo' },
        { value: 'tramitacao', label: 'Tramitação' },
        ...(explicacao ? [{ value: 'explicabilidade' as const, label: 'Explicabilidade' }] : []),
    ];

    const centroCalculado = centroide(geo.poligono) ?? centroDaGeometria(geo.poligono_enderecamento) ?? geo.ponto;
    const centro =
        centroCalculado && Number.isFinite(centroCalculado.lat) && Number.isFinite(centroCalculado.lng)
            ? centroCalculado
            : null;

    // A timeline vem do backend do mais recente para o mais antigo; exibimos em
    // ordem cronológica (mais antigo primeiro) para a leitura do fluxo, com a
    // duração de cada etapa calculada a partir do evento seguinte.
    const eventos = [...timeline].reverse();

    return (
        <>
            <Head title={`Processo ${processo.protocol_number ?? ''}`.trim()} />
            <PageHeader
                title={processo.protocol_number ?? 'Processo'}
                breadcrumbs={[
                    { label: 'Painel', href: '/gestao' },
                    { label: 'Processos', href: '/gestao/processos' },
                ]}
            />

            <div className="space-y-6">
                <Link
                    href="/gestao/processos"
                    className="inline-flex items-center gap-1.5 text-theme-sm font-medium text-brand-500 transition hover:text-brand-600 dark:text-brand-400"
                >
                    <ArrowRightIcon className="size-4 rotate-180" />
                    Voltar à consulta
                </Link>

                {/* Cabeçalho com os três identificadores (RN-007) */}
                <Card>
                    <CardContent className="border-t-0">
                        <div className="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                            <div className="space-y-3">
                                <div className="flex flex-wrap items-center gap-2">
                                    <Badge color="light" size="sm">
                                        {processo.status_label}
                                    </Badge>
                                    <Badge color={processo.origin === 'contingencia' ? 'warning' : 'light'} size="sm">
                                        {processo.origin_label}
                                    </Badge>
                                    <CategoriaBadges categorias={processo.categorias} />
                                </div>
                                {processo.sem_decisao_automatica && (
                                    <div
                                        role="status"
                                        className="flex items-start gap-2 rounded-lg border border-warning-200 bg-warning-50 p-3 dark:border-warning-500/30 dark:bg-warning-500/15"
                                    >
                                        <AlertIcon className="mt-0.5 size-4 shrink-0 fill-current text-warning-500" />
                                        <p className="text-theme-sm text-gray-700 dark:text-gray-200">
                                            {processo.motivo_encaminhamento
                                                ? `Sem decisão automática — ${processo.motivo_encaminhamento}`
                                                : 'Sem decisão automática. O motor não deferiu nem indeferiu; o processo foi encaminhado à análise técnica.'}
                                        </p>
                                    </div>
                                )}
                                <div>
                                    <p className="text-lg font-semibold text-gray-800 dark:text-white/90">
                                        {processo.empresa ?? 'Empresa não informada'}
                                    </p>
                                    <p className="text-theme-sm text-gray-500 dark:text-gray-400">
                                        {processo.cnpj && <span>{processo.cnpj} · </span>}
                                        {processo.imovel || 'Imóvel não informado'}
                                    </p>
                                </div>
                            </div>

                            <dl className="grid shrink-0 grid-cols-1 gap-4 sm:text-right">
                                <DescItem label="Nº do processo">
                                    <span className="font-medium">{processo.protocol_number ?? '—'}</span>
                                </DescItem>
                                <DescItem label="Protocolo BAP">{processo.bap ?? '—'}</DescItem>
                                <DescItem label="Nº da viabilidade">
                                    {processo.tvl_product_number ? (
                                        <span className="inline-flex flex-wrap items-center justify-end gap-2">
                                            <span>{processo.tvl_product_number}</span>
                                            <BaixarTvl solicitacaoId={processo.id} />
                                        </span>
                                    ) : (
                                        '—'
                                    )}
                                </DescItem>
                            </dl>
                        </div>

                        <div className="mt-5 border-t border-gray-100 pt-5 dark:border-gray-800">
                            <div className="flex flex-wrap items-center gap-3">
                                {podeAbrirFicha && (
                                    <Link
                                        href={`/gestao/processos/${processo.id}/ficha`}
                                        className="inline-flex items-center justify-center gap-2 rounded-lg bg-brand-500 px-5 py-3 text-sm text-white shadow-theme-xs transition hover:bg-brand-600 focus:outline-hidden"
                                    >
                                        {fichaExpresso ? 'Abrir ficha' : 'Abrir ficha de análise'}
                                        <ArrowRightIcon className="size-4" />
                                    </Link>
                                )}
                                <Link
                                    href={`/gestao/processos/${processo.id}/comunicacoes`}
                                    className="inline-flex items-center justify-center gap-2 rounded-lg px-5 py-3 text-sm font-medium text-gray-700 ring-1 ring-inset ring-gray-300 transition hover:bg-gray-50 focus:outline-hidden dark:text-gray-300 dark:ring-gray-700 dark:hover:bg-white/[0.03]"
                                >
                                    Histórico de comunicações
                                </Link>
                            </div>
                            {podeAbrirFicha && (
                                <p className="mt-2 text-theme-xs text-gray-400 dark:text-gray-500">
                                    {fichaExpresso
                                        ? 'A ficha mostra o que o fluxo expresso fez e o resultado de cada CNAE. Esta tela é somente leitura.'
                                        : 'A análise, os precedentes e as decisões ficam na ficha. Esta tela é somente leitura.'}
                                </p>
                            )}
                        </div>
                    </CardContent>
                </Card>

                <div className="grid gap-6 lg:grid-cols-3">
                    {/* Coluna principal: abas */}
                    <div className="space-y-5 lg:col-span-2">
                        <div
                            role="tablist"
                            aria-label="Seções do processo"
                            className="inline-flex rounded-lg border border-gray-200 bg-gray-50 p-1 dark:border-gray-800 dark:bg-white/[0.03]"
                        >
                            {abas.map((item) => {
                                const ativa = item.value === aba;

                                return (
                                    <button
                                        key={item.value}
                                        type="button"
                                        role="tab"
                                        aria-selected={ativa}
                                        onClick={() => setAba(item.value)}
                                        className={`rounded-md px-4 py-2 text-theme-sm font-medium transition ${
                                            ativa
                                                ? 'bg-white text-brand-500 shadow-theme-xs dark:bg-gray-900 dark:text-brand-400'
                                                : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'
                                        }`}
                                    >
                                        {item.label}
                                    </button>
                                );
                            })}
                        </div>

                        {aba === 'informacoes' && parecer && <ParecerCard parecer={parecer} />}

                        {aba === 'informacoes' && divergencia_endereco && (
                            <Alert
                                variant="warning"
                                title="Endereço diferente do cadastro da inscrição"
                                message={`O logradouro informado no processo é "${divergencia_endereco.declarado}", mas a inscrição imobiliária está cadastrada na SEFAZ em "${divergencia_endereco.sefaz}". A análise de zona e via usa o imóvel da inscrição.`}
                            />
                        )}

                        {aba === 'informacoes' && (
                            <Card>
                                <CardHeader title="Informações do processo" />
                                <CardContent>
                                    <dl className="grid grid-cols-1 gap-5 sm:grid-cols-2">
                                        <DescItem label="Empresa">{processo.empresa ?? '—'}</DescItem>
                                        <DescItem label="CNPJ">{processo.cnpj ?? '—'}</DescItem>
                                        <DescItem label="Imóvel">{processo.endereco_completo || processo.imovel || '—'}</DescItem>
                                        <DescItem label="Inscrição imobiliária">{processo.inscricao ?? '—'}</DescItem>
                                        <DescItem label="Tipo de imóvel">{processo.tipo_imovel ?? '—'}</DescItem>
                                        {processo.recepcao && <RecepcaoRecebida recepcao={processo.recepcao} />}
                                        <DescItem label="Origem">{processo.origin_label}</DescItem>
                                        {processo.motivo_da_analise ? (
                                            <DescItem label="Motivo do encaminhamento">{processo.motivo_da_analise}</DescItem>
                                        ) : (
                                            processo.contingency_reason && (
                                                <DescItem label="Motivo da contingência">{processo.contingency_reason}</DescItem>
                                            )
                                        )}
                                        <DescItem label="Status">{processo.status_label}</DescItem>
                                        <DescItem label="Situação da análise">
                                            {processo.analysis_status_label ?? '—'}
                                        </DescItem>
                                        {podeAnalisar && podeConcluirAnalise && (
                                            <DescItem label="Conclusão de Análise">
                                                <ConclusaoAnaliseForm
                                                    resultadoId="resultado-conclusao"
                                                    resultado={resultadoConclusao}
                                                    onResultado={setResultadoConclusao}
                                                    opcoes={conclusaoOpcoes}
                                                    erro={erroConclusao}
                                                    processing={conclusaoProcessing}
                                                    onConfirm={concluirAnalise}
                                                />
                                            </DescItem>
                                        )}
                                        {podeAnalisar && analysisStatusProximas.length > 0 && (
                                            <DescItem label="Alterar situação da análise">
                                                <div className="flex flex-col gap-2">
                                                    <Select
                                                        ariaLabel="Situação da análise"
                                                        value={situacaoAnalise}
                                                        placeholder="Selecione…"
                                                        options={analysisStatusProximas}
                                                        onChange={(valor) => {
                                                            setSituacaoAnalise(valor);
                                                            if (valor !== '' && valor !== 'encaminhado') {
                                                                router.post(
                                                                    `/gestao/processos/${processo.id}/status-analise`,
                                                                    { status: valor },
                                                                    { preserveScroll: true },
                                                                );
                                                            }
                                                        }}
                                                    />
                                                    {situacaoAnalise === 'encaminhado' && (
                                                        <>
                                                            <OptionSelect
                                                                id="setor-encaminhamento"
                                                                value={setorEncaminhamento}
                                                                placeholder="Setor"
                                                                options={setoresEncaminhamento.map((setor) => ({
                                                                    value: String(setor.id),
                                                                    label: setor.name,
                                                                }))}
                                                                onChange={(valor) => {
                                                                    setSetorEncaminhamento(valor);
                                                                    setAnalistaEncaminhamento('');
                                                                }}
                                                            />
                                                            <SearchableSelect
                                                                id="usuario-encaminhamento"
                                                                value={analistaEncaminhamento}
                                                                placeholder="Usuário"
                                                                options={analistasEncaminhamento
                                                                    .filter((analista) =>
                                                                        setorEncaminhamento === ''
                                                                            ? false
                                                                            : analista.setores.includes(Number(setorEncaminhamento)),
                                                                    )
                                                                    .map((analista) => ({
                                                                        value: String(analista.id),
                                                                        label: analista.name,
                                                                    }))}
                                                                onChange={setAnalistaEncaminhamento}
                                                            />
                                                            <Button
                                                                size="sm"
                                                                disabled={setorEncaminhamento === '' || analistaEncaminhamento === ''}
                                                                onClick={() =>
                                                                    router.post(
                                                                        `/gestao/processos/${processo.id}/status-analise`,
                                                                        {
                                                                            status: 'encaminhado',
                                                                            sector_id: Number(setorEncaminhamento),
                                                                            user_id: Number(analistaEncaminhamento),
                                                                        },
                                                                        { preserveScroll: true },
                                                                    )
                                                                }
                                                            >
                                                                Encaminhar
                                                            </Button>
                                                        </>
                                                    )}
                                                </div>
                                            </DescItem>
                                        )}
                                        <DescItem label="Serviço">
                                            <CategoriaBadges categorias={processo.categorias} />
                                        </DescItem>
                                        <DescItem label="Analista responsável">
                                            {processo.analista ?? 'Não atribuído'}
                                        </DescItem>
                                        <DescItem label="Setor">{processo.setor ?? '—'}</DescItem>
                                        <DescItem label="Etapa da análise">
                                            {processo.analysis_stage_label ?? '—'}
                                        </DescItem>
                                        <DescItem label="Prazo (SLA)">
                                            <div className="flex flex-col gap-1">
                                                <span>{formatarDataHora(processo.analysis_due_at)}</span>
                                                <SemaforoBadge sla={processo.sla} />
                                            </div>
                                        </DescItem>
                                        <DescItem label="Protocolado em">
                                            {formatarDataHora(processo.protocoled_at)}
                                        </DescItem>
                                    </dl>
                                </CardContent>
                            </Card>
                        )}

                        {aba === 'tramitacao' && (
                            <Card>
                                <CardHeader
                                    title="Tramitação"
                                    description="Linha do tempo das transições de estado, com o tempo em cada etapa."
                                />
                                <CardContent>
                                    {eventos.length === 0 ? (
                                        <EmptyState
                                            title="Sem transições registradas"
                                            description="As mudanças de estado do processo aparecem aqui conforme ele tramita."
                                        />
                                    ) : (
                                        <ol className="relative space-y-6 border-l border-gray-200 pl-6 dark:border-gray-800">
                                            {eventos.map((evento, index) => {
                                                const duracao = formatarDuracao(
                                                    evento.em,
                                                    eventos[index + 1]?.em ?? null,
                                                );

                                                return (
                                                    <li key={`${evento.to}-${evento.em}-${index}`} className="relative">
                                                        <span className="absolute top-1 -left-[1.4rem] size-3 rounded-full border-2 border-white bg-brand-500 dark:border-gray-900" />
                                                        <div className="flex flex-wrap items-center gap-2">
                                                            <span className="text-theme-sm font-medium text-gray-800 dark:text-white/90">
                                                                {evento.to_label}
                                                            </span>
                                                            {evento.distribuidor && (
                                                                <span className="text-theme-xs text-gray-500 dark:text-gray-400">
                                                                    Distribuído por: {evento.distribuidor}
                                                                </span>
                                                            )}
                                                            {evento.ator && (
                                                                <span className="text-theme-xs text-gray-500 dark:text-gray-400">
                                                                    Por: {evento.ator}
                                                                </span>
                                                            )}
                                                            {evento.atribuido && (
                                                                <span className="text-theme-xs text-gray-500 dark:text-gray-400">
                                                                    Atribuído: {evento.atribuido}
                                                                </span>
                                                            )}
                                                            {duracao && (
                                                                <Badge color="light" size="sm">
                                                                    {duracao}
                                                                </Badge>
                                                            )}
                                                        </div>
                                                        <p className="mt-0.5 text-theme-xs text-gray-400 dark:text-gray-500">
                                                            {formatarDataHora(evento.em)}
                                                            {evento.from_label ? ` · de ${evento.from_label}` : ''}
                                                        </p>
                                                        {evento.reason && (
                                                            <p className="mt-1 text-theme-sm text-gray-600 dark:text-gray-300">
                                                                {evento.reason}
                                                            </p>
                                                        )}
                                                    </li>
                                                );
                                            })}
                                        </ol>
                                    )}
                                </CardContent>
                            </Card>
                        )}

                        {aba === 'explicabilidade' && explicacao && (
                            <Card>
                                <CardHeader
                                    title="Explicabilidade da decisão"
                                    description="Passo a passo de como a viabilidade foi decidida — projeção do que foi registrado, sem reexecutar o motor."
                                />
                                <CardContent>
                                    <DecisionExplanation explicacao={explicacao} />
                                </CardContent>
                            </Card>
                        )}
                    </div>

                    {/* Coluna lateral: escritório virtual + mini-mapa permanente */}
                    <div className="space-y-5">
                        {processo.escritorio_virtual && (
                            <Card>
                                <CardHeader
                                    title="Escritório virtual"
                                    description="Vínculo do produto ao regime de escritório virtual (RN-EV)."
                                />
                                <CardContent>
                                    <dl className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                        <DescItem label="Tipo">
                                            <Badge color={processo.escritorio_virtual.tipo === 'sede' ? 'info' : 'primary'} size="sm">
                                                {processo.escritorio_virtual.tipo === 'sede' ? 'Sede' : 'Abrigado'}
                                            </Badge>
                                        </DescItem>
                                        <DescItem
                                            label={processo.escritorio_virtual.tipo === 'abrigado' ? 'End. Virtual — Viabilidade da sede' : 'Viabilidade da sede'}
                                        >
                                            {processo.escritorio_virtual.tvl_sede ?? '—'}
                                        </DescItem>
                                        <DescItem label="Inscrição imobiliária">
                                            {processo.escritorio_virtual.inscricao ?? '—'}
                                        </DescItem>
                                        <DescItem label="Validade da sede">
                                            {/* Não modelada (desfecho spec-2) — degradação honesta. */}
                                            {processo.escritorio_virtual.validade_sede ?? '—'}
                                        </DescItem>
                                        <DescItem label="Status do produto">
                                            {processo.escritorio_virtual.status_produto ?? '—'}
                                        </DescItem>
                                    </dl>
                                </CardContent>
                            </Card>
                        )}

                        <Card>
                            <CardHeader title="Localização do imóvel" />
                            <CardContent>
                                {centro ? (
                                    <div className="space-y-3">
                                        <MapaSection
                                            lat={centro.lat}
                                            lng={centro.lng}
                                            draggable={false}
                                            camadas={[
                                                ...(geo.poligono ? [{ id: 'poligono', type: 'poligono', geojson: geo.poligono }] : []),
                                                ...(geo.poligono_enderecamento
                                                    ? [
                                                          {
                                                              id: 'enderecamento',
                                                              type: 'enderecamento',
                                                              geojson: geo.poligono_enderecamento,
                                                              estilo: ESTILO_ENDERECAMENTO,
                                                          },
                                                      ]
                                                    : []),
                                            ]}
                                        />
                                        {geo.poligono_enderecamento ? (
                                            <p className="text-sm text-gray-600 dark:text-gray-300">
                                                Contorno tracejado: polígono do endereçamento SEDUR.
                                            </p>
                                        ) : null}
                                        {geo.origem && !geo.poligono ? (
                                            <p className="text-sm text-gray-600 dark:text-gray-300">
                                                Localização pelo {geo.origem.label} (ponto, sem o desenho do lote).
                                            </p>
                                        ) : null}
                                    </div>
                                ) : (
                                    <EmptyState
                                        icon={<MapPinIcon className="size-6" />}
                                        title="Sem polígono cadastrado"
                                        description="Este processo ainda não tem a geometria do imóvel para exibir no mapa."
                                    />
                                )}
                            </CardContent>
                        </Card>

                        {geo.camadas && (
                            <Card>
                                <CardHeader title="Camadas do imóvel" />
                                <CardContent>
                                    <CamadasImovelLista camadas={geo.camadas} />
                                </CardContent>
                            </Card>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

Show.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
