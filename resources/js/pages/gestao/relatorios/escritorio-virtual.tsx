import { Head, router } from '@inertiajs/react';
import { useCallback, useMemo, useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import PageHeader from '@/components/app/page-header';
import Checkbox from '@/components/form/checkbox';
import Input from '@/components/form/input';
import Label from '@/components/form/label';
import { InfoIcon, SearchIcon } from '@/components/icons';
import Badge from '@/components/ui/badge';
import Button from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import DataTable from '@/components/ui/data-table/data-table';
import { ExportMenu } from '@/components/ui/data-table/export-menu';
import type { ServerTableParams } from '@/components/ui/data-table/use-server-table';
import type { ColumnDef } from '@/components/ui/data-table/types';
import EmptyState from '@/components/ui/empty-state';
import Pagination, { type PaginationLink } from '@/components/ui/pagination';
import PerPageSelect from '@/components/ui/data-table/per-page-select';
import GestaoLayout from '@/layouts/gestao-layout';

/**
 * Linha do relatório sede × abrigados (Plano R1): a SEDE (alvo do lock ativo da
 * inscrição) e os ABRIGADOS (decisões is_virtual_office_tenant). Campos anuláveis
 * degradam para travessão — nunca um dado inventado.
 */
interface RelatorioRow {
    tipo: 'sede' | 'abrigado';
    tvl: string | null;
    razao_social: string | null;
    /** ISO8601 da emissão do TVL (decided_at) ou null quando não emitido. */
    data_emissao: string | null;
    inscricao: string | null;
    protocolo: string | null;
}

/**
 * Paginator do Laravel projetado por `->through(linha)`: serializa achatado
 * (data + links + from/to/total + per_page), o mesmo contrato consumido pelas
 * demais listagens de gestão.
 */
interface RelatorioPaginator {
    data: RelatorioRow[];
    links: PaginationLink[];
    from: number | null;
    to: number | null;
    total: number;
    per_page: number;
}

/** Filtros aplicados ecoados pelo backend (recorte vigente da tela). */
interface FiltrosAplicados {
    sede?: string | null;
    inscricao?: string | null;
}

interface EscritorioVirtualProps {
    relatorio: RelatorioPaginator;
    filtros: FiltrosAplicados;
    perPageOptions: number[];
}

interface FiltrosForm {
    sede: string;
    inscricao: string;
}

const URL_ESCRITORIO_VIRTUAL = '/gestao/relatorios/escritorio-virtual';

/**
 * A validade/vencimento do produto ainda não é modelada (pendência SAPS). A tela
 * degrada honestamente: a coluna Vencimento é sempre travessão e o filtro de
 * expirados fica desabilitado — nunca uma data ou recorte presumido.
 */
const VALIDADE_PENDENTE = 'Depende da validade do produto (pendente)';

/**
 * Formata a data ISO8601 de emissão no padrão brasileiro. null (ou data
 * inválida) vira travessão — jamais uma data inventada.
 */
function formatarDataEmissao(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    const data = new Date(iso);

    return Number.isNaN(data.getTime()) ? '—' : data.toLocaleDateString('pt-BR');
}

/** Badge do tipo: a SEDE é enfatizada (sólida); o abrigado fica discreto. */
function TipoBadge({ tipo }: { tipo: RelatorioRow['tipo'] }) {
    return tipo === 'sede' ? (
        <Badge variant="solid" color="primary" size="sm">
            Sede
        </Badge>
    ) : (
        <Badge variant="light" color="light" size="sm">
            Abrigado
        </Badge>
    );
}

/** Chave estável da linha (o paginator pode repetir inscrição entre sede/abrigado). */
function chaveLinha(row: RelatorioRow): string {
    return [row.tipo, row.protocolo ?? '', row.inscricao ?? '', row.tvl ?? ''].join('|');
}

const colunas: ColumnDef<RelatorioRow>[] = [
    {
        id: 'tipo',
        header: 'Tipo',
        cell: (linha) => <TipoBadge tipo={linha.tipo} />,
    },
    {
        id: 'tvl',
        header: 'Nº TVL',
        cellClassName: 'font-medium whitespace-nowrap text-gray-800 dark:text-white/90',
        cell: (linha) => linha.tvl ?? '—',
    },
    {
        id: 'razao_social',
        header: 'Razão Social',
        cellClassName: 'text-gray-800 dark:text-white/90',
        cell: (linha) => linha.razao_social ?? '—',
    },
    {
        id: 'data_emissao',
        header: 'Data Emissão',
        cellClassName: 'whitespace-nowrap',
        cell: (linha) => formatarDataEmissao(linha.data_emissao),
    },
    {
        id: 'vencimento',
        header: 'Vencimento',
        cell: () => (
            <span className="text-gray-400 dark:text-gray-500" title={VALIDADE_PENDENTE}>
                —
            </span>
        ),
    },
    {
        id: 'inscricao',
        header: 'Inscrição',
        cellClassName: 'whitespace-nowrap',
        cell: (linha) => linha.inscricao ?? '—',
    },
];

export default function EscritorioVirtual({ relatorio, filtros, perPageOptions }: EscritorioVirtualProps) {
    const [form, setForm] = useState<FiltrosForm>({
        sede: filtros.sede ?? '',
        inscricao: filtros.inscricao ?? '',
    });
    const [perPage, setPerPage] = useState<number>(relatorio.per_page);

    // Navegação server-side: filtros vazios são omitidos da URL; qualquer
    // mudança volta à página 1 (comportamento do paginator com withQueryString).
    const visitar = useCallback((estado: FiltrosForm, itensPorPagina: number) => {
        const params: Record<string, string | number> = { per_page: itensPorPagina };

        if (estado.sede.trim() !== '') {
            params.sede = estado.sede.trim();
        }

        if (estado.inscricao.trim() !== '') {
            params.inscricao = estado.inscricao.trim();
        }

        router.get(URL_ESCRITORIO_VIRTUAL, params, { preserveState: true, preserveScroll: true, replace: true });
    }, []);

    function pesquisar(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        visitar(form, perPage);
    }

    function limpar() {
        const vazio: FiltrosForm = { sede: '', inscricao: '' };
        setForm(vazio);
        visitar(vazio, perPage);
    }

    function alterarPerPage(valor: number) {
        setPerPage(valor);
        visitar(form, valor);
    }

    // Snapshot da exportação: o Excel sai exatamente sobre o recorte APLICADO
    // (o que a tela mostra), não sobre o que está digitado mas não pesquisado.
    const exportParams = useMemo<ServerTableParams>(() => {
        const params: ServerTableParams = {};

        if (filtros.sede) {
            params.sede = filtros.sede;
        }

        if (filtros.inscricao) {
            params.inscricao = filtros.inscricao;
        }

        return params;
    }, [filtros]);

    const filtrando = Boolean(filtros.sede) || Boolean(filtros.inscricao);

    return (
        <>
            <Head title="Relatório — Sede de Escritório Virtual" />
            <PageHeader
                title="Relatório — Sede de Escritório Virtual"
                breadcrumbs={[
                    { label: 'Painel', href: '/gestao' },
                    { label: 'Relatórios' },
                    { label: 'Sede de Escritório Virtual' },
                ]}
            />

            <div className="space-y-4 md:space-y-6">
                <RessalvaValidade />

                <Card>
                    <CardHeader
                        title="Filtros"
                        description="Recorte por número da sede (TVL) e/ou inscrição imobiliária. A sede é o alvo do lock ativo da inscrição; os abrigados são as decisões marcadas como escritório virtual."
                        actions={<ExportMenu url={URL_ESCRITORIO_VIRTUAL} params={exportParams} formatos={['xlsx']} label="Gerar Excel" />}
                    />
                    <CardContent>
                        <form onSubmit={pesquisar}>
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <Label htmlFor="filtro-sede">Nº da Sede (TVL)</Label>
                                    <Input
                                        id="filtro-sede"
                                        type="text"
                                        value={form.sede}
                                        onChange={(e) => setForm((atual) => ({ ...atual, sede: e.target.value }))}
                                        placeholder="Nº do TVL da sede"
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="filtro-inscricao">Inscrição Imobiliária</Label>
                                    <Input
                                        id="filtro-inscricao"
                                        type="text"
                                        value={form.inscricao}
                                        onChange={(e) => setForm((atual) => ({ ...atual, inscricao: e.target.value }))}
                                        placeholder="Inscrição imobiliária"
                                    />
                                </div>
                            </div>

                            <div className="mt-4 flex flex-wrap items-center gap-3">
                                <Button type="submit" size="sm" startIcon={<SearchIcon className="size-5" />}>
                                    Pesquisar
                                </Button>
                                <Button type="button" size="sm" variant="outline" onClick={limpar}>
                                    Limpar
                                </Button>
                            </div>

                            <div className="mt-5 border-t border-gray-100 pt-4 dark:border-white/[0.05]">
                                <Checkbox
                                    id="exibir-expirados"
                                    label="Exibir Expirados"
                                    checked={false}
                                    disabled
                                    onChange={() => {}}
                                />
                                <p
                                    id="exibir-expirados-hint"
                                    title={VALIDADE_PENDENTE}
                                    className="mt-1.5 text-theme-xs text-gray-400 dark:text-gray-500"
                                >
                                    {VALIDADE_PENDENTE}
                                </p>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        title="Sedes e abrigados"
                        description="Cada inscrição travada agrupa a sede e seus abrigados de escritório virtual. A exportação em Excel entrega exatamente este recorte."
                        actions={<PerPageSelect value={perPage} options={perPageOptions} onChange={alterarPerPage} />}
                    />
                    <CardContent>
                        <div className="space-y-5">
                            <DataTable<RelatorioRow>
                                columns={colunas}
                                rows={relatorio.data}
                                rowKey={chaveLinha}
                                density="compact"
                                emptyState={
                                    <EmptyState
                                        title={
                                            filtrando
                                                ? 'Nenhuma sede de escritório virtual para os filtros'
                                                : 'Nenhuma sede de escritório virtual encontrada'
                                        }
                                        description={
                                            filtrando
                                                ? 'Ajuste o número da sede ou a inscrição e tente novamente.'
                                                : 'Nenhuma sede de escritório virtual encontrada para os filtros.'
                                        }
                                    />
                                }
                            />

                            <Pagination
                                links={relatorio.links}
                                meta={{ from: relatorio.from, to: relatorio.to, total: relatorio.total }}
                            />
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

/**
 * Ressalva honesta (Plano R1): a validade/vencimento do produto (TVL) ainda não
 * é modelada. Enquanto a modelagem não chega, a coluna Vencimento fica em
 * travessão e o filtro de expirados fica desabilitado — nada é presumido.
 */
function RessalvaValidade() {
    return (
        <div className="flex items-start gap-3 rounded-2xl border border-warning-200 bg-warning-50 p-4 dark:border-warning-500/30 dark:bg-warning-500/10">
            <InfoIcon className="mt-0.5 size-5 shrink-0 text-warning-600 dark:text-orange-400" />
            <p className="text-theme-sm text-warning-700 dark:text-orange-300">
                A <strong>validade</strong> do produto ainda não é modelada. Até a modelagem, a coluna <strong>Vencimento</strong> fica em
                travessão e o filtro <strong>Exibir Expirados</strong> permanece desabilitado — nenhum vencimento é presumido.
            </p>
        </div>
    );
}

EscritorioVirtual.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
