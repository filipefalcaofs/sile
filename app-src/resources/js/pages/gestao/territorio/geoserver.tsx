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

interface GeoServerLayerItem {
    id: number;
    workspace: string;
    type_name: string;
    nome_completo: string;
    label: string | null;
    ordem: number;
    ativo: boolean;
}

interface GeoServerLayersIndexProps {
    camadas: GeoServerLayerItem[];
}

function SituationBadge({ ativo }: { ativo: boolean }) {
    return (
        <Badge size="sm" color={ativo ? 'success' : 'warning'}>
            {ativo ? 'Ativa' : 'Inativa'}
        </Badge>
    );
}

function CreateLayerModal({ onClose }: { onClose: () => void }) {
    const form = useForm({
        workspace: '',
        type_name: '',
        label: '',
        ordem: 0,
        ativo: true,
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        form.post('/gestao/territorio/geoserver', {
            preserveScroll: true,
            onSuccess: () => onClose(),
        });
    }

    return (
        <Modal isOpen onClose={onClose} className="m-4 max-h-[90vh] max-w-[600px] overflow-y-auto p-6 lg:p-8">
            <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90">Nova camada do GeoServer</h4>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                O par workspace e nome da camada identifica a FeatureType no GeoServer e não poderá ser alterado depois. A camada ativa
                passa a ser consultada na identificação da zona na próxima chamada.
            </p>

            <form onSubmit={handleSubmit} className="mt-6">
                <div className="flex flex-col gap-5">
                    <div>
                        <Label htmlFor="create-workspace">Workspace</Label>
                        <Input
                            id="create-workspace"
                            type="text"
                            name="workspace"
                            required
                            placeholder="louos_zpr4"
                            value={form.data.workspace}
                            onChange={(e) => form.setData('workspace', e.target.value)}
                            error={!!form.errors.workspace}
                            hint={form.errors.workspace}
                        />
                    </div>
                    <div>
                        <Label htmlFor="create-type-name">Nome da camada (FeatureType)</Label>
                        <Input
                            id="create-type-name"
                            type="text"
                            name="type_name"
                            required
                            placeholder="VM_L_Z_USO_ZPR_4"
                            value={form.data.type_name}
                            onChange={(e) => form.setData('type_name', e.target.value)}
                            error={!!form.errors.type_name}
                            hint={form.errors.type_name}
                        />
                    </div>
                    <div>
                        <Label htmlFor="create-label">Rótulo (opcional)</Label>
                        <Input
                            id="create-label"
                            type="text"
                            name="label"
                            placeholder="ZPR-4"
                            value={form.data.label}
                            onChange={(e) => form.setData('label', e.target.value)}
                            error={!!form.errors.label}
                            hint={form.errors.label}
                        />
                    </div>
                    <div>
                        <Label htmlFor="create-ordem">Ordem de consulta</Label>
                        <Input
                            id="create-ordem"
                            type="number"
                            name="ordem"
                            required
                            min={0}
                            value={String(form.data.ordem)}
                            onChange={(e) => form.setData('ordem', Number(e.target.value))}
                            error={!!form.errors.ordem}
                            hint={form.errors.ordem}
                        />
                    </div>
                    <div className="flex items-center justify-end gap-3">
                        <Button size="sm" variant="outline" onClick={onClose} disabled={form.processing} type="button">
                            Cancelar
                        </Button>
                        <Button size="sm" type="submit" disabled={form.processing}>
                            {form.processing ? 'Salvando...' : 'Cadastrar camada'}
                        </Button>
                    </div>
                </div>
            </form>
        </Modal>
    );
}

function EditLayerModal({ camada, onClose }: { camada: GeoServerLayerItem; onClose: () => void }) {
    const form = useForm({
        label: camada.label ?? '',
        ordem: camada.ordem,
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        form.put(`/gestao/territorio/geoserver/${camada.id}`, {
            preserveScroll: true,
            onSuccess: () => onClose(),
        });
    }

    return (
        <Modal isOpen onClose={onClose} className="m-4 max-h-[90vh] max-w-[600px] overflow-y-auto p-6 lg:p-8">
            <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90">Editar camada do GeoServer</h4>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {camada.nome_completo} — o workspace e o nome da camada são fixos; para trocar a FeatureType, desative esta camada e
                cadastre a nova.
            </p>

            <form onSubmit={handleSubmit} className="mt-6">
                <div className="flex flex-col gap-5">
                    <div>
                        <Label htmlFor={`edit-label-${camada.id}`}>Rótulo (opcional)</Label>
                        <Input
                            id={`edit-label-${camada.id}`}
                            type="text"
                            name="label"
                            value={form.data.label}
                            onChange={(e) => form.setData('label', e.target.value)}
                            error={!!form.errors.label}
                            hint={form.errors.label}
                        />
                    </div>
                    <div>
                        <Label htmlFor={`edit-ordem-${camada.id}`}>Ordem de consulta</Label>
                        <Input
                            id={`edit-ordem-${camada.id}`}
                            type="number"
                            name="ordem"
                            required
                            min={0}
                            value={String(form.data.ordem)}
                            onChange={(e) => form.setData('ordem', Number(e.target.value))}
                            error={!!form.errors.ordem}
                            hint={form.errors.ordem}
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

export default function GeoServerLayersIndex({ camadas }: GeoServerLayersIndexProps) {
    const { auth } = usePage<SharedProps>().props;
    const canMaintain = auth.permissions.includes('manter-territorio');

    const [showCreate, setShowCreate] = useState(false);
    const [editing, setEditing] = useState<GeoServerLayerItem | null>(null);
    const [pendingToggle, setPendingToggle] = useState<GeoServerLayerItem | null>(null);
    const [actionProcessing, setActionProcessing] = useState(false);

    const columns: ColumnDef<GeoServerLayerItem>[] = [
        {
            id: 'ordem',
            header: 'Ordem',
            cellClassName: 'whitespace-nowrap',
            cell: (camada) => camada.ordem,
        },
        {
            id: 'workspace',
            header: 'Workspace',
            cellClassName: 'whitespace-nowrap',
            cell: (camada) => (
                <Badge size="sm" color="primary">
                    {camada.workspace}
                </Badge>
            ),
        },
        {
            id: 'type_name',
            header: 'Nome da camada',
            cellClassName: 'font-medium text-gray-800 dark:text-white/90',
            cell: (camada) => camada.type_name,
        },
        {
            id: 'label',
            header: 'Rótulo',
            cell: (camada) => camada.label ?? '—',
        },
        {
            id: 'ativo',
            header: 'Situação',
            cell: (camada) => <SituationBadge ativo={camada.ativo} />,
        },
        ...(canMaintain
            ? [
                  {
                      id: 'actions',
                      header: 'Ações',
                      align: 'end',
                      cellClassName: 'whitespace-nowrap',
                      cell: (camada) => (
                          <div className="flex justify-end gap-2">
                              <TableAction
                                  tone="brand"
                                  icon={<PencilIcon className="size-4.5" />}
                                  label="Editar"
                                  onClick={() => setEditing(camada)}
                              />
                              <TableAction
                                  tone={camada.ativo ? 'warning' : 'success'}
                                  icon={<PowerIcon className="size-4.5" />}
                                  label={camada.ativo ? 'Desativar' : 'Reativar'}
                                  onClick={() => setPendingToggle(camada)}
                              />
                          </div>
                      ),
                  } satisfies ColumnDef<GeoServerLayerItem>,
              ]
            : []),
    ];

    function executeToggle() {
        if (!pendingToggle) {
            return;
        }

        router.put(
            `/gestao/territorio/geoserver/${pendingToggle.id}/ativacao`,
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
                  title: 'Desativar camada do GeoServer',
                  description: `A camada "${pendingToggle.nome_completo}" deixa de ser consultada na identificação da zona a partir da próxima chamada. Confirmar a desativação?`,
                  confirmLabel: 'Desativar',
              }
            : {
                  variant: 'info' as const,
                  title: 'Reativar camada do GeoServer',
                  description: `A camada "${pendingToggle.nome_completo}" volta a ser consultada na identificação da zona. Confirmar a reativação?`,
                  confirmLabel: 'Reativar',
              }
        : null;

    return (
        <>
            <Head title="Camadas do GeoServer" />
            <PageHeader title="Camadas do GeoServer" breadcrumbs={[{ label: 'Painel', href: '/gestao' }]} />

            <Card>
                <CardHeader
                    title="Catálogo de camadas WFS"
                    description="FeatureTypes de zona da LOUOS consultadas no GeoServer da SEDUR na identificação territorial. Uma zona nova entra por cadastro, sem publicação de versão do sistema."
                    actions={
                        canMaintain ? (
                            <Button size="sm" onClick={() => setShowCreate(true)}>
                                Nova camada
                            </Button>
                        ) : undefined
                    }
                />
                <CardContent>
                    <DataTable
                        columns={columns}
                        rows={camadas}
                        rowKey={(camada) => camada.id}
                        density="compact"
                        emptyState={
                            <EmptyState
                                title="Nenhuma camada cadastrada"
                                description="As camadas oficiais são carregadas pela carga inicial do sistema (seed). Sem camadas ativas, a identificação da zona responde indisponível."
                                action={
                                    canMaintain ? (
                                        <Button size="sm" onClick={() => setShowCreate(true)}>
                                            Nova camada
                                        </Button>
                                    ) : undefined
                                }
                            />
                        }
                    />
                </CardContent>
            </Card>

            {canMaintain && showCreate && <CreateLayerModal onClose={() => setShowCreate(false)} />}

            {editing && <EditLayerModal camada={editing} onClose={() => setEditing(null)} />}

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

GeoServerLayersIndex.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
