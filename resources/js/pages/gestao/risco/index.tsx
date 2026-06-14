import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import { useState } from 'react';
import PageHeader from '@/components/app/page-header';
import Input from '@/components/form/input';
import Label from '@/components/form/label';
import Select from '@/components/form/select';
import { ArrowRightIcon, InfoIcon, ListIcon, ShieldIcon, TrashIcon } from '@/components/icons';
import Badge from '@/components/ui/badge';
import Button from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import DataTable from '@/components/ui/data-table/data-table';
import PerPageSelect from '@/components/ui/data-table/per-page-select';
import TableToolbar from '@/components/ui/data-table/table-toolbar';
import type { ColumnDef } from '@/components/ui/data-table/types';
import { useServerTable } from '@/components/ui/data-table/use-server-table';
import EmptyState from '@/components/ui/empty-state';
import KpiCard from '@/components/ui/kpi-card';
import { Modal } from '@/components/ui/modal';
import Pagination, { type PaginationLink } from '@/components/ui/pagination';
import GestaoLayout from '@/layouts/gestao-layout';
import type { SharedProps } from '@/types';

interface ClassificacaoItem {
    id: number;
    cnae_code: string;
    formatted_code: string;
    cnae_description: string | null;
    risco_municipal: string;
    risco_municipal_label: string;
    condicionantes: string[] | null;
    observacao: string | null;
}

interface VersaoInfo {
    version: string;
    valid_from: string | null;
}

interface ResumoNiveis {
    baixo_a: number;
    baixo_b: number;
    alto: number;
}

