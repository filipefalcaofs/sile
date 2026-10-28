import { Head, router, useForm } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import ConclusaoAnaliseForm from '@/components/analise/conclusao-analise-form';
import ProcessoFiltros, { FILTROS_VAZIOS, type ProcessoFiltrosValores } from '@/components/analise/processo-filtros';
import PageHeader from '@/components/app/page-header';
import { ArrowRightIcon } from '@/components/icons';
import Badge from '@/components/ui/badge';
import Button from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import DataTable from '@/components/ui/data-table/data-table';
import PerPageSelect from '@/components/ui/data-table/per-page-select';
import type { ColumnDef } from '@/components/ui/data-table/types';
import EmptyState from '@/components/ui/empty-state';
import KpiCard from '@/components/ui/kpi-card';
import { Modal } from '@/components/ui/modal';
import Pagination, { type PaginationLink } from '@/components/ui/pagination';
import TableAction from '@/components/ui/table-action';
import GestaoLayout from '@/layouts/gestao-layout';

type Aba = 'abertas' | 'concluidas';

interface MalhaFinaItem {
    id: number;
    protocol_number: string | null;
    bap: string | null;
    renovacao: boolean;
    protocoled_at: string | null;
    empresa: string | null;
    requerente: string | null;
    servico: string | null;
    status: string;
    status_label: string;
    analysis_status: string | null;
    analysis_status_label: string | null;
    setor: string | null;
    responsavel: string | null;
    analysis_due_at: string | null;
    entrada_malha_fina: string | null;
    encaminhado_por: string | null;
    motivo: string | null;
    concluido_em: string | null;
    concluido_por: string | null;
    observacao: string | null;
    resultado: string | null;
    resultado_label: string | null;
    ficha_url: string;
}

interface Kpis {
    em_malha_fina: number;
    entradas_mes: number;
    concluidas_mes: number;
    prazo_vencido: number;
}

interface AbaItem {
    id: Aba;
    label: string;
    total: number;
}

interface Paginado<T> {
    data: T[];
    links: PaginationLink[];
    from: number | null;
    to: number | null;
    total: number;
}

interface MalhaFinaIndexProps {
    processos: Paginado<MalhaFinaItem>;
    kpis: Kpis;
    abas: AbaItem[];
    filtros: ProcessoFiltrosValores & { aba: Aba; per_page: number };
    servicoOptions: { value: string; label: string }[];
    analysisStatusOptions: { value: string; label: string }[];
    conclusaoOpcoes: { value: string; label: string }[];
    categoriaOptions: { value: string; label: string }[];
    perPageOptions: number[];
}

