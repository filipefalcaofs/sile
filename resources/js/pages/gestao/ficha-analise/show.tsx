import { Head, Link, router, useHttp, usePage, WhenVisible } from '@inertiajs/react';
import type { GeoJsonObject } from 'geojson';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import PageHeader from '@/components/app/page-header';
import { MapaSection } from '@/components/geo/mapa-section';
import Checkbox from '@/components/form/checkbox';
import Input from '@/components/form/input';
import Label from '@/components/form/label';
import Select from '@/components/form/select';
import { AlertIcon, ArrowRightIcon, CheckCircleIcon, MapPinIcon, TrashIcon } from '@/components/icons';
import Badge from '@/components/ui/badge';
import Button from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import ConfirmDialog from '@/components/ui/confirm-dialog';
import EmptyState from '@/components/ui/empty-state';
import { Modal } from '@/components/ui/modal';
import GestaoLayout from '@/layouts/gestao-layout';
import type { SharedProps } from '@/types';

type StatusFicha = 'deferida' | 'indeferida' | 'analise';

interface PerCnae {
    cnae: string;
    cnae_formatado?: string | null;
    is_primary?: boolean;
    tendencia?: string | null;
    tendencia_label?: string | null;
    status_sugerido?: string | null;
    status_escolhido?: string | null;
    fluxo?: string | null;
    grupo_uso?: string | null;
    valor_tll?: number | string | null;
    gatilhos?: string[] | null;
    fundamentacao?: string[] | null;
    condicionantes?: string[] | null;
    justificativa?: string | null;
}

interface Parking {
    vagas_requeridas?: number | null;
    vagas_exigidas?: number | null;
    vistoria?: boolean;
}

interface Ficha {
    id: number;
    viability_request_id: number;
    revision: number;
    status: string;
    status_label: string;
    editavel: boolean;
    engine_available: boolean;
    per_cnae: PerCnae[];
    conditions: string[];
    parking: Parking;
    parecer: string | null;
    analyst: string | null;
    finalized_at: string | null;
    updated_at: string | null;
}

interface Processo {
    id: number;
    protocol_number: string | null;
    status: string;
    status_label: string;
}

interface TextoPadrao {
    id: number;
    category: string;
    content: string;
    version: number;
}

interface Localizacao {
    poligono: GeoJsonObject | null;
    endereco: string | null;
}

interface PrecedenteImovel {
    viability_request_id: number;
    protocol_number: string | null;
    outcome: string | null;
    decided_at: string | null;
    service_type: string | null;
    analyst: string | null;
}

interface CnaeZona {
    disponivel: boolean;
    cnae?: string;
    zona?: string;
    janela_meses?: number;
    deferidos?: number;
    indeferidos?: number;
    total?: number;
    motivo?: string;
}

interface PrecedentesResponse {
    imovel: PrecedenteImovel[];
    cnae_zona: CnaeZona;
}

type DiffValor = { de: unknown; para: unknown };

interface DiffResponse {
    de: number;
    para: number;
    diff: Record<string, unknown>;
}

type SeveridadeIa = 'baixa' | 'media' | 'alta';

interface InconsistenciaIa {
    campo: string;
    declarado: string;
    documento: string;
    severidade: SeveridadeIa;
}

interface SugestaoIaOutput {
    inconsistencias?: InconsistenciaIa[];
    resumo?: string | null;
    pontos_chave?: string[] | null;
    minuta?: string | null;
    fundamentacao?: string | null;
    fonte?: string | null;
    [chave: string]: unknown;
}

interface SugestaoIa {
    id: number;
    type: string;
    type_label: string;
    status: string;
    status_label: string;
    confianca: string | null;
    output: SugestaoIaOutput;
    created_at: string | null;
}

interface FichaAnaliseShowProps {
    ficha: Ficha;
    processo: Processo;
    localizacao?: Localizacao;
    textosPadrao: TextoPadrao[];
    autosaveDebounceMs: number;
    /** Sugestões de IA (HU-115) — prop deferida, carregada sob demanda pelo card. */
    sugestoesIa?: SugestaoIa[];
}

const STATUS_OPCOES: { value: StatusFicha; label: string }[] = [
    { value: 'deferida', label: 'Deferida' },
    { value: 'indeferida', label: 'Indeferida' },
    { value: 'analise', label: 'Em análise' },
];

/** A minuta (HU-118) roda em fila (assíncrona): após solicitar, sondamos a prop
 *  deferida sugestoesIa por uma janela limitada até a sugestão ser processada. */
const MINUTA_POLL_INTERVAL_MS = 3000;
const MINUTA_POLL_MAX = 8;

function statusLabel(status: string | null | undefined): string {
    return STATUS_OPCOES.find((opcao) => opcao.value === status)?.label ?? (status ?? '—');
}

function statusColor(status: string | null | undefined): 'success' | 'error' | 'warning' | 'light' {
    if (status === 'deferida') {
        return 'success';
    }

    if (status === 'indeferida') {
        return 'error';
    }

    if (status === 'analise') {
        return 'warning';
    }

    return 'light';
}

