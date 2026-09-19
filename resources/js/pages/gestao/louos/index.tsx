import { Head, router, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import PageHeader from '@/components/app/page-header';
import { AlertIcon } from '@/components/icons';
import Badge from '@/components/ui/badge';
import Button from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import DataTable from '@/components/ui/data-table/data-table';
import PerPageSelect from '@/components/ui/data-table/per-page-select';
import TableToolbar from '@/components/ui/data-table/table-toolbar';
import { useServerTable } from '@/components/ui/data-table/use-server-table';
import EmptyState from '@/components/ui/empty-state';
import { Modal } from '@/components/ui/modal';
import Pagination, { type PaginationLink } from '@/components/ui/pagination';
import GestaoLayout from '@/layouts/gestao-layout';
import type { SharedProps } from '@/types';
import { type QuadroItem, formatarData } from './quadro-fields';
import { getColumns } from './quadro-columns';

interface QuadroResumo {
    quadro: string;
    label: string;
    version: string | null;
    valid_from: string | null;
    total: number;
}

interface QuadroVersao {
    id: number;
    version: string;
    status: string;
    status_label: string;
    valid_from: string | null;
    valid_to: string | null;
    source: string | null;
    total: number;
    vigente: boolean;
}

interface LouosIndexProps {
    quadros: QuadroResumo[];
    quadroSelecionado: string;
    itens: {
        data: QuadroItem[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
    versoes: QuadroVersao[];
    filtros: {
        quadro: string;
        search: string;
        per_page: number;
    };
    perPageOptions: number[];
    urlRascunho: string;
    urlManual: string;
}

/**
 * Metadados de UX por Quadro. `operacional` distingue o que já aplica de ponta a
 * ponta do que está MODELADO a partir da Lei nº
 * 9.148/2016 mas depende de base territorial ainda pendente da SEDUR (Quadros 10
 * e 11A — zona urbanística e classificação viária). O aviso é honesto: a
 * regra existe e é versionada, mas não finge operação plena sem o insumo oficial.
 */
const QUADROS_META: Record<string, { descricao: string; operacional: boolean; nota?: string }> = {
    quadro10: {
        descricao: 'Permissão de uso por zona urbanística.',
        operacional: false,
        nota: 'Modelado a partir da Lei nº 9.148/2016. Aplica plenamente quando a base oficial de zonas urbanísticas (pendente SEDUR) for integrada; até lá, o uso por zona degrada para análise técnica.',
    },
    quadro11a: {
        descricao: 'Condições de uso por classe de via (complementar).',
        operacional: false,
        nota: 'Modelado a partir da Lei nº 9.148/2016. Aplica plenamente quando a classificação viária oficial (pendente SEDUR) for confirmada.',
    },
};

interface QuadroSelectorCardProps {
    resumo: QuadroResumo;
    selected: boolean;
    onSelect: () => void;
}

/**
 * Cartão-resumo de um Quadro que também é o seletor: mostra versão vigente,
 * vigência e total, sinaliza honestamente os Quadros apenas modelados e, ao ser
 * acionado, troca o Quadro consultado (param `quadro`).
 */
function QuadroSelectorCard({ resumo, selected, onSelect }: QuadroSelectorCardProps) {
    const meta = QUADROS_META[resumo.quadro] ?? QUADROS_META.quadro10;
    const vigenciaDesde = formatarData(resumo.valid_from);

    return (
        <button
            type="button"
            onClick={onSelect}
            aria-pressed={selected}
            className={`flex flex-col rounded-2xl border p-5 text-left transition focus:outline-hidden focus-visible:ring-2 focus-visible:ring-brand-500/40 ${
                selected
                    ? 'border-brand-500 bg-brand-50/60 ring-1 ring-brand-500 dark:border-brand-500 dark:bg-brand-500/10'
                    : 'border-gray-200 bg-white hover:border-brand-300 dark:border-gray-800 dark:bg-white/[0.03] dark:hover:border-brand-700'
            }`}
        >
            <div className="flex items-start justify-between gap-2">
                <h3 className="text-base font-medium text-gray-800 dark:text-white/90">{resumo.label}</h3>
                {resumo.version ? (
                    <Badge color="success" size="sm">
                        Vigente
                    </Badge>
                ) : (
                    <Badge color="warning" size="sm">
                        Sem versão
                    </Badge>
                )}
            </div>

            <p className="mt-1 text-theme-sm text-gray-500 dark:text-gray-400">{meta.descricao}</p>

            <div className="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-theme-xs text-gray-500 dark:text-gray-400">
                <span className="font-medium text-gray-700 dark:text-gray-300">{resumo.total} registros</span>
                {resumo.version && <span className="truncate">versão {resumo.version}</span>}
                {vigenciaDesde && <span>desde {vigenciaDesde}</span>}
            </div>

            {!meta.operacional && meta.nota && (
                <div className="mt-3 flex items-start gap-2 rounded-lg border border-warning-200 bg-warning-50 p-3 dark:border-warning-500/30 dark:bg-warning-500/10">
                    <AlertIcon className="size-4 shrink-0 fill-current text-warning-500" />
                    <p className="text-theme-xs text-gray-600 dark:text-gray-300">
                        <span className="font-medium text-warning-600 dark:text-orange-400">Modelado.</span>{' '}
                        {meta.nota}
                    </p>
                </div>
            )}
        </button>
    );
}

function AtivarVersaoModal({
    versao,
    quadro,
    onClose,
}: {
    versao: QuadroVersao;
    quadro: string;
    onClose: () => void;
}) {
    const [processing, setProcessing] = useState(false);

    function confirmar() {
        setProcessing(true);
        router.put(
            `/gestao/louos/versoes/${versao.id}/ativar`,
            { quadro },
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
                onSuccess: () => onClose(),
            },
        );
    }

    return (
        <Modal isOpen onClose={onClose} className="m-4 max-w-lg p-6 lg:p-8">
            <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90">Ativar versão</h4>
            <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">
                A versão <strong>{versao.version}</strong> ({versao.total} registros) passará a ser a vigente. A
                versão em uso hoje é fechada e permanece no histórico — nenhuma linha é apagada.
            </p>
            <div className="mt-6 flex items-center justify-end gap-3">
                <Button size="sm" variant="outline" onClick={onClose} disabled={processing}>
                    Cancelar
                </Button>
                <Button size="sm" onClick={confirmar} loading={processing}>
                    Ativar esta versão
                </Button>
            </div>
        </Modal>
    );
}

export default function LouosIndex({
    quadros,
    quadroSelecionado,
    itens,
    versoes,
    filtros,
    perPageOptions,
    urlRascunho,
    urlManual,
}: LouosIndexProps) {
    const { auth } = usePage<SharedProps>().props;
    const canMaintain = auth.permissions.includes('manter-louos');

    const table = useServerTable({
        url: '/gestao/louos',
        initialSearch: filtros.search,
        initialSort: { column: 'natural', direction: 'asc' },
        initialPerPage: filtros.per_page,
        initialFilters: { quadro: filtros.quadro },
    });

    const [versaoParaAtivar, setVersaoParaAtivar] = useState<QuadroVersao | null>(null);

    const selecionado = quadros.find((quadro) => quadro.quadro === quadroSelecionado) ?? quadros[0];
    const meta = QUADROS_META[quadroSelecionado] ?? QUADROS_META.quadro10;
    const columns = getColumns(quadroSelecionado);
    const filtering = table.search.trim() !== '';

    const vigenteDesc = selecionado?.version
        ? `Versão vigente ${selecionado.version}`
        : 'Sem versão vigente publicada';

    return (
        <>
            <Head title="Quadros da LOUOS" />
            <PageHeader title="Quadros da LOUOS" breadcrumbs={[{ label: 'Painel', href: '/gestao' }]} />

            <div className="space-y-6">
                <div className="grid gap-4 sm:grid-cols-2 md:gap-6 xl:grid-cols-4">
                    {quadros.map((quadro) => (
                        <QuadroSelectorCard
                            key={quadro.quadro}
                            resumo={quadro}
                            selected={quadro.quadro === table.filters.quadro}
                            onSelect={() => table.setFilter('quadro', quadro.quadro)}
                        />
                    ))}
                </div>

                <Card>
                    <CardHeader
                        title={selecionado?.label ?? 'Quadro da LOUOS'}
                        description={vigenteDesc}
                        actions={
                            canMaintain ? (
                                <div className="flex flex-wrap items-center gap-3">
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() => router.get(urlManual)}
                                    >
                                        Manual de CSV
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() => router.get(urlRascunho)}
                                    >
                                        Editar Quadro
                                    </Button>
                                    <Button size="sm" onClick={() => router.get(urlRascunho)}>
                                        Publicar nova versão
                                    </Button>
                                </div>
                            ) : undefined
                        }
                    />
                    <CardContent>
                        <div className="space-y-5">
                            {!meta.operacional && meta.nota && (
                                <div className="flex items-start gap-3 rounded-xl border border-warning-200 bg-warning-50 p-4 dark:border-warning-500/30 dark:bg-warning-500/10">
                                    <AlertIcon className="size-5 shrink-0 fill-current text-warning-500" />
                                    <p className="text-theme-sm text-gray-600 dark:text-gray-300">
                                        <span className="font-medium text-warning-600 dark:text-orange-400">
                                            Quadro modelado.
                                        </span>{' '}
                                        {meta.nota}
                                    </p>
                                </div>
                            )}

                            <TableToolbar
                                search={{
                                    value: table.search,
                                    onChange: table.setSearch,
                                    placeholder: 'Buscar no Quadro selecionado...',
                                    label: 'Buscar nos registros do Quadro',
                                }}
                                actions={
                                    <PerPageSelect
                                        value={table.perPage}
                                        options={perPageOptions}
                                        onChange={table.setPerPage}
                                    />
                                }
                            />

                            <DataTable
                                columns={columns}
                                rows={itens.data}
                                rowKey={(row) => row.id}
                                loading={table.processing}
                                skeletonRows={8}
                                density="compact"
                                emptyState={
                                    <EmptyState
                                        title={
                                            filtering ? 'Nenhum resultado para a busca' : 'Nenhum registro vigente'
                                        }
                                        description={
                                            filtering
                                                ? 'Ajuste o termo de busca e tente novamente.'
                                                : 'Publique uma versão deste Quadro para iniciar a base.'
                                        }
                                    />
                                }
                            />

                            <Pagination
                                links={itens.links}
                                meta={{ from: itens.from, to: itens.to, total: itens.total }}
                            />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        title="Histórico de versões"
                        description="Qualquer versão publicada pode voltar a ser a vigente. A anterior permanece no histórico."
                    />
                    <CardContent>
                        {versoes.length === 0 ? (
                            <EmptyState
                                title="Nenhuma versão publicada"
                                description="Publique ou importe um rascunho para criar a primeira versão deste Quadro."
                            />
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="min-w-full text-left text-theme-sm">
                                    <thead>
                                        <tr className="border-b border-gray-200 text-theme-xs text-gray-500 dark:border-gray-800 dark:text-gray-400">
                                            <th className="px-3 py-2 font-medium">Versão</th>
                                            <th className="px-3 py-2 font-medium">Situação</th>
                                            <th className="px-3 py-2 font-medium">Vigência</th>
                                            <th className="px-3 py-2 font-medium">Registros</th>
                                            {canMaintain && <th className="px-3 py-2 font-medium">Ação</th>}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {versoes.map((versao) => (
                                            <tr
                                                key={versao.id}
                                                className="border-b border-gray-100 last:border-0 dark:border-gray-800"
                                            >
                                                <td className="px-3 py-3 font-medium text-gray-800 dark:text-white/90">
                                                    {versao.version}
                                                </td>
                                                <td className="px-3 py-3">
                                                    <Badge color={versao.vigente ? 'success' : 'light'} size="sm">
                                                        {versao.status_label}
                                                    </Badge>
                                                </td>
                                                <td className="px-3 py-3 text-gray-500 dark:text-gray-400">
                                                    {formatarData(versao.valid_from) ?? '—'}
                                                    {versao.valid_to ? ` até ${formatarData(versao.valid_to)}` : ''}
                                                </td>
                                                <td className="px-3 py-3 text-gray-700 dark:text-gray-300">
                                                    {versao.total}
                                                </td>
                                                {canMaintain && (
                                                    <td className="px-3 py-3">
                                                        {versao.vigente ? (
                                                            <span className="text-theme-xs text-gray-400">Em uso</span>
                                                        ) : (
                                                            <Button
                                                                size="xs"
                                                                variant="outline"
                                                                onClick={() => setVersaoParaAtivar(versao)}
                                                            >
                                                                Ativar
                                                            </Button>
                                                        )}
                                                    </td>
                                                )}
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            {canMaintain && versaoParaAtivar && (
                <AtivarVersaoModal
                    versao={versaoParaAtivar}
                    quadro={quadroSelecionado}
                    onClose={() => setVersaoParaAtivar(null)}
                />
            )}
        </>
    );
}

LouosIndex.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;

