import { Head, router, usePage } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import { useState } from 'react';
import { CategoriaBadges, type ProcessoItem } from '@/components/analise/processo-ui';
import PageHeader from '@/components/app/page-header';
import Checkbox from '@/components/form/checkbox';
import Input from '@/components/form/input';
import Label from '@/components/form/label';
import Select from '@/components/form/select';
import { ArrowRightIcon, FileIcon } from '@/components/icons';
import Badge from '@/components/ui/badge';
import Button from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import DataTable from '@/components/ui/data-table/data-table';
import PerPageSelect from '@/components/ui/data-table/per-page-select';
import type { ColumnDef } from '@/components/ui/data-table/types';
import EmptyState from '@/components/ui/empty-state';
import Pagination, { type PaginationLink } from '@/components/ui/pagination';
import TableAction from '@/components/ui/table-action';
import GestaoLayout from '@/layouts/gestao-layout';
import type { SharedProps } from '@/types';

interface SelectOption {
    value: string;
    label: string;
}

/** Filtros de texto/seleção do SAPS (sem o per_page, que é numérico). */
interface FiltrosTexto {
    grupo: string;
    status: string;
    protocolo: string;
    bap: string;
    produto_tvl: string;
    servico: string;
    setor: string;
    analista: string;
    categoria: string;
    inscricao: string;
    nome: string;
    cnpj: string;
    cep: string;
    logradouro: string;
    bairro: string;
    data_de: string;
    data_ate: string;
    analysis_status: string;
}