interface RiscoIndexProps {
    classificacoes: {
        data: ClassificacaoItem[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
    versaoMunicipal: VersaoInfo | null;
    versaoSanitaria: VersaoInfo | null;
    resumoNiveis: ResumoNiveis;
    filtros: {
        search: string;
        nivel: string;
        per_page: number;
    };
    perPageOptions: number[];
}

// Níveis do Decreto nº 32.636/2020 — não existe "médio" no risco municipal.
const NIVEIS_MUNICIPAIS = [
    { value: 'baixo_a', label: 'Baixo Risco A' },
    { value: 'baixo_b', label: 'Baixo Risco B' },
    { value: 'alto', label: 'Alto Risco' },
];

function municipalColor(nivel: string): 'success' | 'error' {
    return nivel === 'alto' ? 'error' : 'success';
}

function nivelLabel(nivel: string): string {
    return NIVEIS_MUNICIPAIS.find((n) => n.value === nivel)?.label ?? nivel;
}

/** Formata a data ISO (YYYY-MM-DD) para dd/mm/aaaa sem deslocamento de fuso. */
function formatarData(iso: string | null): string | null {
    if (!iso) {
        return null;
    }

    const [ano, mes, dia] = iso.split('-');

    return dia && mes && ano ? `${dia}/${mes}/${ano}` : iso;
}

interface DimensaoCardProps {
    eyebrow: string;
    titulo: string;
    fundamento: string;
    icon: ReactNode;
    versao: VersaoInfo | null;
    children?: ReactNode;
}

/**
 * Painel de uma dimensão de risco. Municipal (Decreto 32.636/2020) e sanitária
 * (VISA) são dimensões SEPARADAS (RN-009): cada uma com sua versão vigente.
 */
function DimensaoCard({ eyebrow, titulo, fundamento, icon, versao, children }: DimensaoCardProps) {
    const vigenciaDesde = formatarData(versao?.valid_from ?? null);

    return (
        <div className="flex flex-col rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
            <div className="flex items-center gap-3">
                <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-brand-50 text-brand-500 dark:bg-brand-500/15 dark:text-brand-400">
                    {icon}
                </span>
                <div>
                    <p className="text-theme-xs font-medium tracking-wide text-gray-400 uppercase dark:text-gray-500">
                        {eyebrow}
                    </p>
                    <h3 className="text-base font-medium text-gray-800 dark:text-white/90">{titulo}</h3>
                </div>
            </div>

            <p className="mt-3 text-theme-sm text-gray-500 dark:text-gray-400">{fundamento}</p>

            <div className="mt-3 flex flex-wrap items-center gap-2">
                {versao ? (
                    <>
                        <Badge color="success" size="sm">
                            Versão {versao.version}
                        </Badge>
                        {vigenciaDesde && (
                            <span className="text-theme-xs text-gray-400 dark:text-gray-500">
                                vigente desde {vigenciaDesde}
                            </span>
                        )}
                    </>
                ) : (
                    <Badge color="warning" size="sm">
                        Sem versão vigente
                    </Badge>
                )}
            </div>

            {children && <div className="mt-4">{children}</div>}
        </div>
    );
}

/**
 * Publicação versionada da tabela de risco municipal (HU-053/HU-020). Atualizar
 * publica uma NOVA versão por quatro olhos — o autor deve ser distinto do
 * publicador. A regra é reforçada no backend (flash.error); aqui é comunicada e
 * verificada no cliente (defesa em profundidade), nunca silenciosa.
 */
function PublishVersionModal({ onClose, authUserId }: { onClose: () => void; authUserId: number | null }) {
    const { data, setData, put, processing, errors, reset, transform } = useForm<{
        version: string;
        author_id: string;
        alteracoes: { cnae_code: string; risco_municipal: string }[];
    }>({
        version: '',
        author_id: '',
        alteracoes: [],
    });

    const authorIsPublisher =
        authUserId !== null && data.author_id.trim() !== '' && Number(data.author_id) === authUserId;

    function updateAlteracao(index: number, key: 'cnae_code' | 'risco_municipal', value: string) {
        setData(
            'alteracoes',
            data.alteracoes.map((alteracao, i) => (i === index ? { ...alteracao, [key]: value } : alteracao)),
        );
    }

    const alteracoesError = Object.entries(errors).find(([key]) => key.startsWith('alteracoes'))?.[1];

    function submit(event: FormEvent) {
        event.preventDefault();

        transform((current) => ({
            version: current.version,
            author_id: current.author_id.trim() === '' ? '' : Number(current.author_id),
            alteracoes: current.alteracoes
                .filter((alteracao) => alteracao.cnae_code.trim() !== '' && alteracao.risco_municipal !== '')
                .map((alteracao) => ({
                    cnae_code: alteracao.cnae_code.trim(),
                    risco_municipal: alteracao.risco_municipal,
                })),
        }));

        put('/gestao/risco/publicar', {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onClose();
            },
        });
    }

    const submitDisabled =
        processing || authorIsPublisher || data.version.trim() === '' || data.author_id.trim() === '';

    return (
        <Modal isOpen onClose={onClose} className="m-4 max-h-[90vh] max-w-[700px] overflow-y-auto p-6 lg:p-8">
            <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90">
                Publicar nova versão da tabela de risco
            </h4>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Atualizar a classificação publica uma nova versão e preserva a anterior — nunca edição destrutiva.
            </p>

            <div className="mt-4 flex items-start gap-3 rounded-xl border border-blue-light-200 bg-blue-light-50 p-4 dark:border-blue-light-500/30 dark:bg-blue-light-500/15">
                <InfoIcon className="size-5 shrink-0 fill-current text-blue-light-500" />
                <p className="text-theme-sm text-gray-600 dark:text-gray-300">
                    Publicação por <strong>quatro olhos</strong>: o autor da nova versão deve ser diferente de quem
                    publica. Informe o ID de outro usuário responsável pela alteração
                    {authUserId !== null && <> — você (ID {authUserId}) consta como publicador</>}.
                </p>
            </div>

            <form onSubmit={submit} className="mt-6 flex flex-col gap-5">
                <div className="grid gap-5 sm:grid-cols-2">
                    <div>
                        <Label htmlFor="publish-version" required>
                            Identificador da versão
                        </Label>
                        <Input
                            id="publish-version"
                            type="text"
                            value={data.version}
                            onChange={(event) => setData('version', event.target.value)}
                            placeholder="ex.: decreto-32636-2020-rev2"
                            error={!!errors.version}
                            hint={errors.version}
                        />
                    </div>
                    <div>
                        <Label htmlFor="publish-author" required>
                            ID do usuário autor
                        </Label>
                        <Input
                            id="publish-author"
                            type="number"
                            min={1}
                            value={data.author_id}
                            onChange={(event) => setData('author_id', event.target.value)}
                            placeholder="ex.: 42"
                            error={authorIsPublisher || !!errors.author_id}
                            hint={
                                authorIsPublisher
                                    ? 'O autor deve ser diferente do publicador (quatro olhos).'
                                    : errors.author_id
                            }
                        />
                    </div>
                </div>

                <div className="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <Label className="mb-0">Alterações de classificação (opcional)</Label>
                        <Button
                            size="xs"
                            variant="outline"
                            onClick={() =>
                                setData('alteracoes', [...data.alteracoes, { cnae_code: '', risco_municipal: '' }])
                            }
                        >
                            Adicionar alteração
                        </Button>
                    </div>
                    <p className="mt-1.5 text-theme-xs text-gray-400 dark:text-gray-500">
                        Sem alterações, a nova versão herda integralmente a tabela vigente.
                    </p>

                    {data.alteracoes.length > 0 && (
                        <div className="mt-4 flex flex-col gap-3">
                            {data.alteracoes.map((alteracao, index) => (
                                <div key={index} className="flex flex-col gap-2 sm:flex-row sm:items-center">
                                    <div className="sm:w-44">
                                        <Input
                                            type="text"
                                            value={alteracao.cnae_code}
                                            onChange={(event) =>
                                                updateAlteracao(index, 'cnae_code', event.target.value)
                                            }
                                            placeholder="CNAE (0000-0/00)"
                                            aria-label={`Código CNAE da alteração ${index + 1}`}
                                        />
                                    </div>
                                    <div className="flex-1">
                                        <Select
                                            value={alteracao.risco_municipal}
                                            onChange={(value) => updateAlteracao(index, 'risco_municipal', value)}
                                            placeholder="Novo nível"
                                            options={NIVEIS_MUNICIPAIS}
                                        />
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setData(
                                                'alteracoes',
                                                data.alteracoes.filter((_, i) => i !== index),
                                            )
                                        }
                                        aria-label={`Remover alteração ${index + 1}`}
                                        className="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg text-error-500 ring-1 ring-inset ring-error-200 transition hover:bg-error-50 dark:ring-error-500/30 dark:hover:bg-error-500/10"
                                    >
                                        <TrashIcon className="size-4.5" />
                                    </button>
                                </div>
                            ))}
                        </div>
                    )}

                    {alteracoesError && <p className="mt-2 text-theme-xs text-error-500">{alteracoesError}</p>}
                </div>

                <div className="flex items-center justify-end gap-3">
                    <Button size="sm" variant="outline" onClick={onClose} disabled={processing}>
                        Cancelar
                    </Button>
                    <Button size="sm" type="submit" disabled={submitDisabled}>
                        {processing ? 'Publicando...' : 'Publicar nova versão'}
                    </Button>
                </div>
            </form>
        </Modal>
    );
}

