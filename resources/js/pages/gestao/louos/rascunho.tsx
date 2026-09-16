import { Head, router, useForm, usePage } from '@inertiajs/react';
import type { ChangeEvent, FormEvent, ReactNode } from 'react';
import { useState } from 'react';
import PageHeader from '@/components/app/page-header';
import Input from '@/components/form/input';
import Label from '@/components/form/label';
import Select from '@/components/form/select';
import { AlertIcon, FileIcon, InfoIcon, PencilIcon, TrashIcon } from '@/components/icons';
import Button from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import DataTable from '@/components/ui/data-table/data-table';
import PerPageSelect from '@/components/ui/data-table/per-page-select';
import TableToolbar from '@/components/ui/data-table/table-toolbar';
import type { ColumnDef } from '@/components/ui/data-table/types';
import { useServerTable } from '@/components/ui/data-table/use-server-table';
import EmptyState from '@/components/ui/empty-state';
import { Modal } from '@/components/ui/modal';
import Pagination, { type PaginationLink } from '@/components/ui/pagination';
import GestaoLayout from '@/layouts/gestao-layout';
import type { SharedProps } from '@/types';
import {
    type AlteracaoField,
    type QuadroItem,
    buildAlteracaoPayload,
    linhaFieldsFor,
} from './quadro-fields';
import { getColumns } from './quadro-columns';

const PER_PAGE_OPTIONS = [10, 15, 25, 50];

interface DraftInfo {
    id: number;
    version: string;
    autor: { id: number; name: string | null };
}

interface DiffInfo {
    novas: number;
    alteradas: number;
    excluidas: number;
}

interface ImportacaoRelatorio {
    arquivo?: string;
    lidos: number;
    importados: number;
    atualizados: number;
    rejeitados: string[];
    // Campos extras por quadro (ex.: quadro7 traz cnaes_distintos/total_faixas)
    [key: string]: unknown;
}

