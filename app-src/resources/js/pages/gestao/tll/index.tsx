import { Form, Head, router, useHttp, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useEffect, useRef, useState } from 'react';
import PageHeader from '@/components/app/page-header';
import Input from '@/components/form/input';
import Label from '@/components/form/label';
import Select from '@/components/form/select';
import { PencilIcon, PowerIcon } from '@/components/icons';
import Badge from '@/components/ui/badge';
import Button from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import ConfirmDialog from '@/components/ui/confirm-dialog';
import DataTable from '@/components/ui/data-table/data-table';
import PerPageSelect from '@/components/ui/data-table/per-page-select';
import TableToolbar from '@/components/ui/data-table/table-toolbar';
import type { ColumnDef } from '@/components/ui/data-table/types';
import { useServerTable } from '@/components/ui/data-table/use-server-table';
import EmptyState from '@/components/ui/empty-state';
import { Modal } from '@/components/ui/modal';
import Pagination, { type PaginationLink } from '@/components/ui/pagination';
import TableAction from '@/components/ui/table-action';
import GestaoLayout from '@/layouts/gestao-layout';
import type { SharedProps } from '@/types';

interface TllValorItem {
    id: number;
    codigo_tll: string;
    especificacao: string;
    exercicio: number;
    valor: string;
    taxa_servico: string;
    codigo_tll_sefaz: string | null;
    codigo_servico_sefaz: string | null;
    servico_sefaz: string | null;
    active: boolean;
    cnaes_count: number;
}

interface TllCnaeVinculado {
    cnae: string;
    denominacao: string | null;
}

interface TllCnaesResposta {
    data: TllCnaeVinculado[];
    meta: {
        total: number;
        current_page: number;
        last_page: number;
        per_page: number;
    };
}

interface TllExercicio {
    exercicio: number;
    status: 'rascunho' | 'vigente' | 'substituida';
    autor_id: number | null;
    source: string | null;
    pode_publicar: boolean;
}

interface TllIndexProps {
    valores: {
        data: TllValorItem[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
    exercicios: TllExercicio[];
    filters: {
        search: string;
        per_page: number;
        active: string;
        exercicio: string;
    };
    perPageOptions: number[];
}

const URL_TLL = '/gestao/tll';

const SITUACAO_OPTIONS = [
    { value: '1', label: 'Ativo' },
    { value: '0', label: 'Inativo' },
];

const moedaFormat = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });

/** Formata o valor (string) como moeda brasileira (R$). */
function formatarMoeda(valor: string): string {
    const numero = Number.parseFloat(valor);

    return Number.isNaN(numero) ? valor : moedaFormat.format(numero);
}

function SituationBadge({ active }: { active: boolean }) {
    return (
        <Badge size="sm" color={active ? 'success' : 'warning'}>
            {active ? 'Ativo' : 'Inativo'}
        </Badge>
    );
}