function formatarDataHora(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    const data = new Date(iso);

    if (Number.isNaN(data.getTime())) {
        return iso;
    }

    return data.toLocaleString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

/**
 * Centroide aproximado do anel externo do polígono (GeoJSON [lng, lat]) para
 * centralizar o mini-mapa. Devolve null quando não há polígono confiável —
 * nunca uma coordenada inventada (anti-fachada).
 */
function centroideDoPoligono(geojson: GeoJsonObject | null | undefined): { lat: number; lng: number } | null {
    if (!geojson || typeof geojson !== 'object') {
        return null;
    }

    const objeto = geojson as { type?: string; coordinates?: unknown; geometry?: { coordinates?: unknown } };
    const coordenadas =
        objeto.type === 'Feature' ? (objeto.geometry?.coordinates ?? null) : (objeto.coordinates ?? null);

    if (!Array.isArray(coordenadas) || !Array.isArray(coordenadas[0])) {
        return null;
    }

    const anel = coordenadas[0] as unknown[];
    let somaLat = 0;
    let somaLng = 0;
    let total = 0;

    for (const ponto of anel) {
        if (Array.isArray(ponto) && typeof ponto[0] === 'number' && typeof ponto[1] === 'number') {
            somaLng += ponto[0];
            somaLat += ponto[1];
            total += 1;
        }
    }

    if (total === 0) {
        return null;
    }

    return { lat: somaLat / total, lng: somaLng / total };
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

/** Textarea no padrão visual do design system (sem componente dedicado no kit). */
function Textarea({
    value,
    onChange,
    rows = 4,
    placeholder,
    disabled,
    id,
}: {
    value: string;
    onChange: (value: string) => void;
    rows?: number;
    placeholder?: string;
    disabled?: boolean;
    id?: string;
}) {
    return (
        <textarea
            id={id}
            rows={rows}
            value={value}
            disabled={disabled}
            placeholder={placeholder}
            onChange={(event) => onChange(event.target.value)}
            className="w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/20 focus:outline-hidden disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
        />
    );
}

/** Seletor de status escolhido por CNAE (espelha os radios Deferida/Indeferida/Análise do SAPS). */
function StatusEscolhido({
    cnae,
    valor,
    onChange,
    disabled,
}: {
    cnae: string;
    valor: string | null | undefined;
    onChange: (status: StatusFicha) => void;
    disabled: boolean;
}) {
    return (
        <div className="flex flex-wrap gap-2" role="radiogroup" aria-label={`Decisão do CNAE ${cnae}`}>
            {STATUS_OPCOES.map((opcao) => {
                const ativo = valor === opcao.value;

                return (
                    <button
                        key={opcao.value}
                        type="button"
                        role="radio"
                        aria-checked={ativo}
                        disabled={disabled}
                        onClick={() => onChange(opcao.value)}
                        className={`rounded-lg px-3 py-1.5 text-theme-xs font-medium transition disabled:cursor-not-allowed disabled:opacity-60 ${
                            ativo
                                ? opcao.value === 'deferida'
                                    ? 'bg-success-500 text-white'
                                    : opcao.value === 'indeferida'
                                      ? 'bg-error-500 text-white'
                                      : 'bg-warning-500 text-white'
                                : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-white/5 dark:text-gray-300 dark:hover:bg-white/10'
                        }`}
                    >
                        {opcao.label}
                    </button>
                );
            })}
        </div>
    );
}

export default function FichaAnaliseShow({
    ficha,
    processo,
    localizacao,
    textosPadrao,
    autosaveDebounceMs,
    sugestoesIa,
}: FichaAnaliseShowProps) {
    const { auth } = usePage<SharedProps>().props;
    const podeMalhaFina = auth.permissions.includes('encaminhar-malha-fina');
    const podeEmitirTvl = auth.permissions.includes('emitir-tvl');

    const editavel = ficha.editavel;

    const [perCnae, setPerCnae] = useState<PerCnae[]>(() => ficha.per_cnae ?? []);
    const [conditions, setConditions] = useState<string[]>(() => ficha.conditions ?? []);
    const [parecer, setParecer] = useState<string>(() => ficha.parecer ?? '');
    const [parking, setParking] = useState<Parking>(() => ficha.parking ?? {});
    const [novaCondicao, setNovaCondicao] = useState('');
    const [saveState, setSaveState] = useState<'idle' | 'salvando' | 'salvo' | 'erro'>('idle');

    const autosave = useHttp<{
        per_cnae: Array<{ cnae: string; status_escolhido: string; justificativa: string | null; condicionantes: string[] }>;
        conditions: string[];
        parking: Parking;
        parecer: string | null;
    }>({ per_cnae: [], conditions: [], parking: {}, parecer: null });

    const acao = useHttp<Record<string, never>>({});
    const tvl = useHttp<Record<string, never>, { download_url?: string; url?: string }>({});
    const [pendenciaProcessing, setPendenciaProcessing] = useState(false);
    const [malhaFinaProcessing, setMalhaFinaProcessing] = useState(false);
    const precedentes = useHttp<Record<string, never>, PrecedentesResponse>({});
    const diff = useHttp<{ de: number; para: number }, DiffResponse>({ de: 0, para: 0 });

    // Sugestão de minuta de parecer por IA (HU-118) — apoio, nunca decisão. O job
    // roda em fila; aqui sondamos a sugestão até ela aparecer (UX assíncrona).
    const minuta = useHttp<Record<string, never>, { despachou?: boolean; status?: string }>({});
    const [minutaStatus, setMinutaStatus] = useState<'idle' | 'solicitando' | 'aguardando' | 'pronta' | 'indisponivel'>('idle');
    const [minutaMensagem, setMinutaMensagem] = useState<string | null>(null);
    const minutaPollRef = useRef<number | null>(null);
    const minutaBaselineRef = useRef(0);
    const parecerSugeridos = useMemo(
        () => (sugestoesIa ?? []).filter((sugestao) => sugestao.type === 'parecer').length,
        [sugestoesIa],
    );

    const [precedentesData, setPrecedentesData] = useState<PrecedentesResponse | null>(null);
    const [precedentesErro, setPrecedentesErro] = useState<string | null>(null);

    const [showDiff, setShowDiff] = useState(false);
    const [diffDe, setDiffDe] = useState(Math.max(1, ficha.revision - 1));
    const [diffPara, setDiffPara] = useState(ficha.revision);
    const [diffData, setDiffData] = useState<DiffResponse | null>(null);
    const [diffErro, setDiffErro] = useState<string | null>(null);

    const [showFinalizar, setShowFinalizar] = useState(false);
    const [showDecidir, setShowDecidir] = useState(false);
    const [showPendencia, setShowPendencia] = useState(false);
    const [descricaoPendencia, setDescricaoPendencia] = useState('');
    const [showMalhaFina, setShowMalhaFina] = useState(false);
    const [motivoMalhaFina, setMotivoMalhaFina] = useState('');
    const [pickerParaParecer, setPickerParaParecer] = useState(false);
    const [pickerParaCondicao, setPickerParaCondicao] = useState(false);

    const fichaUrl = `/gestao/processos/${processo.id}/ficha`;

    const construirPayload = useCallback(
        () => ({
            per_cnae: perCnae.map((item) => ({
                cnae: item.cnae,
                status_escolhido: (item.status_escolhido ?? item.status_sugerido ?? 'analise') as string,
                justificativa: item.justificativa ?? null,
                condicionantes: item.condicionantes ?? [],
            })),
            conditions,
            parking,
            parecer: parecer.trim() === '' ? null : parecer,
        }),
        [perCnae, conditions, parking, parecer],
    );

    const salvarRascunho = useCallback(() => {
        if (!editavel) {
            return;
        }

        setSaveState('salvando');
        autosave.transform(() => construirPayload());
        autosave.patch(fichaUrl, {
            onSuccess: () => setSaveState('salvo'),
            onError: () => setSaveState('erro'),
            onHttpException: () => {
                setSaveState('erro');

                return false;
            },
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [editavel, construirPayload, fichaUrl]);

    const primeiraRenderRef = useRef(true);

    useEffect(() => {
        if (!editavel) {
            return;
        }

        if (primeiraRenderRef.current) {
            primeiraRenderRef.current = false;

            return;
        }

        const timer = window.setTimeout(() => salvarRascunho(), autosaveDebounceMs);

        return () => window.clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [perCnae, conditions, parecer, parking]);

    useEffect(() => {
        precedentes.get(`/gestao/processos/${processo.id}/precedentes`, {
            onSuccess: (resposta) => setPrecedentesData(resposta),
            onError: () => setPrecedentesErro('Não foi possível carregar os precedentes.'),
            onHttpException: () => {
                setPrecedentesErro('Não foi possível carregar os precedentes.');

                return false;
            },
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [processo.id]);

    function atualizarCnae(indice: number, patch: Partial<PerCnae>) {
        setPerCnae((atual) => atual.map((item, i) => (i === indice ? { ...item, ...patch } : item)));
    }

    function adicionarCondicao(texto: string) {
        const limpo = texto.trim();

        if (limpo === '' || conditions.includes(limpo)) {
            return;
        }

        setConditions((atual) => [...atual, limpo]);
    }

    function removerCondicao(indice: number) {
        setConditions((atual) => atual.filter((_, i) => i !== indice));
    }

    function inserirTextoPadrao(conteudo: string) {
        if (pickerParaParecer) {
            setParecer((atual) => (atual.trim() === '' ? conteudo : `${atual}\n\n${conteudo}`));
            setPickerParaParecer(false);

            return;
        }

        if (pickerParaCondicao) {
            adicionarCondicao(conteudo);
            setPickerParaCondicao(false);
        }
    }

    function finalizarFicha() {
        acao.post(`${fichaUrl}/finalizar`, {
            onSuccess: () => {
                setShowFinalizar(false);
                router.reload();
            },
            onHttpException: () => false,
        });
    }

    function novaRevisao() {
        acao.post(`${fichaUrl}/nova-revisao`, {
            onSuccess: () => router.reload(),
            onHttpException: () => false,
        });
    }

    function decidirProcesso() {
        router.post(
            `/gestao/processos/${processo.id}/decidir`,
            {},
            {
                preserveScroll: true,
                onFinish: () => setShowDecidir(false),
            },
        );
    }

    function abrirPendencia() {
        router.post(
            `/gestao/processos/${processo.id}/pendencias`,
            { descricao: descricaoPendencia },
            {
                preserveScroll: true,
                onStart: () => setPendenciaProcessing(true),
                onFinish: () => setPendenciaProcessing(false),
                onSuccess: () => {
                    setShowPendencia(false);
                    setDescricaoPendencia('');
                },
            },
        );
    }

    function encaminharMalhaFina() {
        router.post(
            '/gestao/processos/malha-fina',
            { request_ids: [processo.id], motivo: motivoMalhaFina },
            {
                preserveScroll: true,
                onStart: () => setMalhaFinaProcessing(true),
                onFinish: () => setMalhaFinaProcessing(false),
                onSuccess: () => {
                    setShowMalhaFina(false);
                    setMotivoMalhaFina('');
                },
            },
        );
    }

    function emitirTvl() {
        tvl.post(`/gestao/processos/${processo.id}/tvl`, {
            onSuccess: (resposta) => {
                const url = resposta?.download_url ?? resposta?.url ?? null;

                if (url) {
                    window.open(url, '_blank', 'noopener');
                } else {
                    router.reload();
                }
            },
            onHttpException: () => false,
        });
    }

    function compararRevisoes() {
        setDiffErro(null);
        setDiffData(null);
        diff.transform(() => ({ de: diffDe, para: diffPara }));
        diff.get(`${fichaUrl}/diff?de=${diffDe}&para=${diffPara}`, {
            onSuccess: (resposta) => setDiffData(resposta),
            onError: () => setDiffErro('Revisão informada não encontrada.'),
            onHttpException: () => {
                setDiffErro('Revisão informada não encontrada.');

                return false;
            },
        });
    }

    function pararPollMinuta() {
        if (minutaPollRef.current !== null) {
            window.clearInterval(minutaPollRef.current);
            minutaPollRef.current = null;
        }
    }

    function iniciarPollMinuta() {
        pararPollMinuta();
        let tentativas = 0;

        minutaPollRef.current = window.setInterval(() => {
            tentativas += 1;

            if (tentativas > MINUTA_POLL_MAX) {
                pararPollMinuta();

                return;
            }

            router.reload({ only: ['sugestoesIa'] });
        }, MINUTA_POLL_INTERVAL_MS);
    }

    function sugerirMinuta() {
        setMinutaMensagem(null);
        setMinutaStatus('solicitando');
        minutaBaselineRef.current = parecerSugeridos;

        minuta.post(`${fichaUrl}/sugerir-parecer`, {
            onSuccess: (resposta) => {
                setMinutaMensagem(resposta?.status ?? null);

                if (resposta?.despachou) {
                    setMinutaStatus('aguardando');
                    iniciarPollMinuta();
                } else {
                    setMinutaStatus('indisponivel');
                }
            },
            onHttpException: () => {
                setMinutaStatus('indisponivel');
                setMinutaMensagem('Não foi possível solicitar a minuta agora. Tente novamente.');

                return false;
            },
        });
    }

    /**
     * Copia a minuta sugerida para o editor do parecer como RASCUNHO editável
     * (client-side). Nada é decidido nem finalizado: o analista revisa, edita e
     * valida; o autosave persiste apenas o rascunho (RN-008). A IA nunca grava
     * decisão (RN-001) e a revisão finalizada permanece imutável (RN-003).
     */
    function aplicarMinutaAoParecer(minutaTexto: string) {
        setParecer((atual) => (atual.trim() === '' ? minutaTexto : `${atual}\n\n${minutaTexto}`));

        window.requestAnimationFrame(() => {
            document.getElementById('parecer-editor')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
    }

    // A minuta chega de forma assíncrona (fila): quando a contagem de sugestões de
    // parecer cresce além do baseline, encerramos a sondagem e sinalizamos pronta.
    useEffect(() => {
        if (minutaStatus === 'aguardando' && parecerSugeridos > minutaBaselineRef.current) {
            setMinutaStatus('pronta');
            pararPollMinuta();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [parecerSugeridos, minutaStatus]);

    useEffect(() => () => pararPollMinuta(), []);

    const condicionantesSugeridas = useMemo(() => {
        const conjunto = new Set<string>();

        for (const item of perCnae) {
            for (const condicionante of item.condicionantes ?? []) {
                if (condicionante.trim() !== '') {
                    conjunto.add(condicionante.trim());
                }
            }
        }

        return Array.from(conjunto);
    }, [perCnae]);

    const centro = centroideDoPoligono(localizacao?.poligono);
    const temPoligono = centro !== null && localizacao?.poligono != null;

    const vagasRequeridas = parking.vagas_requeridas;
    const vagasExigidas = parking.vagas_exigidas;
    const vagasConforme =
        typeof vagasRequeridas === 'number' && typeof vagasExigidas === 'number'
            ? vagasRequeridas >= vagasExigidas
            : null;

    return (
        <>
            <Head title={`Ficha de análise ${processo.protocol_number ?? ''}`.trim()} />
            <PageHeader
                title={`Ficha de análise ${processo.protocol_number ?? `#${processo.id}`}`}
                breadcrumbs={[
                    { label: 'Painel', href: '/gestao' },
                    { label: 'Processos', href: '/gestao/processos' },
                ]}
                actions={
                    <Badge color={editavel ? 'warning' : 'success'} size="sm">
                        {ficha.status_label} · revisão {ficha.revision}
                    </Badge>
                }
            />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <Link
                        href={`/gestao/processos/${processo.id}`}
                        className="inline-flex items-center gap-1.5 text-theme-sm font-medium text-brand-500 transition hover:text-brand-600 dark:text-brand-400"
                    >
                        <ArrowRightIcon className="size-4 rotate-180" />
                        Voltar ao processo
                    </Link>

                    <div className="flex items-center gap-2 text-theme-xs text-gray-500 dark:text-gray-400">
                        {saveState === 'salvando' && <span>Salvando rascunho…</span>}
                        {saveState === 'salvo' && (
                            <span className="inline-flex items-center gap-1 text-success-600 dark:text-success-500">
                                <CheckCircleIcon className="size-4 fill-current" /> Rascunho salvo
                            </span>
                        )}
                        {saveState === 'erro' && (
                            <span className="inline-flex items-center gap-1 text-error-500">
                                <AlertIcon className="size-4 fill-current" /> Falha ao salvar
                            </span>
                        )}
                    </div>
                </div>

                {!editavel && (
                    <div className="flex items-start gap-3 rounded-xl border border-success-200 bg-success-50 p-4 dark:border-success-500/30 dark:bg-success-500/15">
                        <CheckCircleIcon className="size-5 shrink-0 fill-current text-success-500" />
                        <p className="text-theme-sm text-gray-600 dark:text-gray-300">
                            Esta revisão está <strong>finalizada</strong> e é imutável (RN-003). Para reeditar, crie uma
                            nova revisão. {ficha.analyst && <>Responsável: {ficha.analyst}. </>}
                            {ficha.finalized_at && <>Finalizada em {formatarDataHora(ficha.finalized_at)}.</>}
                        </p>
                    </div>
                )}

                {!ficha.engine_available && (
                    <div className="flex items-start gap-3 rounded-xl border border-warning-200 bg-warning-50 p-4 dark:border-warning-500/30 dark:bg-warning-500/15">
                        <AlertIcon className="size-5 shrink-0 fill-current text-warning-500" />
                        <p className="text-theme-sm text-gray-600 dark:text-gray-300">
                            <strong>Modo manual:</strong> o motor não pôde pré-analisar este processo (sem dado
                            confiável — ex.: zona urbanística pendente SEDUR). Os campos não têm sugestão automática;
                            a análise é integralmente humana, com fundamentação própria.
                        </p>
                    </div>
                )}

                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="space-y-6 lg:col-span-2">
                        {/* Sugestões de IA (HU-117 resumo + HU-115 alertas) —
                            revisáveis, carregadas sob demanda (deferida) quando a
                            seção entra em tela. O resumo do processo abre o topo da
                            ficha; os alertas seguem logo abaixo. */}
                        <WhenVisible data="sugestoesIa" buffer={200} fallback={<SugestoesIaSkeleton />}>
                            <div className="space-y-6">
                                <ResumoProcessoCard sugestoes={sugestoesIa ?? []} />
                                <MinutaParecerCard
                                    sugestoes={sugestoesIa ?? []}
                                    editavel={editavel}
                                    onAplicar={aplicarMinutaAoParecer}
                                />
                                <AlertasIaCard sugestoes={sugestoesIa ?? []} />
                            </div>
                        </WhenVisible>

                        {/* Enquadramento por CNAE (espelha a ficha SAPS) */}
                        <Card>
                            <CardHeader
                                title="Enquadramento por atividade (CNAE)"
                                description="Sugestão do motor (HU-140) ao lado da decisão do analista. A divergência é destacada e exige justificativa."
                            />
                            <CardContent>
                                {perCnae.length === 0 ? (
                                    <EmptyState
                                        title="Sem CNAEs pré-analisados"
                                        description="Nenhuma atividade foi pré-preenchida pelo motor para este processo."
                                    />
                                ) : (
                                    <ul className="space-y-5">
                                        {perCnae.map((item, indice) => {
                                            const escolhido = item.status_escolhido ?? item.status_sugerido ?? null;
                                            const diverge =
                                                item.status_sugerido != null &&
                                                escolhido != null &&
                                                escolhido !== item.status_sugerido;

                                            return (
                                                <li
                                                    key={`${item.cnae}-${indice}`}
                                                    className="rounded-xl border border-gray-200 p-4 dark:border-gray-800"
                                                >
                                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                                        <div>
                                                            <div className="flex flex-wrap items-center gap-2">
                                                                <span className="font-medium text-gray-800 dark:text-white/90">
                                                                    {item.cnae_formatado ?? item.cnae}
                                                                </span>
                                                                {item.is_primary && (
                                                                    <Badge color="info" size="sm">
                                                                        Principal
                                                                    </Badge>
                                                                )}
                                                            </div>
                                                            {item.grupo_uso && (
                                                                <p className="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">
                                                                    Grupo de uso: {item.grupo_uso}
                                                                </p>
                                                            )}
                                                        </div>
                                                        <div className="text-right">
                                                            <p className="text-theme-xs font-medium tracking-wide text-gray-400 uppercase dark:text-gray-500">
                                                                Sugerido pelo motor
                                                            </p>
                                                            <Badge color={statusColor(item.status_sugerido)} size="sm">
                                                                {item.status_sugerido
                                                                    ? statusLabel(item.status_sugerido)
                                                                    : 'Sem sugestão'}
                                                            </Badge>
                                                        </div>
                                                    </div>

                                                    <div className="mt-4">
                                                        <Label className="mb-1.5">Decisão do analista</Label>
                                                        <StatusEscolhido
                                                            cnae={item.cnae}
                                                            valor={escolhido}
                                                            disabled={!editavel}
                                                            onChange={(status) =>
                                                                atualizarCnae(indice, { status_escolhido: status })
                                                            }
                                                        />
                                                    </div>

                                                    {diverge && (
                                                        <div className="mt-3 flex items-start gap-2 rounded-lg border border-warning-200 bg-warning-50 p-3 dark:border-warning-500/30 dark:bg-warning-500/15">
                                                            <AlertIcon className="size-4 shrink-0 fill-current text-warning-500" />
                                                            <p className="text-theme-xs text-gray-600 dark:text-gray-300">
                                                                Divergência da sugestão do motor — registre a
                                                                justificativa (HU-140/HU-145).
                                                            </p>
                                                        </div>
                                                    )}

                                                    {(item.gatilhos?.length ?? 0) > 0 && (
                                                        <div className="mt-3">
                                                            <p className="text-theme-xs font-medium text-gray-500 dark:text-gray-400">
                                                                Gatilhos
                                                            </p>
                                                            <div className="mt-1 flex flex-wrap gap-1.5">
                                                                {item.gatilhos?.map((gatilho, i) => (
                                                                    <Badge key={i} color="warning" size="sm">
                                                                        {gatilho}
                                                                    </Badge>
                                                                ))}
                                                            </div>
                                                        </div>
                                                    )}

                                                    {(item.fundamentacao?.length ?? 0) > 0 && (
                                                        <ul className="mt-3 list-inside list-disc text-theme-xs text-gray-500 dark:text-gray-400">
                                                            {item.fundamentacao?.map((ref, i) => (
                                                                <li key={i}>{ref}</li>
                                                            ))}
                                                        </ul>
                                                    )}

                                                    <div className="mt-3 grid gap-3 sm:grid-cols-2">
                                                        <DescItem label="Valor TLL">
                                                            {item.valor_tll != null && item.valor_tll !== '' ? (
                                                                <span>{String(item.valor_tll)}</span>
                                                            ) : (
                                                                <span className="text-gray-400 dark:text-gray-500">
                                                                    Pendente (tabela de taxas/DAM)
                                                                </span>
                                                            )}
                                                        </DescItem>
                                                        <DescItem label="Fluxo">
                                                            {item.fluxo ?? '—'}
                                                        </DescItem>
                                                    </div>

                                                    <div className="mt-3">
                                                        <Label htmlFor={`justificativa-${indice}`} className="mb-1.5">
                                                            Justificativa {diverge && <span className="text-error-500">*</span>}
                                                        </Label>
                                                        <Textarea
                                                            id={`justificativa-${indice}`}
                                                            rows={2}
                                                            disabled={!editavel}
                                                            placeholder="Fundamente a decisão desta atividade…"
                                                            value={item.justificativa ?? ''}
                                                            onChange={(valor) =>
                                                                atualizarCnae(indice, { justificativa: valor })
                                                            }
                                                        />
                                                    </div>
                                                </li>
                                            );
                                        })}
                                    </ul>
                                )}
                            </CardContent>
                        </Card>

                        {/* Condicionantes */}
                        <Card>
                            <CardHeader
                                title="Condicionantes"
                                description="Marque as sugeridas pelo motor, acrescente em texto livre ou insira da biblioteca de textos-padrão (HU-085 RN-009)."
                            />
                            <CardContent>
                                {condicionantesSugeridas.length > 0 && (
                                    <div className="mb-4 space-y-2">
                                        <p className="text-theme-xs font-medium text-gray-500 dark:text-gray-400">
                                            Sugeridas pelo motor
                                        </p>
                                        {condicionantesSugeridas.map((sugerida) => (
                                            <Checkbox
                                                key={sugerida}
                                                label={sugerida}
                                                disabled={!editavel}
                                                checked={conditions.includes(sugerida)}
                                                onChange={(marcada) =>
                                                    marcada
                                                        ? adicionarCondicao(sugerida)
                                                        : setConditions((atual) =>
                                                              atual.filter((c) => c !== sugerida),
                                                          )
                                                }
                                            />
                                        ))}
                                    </div>
                                )}

                                {conditions.length > 0 ? (
                                    <ul className="space-y-2">
                                        {conditions.map((condicao, indice) => (
                                            <li
                                                key={`${condicao}-${indice}`}
                                                className="flex items-start justify-between gap-3 rounded-lg border border-gray-200 p-3 dark:border-gray-800"
                                            >
                                                <span className="text-theme-sm text-gray-700 dark:text-gray-300">
                                                    {condicao}
                                                </span>
                                                {editavel && (
                                                    <button
                                                        type="button"
                                                        onClick={() => removerCondicao(indice)}
                                                        aria-label={`Remover condicionante ${indice + 1}`}
                                                        className="shrink-0 text-error-500 transition hover:text-error-600"
                                                    >
                                                        <TrashIcon className="size-4.5" />
                                                    </button>
                                                )}
                                            </li>
                                        ))}
                                    </ul>
                                ) : (
                                    <p className="text-theme-sm text-gray-500 dark:text-gray-400">
                                        Nenhuma condicionante registrada.
                                    </p>
                                )}

                                {editavel && (
                                    <div className="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
                                        <div className="flex-1">
                                            <Label htmlFor="nova-condicao">Adicionar condicionante</Label>
                                            <Input
                                                id="nova-condicao"
                                                type="text"
                                                value={novaCondicao}
                                                placeholder="Descreva a condicionante…"
                                                onChange={(event) => setNovaCondicao(event.target.value)}
                                                onKeyDown={(event) => {
                                                    if (event.key === 'Enter') {
                                                        event.preventDefault();
                                                        adicionarCondicao(novaCondicao);
                                                        setNovaCondicao('');
                                                    }
                                                }}
                                            />
                                        </div>
                                        <div className="flex gap-2">
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() => {
                                                    adicionarCondicao(novaCondicao);
                                                    setNovaCondicao('');
                                                }}
                                            >
                                                Adicionar
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                onClick={() => setPickerParaCondicao(true)}
                                            >
                                                Da biblioteca
                                            </Button>
                                        </div>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* Vagas */}
                        <Card>
                            <CardHeader
                                title="Vagas de estacionamento"
                                description="Compara as vagas informadas pelo requerente com as exigidas pela norma."
                            />
                            <CardContent>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <Label htmlFor="vagas-requeridas">Vagas informadas (requerente)</Label>
                                        <Input
                                            id="vagas-requeridas"
                                            type="number"
                                            min={0}
                                            disabled={!editavel}
                                            value={vagasRequeridas ?? ''}
                                            onChange={(event) =>
                                                setParking((atual) => ({
                                                    ...atual,
                                                    vagas_requeridas:
                                                        event.target.value === '' ? null : Number(event.target.value),
                                                }))
                                            }
                                        />
                                    </div>
                                    <div>
                                        <Label htmlFor="vagas-exigidas">Vagas exigidas (norma)</Label>
                                        <Input
                                            id="vagas-exigidas"
                                            type="number"
                                            min={0}
                                            disabled={!editavel}
                                            value={vagasExigidas ?? ''}
                                            onChange={(event) =>
                                                setParking((atual) => ({
                                                    ...atual,
                                                    vagas_exigidas:
                                                        event.target.value === '' ? null : Number(event.target.value),
                                                }))
                                            }
                                        />
                                    </div>
                                </div>

                                <div className="mt-4 flex flex-wrap items-center justify-between gap-3">
                                    <Checkbox
                                        label="Exige vistoria de vagas"
                                        disabled={!editavel}
                                        checked={parking.vistoria ?? false}
                                        onChange={(marcada) =>
                                            setParking((atual) => ({ ...atual, vistoria: marcada }))
                                        }
                                    />
                                    {vagasConforme === null ? (
                                        <Badge color="light" size="sm">
                                            Veredito pendente (informe as vagas)
                                        </Badge>
                                    ) : vagasConforme ? (
                                        <Badge color="success" size="sm">
                                            Imóvel conforme
                                        </Badge>
                                    ) : (
                                        <Badge color="error" size="sm">
                                            Imóvel não conforme
                                        </Badge>
                                    )}
                                </div>
                            </CardContent>
                        </Card>

                        {/* Parecer */}
                        <Card>
                            <CardHeader
                                title="Parecer técnico"
                                description="Fundamentação da análise. Use a biblioteca de textos-padrão para acelerar (HU-085) ou peça uma minuta de apoio à IA (HU-118)."
                                actions={
                                    editavel ? (
                                        <div className="flex flex-wrap items-center gap-2">
                                            <Button
                                                size="xs"
                                                variant="outline"
                                                onClick={sugerirMinuta}
                                                loading={minuta.processing || minutaStatus === 'aguardando'}
                                            >
                                                Sugerir minuta (IA)
                                            </Button>
                                            <Button size="xs" variant="ghost" onClick={() => setPickerParaParecer(true)}>
                                                Inserir texto-padrão
                                            </Button>
                                        </div>
                                    ) : undefined
                                }
                            />
                            <CardContent>
                                <Textarea
                                    id="parecer-editor"
                                    rows={6}
                                    disabled={!editavel}
                                    placeholder="Redija o parecer técnico…"
                                    value={parecer}
                                    onChange={setParecer}
                                />

                                {editavel && minutaStatus !== 'idle' && (
                                    <p
                                        className={`mt-2 text-theme-xs ${
                                            minutaStatus === 'indisponivel'
                                                ? 'text-warning-600 dark:text-warning-500'
                                                : 'text-gray-500 dark:text-gray-400'
                                        }`}
                                        role="status"
                                        aria-live="polite"
                                    >
                                        {minutaStatus === 'solicitando' && 'Solicitando minuta à IA…'}
                                        {minutaStatus === 'aguardando' &&
                                            'Minuta solicitada — aguardando o processamento da IA. Ela aparecerá em “Minuta de parecer (IA)” no topo, como sugestão a revisar.'}
                                        {minutaStatus === 'pronta' &&
                                            'Minuta sugerida disponível em “Minuta de parecer (IA)” no topo — revise e use “Aplicar ao parecer”.'}
                                        {minutaStatus === 'indisponivel' &&
                                            (minutaMensagem ?? 'Sugestão de minuta indisponível no momento.')}
                                    </p>
                                )}

                                <p className="mt-2 text-theme-xs text-gray-400 dark:text-gray-500">
                                    A minuta da IA é apenas sugestão (HU-118): você a revisa, edita e valida. A IA não decide o
                                    desfecho nem finaliza a ficha (RN-001/RN-003).
                                </p>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Coluna lateral: mini-mapa, precedentes, ações */}
                    <div className="space-y-6">
                        <Card>
                            <CardHeader title="Localização" description="Polígono cadastrado do imóvel." />
                            <CardContent>
                                {temPoligono && centro ? (
                                    <>
                                        <MapaSection
                                            lat={centro.lat}
                                            lng={centro.lng}
                                            zoom={17}
                                            draggable={false}
                                            camadas={[
                                                {
                                                    id: 'imovel',
                                                    type: 'imovel',
                                                    geojson: localizacao!.poligono as GeoJsonObject,
                                                },
                                            ]}
                                        />
                                        <p className="mt-3 text-theme-xs text-gray-500 dark:text-gray-400">
                                            Mapa &copy; OpenStreetMap (ODbL).
                                        </p>
                                    </>
                                ) : (
                                    <div className="flex items-start gap-3 rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                                        <MapPinIcon className="size-5 shrink-0 text-gray-400" />
                                        <p className="text-theme-sm text-gray-500 dark:text-gray-400">
                                            Polígono do imóvel não cadastrado neste processo.
                                        </p>
                                    </div>
                                )}
                                {localizacao?.endereco && (
                                    <p className="mt-3 text-theme-sm text-gray-600 dark:text-gray-300">
                                        {localizacao.endereco}
                                    </p>
                                )}
                                <p className="mt-2 text-theme-xs text-gray-400 dark:text-gray-500">
                                    Zona urbanística e via oficiais seguem pendentes SEDUR (Quadro 10).
                                </p>
                            </CardContent>
                        </Card>

                        <PrecedentesPanel
                            carregando={precedentes.processing}
                            erro={precedentesErro}
                            dados={precedentesData}
                        />

                        <Card>
                            <CardHeader title="Ações" description="Conforme a fase da análise e a permissão." />
                            <CardContent>
                                <div className="flex flex-col gap-2">
                                    {editavel ? (
                                        <>
                                            <Button onClick={salvarRascunho} variant="outline" size="sm">
                                                Salvar rascunho
                                            </Button>
                                            <Button onClick={() => setShowFinalizar(true)} size="sm">
                                                Finalizar ficha
                                            </Button>
                                        </>
                                    ) : (
                                        <>
                                            <Button onClick={() => setShowDecidir(true)} size="sm">
                                                Decidir processo (conforme ficha)
                                            </Button>
                                            <Button onClick={novaRevisao} variant="outline" size="sm" loading={acao.processing}>
                                                Criar nova revisão
                                            </Button>
                                            {podeEmitirTvl && (
                                                <Button onClick={emitirTvl} variant="outline" size="sm" loading={tvl.processing}>
                                                    Emitir / baixar TVL
                                                </Button>
                                            )}
                                        </>
                                    )}

                                    <Button onClick={() => setShowPendencia(true)} variant="ghost" size="sm">
                                        Abrir pendência
                                    </Button>

                                    {podeMalhaFina && (
                                        <Button onClick={() => setShowMalhaFina(true)} variant="ghost" size="sm">
                                            Encaminhar à malha fina
                                        </Button>
                                    )}

                                    {ficha.revision > 1 && (
                                        <Button onClick={() => setShowDiff(true)} variant="ghost" size="sm">
                                            Comparar revisões
                                        </Button>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>

            {/* Picker de textos-padrão */}
            {(pickerParaParecer || pickerParaCondicao) && (
                <TextosPadraoPicker
                    textos={textosPadrao}
                    onSelect={inserirTextoPadrao}
                    onClose={() => {
                        setPickerParaParecer(false);
                        setPickerParaCondicao(false);
                    }}
                />
            )}

            <ConfirmDialog
                isOpen={showFinalizar}
                variant="info"
                title="Finalizar ficha?"
                description="A revisão ficará imutável (RN-003) e as divergências do motor serão registradas. Para reeditar depois, será necessário criar uma nova revisão."
                confirmLabel="Finalizar"
                processing={acao.processing}
                onConfirm={finalizarFicha}
                onClose={() => setShowFinalizar(false)}
            />

            <ConfirmDialog
                isOpen={showDecidir}
                variant="warning"
                title="Concluir a decisão do processo?"
                description="O resultado (deferir/indeferir) é construído a partir da ficha finalizada e o processo é encerrado. A ação é auditada e dispara as comunicações cabíveis."
                confirmLabel="Decidir"
                onConfirm={decidirProcesso}
                onClose={() => setShowDecidir(false)}
            />

            {showPendencia && (
                <Modal isOpen onClose={() => setShowPendencia(false)} className="m-4 max-w-[560px] p-6 lg:p-8">
                    <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90">Abrir pendência</h4>
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        O processo vai para “em pendência” e o requerente é notificado para complementar.
                    </p>
                    <div className="mt-4">
                        <Label htmlFor="descricao-pendencia" required>
                            Descrição da pendência
                        </Label>
                        <Textarea
                            id="descricao-pendencia"
                            rows={4}
                            placeholder="Descreva o que falta…"
                            value={descricaoPendencia}
                            onChange={setDescricaoPendencia}
                        />
                    </div>
                    <div className="mt-6 flex items-center justify-end gap-3">
                        <Button variant="outline" size="sm" onClick={() => setShowPendencia(false)}>
                            Cancelar
                        </Button>
                        <Button
                            size="sm"
                            onClick={abrirPendencia}
                            disabled={descricaoPendencia.trim() === ''}
                            loading={pendenciaProcessing}
                        >
                            Abrir pendência
                        </Button>
                    </div>
                </Modal>
            )}

            {showMalhaFina && (
                <Modal isOpen onClose={() => setShowMalhaFina(false)} className="m-4 max-w-[560px] p-6 lg:p-8">
                    <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90">Encaminhar à malha fina</h4>
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        A malha fina é ortogonal ao status e pode atingir qualquer fase. Informe o motivo (obrigatório).
                    </p>
                    <div className="mt-4">
                        <Label htmlFor="motivo-malha-fina" required>
                            Motivo
                        </Label>
                        <Textarea
                            id="motivo-malha-fina"
                            rows={3}
                            placeholder="Motivo do encaminhamento…"
                            value={motivoMalhaFina}
                            onChange={setMotivoMalhaFina}
                        />
                    </div>
                    <div className="mt-6 flex items-center justify-end gap-3">
                        <Button variant="outline" size="sm" onClick={() => setShowMalhaFina(false)}>
                            Cancelar
                        </Button>
                        <Button
                            size="sm"
                            onClick={encaminharMalhaFina}
                            disabled={motivoMalhaFina.trim() === ''}
                            loading={malhaFinaProcessing}
                        >
                            Encaminhar
                        </Button>
                    </div>
                </Modal>
            )}

            {showDiff && (
                <Modal isOpen onClose={() => setShowDiff(false)} className="m-4 max-h-[90vh] max-w-[640px] overflow-y-auto p-6 lg:p-8">
                    <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90">Comparar revisões</h4>
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Mostra apenas o que mudou entre as revisões (RN-007).
                    </p>
                    <div className="mt-4 flex flex-wrap items-end gap-3">
                        <div className="w-28">
                            <Label htmlFor="diff-de">De (revisão)</Label>
                            <Input
                                id="diff-de"
                                type="number"
                                min={1}
                                max={ficha.revision}
                                value={diffDe}
                                onChange={(event) => setDiffDe(Number(event.target.value))}
                            />
                        </div>
                        <div className="w-28">
                            <Label htmlFor="diff-para">Para (revisão)</Label>
                            <Input
                                id="diff-para"
                                type="number"
                                min={1}
                                max={ficha.revision}
                                value={diffPara}
                                onChange={(event) => setDiffPara(Number(event.target.value))}
                            />
                        </div>
                        <Button size="sm" onClick={compararRevisoes} loading={diff.processing}>
                            Comparar
                        </Button>
                    </div>

                    {diffErro && <p className="mt-4 text-theme-sm text-error-500">{diffErro}</p>}

                    {diffData && <DiffView diff={diffData.diff} />}
                </Modal>
            )}
        </>
    );
}

/** Skeleton da prop deferida de sugestões de IA (resumo HU-117 + alertas HU-115) enquanto carrega sob demanda. */
function SugestoesIaSkeleton() {
    return (
        <div className="space-y-6">
            <Card>
                <CardHeader title="Resumo do processo (IA — sugestão, revise)" description="Carregando o resumo da IA…" />
                <CardContent>
                    <div className="space-y-3" aria-hidden="true">
                        <div className="h-4 w-1/2 animate-pulse rounded bg-gray-100 dark:bg-white/[0.06]" />
                        <div className="h-16 w-full animate-pulse rounded bg-gray-100 dark:bg-white/[0.06]" />
                    </div>
                    <p className="sr-only">Carregando o resumo de inteligência artificial.</p>
                </CardContent>
            </Card>
            <Card>
                <CardHeader title="Alertas de IA (sugestão — revise)" description="Carregando sugestões da IA…" />
                <CardContent>
                    <div className="space-y-3" aria-hidden="true">
                        <div className="h-4 w-2/3 animate-pulse rounded bg-gray-100 dark:bg-white/[0.06]" />
                        <div className="h-20 w-full animate-pulse rounded bg-gray-100 dark:bg-white/[0.06]" />
                    </div>
                    <p className="sr-only">Carregando alertas de inteligência artificial.</p>
                </CardContent>
            </Card>
        </div>
    );
}

/**
 * Card "Resumo do processo" (HU-117): apresenta a síntese gerada pela IA
 * (resumo + pontos-chave + fonte) no TOPO da ficha, sempre marcada como
 * "sugestão — revise". APENAS LEITURA — apoia a leitura do analista, não decide
 * nem antecipa o desfecho (AI-SPEC Failure Mode #1). Lê do mesmo ledger
 * ai_suggestions (prop deferida), filtrando o tipo resumo_processo; mostra a
 * síntese mais recente primeiro (o controller ordena por id desc).
 */
function ResumoProcessoCard({ sugestoes }: { sugestoes: SugestaoIa[] }) {
    const resumos = sugestoes.filter((sugestao) => sugestao.type === 'resumo_processo');

    return (
        <Card>
            <CardHeader
                title="Resumo do processo (IA — sugestão, revise)"
                description="Síntese do processo (motor, enquadramento, inconsistências e pendências) gerada pela IA para apoiar a leitura. Não decide nem antecipa o desfecho."
            />
            <CardContent>
                {resumos.length === 0 ? (
                    <p className="text-theme-sm text-gray-500 dark:text-gray-400">
                        Nenhum resumo de IA para este processo.
                    </p>
                ) : (
                    <ul className="space-y-4" aria-label="Resumo do processo sugerido pela IA">
                        {resumos.map((sugestao) => {
                            const resumo = typeof sugestao.output.resumo === 'string' ? sugestao.output.resumo : '';
                            const pontos = Array.isArray(sugestao.output.pontos_chave) ? sugestao.output.pontos_chave : [];
                            const fonte = typeof sugestao.output.fonte === 'string' ? sugestao.output.fonte : null;

                            return (
                                <li
                                    key={sugestao.id}
                                    className="rounded-xl border border-brand-200 bg-brand-50 p-4 dark:border-brand-500/30 dark:bg-brand-500/10"
                                >
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <Badge color="info" size="sm">
                                            Sugestão — revise
                                        </Badge>
                                        <Badge color={sugestao.status === 'escalada_humano' ? 'error' : 'light'} size="sm">
                                            {sugestao.status_label}
                                        </Badge>
                                    </div>

                                    {resumo === '' ? (
                                        <p className="mt-3 text-theme-sm text-gray-600 dark:text-gray-300">
                                            A IA não retornou texto de resumo nesta geração.
                                        </p>
                                    ) : (
                                        <p className="mt-3 text-theme-sm whitespace-pre-line text-gray-800 dark:text-white/90">
                                            {resumo}
                                        </p>
                                    )}

                                    {pontos.length > 0 && (
                                        <div className="mt-3">
                                            <p className="text-theme-xs font-medium tracking-wide text-gray-400 uppercase dark:text-gray-500">
                                                Pontos de atenção
                                            </p>
                                            <ul className="mt-1 list-inside list-disc text-theme-sm text-gray-700 dark:text-gray-300">
                                                {pontos.map((ponto, indice) => (
                                                    <li key={indice}>{ponto}</li>
                                                ))}
                                            </ul>
                                        </div>
                                    )}

                                    {fonte && (
                                        <p className="mt-3 text-theme-xs text-gray-500 dark:text-gray-400">
                                            Fonte: {fonte}
                                        </p>
                                    )}
                                </li>
                            );
                        })}
                    </ul>
                )}

                <p className="mt-4 text-theme-xs text-gray-400 dark:text-gray-500">
                    Apoio à leitura, sempre revisável — não substitui a análise nem antecipa a decisão (RN-001/RN-004).
                </p>
            </CardContent>
        </Card>
    );
}

/** Nível de confiança da IA → rótulo em pt-BR (texto, nunca só cor — eMAG). */
function confiancaLabel(confianca: string): string {
    if (confianca === 'alta') {
        return 'Alta';
    }

    if (confianca === 'media') {
        return 'Média';
    }

    if (confianca === 'baixa') {
        return 'Baixa';
    }

    return confianca;
}

/**
 * Card "Minuta de parecer" (HU-118 — Failure Mode #1): exibe os rascunhos de
 * parecer sugeridos pela IA (minuta + fundamentação do motor + confiança +
 * fonte), sempre marcados como "sugestão — revise". A ação "Aplicar ao parecer"
 * copia o texto para o editor do parecer (client-side, editável) — NUNCA grava
 * decisão nem finaliza (RN-001/RN-003). Só aparece quando há minuta sugerida,
 * para não poluir a ficha; lê do mesmo ledger ai_suggestions (prop deferida).
 */
function MinutaParecerCard({
    sugestoes,
    editavel,
    onAplicar,
}: {
    sugestoes: SugestaoIa[];
    editavel: boolean;
    onAplicar: (minuta: string) => void;
}) {
    const minutas = sugestoes.filter((sugestao) => sugestao.type === 'parecer');

    if (minutas.length === 0) {
        return null;
    }

    return (
        <Card>
            <CardHeader
                title="Minuta de parecer (IA — sugestão, revise)"
                description="Rascunho de parecer proposto pela IA com base na pré-análise do motor. É apoio, não decisão: revise, edite e valide."
            />
            <CardContent>
                <ul className="space-y-4" aria-label="Minutas de parecer sugeridas pela IA">
                    {minutas.map((sugestao) => {
                        const minutaTexto = typeof sugestao.output.minuta === 'string' ? sugestao.output.minuta : '';
                        const fundamentacao =
                            typeof sugestao.output.fundamentacao === 'string' ? sugestao.output.fundamentacao : null;
                        const fonte = typeof sugestao.output.fonte === 'string' ? sugestao.output.fonte : null;

                        return (
                            <li
                                key={sugestao.id}
                                className="rounded-xl border border-brand-200 bg-brand-50 p-4 dark:border-brand-500/30 dark:bg-brand-500/10"
                            >
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <Badge color="info" size="sm">
                                        Sugestão — revise
                                    </Badge>
                                    <div className="flex flex-wrap items-center gap-2">
                                        {sugestao.confianca && (
                                            <Badge color="light" size="sm">
                                                Confiança: {confiancaLabel(sugestao.confianca)}
                                            </Badge>
                                        )}
                                        <Badge color={sugestao.status === 'escalada_humano' ? 'error' : 'light'} size="sm">
                                            {sugestao.status_label}
                                        </Badge>
                                    </div>
                                </div>

                                {minutaTexto === '' ? (
                                    <p className="mt-3 text-theme-sm text-gray-600 dark:text-gray-300">
                                        A IA não retornou texto de minuta nesta geração.
                                    </p>
                                ) : (
                                    <p className="mt-3 text-theme-sm whitespace-pre-line text-gray-800 dark:text-white/90">
                                        {minutaTexto}
                                    </p>
                                )}

                                {fundamentacao && (
                                    <div className="mt-3">
                                        <p className="text-theme-xs font-medium tracking-wide text-gray-400 uppercase dark:text-gray-500">
                                            Fundamentação (do motor)
                                        </p>
                                        <p className="mt-1 text-theme-sm whitespace-pre-line text-gray-700 dark:text-gray-300">
                                            {fundamentacao}
                                        </p>
                                    </div>
                                )}

                                {fonte && (
                                    <p className="mt-3 text-theme-xs text-gray-500 dark:text-gray-400">
                                        Fonte: {fonte}
                                    </p>
                                )}

                                {editavel && minutaTexto !== '' && (
                                    <div className="mt-4">
                                        <Button size="sm" variant="outline" onClick={() => onAplicar(minutaTexto)}>
                                            Aplicar ao parecer
                                        </Button>
                                    </div>
                                )}
                            </li>
                        );
                    })}
                </ul>

                <p className="mt-4 text-theme-xs text-gray-400 dark:text-gray-500">
                    “Aplicar ao parecer” copia o texto para o editor do parecer, onde você revisa e ajusta — nada é gravado
                    nem decidido automaticamente (RN-001).
                </p>
            </CardContent>
        </Card>
    );
}

/** Severidade da inconsistência → cor e rótulo em pt-BR (texto, nunca só cor). */
function severidadeBadge(severidade: SeveridadeIa): { cor: 'error' | 'warning' | 'light'; label: string } {
    if (severidade === 'alta') {
        return { cor: 'error', label: 'Alta' };
    }

    if (severidade === 'media') {
        return { cor: 'warning', label: 'Média' };
    }

    return { cor: 'light', label: 'Baixa' };
}

/** Rótulo legível do campo apontado pela IA (ex.: "endereco_numero" → "Endereco numero"). */
function rotuloCampo(campo: string): string {
    const limpo = campo.replace(/_/g, ' ').trim();

    if (limpo === '') {
        return 'Campo';
    }

    return limpo.charAt(0).toUpperCase() + limpo.slice(1);
}

/**
 * Card "Alertas de IA" (HU-115): lista as inconsistências detectadas
 * (campo/declarado/documento/severidade/fonte) sempre marcadas como
 * "sugestão — revise". SINALIZA para a revisão humana — não decide nem penaliza
 * (RN-004) — e COMPLEMENTA as validações determinísticas, sem substituí-las
 * (RN-005). Espelha o padrão visual de divergência da própria ficha.
 */
function AlertasIaCard({ sugestoes }: { sugestoes: SugestaoIa[] }) {
    const inconsistencias = sugestoes.filter((sugestao) => sugestao.type === 'inconsistencias');

    return (
        <Card>
            <CardHeader
                title="Alertas de IA (sugestão — revise)"
                description="Divergências apontadas pela IA entre o que foi declarado e o que os documentos/foto mostram. Complementa, não substitui, as validações técnicas."
            />
            <CardContent>
                {inconsistencias.length === 0 ? (
                    <p className="text-theme-sm text-gray-500 dark:text-gray-400">
                        Nenhum alerta de IA para este processo.
                    </p>
                ) : (
                    <ul className="space-y-4" aria-label="Alertas de inconsistência sugeridos pela IA">
                        {inconsistencias.map((sugestao) => {
                            const itens = sugestao.output.inconsistencias ?? [];
                            const fonte = typeof sugestao.output.fonte === 'string' ? sugestao.output.fonte : null;

                            return (
                                <li
                                    key={sugestao.id}
                                    className="rounded-xl border border-warning-200 bg-warning-50 p-4 dark:border-warning-500/30 dark:bg-warning-500/15"
                                >
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <span className="inline-flex items-center gap-2">
                                            <AlertIcon className="size-4 shrink-0 fill-current text-warning-500" />
                                            <Badge color="warning" size="sm">
                                                Sugestão — revise
                                            </Badge>
                                        </span>
                                        <Badge color={sugestao.status === 'escalada_humano' ? 'error' : 'light'} size="sm">
                                            {sugestao.status_label}
                                        </Badge>
                                    </div>

                                    {itens.length === 0 ? (
                                        <p className="mt-3 text-theme-sm text-gray-600 dark:text-gray-300">
                                            A IA não apontou divergências específicas nesta verificação.
                                        </p>
                                    ) : (
                                        <ul className="mt-3 space-y-3">
                                            {itens.map((item, indice) => {
                                                const severidade = severidadeBadge(item.severidade);

                                                return (
                                                    <li
                                                        key={indice}
                                                        className="rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-800 dark:bg-gray-900"
                                                    >
                                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                                            <span className="text-theme-sm font-medium text-gray-800 dark:text-white/90">
                                                                {rotuloCampo(item.campo)}
                                                            </span>
                                                            <Badge color={severidade.cor} size="sm">
                                                                Severidade: {severidade.label}
                                                            </Badge>
                                                        </div>
                                                        <dl className="mt-2 grid gap-2 sm:grid-cols-2">
                                                            <div>
                                                                <dt className="text-theme-xs font-medium tracking-wide text-gray-400 uppercase dark:text-gray-500">
                                                                    Declarado
                                                                </dt>
                                                                <dd className="mt-1 text-theme-sm text-gray-800 dark:text-white/90">
                                                                    {item.declarado}
                                                                </dd>
                                                            </div>
                                                            <div>
                                                                <dt className="text-theme-xs font-medium tracking-wide text-gray-400 uppercase dark:text-gray-500">
                                                                    No documento
                                                                </dt>
                                                                <dd className="mt-1 text-theme-sm text-gray-800 dark:text-white/90">
                                                                    {item.documento}
                                                                </dd>
                                                            </div>
                                                        </dl>
                                                    </li>
                                                );
                                            })}
                                        </ul>
                                    )}

                                    {fonte && (
                                        <p className="mt-3 text-theme-xs text-gray-500 dark:text-gray-400">
                                            Fonte: {fonte}
                                        </p>
                                    )}
                                </li>
                            );
                        })}
                    </ul>
                )}

                <p className="mt-4 text-theme-xs text-gray-400 dark:text-gray-500">
                    Sinais para revisão humana — não decidem nem penalizam (RN-004). As validações de lote (GIS) e
                    Receita seguem pendentes SEDUR (HU-037/105) e não são supridas por estes alertas.
                </p>
            </CardContent>
        </Card>
    );
}

/** Painel de precedentes (HU-142): processos do imóvel + estatística do CNAE na zona. */
function PrecedentesPanel({
    carregando,
    erro,
    dados,
}: {
    carregando: boolean;
    erro: string | null;
    dados: PrecedentesResponse | null;
}) {
    return (
        <Card>
            <CardHeader title="Precedentes" description="Histórico do imóvel e estatística do CNAE na zona (HU-142)." />
            <CardContent>
                {carregando && (
                    <div className="space-y-2">
                        <div className="h-4 w-3/4 animate-pulse rounded bg-gray-100 dark:bg-white/[0.06]" />
                        <div className="h-4 w-1/2 animate-pulse rounded bg-gray-100 dark:bg-white/[0.06]" />
                    </div>
                )}

                {!carregando && erro && <p className="text-theme-sm text-error-500">{erro}</p>}

                {!carregando && !erro && dados && (
                    <div className="space-y-4">
                        <div>
                            <p className="text-theme-xs font-medium tracking-wide text-gray-400 uppercase dark:text-gray-500">
                                Processos do imóvel
                            </p>
                            {dados.imovel.length === 0 ? (
                                <p className="mt-1 text-theme-sm text-gray-500 dark:text-gray-400">
                                    Sem precedentes para este imóvel.
                                </p>
                            ) : (
                                <ul className="mt-2 space-y-2">
                                    {dados.imovel.map((precedente) => (
                                        <li
                                            key={precedente.viability_request_id}
                                            className="rounded-lg border border-gray-200 p-3 dark:border-gray-800"
                                        >
                                            <div className="flex flex-wrap items-center justify-between gap-2">
                                                <Link
                                                    href={`/gestao/processos/${precedente.viability_request_id}`}
                                                    className="text-theme-sm font-medium text-brand-500 hover:text-brand-600 dark:text-brand-400"
                                                >
                                                    {precedente.protocol_number ?? `#${precedente.viability_request_id}`}
                                                </Link>
                                                {precedente.outcome && (
                                                    <Badge color={statusColor(precedente.outcome)} size="sm">
                                                        {statusLabel(precedente.outcome)}
                                                    </Badge>
                                                )}
                                            </div>
                                            <p className="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">
                                                {[precedente.service_type, precedente.analyst, formatarDataHora(precedente.decided_at)]
                                                    .filter(Boolean)
                                                    .join(' · ')}
                                            </p>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>

                        <div>
                            <p className="text-theme-xs font-medium tracking-wide text-gray-400 uppercase dark:text-gray-500">
                                CNAE na zona
                            </p>
                            {dados.cnae_zona.disponivel ? (
                                <p className="mt-1 text-theme-sm text-gray-600 dark:text-gray-300">
                                    Últimos {dados.cnae_zona.janela_meses} meses:{' '}
                                    <span className="font-medium text-success-600 dark:text-success-500">
                                        {dados.cnae_zona.deferidos ?? 0} deferimentos
                                    </span>
                                    ,{' '}
                                    <span className="font-medium text-error-600 dark:text-error-500">
                                        {dados.cnae_zona.indeferidos ?? 0} indeferimentos
                                    </span>{' '}
                                    (de {dados.cnae_zona.total ?? 0}).
                                </p>
                            ) : (
                                <p className="mt-1 text-theme-sm text-gray-500 dark:text-gray-400">
                                    {dados.cnae_zona.motivo ?? 'Estatística da zona indisponível (zona pendente SEDUR).'}
                                </p>
                            )}
                        </div>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

/** Renderiza o diff {campo: {de, para}} de forma legível. */
function DiffView({ diff }: { diff: Record<string, unknown> }) {
    const entradas = Object.entries(diff);

    if (entradas.length === 0) {
        return (
            <p className="mt-4 text-theme-sm text-gray-500 dark:text-gray-400">
                As revisões são idênticas — nenhuma diferença.
            </p>
        );
    }

    return (
        <div className="mt-4 space-y-3">
            {entradas.map(([campo, valor]) => (
                <div key={campo} className="rounded-lg border border-gray-200 p-3 dark:border-gray-800">
                    <p className="text-theme-xs font-medium text-gray-500 uppercase dark:text-gray-400">{campo}</p>
                    {ehDiffValor(valor) ? (
                        <DiffLinha valor={valor} />
                    ) : (
                        <div className="mt-2 space-y-2">
                            {Object.entries(valor as Record<string, unknown>).map(([subcampo, subvalor]) => (
                                <div key={subcampo}>
                                    <p className="text-theme-xs text-gray-400 dark:text-gray-500">{subcampo}</p>
                                    {ehDiffValor(subvalor) ? (
                                        <DiffLinha valor={subvalor} />
                                    ) : (
                                        <pre className="overflow-x-auto text-theme-xs text-gray-600 dark:text-gray-300">
                                            {JSON.stringify(subvalor, null, 2)}
                                        </pre>
                                    )}
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            ))}
        </div>
    );
}

function ehDiffValor(valor: unknown): valor is DiffValor {
    return typeof valor === 'object' && valor !== null && 'de' in valor && 'para' in valor;
}

function DiffLinha({ valor }: { valor: DiffValor }) {
    return (
        <div className="mt-1 grid gap-2 sm:grid-cols-2">
            <div className="rounded bg-error-50 p-2 text-theme-xs text-gray-700 dark:bg-error-500/10 dark:text-gray-300">
                <span className="font-medium">De:</span> {formatarDiffValor(valor.de)}
            </div>
            <div className="rounded bg-success-50 p-2 text-theme-xs text-gray-700 dark:bg-success-500/10 dark:text-gray-300">
                <span className="font-medium">Para:</span> {formatarDiffValor(valor.para)}
            </div>
        </div>
    );
}

function formatarDiffValor(valor: unknown): string {
    if (valor === null || valor === undefined || valor === '') {
        return '—';
    }

    if (typeof valor === 'object') {
        return JSON.stringify(valor);
    }

    return String(valor);
}

/** Modal de seleção de trechos da biblioteca de textos-padrão (HU-085). */
function TextosPadraoPicker({
    textos,
    onSelect,
    onClose,
}: {
    textos: TextoPadrao[];
    onSelect: (conteudo: string) => void;
    onClose: () => void;
}) {
    const [categoria, setCategoria] = useState('');

    const categorias = useMemo(() => Array.from(new Set(textos.map((texto) => texto.category))), [textos]);
    const filtrados = categoria === '' ? textos : textos.filter((texto) => texto.category === categoria);

    return (
        <Modal isOpen onClose={onClose} className="m-4 max-h-[90vh] max-w-[640px] overflow-y-auto p-6 lg:p-8">
            <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90">Biblioteca de textos-padrão</h4>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Selecione um trecho pré-aprovado para inserir.
            </p>

            {categorias.length > 0 && (
                <div className="mt-4 w-56">
                    <Select
                        value={categoria}
                        onChange={setCategoria}
                        placeholder="Todas as categorias"
                        options={categorias.map((cat) => ({ value: cat, label: cat }))}
                    />
                </div>
            )}

            <div className="mt-4 space-y-2">
                {filtrados.length === 0 ? (
                    <EmptyState
                        title="Nenhum texto-padrão ativo"
                        description="Cadastre trechos na administração de textos-padrão para reusá-los aqui."
                    />
                ) : (
                    filtrados.map((texto) => (
                        <button
                            key={texto.id}
                            type="button"
                            onClick={() => onSelect(texto.content)}
                            className="w-full rounded-lg border border-gray-200 p-3 text-left transition hover:border-brand-300 hover:bg-brand-50 dark:border-gray-800 dark:hover:border-brand-800 dark:hover:bg-brand-500/10"
                        >
                            <div className="flex items-center justify-between gap-2">
                                <Badge color="light" size="sm">
                                    {texto.category}
                                </Badge>
                                <span className="text-theme-xs text-gray-400 dark:text-gray-500">v{texto.version}</span>
                            </div>
                            <p className="mt-2 text-theme-sm text-gray-700 dark:text-gray-300">{texto.content}</p>
                        </button>
                    ))
                )}
            </div>

            <div className="mt-6 flex items-center justify-end gap-3">
                <Button variant="outline" size="sm" onClick={onClose}>
                    Fechar
                </Button>
            </div>
        </Modal>
    );
}

FichaAnaliseShow.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