interface RascunhoProps {
    quadro: string;
    quadroLabel: string;
    draft: DraftInfo | null;
    itens: {
        data: QuadroItem[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    } | null;
    diff: DiffInfo | null;
    canPublish: boolean;
    perPageOptions?: number[];
}

type SharedPropsWithImportacao = SharedProps & {
    flash: SharedProps['flash'] & { importacao?: ImportacaoRelatorio };
};

/** Inicializa o formulário de uma linha a partir de uma linha existente. */
function linhaToFormValues(quadro: string, row: QuadroItem): Record<string, string> {
    const fields = linhaFieldsFor(quadro);
    const values: Record<string, string> = {};

    for (const field of fields) {
        const raw = (row as Record<string, unknown>)[field.key];

        if (raw === null || raw === undefined) {
            values[field.key] = '';
        } else if (field.toArray && Array.isArray(raw)) {
            values[field.key] = raw.join('\n');
        } else {
            values[field.key] = String(raw);
        }
    }

    return values;
}

/** Modal de criação e edição de uma linha do rascunho. */
function LinhaQuadroModal({
    quadro,
    linhaId,
    initialValues,
    onClose,
}: {
    quadro: string;
    linhaId: number | null;
    initialValues: Record<string, string>;
    onClose: () => void;
}) {
    const isEdit = linhaId !== null;
    const fields = linhaFieldsFor(quadro);

    const { data, setData, post, put, processing, errors, reset, transform } = useForm<Record<string, string>>(initialValues);

    function submit(event: FormEvent) {
        event.preventDefault();

        transform((current) => ({
            quadro,
            ...buildAlteracaoPayload(fields, current),
        }));

        if (isEdit) {
            put(`/gestao/louos/rascunho/linhas/${linhaId}`, {
                preserveScroll: true,
                onSuccess: () => { reset(); onClose(); },
            });
        } else {
            post('/gestao/louos/rascunho/linhas', {
                preserveScroll: true,
                onSuccess: () => { reset(); onClose(); },
            });
        }
    }

    return (
        <Modal isOpen onClose={onClose} className="m-4 max-h-[90vh] max-w-[700px] overflow-y-auto p-6 lg:p-8">
            <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90">
                {isEdit ? 'Editar linha' : 'Nova linha'} do rascunho
            </h4>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                A alteração é salva no rascunho. A versão vigente não é afetada até a publicação.
            </p>

            <form onSubmit={submit} className="mt-6 grid gap-4 sm:grid-cols-2">
                {fields.map((field: AlteracaoField) => {
                    const fieldId = `linha-${field.key}`;
                    const value = data[field.key] ?? '';

                    return (
                        <div key={field.key} className={field.full ? 'sm:col-span-2' : ''}>
                            <Label htmlFor={fieldId} required={field.required}>
                                {field.label}
                            </Label>
                            {field.kind === 'select' ? (
                                <>
                                    <Select
                                        id={fieldId}
                                        value={value}
                                        onChange={(next) => setData(field.key, next)}
                                        placeholder="Selecione"
                                        options={field.options ?? []}
                                    />
                                    {errors[field.key] && (
                                        <p className="mt-1 text-theme-xs text-error-500">{errors[field.key]}</p>
                                    )}
                                </>
                            ) : field.kind === 'textarea' ? (
                                <textarea
                                    id={fieldId}
                                    value={value}
                                    onChange={(event: ChangeEvent<HTMLTextAreaElement>) =>
                                        setData(field.key, event.target.value)
                                    }
                                    rows={4}
                                    placeholder={field.placeholder}
                                    className={`w-full rounded-lg border bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 ${errors[field.key] ? 'border-error-300 focus:border-error-300 focus:ring-error-500/20 dark:border-error-700' : 'border-gray-300 focus:border-brand-300 focus:ring-brand-500/20 dark:border-gray-700'}`}
                                />
                            ) : (
                                <Input
                                    id={fieldId}
                                    type={field.kind === 'number' ? 'number' : 'text'}
                                    min={field.kind === 'number' ? 0 : undefined}
                                    value={value}
                                    onChange={(event) => setData(field.key, event.target.value)}
                                    placeholder={field.placeholder}
                                    error={!!errors[field.key]}
                                    hint={errors[field.key]}
                                />
                            )}
                        </div>
                    );
                })}

                <div className="flex items-center justify-end gap-3 sm:col-span-2">
                    <Button size="sm" variant="outline" onClick={onClose} disabled={processing}>
                        Cancelar
                    </Button>
                    <Button size="sm" type="submit" loading={processing}>
                        {isEdit ? 'Salvar alteração' : 'Inserir linha'}
                    </Button>
                </div>
            </form>
        </Modal>
    );
}

/** Modal de confirmação de exclusão de linha. */
function ConfirmarExclusaoModal({
    linhaId,
    quadro,
    onClose,
}: {
    linhaId: number;
    quadro: string;
    onClose: () => void;
}) {
    const [processing, setProcessing] = useState(false);

    function confirmar() {
        setProcessing(true);

        router.delete(`/gestao/louos/rascunho/linhas/${linhaId}`, {
            data: { quadro },
            preserveScroll: true,
            onFinish: () => setProcessing(false),
            onSuccess: () => onClose(),
        });
    }

    return (
        <Modal isOpen onClose={onClose} className="m-4 max-w-md p-6 lg:p-8">
            <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90">Excluir linha</h4>
            <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">
                A linha #{linhaId} será removida do rascunho. A versão vigente não é afetada.
            </p>
            <div className="mt-6 flex items-center justify-end gap-3">
                <Button size="sm" variant="outline" onClick={onClose} disabled={processing}>
                    Cancelar
                </Button>
                <Button size="sm" variant="danger" onClick={confirmar} loading={processing}>
                    Excluir linha
                </Button>
            </div>
        </Modal>
    );
}

/** Modal de importação CSV. */
function ImportarCsvModal({
    quadro,
    onClose,
}: {
    quadro: string;
    onClose: () => void;
}) {
    const { data, setData, post, processing, errors } = useForm<{ quadro: string; arquivo: File | null }>({
        quadro,
        arquivo: null,
    });

    function submit(event: FormEvent) {
        event.preventDefault();

        post('/gestao/louos/rascunho/importar', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => onClose(),
        });
    }

