import { Head, Link, router, useForm } from '@inertiajs/react';
import { useRef, useState } from 'react';
import type { ReactNode } from 'react';
import PageHeader from '@/components/app/page-header';
import Input from '@/components/form/input';
import Label from '@/components/form/label';
import Select from '@/components/form/select';
import { AlertIcon, CheckCircleIcon, CloseIcon, ShieldIcon } from '@/components/icons';
import Badge from '@/components/ui/badge';
import Button from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import DataTable from '@/components/ui/data-table/data-table';
import PerPageSelect from '@/components/ui/data-table/per-page-select';
import type { ColumnDef } from '@/components/ui/data-table/types';
import EmptyState from '@/components/ui/empty-state';
import KpiCard from '@/components/ui/kpi-card';
import { Modal } from '@/components/ui/modal';
import Pagination, { type PaginationLink } from '@/components/ui/pagination';
import TableAction from '@/components/ui/table-action';
import GestaoLayout from '@/layouts/gestao-layout';

type BadgeColor = 'primary' | 'success' | 'error' | 'warning' | 'info' | 'light' | 'dark';

interface SelectOption {
    value: string;
    label: string;
}

/** Par value+label de enum (severity/status) exposto pelo AbuseAlertResource. */
interface ValorRotulo {
    value: string;
    label: string;
}

interface ProcessoResumo {
    id: number;
    protocol_number: string | null;
}

interface ResolucaoAlerta {
    resolved_by: { id: number; nome: string | null } | null;
    resolved_at: string | null;
    justification: string | null;
}

/** Alerta de abuso (shape do AbuseAlertResource — 12-09). */
interface AbuseAlertItem {
    id: number;
    rule_key: string;
    severity: ValorRotulo;
    status: ValorRotulo;
    detected_at: string | null;
    window: { start: string | null; end: string | null };
    evidence: Record<string, unknown> | null;
    processo: ProcessoResumo | null;
    encaminhado_malha_fina: boolean;
    resolucao: ResolucaoAlerta | null;
}

interface EfetividadeRegra {
    rule_key: string;
    gerados: number;
    confirmados: number;
    descartados: number;
    abertos: number;
    taxa: number | null;
}

interface Efetividade {
    geral: Omit<EfetividadeRegra, 'rule_key'>;
    por_regra: EfetividadeRegra[];
}

interface Filtros {
    rule_key: string;
    severity: string;
    status: string;
    data_de: string;
    data_ate: string;
    per_page: number;
}

