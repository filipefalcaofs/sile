import { Form, Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import Input from '@/components/form/input';
import Label from '@/components/form/label';
import { FileIcon } from '@/components/icons';
import Alert from '@/components/ui/alert';
import Badge from '@/components/ui/badge';
import Button from '@/components/ui/button';
import ConfirmDialog from '@/components/ui/confirm-dialog';
import EmptyState from '@/components/ui/empty-state';
import { Table, TableBody, TableCell, TableHeader, TableRow } from '@/components/ui/table';
import PortalLayout from '@/layouts/portal-layout';

interface ProcurationItem {
    id: number;
    name: string;
    email: string;
    starts_at: string;
    expires_at: string | null;
    revoked_at: string | null;
    is_active: boolean;
}

interface ProcuracoesIndexProps {
    granted: ProcurationItem[];
    received: ProcurationItem[];
    procuracoesEnabled: boolean;
}

const headerCellStyles = 'px-5 py-3 text-start text-theme-xs font-medium text-gray-500 dark:text-gray-400';

const bodyCellStyles = 'px-5 py-4 text-start text-theme-sm text-gray-500 dark:text-gray-400';

const actionButtonStyles =
    'inline-flex items-center justify-center rounded-lg px-3 py-2 text-theme-xs font-medium ring-1 ring-inset transition';

const brandActionStyles = `${actionButtonStyles} text-brand-500 ring-brand-200 hover:bg-brand-50 dark:text-brand-400 dark:ring-brand-500/30 dark:hover:bg-brand-500/10`;

const errorActionStyles = `${actionButtonStyles} text-error-600 ring-error-300 hover:bg-error-50 dark:text-error-400 dark:ring-error-500/30 dark:hover:bg-error-500/10`;

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString('pt-BR');
}

function situationLabel(item: ProcurationItem): string {
    if (item.revoked_at) {
        return 'Revogada';
    }

    return item.is_active ? 'Ativa' : 'Expirada';
}

function SituationBadge({ item }: { item: ProcurationItem }) {
    const label = situationLabel(item);
    const color = label === 'Ativa' ? 'success' : label === 'Revogada' ? 'error' : 'light';

    return (
        <Badge size="sm" color={color}>
            {label}
        </Badge>
    );
}

function PageBreadcrumb({ pageTitle }: { pageTitle: string }) {
    return (
        <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
            <h2 className="text-xl font-semibold text-gray-800 dark:text-white/90">{pageTitle}</h2>
            <nav aria-label="Trilha de navegação">
                <ol className="flex flex-wrap items-center gap-1.5">
                    <li>
                        <Link
                            className="inline-flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400"
                            href="/portal"
                        >
                            Portal
                            <svg
                                className="stroke-current"
                                width="17"
                                height="16"
                                viewBox="0 0 17 16"
                                fill="none"
                                xmlns="http://www.w3.org/2000/svg"
                            >
                                <path
                                    d="M6.0765 12.667L10.2432 8.50033L6.0765 4.33366"
                                    strokeWidth="1.2"
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                />
                            </svg>
                        </Link>
                    </li>
                    <li className="text-sm text-gray-800 dark:text-white/90">Procurações</li>
                </ol>
            </nav>
        </div>
    );
}

function GrantProcurationCard() {
    return (
        <div className="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div className="px-6 py-5">
                <h3 className="text-base font-medium text-gray-800 dark:text-white/90">
                    Vincular procurador
                </h3>
                <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    O procurador precisa ter conta no Simplifica. Informe o e-mail cadastrado e, se
                    desejar, uma data de validade para a procuração.
                </p>
            </div>
            <div className="border-t border-gray-100 p-4 dark:border-gray-800 sm:p-6">
                <Form action="/portal/procuracoes" method="post">
                    {({ errors, processing }) => (
                        <div className="flex flex-col gap-5 sm:flex-row sm:items-end">
                            <div className="flex-1">
                                <Label htmlFor="attorney_email">E-mail do procurador</Label>
                                <Input
                                    id="attorney_email"
                                    type="email"
                                    name="attorney_email"
                                    required
                                    error={!!errors.attorney_email}
                                    hint={errors.attorney_email}
                                />
                            </div>
                            <div>
                                <Label htmlFor="expires_at">Validade (opcional)</Label>
                                <Input
                                    id="expires_at"
                                    type="date"
                                    name="expires_at"
                                    error={!!errors.expires_at}
                                    hint={errors.expires_at}
                                />
                            </div>
                            <div>
                                <Button size="sm" type="submit" disabled={processing}>
                                    {processing ? 'Vinculando...' : 'Vincular'}
                                </Button>
                            </div>
                        </div>
                    )}
                </Form>
            </div>
        </div>
    );
}

