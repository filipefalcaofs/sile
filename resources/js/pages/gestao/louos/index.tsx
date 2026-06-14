import { Head, useForm, usePage } from '@inertiajs/react';
import type { ChangeEvent, FormEvent, ReactNode } from 'react';
import { useState } from 'react';
import PageHeader from '@/components/app/page-header';
import Input from '@/components/form/input';
import Label from '@/components/form/label';
import Select from '@/components/form/select';
import { AlertIcon, InfoIcon, TrashIcon } from '@/components/icons';
import Badge from '@/components/ui/badge';
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

interface QuadroResumo {
    quadro: string;
    label: string;
    version: string | null;
    valid_from: string | null;
    total: number;
}

interface Quadro7Item {
    id: number;
    cnae_code: string;
    formatted_code: string;
    grupo: string;
    subgrupo: string | null;
    area_min: number;
    area_max: number | null;
    observacao: string | null;
}

interface Quadro10Item {
    id: number;
    zona: string;
    grupo_uso: string;
    subgrupo: string | null;
    permissao: string;
    permissao_label: string;
    condicionante_ref: string | null;
    base_legal: string | null;
}

interface Quadro11Item {
    id: number;
    classe_via: string;
    grupo_uso: string | null;
    condicoes: string[] | null;
    base_legal: string | null;
}

type QuadroItem = Quadro7Item | Quadro10Item | Quadro11Item;

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
    filtros: {
        quadro: string;
        search: string;
        per_page: number;
    };
    perPageOptions: number[];
}

/**
 * Metadados de UX por Quadro. `operacional` distingue o que já aplica de ponta a
 * ponta (Quadro 7 — faixa de área) do que está MODELADO a partir da Lei nº
 * 9.148/2016 mas depende de base territorial ainda pendente da SEDUR (Quadros 10
 * e 11/11A — zona urbanística e classificação viária). O aviso é honesto: a
 * regra existe e é versionada, mas não finge operação plena sem o insumo oficial.
 */
const QUADROS_META: Record<string, { descricao: string; operacional: boolean; nota?: string }> = {
    quadro7: {
        descricao: 'Faixas de área por CNAE — aplica integralmente no enquadramento.',
        operacional: true,
    },
    quadro10: {
        descricao: 'Permissão de uso por zona urbanística.',
        operacional: false,
        nota: 'Modelado a partir da Lei nº 9.148/2016. Aplica plenamente quando a base oficial de zonas urbanísticas (pendente SEDUR) for integrada; até lá, o uso por zona degrada para análise técnica.',
    },
    quadro11: {
        descricao: 'Condições de uso por classe de via.',
        operacional: false,
        nota: 'Modelado a partir da Lei nº 9.148/2016. Aplica plenamente quando a classificação viária oficial (pendente SEDUR) for confirmada.',
    },
    quadro11a: {
        descricao: 'Condições de uso por classe de via (complementar).',
        operacional: false,
        nota: 'Modelado a partir da Lei nº 9.148/2016. Aplica plenamente quando a classificação viária oficial (pendente SEDUR) for confirmada.',
    },
};

const PERMISSAO_OPTIONS = [
    { value: 'permitido', label: 'Permitido' },
    { value: 'permitido_condicionado', label: 'Permitido condicionado' },
    { value: 'proibido', label: 'Proibido' },
];

interface AlteracaoField {
    key: string;
    label: string;
    kind: 'text' | 'number' | 'select' | 'textarea';
    required?: boolean;
    options?: { value: string; label: string }[];
    placeholder?: string;
    /** Campo de texto que vira lista (uma entrada por linha) no payload. */
    toArray?: boolean;
    /** Ocupa a linha inteira do grid no modal. */
    full?: boolean;
}

/**
 * Campos editáveis por Quadro numa alteração de publicação. Espelham a chave
 * natural e o esquema da tabela tipada validados no PublishLouosVersionRequest:
 * sem a chave natural (required) a alteração não casa com a linha vigente.
 */