/** Formata a data-hora ISO para o padrão pt-BR (dd/mm/aaaa hh:mm). */
function formatarDataHora(iso: string | null): string {
    if (!iso) {
        return '-';
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

export default function MalhaFinaIndex({
    processos,
    kpis,
    abas,
    filtros,
    servicoOptions,
    analysisStatusOptions,
    conclusaoOpcoes,
    categoriaOptions,
    perPageOptions,
}: MalhaFinaIndexProps) {
    const linhas = Array.isArray(processos?.data) ? processos.data : [];
    const [concluindo, setConcluindo] = useState<MalhaFinaItem | null>(null);
    const conclusao = useForm({ resultado: '', observacao: '', tipo: '', validade_meses: '', validade_data: '' });

    const { aba, per_page: _perPage, ...filtrosDeCampo } = filtros;

    function navegar(params: { aba?: Aba; per_page?: number; filtros?: ProcessoFiltrosValores }) {
        const filtrosAtuais = params.filtros ?? filtrosDeCampo;
        router.get(
            '/gestao/malha-fina',
            {
                aba: params.aba ?? aba,
                per_page: params.per_page ?? filtros.per_page,
                ...Object.fromEntries(Object.entries(filtrosAtuais).filter(([, v]) => v !== '')),
            },
            { preserveScroll: true, preserveState: true },
        );
    }

    function abrirConclusao(item: MalhaFinaItem) {
        conclusao.reset();
        conclusao.clearErrors();
        setConcluindo(item);
    }

    function concluir() {
        if (concluindo === null) {
            return;
        }

        conclusao.post(`/gestao/malha-fina/${concluindo.id}/concluir`, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => setConcluindo(null),
        });
    }

    const columns: ColumnDef<MalhaFinaItem>[] = [
        {
            id: 'processo',
            header: 'Processo',
            cellClassName: 'whitespace-nowrap',
            cell: (item) => (
                <div className="flex flex-col">
                    <span className="font-medium text-gray-800 tabular-nums dark:text-white/90">
                        {item.protocol_number ?? '-'}
                    </span>
                    <span className="text-theme-xs text-gray-400 dark:text-gray-500">
                        {item.bap ? `BAP ${item.bap}` : 'sem BAP'}
                    </span>
                    <span className="text-theme-xs text-gray-400 dark:text-gray-500">
                        Entrada: {formatarDataHora(item.protocoled_at)}
                    </span>
                </div>
            ),
        },
        {
            id: 'requerente',
            header: 'Requerente',
            cell: (item) => (
                <div className="flex flex-col">
                    <span className="text-gray-700 dark:text-gray-300">{item.empresa ?? item.requerente ?? '-'}</span>
                    {item.empresa && item.requerente && (
                        <span className="text-theme-xs text-gray-400 dark:text-gray-500">{item.requerente}</span>
                    )}
                    {item.servico && (
                        <span className="text-theme-xs text-gray-400 dark:text-gray-500">{item.servico}</span>
                    )}
                </div>
            ),
        },
        {
            id: 'status',
            header: 'Status',
            cellClassName: 'whitespace-nowrap',
            cell: (item) => (
                <div className="flex flex-col gap-1">
                    <Badge size="sm" color="primary">{item.status_label}</Badge>
                    {item.analysis_status_label && (
                        <span className="text-theme-xs text-gray-400 dark:text-gray-500">
                            {item.analysis_status_label}
                        </span>
                    )}
                </div>
            ),
        },
        {
            id: 'responsavel',
            header: 'Responsável',
            cellClassName: 'whitespace-nowrap',
            cell: (item) =>
                item.responsavel ?? <span className="text-gray-400 dark:text-gray-500">Não atribuído</span>,
        },
        {
            id: 'malha_fina',
            header: aba === 'concluidas' ? 'Baixa' : 'Encaminhamento',
            cell: (item) => (
                <div className="flex flex-col">
                    {aba === 'concluidas' ? (
                        <>
                            <span className="text-gray-700 dark:text-gray-300">
                                {item.concluido_por ?? '-'} · {formatarDataHora(item.concluido_em)}
                            </span>
                            {item.resultado_label && (
                                <span className="text-theme-xs font-medium text-gray-700 dark:text-gray-300">
                                    {item.resultado_label}
                                </span>
                            )}
                            {item.observacao && (
                                <span className="text-theme-xs text-gray-400 dark:text-gray-500">{item.observacao}</span>
                            )}
                        </>
                    ) : (
                        <>
                            <span className="text-gray-700 dark:text-gray-300">
                                {item.encaminhado_por ?? 'Sistema'} · {formatarDataHora(item.entrada_malha_fina)}
                            </span>
                            {item.motivo && (
                                <span className="text-theme-xs text-gray-400 dark:text-gray-500">{item.motivo}</span>
                            )}
                        </>
                    )}
                </div>
            ),
        },
        {
            id: 'actions',
            header: 'Ações',
            align: 'end',
            cellClassName: 'whitespace-nowrap',
            cell: (item) => (
                <div className="flex justify-end gap-2">
                    <TableAction
                        tone="brand"
                        href={item.ficha_url}
                        icon={<ArrowRightIcon className="size-4.5" />}
                        label="Abrir processo"
                    />
                    {aba === 'abertas' && (
                        <TableAction tone="success" onClick={() => abrirConclusao(item)}>
                            Concluir
                        </TableAction>
                    )}
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title="Caixa de Malha Fina" />
            <PageHeader
                title="Caixa de Malha Fina"
                breadcrumbs={[{ label: 'Painel', href: '/gestao' }]}
            />

            <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <KpiCard label="Em malha fina" value={kpis.em_malha_fina} tone="brand" note="aguardando análise" />
                <KpiCard label="Entradas no mês" value={kpis.entradas_mes} tone="info" note="encaminhamentos" />
                <KpiCard label="Prazo vencido" value={kpis.prazo_vencido} tone="error" note="exigem atenção" />
                <KpiCard label="Concluídas no mês" value={kpis.concluidas_mes} tone="success" note="baixas da malha fina" />
            </div>

            <Card>
                <CardContent className="border-t-0">
                    <div className="space-y-5">
                        <ProcessoFiltros
                            valores={filtrosDeCampo}
                            servicoOptions={servicoOptions}
                            analysisStatusOptions={analysisStatusOptions}
                            categoriaOptions={categoriaOptions}
                            onAplicar={(valores) => navegar({ filtros: valores })}
                            onLimpar={() => navegar({ filtros: FILTROS_VAZIOS })}
                        />

                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div className="flex flex-wrap items-center gap-2" role="tablist" aria-label="Situação da malha fina">
                                {abas.map((abaItem) => (
                                    <button
                                        key={abaItem.id}
                                        type="button"
                                        role="tab"
                                        aria-selected={aba === abaItem.id}
                                        onClick={() => navegar({ aba: abaItem.id })}
                                        className={`inline-flex items-center gap-2 rounded-full border px-4 py-2 text-sm font-medium transition ${
                                            aba === abaItem.id
                                                ? 'border-brand-500 bg-brand-500 text-white'
                                                : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-transparent dark:text-gray-300 dark:hover:bg-white/[0.03]'
                                        }`}
                                    >
                                        {abaItem.label}
                                        <span
                                            className={`inline-flex min-w-6 justify-center rounded-full px-1.5 text-xs font-semibold ${
                                                aba === abaItem.id
                                                    ? 'bg-white/20 text-white'
                                                    : 'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-300'
                                            }`}
                                        >
                                            {abaItem.total}
                                        </span>
                                    </button>
                                ))}
                            </div>
                            <PerPageSelect
                                value={filtros.per_page}
                                options={perPageOptions}
                                onChange={(perPage) => navegar({ per_page: perPage })}
                            />
                        </div>

                        <DataTable
                            columns={columns}
                            rows={linhas}
                            rowKey={(item) => item.id}
                            density="compact"
                            emptyState={
                                <EmptyState
                                    title={aba === 'concluidas' ? 'Nenhuma baixa de malha fina' : 'Nenhum processo em malha fina'}
                                    description={
                                        aba === 'concluidas'
                                            ? 'Processos concluídos na malha fina aparecem aqui.'
                                            : 'Processos entram aqui quando encaminhados à malha fina, pela ficha, em lote na consulta ou pela detecção de abuso.'
                                    }
                                />
                            }
                        />

                        <Pagination
                            links={processos?.links ?? []}
                            meta={{ from: processos?.from ?? null, to: processos?.to ?? null, total: processos?.total ?? 0 }}
                        />
                    </div>
                </CardContent>
            </Card>

            <Modal isOpen={concluindo !== null} onClose={() => setConcluindo(null)} className="m-4 max-h-[90vh] max-w-lg overflow-y-auto p-6">
                <h2 className="text-lg font-semibold text-gray-800 dark:text-white/90">Conclusão de Análise</h2>
                <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    O processo {concluindo?.protocol_number ?? ''} sai da malha fina com o resultado escolhido. Deferido
                    e indeferido seguem a ficha finalizada. Desativado, cassado e revogado encerram o processo,
                    inclusive um já deferido.
                </p>
                <div className="mt-4">
                    <ConclusaoAnaliseForm
                        resultadoId="concluir-resultado"
                        resultado={conclusao.data.resultado}
                        onResultado={(valor) =>
                            conclusao.setData((dados) => ({
                                ...dados,
                                resultado: valor,
                                ...(valor !== 'deferido' ? { tipo: '', validade_meses: '', validade_data: '' } : {}),
                            }))
                        }
                        opcoes={conclusaoOpcoes}
                        tipo={conclusao.data.tipo}
                        onTipo={
                            concluindo?.renovacao
                                ? undefined
                                : (valor) =>
                                      conclusao.setData((dados) => ({
                                          ...dados,
                                          tipo: valor,
                                          ...(valor === 'definitiva' ? { validade_meses: '', validade_data: '' } : {}),
                                      }))
                        }
                        validadeMeses={conclusao.data.validade_meses}
                        onValidadeMeses={(valor) => conclusao.setData('validade_meses', valor)}
                        validadeData={conclusao.data.validade_data}
                        onValidadeData={(valor) => conclusao.setData('validade_data', valor)}
                        erroValidadeData={(conclusao.errors as Record<string, string | undefined>).validade_data ?? null}
                        erroTipo={(conclusao.errors as Record<string, string | undefined>).tipo ?? null}
                        erroValidade={(conclusao.errors as Record<string, string | undefined>).validade_meses ?? null}
                        parecerId="concluir-observacao"
                        parecer={conclusao.data.observacao}
                        onParecer={(valor) => conclusao.setData('observacao', valor)}
                        parecerLabel="Parecer (opcional)"
                        erro={
                            (conclusao.errors as Record<string, string | undefined>).resultado
                            ?? (conclusao.errors as Record<string, string | undefined>).conclusao
                            ?? null
                        }
                        processing={conclusao.processing}
                        onConfirm={concluir}
                        onCancel={() => setConcluindo(null)}
                    />
                </div>
            </Modal>
        </>
    );
}

MalhaFinaIndex.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
