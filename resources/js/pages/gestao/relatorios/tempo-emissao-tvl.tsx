import { Head, router } from '@inertiajs/react';
import { useCallback, useMemo, useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import PageHeader from '@/components/app/page-header';
import Checkbox from '@/components/form/checkbox';
import Input from '@/components/form/input';
import Label from '@/components/form/label';
import Select from '@/components/form/select';
import { InfoIcon, SearchIcon, TableIcon } from '@/components/icons';
import Badge from '@/components/ui/badge';
import Button from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import DataTable from '@/components/ui/data-table/data-table';
import { ExportMenu } from '@/components/ui/data-table/export-menu';
import PerPageSelect from '@/components/ui/data-table/per-page-select';
import type { ServerTableParams } from '@/components/ui/data-table/use-server-table';
import type { ColumnDef } from '@/components/ui/data-table/types';
import { Dropdown } from '@/components/ui/dropdown';
import EmptyState from '@/components/ui/empty-state';
import Pagination, { type PaginationLink } from '@/components/ui/pagination';
import GestaoLayout from '@/layouts/gestao-layout';

/**
 * Linha do relatório "Tempo de Emissão de TVL" (Tela R2 — SAPS): um processo
 * DECIDIDO no recorte com o tempo Emissão−Abertura em minutos ÚTEIS. Campos
 * anuláveis degradam para travessão — nunca um dado inventado. Os blocos DAM não
 * são modelados no SILE (vêm em branco) e o `tipo` é sempre "Viabilidade" (a
 * Revisão via REDESIM não é homologada).
 */
interface TvlRow {
    processo: string | null;
    servico: string | null;
    tipo: string;
    /** ISO8601 da Abertura (created_at). */
    abertura: string | null;
    dam_numero: string | null;
    dam_emissao: string | null;
    dam_pagamento: string | null;
    dam_valor: string | null;
    tvl_disponivel: boolean;
    tvl_numero: string | null;
    /** ISO8601 da Emissão (decided_at). */
    emissao: string | null;
    /** Minutos ÚTEIS entre Abertura e Emissão (desconta fim de semana/feriado). */
    duracao_minutos: number | null;
}

/** Paginator do Laravel projetado por `->through(linha)` (serializa achatado). */
interface RelatorioPaginator {
    data: TvlRow[];
    links: PaginationLink[];
    from: number | null;
    to: number | null;
    total: number;
    per_page: number;
}

interface ServicoOption {
    value: number;
    label: string;
}

/** Filtros aplicados ecoados pelo backend (recorte vigente da tela). */
interface FiltrosAplicados {
    data_de?: string;
    data_ate?: string;
    servico?: number | string;
    resultado?: string;
    cnae?: string;
    tipo?: string;
}

interface TempoEmissaoTvlProps {
    relatorio: RelatorioPaginator;
    servicos: ServicoOption[];
    filtros: FiltrosAplicados;
    perPageOptions: number[];
}

interface FiltrosForm {
    servico: string;
    data_de: string;
    data_ate: string;
    resultado: string;
    cnae: string;
}

const URL_TEMPO_EMISSAO_TVL = '/gestao/relatorios/tempo-emissao-tvl';

/** Blocos DAM (Documento de Arrecadação Municipal) — não modelados no SILE. */
const COLUNAS_DAM = ['dam_numero', 'dam_emissao', 'dam_pagamento', 'dam_valor'] as const;

/**
 * Degradação honesta: a integração de Revisão (REDESIM) não é homologada. O modo
 * fica VISÍVEL mas desabilitado e NENHUM dado de revisão é simulado.
 */
const AVISO_REVISAO = 'Revisões via REDESIM — integração não homologada';

const RESULTADO_OPTIONS = [
    { value: 'deferida', label: 'Deferido' },
    { value: 'indeferida', label: 'Indeferido' },
];

const dateTimeFormat = new Intl.DateTimeFormat('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
});

/** Formata a data/hora ISO8601 no padrão brasileiro; null/inválida → travessão. */
function formatarDataHora(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    const data = new Date(iso);

    return Number.isNaN(data.getTime()) ? '—' : dateTimeFormat.format(data);
}