    return (
        <Modal isOpen onClose={onClose} className="m-4 max-w-lg p-6 lg:p-8">
            <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90">Importar CSV</h4>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                As linhas do arquivo serão inseridas ou atualizadas no rascunho pela chave natural.
            </p>

            <form onSubmit={submit} className="mt-5 flex flex-col gap-4">
                <div>
                    <Label htmlFor="csv-arquivo" required>
                        Arquivo CSV
                    </Label>
                    <input
                        id="csv-arquivo"
                        type="file"
                        accept=".csv,text/csv"
                        onChange={(event) => setData('arquivo', event.target.files?.[0] ?? null)}
                        className="block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm text-gray-800 file:mr-3 file:rounded file:border-0 file:bg-gray-100 file:px-3 file:py-1 file:text-xs file:text-gray-700 focus:border-brand-300 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                    />
                    {errors.arquivo && <p className="mt-1 text-theme-xs text-error-500">{errors.arquivo}</p>}
                </div>

                <div className="flex items-center justify-end gap-3">
                    <Button size="sm" variant="outline" onClick={onClose} disabled={processing}>
                        Cancelar
                    </Button>
                    <Button size="sm" type="submit" loading={processing} disabled={!data.arquivo}>
                        Importar
                    </Button>
                </div>
            </form>
        </Modal>
    );
}

/** Card exibido após importação com o relatório de resultados. */
function RelatorioImportacao({ relatorio }: { relatorio: ImportacaoRelatorio }) {
    return (
        <div className="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-white/[0.02]">
            <div className="flex items-center gap-2">
                <FileIcon className="size-4 fill-current text-gray-400" />
                <span className="text-theme-sm font-medium text-gray-700 dark:text-gray-300">
                    Importação: {relatorio.arquivo ?? 'arquivo.csv'}
                </span>
            </div>
            <div className="mt-3 flex flex-wrap gap-4 text-theme-sm text-gray-600 dark:text-gray-400">
                <span>
                    <strong className="text-gray-800 dark:text-white/90">{relatorio.lidos}</strong> lidas
                </span>
                <span>
                    <strong className="text-gray-800 dark:text-white/90">{relatorio.importados}</strong> inseridas
                </span>
                <span>
                    <strong className="text-gray-800 dark:text-white/90">{relatorio.atualizados}</strong> atualizadas
                </span>
                {relatorio.rejeitados.length > 0 && (
                    <span className="text-error-600 dark:text-error-400">
                        <strong>{relatorio.rejeitados.length}</strong> rejeitadas
                    </span>
                )}
            </div>
            {relatorio.rejeitados.length > 0 && (
                <div className="mt-3 max-h-40 overflow-y-auto rounded-lg border border-error-200 bg-error-50 p-3 dark:border-error-500/30 dark:bg-error-500/10">
                    <p className="mb-1.5 text-theme-xs font-medium text-error-700 dark:text-error-400">
                        Linhas rejeitadas
                    </p>
                    <ol className="space-y-1 text-theme-xs text-error-600 dark:text-error-400">
                        {relatorio.rejeitados.map((motivo, index) => (
                            <li key={index}>{motivo}</li>
                        ))}
                    </ol>
                </div>
            )}
        </div>
    );
}