function focusGrantForm() {
    const input = document.getElementById('attorney_email');

    if (input instanceof HTMLInputElement) {
        input.scrollIntoView({ behavior: 'smooth', block: 'center' });
        input.focus({ preventScroll: true });
    }
}

function GrantedTable({
    granted,
    canGrant,
    onRevoke,
}: {
    granted: ProcurationItem[];
    canGrant: boolean;
    onRevoke: (item: ProcurationItem) => void;
}) {
    if (granted.length === 0) {
        return (
            <EmptyState
                icon={<FileIcon className="size-7" aria-hidden="true" />}
                title="Nenhuma procuração outorgada"
                description="Você ainda não vinculou um procurador para atuar em seu nome."
                action={
                    canGrant ? (
                        <Button size="sm" variant="outline" onClick={focusGrantForm}>
                            Vincular procurador
                        </Button>
                    ) : undefined
                }
            />
        );
    }

    return (
        <div className="overflow-hidden rounded-xl border border-gray-200 dark:border-white/[0.05]">
            <div className="max-w-full overflow-x-auto">
                <Table>
                    <TableHeader className="border-b border-gray-100 dark:border-white/[0.05]">
                        <TableRow>
                            <TableCell isHeader className={headerCellStyles}>
                                Procurador
                            </TableCell>
                            <TableCell isHeader className={headerCellStyles}>
                                E-mail
                            </TableCell>
                            <TableCell isHeader className={headerCellStyles}>
                                Início
                            </TableCell>
                            <TableCell isHeader className={headerCellStyles}>
                                Validade
                            </TableCell>
                            <TableCell isHeader className={headerCellStyles}>
                                Situação
                            </TableCell>
                            <TableCell isHeader className={headerCellStyles}>
                                Ações
                            </TableCell>
                        </TableRow>
                    </TableHeader>
                    <TableBody className="divide-y divide-gray-100 dark:divide-white/[0.05]">
                        {granted.map((item) => (
                            <TableRow
                                key={item.id}
                                className="transition hover:bg-gray-50 dark:hover:bg-white/[0.03]"
                            >
                                <TableCell className="px-5 py-4 text-start text-theme-sm font-medium text-gray-800 dark:text-white/90">
                                    {item.name}
                                </TableCell>
                                <TableCell className={bodyCellStyles}>{item.email}</TableCell>
                                <TableCell className={`${bodyCellStyles} whitespace-nowrap`}>
                                    {formatDate(item.starts_at)}
                                </TableCell>
                                <TableCell className={`${bodyCellStyles} whitespace-nowrap`}>
                                    {formatDate(item.expires_at)}
                                </TableCell>
                                <TableCell className={bodyCellStyles}>
                                    <SituationBadge item={item} />
                                </TableCell>
                                <TableCell className={bodyCellStyles}>
                                    {item.is_active ? (
                                        <button
                                            type="button"
                                            onClick={() => onRevoke(item)}
                                            className={errorActionStyles}
                                        >
                                            Revogar
                                        </button>
                                    ) : (
                                        <span className="text-gray-400 dark:text-gray-500">—</span>
                                    )}
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>
        </div>
    );
}

function ReceivedTable({ received }: { received: ProcurationItem[] }) {
    if (received.length === 0) {
        return (
            <EmptyState
                icon={<FileIcon className="size-7" aria-hidden="true" />}
                title="Nenhuma procuração recebida"
                description="Quando um interessado vincular você como procurador, o registro aparecerá aqui."
            />
        );
    }

    return (
        <div className="overflow-hidden rounded-xl border border-gray-200 dark:border-white/[0.05]">
            <div className="max-w-full overflow-x-auto">
                <Table>
                    <TableHeader className="border-b border-gray-100 dark:border-white/[0.05]">
                        <TableRow>
                            <TableCell isHeader className={headerCellStyles}>
                                Outorgante
                            </TableCell>
                            <TableCell isHeader className={headerCellStyles}>
                                E-mail
                            </TableCell>
                            <TableCell isHeader className={headerCellStyles}>
                                Situação
                            </TableCell>
                            <TableCell isHeader className={headerCellStyles}>
                                Ações
                            </TableCell>
                        </TableRow>
                    </TableHeader>
                    <TableBody className="divide-y divide-gray-100 dark:divide-white/[0.05]">
                        {received.map((item) => (
                            <TableRow
                                key={item.id}
                                className="transition hover:bg-gray-50 dark:hover:bg-white/[0.03]"
                            >
                                <TableCell className="px-5 py-4 text-start text-theme-sm font-medium text-gray-800 dark:text-white/90">
                                    {item.name}
                                </TableCell>
                                <TableCell className={bodyCellStyles}>{item.email}</TableCell>
                                <TableCell className={bodyCellStyles}>
                                    <SituationBadge item={item} />
                                </TableCell>
                                <TableCell className={bodyCellStyles}>
                                    {item.is_active ? (
                                        <Link
                                            href="/portal/representacao"
                                            method="post"
                                            data={{ procuration_id: item.id }}
                                            as="button"
                                            className={brandActionStyles}
                                        >
                                            Atuar em nome de
                                        </Link>
                                    ) : (
                                        <span className="text-gray-400 dark:text-gray-500">—</span>
                                    )}
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>
        </div>
    );
}

export default function ProcuracoesIndex({ granted, received, procuracoesEnabled }: ProcuracoesIndexProps) {
    const [revokeTarget, setRevokeTarget] = useState<ProcurationItem | null>(null);
    const [revoking, setRevoking] = useState(false);

    function closeRevokeDialog() {
        if (!revoking) {
            setRevokeTarget(null);
        }
    }

    function confirmRevoke() {
        if (!revokeTarget) {
            return;
        }

        router.delete(`/portal/procuracoes/${revokeTarget.id}`, {
            onStart: () => setRevoking(true),
            onFinish: () => {
                setRevoking(false);
                setRevokeTarget(null);
            },
        });
    }

    return (
        <PortalLayout>
            <Head title="Minhas procurações" />
            <PageBreadcrumb pageTitle="Minhas procurações" />

            <div className="flex flex-col gap-4 md:gap-6">
                {!procuracoesEnabled && (
                    <Alert
                        variant="warning"
                        title="Funcionalidade desativada"
                        message="A funcionalidade de procurações está temporariamente desativada pelo administrador. Vínculos existentes permanecem visíveis e revogáveis."
                    />
                )}

                {procuracoesEnabled && <GrantProcurationCard />}

                <div className="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                    <div className="px-6 py-5">
                        <h3 className="text-base font-medium text-gray-800 dark:text-white/90">
                            Procurações outorgadas
                        </h3>
                    </div>
                    <div className="border-t border-gray-100 p-4 dark:border-gray-800 sm:p-6">
                        <GrantedTable
                            granted={granted}
                            canGrant={procuracoesEnabled}
                            onRevoke={setRevokeTarget}
                        />
                    </div>
                </div>

                <div className="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                    <div className="px-6 py-5">
                        <h3 className="text-base font-medium text-gray-800 dark:text-white/90">
                            Procurações recebidas
                        </h3>
                    </div>
                    <div className="border-t border-gray-100 p-4 dark:border-gray-800 sm:p-6">
                        <ReceivedTable received={received} />
                    </div>
                </div>
            </div>

            <ConfirmDialog
                isOpen={revokeTarget !== null}
                onClose={closeRevokeDialog}
                onConfirm={confirmRevoke}
                title="Revogar procuração"
                description={
                    revokeTarget ? (
                        <>
                            A procuração outorgada a{' '}
                            <span className="font-medium text-gray-800 dark:text-white/90">
                                {revokeTarget.name}
                            </span>{' '}
                            será revogada imediatamente e o procurador deixará de poder atuar em
                            seu nome.
                        </>
                    ) : (
                        ''
                    )
                }
                confirmLabel="Revogar"
                variant="danger"
                processing={revoking}
            />
        </PortalLayout>
    );
}