const ALTERACAO_FIELDS: Record<string, AlteracaoField[]> = {
    quadro7: [
        { key: 'cnae_code', label: 'CNAE', kind: 'text', required: true, placeholder: '0000-0/00' },
        { key: 'area_min', label: 'Área mínima (m²)', kind: 'number', required: true, placeholder: '0' },
        { key: 'area_max', label: 'Área máxima (m²)', kind: 'number', placeholder: 'sem limite' },
        { key: 'grupo', label: 'Grupo', kind: 'text', required: true, placeholder: 'ex.: nR3' },
        { key: 'subgrupo', label: 'Subgrupo', kind: 'text', placeholder: 'ex.: nR3-99' },
    ],
    quadro10: [
        { key: 'zona', label: 'Zona', kind: 'text', required: true, placeholder: 'ex.: ZCAL.1' },
        { key: 'grupo_uso', label: 'Grupo de uso', kind: 'text', required: true },
        { key: 'subgrupo', label: 'Subgrupo', kind: 'text' },
        { key: 'permissao', label: 'Permissão', kind: 'select', required: true, options: PERMISSAO_OPTIONS },
        { key: 'condicionante_ref', label: 'Condicionante', kind: 'text' },
        { key: 'base_legal', label: 'Base legal', kind: 'text', full: true },
    ],
    quadro11: [
        { key: 'classe_via', label: 'Classe de via', kind: 'text', required: true, placeholder: 'ex.: Via arterial' },
        { key: 'grupo_uso', label: 'Grupo de uso', kind: 'text' },
        { key: 'condicoes', label: 'Condições (uma por linha)', kind: 'textarea', toArray: true, full: true },
        { key: 'base_legal', label: 'Base legal', kind: 'text', full: true },
    ],
};
ALTERACAO_FIELDS.quadro11a = ALTERACAO_FIELDS.quadro11;

function fieldsFor(quadro: string): AlteracaoField[] {
    return ALTERACAO_FIELDS[quadro] ?? ALTERACAO_FIELDS.quadro7;
}

function permissaoColor(permissao: string): 'success' | 'warning' | 'error' {
    if (permissao === 'permitido') {
        return 'success';
    }

    if (permissao === 'proibido') {
        return 'error';
    }

    return 'warning';
}

/** Formata a data ISO (YYYY-MM-DD) para dd/mm/aaaa sem deslocamento de fuso. */
function formatarData(iso: string | null): string | null {
    if (!iso) {
        return null;
    }

    const [ano, mes, dia] = iso.split('-');

    return dia && mes && ano ? `${dia}/${mes}/${ano}` : iso;
}

/** Faixa de área legível: "0 – 350 m²" ou "≥ 200 m²" quando sem limite superior. */
function faixaArea(min: number, max: number | null): string {
    const fmt = (valor: number) => valor.toLocaleString('pt-BR');

    return max === null ? `≥ ${fmt(min)} m²` : `${fmt(min)} – ${fmt(max)} m²`;
}

/**
 * Monta o payload de UMA alteração a partir dos campos preenchidos: converte
 * números, transforma textareas em listas e descarta campos vazios.
 */
function buildAlteracaoPayload(fields: AlteracaoField[], row: Record<string, string>): Record<string, unknown> {
    const payload: Record<string, unknown> = {};

    for (const field of fields) {
        const raw = (row[field.key] ?? '').trim();

        if (raw === '') {
            continue;
        }

        if (field.kind === 'number') {
            payload[field.key] = Number(raw);
        } else if (field.toArray) {
            const itens = raw
                .split('\n')
                .map((line) => line.trim())
                .filter((line) => line !== '');

            if (itens.length > 0) {
                payload[field.key] = itens;
            }
        } else {
            payload[field.key] = raw;
        }
    }

    return payload;
}

/**
 * Publicação versionada de um Quadro da LOUOS (HU-046). Publicar gera uma NOVA
 * versão por quatro olhos — o autor deve ser distinto do publicador. A regra é
 * reforçada no backend (flash.error); aqui é comunicada e verificada no cliente
 * (defesa em profundidade), nunca silenciosa. Sem alterações, a nova versão
 * herda integralmente o Quadro vigente.
 */
