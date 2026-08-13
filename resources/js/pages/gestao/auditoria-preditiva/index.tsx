import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import PageHeader from '@/components/app/page-header';
import Select from '@/components/form/select';
import { AlertIcon, CheckCircleIcon, ListIcon } from '@/components/icons';
import Badge from '@/components/ui/badge';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import DataTable from '@/components/ui/data-table/data-table';
import type { ColumnDef } from '@/components/ui/data-table/types';
import EmptyState from '@/components/ui/empty-state';
import KpiCard from '@/components/ui/kpi-card';
import GestaoLayout from '@/layouts/gestao-layout';

interface Fator {
    chave: string;
    peso: number;
    detalhe: string;
}

interface Anomalia {
    id: number;
    score: number;
    severity: string;
    severity_label: string;
    status: string;
    status_label: string;
    factors: Fator[];
    protocolo: string | null;
    viability_request_id: number;
    encaminhado_malha_fina: boolean;
    detected_at: string | null;
    resolvido_por: string | null;
    resolved_at: string | null;
    justificativa: string | null;
}

interface Paginada<T> {
    data: T[];
    total: number;
    per_page: number;
    current_page: number;
    last_page: number;
}

interface Efetividade {
    gerados: number;
    confirmados: number;
    descartados: number;
    abertos: number;
    taxa: number | null;
}

interface Opcao {
    value: string;
    label: string;
}

interface Filtros {
    severity: string;
    status: string;
    data_de: string;
    data_ate: string;
    per_page: number;
}

interface AuditoriaPreditivaProps {
    anomalias: Paginada<Anomalia>;
    efetividade: Efetividade;
    ligado: boolean;
    filtros: Filtros;
    severityOptions: Opcao[];
    statusOptions: Opcao[];
}

const URL = '/gestao/auditoria-preditiva';

const numberFormat = new Intl.NumberFormat('pt-BR');
const percentFormat = new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 1 });

const SEVERITY_COLOR: Record<string, 'error' | 'warning' | 'light'> = {
    alta: 'error',
    media: 'warning',
    baixa: 'light',
};

const STATUS_COLOR: Record<string, 'info' | 'success' | 'light'> = {
    aberto: 'info',
    confirmado: 'success',
    descartado: 'light',
};

function formatarPercentual(valor: number | null): string {
    return valor === null ? '—' : `${percentFormat.format(valor)}%`;
}

function dataCurta(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    return new Date(iso).toLocaleDateString('pt-BR');
}