/** Campos do formulário de valor TLL (criar e editar). */
function CamposValor({ valor, errors }: { valor?: TllValorItem; errors: Record<string, string> }) {
    const [active, setActive] = useState(valor ? (valor.active ? '1' : '0') : '1');

    return (
        <div className="flex flex-col gap-5">
            <div className="grid gap-5 sm:grid-cols-2">
                <div>
                    <Label htmlFor={valor ? `edit-codigo-${valor.id}` : 'create-codigo'}>Código TLL</Label>
                    <Input
                        id={valor ? `edit-codigo-${valor.id}` : 'create-codigo'}
                        type="text"
                        name="codigo_tll"
                        defaultValue={valor?.codigo_tll ?? ''}
                        required
                        placeholder="Ex.: 1.01"
                        error={!!errors.codigo_tll}
                        hint={errors.codigo_tll}
                    />
                </div>
                <div>
                    <Label htmlFor={valor ? `edit-espec-${valor.id}` : 'create-espec'}>Especificação</Label>
                    <Input
                        id={valor ? `edit-espec-${valor.id}` : 'create-espec'}
                        type="text"
                        name="especificacao"
                        defaultValue={valor?.especificacao ?? ''}
                        placeholder="Ex.: Comércio Varejista"
                        error={!!errors.especificacao}
                        hint={errors.especificacao}
                    />
                </div>
                <div>
                    <Label htmlFor={valor ? `edit-exercicio-${valor.id}` : 'create-exercicio'}>Exercício</Label>
                    <Input
                        id={valor ? `edit-exercicio-${valor.id}` : 'create-exercicio'}
                        type="number"
                        name="exercicio"
                        defaultValue={valor?.exercicio ?? new Date().getFullYear()}
                        required
                        error={!!errors.exercicio}
                        hint={errors.exercicio}
                    />
                </div>
            </div>
            <div className="grid gap-5 sm:grid-cols-2">
                <div>
                    <Label htmlFor={valor ? `edit-valor-${valor.id}` : 'create-valor'}>Valor (R$)</Label>
                    <Input
                        id={valor ? `edit-valor-${valor.id}` : 'create-valor'}
                        type="number"
                        step="0.01"
                        name="valor"
                        defaultValue={valor?.valor ?? ''}
                        required
                        placeholder="Ex.: 1111.78"
                        error={!!errors.valor}
                        hint={errors.valor}
                    />
                </div>
                <div>
                    <Label htmlFor={valor ? `edit-taxa-${valor.id}` : 'create-taxa'}>Taxa de serviço (R$)</Label>
                    <Input
                        id={valor ? `edit-taxa-${valor.id}` : 'create-taxa'}
                        type="number"
                        step="0.01"
                        name="taxa_servico"
                        defaultValue={valor?.taxa_servico ?? '0'}
                        error={!!errors.taxa_servico}
                        hint={errors.taxa_servico}
                    />
                </div>
            </div>
            <div className="grid gap-5 sm:grid-cols-2">
                <div>
                    <Label htmlFor={valor ? `edit-codtll-sefaz-${valor.id}` : 'create-codtll-sefaz'}>Código SEDUR</Label>
                    <Input
                        id={valor ? `edit-codtll-sefaz-${valor.id}` : 'create-codtll-sefaz'}
                        type="text"
                        name="codigo_tll_sefaz"
                        defaultValue={valor?.codigo_tll_sefaz ?? ''}
                        placeholder="Ex.: T45020425"
                        error={!!errors.codigo_tll_sefaz}
                        hint={errors.codigo_tll_sefaz}
                    />
                </div>
                <div>
                    <Label htmlFor={valor ? `edit-codserv-sefaz-${valor.id}` : 'create-codserv-sefaz'}>Código de serviço SEFAZ</Label>
                    <Input
                        id={valor ? `edit-codserv-sefaz-${valor.id}` : 'create-codserv-sefaz'}
                        type="text"
                        name="codigo_servico_sefaz"
                        defaultValue={valor?.codigo_servico_sefaz ?? ''}
                        placeholder="Ex.: S2253362"
                        error={!!errors.codigo_servico_sefaz}
                        hint={errors.codigo_servico_sefaz}
                    />
                </div>
            </div>
            <div>
                <Label htmlFor={valor ? `edit-servico-sefaz-${valor.id}` : 'create-servico-sefaz'}>Serviço SEFAZ</Label>
                <Input
                    id={valor ? `edit-servico-sefaz-${valor.id}` : 'create-servico-sefaz'}
                    type="text"
                    name="servico_sefaz"
                    defaultValue={valor?.servico_sefaz ?? ''}
                    placeholder="Ex.: Inclusão de Atividade em Viabilidade MEI"
                    error={!!errors.servico_sefaz}
                    hint={errors.servico_sefaz}
                />
            </div>
            <div>
                <Label htmlFor={valor ? `edit-active-${valor.id}` : 'create-active'}>Situação</Label>
                <Select
                    id={valor ? `edit-active-${valor.id}` : 'create-active'}
                    name="active"
                    value={active}
                    onChange={setActive}
                    options={SITUACAO_OPTIONS}
                />
                {errors.active && <p className="mt-1.5 text-theme-xs text-error-500">{errors.active}</p>}
            </div>
        </div>
    );
}

