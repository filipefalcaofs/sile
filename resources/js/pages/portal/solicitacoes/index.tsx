import { Head, Link, router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import PageHeader from '@/components/app/page-header';
import Label from '@/components/form/label';
import { AlertIcon, EyeIcon, PencilIcon, TrashIcon } from '@/components/icons';
import Alert from '@/components/ui/alert';
import Badge from '@/components/ui/badge';
import Button from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import ConfirmDialog from '@/components/ui/confirm-dialog';
import DataTable from '@/components/ui/data-table/data-table';
import PerPageSelect from '@/components/ui/data-table/per-page-select';
import TableToolbar from '@/components/ui/data-table/table-toolbar';
import type { ColumnDef, SortDirection } from '@/components/ui/data-table/types';
import { useServerTable } from '@/components/ui/data-table/use-server-table';
import EmptyState from '@/components/ui/empty-state';
import Pagination, { type PaginationLink } from '@/components/ui/pagination';
import TableAction from '@/components/ui/table-action';
import PortalLayout from '@/layouts/portal-layout';

type BadgeColor = 'primary' | 'success' | 'error' | 'warning' | 'info' | 'light';

interface StatusInfo {
    value: string;
    label: string;
    public_label: string;
}

interface SolicitacaoRow {
    id: number;
    protocol_number: string | null;
    status: StatusInfo;
    service_type: string | null;
    company: { legal_name: string; formatted_cnpj: string } | null;
    created_at: string | null;
    editable: boolean;
    cancelable: boolean;
}

interface DuplicateAlert {
    request_id: number;
    protocol_number: string | null;
    status: string;
    created_at: string | null;
}

interface SolicitacoesIndexProps {
    solicitacoes: {
        data: SolicitacaoRow[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
    filters: {
        search: string;
        sort: string;
        direction: SortDirection;
        per_page: number;
    };
    perPageOptions: number[];
    solicitacaoEnabled: boolean;
    duplicateAlert: DuplicateAlert | null;
}

/** Cor do selo conforme o estado do processo (diferenciação visual, não estado). */
function statusColor(value: string): BadgeColor {
    switch (value) {
        case 'protocolada':
            return 'info';
        case 'deferida':
            return 'success';
        case 'indeferida':
        case 'cancelada':
            return 'error';
        case 'em_pendencia':
        case 'aguardando_bap':
            return 'warning';
        default:
            return 'light';
    }
}

/** Formata 'YYYY-MM-DD HH:MM:SS' como 'DD/MM/YYYY HH:MM' sem depender do fuso do navegador. */
function formatDateTime(value: string | null): string {
    if (!value) {
        return '—';
    }

    const match = value.match(/^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})/);

    if (!match) {
        return value;
    }

    const [, year, month, day, hour, minute] = match;

    return `${day}/${month}/${year} ${hour}:${minute}`;
}

export default function SolicitacoesIndex({
    solicitacoes,
    filters,
    perPageOptions,
    solicitacaoEnabled,
    duplicateAlert,
}: SolicitacoesIndexProps) {
    const table = useServerTable({
        url: '/portal/solicitacoes',
        initialSearch: filters.search,
        initialSort: { column: filters.sort, direction: filters.direction },
        initialPerPage: filters.per_page,
    });

    const filtering = table.search.trim() !== '';

    const [cancelTarget, setCancelTarget] = useState<SolicitacaoRow | null>(null);
    const [cancelReason, setCancelReason] = useState('');
    const [cancelProcessing, setCancelProcessing] = useState(false);

    function openCancel(solicitacao: SolicitacaoRow) {
        setCancelReason('');
        setCancelTarget(solicitacao);
    }

    function confirmCancel() {
        if (!cancelTarget || cancelReason.trim() === '') {
            return;
        }

        router.delete(`/portal/solicitacoes/${cancelTarget.id}`, {
            data: { reason: cancelReason.trim() },
            preserveScroll: true,
            onStart: () => setCancelProcessing(true),
            onSuccess: () => setCancelTarget(null),
            onFinish: () => setCancelProcessing(false),
        });
    }

    const columns: ColumnDef<SolicitacaoRow>[] = [
        {
            id: 'protocol_number',
            header: 'Protocolo',
            sortable: true,
            cell: (solicitacao) => (
                <div className="flex flex-col">
                    <span className="font-medium text-gray-800 dark:text-white/90">
                        {solicitacao.protocol_number ?? 'Em preenchimento'}
                    </span>
                    {solicitacao.service_type && (
                        <span className="text-theme-xs text-gray-500 dark:text-gray-400">
                            {solicitacao.service_type}
                        </span>
                    )}
                </div>
            ),
        },
        {
            id: 'company',
            header: 'Empresa',
            cell: (solicitacao) =>
                solicitacao.company ? (
                    <div className="flex flex-col">
                        <span className="text-gray-800 dark:text-white/90">{solicitacao.company.legal_name}</span>
                        <span className="text-theme-xs text-gray-500 dark:text-gray-400">
                            {solicitacao.company.formatted_cnpj}
                        </span>
                    </div>
                ) : (
                    <span className="text-gray-400 dark:text-gray-500">—</span>
                ),
        },
        {
            id: 'status',
            header: 'Situação',
            cell: (solicitacao) => (
                <Badge size="sm" color={statusColor(solicitacao.status.value)}>
                    {solicitacao.status.public_label}
                </Badge>
            ),
        },
        {
            id: 'created_at',
            header: 'Criada em',
            sortable: true,
            cellClassName: 'whitespace-nowrap',
            cell: (solicitacao) => (
                <span className="text-gray-600 dark:text-gray-300">{formatDateTime(solicitacao.created_at)}</span>
            ),
        },
        {
            id: 'actions',
            header: 'Ações',
            align: 'end',
            cellClassName: 'whitespace-nowrap',
            cell: (solicitacao) => (
                <div className="flex justify-end gap-1">
                    {solicitacao.status.value === 'em_pendencia' && (
                        <TableAction
                            tone="warning"
                            icon={<AlertIcon className="size-4.5" />}
                            label="Responder pendência"
                            title="Responder pendência"
                            href={`/portal/solicitacoes/${solicitacao.id}/pendencias`}
                        />
                    )}
                    {solicitacao.editable && (
                        <TableAction
                            tone="brand"
                            icon={<PencilIcon className="size-4.5" />}
                            label="Continuar preenchimento"
                            title="Continuar preenchimento"
                            href={`/portal/solicitacoes/${solicitacao.id}/editar`}
                        />
                    )}
                    <TableAction
                        tone="neutral"
                        icon={<EyeIcon className="size-4.5" />}
                        label="Consultar"
                        title="Consultar"
                        href={`/portal/solicitacoes/${solicitacao.id}`}
                    />
                    {solicitacao.cancelable && (
                        <TableAction
                            tone="error"
                            icon={<TrashIcon className="size-4.5" />}
                            label="Cancelar solicitação"
                            title="Cancelar solicitação"
                            onClick={() => openCancel(solicitacao)}
                        />
                    )}
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title="Minhas solicitações" />
            <PageHeader
                title="Minhas solicitações"
                breadcrumbs={[{ label: 'Meu painel', href: '/portal/painel' }]}
            />

            {duplicateAlert && (
                <div className="mb-6">
                    <Alert
                        variant="warning"
                        title="Já existe um processo recente para esta empresa"
                        message="Você pode prosseguir mesmo assim (é um direito seu). Se preferir, consulte o processo anterior antes de continuar."
                        showLink
                        linkHref={`/portal/solicitacoes/${duplicateAlert.request_id}`}
                        linkText={`Consultar ${duplicateAlert.protocol_number ?? 'processo anterior'}`}
                    />
                </div>
            )}

            {!solicitacaoEnabled && (
                <div className="mb-6">
                    <Alert
                        variant="info"
                        title="Novas solicitações temporariamente indisponíveis"
                        message="A abertura de novas solicitações de viabilidade está desativada no momento. Você ainda pode consultar e acompanhar as suas solicitações."
                    />
                </div>
            )}

            {solicitacoes.data.some((solicitacao) => solicitacao.status.value === 'em_pendencia') && (
                <div className="mb-6">
                    <Alert
                        variant="warning"
                        title="Você tem pendências aguardando resposta"
                        message="As solicitações em pendência estão destacadas abaixo. Use a ação Responder pendência na linha para enviar sua resposta e reabrir a análise."
                    />
                </div>
            )}

            <Card>
                <CardHeader
                    title="Solicitações de viabilidade"
                    description="Acompanhe, continue o preenchimento e protocole suas solicitações de viabilidade locacional."
                    actions={
                        solicitacaoEnabled ? (
                            <Link href="/portal/solicitacoes/nova">
                                <Button size="sm">Nova solicitação</Button>
                            </Link>
                        ) : undefined
                    }
                />
                <CardContent>
                    <div className="space-y-5">
                        <TableToolbar
                            search={{
                                value: table.search,
                                onChange: table.setSearch,
                                placeholder: 'Buscar por protocolo ou empresa...',
                                label: 'Buscar solicitações',
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
                            rows={solicitacoes.data}
                            rowKey={(solicitacao) => solicitacao.id}
                            sort={table.sort}
                            onSortChange={table.setSort}
                            loading={table.processing}
                            skeletonRows={6}
                            emptyState={
                                <EmptyState
                                    title={filtering ? 'Nenhuma solicitação encontrada' : 'Nenhuma solicitação ainda'}
                                    description={
                                        filtering
                                            ? 'Ajuste o termo da busca e tente novamente.'
                                            : 'Inicie uma nova solicitação de viabilidade para a sua empresa.'
                                    }
                                    action={
                                        !filtering && solicitacaoEnabled ? (
                                            <Link href="/portal/solicitacoes/nova">
                                                <Button size="sm">Nova solicitação</Button>
                                            </Link>
                                        ) : undefined
                                    }
                                />
                            }
                        />

                        <Pagination
                            links={solicitacoes.links}
                            meta={{ from: solicitacoes.from, to: solicitacoes.to, total: solicitacoes.total }}
                        />
                    </div>
                </CardContent>
            </Card>

            <ConfirmDialog
                isOpen={cancelTarget !== null}
                onClose={() => setCancelTarget(null)}
                onConfirm={confirmCancel}
                title="Cancelar solicitação"
                description={
                    <div className="flex flex-col gap-4 text-start">
                        <p>
                            O cancelamento encerra esta solicitação e fica registrado na trilha do processo. Informe o
                            motivo para concluir.
                        </p>
                        <div>
                            <Label htmlFor="cancel_reason" required>
                                Motivo do cancelamento
                            </Label>
                            <textarea
                                id="cancel_reason"
                                name="reason"
                                value={cancelReason}
                                maxLength={1000}
                                rows={3}
                                onChange={(event) => setCancelReason(event.target.value)}
                                placeholder="Ex.: solicitação criada por engano"
                                className="w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                            />
                        </div>
                    </div>
                }
                confirmLabel="Cancelar solicitação"
                cancelLabel="Voltar"
                variant="danger"
                processing={cancelProcessing}
            />
        </>
    );
}

SolicitacoesIndex.layout = (page: ReactNode) => <PortalLayout>{page}</PortalLayout>;
