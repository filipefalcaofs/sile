import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import PageHeader from '@/components/app/page-header';
import Input from '@/components/form/input';
import Select from '@/components/form/select';
import { ArrowRightIcon } from '@/components/icons';
import Badge from '@/components/ui/badge';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import DataTable from '@/components/ui/data-table/data-table';
import PerPageSelect from '@/components/ui/data-table/per-page-select';
import TableToolbar from '@/components/ui/data-table/table-toolbar';
import type { ColumnDef } from '@/components/ui/data-table/types';
import { useServerTable } from '@/components/ui/data-table/use-server-table';
import EmptyState from '@/components/ui/empty-state';
import Pagination, { type PaginationLink } from '@/components/ui/pagination';
import TableAction from '@/components/ui/table-action';
import GestaoLayout from '@/layouts/gestao-layout';

interface ResultadoItem {
    id: number;
    protocol_number: string | null;
    status: string;
    status_label: string;
    empresa: string | null;
    cnpj: string | null;
    outcome: string;
    outcome_label: string;
    consolidated_result: string | null;
    tvl_product_number: string | null;
    decided_at: string | null;
}

interface SelectOption {
    value: string;
    label: string;
}

interface ResultadosExpressoIndexProps {
    decisoes: {
        data: ResultadoItem[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
    filtros: {
        search: string;
        outcome: string;
        data_de: string;
        data_ate: string;
        per_page: number;
    };
    perPageOptions: number[];
    outcomeOptions: SelectOption[];
}

/** Cor do badge por desfecho: deferida=verde, indeferida=vermelho. */
function outcomeColor(outcome: string): 'success' | 'error' | 'light' {
    if (outcome === 'deferida') {
        return 'success';
    }

    if (outcome === 'indeferida') {
        return 'error';
    }

    return 'light';
}

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

export default function ResultadosExpressoIndex({
    decisoes,
    filtros,
    perPageOptions,
    outcomeOptions,
}: ResultadosExpressoIndexProps) {
    const table = useServerTable({
        url: '/gestao/resultados-expresso',
        initialSearch: filtros.search,
        initialSort: { column: 'decided_at', direction: 'desc' },
        initialPerPage: filtros.per_page,
        initialFilters: { outcome: filtros.outcome, data_de: filtros.data_de, data_ate: filtros.data_ate },
    });

    const filtering =
        table.search.trim() !== '' ||
        table.filters.outcome !== '' ||
        table.filters.data_de !== '' ||
        table.filters.data_ate !== '';

    const outcomeLabel = outcomeOptions.find((option) => option.value === table.filters.outcome)?.label;

    const columns: ColumnDef<ResultadoItem>[] = [
        {
            id: 'protocolo',
            header: 'Protocolo',
            cellClassName: 'font-medium whitespace-nowrap text-gray-800 dark:text-white/90',
            cell: (item) => item.protocol_number ?? '—',
        },
        {
            id: 'empresa',
            header: 'Empresa',
            cell: (item) => (
                <div className="flex flex-col">
                    <span className="text-gray-700 dark:text-gray-300">{item.empresa ?? '—'}</span>
                    {item.cnpj && <span className="text-theme-xs text-gray-400 dark:text-gray-500">{item.cnpj}</span>}
                </div>
            ),
        },
        {
            id: 'resultado',
            header: 'Resultado',
            cellClassName: 'whitespace-nowrap',
            cell: (item) => (
                <Badge color={outcomeColor(item.outcome)} size="sm">
                    {item.outcome_label}
                </Badge>
            ),
        },
        {
            id: 'tvl',
            header: 'Número TVL',
            cellClassName: 'whitespace-nowrap',
            cell: (item) =>
                item.tvl_product_number ?? <span className="text-gray-400 dark:text-gray-500">—</span>,
        },
        {
            id: 'decidido_em',
            header: 'Decidido em',
            cellClassName: 'whitespace-nowrap',
            cell: (item) => formatarDataHora(item.decided_at),
        },
        {
            id: 'actions',
            header: 'Ações',
            align: 'end',
            cellClassName: 'whitespace-nowrap',
            cell: (item) => (
                <div className="flex justify-end">
                    <TableAction
                        tone="brand"
                        href={`/gestao/resultados-expresso/${item.id}`}
                        icon={<ArrowRightIcon className="size-4.5" />}
                        label="Ver detalhe"
                    />
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title="Resultados do fluxo expresso" />
            <PageHeader
                title="Resultados do fluxo expresso"
                breadcrumbs={[{ label: 'Painel', href: '/gestao' }]}
            />

            <Card>
                <CardHeader
                    title="Decisões automáticas"
                    description="Solicitações deferidas e indeferidas pelo fluxo expresso (HU-076/HU-078). Consulta somente leitura — a decisão é imutável e auditável."
                />
                <CardContent>
                    <div className="space-y-5">
                        <TableToolbar
                            search={{
                                value: table.search,
                                onChange: table.setSearch,
                                placeholder: 'Buscar por protocolo ou número TVL...',
                                label: 'Buscar resultados do fluxo expresso',
                            }}
                            filters={
                                <>
                                    <div className="w-44">
                                        <label htmlFor="filter-outcome" className="sr-only">
                                            Filtrar por resultado
                                        </label>
                                        <Select
                                            id="filter-outcome"
                                            value={table.filters.outcome}
                                            onChange={(value) => table.setFilter('outcome', value)}
                                            placeholder="Resultado"
                                            options={outcomeOptions}
                                        />
                                    </div>
                                    <div className="w-40">
                                        <label htmlFor="filter-data-de" className="sr-only">
                                            Decidido a partir de
                                        </label>
                                        <Input
                                            id="filter-data-de"
                                            type="date"
                                            value={table.filters.data_de}
                                            onChange={(event) => table.setFilter('data_de', event.target.value)}
                                            aria-label="Decidido a partir de"
                                        />
                                    </div>
                                    <div className="w-40">
                                        <label htmlFor="filter-data-ate" className="sr-only">
                                            Decidido até
                                        </label>
                                        <Input
                                            id="filter-data-ate"
                                            type="date"
                                            value={table.filters.data_ate}
                                            onChange={(event) => table.setFilter('data_ate', event.target.value)}
                                            aria-label="Decidido até"
                                        />
                                    </div>
                                </>
                            }
                            actions={
                                <PerPageSelect
                                    value={table.perPage}
                                    options={perPageOptions}
                                    onChange={table.setPerPage}
                                />
                            }
                        />

                        {filtering && (
                            <div className="flex flex-wrap items-center gap-2">
                                {table.filters.outcome !== '' && (
                                    <FilterChip
                                        label={`Resultado: ${outcomeLabel ?? table.filters.outcome}`}
                                        onClear={() => table.setFilter('outcome', '')}
                                    />
                                )}
                                {table.filters.data_de !== '' && (
                                    <FilterChip
                                        label={`De: ${table.filters.data_de}`}
                                        onClear={() => table.setFilter('data_de', '')}
                                    />
                                )}
                                {table.filters.data_ate !== '' && (
                                    <FilterChip
                                        label={`Até: ${table.filters.data_ate}`}
                                        onClear={() => table.setFilter('data_ate', '')}
                                    />
                                )}
                            </div>
                        )}

                        <DataTable
                            columns={columns}
                            rows={decisoes.data}
                            rowKey={(item) => item.id}
                            loading={table.processing}
                            skeletonRows={8}
                            density="compact"
                            emptyState={
                                <EmptyState
                                    title={filtering ? 'Nenhum resultado para a busca' : 'Nenhuma decisão registrada'}
                                    description={
                                        filtering
                                            ? 'Ajuste a busca ou os filtros e tente novamente.'
                                            : 'As solicitações deferidas ou indeferidas pelo fluxo expresso aparecem aqui. Protocolos em análise técnica não geram decisão automática.'
                                    }
                                />
                            }
                        />

                        <Pagination
                            links={decisoes.links}
                            meta={{ from: decisoes.from, to: decisoes.to, total: decisoes.total }}
                        />
                    </div>
                </CardContent>
            </Card>
        </>
    );
}

/** Chip de filtro ativo com ação de limpar (um filtro por vez). */
function FilterChip({ label, onClear }: { label: string; onClear: () => void }) {
    return (
        <span className="inline-flex items-center gap-1.5">
            <Badge size="sm" color="light">
                {label}
            </Badge>
            <button
                type="button"
                onClick={onClear}
                className="text-theme-xs font-medium text-brand-500 transition hover:text-brand-600 dark:text-brand-400"
            >
                limpar
            </button>
        </span>
    );
}

ResultadosExpressoIndex.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