function PublishQuadroVersionModal({
    quadro,
    quadroLabel,
    onClose,
    authUserId,
}: {
    quadro: string;
    quadroLabel: string;
    onClose: () => void;
    authUserId: number | null;
}) {
    const fields = fieldsFor(quadro);

    const { data, setData, put, processing, errors, reset, transform } = useForm<{
        version: string;
        author_id: string;
        alteracoes: Record<string, string>[];
    }>({
        version: '',
        author_id: '',
        alteracoes: [],
    });

    const authorIsPublisher =
        authUserId !== null && data.author_id.trim() !== '' && Number(data.author_id) === authUserId;

    function updateAlteracao(index: number, key: string, value: string) {
        setData(
            'alteracoes',
            data.alteracoes.map((alteracao, i) => (i === index ? { ...alteracao, [key]: value } : alteracao)),
        );
    }

    const alteracoesError = Object.entries(errors).find(([key]) => key.startsWith('alteracoes'))?.[1];

    function submit(event: FormEvent) {
        event.preventDefault();

        const requiredKeys = fields.filter((field) => field.required).map((field) => field.key);

        transform((current) => ({
            quadro,
            version: current.version.trim(),
            author_id: current.author_id.trim() === '' ? '' : Number(current.author_id),
            alteracoes: current.alteracoes
                .map((row) => buildAlteracaoPayload(fields, row))
                .filter((payload) => requiredKeys.every((key) => payload[key] !== undefined)),
        }));

        put('/gestao/louos/publicar', {
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
        <Modal isOpen onClose={onClose} className="m-4 max-h-[90vh] max-w-[760px] overflow-y-auto p-6 lg:p-8">
            <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90">
                Publicar nova versão do {quadroLabel}
            </h4>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Publicar gera uma nova versão e preserva a anterior — nunca edição destrutiva.
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
                            placeholder="ex.: lei-9148-2016-quadro7-rev2"
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
                        <Label className="mb-0">Alterações do {quadroLabel} (opcional)</Label>
                        <Button
                            size="xs"
                            variant="outline"
                            onClick={() => setData('alteracoes', [...data.alteracoes, {}])}
                        >
                            Adicionar alteração
                        </Button>
                    </div>
                    <p className="mt-1.5 text-theme-xs text-gray-400 dark:text-gray-500">
                        Sem alterações, a nova versão herda integralmente o Quadro vigente.
                    </p>

                    {data.alteracoes.length > 0 && (
                        <div className="mt-4 flex flex-col gap-4">
                            {data.alteracoes.map((alteracao, index) => (
                                <div
                                    key={index}
                                    className="rounded-lg border border-gray-200 p-4 dark:border-gray-800"
                                >
                                    <div className="mb-3 flex items-center justify-between gap-2">
                                        <span className="text-theme-sm font-medium text-gray-700 dark:text-gray-300">
                                            Alteração {index + 1}
                                        </span>
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setData(
                                                    'alteracoes',
                                                    data.alteracoes.filter((_, i) => i !== index),
                                                )
                                            }
                                            aria-label={`Remover alteração ${index + 1}`}
                                            className="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-error-500 ring-1 ring-inset ring-error-200 transition hover:bg-error-50 dark:ring-error-500/30 dark:hover:bg-error-500/10"
                                        >
                                            <TrashIcon className="size-4.5" />
                                        </button>
                                    </div>

                                    <div className="grid gap-4 sm:grid-cols-2">
                                        {fields.map((field) => {
                                            const fieldId = `alt-${index}-${field.key}`;
                                            const value = alteracao[field.key] ?? '';

                                            return (
                                                <div key={field.key} className={field.full ? 'sm:col-span-2' : ''}>
                                                    <Label htmlFor={fieldId} required={field.required}>
                                                        {field.label}
                                                    </Label>
                                                    {field.kind === 'select' ? (
                                                        <Select
                                                            id={fieldId}
                                                            value={value}
                                                            onChange={(next) =>
                                                                updateAlteracao(index, field.key, next)
                                                            }
                                                            placeholder="Selecione"
                                                            options={field.options ?? []}
                                                        />
                                                    ) : field.kind === 'textarea' ? (
                                                        <textarea
                                                            id={fieldId}
                                                            value={value}
                                                            onChange={(event: ChangeEvent<HTMLTextAreaElement>) =>
                                                                updateAlteracao(index, field.key, event.target.value)
                                                            }
                                                            rows={3}
                                                            placeholder={field.placeholder}
                                                            className="w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/20 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                                                        />
                                                    ) : (
                                                        <Input
                                                            id={fieldId}
                                                            type={field.kind === 'number' ? 'number' : 'text'}
                                                            min={field.kind === 'number' ? 0 : undefined}
                                                            value={value}
                                                            onChange={(event) =>
                                                                updateAlteracao(index, field.key, event.target.value)
                                                            }
                                                            placeholder={field.placeholder}
                                                        />
                                                    )}
                                                </div>
                                            );
                                        })}
                                    </div>
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
    const meta = QUADROS_META[resumo.quadro] ?? QUADROS_META.quadro7;
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

function getColumns(quadro: string): ColumnDef<QuadroItem>[] {
    if (quadro === 'quadro7') {
        return [
            {
                id: 'cnae',
                header: 'CNAE',
                cellClassName: 'font-medium whitespace-nowrap text-gray-800 dark:text-white/90',
                cell: (row) => (row as Quadro7Item).formatted_code,
            },
            { id: 'grupo', header: 'Grupo', cell: (row) => (row as Quadro7Item).grupo },
            { id: 'subgrupo', header: 'Subgrupo', cell: (row) => (row as Quadro7Item).subgrupo ?? '—' },
            {
                id: 'faixa',
                header: 'Faixa de área',
                cellClassName: 'whitespace-nowrap',
                cell: (row) => faixaArea((row as Quadro7Item).area_min, (row as Quadro7Item).area_max),
            },
            { id: 'observacao', header: 'Observação', cell: (row) => (row as Quadro7Item).observacao ?? '—' },
        ];
    }

    if (quadro === 'quadro10') {
        return [
            {
                id: 'zona',
                header: 'Zona',
                cellClassName: 'font-medium whitespace-nowrap text-gray-800 dark:text-white/90',
                cell: (row) => (row as Quadro10Item).zona,
            },
            { id: 'grupo_uso', header: 'Grupo de uso', cell: (row) => (row as Quadro10Item).grupo_uso },
            { id: 'subgrupo', header: 'Subgrupo', cell: (row) => (row as Quadro10Item).subgrupo ?? '—' },
            {
                id: 'permissao',
                header: 'Permissão',
                cellClassName: 'whitespace-nowrap',
                cell: (row) => (
                    <Badge color={permissaoColor((row as Quadro10Item).permissao)} size="sm">
                        {(row as Quadro10Item).permissao_label}
                    </Badge>
                ),
            },
            {
                id: 'condicionante',
                header: 'Condicionante',
                cell: (row) => (row as Quadro10Item).condicionante_ref ?? '—',
            },
            { id: 'base_legal', header: 'Base legal', cell: (row) => (row as Quadro10Item).base_legal ?? '—' },
        ];
    }

    return [
        {
            id: 'classe_via',
            header: 'Classe de via',
            cellClassName: 'font-medium whitespace-nowrap text-gray-800 dark:text-white/90',
            cell: (row) => (row as Quadro11Item).classe_via,
        },
        { id: 'grupo_uso', header: 'Grupo de uso', cell: (row) => (row as Quadro11Item).grupo_uso ?? '—' },
        {
            id: 'condicoes',
            header: 'Condições',
            cell: (row) => {
                const condicoes = (row as Quadro11Item).condicoes;

                return Array.isArray(condicoes) && condicoes.length > 0 ? condicoes.join('; ') : '—';
            },
        },
        { id: 'base_legal', header: 'Base legal', cell: (row) => (row as Quadro11Item).base_legal ?? '—' },
    ];
}

export default function LouosIndex({ quadros, quadroSelecionado, itens, filtros, perPageOptions }: LouosIndexProps) {
    const { auth } = usePage<SharedProps>().props;
    const canMaintain = auth.permissions.includes('manter-louos');

    const table = useServerTable({
        url: '/gestao/louos',
        initialSearch: filtros.search,
        initialSort: { column: 'natural', direction: 'asc' },
        initialPerPage: filtros.per_page,
        initialFilters: { quadro: filtros.quadro },
    });

    const [showPublish, setShowPublish] = useState(false);

    const selecionado = quadros.find((quadro) => quadro.quadro === quadroSelecionado) ?? quadros[0];
    const meta = QUADROS_META[quadroSelecionado] ?? QUADROS_META.quadro7;
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
                                <Button size="sm" onClick={() => setShowPublish(true)}>
                                    Publicar nova versão
                                </Button>
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
            </div>

            {canMaintain && showPublish && selecionado && (
                <PublishQuadroVersionModal
                    quadro={quadroSelecionado}
                    quadroLabel={selecionado.label}
                    onClose={() => setShowPublish(false)}
                    authUserId={auth.user?.id ?? null}
                />
            )}
        </>
    );
}

LouosIndex.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