/** Modal de confirmação de publicação com diff e regra de quatro olhos. */
function PublicarModal({
    quadro,
    diff,
    canPublish,
    onClose,
}: {
    quadro: string;
    diff: DiffInfo | null;
    canPublish: boolean;
    onClose: () => void;
}) {
    const [processing, setProcessing] = useState(false);

    function confirmar() {
        setProcessing(true);

        router.put('/gestao/louos/rascunho/publicar', { quadro }, {
            preserveScroll: true,
            onFinish: () => setProcessing(false),
        });
    }

    const partes: string[] = [];

    if (diff) {
        if (diff.novas > 0) {
            partes.push(`${diff.novas} ${diff.novas === 1 ? 'nova' : 'novas'}`);
        }

        if (diff.alteradas > 0) {
            partes.push(`${diff.alteradas} ${diff.alteradas === 1 ? 'alterada' : 'alteradas'}`);
        }

        if (diff.excluidas > 0) {
            partes.push(`${diff.excluidas} ${diff.excluidas === 1 ? 'excluída' : 'excluídas'}`);
        }
    }

    const diffDesc = partes.length > 0 ? partes.join(', ') : 'nenhuma alteração em relação à versão vigente';

    return (
        <Modal isOpen onClose={onClose} className="m-4 max-w-md p-6 lg:p-8">
            <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90">Publicar rascunho</h4>

            <div className="mt-3 rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-white/[0.02]">
                <p className="text-theme-sm text-gray-600 dark:text-gray-400">
                    Diferenças em relação à versão vigente:{' '}
                    <strong className="text-gray-800 dark:text-white/90">{diffDesc}</strong>.
                </p>
            </div>

            {!canPublish && (
                <div className="mt-4 flex items-start gap-3 rounded-xl border border-warning-200 bg-warning-50 p-4 dark:border-warning-500/30 dark:bg-warning-500/10">
                    <AlertIcon className="size-5 shrink-0 fill-current text-warning-500" />
                    <p className="text-theme-sm text-gray-600 dark:text-gray-300">
                        <span className="font-medium text-warning-600 dark:text-orange-400">Quatro olhos.</span>{' '}
                        Você abriu este rascunho e não pode publicá-lo. Solicite que outro usuário faça a publicação.
                    </p>
                </div>
            )}

            <div className="mt-6 flex items-center justify-end gap-3">
                <Button size="sm" variant="outline" onClick={onClose} disabled={processing}>
                    Cancelar
                </Button>
                <Button size="sm" onClick={confirmar} loading={processing} disabled={!canPublish}>
                    Confirmar publicação
                </Button>
            </div>
        </Modal>
    );
}

/** Modal de confirmação de descarte do rascunho. */
function DescartarModal({
    quadro,
    draftVersion,
    onClose,
}: {
    quadro: string;
    draftVersion: string;
    onClose: () => void;
}) {
    const [processing, setProcessing] = useState(false);

    function confirmar() {
        setProcessing(true);

        router.delete('/gestao/louos/rascunho', {
            data: { quadro },
            preserveScroll: true,
            onFinish: () => setProcessing(false),
        });
    }

    return (
        <Modal isOpen onClose={onClose} className="m-4 max-w-md p-6 lg:p-8">
            <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90">Descartar rascunho</h4>
            <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">
                O rascunho <strong>{draftVersion}</strong> e todas as linhas editadas serão descartados permanentemente.
                A versão vigente não é afetada.
            </p>
            <div className="mt-6 flex items-center justify-end gap-3">
                <Button size="sm" variant="outline" onClick={onClose} disabled={processing}>
                    Cancelar
                </Button>
                <Button size="sm" variant="danger" onClick={confirmar} loading={processing}>
                    Descartar rascunho
                </Button>
            </div>
        </Modal>
    );
}

/** Formulário de abertura de rascunho quando nenhum está ativo. */
function AbrirRascunhoCard({ quadro, quadroLabel }: { quadro: string; quadroLabel: string }) {
    const { data, setData, post, processing, errors } = useForm({ quadro, version: '' });

    function submit(event: FormEvent) {
        event.preventDefault();
        post('/gestao/louos/rascunho', { preserveScroll: true });
    }

    return (
        <Card>
            <CardContent>
                <div className="flex flex-col items-start gap-6 sm:flex-row sm:items-center">
                    <div className="flex-1">
                        <h3 className="text-base font-medium text-gray-800 dark:text-white/90">
                            Nenhum rascunho aberto
                        </h3>
                        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Abra um rascunho de edição para o {quadroLabel}. A versão vigente permanece intacta até a
                            publicação.
                        </p>
                    </div>
                    <form onSubmit={submit} className="flex w-full items-end gap-3 sm:w-auto">
                        <div className="flex-1 sm:w-64">
                            <Label htmlFor="open-version" required>
                                Identificador da versão
                            </Label>
                            <Input
                                id="open-version"
                                type="text"
                                value={data.version}
                                onChange={(event) => setData('version', event.target.value)}
                                placeholder="ex.: quadro7-rev3"
                                error={!!errors.version}
                                hint={errors.version}
                            />
                        </div>
                        <Button type="submit" size="sm" loading={processing} disabled={data.version.trim() === ''}>
                            Abrir rascunho
                        </Button>
                    </form>
                </div>
            </CardContent>
        </Card>
    );
}