interface AbusoIndexProps {
    alertas: {
        data: AbuseAlertItem[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
    efetividade: Efetividade;
    filtros: Filtros;
    perPageOptions: number[];
    ruleKeyOptions: string[];
    severityOptions: SelectOption[];
    statusOptions: SelectOption[];
}

type FiltrosForm = Omit<Filtros, 'per_page'>;
type AcaoResolucao = 'confirmar' | 'descartar';

const URL_PAINEL = '/gestao/abuso';

/** Formata a data-hora ISO para o padrão pt-BR (dd/mm/aaaa hh:mm). */
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

/** Número inteiro formatado em pt-BR. */
function formatarNumero(valor: number | null | undefined): string {
    if (typeof valor !== 'number' || Number.isNaN(valor)) {
        return '—';
    }

    return valor.toLocaleString('pt-BR');
}

/**
 * Taxa de confirmação (RN-005). `null` = sem alertas gerados: a tela exibe um
 * travessão, NUNCA um número inventado.
 */
function formatarTaxa(taxa: number | null): string {
    if (taxa === null || typeof taxa !== 'number' || Number.isNaN(taxa)) {
        return '—';
    }

    return `${taxa.toLocaleString('pt-BR', { maximumFractionDigits: 1 })}%`;
}

/** Cor do badge por severidade: baixa=neutro, média=alerta, alta=erro. */
function severityColor(value: string): BadgeColor {
    if (value === 'alta') {
        return 'error';
    }

    if (value === 'media') {
        return 'warning';
    }

    return 'light';
}

/** Cor do badge por status do alerta (triagem), nunca do processo. */
function statusColor(value: string): BadgeColor {
    if (value === 'confirmado') {
        return 'error';
    }

    if (value === 'aberto') {
        return 'warning';
    }

    return 'light';
}

/**
 * Resumo legível das evidências já minimizadas pelo detector (total/limite/ids…):
 * só escalares e contagem de listas, sem despejar estrutura crua. SSR-safe.
 */
function resumoEvidencia(evidence: Record<string, unknown> | null): { chave: string; valor: string }[] {
    if (!evidence || typeof evidence !== 'object') {
        return [];
    }

    const itens: { chave: string; valor: string }[] = [];

    for (const [chave, valor] of Object.entries(evidence)) {
        if (valor === null || valor === undefined) {
            continue;
        }

        if (typeof valor === 'string' || typeof valor === 'number') {
            itens.push({ chave, valor: String(valor) });
        } else if (typeof valor === 'boolean') {
            itens.push({ chave, valor: valor ? 'sim' : 'não' });
        } else if (Array.isArray(valor)) {
            itens.push({ chave, valor: String(valor.length) });
        }

        if (itens.length >= 5) {
            break;
        }
    }

    return itens;
}

const colunasEfetividade: ColumnDef<EfetividadeRegra>[] = [
    {
        id: 'rule_key',
        header: 'Regra',
        cellClassName: 'font-medium whitespace-nowrap text-gray-800 dark:text-white/90',
        cell: (linha) => linha.rule_key,
    },
    {
        id: 'gerados',
        header: 'Gerados',
        align: 'end',
        cellClassName: 'whitespace-nowrap',
        cell: (linha) => formatarNumero(linha.gerados),
    },
    {
        id: 'confirmados',
        header: 'Confirmados',
        align: 'end',
        cellClassName: 'whitespace-nowrap',
        cell: (linha) => formatarNumero(linha.confirmados),
    },
    {
        id: 'descartados',
        header: 'Descartados',
        align: 'end',
        cellClassName: 'whitespace-nowrap',
        cell: (linha) => formatarNumero(linha.descartados),
    },
    {
        id: 'abertos',
        header: 'Abertos',
        align: 'end',
        cellClassName: 'whitespace-nowrap',
        cell: (linha) => formatarNumero(linha.abertos),
    },
    {
        id: 'taxa',
        header: 'Taxa de confirmação',
        align: 'end',
        cellClassName: 'whitespace-nowrap font-medium text-gray-800 dark:text-white/90',
        cell: (linha) => formatarTaxa(linha.taxa),
    },
];

/**
 * Painel humano de alertas de abuso (página gestao/abuso/index, HU-149): consome
 * o backend de 12-09 — lista filtrável e paginada (server-driven), o indicador de
 * EFETIVIDADE (confirmados ÷ gerados, RN-005) e a resolução confirmar/descartar
 * com justificativa OBRIGATÓRIA. ANTI-FACHADA (CA-02): a resolução é revisão
 * humana do ALERTA — NUNCA altera, indefere ou pune o processo, e a malha fina é
 * ortogonal (decidida pelo motor). Defensivo para o SSR não quebrar com props
 * vazias/ausentes (lição da Fase 9).
 */
export default function AbusoIndex({
    alertas,
    efetividade,
    filtros,
    perPageOptions,
    ruleKeyOptions,
    severityOptions,
    statusOptions,
}: AbusoIndexProps) {
    const [form, setForm] = useState<FiltrosForm>({
        rule_key: filtros?.rule_key ?? '',
        severity: filtros?.severity ?? '',
        status: filtros?.status ?? '',
        data_de: filtros?.data_de ?? '',
        data_ate: filtros?.data_ate ?? '',
    });
    const [perPage, setPerPage] = useState(filtros?.per_page ?? 20);
    const [processing, setProcessing] = useState(false);

    const stateRef = useRef({ form, perPage });
    stateRef.current = { form, perPage };

    const [resolucao, setResolucao] = useState<{ alerta: AbuseAlertItem; acao: AcaoResolucao } | null>(null);
    const resolverForm = useForm<{ justification: string }>({ justification: '' });

    /** Visita server-driven: monta os params (omitindo vazios) e navega. */
    function visitar(state: { form: FiltrosForm; perPage: number }) {
        const params: Record<string, string | number> = { per_page: state.perPage };

        (Object.keys(state.form) as (keyof FiltrosForm)[]).forEach((chave) => {
            const valor = state.form[chave];

            if (valor && valor.trim() !== '') {
                params[chave] = valor;
            }
        });

        router.get(URL_PAINEL, params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
        });
    }

    function setFiltro(chave: keyof FiltrosForm, valor: string) {
        const proximo = { ...stateRef.current.form, [chave]: valor };
        setForm(proximo);
        visitar({ ...stateRef.current, form: proximo });
    }

    function trocarPerPage(novo: number) {
        setPerPage(novo);
        visitar({ ...stateRef.current, perPage: novo });
    }

    function limparFiltros() {
        const vazio: FiltrosForm = { rule_key: '', severity: '', status: '', data_de: '', data_ate: '' };
        setForm(vazio);
        visitar({ ...stateRef.current, form: vazio });
    }

    function abrirResolucao(alerta: AbuseAlertItem, acao: AcaoResolucao) {
        resolverForm.clearErrors();
        resolverForm.setData('justification', '');
        setResolucao({ alerta, acao });
    }

    function fecharResolucao() {
        setResolucao(null);
        resolverForm.reset();
        resolverForm.clearErrors();
    }

    function submeterResolucao(evento: React.FormEvent) {
        evento.preventDefault();

        if (!resolucao) {
            return;
        }

        // Justificativa OBRIGATÓRIA validada pelo backend (ResolverAbuseAlertRequest):
        // o erro retornado é exibido abaixo do campo. Server-driven, sem fachada.
        resolverForm.post(`${URL_PAINEL}/${resolucao.alerta.id}/${resolucao.acao}`, {
            preserveScroll: true,
            onSuccess: () => {
                setResolucao(null);
                resolverForm.reset();
            },
        });
    }

    const geral = efetividade?.geral ?? { gerados: 0, confirmados: 0, descartados: 0, abertos: 0, taxa: null };
    const porRegra = Array.isArray(efetividade?.por_regra) ? efetividade.por_regra : [];

    const linhas = Array.isArray(alertas?.data) ? alertas.data : [];
    const filtrando = (Object.keys(form) as (keyof FiltrosForm)[]).some((chave) => (form[chave] ?? '').trim() !== '');

    const ruleKeyFiltroOptions: SelectOption[] = (Array.isArray(ruleKeyOptions) ? ruleKeyOptions : []).map((chave) => ({
        value: chave,
        label: chave,
    }));
    const severitySelect = Array.isArray(severityOptions) ? severityOptions : [];
    const statusSelect = Array.isArray(statusOptions) ? statusOptions : [];

    const colunasAlertas: ColumnDef<AbuseAlertItem>[] = [
        {
            id: 'regra',
            header: 'Regra / severidade',
            cell: (alerta) => (
                <div className="flex flex-col gap-1.5">
                    <span className="font-medium text-gray-800 dark:text-white/90">{alerta.rule_key}</span>
                    <Badge color={severityColor(alerta.severity.value)} size="sm">
                        {alerta.severity.label}
                    </Badge>
                </div>
            ),
        },
        {
            id: 'status',
            header: 'Status',
            cellClassName: 'whitespace-nowrap',
            cell: (alerta) => (
                <Badge color={statusColor(alerta.status.value)} size="sm">
                    {alerta.status.label}
                </Badge>
            ),
        },
        {
            id: 'processo',
            header: 'Processo',
            cell: (alerta) => (
                <div className="flex flex-col gap-1.5">
                    {alerta.processo ? (
                        <Link
                            href={`/gestao/processos/${alerta.processo.id}`}
                            className="text-theme-sm font-medium text-brand-500 transition hover:text-brand-600 dark:text-brand-400"
                        >
                            {alerta.processo.protocol_number ?? `#${alerta.processo.id}`}
                        </Link>
                    ) : (
                        <span className="text-gray-400 dark:text-gray-500">—</span>
                    )}
                    {alerta.encaminhado_malha_fina && (
                        <Badge color="warning" size="sm">
                            Em malha fina
                        </Badge>
                    )}
                </div>
            ),
        },
        {
            id: 'evidencia',
            header: 'Evidência',
            cell: (alerta) => {
                const itens = resumoEvidencia(alerta.evidence);

                if (itens.length === 0) {
                    return <span className="text-gray-400 dark:text-gray-500">—</span>;
                }

                return (
                    <div className="flex flex-wrap gap-1">
                        {itens.map((item) => (
                            <span
                                key={item.chave}
                                className="inline-flex items-center gap-1 rounded-md bg-gray-100 px-2 py-0.5 text-theme-xs text-gray-600 dark:bg-white/5 dark:text-gray-300"
                            >
                                {item.chave}: <strong className="font-medium">{item.valor}</strong>
                            </span>
                        ))}
                    </div>
                );
            },
        },
        {
            id: 'detectado',
            header: 'Detectado em',
            cellClassName: 'whitespace-nowrap text-gray-700 dark:text-gray-300',
            cell: (alerta) => formatarDataHora(alerta.detected_at),
        },
        {
            id: 'resolucao',
            header: 'Resolução',
            cell: (alerta) => {
                if (!alerta.resolucao) {
                    return <span className="text-gray-400 dark:text-gray-500">Em aberto</span>;
                }

                return (
                    <div className="flex max-w-[240px] flex-col gap-0.5">
                        <span className="text-theme-xs text-gray-600 dark:text-gray-300">
                            {alerta.resolucao.resolved_by?.nome ?? 'Sistema'} • {formatarDataHora(alerta.resolucao.resolved_at)}
                        </span>
                        {alerta.resolucao.justification && (
                            <span
                                className="line-clamp-2 text-theme-xs text-gray-400 dark:text-gray-500"
                                title={alerta.resolucao.justification}
                            >
                                {alerta.resolucao.justification}
                            </span>
                        )}
                    </div>
                );
            },
        },
        {
            id: 'acoes',
            header: 'Ações',
            align: 'end',
            cellClassName: 'whitespace-nowrap',
            cell: (alerta) => (
                <div className="flex justify-end gap-2">
                    <TableAction tone="brand" onClick={() => abrirResolucao(alerta, 'confirmar')}>
                        Confirmar
                    </TableAction>
                    <TableAction tone="neutral" onClick={() => abrirResolucao(alerta, 'descartar')}>
                        Descartar
                    </TableAction>
                </div>
            ),
        },
    ];

    const acaoLabel = resolucao?.acao === 'confirmar' ? 'Confirmar alerta' : 'Descartar alerta';

    return (
        <>
            <Head title="Alertas de abuso" />
            <PageHeader title="Alertas de abuso" breadcrumbs={[{ label: 'Painel', href: '/gestao' }]} />

            <div className="space-y-6">
                {/* Anti-fachada (CA-02): a resolução é revisão humana, nunca punição do processo. */}
                <div className="flex items-start gap-3 rounded-xl border border-blue-light-200 bg-blue-light-50 p-4 dark:border-blue-light-500/30 dark:bg-blue-light-500/15">
                    <ShieldIcon className="size-5 shrink-0 text-blue-light-500" />
                    <div className="text-theme-sm text-gray-600 dark:text-gray-300">
                        <p className="font-medium text-gray-800 dark:text-white/90">Revisão humana — nunca punição automática</p>
                        <p className="mt-1">
                            Confirmar ou descartar é a triagem humana do próprio alerta (HU-149). Não altera, não indefere e
                            não pune o processo, e não mexe no encaminhamento à malha fina — que é ortogonal e decidido pelo
                            motor. A detecção nasce desligada e gera apenas insumo para análise.
                        </p>
                    </div>
                </div>

                {/* Efetividade (RN-005): global, independente dos filtros da lista. */}
                <Card>
                    <CardHeader
                        title="Efetividade da detecção"
                        description="Confirmados ÷ gerados sobre todos os alertas reais — calibração das regras (RN-005). Indicador global: independe dos filtros da lista. Sem alertas, a taxa não é exibida (nunca um número inventado)."
                    />
                    <CardContent>
                        <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                            <KpiCard
                                label="Alertas gerados"
                                value={formatarNumero(geral.gerados)}
                                icon={<AlertIcon className="size-6" />}
                                tone="info"
                                note="todas as regras"
                            />
                            <KpiCard
                                label="Confirmados"
                                value={formatarNumero(geral.confirmados)}
                                icon={<CheckCircleIcon className="size-6" />}
                                tone="success"
                            />
                            <KpiCard
                                label="Descartados"
                                value={formatarNumero(geral.descartados)}
                                icon={<CloseIcon className="size-6" />}
                                tone="warning"
                            />
                            <KpiCard
                                label="Taxa de confirmação"
                                value={formatarTaxa(geral.taxa)}
                                icon={<ShieldIcon className="size-6" />}
                                tone="brand"
                                note={geral.taxa === null ? 'sem base para calcular' : 'confirmados ÷ gerados'}
                            />
                        </div>

                        <div className="mt-5">
                            <DataTable<EfetividadeRegra>
                                columns={colunasEfetividade}
                                rows={porRegra}
                                rowKey={(linha) => linha.rule_key}
                                density="compact"
                                emptyState={
                                    <EmptyState
                                        title="Sem alertas para calcular efetividade"
                                        description="A efetividade por regra aparece quando houver alertas gerados pelos detectores."
                                    />
                                }
                            />
                        </div>
                    </CardContent>
                </Card>

                {/* Lista de alertas (server-driven). */}
                <Card>
                    <CardHeader
                        title="Alertas para triagem"
                        description="Ocorrências sinalizadas pelos detectores (HU-149) para revisão humana. Consulta somente leitura, server-driven — a própria consulta é auditada (RN-002)."
                    />
                    <CardContent>
                        <div className="space-y-5">
                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                                <FiltroCampo label="Regra">
                                    <Select
                                        value={form.rule_key}
                                        onChange={(valor) => setFiltro('rule_key', valor)}
                                        placeholder="Todas as regras"
                                        options={ruleKeyFiltroOptions}
                                    />
                                </FiltroCampo>
                                <FiltroCampo label="Severidade">
                                    <Select
                                        value={form.severity}
                                        onChange={(valor) => setFiltro('severity', valor)}
                                        placeholder="Todas"
                                        options={severitySelect}
                                    />
                                </FiltroCampo>
                                <FiltroCampo label="Status">
                                    <Select
                                        value={form.status}
                                        onChange={(valor) => setFiltro('status', valor)}
                                        placeholder="Todos"
                                        options={statusSelect}
                                    />
                                </FiltroCampo>
                                <FiltroCampo label="Detectado de">
                                    <Input
                                        type="date"
                                        value={form.data_de}
                                        onChange={(evento) => setFiltro('data_de', evento.target.value)}
                                        aria-label="Detectado de"
                                    />
                                </FiltroCampo>
                                <FiltroCampo label="Detectado até">
                                    <Input
                                        type="date"
                                        value={form.data_ate}
                                        onChange={(evento) => setFiltro('data_ate', evento.target.value)}
                                        aria-label="Detectado até"
                                    />
                                </FiltroCampo>
                            </div>

                            <div className="flex flex-wrap items-center justify-between gap-3">
                                {filtrando ? (
                                    <button
                                        type="button"
                                        onClick={limparFiltros}
                                        className="text-theme-sm font-medium text-brand-500 transition hover:text-brand-600 dark:text-brand-400"
                                    >
                                        Limpar filtros
                                    </button>
                                ) : (
                                    <span />
                                )}
                                <PerPageSelect value={perPage} options={perPageOptions} onChange={trocarPerPage} />
                            </div>

                            <DataTable<AbuseAlertItem>
                                columns={colunasAlertas}
                                rows={linhas}
                                rowKey={(alerta) => alerta.id}
                                loading={processing}
                                skeletonRows={8}
                                density="compact"
                                emptyState={<AlertasVazio filtrando={filtrando} />}
                            />

                            <Pagination
                                links={Array.isArray(alertas?.links) ? alertas.links : []}
                                meta={{ from: alertas?.from ?? null, to: alertas?.to ?? null, total: alertas?.total ?? 0 }}
                            />
                        </div>
                    </CardContent>
                </Card>
            </div>

            {resolucao && (
                <Modal isOpen onClose={fecharResolucao} className="m-4 max-w-[560px] p-6 lg:p-8">
                    <form onSubmit={submeterResolucao}>
                        <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90">{acaoLabel}</h4>
                        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Regra <strong className="font-medium text-gray-700 dark:text-gray-300">{resolucao.alerta.rule_key}</strong> ·{' '}
                            {resolucao.alerta.severity.label} · detectado em {formatarDataHora(resolucao.alerta.detected_at)}.
                        </p>

                        <div className="mt-4 flex items-start gap-3 rounded-xl border border-blue-light-200 bg-blue-light-50 p-3 dark:border-blue-light-500/30 dark:bg-blue-light-500/15">
                            <ShieldIcon className="size-5 shrink-0 text-blue-light-500" />
                            <p className="text-theme-xs text-gray-600 dark:text-gray-300">
                                Esta decisão registra a revisão humana do <strong>alerta</strong>. Ela não altera o status do
                                processo, não o pune e não mexe na malha fina — que é ortogonal.
                            </p>
                        </div>

                        <div className="mt-4">
                            <Label htmlFor="justificativa-alerta" required>
                                Justificativa
                            </Label>
                            <textarea
                                id="justificativa-alerta"
                                rows={4}
                                value={resolverForm.data.justification}
                                onChange={(evento) => resolverForm.setData('justification', evento.target.value)}
                                placeholder="Fundamente a análise que confirma ou descarta este alerta…"
                                className="w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/20 focus:outline-hidden disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                            />
                            {resolverForm.errors.justification && (
                                <p className="mt-1.5 text-theme-xs text-error-500">{resolverForm.errors.justification}</p>
                            )}
                        </div>

                        <div className="mt-6 flex items-center justify-end gap-3">
                            <Button variant="outline" size="sm" type="button" onClick={fecharResolucao}>
                                Cancelar
                            </Button>
                            <Button type="submit" size="sm" loading={resolverForm.processing}>
                                {acaoLabel}
                            </Button>
                        </div>
                    </form>
                </Modal>
            )}
        </>
    );
}

/** Campo de filtro com rótulo acessível acima do controle. */
function FiltroCampo({ label, children }: { label: string; children: ReactNode }) {
    return (
        <label className="flex flex-col gap-1.5">
            <span className="text-theme-xs font-medium text-gray-500 dark:text-gray-400">{label}</span>
            {children}
        </label>
    );
}

/**
 * Estado vazio honesto: distingue "sem resultado de busca" de "sem alertas", e
 * nesse caso explica que a detecção nasce desligada (features.deteccao_abuso) —
 * a ausência pode ser desativação OU nada sinalizado, nunca um estado fingido.
 */
function AlertasVazio({ filtrando }: { filtrando: boolean }) {
    return (
        <EmptyState
            title={filtrando ? 'Nenhum alerta para os filtros' : 'Nenhum alerta de abuso registrado'}
            description={
                filtrando
                    ? 'Ajuste os filtros e tente novamente.'
                    : 'A detecção de abuso roda como tarefa agendada e nasce desligada (parâmetro features.deteccao_abuso). Sem alertas, ou a detecção está desativada, ou nada foi sinalizado no período.'
            }
        />
    );
}

AbusoIndex.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
