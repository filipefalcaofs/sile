import { Head, Link, router, usePage } from '@inertiajs/react';
import type { MultiPolygon, Polygon } from 'geojson';
import type { ReactNode } from 'react';
import { useState } from 'react';
import ConclusaoAnaliseForm from '@/components/analise/conclusao-analise-form';
import BaixarTvl from '@/components/analise/baixar-tvl';
import JustificativaReginCard, { type JustificativaRegin } from '@/components/analise/justificativa-regin-card';
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
import { MapaDecisaoSection } from '@/components/geo/mapa-decisao-section';
import { MapaSection } from '@/components/geo/mapa-section';
import CnaesDoProcesso from '@/components/analise/cnaes-do-processo';
import { AlertIcon, ArrowRightIcon, MapPinIcon } from '@/components/icons';
import Alert from '@/components/ui/alert';
import Badge from '@/components/ui/badge';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import EmptyState from '@/components/ui/empty-state';
import GestaoLayout from '@/layouts/gestao-layout';
import type { SharedProps } from '@/types';
import { duracaoNaTramitacao } from './duracao-tramitacao';

interface TimelineItem {
    /** status | analise | atribuicao | pendencia | vistoria | malha_fina | documento | integracao */
    tipo?: string;
    resultado?: string | null;
    from: string | null;
    from_label: string | null;
    to: string;
    to_label: string;
    reason: string | null;
    em: string | null;
    ator?: string | null;
    atribuido?: string | null;
    distribuidor?: string | null;
    link?: string | null;
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
    /** Texto que o parecer leva à JUCEB; null sem decisão ou fora do REGIN. */
    justificativaRegin?: JustificativaRegin | null;
    /** Explicabilidade passo a passo (HU-095); null quando não há decisão. */
    explicacao?: DecisionExplanationData | null;
    /** Próximas transições manuais oferecidas ao analista (Tarefa 10). */
    analysisStatusProximas: { value: string; label: string }[];
    conclusaoOpcoes: { value: string; label: string }[];
    /** Capa aberta pela caixa de vistoria: voltar à consulta de vistorias e permitir imprimir. */
    consultaVistoria?: boolean;
    /** Despacho do analista ao encaminhar o processo à vistoria; null sem encaminhamento. */
    despachoVistoria?: {
        orientacoes: string;
        analista: string | null;
        setor_origem: string | null;
        setor_vistoria: string | null;
        encaminhado_em: string | null;
    } | null;
}

type Aba = 'informacoes' | 'tramitacao' | 'explicabilidade';

const ROTULOS_TIPO: Record<string, string> = {
    analise: 'Análise',
    atribuicao: 'Atribuição',
    pendencia: 'Pendência',
    vistoria: 'Vistoria',
    malha_fina: 'Malha fina',
    documento: 'Documento',
    integracao: 'Integração',
};

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