interface ConsultaProps {
    processos: {
        data: ProcessoItem[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
    filtros: FiltrosTexto & { per_page: number };
    perPageOptions: number[];
    statusOptions: SelectOption[];
    categoriaOptions: SelectOption[];
    analysisStatusOptions: SelectOption[];
}

/** Grupos macro de status (espelham GRUPOS_STATUS do ProcessoQueryService). */
const GRUPO_OPTIONS: SelectOption[] = [
    { value: 'rascunho', label: 'Rascunho' },
    { value: 'em_andamento', label: 'Em andamento' },
    { value: 'concluido', label: 'Concluído' },
    { value: 'cancelado', label: 'Cancelado' },
];

const CHAVES_FILTRO: (keyof FiltrosTexto)[] = [
    'grupo',
    'status',
    'protocolo',
    'bap',
    'produto_tvl',
    'servico',
    'setor',
    'analista',
    'categoria',
    'inscricao',
    'nome',
    'cnpj',
    'cep',
    'logradouro',
    'bairro',
    'data_de',
    'data_ate',
    'analysis_status',
];

function statusColor(status: string): 'success' | 'error' | 'warning' | 'info' | 'light' {
    if (status === 'deferida') {
        return 'success';
    }
    if (status === 'indeferida' || status === 'cancelada') {
        return 'error';
    }
    if (status === 'em_pendencia') {
        return 'warning';
    }
    if (status === 'em_analise') {
        return 'info';
    }
    return 'light';
}

export default function ConsultaProcessos({
    processos,
    filtros,
    perPageOptions,
    statusOptions,
    categoriaOptions,
    analysisStatusOptions,
}: ConsultaProps) {
    const { auth } = usePage<SharedProps>().props;
    const podeMalhaFina = auth.permissions.includes('encaminhar-malha-fina');

    const [form, setForm] = useState<FiltrosTexto>(() => {
        const inicial = {} as FiltrosTexto;

        for (const chave of CHAVES_FILTRO) {
            inicial[chave] = filtros[chave] ?? '';
        }

        return inicial;
    });
    const [perPage, setPerPage] = useState(filtros.per_page);

    const [selecionados, setSelecionados] = useState<number[]>([]);
    const [motivo, setMotivo] = useState('');
    const [erroMotivo, setErroMotivo] = useState<string | null>(null);
    const [enviando, setEnviando] = useState(false);

    function definir(chave: keyof FiltrosTexto, valor: string) {
        setForm((anterior) => ({ ...anterior, [chave]: valor }));
    }

    function montarParams(base: FiltrosTexto, pp: number): Record<string, string | number> {
        const params: Record<string, string | number> = { per_page: pp };

        for (const chave of CHAVES_FILTRO) {
            const valor = base[chave].trim();

            if (valor !== '') {
                params[chave] = valor;
            }
        }

        return params;
    }

    function visitar(base: FiltrosTexto, pp: number) {
        router.get('/gestao/processos', montarParams(base, pp), {
            preserveScroll: true,
            preserveState: true,
            replace: true,
            onSuccess: () => setSelecionados([]),
        });
    }

    function aplicar(evento: FormEvent) {
        evento.preventDefault();
        visitar(form, perPage);
    }

    function limpar() {
        const vazio = {} as FiltrosTexto;

        for (const chave of CHAVES_FILTRO) {
            vazio[chave] = '';
        }

        setForm(vazio);
        visitar(vazio, perPage);
    }

    function trocarPerPage(proximo: number) {
        setPerPage(proximo);
        visitar(form, proximo);
    }

    function encaminharMalhaFina() {
        if (motivo.trim() === '') {
            setErroMotivo('Informe o motivo do encaminhamento.');

            return;
        }

        router.post(
            '/gestao/processos/malha-fina',
            { request_ids: selecionados, motivo },
            {
                preserveScroll: true,
                onStart: () => setEnviando(true),
                onFinish: () => setEnviando(false),
                onSuccess: () => {
                    setSelecionados([]);
                    setMotivo('');
                    setErroMotivo(null);
                },
            },
        );
    }

    const idsNaPagina = processos.data.map((item) => item.id);
    const todosSelecionados = idsNaPagina.length > 0 && idsNaPagina.every((id) => selecionados.includes(id));

    function alternarTodos() {
        setSelecionados(todosSelecionados ? [] : idsNaPagina);
    }

    function alternar(id: number) {
        setSelecionados((anterior) =>
            anterior.includes(id) ? anterior.filter((item) => item !== id) : [...anterior, id],
        );
    }

    const filtrando = CHAVES_FILTRO.some((chave) => (filtros[chave] ?? '') !== '');

    const csvParams = new URLSearchParams({ formato: 'csv' });
    for (const chave of CHAVES_FILTRO) {
        const valor = filtros[chave] ?? '';
        if (valor !== '') {
            csvParams.set(chave, valor);
        }
    }
    const csvHref = `/gestao/processos?${csvParams.toString()}`;

    const columns: ColumnDef<ProcessoItem>[] = [
        ...(podeMalhaFina
            ? [
                  {
                      id: 'selecao',
                      header: <Checkbox checked={todosSelecionados} onChange={alternarTodos} />,
                      cellClassName: 'w-10',
                      cell: (item: ProcessoItem) => (
                          <Checkbox checked={selecionados.includes(item.id)} onChange={() => alternar(item.id)} />
                      ),
                  } satisfies ColumnDef<ProcessoItem>,
              ]
            : []),
        {
            id: 'processo',
            header: 'Processo',
            cellClassName: 'whitespace-nowrap',
            cell: (item) => (
                <div className="flex flex-col">
                    <span className="font-medium text-gray-800 dark:text-white/90">{item.protocol_number ?? '—'}</span>
                    <span className="text-theme-xs text-gray-400 dark:text-gray-500">
                        {item.bap ? `BAP ${item.bap}` : 'sem BAP'}
                        {item.tvl_product_number ? ` · TVL ${item.tvl_product_number}` : ''}
                    </span>
                </div>
            ),
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
            id: 'categoria',
            header: 'Categoria',
            cell: (item) => <CategoriaBadges categorias={item.categorias} />,
        },
        {
            id: 'status',
            header: 'Status',
            cellClassName: 'whitespace-nowrap',
            cell: (item) => (
                <Badge color={statusColor(item.status)} size="sm">
                    {item.status_label}
                </Badge>
            ),
        },
        {
            id: 'analysis_status',
            header: 'Situação da análise',
            cellClassName: 'whitespace-nowrap',
            cell: (item) =>
                item.analysis_status_label ? (
                    <Badge color="light" size="sm">
                        {item.analysis_status_label}
                    </Badge>
                ) : (
                    <span className="text-gray-400 dark:text-gray-500">—</span>
                ),
        },
        {
            id: 'responsavel',
            header: 'Responsável',
            cell: (item) => (
                <div className="flex flex-col">
                    <span className="text-gray-700 dark:text-gray-300">{item.analista ?? 'Não atribuído'}</span>
                    {item.setor && <span className="text-theme-xs text-gray-400 dark:text-gray-500">{item.setor}</span>}
                </div>
            ),
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
                        href={`/gestao/processos/${item.id}`}
                        icon={<ArrowRightIcon className="size-4.5" />}
                        label="Abrir processo"
                    />
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title="Consulta de processos" />
            <PageHeader title="Consulta de processos" breadcrumbs={[{ label: 'Painel', href: '/gestao' }]} />

            <div className="space-y-6">
                <Card>
                    <CardHeader
                        title="Filtros"
                        description="Localize processos pelos identificadores, empresa, imóvel, responsável e categoria."
                    />
                    <CardContent>
                        <form onSubmit={aplicar} className="space-y-5">
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                                <div>
                                    <Label htmlFor="filtro-grupo">Grupo de status</Label>
                                    <Select
                                        id="filtro-grupo"
                                        value={form.grupo}
                                        onChange={(valor) => definir('grupo', valor)}
                                        placeholder="Todos"
                                        options={GRUPO_OPTIONS}
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="filtro-status">Status</Label>
                                    <Select
                                        id="filtro-status"
                                        value={form.status}
                                        onChange={(valor) => definir('status', valor)}
                                        placeholder="Todos"
                                        options={statusOptions}
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="filtro-analysis-status">Situação da análise</Label>
                                    <Select
                                        id="filtro-analysis-status"
                                        value={form.analysis_status}
                                        onChange={(valor) => definir('analysis_status', valor)}
                                        placeholder="Todas"
                                        options={analysisStatusOptions}
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="filtro-categoria">Categoria</Label>
                                    <Select
                                        id="filtro-categoria"
                                        value={form.categoria}
                                        onChange={(valor) => definir('categoria', valor)}
                                        placeholder="Todas"
                                        options={categoriaOptions}
                                    />
                                </div>
                                <CampoTexto
                                    id="filtro-protocolo"
                                    label="Nº do processo"
                                    value={form.protocolo}
                                    onChange={(valor) => definir('protocolo', valor)}
                                />
                                <CampoTexto
                                    id="filtro-bap"
                                    label="BAP"
                                    value={form.bap}
                                    onChange={(valor) => definir('bap', valor)}
                                />
                                <CampoTexto
                                    id="filtro-produto-tvl"
                                    label="Produto TVL"
                                    value={form.produto_tvl}
                                    onChange={(valor) => definir('produto_tvl', valor)}
                                />
                                <CampoTexto
                                    id="filtro-nome"
                                    label="Empresa / requerente"
                                    value={form.nome}
                                    onChange={(valor) => definir('nome', valor)}
                                />
                                <CampoTexto
                                    id="filtro-cnpj"
                                    label="CNPJ"
                                    value={form.cnpj}
                                    onChange={(valor) => definir('cnpj', valor)}
                                />
                                <CampoTexto
                                    id="filtro-inscricao"
                                    label="Inscrição imobiliária"
                                    value={form.inscricao}
                                    onChange={(valor) => definir('inscricao', valor)}
                                />
                                <CampoTexto
                                    id="filtro-logradouro"
                                    label="Logradouro"
                                    value={form.logradouro}
                                    onChange={(valor) => definir('logradouro', valor)}
                                />
                                <CampoTexto
                                    id="filtro-bairro"
                                    label="Bairro"
                                    value={form.bairro}
                                    onChange={(valor) => definir('bairro', valor)}
                                />
                                <CampoTexto
                                    id="filtro-cep"
                                    label="CEP"
                                    value={form.cep}
                                    onChange={(valor) => definir('cep', valor)}
                                />
                                <CampoTexto
                                    id="filtro-servico"
                                    label="Serviço (ID)"
                                    type="number"
                                    value={form.servico}
                                    onChange={(valor) => definir('servico', valor)}
                                />
                                <CampoTexto
                                    id="filtro-setor"
                                    label="Setor (ID)"
                                    type="number"
                                    value={form.setor}
                                    onChange={(valor) => definir('setor', valor)}
                                />
                                <CampoTexto
                                    id="filtro-analista"
                                    label="Analista (ID)"
                                    type="number"
                                    value={form.analista}
                                    onChange={(valor) => definir('analista', valor)}
                                />
                                <CampoTexto
                                    id="filtro-data-de"
                                    label="Protocolado de"
                                    type="date"
                                    value={form.data_de}
                                    onChange={(valor) => definir('data_de', valor)}
                                />
                                <CampoTexto
                                    id="filtro-data-ate"
                                    label="Protocolado até"
                                    type="date"
                                    value={form.data_ate}
                                    onChange={(valor) => definir('data_ate', valor)}
                                />
                            </div>

                            <div className="flex flex-wrap items-center gap-3">
                                <Button type="submit" size="sm">
                                    Filtrar
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
                        title="Processos"
                        description="Resultado da consulta — paginado no servidor."
                        actions={
                            <a
                                href={csvHref}
                                className="inline-flex items-center justify-center gap-2 rounded-lg bg-white px-4 py-3 text-sm text-gray-700 shadow-theme-xs ring-1 ring-gray-300 ring-inset transition hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-400 dark:ring-gray-700 dark:hover:bg-white/[0.03]"
                            >
                                <FileIcon className="size-5" />
                                Exportar CSV
                            </a>
                        }
                    />
                    <CardContent>
                        <div className="space-y-5">
                            {podeMalhaFina && selecionados.length > 0 && (
                                <div className="flex flex-col gap-3 rounded-xl border border-brand-200 bg-brand-50 p-4 dark:border-brand-500/30 dark:bg-brand-500/10 sm:flex-row sm:items-end">
                                    <div className="flex-1">
                                        <Label htmlFor="malha-fina-motivo">
                                            Encaminhar {selecionados.length}{' '}
                                            {selecionados.length === 1 ? 'processo' : 'processos'} à malha fina
                                        </Label>
                                        <Input
                                            id="malha-fina-motivo"
                                            value={motivo}
                                            placeholder="Motivo do encaminhamento (obrigatório)"
                                            error={erroMotivo !== null}
                                            onChange={(evento) => {
                                                setMotivo(evento.target.value);
                                                setErroMotivo(null);
                                            }}
                                        />
                                        {erroMotivo && <p className="mt-1.5 text-theme-xs text-error-500">{erroMotivo}</p>}
                                    </div>
                                    <Button
                                        type="button"
                                        size="sm"
                                        onClick={encaminharMalhaFina}
                                        loading={enviando}
                                    >
                                        Encaminhar à malha fina
                                    </Button>
                                </div>
                            )}

                            <div className="flex justify-end">
                                <PerPageSelect value={perPage} options={perPageOptions} onChange={trocarPerPage} />
                            </div>

                            <DataTable
                                columns={columns}
                                rows={processos.data}
                                rowKey={(item) => item.id}
                                density="compact"
                                emptyState={
                                    <EmptyState
                                        title={filtrando ? 'Nenhum processo para os filtros' : 'Nenhum processo cadastrado'}
                                        description={
                                            filtrando
                                                ? 'Ajuste ou limpe os filtros e tente novamente.'
                                                : 'Os processos protocolados aparecem aqui conforme avançam no fluxo.'
                                        }
                                    />
                                }
                            />

                            <Pagination
                                links={processos.links}
                                meta={{ from: processos.from, to: processos.to, total: processos.total }}
                            />
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

/** Campo de texto rotulado do formulário de filtros. */
function CampoTexto({
    id,
    label,
    value,
    onChange,
    type = 'text',
}: {
    id: string;
    label: string;
    value: string;
    onChange: (value: string) => void;
    type?: string;
}) {
    return (
        <div>
            <Label htmlFor={id}>{label}</Label>
            <Input id={id} type={type} value={value} onChange={(evento) => onChange(evento.target.value)} />
        </div>
    );
}

ConsultaProcessos.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