export default function RiscoIndex({
    classificacoes,
    versaoMunicipal,
    versaoSanitaria,
    resumoNiveis,
    filtros,
    perPageOptions,
}: RiscoIndexProps) {
    const { auth } = usePage<SharedProps>().props;
    const canMaintain = auth.permissions.includes('manter-risco');

    const table = useServerTable({
        url: '/gestao/risco',
        initialSearch: filtros.search,
        initialSort: { column: 'cnae_code', direction: 'asc' },
        initialPerPage: filtros.per_page,
        initialFilters: { nivel: filtros.nivel },
    });

    const [showPublish, setShowPublish] = useState(false);

    const filtering = table.search.trim() !== '' || table.filters.nivel !== '';

    const columns: ColumnDef<ClassificacaoItem>[] = [
        {
            id: 'code',
            header: 'CNAE',
            cellClassName: 'font-medium whitespace-nowrap text-gray-800 dark:text-white/90',
            cell: (item) => item.formatted_code,
        },
        {
            id: 'description',
            header: 'Denominação',
            cell: (item) => item.cnae_description ?? '—',
        },
        {
            id: 'risco_municipal',
            header: 'Risco municipal',
            cellClassName: 'whitespace-nowrap',
            cell: (item) => (
                <Badge color={municipalColor(item.risco_municipal)} size="sm">
                    {item.risco_municipal_label}
                </Badge>
            ),
        },
        {
            id: 'condicionantes',
            header: 'Condicionantes',
            align: 'center',
            cellClassName: 'whitespace-nowrap',
            cell: (item) =>
                item.condicionantes && item.condicionantes.length > 0 ? (
                    <Badge color="light" size="sm">
                        {item.condicionantes.length}
                    </Badge>
                ) : (
                    <span className="text-gray-400 dark:text-gray-500">—</span>
                ),
        },
    ];

    return (
        <>
            <Head title="Classificação de risco" />
            <PageHeader title="Classificação de risco" breadcrumbs={[{ label: 'Painel', href: '/gestao' }]} />

            <div className="space-y-6">
                <div className="grid gap-4 md:gap-6 lg:grid-cols-2">
                    <DimensaoCard
                        eyebrow="Dimensão municipal"
                        titulo="Risco municipal"
                        fundamento="Decreto nº 32.636/2020 — classificação por subclasse CNAE."
                        icon={<ShieldIcon className="size-6" />}
                        versao={versaoMunicipal}
                    />
                    <DimensaoCard
                        eyebrow="Dimensão sanitária"
                        titulo="Risco sanitário"
                        fundamento="Vigilância Sanitária (VISA) — mantida por condicionantes-pergunta."
                        icon={<ListIcon className="size-6" />}
                        versao={versaoSanitaria}
                    >
                        <Link
                            href="/gestao/risco/condicionantes"
                            className="inline-flex items-center gap-1.5 text-theme-sm font-medium text-brand-500 transition hover:text-brand-600 dark:text-brand-400"
                        >
                            Gerenciar condicionantes
                            <ArrowRightIcon className="size-4" />
                        </Link>
                    </DimensaoCard>
                </div>

                <div className="grid gap-4 sm:grid-cols-3 md:gap-6">
                    <KpiCard
                        label="Baixo Risco A"
                        value={resumoNiveis.baixo_a}
                        tone="success"
                        icon={<ShieldIcon className="size-6" />}
                        note="Risco municipal vigente"
                    />
                    <KpiCard
                        label="Baixo Risco B"
                        value={resumoNiveis.baixo_b}
                        tone="success"
                        icon={<ShieldIcon className="size-6" />}
                        note="Risco municipal vigente"
                    />
                    <KpiCard
                        label="Alto Risco"
                        value={resumoNiveis.alto}
                        tone="error"
                        icon={<ShieldIcon className="size-6" />}
                        note="Risco municipal vigente"
                    />
                </div>

                <Card>
                    <CardHeader
                        title="Classificação municipal por CNAE"
                        description="Tabela de risco municipal vigente (Decreto nº 32.636/2020)"
                        actions={
                            canMaintain ? (
                                <Button size="sm" onClick={() => setShowPublish(true)}>
                                    Publicar nova versão
                                </Button>
                            ) : undefined
                        }
                    />
                    <CardContent>
                        <div className="space-y-5">
                            <TableToolbar
                                search={{
                                    value: table.search,
                                    onChange: table.setSearch,
                                    placeholder: 'Buscar por código ou denominação...',
                                    label: 'Buscar classificações de risco',
                                }}
                                filters={
                                    <div className="w-44">
                                        <label htmlFor="filter-nivel" className="sr-only">
                                            Filtrar por nível
                                        </label>
                                        <Select
                                            id="filter-nivel"
                                            value={table.filters.nivel}
                                            onChange={(value) => table.setFilter('nivel', value)}
                                            placeholder="Nível municipal"
                                            options={NIVEIS_MUNICIPAIS}
                                        />
                                    </div>
                                }
                                actions={
                                    <PerPageSelect
                                        value={table.perPage}
                                        options={perPageOptions}
                                        onChange={table.setPerPage}
                                    />
                                }
                            />

                            {table.filters.nivel !== '' && (
                                <div className="flex flex-wrap items-center gap-2">
                                    <Badge size="sm" color="light">
                                        Nível: {nivelLabel(table.filters.nivel)}
                                    </Badge>
                                    <button
                                        type="button"
                                        onClick={() => table.setFilter('nivel', '')}
                                        className="text-theme-xs font-medium text-brand-500 transition hover:text-brand-600 dark:text-brand-400"
                                    >
                                        Limpar filtro
                                    </button>
                                </div>
                            )}

                            <DataTable
                                columns={columns}
                                rows={classificacoes.data}
                                rowKey={(item) => item.id}
                                loading={table.processing}
                                skeletonRows={8}
                                density="compact"
                                emptyState={
                                    <EmptyState
                                        title={
                                            filtering
                                                ? 'Nenhum resultado para a busca'
                                                : 'Nenhuma classificação vigente'
                                        }
                                        description={
                                            filtering
                                                ? 'Ajuste o termo de busca ou o filtro de nível e tente novamente.'
                                                : 'Publique uma versão da tabela de risco municipal para iniciar a base.'
                                        }
                                    />
                                }
                            />

                            <Pagination
                                links={classificacoes.links}
                                meta={{
                                    from: classificacoes.from,
                                    to: classificacoes.to,
                                    total: classificacoes.total,
                                }}
                            />
                        </div>
                    </CardContent>
                </Card>
            </div>

            {canMaintain && showPublish && (
                <PublishVersionModal onClose={() => setShowPublish(false)} authUserId={auth.user?.id ?? null} />
            )}
        </>
    );
}

RiscoIndex.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