function DescItem({ label, children, className }: { label: string; children: ReactNode; className?: string }) {
    return (
        <div className={className}>
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
    justificativaRegin = null,
    conclusaoOpcoes,
    consultaVistoria = false,
    despachoVistoria = null,
}: ShowProps & {
    setoresEncaminhamento?: SetorEncaminhamento[];
    analistasEncaminhamento?: AnalistaEncaminhamento[];
}) {
    const { auth } = usePage<SharedProps>().props;
    const podeAnalisar = auth.permissions.includes('analisar-processos');
    const fichaExpresso = processo.fluxo === 'expresso';
    const podeAbrirFicha = podeAnalisar || fichaExpresso;
    const podeConcluirAnalise =
        !fichaExpresso &&
        auth.permissions.includes('concluir-analise') &&
        processo.status === 'em_analise';

    const [aba, setAba] = useState<Aba>('informacoes');
    const [resultadoConclusao, setResultadoConclusao] = useState('');
    const [tipoDeferimento, setTipoDeferimento] = useState('');
    const [validadeMeses, setValidadeMeses] = useState('');
    const [validadeData, setValidadeData] = useState('');
    const [errosTipo, setErrosTipo] = useState<{ tipo: string | null; validade: string | null; validadeData: string | null }>({ tipo: null, validade: null, validadeData: null });
    const [conclusaoProcessing, setConclusaoProcessing] = useState(false);
    const [erroConclusao, setErroConclusao] = useState<string | null>(null);
    const [divergenciaConclusao, setDivergenciaConclusao] = useState<string | null>(null);

    function concluirAnalise(confirmarDivergencia = false) {
        setErroConclusao(null);
        setDivergenciaConclusao(null);
        setErrosTipo({ tipo: null, validade: null, validadeData: null });
        router.post(
            `/gestao/processos/${processo.id}/conclusao-analise`,
            {
                resultado: resultadoConclusao,
                confirmar_divergencia: confirmarDivergencia,
                tipo: tipoDeferimento,
                validade_meses: validadeMeses,
                validade_data: validadeMeses === 'personalizado' ? validadeData : null,
            },
            {
                preserveScroll: true,
                onStart: () => setConclusaoProcessing(true),
                onFinish: () => setConclusaoProcessing(false),
                onSuccess: () => {
                    setResultadoConclusao('');
                    setTipoDeferimento('');
                    setValidadeMeses('');
                    setValidadeData('');
                },
                onError: (erros) => {
                    const mapa = erros as Record<string, string>;
                    setDivergenciaConclusao(mapa.divergencia ?? null);
                    setErrosTipo({ tipo: mapa.tipo ?? null, validade: mapa.validade_meses ?? null, validadeData: mapa.validade_data ?? null });
                    const erroDeTipo = mapa.tipo !== undefined || mapa.validade_meses !== undefined || mapa.validade_data !== undefined;
                    setErroConclusao(
                        mapa.divergencia || erroDeTipo ? null : (mapa.conclusao ?? mapa.resultado ?? 'Não foi possível concluir a análise.'),
                    );
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
                    consultaVistoria
                        ? { label: 'Consulta de vistorias', href: '/gestao/vistorias' }
                        : { label: 'Processos', href: '/gestao/processos' },
                ]}
            />

            <div className="space-y-6">
                <Link
                    href={consultaVistoria ? '/gestao/vistorias' : '/gestao/processos'}
                    className="inline-flex items-center gap-1.5 text-theme-sm font-medium text-brand-500 transition hover:text-brand-600 dark:text-brand-400"
                >
                    <ArrowRightIcon className="size-4 rotate-180" />
                    {consultaVistoria ? 'Voltar às vistorias' : 'Voltar à consulta'}
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
                                <DescItem label="Data de entrada">
                                    {formatarDataHora(processo.protocoled_at)}
                                </DescItem>
                            </dl>
                        </div>

                        <div className="mt-5 border-t border-gray-100 pt-5 dark:border-gray-800">
                            <div className="flex flex-wrap items-center gap-3">
                                {consultaVistoria && (
                                    <a
                                        href={`/gestao/vistorias/${processo.id}/consulta/imprimir`}
                                        className="inline-flex items-center justify-center gap-2 rounded-lg px-5 py-3 text-sm font-medium text-gray-700 ring-1 ring-inset ring-gray-300 transition hover:bg-gray-50 focus:outline-hidden dark:text-gray-300 dark:ring-gray-700 dark:hover:bg-white/[0.03]"
                                    >
                                        Imprimir consulta (PDF)
                                    </a>
                                )}
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

                {despachoVistoria && (
                    <Card>
                        <CardHeader
                            title="Orientações do analista para a vistoria"
                            description={[
                                despachoVistoria.analista && `Analista: ${despachoVistoria.analista}`,
                                despachoVistoria.setor_origem && `Setor de origem: ${despachoVistoria.setor_origem}`,
                                despachoVistoria.encaminhado_em && `Encaminhado em ${formatarDataHora(despachoVistoria.encaminhado_em)}`,
                            ]
                                .filter(Boolean)
                                .join(' · ')}
                        />
                        <CardContent>
                            <p className="whitespace-pre-line text-theme-sm text-gray-700 dark:text-gray-200">
                                {despachoVistoria.orientacoes}
                            </p>
                        </CardContent>
                    </Card>
                )}

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

                        {aba === 'informacoes' && justificativaRegin && auth.permissions.includes('concluir-analise') && (
                            <JustificativaReginCard
                                key={justificativaRegin.texto}
                                processoId={processo.id}
                                justificativa={justificativaRegin}
                            />
                        )}

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
                                        <DescItem label="Solicitante">{processo.solicitante ?? '—'}</DescItem>
                                        <DescItem label="CPF do solicitante">{processo.solicitante_cpf ?? '—'}</DescItem>
                                        <DescItem label="CNAE" className="sm:col-span-2">
                                            <CnaesDoProcesso cnaes={processo.cnaes_detalhe ?? []} exibirTodos />
                                        </DescItem>
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
                                                    onResultado={(valor) => {
                                                        setResultadoConclusao(valor);
                                                        setDivergenciaConclusao(null);
                                                        if (valor !== 'deferido') {
                                                            setTipoDeferimento('');
                                                            setValidadeMeses('');
                                                            setValidadeData('');
                                                        }
                                                    }}
                                                    opcoes={conclusaoOpcoes}
                                                    tipo={tipoDeferimento}
                                                    onTipo={
                                                        processo.renovacao
                                                            ? undefined
                                                            : (valor) => {
                                                                  setTipoDeferimento(valor);
                                                                  if (valor === 'definitiva') {
                                                                      setValidadeMeses('');
                                                                      setValidadeData('');
                                                                  }
                                                              }
                                                    }
                                                    validadeMeses={validadeMeses}
                                                    onValidadeMeses={setValidadeMeses}
                            validadeData={validadeData}
                            onValidadeData={setValidadeData}
                            erroValidadeData={errosTipo.validadeData}
                                                    erroTipo={errosTipo.tipo}
                                                    erroValidade={errosTipo.validade}
                                                    erro={erroConclusao}
                                                    divergencia={divergenciaConclusao}
                                                    onConfirmarDivergencia={() => concluirAnalise(true)}
                                                    processing={conclusaoProcessing}
                                                    onConfirm={() => concluirAnalise()}
                                                />
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
                                    description="Linha do tempo completa do processo: mudanças de situação, análise, atribuições, pendências e envios à JUCEB e à SEFAZ."
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
                                                const status = (evento.tipo ?? 'status') === 'status';
                                                const proximoStatus = eventos
                                                    .slice(index + 1)
                                                    .find((outro) => (outro.tipo ?? 'status') === 'status');
                                                const duracao = status ? duracaoNaTramitacao(evento.to, evento.em, proximoStatus?.em ?? null) : null;
                                                const rotuloTipo = ROTULOS_TIPO[evento.tipo ?? 'status'];

                                                return (
                                                    <li key={`${evento.tipo ?? 'status'}-${evento.to}-${evento.em}-${index}`} className="relative">
                                                        <span
                                                            className={`absolute top-1 -left-[1.4rem] size-3 rounded-full border-2 border-white dark:border-gray-900 ${
                                                                status
                                                                    ? 'bg-brand-500'
                                                                    : evento.resultado === 'bloqueado'
                                                                      ? 'bg-warning-500'
                                                                      : evento.tipo === 'integracao'
                                                                        ? 'bg-success-500'
                                                                        : 'bg-gray-400'
                                                            }`}
                                                        />
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
                                                            {!status && rotuloTipo && (
                                                                <Badge
                                                                    color={
                                                                        evento.resultado === 'bloqueado'
                                                                            ? 'warning'
                                                                            : evento.tipo === 'integracao'
                                                                              ? 'success'
                                                                              : 'light'
                                                                    }
                                                                    size="sm"
                                                                >
                                                                    {rotuloTipo}
                                                                </Badge>
                                                            )}
                                                        </div>
                                                        <p className="mt-0.5 text-theme-xs text-gray-400 dark:text-gray-500">
                                                            {formatarDataHora(evento.em)}
                                                            {evento.from_label ? ` · de ${evento.from_label}` : ''}
                                                        </p>
                                                        {evento.reason && (
                                                            <p className="mt-1 text-theme-sm text-gray-600 dark:text-gray-300">
                                                                {evento.link && podeAbrirFicha ? (
                                                                    <a
                                                                        href={evento.link}
                                                                        target="_blank"
                                                                        rel="noopener noreferrer"
                                                                        className="text-brand-500 underline underline-offset-2 hover:text-brand-600 dark:text-brand-400"
                                                                    >
                                                                        {evento.reason}
                                                                    </a>
                                                                ) : (
                                                                    evento.reason
                                                                )}
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
                                        <MapaDecisaoSection
                                            url={`/gestao/processos/${processo.id}/mapa-decisao`}
                                            rotuloLote={processo.endereco_completo || processo.imovel || null}
                                            fallback={
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
                                            }
                                        />
                                        {geo.poligono_enderecamento ? (
                                            <p className="text-sm text-gray-600 dark:text-gray-300">
                                                Contorno tracejado: polígono do endereçamento SEDUR.
                                            </p>
                                        ) : null}
                                        {geo.origem && !geo.poligono ? (
                                            <p className="text-sm text-gray-600 dark:text-gray-300">
                                                Localização pelo {geo.origem.label}.
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