function GerarExercicioModal({ isOpen, onClose }: { isOpen: boolean; onClose: () => void }) {
    const ano = new Date().getFullYear();

    return (
        <Modal isOpen={isOpen} onClose={onClose} className="m-4 max-w-[520px] overflow-y-auto p-6 lg:p-8">
            <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90">Gerar exercício</h4>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Clona os valores ativos da origem, aplica o fator do decreto e grava um rascunho. A publicação exige um
                segundo usuário.
            </p>

            <Form action={`${URL_TLL}/exercicios`} method="post" resetOnSuccess onSuccess={onClose} className="mt-6">
                {({ errors, processing }) => (
                    <>
                        <div className="grid gap-5 sm:grid-cols-2">
                            <div>
                                <Label htmlFor="prop-origem">Exercício de origem</Label>
                                <Input
                                    id="prop-origem"
                                    type="number"
                                    name="exercicio_origem"
                                    defaultValue={ano}
                                    required
                                    error={!!errors.exercicio_origem}
                                    hint={errors.exercicio_origem}
                                />
                            </div>
                            <div>
                                <Label htmlFor="prop-destino">Exercício de destino</Label>
                                <Input
                                    id="prop-destino"
                                    type="number"
                                    name="exercicio_destino"
                                    defaultValue={ano + 1}
                                    required
                                    error={!!errors.exercicio_destino}
                                    hint={errors.exercicio_destino}
                                />
                            </div>
                        </div>
                        <div className="mt-5 grid gap-5 sm:grid-cols-2">
                            <div>
                                <Label htmlFor="prop-fator">Fator do decreto</Label>
                                <Input
                                    id="prop-fator"
                                    type="number"
                                    step="0.0001"
                                    name="fator"
                                    placeholder="Ex.: 1.0446"
                                    required
                                    error={!!errors.fator}
                                    hint={errors.fator}
                                />
                            </div>
                            <div>
                                <Label htmlFor="prop-decreto">Decreto</Label>
                                <Input
                                    id="prop-decreto"
                                    type="text"
                                    name="decreto"
                                    placeholder="Decreto nº 41.304/2025"
                                    required
                                    error={!!errors.decreto}
                                    hint={errors.decreto}
                                />
                            </div>
                        </div>
                        <div className="mt-6 flex items-center justify-end gap-3">
                            <Button size="sm" variant="outline" onClick={onClose} disabled={processing}>
                                Cancelar
                            </Button>
                            <Button size="sm" type="submit" disabled={processing}>
                                {processing ? 'Gerando...' : 'Gerar rascunho'}
                            </Button>
                        </div>
                    </>
                )}
            </Form>
        </Modal>
    );
}

function CnaesVinculadosModal({ valor, onClose }: { valor: TllValorItem; onClose: () => void }) {
    const [pagina, setPagina] = useState(1);
    const [resposta, setResposta] = useState<TllCnaesResposta | null>(null);
    const [failed, setFailed] = useState(false);
    const http = useHttp<Record<string, never>, TllCnaesResposta>({});
    const httpRef = useRef(http);
    httpRef.current = http;

    useEffect(() => {
        setFailed(false);
        setResposta(null);

        void httpRef.current.get(`${URL_TLL}/${valor.id}/cnaes?page=${pagina}`, {
            onSuccess: (payload) => {
                setResposta(payload);
                setFailed(false);
            },
            onError: () => {
                setResposta(null);
                setFailed(true);
            },
        });
    }, [valor.id, pagina]);

    const total = resposta?.meta.total ?? valor.cnaes_count;
    const ultima = resposta?.meta.last_page ?? 1;

    return (
        <Modal isOpen onClose={onClose} className="m-4 max-h-[90vh] max-w-[640px] overflow-y-auto p-6 lg:p-8">
            <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90">
                CNAEs do código {valor.codigo_tll}
            </h4>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Vinculados na versão vigente da planilha de regras. Somente leitura — o mapeamento se altera
                versionando a planilha, não nesta tarifa.
            </p>

            {failed && (
                <p className="mt-4 text-sm text-error-500">Não foi possível carregar os CNAEs vinculados.</p>
            )}

            {!failed && resposta === null && (
                <p className="mt-4 text-sm text-gray-500 dark:text-gray-400">Carregando CNAEs…</p>
            )}

            {resposta && resposta.data.length === 0 && (
                <EmptyState
                    title="Nenhum CNAE vinculado"
                    description="A planilha vigente não referencia este código TLL."
                />
            )}

            {resposta && resposta.data.length > 0 && (
                <ul className="mt-5 divide-y divide-gray-100 dark:divide-gray-800">
                    {resposta.data.map((item) => (
                        <li key={item.cnae} className="py-2.5">
                            <p className="font-medium text-gray-800 dark:text-white/90">{item.cnae}</p>
                            <p className="text-sm text-gray-500 dark:text-gray-400">{item.denominacao || '—'}</p>
                        </li>
                    ))}
                </ul>
            )}

            <div className="mt-5 flex items-center justify-between gap-3">
                <p className="text-sm text-gray-500 dark:text-gray-400">
                    {total} {total === 1 ? 'CNAE' : 'CNAEs'}
                </p>
                {ultima > 1 && (
                    <div className="flex gap-2">
                        <Button size="sm" variant="outline" disabled={pagina <= 1} onClick={() => setPagina((atual) => atual - 1)}>
                            Anterior
                        </Button>
                        <Button
                            size="sm"
                            variant="outline"
                            disabled={pagina >= ultima}
                            onClick={() => setPagina((atual) => atual + 1)}
                        >
                            Próxima
                        </Button>
                    </div>
                )}
            </div>
        </Modal>
    );
}