/** Minutos úteis → "Xh Ym" (tempo útil, sem fim de semana/feriado); null → travessão. */
function formatarDuracao(minutos: number | null): string {
    if (minutos === null || Number.isNaN(minutos)) {
        return '—';
    }

    const horas = Math.floor(minutos / 60);
    const resto = minutos % 60;

    return `${horas}h ${resto.toString().padStart(2, '0')}m`;
}

/** Badge de disponibilidade do TVL (Sim enfatizado, Não discreto). */
function DisponivelBadge({ disponivel }: { disponivel: boolean }) {
    return disponivel ? (
        <Badge variant="light" color="success" size="sm">
            Sim
        </Badge>
    ) : (
        <Badge variant="light" color="light" size="sm">
            Não
        </Badge>
    );
}

export default function TempoEmissaoTvl({ relatorio, servicos, filtros, perPageOptions }: TempoEmissaoTvlProps) {
    const [form, setForm] = useState<FiltrosForm>({
        servico: filtros.servico != null ? String(filtros.servico) : '',
        data_de: filtros.data_de ?? '',
        data_ate: filtros.data_ate ?? '',
        resultado: filtros.resultado ?? '',
        cnae: filtros.cnae ?? '',
    });
    const [perPage, setPerPage] = useState<number>(relatorio.per_page);

    // Seletor de colunas: os blocos DAM ficam OCULTOS por padrão (vêm em branco).
    const [damVisiveis, setDamVisiveis] = useState<Set<string>>(new Set());
    const [seletorAberto, setSeletorAberto] = useState(false);

    // Navegação server-side: filtros vazios são omitidos da URL; qualquer mudança
    // volta à página 1 (withQueryString no paginator). A Revisão está desabilitada,
    // então `tipo` nunca é enviado — o backend trata como Viabilidade.
    const visitar = useCallback((estado: FiltrosForm, itensPorPagina: number) => {
        const params: Record<string, string | number> = { per_page: itensPorPagina };

        if (estado.servico.trim() !== '') {
            params.servico = estado.servico.trim();
        }
        if (estado.data_de.trim() !== '') {
            params.data_de = estado.data_de;
        }
        if (estado.data_ate.trim() !== '') {
            params.data_ate = estado.data_ate;
        }
        if (estado.resultado.trim() !== '') {
            params.resultado = estado.resultado;
        }
        if (estado.cnae.trim() !== '') {
            params.cnae = estado.cnae.trim();
        }

        router.get(URL_TEMPO_EMISSAO_TVL, params, { preserveState: true, preserveScroll: true, replace: true });
    }, []);

    function pesquisar(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        visitar(form, perPage);
    }

    function limpar() {
        const vazio: FiltrosForm = { servico: '', data_de: '', data_ate: '', resultado: '', cnae: '' };
        setForm(vazio);
        visitar(vazio, perPage);
    }

    function alterarPerPage(valor: number) {
        setPerPage(valor);
        visitar(form, valor);
    }

    function alternarColuna(id: string, visivel: boolean) {
        setDamVisiveis((atual) => {
            const proximo = new Set(atual);
            if (visivel) {
                proximo.add(id);
            } else {
                proximo.delete(id);
            }
            return proximo;
        });
    }

    // Snapshot da exportação: o Excel sai exatamente sobre o recorte APLICADO
    // (o que a tela mostra), não sobre o que está digitado mas não pesquisado.
    const exportParams = useMemo<ServerTableParams>(() => {
        const params: ServerTableParams = {};

        if (filtros.servico != null && String(filtros.servico) !== '') {
            params.servico = String(filtros.servico);
        }
        if (filtros.data_de) {
            params.data_de = filtros.data_de;
        }
        if (filtros.data_ate) {
            params.data_ate = filtros.data_ate;
        }
        if (filtros.resultado) {
            params.resultado = filtros.resultado;
        }
        if (filtros.cnae) {
            params.cnae = filtros.cnae;
        }

        return params;
    }, [filtros]);

    const filtrando =
        Boolean(filtros.servico) ||
        Boolean(filtros.data_de) ||
        Boolean(filtros.data_ate) ||
        Boolean(filtros.resultado) ||
        Boolean(filtros.cnae);

    const servicoOptions = useMemo(
        () => servicos.map((s) => ({ value: String(s.value), label: s.label })),
        [servicos],
    );

    // Processo é a coluna FIXA (sticky à esquerda); os blocos DAM são opcionais.
    const stickyHeader = 'sticky left-0 z-20 bg-gray-50 dark:bg-gray-900';
    const stickyCell = 'sticky left-0 z-10 bg-white font-medium whitespace-nowrap text-gray-800 dark:bg-gray-900 dark:text-white/90';

    const todasColunas: ColumnDef<TvlRow>[] = [
        {
            id: 'processo',
            header: 'Processo',
            headerClassName: stickyHeader,
            cellClassName: stickyCell,
            cell: (linha) => linha.processo ?? '—',
        },
        {
            id: 'servico',
            header: 'Serviço',
            cellClassName: 'whitespace-nowrap',
            cell: (linha) => linha.servico ?? '—',
        },
        {
            id: 'tipo',
            header: 'Tipo',
            cell: (linha) => linha.tipo,
        },
        {
            id: 'abertura',
            header: 'Abertura',
            cellClassName: 'whitespace-nowrap',
            cell: (linha) => formatarDataHora(linha.abertura),
        },
        { id: 'dam_numero', header: 'DAM Nº', cell: (linha) => linha.dam_numero ?? '—' },
        { id: 'dam_emissao', header: 'DAM Emissão', cell: (linha) => formatarDataHora(linha.dam_emissao) },
        { id: 'dam_pagamento', header: 'DAM Pagamento', cell: (linha) => formatarDataHora(linha.dam_pagamento) },
        { id: 'dam_valor', header: 'DAM Valor', cell: (linha) => linha.dam_valor ?? '—' },
        {
            id: 'tvl_disponivel',
            header: 'TVL Disponível',
            cell: (linha) => <DisponivelBadge disponivel={linha.tvl_disponivel} />,
        },
        {
            id: 'tvl_numero',
            header: 'Nº TVL',
            cellClassName: 'whitespace-nowrap font-medium text-gray-800 dark:text-white/90',
            cell: (linha) => linha.tvl_numero ?? '—',
        },
        {
            id: 'emissao',
            header: 'Emissão',
            cellClassName: 'whitespace-nowrap',
            cell: (linha) => formatarDataHora(linha.emissao),
        },
        {
            id: 'duracao_minutos',
            header: 'Emissão−Abertura',
            align: 'end',
            cellClassName: 'whitespace-nowrap',
            cell: (linha) => (
                <span title="Tempo útil (desconta fim de semana e feriados)">{formatarDuracao(linha.duracao_minutos)}</span>
            ),
        },
    ];

    const colunas = todasColunas.filter(
        (coluna) => !COLUNAS_DAM.includes(coluna.id as (typeof COLUNAS_DAM)[number]) || damVisiveis.has(coluna.id),
    );

    return (
        <>
            <Head title="Tempo de Emissão de TVL" />
            <PageHeader
                title="Tempo de Emissão de TVL"
                breadcrumbs={[
                    { label: 'Painel', href: '/gestao' },
                    { label: 'Relatórios' },
                    { label: 'Tempo de Emissão de TVL' },
                ]}
            />

            <div className="space-y-4 md:space-y-6">
                <AvisoRevisao />

                <Card>
                    <CardHeader
                        title="Filtros"
                        description="Recorte por serviço, período de emissão, resultado e CNAE. O período recorta pela emissão do TVL — sem processos no recorte, a tabela fica vazia (nunca um dado inventado)."
                        actions={
                            <ExportMenu
                                url={URL_TEMPO_EMISSAO_TVL}
                                params={exportParams}
                                formatos={['xlsx']}
                                label="Exportar Excel"
                            />
                        }
                    />
                    <CardContent>
                        <form onSubmit={pesquisar}>
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                <div>
                                    <Label htmlFor="filtro-servico">Serviço</Label>
                                    <Select
                                        id="filtro-servico"
                                        options={servicoOptions}
                                        placeholder="Todos os serviços"
                                        value={form.servico}
                                        onChange={(valor) => setForm((atual) => ({ ...atual, servico: valor }))}
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="filtro-data-de">Data inicial</Label>
                                    <Input
                                        id="filtro-data-de"
                                        type="date"
                                        value={form.data_de}
                                        onChange={(e) => setForm((atual) => ({ ...atual, data_de: e.target.value }))}
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="filtro-data-ate">Data final</Label>
                                    <Input
                                        id="filtro-data-ate"
                                        type="date"
                                        value={form.data_ate}
                                        onChange={(e) => setForm((atual) => ({ ...atual, data_ate: e.target.value }))}
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="filtro-resultado">Resultado</Label>
                                    <Select
                                        id="filtro-resultado"
                                        options={RESULTADO_OPTIONS}
                                        placeholder="Todos os resultados"
                                        value={form.resultado}
                                        onChange={(valor) => setForm((atual) => ({ ...atual, resultado: valor }))}
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="filtro-cnae">CNAE</Label>
                                    <Input
                                        id="filtro-cnae"
                                        type="text"
                                        value={form.cnae}
                                        onChange={(e) => setForm((atual) => ({ ...atual, cnae: e.target.value }))}
                                        placeholder="Código do CNAE"
                                    />
                                </div>
                                <ViabilidadeRevisao />
                            </div>

                            <div className="mt-4 flex flex-wrap items-center gap-3">
                                <Button type="submit" size="sm" startIcon={<SearchIcon className="size-5" />}>
                                    Pesquisar
                                </Button>
                                <Button type="button" size="sm" variant="outline" onClick={limpar}>
                                    Limpar
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        title="Processos e tempo de emissão"
                        description="Cada linha é um processo decidido no recorte com o tempo entre a abertura e a emissão do TVL. A exportação em Excel entrega exatamente este recorte."
                        actions={
                            <div className="flex flex-wrap items-center gap-3">
                                <SeletorColunas
                                    aberto={seletorAberto}
                                    onToggle={() => setSeletorAberto((v) => !v)}
                                    onClose={() => setSeletorAberto(false)}
                                    colunas={COLUNAS_DAM.map((id) => ({
                                        id,
                                        label: rotuloDam(id),
                                        visivel: damVisiveis.has(id),
                                    }))}
                                    onAlternar={alternarColuna}
                                />
                                <PerPageSelect value={perPage} options={perPageOptions} onChange={alterarPerPage} />
                            </div>
                        }
                    />
                    <CardContent>
                        <div className="space-y-5">
                            <DataTable<TvlRow>
                                columns={colunas}
                                rows={relatorio.data}
                                rowKey={(linha) => `${linha.processo ?? ''}|${linha.tvl_numero ?? ''}`}
                                density="compact"
                                emptyState={
                                    <EmptyState
                                        title={
                                            filtrando
                                                ? 'Nenhum TVL emitido para os filtros'
                                                : 'Nenhum TVL emitido no período'
                                        }
                                        description={
                                            filtrando
                                                ? 'Ajuste o serviço, o período, o resultado ou o CNAE e tente novamente.'
                                                : 'Quando houver processos decididos, o tempo de emissão do TVL aparece aqui.'
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

/** Rótulo pt-BR de cada bloco DAM no seletor de colunas. */
function rotuloDam(id: (typeof COLUNAS_DAM)[number]): string {
    return {
        dam_numero: 'DAM Nº',
        dam_emissao: 'DAM Emissão',
        dam_pagamento: 'DAM Pagamento',
        dam_valor: 'DAM Valor',
    }[id];
}

/**
 * Controle Viabilidade × Revisão: a Viabilidade é o único modo ATIVO. A Revisão
 * fica VISÍVEL mas desabilitada (integração REDESIM não homologada) — nenhum dado
 * de revisão é simulado (degradação honesta, CA-R2-05).
 */
function ViabilidadeRevisao() {
    return (
        <div>
            <span className="mb-1.5 block text-theme-xs font-medium text-gray-700 dark:text-gray-400">Modo</span>
            <div
                role="group"
                aria-label="Modo do relatório: Viabilidade ou Revisão"
                className="inline-flex rounded-lg border border-gray-300 p-1 dark:border-gray-700"
            >
                <span
                    aria-current="true"
                    className="rounded-md bg-brand-500 px-3 py-1.5 text-theme-sm font-medium text-white"
                >
                    Viabilidade
                </span>
                <button
                    type="button"
                    disabled
                    aria-disabled="true"
                    title={AVISO_REVISAO}
                    className="cursor-not-allowed rounded-md px-3 py-1.5 text-theme-sm font-medium text-gray-400 dark:text-gray-500"
                >
                    Revisão
                </button>
            </div>
            <p className="mt-1.5 text-theme-xs text-gray-400 dark:text-gray-500">{AVISO_REVISAO}</p>
        </div>
    );
}

/** Aviso de topo reforçando a degradação honesta da Revisão. */
function AvisoRevisao() {
    return (
        <div className="flex items-start gap-3 rounded-2xl border border-warning-200 bg-warning-50 p-4 dark:border-warning-500/30 dark:bg-warning-500/10">
            <InfoIcon className="mt-0.5 size-5 shrink-0 text-warning-600 dark:text-orange-400" />
            <p className="text-theme-sm text-warning-700 dark:text-orange-300">
                Este relatório cobre a <strong>Viabilidade</strong>. As <strong>Revisões via REDESIM</strong> dependem de uma
                integração ainda <strong>não homologada</strong> — o modo aparece desabilitado e nenhum dado de revisão é
                exibido. Os blocos <strong>DAM</strong> não são modelados no SILE e ficam em branco (ocultos por padrão no seletor
                de colunas).
            </p>
        </div>
    );
}

interface ColunaOpcional {
    id: string;
    label: string;
    visivel: boolean;
}

/**
 * Seletor de colunas opcionais (blocos DAM): ocultas por padrão, alternáveis por
 * checkbox. Dropdown acessível com rótulos associados aos controles.
 */
function SeletorColunas({
    aberto,
    onToggle,
    onClose,
    colunas,
    onAlternar,
}: {
    aberto: boolean;
    onToggle: () => void;
    onClose: () => void;
    colunas: ColunaOpcional[];
    onAlternar: (id: string, visivel: boolean) => void;
}) {
    const visiveis = colunas.filter((c) => c.visivel).length;

    return (
        <div className="relative inline-block">
            <button
                type="button"
                onClick={onToggle}
                aria-haspopup="menu"
                aria-expanded={aberto}
                className="inline-flex items-center justify-center gap-2 rounded-lg bg-white px-4 py-3 text-sm text-gray-700 shadow-theme-xs ring-1 ring-gray-300 ring-inset transition hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-400 dark:ring-gray-700 dark:hover:bg-white/[0.03]"
            >
                <TableIcon className="size-5" />
                Colunas DAM{visiveis > 0 ? ` (${visiveis})` : ''}
            </button>

            <Dropdown isOpen={aberto} onClose={onClose} className="w-56 p-3">
                <p className="mb-2 text-theme-xs font-medium text-gray-500 dark:text-gray-400">
                    Blocos DAM (em branco — não modelados)
                </p>
                <ul className="flex flex-col gap-2">
                    {colunas.map((coluna) => (
                        <li key={coluna.id}>
                            <Checkbox
                                id={`coluna-${coluna.id}`}
                                label={coluna.label}
                                checked={coluna.visivel}
                                onChange={(checked) => onAlternar(coluna.id, checked)}
                            />
                        </li>
                    ))}
                </ul>
            </Dropdown>
        </div>
    );
}

TempoEmissaoTvl.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
