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

interface ViaItem {
    id: number;
    codigo: string;
    nome: string;
    ativo: boolean;
}

interface ViasIndexProps {
    vias: ViaItem[];
}

function SituationBadge({ ativo }: { ativo: boolean }) {
    return (
        <Badge size="sm" color={ativo ? 'success' : 'warning'}>
            {ativo ? 'Ativa' : 'Inativa'}
        </Badge>
    );
}

function CreateViaModal({ onClose }: { onClose: () => void }) {
    const form = useForm({
        codigo: '',
        nome: '',
        ativo: true,
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        form.post('/gestao/louos/vias', {
            preserveScroll: true,
            onSuccess: () => onClose(),
        });
    }

    return (
        <Modal isOpen onClose={onClose} className="m-4 max-h-[90vh] max-w-[600px] overflow-y-auto p-6 lg:p-8">
            <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90">Nova via</h4>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                O código é exatamente o valor que as linhas do Quadro 11A da LOUOS referenciam e não poderá ser alterado depois. A via
                ativa passa a permitir a publicação de quadros que a referenciam.
            </p>

            <form onSubmit={handleSubmit} className="mt-6">
                <div className="flex flex-col gap-5">
                    <div>
                        <Label htmlFor="create-codigo">Código</Label>
                        <Input
                            id="create-codigo"
                            type="text"
                            name="codigo"
                            required
                            placeholder="VA I"
                            value={form.data.codigo}
                            onChange={(e) => form.setData('codigo', e.target.value)}
                            error={!!form.errors.codigo}
                            hint={form.errors.codigo}
                        />
                    </div>
                    <div>
                        <Label htmlFor="create-nome">Nome</Label>
                        <Input
                            id="create-nome"
                            type="text"
                            name="nome"
                            required
                            placeholder="Via Arterial I"
                            value={form.data.nome}
                            onChange={(e) => form.setData('nome', e.target.value)}
                            error={!!form.errors.nome}
                            hint={form.errors.nome}
                        />
                    </div>
                    <div className="flex items-center justify-end gap-3">
                        <Button size="sm" variant="outline" onClick={onClose} disabled={form.processing} type="button">
                            Cancelar
                        </Button>
                        <Button size="sm" type="submit" disabled={form.processing}>
                            {form.processing ? 'Salvando...' : 'Cadastrar via'}
                        </Button>
                    </div>
                </div>
            </form>
        </Modal>
    );
}

function EditViaModal({ via, onClose }: { via: ViaItem; onClose: () => void }) {
    const form = useForm({
        nome: via.nome,
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        form.put(`/gestao/louos/vias/${via.id}`, {
            preserveScroll: true,
            onSuccess: () => onClose(),
        });
    }

    return (
        <Modal isOpen onClose={onClose} className="m-4 max-h-[90vh] max-w-[600px] overflow-y-auto p-6 lg:p-8">
            <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90">Editar via</h4>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {via.codigo} — o código é fixo porque as linhas do Quadro 11A o referenciam; para trocar o código, desative esta via e
                cadastre a nova.
            </p>

            <form onSubmit={handleSubmit} className="mt-6">
                <div className="flex flex-col gap-5">
                    <div>
                        <Label htmlFor={`edit-nome-${via.id}`}>Nome</Label>
                        <Input
                            id={`edit-nome-${via.id}`}
                            type="text"
                            name="nome"
                            required
                            value={form.data.nome}
                            onChange={(e) => form.setData('nome', e.target.value)}
                            error={!!form.errors.nome}
                            hint={form.errors.nome}
                        />
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

export default function ViasIndex({ vias }: ViasIndexProps) {
    const { auth } = usePage<SharedProps>().props;
    const canMaintain = auth.permissions.includes('manter-louos');

    const [showCreate, setShowCreate] = useState(false);
    const [editing, setEditing] = useState<ViaItem | null>(null);
    const [pendingToggle, setPendingToggle] = useState<ViaItem | null>(null);
    const [actionProcessing, setActionProcessing] = useState(false);

    const columns: ColumnDef<ViaItem>[] = [
        {
            id: 'codigo',
            header: 'Código',
            cellClassName: 'whitespace-nowrap',
            cell: (via) => (
                <Badge size="sm" color="primary">
                    {via.codigo}
                </Badge>
            ),
        },
        {
            id: 'nome',
            header: 'Nome',
            cellClassName: 'font-medium text-gray-800 dark:text-white/90',
            cell: (via) => via.nome,
        },
        {
            id: 'ativo',
            header: 'Situação',
            cell: (via) => <SituationBadge ativo={via.ativo} />,
        },
        ...(canMaintain
            ? [
                  {
                      id: 'actions',
                      header: 'Ações',
                      align: 'end',
                      cellClassName: 'whitespace-nowrap',
                      cell: (via) => (
                          <div className="flex justify-end gap-2">
                              <TableAction
                                  tone="brand"
                                  icon={<PencilIcon className="size-4.5" />}
                                  label="Editar"
                                  onClick={() => setEditing(via)}
                              />
                              <TableAction
                                  tone={via.ativo ? 'warning' : 'success'}
                                  icon={<PowerIcon className="size-4.5" />}
                                  label={via.ativo ? 'Desativar' : 'Reativar'}
                                  onClick={() => setPendingToggle(via)}
                              />
                          </div>
                      ),
                  } satisfies ColumnDef<ViaItem>,
              ]
            : []),
    ];

    function executeToggle() {
        if (!pendingToggle) {
            return;
        }

        router.put(
            `/gestao/louos/vias/${pendingToggle.id}/ativacao`,
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
                  title: 'Desativar via',
                  description: `A via "${pendingToggle.codigo}" deixa de ser aceita em novas publicações do Quadro 11A. Os quadros vigentes que a referenciam são preservados. Confirmar a desativação?`,
                  confirmLabel: 'Desativar',
              }
            : {
                  variant: 'info' as const,
                  title: 'Reativar via',
                  description: `A via "${pendingToggle.codigo}" volta a ser aceita na publicação do Quadro 11A. Confirmar a reativação?`,
                  confirmLabel: 'Reativar',
              }
        : null;

    return (
        <>
            <Head title="Vias" />
            <PageHeader title="Vias" breadcrumbs={[{ label: 'Painel', href: '/gestao' }]} />

            <Card>
                <CardHeader
                    title="Classes de via da LOUOS"
                    description="Classes reconhecidas pelo Quadro 11A da Lei nº 9.148/2016. A publicação de um rascunho do Quadro 11A só é aceita quando toda classe referenciada consta aqui e está ativa — um erro de digitação vira bloqueio explícito, nunca leitura silenciosa da via."
                    actions={
                        canMaintain ? (
                            <Button size="sm" onClick={() => setShowCreate(true)}>
                                Nova via
                            </Button>
                        ) : undefined
                    }
                />
                <CardContent>
                    <DataTable
                        columns={columns}
                        rows={vias}
                        rowKey={(via) => via.id}
                        density="compact"
                        emptyState={
                            <EmptyState
                                title="Nenhuma via cadastrada"
                                description="As classes oficiais são carregadas pela carga inicial do sistema (seed), a partir do Quadro 11A vigente."
                                action={
                                    canMaintain ? (
                                        <Button size="sm" onClick={() => setShowCreate(true)}>
                                            Nova via
                                        </Button>
                                    ) : undefined
                                }
                            />
                        }
                    />
                </CardContent>
            </Card>

            {canMaintain && showCreate && <CreateViaModal onClose={() => setShowCreate(false)} />}

            {editing && <EditViaModal via={editing} onClose={() => setEditing(null)} />}

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

ViasIndex.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