function CreateValorModal({ isOpen, onClose }: { isOpen: boolean; onClose: () => void }) {
    return (
        <Modal isOpen={isOpen} onClose={onClose} className="m-4 max-h-[90vh] max-w-[640px] overflow-y-auto p-6 lg:p-8">
            <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90">Novo valor de TLL</h4>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                O valor entra no cálculo do DAM e no bloco de taxas enviado à SEFAZ. A chave (código TLL, exercício) é única.
            </p>

            <Form action={URL_TLL} method="post" resetOnSuccess onSuccess={onClose} className="mt-6">
                {({ errors, processing }) => (
                    <>
                        <CamposValor errors={errors} />
                        <div className="mt-6 flex items-center justify-end gap-3">
                            <Button size="sm" variant="outline" onClick={onClose} disabled={processing}>
                                Cancelar
                            </Button>
                            <Button size="sm" type="submit" disabled={processing}>
                                {processing ? 'Salvando...' : 'Salvar'}
                            </Button>
                        </div>
                    </>
                )}
            </Form>
        </Modal>
    );
}

function EditValorModal({ valor, onClose }: { valor: TllValorItem; onClose: () => void }) {
    return (
        <Modal isOpen onClose={onClose} className="m-4 max-h-[90vh] max-w-[640px] overflow-y-auto p-6 lg:p-8">
            <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90">Editar valor de TLL</h4>

            <Form action={`${URL_TLL}/${valor.id}`} method="put" onSuccess={onClose} className="mt-6">
                {({ errors, processing }) => (
                    <>
                        <CamposValor valor={valor} errors={errors} />
                        <div className="mt-6 flex items-center justify-end gap-3">
                            <Button size="sm" variant="outline" onClick={onClose} disabled={processing}>
                                Cancelar
                            </Button>
                            <Button size="sm" type="submit" disabled={processing}>
                                {processing ? 'Salvando...' : 'Salvar alterações'}
                            </Button>
                        </div>
                    </>
                )}
            </Form>
        </Modal>
    );
}

