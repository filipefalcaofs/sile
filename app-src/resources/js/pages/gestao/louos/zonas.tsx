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

interface ZonaItem {
    id: number;
    codigo: string;
    nome: string;
    macrozona: string | null;
    ativo: boolean;
}

interface ZonasIndexProps {
    zonas: ZonaItem[];
}

function SituationBadge({ ativo }: { ativo: boolean }) {
    return (
        <Badge size="sm" color={ativo ? 'success' : 'warning'}>
            {ativo ? 'Ativa' : 'Inativa'}
        </Badge>
    );
}

function CreateZonaModal({ onClose }: { onClose: () => void }) {
    const form = useForm({
        codigo: '',
        nome: '',
        macrozona: '',
        ativo: true,
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        form.post('/gestao/louos/zonas', {
            preserveScroll: true,
            onSuccess: () => onClose(),
        });
    }

    return (
        <Modal isOpen onClose={onClose} className="m-4 max-h-[90vh] max-w-[600px] overflow-y-auto p-6 lg:p-8">
            <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90">Nova zona</h4>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                O código é exatamente o valor que as linhas do Quadro 10 da LOUOS referenciam e não poderá ser alterado depois. A zona
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
                            placeholder="ZPR 4"
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
                            placeholder="Zona Preferencial Residencial 4"
                            value={form.data.nome}
                            onChange={(e) => form.setData('nome', e.target.value)}
                            error={!!form.errors.nome}
                            hint={form.errors.nome}
                        />
                    </div>
                    <div>
                        <Label htmlFor="create-macrozona">Macrozona (opcional)</Label>
                        <Input
                            id="create-macrozona"
                            type="text"
                            name="macrozona"
                            placeholder="Macrozona Norte"
                            value={form.data.macrozona}
                            onChange={(e) => form.setData('macrozona', e.target.value)}
                            error={!!form.errors.macrozona}
                            hint={form.errors.macrozona}
                        />
                    </div>
                    <div className="flex items-center justify-end gap-3">
                        <Button size="sm" variant="outline" onClick={onClose} disabled={form.processing} type="button">
                            Cancelar
                        </Button>
                        <Button size="sm" type="submit" disabled={form.processing}>
                            {form.processing ? 'Salvando...' : 'Cadastrar zona'}
                        </Button>
                    </div>
                </div>
            </form>
        </Modal>
    );
}

function EditZonaModal({ zona, onClose }: { zona: ZonaItem; onClose: () => void }) {
    const form = useForm({
        nome: zona.nome,
        macrozona: zona.macrozona ?? '',
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        form.put(`/gestao/louos/zonas/${zona.id}`, {
            preserveScroll: true,
            onSuccess: () => onClose(),
        });
    }

    return (
        <Modal isOpen onClose={onClose} className="m-4 max-h-[90vh] max-w-[600px] overflow-y-auto p-6 lg:p-8">
            <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90">Editar zona</h4>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {zona.codigo} — o código é fixo porque as linhas do Quadro 10 o referenciam; para trocar o código, desative esta zona e
                cadastre a nova.
            </p>

            <form onSubmit={handleSubmit} className="mt-6">
                <div className="flex flex-col gap-5">
                    <div>
                        <Label htmlFor={`edit-nome-${zona.id}`}>Nome</Label>
                        <Input
                            id={`edit-nome-${zona.id}`}
                            type="text"
                            name="nome"
                            required
                            value={form.data.nome}
                            onChange={(e) => form.setData('nome', e.target.value)}
                            error={!!form.errors.nome}
                            hint={form.errors.nome}
                        />
                    </div>
                    <div>
                        <Label htmlFor={`edit-macrozona-${zona.id}`}>Macrozona (opcional)</Label>
                        <Input
                            id={`edit-macrozona-${zona.id}`}
                            type="text"
                            name="macrozona"
                            value={form.data.macrozona}
                            onChange={(e) => form.setData('macrozona', e.target.value)}
                            error={!!form.errors.macrozona}
                            hint={form.errors.macrozona}
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

export default function ZonasIndex({ zonas }: ZonasIndexProps) {
    const { auth } = usePage<SharedProps>().props;
    const canMaintain = auth.permissions.includes('manter-louos');

    const [showCreate, setShowCreate] = useState(false);
    const [editing, setEditing] = useState<ZonaItem | null>(null);
    const [pendingToggle, setPendingToggle] = useState<ZonaItem | null>(null);
    const [actionProcessing, setActionProcessing] = useState(false);

    const columns: ColumnDef<ZonaItem>[] = [
        {
            id: 'codigo',
            header: 'Código',
            cellClassName: 'whitespace-nowrap',
            cell: (zona) => (
                <Badge size="sm" color="primary">
                    {zona.codigo}
                </Badge>
            ),
        },
        {
            id: 'nome',
            header: 'Nome',
            cellClassName: 'font-medium text-gray-800 dark:text-white/90',
            cell: (zona) => zona.nome,
        },
        {
            id: 'macrozona',
            header: 'Macrozona',
            cell: (zona) => zona.macrozona ?? '—',
        },
        {
            id: 'ativo',
            header: 'Situação',
            cell: (zona) => <SituationBadge ativo={zona.ativo} />,
        },
        ...(canMaintain
            ? [
                  {
                      id: 'actions',
                      header: 'Ações',
                      align: 'end',
                      cellClassName: 'whitespace-nowrap',
                      cell: (zona) => (
                          <div className="flex justify-end gap-2">
                              <TableAction
                                  tone="brand"
                                  icon={<PencilIcon className="size-4.5" />}
                                  label="Editar"
                                  onClick={() => setEditing(zona)}
                              />
                              <TableAction
                                  tone={zona.ativo ? 'warning' : 'success'}
                                  icon={<PowerIcon className="size-4.5" />}
                                  label={zona.ativo ? 'Desativar' : 'Reativar'}
                                  onClick={() => setPendingToggle(zona)}
                              />
                          </div>
                      ),
                  } satisfies ColumnDef<ZonaItem>,
              ]
            : []),
    ];

    function executeToggle() {
        if (!pendingToggle) {
            return;
        }

        router.put(
            `/gestao/louos/zonas/${pendingToggle.id}/ativacao`,
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
                  title: 'Desativar zona',
                  description: `A zona "${pendingToggle.codigo}" deixa de ser aceita em novas publicações do Quadro 10. Os quadros vigentes que a referenciam são preservados. Confirmar a desativação?`,
                  confirmLabel: 'Desativar',
              }
            : {
                  variant: 'info' as const,
                  title: 'Reativar zona',
                  description: `A zona "${pendingToggle.codigo}" volta a ser aceita na publicação do Quadro 10. Confirmar a reativação?`,
                  confirmLabel: 'Reativar',
              }
        : null;

    return (
        <>
            <Head title="Zonas" />
            <PageHeader title="Zonas" breadcrumbs={[{ label: 'Painel', href: '/gestao' }]} />

            <Card>
                <CardHeader
                    title="Zonas urbanísticas da LOUOS"
                    description="Zonas reconhecidas pelo Quadro 10 da Lei nº 9.148/2016. A publicação de um rascunho do Quadro 10 só é aceita quando toda zona referenciada consta aqui e está ativa — um erro de digitação vira bloqueio explícito, nunca processo pendente silencioso."
                    actions={
                        canMaintain ? (
                            <Button size="sm" onClick={() => setShowCreate(true)}>
                                Nova zona
                            </Button>
                        ) : undefined
                    }
                />
                <CardContent>
                    <DataTable
                        columns={columns}
                        rows={zonas}
                        rowKey={(zona) => zona.id}
                        density="compact"
                        emptyState={
                            <EmptyState
                                title="Nenhuma zona cadastrada"
                                description="As zonas oficiais são carregadas pela carga inicial do sistema (seed), a partir do Quadro 10 vigente. Sem zonas ativas, a publicação do Quadro 10 fica bloqueada."
                                action={
                                    canMaintain ? (
                                        <Button size="sm" onClick={() => setShowCreate(true)}>
                                            Nova zona
                                        </Button>
                                    ) : undefined
                                }
                            />
                        }
                    />
                </CardContent>
            </Card>

            {canMaintain && showCreate && <CreateZonaModal onClose={() => setShowCreate(false)} />}

            {editing && <EditZonaModal zona={editing} onClose={() => setEditing(null)} />}

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

ZonasIndex.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