export default function LouosRascunho({
    quadro,
    quadroLabel,
    draft,
    itens,
    diff,
    canPublish,
    perPageOptions = PER_PAGE_OPTIONS,
}: RascunhoProps) {
    const { auth, flash } = usePage<SharedPropsWithImportacao>().props;
    const canMaintain = auth.permissions.includes('manter-louos');

    const table = useServerTable({
        url: '/gestao/louos/rascunho',
        initialSearch: '',
        initialSort: { column: 'natural', direction: 'asc' },
        initialPerPage: 15,
        initialFilters: { quadro },
    });

    const [linhaModal, setLinhaModal] = useState<{ linhaId: number | null; initialValues: Record<string, string> } | null>(null);
    const [excluirLinhaId, setExcluirLinhaId] = useState<number | null>(null);
    const [showImportar, setShowImportar] = useState(false);
    const [showPublicar, setShowPublicar] = useState(false);
    const [showDescartar, setShowDescartar] = useState(false);

    const importacaoRelatorio = flash.importacao ?? null;

    const baseColumns = getColumns(quadro);
    const columns: ColumnDef<QuadroItem>[] = [
        ...baseColumns,
        {
            id: 'acoes',
            header: '',
            cellClassName: 'whitespace-nowrap',
            cell: (row) => (
                <div className="flex items-center gap-2">
                    <button
                        type="button"
                        onClick={() =>
                            setLinhaModal({ linhaId: row.id, initialValues: linhaToFormValues(quadro, row) })
                        }
                        aria-label={`Editar linha ${row.id}`}
                        className="inline-flex h-8 w-8 items-center justify-center rounded-lg text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/[0.05] dark:hover:text-gray-300"
                    >
                        <PencilIcon className="size-4" />
                    </button>
                    <button
                        type="button"
                        onClick={() => setExcluirLinhaId(row.id)}
                        aria-label={`Excluir linha ${row.id}`}
                        className="inline-flex h-8 w-8 items-center justify-center rounded-lg text-error-500 transition hover:bg-error-50 dark:hover:bg-error-500/10"
                    >
                        <TrashIcon className="size-4" />
                    </button>
                </div>
            ),
        },
    ];

    const filtering = table.search.trim() !== '';

    return (
        <>
            <Head title={`Rascunho — ${quadroLabel}`} />
            <PageHeader
                title={`Rascunho — ${quadroLabel}`}
                breadcrumbs={[
                    { label: 'Painel', href: '/gestao' },
                    { label: 'Quadros da LOUOS', href: '/gestao/louos' },
                ]}
            />

            <div className="space-y-6">
                {flash.status && (
                    <div className="flex items-start gap-3 rounded-xl border border-success-200 bg-success-50 p-4 dark:border-success-500/30 dark:bg-success-500/10">
                        <InfoIcon className="size-5 shrink-0 fill-current text-success-500" />
                        <p className="text-theme-sm text-gray-700 dark:text-gray-300">{flash.status}</p>
                    </div>
                )}

                {flash.error && (
                    <div className="flex items-start gap-3 rounded-xl border border-error-200 bg-error-50 p-4 dark:border-error-500/30 dark:bg-error-500/10">
                        <AlertIcon className="size-5 shrink-0 fill-current text-error-500" />
                        <p className="text-theme-sm text-gray-700 dark:text-gray-300">{flash.error}</p>
                    </div>
                )}

                {importacaoRelatorio && <RelatorioImportacao relatorio={importacaoRelatorio} />}

                {draft === null ? (
                    canMaintain ? (
                        <AbrirRascunhoCard quadro={quadro} quadroLabel={quadroLabel} />
                    ) : (
                        <Card>
                            <CardContent>
                                <p className="text-sm text-gray-500 dark:text-gray-400">
                                    Nenhum rascunho em edição para o {quadroLabel}.
                                </p>
                            </CardContent>
                        </Card>
                    )
                ) : (
                    <>
                        <div className="flex items-start gap-3 rounded-xl border border-blue-light-200 bg-blue-light-50 p-4 dark:border-blue-light-500/30 dark:bg-blue-light-500/15">
                            <InfoIcon className="size-5 shrink-0 fill-current text-blue-light-500" />
                            <p className="text-theme-sm text-gray-700 dark:text-gray-300">
                                Rascunho <strong>{draft.version}</strong> em edição — autor:{' '}
                                <strong>{draft.autor.name ?? `ID ${draft.autor.id}`}</strong>. A versão vigente não é
                                alterada até a publicação.
                            </p>
                        </div>

                        <Card>
                            <CardHeader
                                title={quadroLabel}
                                description={`Rascunho ${draft.version}`}
                                actions={
                                    canMaintain ? (
                                        <div className="flex flex-wrap items-center gap-2">
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    setLinhaModal({ linhaId: null, initialValues: {} })
                                                }
                                            >
                                                Nova linha
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() => setShowImportar(true)}
                                            >
                                                Importar CSV
                                            </Button>
                                            <a
                                                href={`/gestao/louos/modelo-csv?quadro=${quadro}`}
                                                download
                                                className="inline-flex items-center justify-center gap-2 rounded-lg bg-white px-4 py-3 text-sm text-gray-700 ring-1 ring-inset ring-gray-300 transition hover:bg-gray-50 focus:outline-hidden focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500 dark:bg-gray-800 dark:text-gray-400 dark:ring-gray-700 dark:hover:bg-white/[0.03]"
                                            >
                                                Baixar modelo CSV
                                            </a>
                                            <Button
                                                size="sm"
                                                onClick={() => setShowPublicar(true)}
                                            >
                                                Publicar
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="danger"
                                                onClick={() => setShowDescartar(true)}
                                            >
                                                Descartar
                                            </Button>
                                        </div>
                                    ) : undefined
                                }
                            />
                            <CardContent>
                                <div className="space-y-5">
                                    <TableToolbar
                                        search={{
                                            value: table.search,
                                            onChange: table.setSearch,
                                            placeholder: 'Buscar no rascunho...',
                                            label: 'Buscar nas linhas do rascunho',
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
                                        rows={itens?.data ?? []}
                                        rowKey={(row) => row.id}
                                        loading={table.processing}
                                        skeletonRows={8}
                                        density="compact"
                                        emptyState={
                                            <EmptyState
                                                title={
                                                    filtering
                                                        ? 'Nenhum resultado para a busca'
                                                        : 'Nenhuma linha no rascunho'
                                                }
                                                description={
                                                    filtering
                                                        ? 'Ajuste o termo de busca e tente novamente.'
                                                        : 'Adicione linhas manualmente ou importe um CSV.'
                                                }
                                            />
                                        }
                                    />

                                    {itens && (
                                        <Pagination
                                            links={itens.links}
                                            meta={{ from: itens.from, to: itens.to, total: itens.total }}
                                        />
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    </>
                )}
            </div>

            {linhaModal !== null && (
                <LinhaQuadroModal
                    quadro={quadro}
                    linhaId={linhaModal.linhaId}
                    initialValues={linhaModal.initialValues}
                    onClose={() => setLinhaModal(null)}
                />
            )}

            {excluirLinhaId !== null && (
                <ConfirmarExclusaoModal
                    linhaId={excluirLinhaId}
                    quadro={quadro}
                    onClose={() => setExcluirLinhaId(null)}
                />
            )}

            {showImportar && (
                <ImportarCsvModal quadro={quadro} onClose={() => setShowImportar(false)} />
            )}

            {showPublicar && draft !== null && (
                <PublicarModal
                    quadro={quadro}
                    diff={diff}
                    canPublish={canPublish}
                    onClose={() => setShowPublicar(false)}
                />
            )}

            {showDescartar && draft !== null && (
                <DescartarModal
                    quadro={quadro}
                    draftVersion={draft.version}
                    onClose={() => setShowDescartar(false)}
                />
            )}
        </>
    );
}

LouosRascunho.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