export default function TllIndex({ valores, exercicios, filters, perPageOptions }: TllIndexProps) {
    const { auth } = usePage<SharedProps>().props;
    const canMaintain = auth.permissions.includes('manter-parametros');
    const rascunhos = exercicios.filter((item) => item.status === 'rascunho');

    const table = useServerTable({
        url: URL_TLL,
        initialSearch: filters.search,
        initialSort: { column: 'exercicio', direction: 'desc' },
        initialPerPage: filters.per_page,
        initialFilters: { active: filters.active, exercicio: filters.exercicio },
    });

    const [showCreate, setShowCreate] = useState(false);
    const [showGerar, setShowGerar] = useState(false);
    const [editing, setEditing] = useState<TllValorItem | null>(null);
    const [pendingToggle, setPendingToggle] = useState<TllValorItem | null>(null);
    const [pendingPublish, setPendingPublish] = useState<TllExercicio | null>(null);
    const [viewingCnaes, setViewingCnaes] = useState<TllValorItem | null>(null);
    const [actionProcessing, setActionProcessing] = useState(false);

    const filtering = table.search.trim() !== '' || table.filters.active !== '' || table.filters.exercicio !== '';

    const columns: ColumnDef<TllValorItem>[] = [
        {
            id: 'codigo_tll',
            header: 'Código TLL',
            cellClassName: 'font-medium whitespace-nowrap text-gray-800 dark:text-white/90',
            cell: (valor) => valor.codigo_tll,
        },
        {
            id: 'especificacao',
            header: 'Especificação',
            cellClassName: 'text-gray-500 dark:text-gray-400',
            cell: (valor) => valor.especificacao || '—',
        },
        {
            id: 'cnaes',
            header: 'CNAEs',
            cellClassName: 'whitespace-nowrap',
            cell: (valor) => (
                <button
                    type="button"
                    className="text-sm font-medium text-brand-500 transition hover:text-brand-600 hover:underline dark:text-brand-400"
                    onClick={() => setViewingCnaes(valor)}
                >
                    {valor.cnaes_count.toLocaleString('pt-BR')} {valor.cnaes_count === 1 ? 'CNAE' : 'CNAEs'}
                </button>
            ),
        },
        {
            id: 'exercicio',
            header: 'Exercício',
            cellClassName: 'whitespace-nowrap text-gray-800 dark:text-white/90',
            cell: (valor) => valor.exercicio,
        },
        {
            id: 'valor',
            header: 'Valor',
            cellClassName: 'whitespace-nowrap text-gray-800 dark:text-white/90',
            cell: (valor) => formatarMoeda(valor.valor),
        },
        {
            id: 'taxa_servico',
            header: 'Taxa de serviço',
            cellClassName: 'whitespace-nowrap text-gray-800 dark:text-white/90',
            cell: (valor) => formatarMoeda(valor.taxa_servico),
        },
        {
            id: 'codigo_tll_sefaz',
            header: 'Código SEDUR',
            cellClassName: 'text-gray-500 dark:text-gray-400',
            cell: (valor) => valor.codigo_tll_sefaz ?? '—',
        },
        {
            id: 'active',
            header: 'Situação',
            cell: (valor) => <SituationBadge active={valor.active} />,
        },
        ...(canMaintain
            ? [
                  {
                      id: 'actions',
                      header: 'Ações',
                      align: 'end',
                      cellClassName: 'whitespace-nowrap',
                      cell: (valor: TllValorItem) => (
                          <div className="flex justify-end gap-2">
                              <TableAction
                                  tone="brand"
                                  icon={<PencilIcon className="size-4.5" />}
                                  label="Editar"
                                  onClick={() => setEditing(valor)}
                              />
                              <TableAction
                                  tone={valor.active ? 'warning' : 'success'}
                                  icon={<PowerIcon className="size-4.5" />}
                                  label={valor.active ? 'Inativar' : 'Reativar'}
                                  onClick={() => setPendingToggle(valor)}
                              />
                          </div>
                      ),
                  } satisfies ColumnDef<TllValorItem>,
              ]
            : []),
    ];

    function executeToggle() {
        if (!pendingToggle) {
            return;
        }

        router.put(
            `${URL_TLL}/${pendingToggle.id}/ativacao`,
            {},
            {
                preserveScroll: true,
                onStart: () => setActionProcessing(true),
                onFinish: () => setActionProcessing(false),
                onSuccess: () => setPendingToggle(null),
            },
        );
    }

    const confirmContent = pendingToggle
        ? pendingToggle.active
            ? {
                  variant: 'warning' as const,
                  title: 'Inativar valor de TLL',
                  description: `Confirma a inativação do valor ${pendingToggle.codigo_tll} (${pendingToggle.exercicio})? Ele deixa de entrar no cálculo do DAM, mas o histórico é preservado.`,
                  confirmLabel: 'Inativar',
              }
            : {
                  variant: 'info' as const,
                  title: 'Reativar valor de TLL',
                  description: `Confirma a reativação do valor ${pendingToggle.codigo_tll} (${pendingToggle.exercicio})? Ele volta a entrar no cálculo do DAM.`,
                  confirmLabel: 'Reativar',
              }
        : null;

    return (
        <>
            <Head title="Valores TLL" />
            <PageHeader title="Valores TLL" breadcrumbs={[{ label: 'Painel', href: '/gestao' }]} />

            <div className="space-y-4 md:space-y-6">
                <Card>
                    <CardHeader
                        title="Tabela de valores TLL por exercício"
                        description="Valores da Taxa de Licença de Localização por código e exercício. Os CNAEs vêm da planilha vigente (somente leitura). Alimentam o cálculo do DAM e o bloco de taxas enviado à SEFAZ."
                        actions={
                            canMaintain ? (
                                <div className="flex flex-wrap gap-2">
                                    <Button size="sm" variant="outline" onClick={() => setShowGerar(true)}>
                                        Gerar exercício
                                    </Button>
                                    <Button size="sm" onClick={() => setShowCreate(true)}>
                                        Novo valor
                                    </Button>
                                </div>
                            ) : undefined
                        }
                    />
                    <CardContent>
                        {rascunhos.length > 0 && (
                            <div className="mb-5 rounded-lg border border-warning-200 bg-warning-50 px-4 py-3 text-sm text-gray-700 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-gray-200">
                                <p className="font-medium">Rascunho aguardando publicação</p>
                                <ul className="mt-2 space-y-2">
                                    {rascunhos.map((item) => (
                                        <li key={item.exercicio} className="flex flex-wrap items-center justify-between gap-3">
                                            <span>
                                                Exercício {item.exercicio}
                                                {item.source ? ` — ${item.source}` : ''}. A publicação exige um segundo
                                                usuário.
                                            </span>
                                            {canMaintain && (
                                                <Button
                                                    size="sm"
                                                    disabled={!item.pode_publicar}
                                                    onClick={() => setPendingPublish(item)}
                                                >
                                                    Publicar
                                                </Button>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}
                        <div className="space-y-5">
                            <TableToolbar
                                search={{
                                    value: table.search,
                                    onChange: table.setSearch,
                                    placeholder: 'Buscar por código TLL, CNAE ou especificação...',
                                    label: 'Buscar valores TLL',
                                }}
                                filters={
                                    <div className="flex flex-wrap gap-3">
                                        <div className="w-36">
                                            <label htmlFor="filter-exercicio" className="sr-only">
                                                Filtrar por exercício
                                            </label>
                                            <Input
                                                id="filter-exercicio"
                                                type="number"
                                                value={table.filters.exercicio}
                                                onChange={(event) => table.setFilter('exercicio', event.target.value)}
                                                placeholder="Exercício"
                                            />
                                        </div>
                                        <div className="w-40">
                                            <label htmlFor="filter-active" className="sr-only">
                                                Filtrar por situação
                                            </label>
                                            <Select
                                                id="filter-active"
                                                value={table.filters.active}
                                                onChange={(value) => table.setFilter('active', value)}
                                                placeholder="Situação"
                                                options={SITUACAO_OPTIONS.map((opt) => ({
                                                    value: opt.value,
                                                    label: opt.value === '1' ? 'Ativos' : 'Inativos',
                                                }))}
                                            />
                                        </div>
                                    </div>
                                }
                                actions={<PerPageSelect value={table.perPage} options={perPageOptions} onChange={table.setPerPage} />}
                            />

                            <DataTable
                                columns={columns}
                                rows={valores.data}
                                rowKey={(valor) => valor.id}
                                loading={table.processing}
                                skeletonRows={8}
                                density="compact"
                                emptyState={
                                    <EmptyState
                                        title={filtering ? 'Nenhum resultado para a busca' : 'Nenhum valor de TLL cadastrado'}
                                        description={
                                            filtering
                                                ? 'Ajuste o termo de busca ou os filtros e tente novamente.'
                                                : 'Cadastre o primeiro valor de TLL para o exercício corrente.'
                                        }
                                        action={
                                            !filtering && canMaintain ? (
                                                <Button size="sm" onClick={() => setShowCreate(true)}>
                                                    Novo valor
                                                </Button>
                                            ) : undefined
                                        }
                                    />
                                }
                            />

                            <Pagination links={valores.links} meta={{ from: valores.from, to: valores.to, total: valores.total }} />
                        </div>
                    </CardContent>
                </Card>
            </div>

            {canMaintain && <GerarExercicioModal isOpen={showGerar} onClose={() => setShowGerar(false)} />}

            {canMaintain && <CreateValorModal isOpen={showCreate} onClose={() => setShowCreate(false)} />}

            {editing && <EditValorModal valor={editing} onClose={() => setEditing(null)} />}

            {viewingCnaes && <CnaesVinculadosModal valor={viewingCnaes} onClose={() => setViewingCnaes(null)} />}

            {confirmContent && (
                <ConfirmDialog
                    isOpen
                    onClose={() => setPendingToggle(null)}
                    onConfirm={executeToggle}
                    title={confirmContent.title}
                    description={confirmContent.description}
                    confirmLabel={confirmContent.confirmLabel}
                    variant={confirmContent.variant}
                    processing={actionProcessing}
                />
            )}

            {pendingPublish && (
                <ConfirmDialog
                    isOpen
                    onClose={() => setPendingPublish(null)}
                    onConfirm={() => {
                        router.post(
                            `${URL_TLL}/exercicios/${pendingPublish.exercicio}/publicar`,
                            {},
                            {
                                preserveScroll: true,
                                onStart: () => setActionProcessing(true),
                                onFinish: () => setActionProcessing(false),
                                onSuccess: () => setPendingPublish(null),
                            },
                        );
                    }}
                    title={`Publicar exercício ${pendingPublish.exercicio}`}
                    description="A tabela deste exercício passa a valer no cálculo do DAM. Só um usuário diferente de quem gerou o rascunho pode publicar."
                    confirmLabel="Publicar"
                    variant="info"
                    processing={actionProcessing}
                />
            )}
        </>
    );
}

TllIndex.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
