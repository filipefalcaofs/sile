import { Head, router, useForm, usePage } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import { useState } from 'react';
import PageHeader from '@/components/app/page-header';
import Input from '@/components/form/input';
import Label from '@/components/form/label';
import Select from '@/components/form/select';
import Switch from '@/components/form/switch';
import { PencilIcon, TrashIcon } from '@/components/icons';
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

interface RegraReclassificacao {
    resposta_gatilho: boolean;
    reclassifica_para: string | null;
    fundamento?: string | null;
}

interface CondicionanteItem {
    id: number;
    cnae_code: string | null;
    formatted_code: string | null;
    pergunta: string;
    tipo_resposta: string;
    regra_reclassificacao: RegraReclassificacao | null;
    texto_parecer: string | null;
}

interface NivelOption {
    value: string;
    label: string;
}

interface VersaoInfo {
    version: string;
    valid_from: string | null;
}

interface CondicionantesIndexProps {
    condicionantes: {
        data: CondicionanteItem[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
    versaoSanitaria: VersaoInfo | null;
    filtros: {
        search: string;
        per_page: number;
    };
    perPageOptions: number[];
    niveisReclassificacao: NivelOption[];
}

// Sentinela do select de reclassificação: "sem nível" = encaminha à análise.
const INDETERMINADO = 'indeterminado';

const textareaStyles =
    'w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800';

function tipoRespostaLabel(tipo: string): string {
    return tipo === 'booleano_sim_nao' ? 'Sim ou Não' : 'Seleção';
}

function sanitarioColor(nivel: string | null): 'success' | 'warning' | 'error' | 'light' {
    if (nivel === 'alto') {
        return 'error';
    }
    if (nivel === 'medio') {
        return 'warning';
    }
    if (nivel === 'baixo') {
        return 'success';
    }

    return 'light';
}

function formatarData(iso: string | null): string | null {
    if (!iso) {
        return null;
    }

    const [ano, mes, dia] = iso.split('-');

    return dia && mes && ano ? `${dia}/${mes}/${ano}` : iso;
}

/**
 * Modal de criar/editar condicionante-pergunta (HU-019). A condicionante opera
 * como pergunta booleana: quando a resposta coincide com a resposta-gatilho, o
 * risco é reclassificado para o nível escolhido (ou encaminhado à análise se
 * indeterminado). O preview torna o mecanismo tangível para o admin.
 */
function CondicionanteFormModal({
    condicionante,
    niveis,
    onClose,
}: {
    condicionante: CondicionanteItem | null;
    niveis: NivelOption[];
    onClose: () => void;
}) {
    const isEdit = condicionante !== null;
    const regra = condicionante?.regra_reclassificacao ?? null;

    const { data, setData, post, put, processing, errors, transform } = useForm<{
        pergunta: string;
        cnae_code: string;
        tipo_resposta: string;
        hasRule: boolean;
        resposta_gatilho: string;
        reclassifica_para: string;
        fundamento: string;
        texto_parecer: string;
    }>({
        pergunta: condicionante?.pergunta ?? '',
        cnae_code: condicionante?.formatted_code ?? '',
        tipo_resposta: 'booleano_sim_nao',
        hasRule: regra !== null,
        resposta_gatilho: regra && regra.resposta_gatilho === false ? '0' : '1',
        reclassifica_para: regra?.reclassifica_para ?? INDETERMINADO,
        fundamento: regra?.fundamento ?? '',
        texto_parecer: condicionante?.texto_parecer ?? '',
    });

    const fieldErrors = errors as Record<string, string | undefined>;

    function submit(event: FormEvent) {
        event.preventDefault();

        transform((current) => ({
            cnae_code: current.cnae_code.trim() === '' ? null : current.cnae_code.trim(),
            pergunta: current.pergunta,
            tipo_resposta: current.tipo_resposta,
            texto_parecer: current.texto_parecer.trim() === '' ? null : current.texto_parecer,
            regra_reclassificacao: current.hasRule
                ? {
                      resposta_gatilho: current.resposta_gatilho === '1',
                      reclassifica_para:
                          current.reclassifica_para === INDETERMINADO ? null : current.reclassifica_para,
                      fundamento: current.fundamento.trim() === '' ? null : current.fundamento,
                  }
                : null,
        }));

        const options = { preserveScroll: true, onSuccess: onClose };

        if (isEdit && condicionante) {
            put(`/gestao/risco/condicionantes/${condicionante.id}`, options);
        } else {
            post('/gestao/risco/condicionantes', options);
        }
    }

    const respostaLabel = data.resposta_gatilho === '1' ? 'Sim' : 'Não';
    const alvoLabel =
        data.reclassifica_para === INDETERMINADO
            ? 'análise técnica'
            : (niveis.find((n) => n.value === data.reclassifica_para)?.label ?? data.reclassifica_para);

    return (
        <Modal isOpen onClose={onClose} className="m-4 max-h-[90vh] max-w-[700px] overflow-y-auto p-6 lg:p-8">
            <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90">
                {isEdit ? 'Editar condicionante' : 'Nova condicionante'}
            </h4>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                A pergunta é feita ao requerente; a resposta pode reclassificar o risco sanitário (mecanismo DI).
            </p>

            <form onSubmit={submit} className="mt-6 flex flex-col gap-5">
                <div>
                    <Label htmlFor="cond-pergunta" required>
                        Pergunta
                    </Label>
                    <textarea
                        id="cond-pergunta"
                        value={data.pergunta}
                        onChange={(event) => setData('pergunta', event.target.value)}
                        rows={2}
                        required
                        className={textareaStyles}
                        placeholder="ex.: O resultado da atividade será diferente de produto artesanal?"
                    />
                    {fieldErrors.pergunta && (
                        <p className="mt-1.5 text-theme-xs text-error-500">{fieldErrors.pergunta}</p>
                    )}
                </div>

                <div className="grid gap-5 sm:grid-cols-2">
                    <div>
                        <Label htmlFor="cond-cnae">CNAE (opcional)</Label>
                        <Input
                            id="cond-cnae"
                            type="text"
                            value={data.cnae_code}
                            onChange={(event) => setData('cnae_code', event.target.value)}
                            placeholder="0000-0/00"
                            error={!!fieldErrors.cnae_code}
                            hint={fieldErrors.cnae_code ?? 'Vazio = condicionante geral (todas as atividades).'}
                        />
                    </div>
                    <div>
                        <Label htmlFor="cond-tipo">Tipo de resposta</Label>
                        <Select
                            id="cond-tipo"
                            value={data.tipo_resposta}
                            onChange={() => undefined}
                            disabled
                            options={[{ value: 'booleano_sim_nao', label: 'Sim ou Não' }]}
                        />
                        <p className="mt-1.5 text-theme-xs text-gray-400 dark:text-gray-500">
                            Seleção (múltipla escolha) reservada para versão futura.
                        </p>
                    </div>
                </div>

                <div className="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                    <Switch
                        label="Reclassifica o risco automaticamente"
                        defaultChecked={data.hasRule}
                        onChange={(checked) => setData('hasRule', checked)}
                    />

                    {data.hasRule && (
                        <div className="mt-4 flex flex-col gap-4">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <Label htmlFor="cond-gatilho">Resposta que reclassifica</Label>
                                    <Select
                                        id="cond-gatilho"
                                        value={data.resposta_gatilho}
                                        onChange={(value) => setData('resposta_gatilho', value)}
                                        options={[
                                            { value: '1', label: 'Sim' },
                                            { value: '0', label: 'Não' },
                                        ]}
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="cond-nivel">Reclassifica para</Label>
                                    <Select
                                        id="cond-nivel"
                                        value={data.reclassifica_para}
                                        onChange={(value) => setData('reclassifica_para', value)}
                                        options={[
                                            { value: INDETERMINADO, label: 'Indeterminado — encaminha à análise' },
                                            ...niveis,
                                        ]}
                                    />
                                    {fieldErrors['regra_reclassificacao.reclassifica_para'] && (
                                        <p className="mt-1.5 text-theme-xs text-error-500">
                                            {fieldErrors['regra_reclassificacao.reclassifica_para']}
                                        </p>
                                    )}
                                </div>
                            </div>
                            <div>
                                <Label htmlFor="cond-fundamento">Fundamento</Label>
                                <textarea
                                    id="cond-fundamento"
                                    value={data.fundamento}
                                    onChange={(event) => setData('fundamento', event.target.value)}
                                    rows={2}
                                    className={textareaStyles}
                                    placeholder="Base legal / motivo da reclassificação."
                                />
                            </div>
                            <p className="rounded-lg bg-gray-50 px-3 py-2 text-theme-xs text-gray-500 dark:bg-white/[0.03] dark:text-gray-400">
                                Quando a resposta for <strong>{respostaLabel}</strong>, o risco é reclassificado para{' '}
                                <strong>{alvoLabel}</strong>.
                            </p>
                        </div>
                    )}
                </div>

                <div>
                    <Label htmlFor="cond-parecer">Texto do parecer (opcional)</Label>
                    <textarea
                        id="cond-parecer"
                        value={data.texto_parecer}
                        onChange={(event) => setData('texto_parecer', event.target.value)}
                        rows={2}
                        className={textareaStyles}
                        placeholder="Orientação registrada no parecer técnico."
                    />
                </div>

                <div className="flex items-center justify-end gap-3">
                    <Button size="sm" variant="outline" onClick={onClose} disabled={processing}>
                        Cancelar
                    </Button>
                    <Button size="sm" type="submit" disabled={processing || data.pergunta.trim() === ''}>
                        {processing ? 'Salvando...' : isEdit ? 'Salvar alterações' : 'Salvar'}
                    </Button>
                </div>
            </form>
        </Modal>
    );
}

function EfeitoReclassificacao({ item, niveis }: { item: CondicionanteItem; niveis: NivelOption[] }) {
    const regra = item.regra_reclassificacao;

    if (!regra) {
        return <span className="text-gray-400 dark:text-gray-500">—</span>;
    }

    const gatilho = regra.resposta_gatilho ? 'Sim' : 'Não';
    const alvoLabel =
        regra.reclassifica_para === null
            ? 'Análise técnica'
            : (niveis.find((n) => n.value === regra.reclassifica_para)?.label ?? regra.reclassifica_para);

    return (
        <span className="inline-flex items-center gap-1.5 whitespace-nowrap">
            <span className="text-gray-600 dark:text-gray-300">{gatilho}</span>
            <span className="text-gray-400">→</span>
            <Badge size="sm" color={sanitarioColor(regra.reclassifica_para)}>
                {alvoLabel}
            </Badge>
        </span>
    );
}

export default function CondicionantesIndex({
    condicionantes,
    versaoSanitaria,
    filtros,
    perPageOptions,
    niveisReclassificacao,
}: CondicionantesIndexProps) {
    const { auth } = usePage<SharedProps>().props;
    const canMaintain = auth.permissions.includes('manter-risco');
    const hasVigente = versaoSanitaria !== null;

    const table = useServerTable({
        url: '/gestao/risco/condicionantes',
        initialSearch: filtros.search,
        initialSort: { column: 'id', direction: 'asc' },
        initialPerPage: filtros.per_page,
    });

    const [showCreate, setShowCreate] = useState(false);
    const [editing, setEditing] = useState<CondicionanteItem | null>(null);
    const [removing, setRemoving] = useState<CondicionanteItem | null>(null);
    const [removeProcessing, setRemoveProcessing] = useState(false);

    const filtering = table.search.trim() !== '';
    const vigenciaDesde = formatarData(versaoSanitaria?.valid_from ?? null);

    const columns: ColumnDef<CondicionanteItem>[] = [
        {
            id: 'pergunta',
            header: 'Pergunta',
            cell: (item) => (
                <span className="line-clamp-2 max-w-md text-gray-700 dark:text-gray-300">{item.pergunta}</span>
            ),
        },
        {
            id: 'cnae',
            header: 'CNAE',
            cellClassName: 'whitespace-nowrap',
            cell: (item) =>
                item.formatted_code ? (
                    <span className="font-medium text-gray-800 dark:text-white/90">{item.formatted_code}</span>
                ) : (
                    <Badge size="sm" color="light">
                        Geral
                    </Badge>
                ),
        },
        {
            id: 'efeito',
            header: 'Efeito de reclassificação',
            cell: (item) => <EfeitoReclassificacao item={item} niveis={niveisReclassificacao} />,
        },
        {
            id: 'tipo',
            header: 'Resposta',
            cellClassName: 'whitespace-nowrap',
            cell: (item) => tipoRespostaLabel(item.tipo_resposta),
        },
        ...(canMaintain
            ? [
                  {
                      id: 'actions',
                      header: 'Ações',
                      align: 'end',
                      cellClassName: 'whitespace-nowrap',
                      cell: (item) => (
                          <div className="flex justify-end gap-2">
                              <TableAction
                                  tone="brand"
                                  icon={<PencilIcon className="size-4.5" />}
                                  label="Editar"
                                  onClick={() => setEditing(item)}
                              />
                              <TableAction
                                  tone="error"
                                  icon={<TrashIcon className="size-4.5" />}
                                  label="Remover"
                                  onClick={() => setRemoving(item)}
                              />
                          </div>
                      ),
                  } satisfies ColumnDef<CondicionanteItem>,
              ]
            : []),
    ];

    function executeRemoval() {
        if (!removing) {
            return;
        }

        router.delete(`/gestao/risco/condicionantes/${removing.id}`, {
            preserveScroll: true,
            onStart: () => setRemoveProcessing(true),
            onFinish: () => setRemoveProcessing(false),
            onSuccess: () => setRemoving(null),
        });
    }

    return (
        <>
            <Head title="Condicionantes de risco" />
            <PageHeader
                title="Condicionantes"
                breadcrumbs={[
                    { label: 'Painel', href: '/gestao' },
                    { label: 'Classificação de risco', href: '/gestao/risco' },
                ]}
            />

            <Card>
                <CardHeader
                    title="Condicionantes-pergunta"
                    description={
                        versaoSanitaria
                            ? `Versão sanitária vigente: ${versaoSanitaria.version}${
                                  vigenciaDesde ? ` (desde ${vigenciaDesde})` : ''
                              }`
                            : 'Nenhuma versão sanitária vigente.'
                    }
                    actions={
                        canMaintain ? (
                            <Button size="sm" onClick={() => setShowCreate(true)} disabled={!hasVigente}>
                                Nova condicionante
                            </Button>
                        ) : undefined
                    }
                />
                <CardContent>
                    <div className="space-y-5">
                        {canMaintain && !hasVigente && (
                            <div className="rounded-xl border border-warning-500 bg-warning-50 p-4 text-theme-sm text-gray-600 dark:border-warning-500/30 dark:bg-warning-500/15 dark:text-gray-300">
                                Não há versão sanitária vigente para vincular condicionantes. Publique uma versão de
                                risco sanitário antes de cadastrar.
                            </div>
                        )}

                        <TableToolbar
                            search={{
                                value: table.search,
                                onChange: table.setSearch,
                                placeholder: 'Buscar por pergunta ou CNAE...',
                                label: 'Buscar condicionantes',
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
                            rows={condicionantes.data}
                            rowKey={(item) => item.id}
                            loading={table.processing}
                            skeletonRows={8}
                            density="compact"
                            emptyState={
                                <EmptyState
                                    title={
                                        filtering ? 'Nenhum resultado para a busca' : 'Nenhuma condicionante cadastrada'
                                    }
                                    description={
                                        filtering
                                            ? 'Ajuste o termo de busca e tente novamente.'
                                            : 'Cadastre a primeira condicionante-pergunta da versão sanitária vigente.'
                                    }
                                    action={
                                        !filtering && canMaintain && hasVigente ? (
                                            <Button size="sm" onClick={() => setShowCreate(true)}>
                                                Nova condicionante
                                            </Button>
                                        ) : undefined
                                    }
                                />
                            }
                        />

                        <Pagination
                            links={condicionantes.links}
                            meta={{ from: condicionantes.from, to: condicionantes.to, total: condicionantes.total }}
                        />
                    </div>
                </CardContent>
            </Card>

            {canMaintain && showCreate && (
                <CondicionanteFormModal
                    condicionante={null}
                    niveis={niveisReclassificacao}
                    onClose={() => setShowCreate(false)}
                />
            )}

            {canMaintain && editing && (
                <CondicionanteFormModal
                    condicionante={editing}
                    niveis={niveisReclassificacao}
                    onClose={() => setEditing(null)}
                />
            )}

            {removing && (
                <ConfirmDialog
                    isOpen
                    onClose={() => setRemoving(null)}
                    onConfirm={executeRemoval}
                    title="Remover condicionante"
                    description={`Confirma a remoção da condicionante "${removing.pergunta}"? A pergunta deixa de ser feita ao requerente e o risco não será mais reclassificado por ela.`}
                    confirmLabel="Remover"
                    variant="danger"
                    processing={removeProcessing}
                />
            )}
        </>
    );
}

CondicionantesIndex.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
