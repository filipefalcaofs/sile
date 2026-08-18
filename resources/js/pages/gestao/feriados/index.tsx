import { Form, Head, router, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import PageHeader from '@/components/app/page-header';
import Input from '@/components/form/input';
import Label from '@/components/form/label';
import Select from '@/components/form/select';
import { AlertIcon, PencilIcon, PowerIcon } from '@/components/icons';
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

interface HolidayItem {
    id: number;
    /** Data ISO (YYYY-MM-DD) ou null em registro inconsistente. */
    date: string | null;
    name: string;
    recurring_annually: boolean;
    active: boolean;
}

interface HolidaysIndexProps {
    holidays: {
        data: HolidayItem[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
    filters: {
        search: string;
        per_page: number;
        active: string;
    };
    perPageOptions: number[];
}

const URL_FERIADOS = '/gestao/feriados';

const SITUACAO_OPTIONS = [
    { value: '1', label: 'Ativo' },
    { value: '0', label: 'Inativo' },
];

const RECORRENCIA_OPTIONS = [
    { value: '0', label: 'Não (data fixa)' },
    { value: '1', label: 'Sim (todo ano)' },
];

/** Formata a data ISO (YYYY-MM-DD) no padrão brasileiro dd/mm/aaaa. */
function formatarData(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    const partes = iso.split('-');

    return partes.length === 3 ? `${partes[2]}/${partes[1]}/${partes[0]}` : iso;
}

function SituationBadge({ active }: { active: boolean }) {
    return (
        <Badge size="sm" color={active ? 'success' : 'warning'}>
            {active ? 'Ativo' : 'Inativo'}
        </Badge>
    );
}

function CreateHolidayModal({ isOpen, onClose }: { isOpen: boolean; onClose: () => void }) {
    const [recurring, setRecurring] = useState('0');
    const [active, setActive] = useState('1');

    return (
        <Modal isOpen={isOpen} onClose={onClose} className="m-4 max-h-[90vh] max-w-[560px] overflow-y-auto p-6 lg:p-8">
            <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90">Novo feriado</h4>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                O feriado entra no cálculo de dias úteis (HU-129). A data é única — não há feriado duplicado.
            </p>

            <Form action={URL_FERIADOS} method="post" resetOnSuccess onSuccess={onClose} className="mt-6">
                {({ errors, processing }) => (
                    <div className="flex flex-col gap-5">
                        <div>
                            <Label htmlFor="create-holiday-date">Data</Label>
                            <Input id="create-holiday-date" type="date" name="date" required error={!!errors.date} hint={errors.date} />
                        </div>
                        <div>
                            <Label htmlFor="create-holiday-name">Nome</Label>
                            <Input
                                id="create-holiday-name"
                                type="text"
                                name="name"
                                required
                                placeholder="Ex.: Dia de Santa Bárbara"
                                error={!!errors.name}
                                hint={errors.name}
                            />
                        </div>
                        <div>
                            <Label htmlFor="create-holiday-recurring">Recorrência anual</Label>
                            <Select
                                id="create-holiday-recurring"
                                name="recurring_annually"
                                value={recurring}
                                onChange={setRecurring}
                                options={RECORRENCIA_OPTIONS}
                            />
                        </div>
                        <div>
                            <Label htmlFor="create-holiday-active">Situação</Label>
                            <Select id="create-holiday-active" name="active" value={active} onChange={setActive} options={SITUACAO_OPTIONS} />
                        </div>
                        <div className="flex items-center justify-end gap-3">
                            <Button size="sm" variant="outline" onClick={onClose} disabled={processing}>
                                Cancelar
                            </Button>
                            <Button size="sm" type="submit" disabled={processing}>
                                {processing ? 'Salvando...' : 'Salvar'}
                            </Button>
                        </div>
                    </div>
                )}
            </Form>
        </Modal>
    );
}

function EditHolidayModal({ holiday, onClose }: { holiday: HolidayItem; onClose: () => void }) {
    const [recurring, setRecurring] = useState(holiday.recurring_annually ? '1' : '0');
    const [active, setActive] = useState(holiday.active ? '1' : '0');

    return (
        <Modal isOpen onClose={onClose} className="m-4 max-h-[90vh] max-w-[560px] overflow-y-auto p-6 lg:p-8">
            <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90">Editar feriado</h4>

            <Form action={`${URL_FERIADOS}/${holiday.id}`} method="put" onSuccess={onClose} className="mt-6">
                {({ errors, processing }) => (
                    <div className="flex flex-col gap-5">
                        <div>
                            <Label htmlFor={`edit-holiday-date-${holiday.id}`}>Data</Label>
                            <Input
                                id={`edit-holiday-date-${holiday.id}`}
                                type="date"
                                name="date"
                                defaultValue={holiday.date ?? ''}
                                required
                                error={!!errors.date}
                                hint={errors.date}
                            />
                        </div>
                        <div>
                            <Label htmlFor={`edit-holiday-name-${holiday.id}`}>Nome</Label>
                            <Input
                                id={`edit-holiday-name-${holiday.id}`}
                                type="text"
                                name="name"
                                defaultValue={holiday.name}
                                required
                                error={!!errors.name}
                                hint={errors.name}
                            />
                        </div>
                        <div>
                            <Label htmlFor={`edit-holiday-recurring-${holiday.id}`}>Recorrência anual</Label>
                            <Select
                                id={`edit-holiday-recurring-${holiday.id}`}
                                name="recurring_annually"
                                value={recurring}
                                onChange={setRecurring}
                                options={RECORRENCIA_OPTIONS}
                            />
                        </div>
                        <div>
                            <Label htmlFor={`edit-holiday-active-${holiday.id}`}>Situação</Label>
                            <Select
                                id={`edit-holiday-active-${holiday.id}`}
                                name="active"
                                value={active}
                                onChange={setActive}
                                options={SITUACAO_OPTIONS}
                            />
                            {errors.active && <p className="mt-1.5 text-theme-xs text-error-500">{errors.active}</p>}
                        </div>
                        <div className="flex items-center justify-end gap-3">
                            <Button size="sm" variant="outline" onClick={onClose} disabled={processing}>
                                Cancelar
                            </Button>
                            <Button size="sm" type="submit" disabled={processing}>
                                {processing ? 'Salvando...' : 'Salvar alterações'}
                            </Button>
                        </div>
                    </div>
                )}
            </Form>
        </Modal>
    );
}

export default function HolidaysIndex({ holidays, filters, perPageOptions }: HolidaysIndexProps) {
    const { auth } = usePage<SharedProps>().props;
    const canMaintain = auth.permissions.includes('manter-parametros');

    const table = useServerTable({
        url: URL_FERIADOS,
        initialSearch: filters.search,
        // O backend ordena por data desc fixo (sem ordenação por coluna); nenhuma
        // coluna é sortable para não oferecer um controle que não faz nada.
        initialSort: { column: 'date', direction: 'desc' },
        initialPerPage: filters.per_page,
        initialFilters: { active: filters.active },
    });

    const [showCreate, setShowCreate] = useState(false);
    const [editing, setEditing] = useState<HolidayItem | null>(null);
    const [pendingToggle, setPendingToggle] = useState<HolidayItem | null>(null);
    const [actionProcessing, setActionProcessing] = useState(false);

    const filtering = table.search.trim() !== '' || table.filters.active !== '';

    const columns: ColumnDef<HolidayItem>[] = [
        {
            id: 'date',
            header: 'Data',
            cellClassName: 'font-medium whitespace-nowrap text-gray-800 dark:text-white/90',
            cell: (holiday) => formatarData(holiday.date),
        },
        {
            id: 'name',
            header: 'Nome',
            cellClassName: 'text-gray-800 dark:text-white/90',
            cell: (holiday) => holiday.name,
        },
        {
            id: 'recurring_annually',
            header: 'Recorrente',
            align: 'center',
            cell: (holiday) => (
                <Badge color={holiday.recurring_annually ? 'info' : 'light'} size="sm">
                    {holiday.recurring_annually ? 'Todo ano' : 'Data fixa'}
                </Badge>
            ),
        },
        {
            id: 'active',
            header: 'Situação',
            cell: (holiday) => <SituationBadge active={holiday.active} />,
        },
        ...(canMaintain
            ? [
                  {
                      id: 'actions',
                      header: 'Ações',
                      align: 'end',
                      cellClassName: 'whitespace-nowrap',
                      cell: (holiday) => (
                          <div className="flex justify-end gap-2">
                              <TableAction
                                  tone="brand"
                                  icon={<PencilIcon className="size-4.5" />}
                                  label="Editar"
                                  onClick={() => setEditing(holiday)}
                              />
                              <TableAction
                                  tone={holiday.active ? 'warning' : 'success'}
                                  icon={<PowerIcon className="size-4.5" />}
                                  label={holiday.active ? 'Inativar' : 'Reativar'}
                                  onClick={() => setPendingToggle(holiday)}
                              />
                          </div>
                      ),
                  } satisfies ColumnDef<HolidayItem>,
              ]
            : []),
    ];

    function executeToggle() {
        if (!pendingToggle) {
            return;
        }

        router.put(
            `${URL_FERIADOS}/${pendingToggle.id}/ativacao`,
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
                  title: 'Inativar feriado',
                  description: `Confirma a inativação de "${pendingToggle.name}"? Ele deixa de ser descontado no cálculo de dias úteis, mas o histórico é preservado (RN-004).`,
                  confirmLabel: 'Inativar',
              }
            : {
                  variant: 'info' as const,
                  title: 'Reativar feriado',
                  description: `Confirma a reativação de "${pendingToggle.name}"? Ele volta a ser descontado no cálculo de dias úteis.`,
                  confirmLabel: 'Reativar',
              }
        : null;

    return (
        <>
            <Head title="Feriados" />
            <PageHeader title="Feriados" breadcrumbs={[{ label: 'Painel', href: '/gestao' }]} />

            <div className="space-y-4 md:space-y-6">
                <RessalvaListaOficial />

                <Card>
                    <CardHeader
                        title="Calendário de feriados"
                        description="Dado administrável que o cálculo de tempo de análise desconta como dia não útil (HU-129). A data é única; feriados não são excluídos, apenas inativados (RN-004)."
                        actions={
                            canMaintain ? (
                                <Button size="sm" onClick={() => setShowCreate(true)}>
                                    Novo feriado
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
                                    placeholder: 'Buscar por nome...',
                                    label: 'Buscar feriados',
                                }}
                                filters={
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
                                }
                                actions={<PerPageSelect value={table.perPage} options={perPageOptions} onChange={table.setPerPage} />}
                            />

                            {table.filters.active !== '' && (
                                <div className="flex flex-wrap items-center gap-2">
                                    <Badge size="sm" color="light">
                                        Situação: {table.filters.active === '1' ? 'Ativos' : 'Inativos'}
                                    </Badge>
                                    <button
                                        type="button"
                                        onClick={() => table.setFilter('active', '')}
                                        className="text-theme-xs font-medium text-brand-500 transition hover:text-brand-600 dark:text-brand-400"
                                    >
                                        Limpar filtro
                                    </button>
                                </div>
                            )}

                            <DataTable
                                columns={columns}
                                rows={holidays.data}
                                rowKey={(holiday) => holiday.id}
                                loading={table.processing}
                                skeletonRows={8}
                                density="compact"
                                emptyState={
                                    <EmptyState
                                        title={filtering ? 'Nenhum resultado para a busca' : 'Nenhum feriado cadastrado'}
                                        description={
                                            filtering
                                                ? 'Ajuste o termo de busca ou os filtros e tente novamente.'
                                                : 'Cadastre o primeiro feriado para refinar o cálculo de dias úteis.'
                                        }
                                        action={
                                            !filtering && canMaintain ? (
                                                <Button size="sm" onClick={() => setShowCreate(true)}>
                                                    Novo feriado
                                                </Button>
                                            ) : undefined
                                        }
                                    />
                                }
                            />

                            <Pagination links={holidays.links} meta={{ from: holidays.from, to: holidays.to, total: holidays.total }} />
                        </div>
                    </CardContent>
                </Card>
            </div>

            {canMaintain && <CreateHolidayModal isOpen={showCreate} onClose={() => setShowCreate(false)} />}

            {editing && <EditHolidayModal holiday={editing} onClose={() => setEditing(null)} />}

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
        </>
    );
}

/**
 * Ressalva honesta (HU-137): a lista oficial de feriados municipais de Salvador
 * é pendência da SEDUR. Até a carga oficial, o cálculo de dias úteis desconta
 * apenas os feriados cadastrados aqui — nenhum feriado é inventado.
 */
function RessalvaListaOficial() {
    return (
        <div className="flex items-start gap-3 rounded-2xl border border-warning-200 bg-warning-50 p-4 dark:border-warning-500/30 dark:bg-warning-500/10">
            <AlertIcon className="mt-0.5 size-5 shrink-0 text-warning-600 dark:text-orange-400" />
            <p className="text-theme-sm text-warning-700 dark:text-orange-300">
                A lista <strong>oficial</strong> de feriados municipais de Salvador ainda é pendência da SEDUR. Enquanto a carga oficial não
                chega, o cálculo de dias úteis desconta apenas os feriados cadastrados nesta tela — nenhum feriado é presumido.
            </p>
        </div>
    );
}

HolidaysIndex.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
