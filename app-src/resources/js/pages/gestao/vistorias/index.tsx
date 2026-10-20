import { Head, router } from '@inertiajs/react';
import { useEffect, useRef, useState, type ReactNode } from 'react';
import DistribuirModal, { type DestinatarioDaDistribuicao } from '@/components/analise/distribuir-modal';
import ProcessoFiltros, { FILTROS_VAZIOS, type ProcessoFiltrosValores } from '@/components/analise/processo-filtros';
import PageHeader from '@/components/app/page-header';
import { ArrowRightIcon, EyeIcon, GroupIcon } from '@/components/icons';
import Badge from '@/components/ui/badge';
import Button from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import DataTable from '@/components/ui/data-table/data-table';
import PerPageSelect from '@/components/ui/data-table/per-page-select';
import type { ColumnDef } from '@/components/ui/data-table/types';
import EmptyState from '@/components/ui/empty-state';
import KpiCard from '@/components/ui/kpi-card';
import Pagination, { type PaginationLink } from '@/components/ui/pagination';
import TableAction from '@/components/ui/table-action';
import GestaoLayout from '@/layouts/gestao-layout';
import { distribuiveis, podeSelecionar } from './selecao';

interface VistoriaItem {
    id: number;
    protocol_number: string | null;
    bap: string | null;
    empresa: string | null;
    imovel: string;
    servicos: string[];
    sector_id: number | null;
    zona: string | null;
    setor: string | null;
    situacao: 'designar' | 'distribuido' | 'em_campo' | 'concluida';
    situacao_label: string;
    pode_redistribuir: boolean;
    vistoriador: string | null;
    responsavel: string | null;
    analysis_due_at: string | null;
    ficha_url: string;
    consulta_url: string;
}

type OpcaoDeFiltro = { value: string; label: string };

interface Kpis {
    encaminhadas: number;
    a_designar: number;
    distribuidos: number;
    em_campo: number;
    concluidas: number;
    concluidas_mes: number;
    prazo_vencido: number;
}

