import { Head, router, useForm, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import PageHeader from '@/components/app/page-header';
import Input from '@/components/form/input';
import Label from '@/components/form/label';
import { PencilIcon, PowerIcon } from '@/components/icons';
import Badge from '@/components/ui/badge';
import Button from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import ConfirmDialog from '@/components/ui/confirm-dialog';
import DataTable from '@/components/ui/data-table/data-table';
import type { ColumnDef } from '@/components/ui/data-table/types';
import EmptyState from '@/components/ui/empty-state';
import { Modal } from '@/components/ui/modal';
import TableAction from '@/components/ui/table-action';
import GestaoLayout from '@/layouts/gestao-layout';
import type { SharedProps } from '@/types';

interface RiskTriggerItem {
    id: number;
    codigo: string;
    codigo_label: string;
    titulo: string;
    motivo: string;
    ativo: boolean;
}

interface RiskTriggersIndexProps {
    gatilhos: RiskTriggerItem[];
}

function SituationBadge({ ativo }: { ativo: boolean }) {
    return (
        <Badge size="sm" color={ativo ? 'success' : 'warning'}>
            {ativo ? 'Ativo' : 'Inativo'}
        </Badge>
    );
}

function EditRiskTriggerModal({ gatilho, onClose }: { gatilho: RiskTriggerItem; onClose: () => void }) {
    const form = useForm({
        titulo: gatilho.titulo,
        motivo: gatilho.motivo,
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        form.put(`/gestao/gatilhos-risco/${gatilho.id}`, {
            preserveScroll: true,
            onSuccess: () => onClose(),
        });
    }

    return (
        <Modal isOpen onClose={onClose} className="m-4 max-h-[90vh] max-w-[600px] overflow-y-auto p-6 lg:p-8">
            <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90">Editar gatilho de risco</h4>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {gatilho.codigo_label} — o código é fixo e vinculado ao motor de regras; não pode ser alterado.
            </p>

            <form onSubmit={handleSubmit} className="mt-6">
                <div className="flex flex-col gap-5">
                    <div>
                        <Label htmlFor={`edit-titulo-${gatilho.id}`}>Título</Label>
                        <Input
                            id={`edit-titulo-${gatilho.id}`}
                            type="text"
                            name="titulo"
                            required
                            value={form.data.titulo}
                            onChange={(e) => form.setData('titulo', e.target.value)}
                            error={!!form.errors.titulo}
                            hint={form.errors.titulo}
                        />
                    </div>
                    <div>
                        <Label htmlFor={`edit-motivo-${gatilho.id}`}>Motivo registrado na decisão</Label>
                        <textarea
                            id={`edit-motivo-${gatilho.id}`}
                            rows={4}
                            required
                            value={form.data.motivo}
                            onChange={(e) => form.setData('motivo', e.target.value)}
                            className="w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                        />
                        {form.errors.motivo && <p className="mt-1.5 text-xs text-error-500">{form.errors.motivo}</p>}
                    </div>
                    <div className="flex items-center justify-end gap-3">
                        <Button size="sm" variant="outline" onClick={onClose} disabled={form.processing} type="button">
                            Cancelar
                        </Button>
                        <Button size="sm" type="submit" disabled={form.processing}>
                            {form.processing ? 'Salvando...' : 'Salvar alterações'}
                        </Button>
                    </div>
                </div>
            </form>
        </Modal>
    );
}

export default function RiskTriggersIndex({ gatilhos }: RiskTriggersIndexProps) {
    const { auth } = usePage<SharedProps>().props;
    const canMaintain = auth.permissions.includes('manter-gatilhos-risco');

    const [editing, setEditing] = useState<RiskTriggerItem | null>(null);
    const [pendingToggle, setPendingToggle] = useState<RiskTriggerItem | null>(null);
    const [actionProcessing, setActionProcessing] = useState(false);

    const columns: ColumnDef<RiskTriggerItem>[] = [
        {
            id: 'codigo',
            header: 'Código',
            cellClassName: 'whitespace-nowrap',
            cell: (gatilho) => (
                <Badge size="sm" color="primary">
                    {gatilho.codigo_label}
                </Badge>
            ),
        },
        {
            id: 'titulo',
            header: 'Título',
            cellClassName: 'font-medium text-gray-800 dark:text-white/90',
            cell: (gatilho) => gatilho.titulo,
        },
        {
            id: 'motivo',
            header: 'Motivo',
            cell: (gatilho) => (
                <span className="block max-w-md truncate" title={gatilho.motivo}>
                    {gatilho.motivo}
                </span>
            ),
        },
        {
            id: 'ativo',
            header: 'Situação',
            cell: (gatilho) => <SituationBadge ativo={gatilho.ativo} />,
        },
        ...(canMaintain
            ? [
                  {
                      id: 'actions',
                      header: 'Ações',
                      align: 'end',
                      cellClassName: 'whitespace-nowrap',
                      cell: (gatilho) => (
                          <div className="flex justify-end gap-2">
                              <TableAction
                                  tone="brand"
                                  icon={<PencilIcon className="size-4.5" />}
                                  label="Editar"
                                  onClick={() => setEditing(gatilho)}
                              />
                              <TableAction
                                  tone={gatilho.ativo ? 'warning' : 'success'}
                                  icon={<PowerIcon className="size-4.5" />}
                                  label={gatilho.ativo ? 'Desativar' : 'Reativar'}
                                  onClick={() => setPendingToggle(gatilho)}
                              />
                          </div>
                      ),
                  } satisfies ColumnDef<RiskTriggerItem>,
              ]
            : []),
    ];

    function executeToggle() {
        if (!pendingToggle) {
            return;
        }

        router.put(
            `/gestao/gatilhos-risco/${pendingToggle.id}/ativacao`,
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
        ? pendingToggle.ativo
            ? {
                  variant: 'warning' as const,
                  title: 'Desativar gatilho de risco',
                  description: `Processos que hoje caem neste gatilho passam a concluir automaticamente no fluxo expresso. Confirmar a desativação de "${pendingToggle.titulo}"?`,
                  confirmLabel: 'Desativar',
              }
            : {
                  variant: 'info' as const,
                  title: 'Reativar gatilho de risco',
                  description: `Confirma a reativação de "${pendingToggle.titulo}"? Os processos que caírem neste gatilho voltam a ser encaminhados à análise técnica.`,
                  confirmLabel: 'Reativar',
              }
        : null;

    return (
        <>
            <Head title="Gatilhos de risco" />
            <PageHeader title="Gatilhos de risco" breadcrumbs={[{ label: 'Painel', href: '/gestao' }]} />

            <Card>
                <CardHeader
                    title="Gatilhos semi-expresso"
                    description="Condições que enviam o processo à análise técnica em vez da conclusão automática no fluxo expresso. Desativar um gatilho muda o roteamento dos próximos processos."
                />
                <CardContent>
                    <DataTable
                        columns={columns}
                        rows={gatilhos}
                        rowKey={(gatilho) => gatilho.id}
                        density="compact"
                        emptyState={
                            <EmptyState
                                title="Nenhum gatilho cadastrado"
                                description="Os gatilhos conhecidos são carregados pela carga inicial do sistema (seed), vinculados ao motor de regras."
                            />
                        }
                    />
                </CardContent>
            </Card>

            {editing && <EditRiskTriggerModal gatilho={editing} onClose={() => setEditing(null)} />}

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

RiskTriggersIndex.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