export default function AuditoriaPreditiva({ anomalias, efetividade, ligado, filtros, severityOptions, statusOptions }: AuditoriaPreditivaProps) {
    const [severity, setSeverity] = useState(filtros.severity ?? '');
    const [status, setStatus] = useState(filtros.status ?? '');
    const [acao, setAcao] = useState<{ anomalia: Anomalia; tipo: 'confirmar' | 'descartar' } | null>(null);
    const [justificativa, setJustificativa] = useState('');
    const [processando, setProcessando] = useState(false);

    function aplicar(proximo: Partial<{ severity: string; status: string }>) {
        const params: Record<string, string> = {};
        const sev = proximo.severity ?? severity;
        const st = proximo.status ?? status;

        if (sev) {
            params.severity = sev;
        }

        if (st) {
            params.status = st;
        }

        router.get(URL, params, { preserveState: true, preserveScroll: true, replace: true });
    }

    function abrir(anomalia: Anomalia, tipo: 'confirmar' | 'descartar') {
        setAcao({ anomalia, tipo });
        setJustificativa('');
    }

    function submeter() {
        if (!acao || justificativa.trim() === '') {
            return;
        }

        router.post(
            `${URL}/${acao.anomalia.id}/${acao.tipo}`,
            { justification: justificativa },
            {
                preserveScroll: true,
                onStart: () => setProcessando(true),
                onFinish: () => setProcessando(false),
                onSuccess: () => setAcao(null),
            },
        );
    }

    const colunas: ColumnDef<Anomalia>[] = [
        { id: 'protocolo', header: 'Processo', cellClassName: 'whitespace-nowrap text-gray-800 dark:text-white/90', cell: (a) => a.protocolo ?? `#${a.viability_request_id}` },
        { id: 'score', header: 'Score', align: 'end', cellClassName: 'whitespace-nowrap font-medium text-gray-800 dark:text-white/90', cell: (a) => numberFormat.format(a.score) },
        {
            id: 'severity',
            header: 'Severidade',
            cell: (a) => (
                <Badge color={SEVERITY_COLOR[a.severity] ?? 'light'} size="sm">
                    {a.severity_label}
                </Badge>
            ),
        },
        {
            id: 'fatores',
            header: 'Fatores',
            cell: (a) => (
                <span className="text-theme-xs text-gray-500 dark:text-gray-400">{a.factors.map((f) => f.chave).join(', ') || '—'}</span>
            ),
        },
        {
            id: 'malha_fina',
            header: 'Malha fina',
            cell: (a) => (a.encaminhado_malha_fina ? <Badge color="warning" size="sm">Encaminhado</Badge> : <span className="text-gray-400">—</span>),
        },
        {
            id: 'status',
            header: 'Situação',
            cell: (a) => (
                <Badge color={STATUS_COLOR[a.status] ?? 'light'} size="sm">
                    {a.status_label}
                </Badge>
            ),
        },
        {
            id: 'acoes',
            header: 'Ações',
            align: 'end',
            cell: (a) =>
                a.status === 'aberto' ? (
                    <span className="inline-flex gap-2">
                        <button type="button" onClick={() => abrir(a, 'confirmar')} className="text-theme-sm font-medium text-success-600 hover:text-success-700 dark:text-success-400">
                            Confirmar
                        </button>
                        <button type="button" onClick={() => abrir(a, 'descartar')} className="text-theme-sm font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400">
                            Descartar
                        </button>
                    </span>
                ) : (
                    <span className="text-theme-xs text-gray-400">{a.resolvido_por ?? '—'}</span>
                ),
        },
    ];

    return (
        <>
            <Head title="Auditoria preditiva" />
            <PageHeader
                title="Auditoria preditiva de processos expressos"
                breadcrumbs={[{ label: 'Painel', href: '/gestao' }, { label: 'Auditoria e compliance' }, { label: 'Auditoria preditiva' }]}
            />

            <div className="space-y-4 md:space-y-6">
                {!ligado && (
                    <Card>
                        <CardContent>
                            <p className="text-theme-sm text-warning-600 dark:text-warning-400">
                                A varredura de auditoria preditiva está <strong>desligada</strong> (governança do DPO — LGPD art. 20). O painel exibe as anomalias já registradas; nenhuma nova é gerada até a ativação em Parâmetros. A auditoria nunca pune: apenas alerta e encaminha à malha fina.
                            </p>
                        </CardContent>
                    </Card>
                )}

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 md:gap-6 xl:grid-cols-4">
                    <KpiCard label="Anomalias geradas" value={numberFormat.format(efetividade.gerados)} note="no total" icon={<ListIcon className="size-6" />} tone="brand" />
                    <KpiCard label="Confirmadas" value={numberFormat.format(efetividade.confirmados)} note="revisadas e procedentes" icon={<CheckCircleIcon className="size-6" />} tone="success" />
                    <KpiCard label="Em aberto" value={numberFormat.format(efetividade.abertos)} note="aguardando revisão" icon={<AlertIcon className="size-6" />} tone={efetividade.abertos > 0 ? 'warning' : 'brand'} />
                    <KpiCard label="Efetividade" value={formatarPercentual(efetividade.taxa)} note="confirmadas ÷ geradas" icon={<CheckCircleIcon className="size-6" />} tone="brand" />
                </div>

                <Card>
                    <CardHeader title="Filtros" description="Anomalias preditivas dos deferimentos do fluxo expresso. Confirmar/descartar muda só o status da anomalia — nunca pune o processo." />
                    <CardContent>
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            <FiltroCampo label="Severidade">
                                <Select
                                    options={severityOptions}
                                    placeholder="Todas"
                                    value={severity}
                                    onChange={(valor) => {
                                        setSeverity(valor);
                                        aplicar({ severity: valor });
                                    }}
                                />
                            </FiltroCampo>
                            <FiltroCampo label="Situação">
                                <Select
                                    options={statusOptions}
                                    placeholder="Todas"
                                    value={status}
                                    onChange={(valor) => {
                                        setStatus(valor);
                                        aplicar({ status: valor });
                                    }}
                                />
                            </FiltroCampo>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader title="Anomalias" description="Ordenadas por score de risco." />
                    <CardContent>
                        <DataTable<Anomalia>
                            columns={colunas}
                            rows={anomalias.data}
                            rowKey={(a) => a.id}
                            density="compact"
                            emptyState={<EmptyState title="Nenhuma anomalia" description="Nenhuma anomalia preditiva no recorte. Com a varredura ligada, processos deferidos com sinais de risco aparecem aqui." />}
                        />
                    </CardContent>
                </Card>
            </div>

            {acao && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" role="dialog" aria-modal="true">
                    <div className="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-900">
                        <h2 className="text-lg font-semibold text-gray-800 dark:text-white/90">
                            {acao.tipo === 'confirmar' ? 'Confirmar anomalia' : 'Descartar anomalia'}
                        </h2>
                        <p className="mt-1 text-theme-sm text-gray-500 dark:text-gray-400">
                            Processo {acao.anomalia.protocolo ?? `#${acao.anomalia.viability_request_id}`}. A justificativa é obrigatória e auditada. Esta ação não altera o status do processo.
                        </p>
                        <textarea
                            value={justificativa}
                            onChange={(e) => setJustificativa(e.target.value)}
                            rows={4}
                            placeholder="Justificativa"
                            aria-label="Justificativa"
                            className="mt-4 w-full rounded-lg border border-gray-300 bg-transparent p-3 text-theme-sm text-gray-800 focus:border-brand-400 focus:outline-none dark:border-gray-700 dark:text-white/90"
                        />
                        <div className="mt-4 flex justify-end gap-3">
                            <button type="button" onClick={() => setAcao(null)} className="text-theme-sm font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400">
                                Cancelar
                            </button>
                            <button
                                type="button"
                                onClick={submeter}
                                disabled={processando || justificativa.trim() === ''}
                                className="rounded-lg bg-brand-500 px-4 py-2 text-theme-sm font-medium text-white transition hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                {acao.tipo === 'confirmar' ? 'Confirmar' : 'Descartar'}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}

function FiltroCampo({ label, children }: { label: string; children: ReactNode }) {
    return (
        <label className="flex flex-col gap-1.5">
            <span className="text-theme-xs font-medium text-gray-500 dark:text-gray-400">{label}</span>
            {children}
        </label>
    );
}

AuditoriaPreditiva.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