interface Aba {
    id: string;
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

interface VistoriasIndexProps {
    vistorias: Paginado<VistoriaItem>;
    kpis: Kpis;
    abas: Aba[];
    filtros: Partial<ProcessoFiltrosValores> & {
        situacao: string;
        busca: string;
        per_page: number;
    };
    perPageOptions: number[];
    servicoOptions: OpcaoDeFiltro[];
    analysisStatusOptions: OpcaoDeFiltro[];
    categoriaOptions: OpcaoDeFiltro[];
    analistaOptions: OpcaoDeFiltro[];
    podeDistribuir: boolean;
    distribuiEmQualquerSetor: boolean;
    vistoriadores: DestinatarioDaDistribuicao[];
}

const tomSituacao: Record<VistoriaItem['situacao'], 'warning' | 'info' | 'success' | 'light'> = {
    designar: 'warning',
    distribuido: 'light',
    em_campo: 'info',
    concluida: 'success',
};

/** Formata a data-hora ISO do prazo para o padrão pt-BR (dd/mm/aaaa hh:mm). */
function formatarPrazo(iso: string | null): string {
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

export default function VistoriasIndex({
    vistorias,
    kpis,
    abas,
    filtros,
    perPageOptions,
    servicoOptions,
    analysisStatusOptions,
    categoriaOptions,
    analistaOptions,
    podeDistribuir,
    distribuiEmQualquerSetor,
    vistoriadores,
}: VistoriasIndexProps) {
    const linhas = Array.isArray(vistorias?.data) ? vistorias.data : [];
    const [busca, setBusca] = useState(filtros.busca);
    const primeiraBusca = useRef(true);
    const [selecionados, setSelecionados] = useState<number[]>([]);
    const [modalAberto, setModalAberto] = useState(false);
    const [redistribuindo, setRedistribuindo] = useState<VistoriaItem | null>(null);

    const { situacao: _situacao, busca: _busca, per_page: _perPage, ...filtrosDeCampo } = filtros;
    const selecaoAtiva = podeDistribuir && (filtros.situacao === 'designar' || filtros.situacao === 'todas');
    const selecionaveis = distribuiveis(linhas);
    const todosSelecionados = selecionaveis.length > 0 && selecionaveis.every((item) => selecionados.includes(item.id));

    function navegar(params: Record<string, string | number>, camposDoFiltro: Partial<ProcessoFiltrosValores> = filtrosDeCampo) {
        setSelecionados([]);
        router.get(
            '/gestao/vistorias',
            {
                situacao: filtros.situacao,
                busca: filtros.busca,
                per_page: filtros.per_page,
                ...Object.fromEntries(Object.entries(camposDoFiltro).filter(([, valor]) => valor !== '' && valor !== undefined)),
                ...params,
            },
            { preserveScroll: true, preserveState: true },
        );
    }

    function alternarSelecao(id: number) {
        setSelecionados((atual) => (atual.includes(id) ? atual.filter((item) => item !== id) : [...atual, id]));
    }

    function alternarTodos() {
        setSelecionados(todosSelecionados ? [] : selecionaveis.map((item) => item.id));
    }

    // Busca com debounce — server-driven, sem recarregar a página inteira.
    useEffect(() => {
        if (primeiraBusca.current) {
            primeiraBusca.current = false;

            return;
        }

        const timer = window.setTimeout(() => navegar({ busca }), 400);

        return () => window.clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [busca]);

    const columns: ColumnDef<VistoriaItem>[] = [
        ...(selecaoAtiva
            ? [
                  {
                      id: 'selecao',
                      header: (
                          <input
                              type="checkbox"
                              aria-label="Selecionar todos os processos da página"
                              checked={todosSelecionados}
                              disabled={selecionaveis.length === 0}
                              onChange={alternarTodos}
                              className="size-4 rounded border-gray-300 bg-white text-brand-600 scheme-light focus:ring-brand-500 dark:border-gray-400 dark:bg-gray-900 dark:scheme-dark"
                          />
                      ),
                      cellClassName: 'w-10 whitespace-nowrap',
                      cell: (item: VistoriaItem) => (
                          <input
                              type="checkbox"
                              aria-label={`Selecionar processo ${item.bap ?? item.protocol_number ?? item.id}`}
                              checked={selecionados.includes(item.id)}
                              disabled={!podeSelecionar(item)}
                              title={!podeSelecionar(item) ? 'Só processos para distribuir podem ser selecionados' : undefined}
                              onChange={() => alternarSelecao(item.id)}
                              className="size-4 rounded border-gray-300 bg-white text-brand-600 scheme-light focus:ring-brand-500 dark:border-gray-400 dark:bg-gray-900 dark:scheme-dark"
                          />
                      ),
                  } satisfies ColumnDef<VistoriaItem>,
              ]
            : []),
        {
            id: 'processo',
            header: 'Processo',
            cellClassName: 'whitespace-nowrap',
            cell: (item) => (
                <div className="flex flex-col">
                    <span className="font-medium text-gray-800 tabular-nums dark:text-white/90">
                        {item.bap ?? item.protocol_number ?? '—'}
                    </span>
                    <span className="text-theme-xs text-gray-400 dark:text-gray-500">
                        {item.bap ? (item.protocol_number ?? 'sem protocolo') : (item.empresa ?? '')}
                    </span>
                    {item.bap && item.empresa && (
                        <span className="text-theme-xs text-gray-500 dark:text-gray-400">{item.empresa}</span>
                    )}
                </div>
            ),
        },
        {
            id: 'imovel',
            header: 'Imóvel',
            cell: (item) => (
                <div className="flex flex-col">
                    <span className="text-gray-700 dark:text-gray-300">{item.imovel !== '' ? item.imovel : '—'}</span>
                    {(item.zona || item.setor) && (
                        <span className="text-theme-xs text-gray-400 dark:text-gray-500">
                            {[item.zona, item.setor].filter(Boolean).join(' · ')}
                        </span>
                    )}
                </div>
            ),
        },
        {
            id: 'servico',
            header: 'Serviço',
            cellClassName: 'min-w-56 max-w-xs',
            cell: (item) =>
                item.servicos.length === 0 ? (
                    <span className="text-gray-400 dark:text-gray-500">—</span>
                ) : (
                    <ul className="flex flex-col gap-1">
                        {item.servicos.map((servico) => (
                            <li key={servico} className="leading-snug text-gray-700 dark:text-gray-300">
                                {servico}
                            </li>
                        ))}
                    </ul>
                ),
        },
        {
            id: 'situacao',
            header: 'Situação',
            cellClassName: 'whitespace-nowrap',
            cell: (item) => (
                <Badge size="sm" color={tomSituacao[item.situacao]}>
                    {item.situacao_label}
                </Badge>
            ),
        },
        {
            id: 'vistoriador',
            header: 'Vistoriador',
            cellClassName: 'whitespace-nowrap',
            cell: (item) =>
                item.vistoriador ?? item.responsavel ?? <span className="text-gray-400 dark:text-gray-500">Não designado</span>,
        },
        {
            id: 'prazo',
            header: 'Prazo',
            cellClassName: 'whitespace-nowrap',
            cell: (item) => formatarPrazo(item.analysis_due_at),
        },
        {
            id: 'actions',
            header: 'Ações',
            align: 'end',
            cellClassName: 'whitespace-nowrap',
            cell: (item) => (
                <div className="flex justify-end gap-2">
                    <TableAction
                        tone="neutral"
                        href={item.consulta_url}
                        icon={<EyeIcon className="size-4.5" />}
                        label="Consultar processo"
                    />
                    {podeDistribuir && item.pode_redistribuir && (
                        <TableAction
                            tone="neutral"
                            onClick={() => setRedistribuindo(item)}
                            icon={<GroupIcon className="size-4.5" />}
                            label="Redistribuir para outro vistoriador"
                        />
                    )}
                    {item.situacao === 'designar' ? (
                        <span className="text-theme-xs text-gray-400 dark:text-gray-500">Aguarda distribuição</span>
                    ) : (
                        <TableAction
                            tone="brand"
                            href={item.ficha_url}
                            icon={<ArrowRightIcon className="size-4.5" />}
                            label={item.situacao === 'concluida' ? 'Ver ficha' : 'Abrir ficha'}
                        />
                    )}
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title="Consulta de vistorias" />
            <PageHeader title="Consulta de vistorias" breadcrumbs={[{ label: 'Painel', href: '/gestao' }]} />

            <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <KpiCard label="Encaminhadas" value={kpis.encaminhadas} tone="brand" note="no eixo de vistoria" />
                <KpiCard label="Para distribuir" value={kpis.a_designar} tone="warning" note="sem vistoriador" />
                <KpiCard label="Em campo" value={kpis.em_campo} tone="info" note="ficha em preenchimento" />
                <KpiCard label="Prazo vencido" value={kpis.prazo_vencido} tone="error" note="exigem atenção" />
                <KpiCard label="Concluídas no mês" value={kpis.concluidas_mes} tone="success" note={`${kpis.concluidas} no total`} />
            </div>

            <Card>
                <CardContent className="border-t-0">
                    <div className="space-y-5">
                        <ProcessoFiltros
                            valores={filtrosDeCampo}
                            servicoOptions={servicoOptions}
                            analysisStatusOptions={analysisStatusOptions}
                            categoriaOptions={categoriaOptions}
                            analistaOptions={analistaOptions}
                            onAplicar={(valores) => navegar({}, valores)}
                            onLimpar={() => navegar({}, FILTROS_VAZIOS)}
                        />

                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div className="flex flex-wrap items-center gap-2" role="tablist" aria-label="Situação da vistoria">
                                {abas.map((aba) => (
                                    <button
                                        key={aba.id}
                                        type="button"
                                        role="tab"
                                        aria-selected={filtros.situacao === aba.id}
                                        onClick={() => navegar({ situacao: aba.id })}
                                        className={`inline-flex items-center gap-2 rounded-full border px-4 py-2 text-sm font-medium transition ${
                                            filtros.situacao === aba.id
                                                ? 'border-brand-500 bg-brand-500 text-white'
                                                : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-transparent dark:text-gray-300 dark:hover:bg-white/[0.03]'
                                        }`}
                                    >
                                        {aba.label}
                                        <span
                                            className={`inline-flex min-w-6 justify-center rounded-full px-1.5 text-xs font-semibold ${
                                                filtros.situacao === aba.id
                                                    ? 'bg-white/20 text-white'
                                                    : 'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-300'
                                            }`}
                                        >
                                            {aba.total}
                                        </span>
                                    </button>
                                ))}
                            </div>
                            <div className="flex flex-wrap items-center gap-3">
                                <input
                                    type="search"
                                    value={busca}
                                    onChange={(e) => setBusca(e.target.value)}
                                    placeholder="Buscar por processo, BAP, empresa, endereço ou vistoriador"
                                    aria-label="Buscar vistorias"
                                    className="h-11 w-full min-w-64 rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/20 focus:outline-hidden sm:w-96 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                                />
                                <PerPageSelect
                                    value={filtros.per_page}
                                    options={perPageOptions}
                                    onChange={(perPage) => navegar({ per_page: perPage })}
                                />
                            </div>
                        </div>

                        {selecaoAtiva && selecionados.length > 0 && (
                            <div className="flex flex-wrap items-center gap-3">
                                <Button variant="primary" onClick={() => setModalAberto(true)}>
                                    Distribuir selecionados ({selecionados.length})
                                </Button>
                            </div>
                        )}

                        <DataTable
                            columns={columns}
                            rows={linhas}
                            rowKey={(item) => item.id}
                            density="compact"
                            emptyState={
                                <EmptyState
                                    title="Nenhuma vistoria para esta visão"
                                    description="Ajuste a busca ou troque de aba. Processos entram aqui quando o status de análise vai para Vistoriar."
                                />
                            }
                        />

                        <Pagination
                            links={vistorias?.links ?? []}
                            meta={{ from: vistorias?.from ?? null, to: vistorias?.to ?? null, total: vistorias?.total ?? 0 }}
                        />
                    </div>
                </CardContent>
            </Card>

            {modalAberto && (
                <DistribuirModal
                    tipo="distribuir"
                    ids={selecionados}
                    descricao={`${selecionados.length} processo(s) selecionado(s)`}
                    destinatarios={vistoriadores}
                    papel="Vistoriador"
                    distribuiEmQualquerSetor={distribuiEmQualquerSetor}
                    setoresDosProcessos={linhas.filter((linha) => selecionados.includes(linha.id)).map((linha) => linha.sector_id)}
                    onClose={() => setModalAberto(false)}
                    onSuccess={() => {
                        setModalAberto(false);
                        setSelecionados([]);
                    }}
                />
            )}

            {redistribuindo && (
                <DistribuirModal
                    tipo="redistribuir"
                    ids={[redistribuindo.id]}
                    descricao={`Processo ${redistribuindo.bap ?? redistribuindo.protocol_number ?? redistribuindo.id} — hoje com ${redistribuindo.vistoriador ?? redistribuindo.responsavel ?? 'vistoriador'}`}
                    destinatarios={vistoriadores}
                    papel="Vistoriador"
                    distribuiEmQualquerSetor={distribuiEmQualquerSetor}
                    setoresDosProcessos={[redistribuindo.sector_id]}
                    onClose={() => setRedistribuindo(null)}
                    onSuccess={() => setRedistribuindo(null)}
                />
            )}
        </>
    );
}

VistoriasIndex.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
