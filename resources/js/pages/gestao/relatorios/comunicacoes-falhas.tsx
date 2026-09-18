import { Head, router } from '@inertiajs/react';
import { useCallback, useMemo, useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import PageHeader from '@/components/app/page-header';
import Input from '@/components/form/input';
import Label from '@/components/form/label';
import Select from '@/components/form/select';
import { AlertIcon, MailIcon, SearchIcon } from '@/components/icons';
import Badge from '@/components/ui/badge';
import Button from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import DataTable from '@/components/ui/data-table/data-table';
import { ExportMenu } from '@/components/ui/data-table/export-menu';
import PerPageSelect from '@/components/ui/data-table/per-page-select';
import type { ServerTableParams } from '@/components/ui/data-table/use-server-table';
import type { ColumnDef } from '@/components/ui/data-table/types';
import EmptyState from '@/components/ui/empty-state';
import KpiCard from '@/components/ui/kpi-card';
import Pagination, { type PaginationLink } from '@/components/ui/pagination';
import GestaoLayout from '@/layouts/gestao-layout';

/**
 * Falhas de comunicação (consulta operacional — HU-096): notificações que
 * falharam ou foram bloqueadas, por processo. SEM dados do destinatário (LGPD
 * — o cidadão não aparece). Campos anuláveis degradam para travessão — nunca
 * um dado inventado.
 */
interface ComunicacaoRow {
    id: number;
    processo: string | null;
    canal: string;
    canal_label: string;
    tipo_label: string;
    titulo: string | null;
    erro: string | null;
    status: string;
    status_label: string;
    em: string | null;
}

interface RelatorioPaginator {
    data: ComunicacaoRow[];
    links: PaginationLink[];
    from: number | null;
    to: number | null;
    total: number;
    per_page: number;
}

interface ResumoFalhas {
    falharam: number;
    bloqueadas: number;
    por_canal: { canal: string; canal_label: string; total: number }[];
}

interface FiltrosAplicados {
    data_de?: string;
    data_ate?: string;
    canal?: string;
}

interface ComunicacoesFalhasProps {
    resumo: ResumoFalhas;
    relatorio: RelatorioPaginator;
    filtros: FiltrosAplicados;
    perPageOptions: number[];
}

interface FiltrosForm {
    data_de: string;
    data_ate: string;
    canal: string;
}

const URL_FALHAS = '/gestao/relatorios/comunicacoes-falhas';

const CANAL_OPTIONS = [
    { value: 'email', label: 'E-mail' },
    { value: 'in_app', label: 'No sistema' },
    { value: 'whatsapp', label: 'WhatsApp' },
];

const numberFormat = new Intl.NumberFormat('pt-BR');
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

const colunas: ColumnDef<ComunicacaoRow>[] = [
    {
        id: 'processo',
        header: 'Processo',
        cellClassName: 'font-medium whitespace-nowrap text-gray-800 dark:text-white/90',
        cell: (linha) => linha.processo ?? '—',
    },
    {
        id: 'canal',
        header: 'Canal',
        cellClassName: 'whitespace-nowrap',
        cell: (linha) => linha.canal_label,
    },
    {
        id: 'tipo',
        header: 'Tipo',
        cellClassName: 'whitespace-nowrap',
        cell: (linha) => linha.tipo_label,
    },
    {
        id: 'titulo',
        header: 'Título',
        cellClassName: 'max-w-xs truncate',
        cell: (linha) => <span title={linha.titulo ?? undefined}>{linha.titulo ?? '—'}</span>,
    },
    {
        id: 'status',
        header: 'Situação',
        cell: (linha) => (
            <Badge variant="light" color={linha.status === 'falhou' ? 'error' : 'warning'} size="sm">
                {linha.status_label}
            </Badge>
        ),
    },
    {
        id: 'erro',
        header: 'Erro',
        cellClassName: 'max-w-xs truncate',
        cell: (linha) => <span title={linha.erro ?? undefined}>{linha.erro ?? '—'}</span>,
    },
    {
        id: 'em',
        header: 'Em',
        cellClassName: 'whitespace-nowrap',
        cell: (linha) => formatarDataHora(linha.em),
    },
];

export default function ComunicacoesFalhas({ resumo, relatorio, filtros, perPageOptions }: ComunicacoesFalhasProps) {
    const [form, setForm] = useState<FiltrosForm>({
        data_de: filtros.data_de ?? '',
        data_ate: filtros.data_ate ?? '',
        canal: filtros.canal ?? '',
    });
    const [perPage, setPerPage] = useState<number>(relatorio.per_page);

    // Navegação server-side: filtros vazios são omitidos da URL; qualquer
    // mudança volta à página 1 (comportamento do paginator com withQueryString).
    const visitar = useCallback((estado: FiltrosForm, itensPorPagina: number) => {
        const params: Record<string, string | number> = { per_page: itensPorPagina };

        if (estado.data_de.trim() !== '') {
            params.data_de = estado.data_de;
        }

        if (estado.data_ate.trim() !== '') {
            params.data_ate = estado.data_ate;
        }

        if (estado.canal.trim() !== '') {
            params.canal = estado.canal;
        }

        router.get(URL_FALHAS, params, { preserveState: true, preserveScroll: true, replace: true });
    }, []);

    function pesquisar(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        visitar(form, perPage);
    }

    function limpar() {
        const vazio: FiltrosForm = { data_de: '', data_ate: '', canal: '' };
        setForm(vazio);
        visitar(vazio, perPage);
    }

    function alterarPerPage(valor: number) {
        setPerPage(valor);
        visitar(form, valor);
    }

    // Snapshot da exportação: o arquivo sai exatamente sobre o recorte APLICADO.
    const exportParams = useMemo<ServerTableParams>(() => {
        const params: ServerTableParams = {};

        if (filtros.data_de) {
            params.data_de = filtros.data_de;
        }

        if (filtros.data_ate) {
            params.data_ate = filtros.data_ate;
        }

        if (filtros.canal) {
            params.canal = filtros.canal;
        }

        return params;
    }, [filtros]);

    const filtrando = Boolean(filtros.data_de) || Boolean(filtros.data_ate) || Boolean(filtros.canal);

    return (
        <>
            <Head title="Falhas de comunicação" />
            <PageHeader
                title="Falhas de comunicação"
                breadcrumbs={[{ label: 'Painel', href: '/gestao' }, { label: 'Relatórios' }, { label: 'Falhas de comunicação' }]}
            />

            <div className="space-y-4 md:space-y-6">
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 md:gap-6">
                    <KpiCard
                        label="Falharam"
                        value={numberFormat.format(resumo.falharam)}
                        note="erro no disparo, no recorte"
                        icon={<AlertIcon className="size-6" />}
                        tone="error"
                    />
                    <KpiCard
                        label="Bloqueadas"
                        value={numberFormat.format(resumo.bloqueadas)}
                        note="canal indisponível no disparo, no recorte"
                        icon={<MailIcon className="size-6" />}
                        tone="warning"
                    />
                </div>

                {resumo.por_canal.length > 0 && (
                    <Card>
                        <CardHeader title="Por canal" description="Quebra das não entregues do recorte." />
                        <CardContent>
                            <ul className="flex flex-col divide-y divide-gray-100 dark:divide-white/[0.05]">
                                {resumo.por_canal.map((item) => (
                                    <li key={item.canal} className="flex items-center justify-between gap-4 py-2.5">
                                        <span className="text-theme-sm text-gray-800 dark:text-white/90">{item.canal_label}</span>
                                        <span className="text-theme-sm font-medium text-gray-500 dark:text-gray-400">
                                            {numberFormat.format(item.total)}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader
                        title="Filtros"
                        description="Recorte por período e canal. A exportação (CSV/Excel/PDF) entrega exatamente o recorte aplicado — sem dados do destinatário."
                        actions={<ExportMenu url={URL_FALHAS} params={exportParams} />}
                    />
                    <CardContent>
                        <form onSubmit={pesquisar}>
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                <div>
                                    <Label htmlFor="filtro-data-de">De</Label>
                                    <Input
                                        id="filtro-data-de"
                                        type="date"
                                        value={form.data_de}
                                        onChange={(e) => setForm((atual) => ({ ...atual, data_de: e.target.value }))}
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="filtro-data-ate">Até</Label>
                                    <Input
                                        id="filtro-data-ate"
                                        type="date"
                                        value={form.data_ate}
                                        onChange={(e) => setForm((atual) => ({ ...atual, data_ate: e.target.value }))}
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="filtro-canal">Canal</Label>
                                    <Select
                                        id="filtro-canal"
                                        options={CANAL_OPTIONS}
                                        placeholder="Todos os canais"
                                        value={form.canal}
                                        onChange={(valor) => setForm((atual) => ({ ...atual, canal: valor }))}
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
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        title="Não entregues"
                        description="As mais recentes primeiro. Cada linha é uma pendência operacional — o requerente pode não ter sido avisado."
                        actions={<PerPageSelect value={perPage} options={perPageOptions} onChange={alterarPerPage} />}
                    />
                    <CardContent>
                        <div className="space-y-5">
                            <DataTable<ComunicacaoRow>
                                columns={colunas}
                                rows={relatorio.data}
                                rowKey={(linha) => String(linha.id)}
                                density="compact"
                                emptyState={
                                    <EmptyState
                                        title={filtrando ? 'Nenhuma falha para os filtros' : 'Nenhuma falha no recorte'}
                                        description={
                                            filtrando
                                                ? 'Ajuste o período ou o canal e tente novamente.'
                                                : 'Quando uma notificação falhar ou for bloqueada, ela aparece aqui.'
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

ComunicacoesFalhas.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
