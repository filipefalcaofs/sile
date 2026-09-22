import { Head, router, useForm, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import PageHeader from '@/components/app/page-header';
import Input from '@/components/form/input';
import Label from '@/components/form/label';
import Select from '@/components/form/select';
import { CheckCircleIcon, PencilIcon, TrashIcon } from '@/components/icons';
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

type TermoStatus = 'vigente' | 'rascunho' | 'substituido';

interface LegalTermItem {
    id: number;
    type: string;
    version: number;
    title: string;
    content: string;
    published_at: string | null;
    status: TermoStatus;
}

interface LegalTermsIndexProps {
    termos: LegalTermItem[];
    tipos: string[];
}

const NOVO_TIPO = '__novo__';

const STATUS_LABEL: Record<TermoStatus, string> = {
    vigente: 'Vigente',
    rascunho: 'Aguardando publicação',
    substituido: 'Substituído',
};

const STATUS_COLOR: Record<TermoStatus, 'success' | 'warning' | 'light'> = {
    vigente: 'success',
    rascunho: 'warning',
    substituido: 'light',
};

function StatusBadge({ status }: { status: TermoStatus }) {
    return (
        <Badge size="sm" color={STATUS_COLOR[status]}>
            {STATUS_LABEL[status]}
        </Badge>
    );
}

function NewLegalTermModal({ tipos, onClose }: { tipos: string[]; onClose: () => void }) {
    const [tipoSelecionado, setTipoSelecionado] = useState(tipos[0] ?? NOVO_TIPO);
    const form = useForm({
        type: tipos[0] ?? '',
        title: '',
        content: '',
    });

    function handleTipoChange(value: string) {
        setTipoSelecionado(value);
        form.setData('type', value === NOVO_TIPO ? '' : value);
    }

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        form.post('/gestao/termos-legais', {
            preserveScroll: true,
            onSuccess: () => onClose(),
        });
    }

    return (
        <Modal isOpen onClose={onClose} className="m-4 max-h-[90vh] max-w-[720px] overflow-y-auto p-6 lg:p-8">
            <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90">Novo termo legal</h4>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Nasce como rascunho com a próxima versão do tipo — o texto só passa a valer após a publicação.
            </p>

            <form onSubmit={handleSubmit} className="mt-6">
                <div className="flex flex-col gap-5">
                    <div>
                        <Label htmlFor="novo-tipo">Tipo do termo</Label>
                        <Select
                            id="novo-tipo"
                            name="type"
                            options={[
                                ...tipos.map((tipo) => ({ value: tipo, label: tipo })),
                                { value: NOVO_TIPO, label: 'Novo tipo...' },
                            ]}
                            placeholder="Selecione o tipo"
                            value={tipoSelecionado}
                            onChange={handleTipoChange}
                        />
                        {tipoSelecionado === NOVO_TIPO && (
                            <div className="mt-3">
                                <Input
                                    id="novo-tipo-livre"
                                    type="text"
                                    name="type"
                                    required
                                    placeholder="ex.: lgpd, uso_de_imagem"
                                    value={form.data.type}
                                    onChange={(e) => form.setData('type', e.target.value)}
                                    error={!!form.errors.type}
                                    hint={form.errors.type ?? 'Minúsculas, números e sublinhado (a-z, 0-9, _).'}
                                />
                            </div>
                        )}
                        {tipoSelecionado !== NOVO_TIPO && form.errors.type && (
                            <p className="mt-1.5 text-xs text-error-500">{form.errors.type}</p>
                        )}
                    </div>
                    <div>
                        <Label htmlFor="novo-titulo">Título</Label>
                        <Input
                            id="novo-titulo"
                            type="text"
                            name="title"
                            required
                            value={form.data.title}
                            onChange={(e) => form.setData('title', e.target.value)}
                            error={!!form.errors.title}
                            hint={form.errors.title}
                        />
                    </div>
                    <div>
                        <Label htmlFor="novo-conteudo">Conteúdo</Label>
                        <textarea
                            id="novo-conteudo"
                            rows={10}
                            required
                            value={form.data.content}
                            onChange={(e) => form.setData('content', e.target.value)}
                            className="w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                        />
                        {form.errors.content && <p className="mt-1.5 text-xs text-error-500">{form.errors.content}</p>}
                    </div>
                    <div className="flex items-center justify-end gap-3">
                        <Button size="sm" variant="outline" onClick={onClose} disabled={form.processing} type="button">
                            Cancelar
                        </Button>
                        <Button size="sm" type="submit" disabled={form.processing}>
                            {form.processing ? 'Salvando...' : 'Criar rascunho'}
                        </Button>
                    </div>
                </div>
            </form>
        </Modal>
    );
}

function EditLegalTermModal({ termo, onClose }: { termo: LegalTermItem; onClose: () => void }) {
    const form = useForm({
        title: termo.title,
        content: termo.content,
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        form.put(`/gestao/termos-legais/${termo.id}`, {
            preserveScroll: true,
            onSuccess: () => onClose(),
        });
    }

    return (
        <Modal isOpen onClose={onClose} className="m-4 max-h-[90vh] max-w-[720px] overflow-y-auto p-6 lg:p-8">
            <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90">Editar rascunho</h4>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {termo.type} — versão {termo.version}. O tipo e a versão são fixos; só título e conteúdo podem mudar
                antes da publicação.
            </p>

            <form onSubmit={handleSubmit} className="mt-6">
                <div className="flex flex-col gap-5">
                    <div>
                        <Label htmlFor={`edit-titulo-${termo.id}`}>Título</Label>
                        <Input
                            id={`edit-titulo-${termo.id}`}
                            type="text"
                            name="title"
                            required
                            value={form.data.title}
                            onChange={(e) => form.setData('title', e.target.value)}
                            error={!!form.errors.title}
                            hint={form.errors.title}
                        />
                    </div>
                    <div>
                        <Label htmlFor={`edit-conteudo-${termo.id}`}>Conteúdo</Label>
                        <textarea
                            id={`edit-conteudo-${termo.id}`}
                            rows={10}
                            required
                            value={form.data.content}
                            onChange={(e) => form.setData('content', e.target.value)}
                            className="w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                        />
                        {form.errors.content && <p className="mt-1.5 text-xs text-error-500">{form.errors.content}</p>}
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

export default function LegalTermsIndex({ termos, tipos }: LegalTermsIndexProps) {
    const { auth } = usePage<SharedProps>().props;
    const canMaintain = auth.permissions.includes('manter-parametros');

    const [creating, setCreating] = useState(false);
    const [editing, setEditing] = useState<LegalTermItem | null>(null);
    const [pendingPublish, setPendingPublish] = useState<LegalTermItem | null>(null);
    const [pendingDelete, setPendingDelete] = useState<LegalTermItem | null>(null);
    const [actionProcessing, setActionProcessing] = useState(false);

    const columns: ColumnDef<LegalTermItem>[] = [
        {
            id: 'type',
            header: 'Tipo',
            cellClassName: 'whitespace-nowrap',
            cell: (termo) => (
                <Badge size="sm" color="primary">
                    {termo.type}
                </Badge>
            ),
        },
        {
            id: 'version',
            header: 'Versão',
            cellClassName: 'whitespace-nowrap',
            cell: (termo) => `v${termo.version}`,
        },
        {
            id: 'title',
            header: 'Título',
            cellClassName: 'font-medium text-gray-800 dark:text-white/90',
            cell: (termo) => termo.title,
        },
        {
            id: 'status',
            header: 'Situação',
            cell: (termo) => <StatusBadge status={termo.status} />,
        },
        {
            id: 'published_at',
            header: 'Publicado em',
            cellClassName: 'whitespace-nowrap',
            cell: (termo) => termo.published_at ?? '—',
        },
        ...(canMaintain
            ? [
                  {
                      id: 'actions',
                      header: 'Ações',
                      align: 'end',
                      cellClassName: 'whitespace-nowrap',
                      cell: (termo) =>
                          termo.status === 'rascunho' ? (
                              <div className="flex justify-end gap-2">
                                  <TableAction
                                      tone="brand"
                                      icon={<PencilIcon className="size-4.5" />}
                                      label="Editar"
                                      onClick={() => setEditing(termo)}
                                  />
                                  <TableAction
                                      tone="success"
                                      icon={<CheckCircleIcon className="size-4.5" />}
                                      label="Publicar"
                                      onClick={() => setPendingPublish(termo)}
                                  />
                                  <TableAction
                                      tone="error"
                                      icon={<TrashIcon className="size-4.5" />}
                                      label="Excluir"
                                      onClick={() => setPendingDelete(termo)}
                                  />
                              </div>
                          ) : (
                              <span className="text-xs text-gray-400 dark:text-gray-500" title="Termos publicados são imutáveis — crie uma nova versão.">
                                  Imutável
                              </span>
                          ),
                  } satisfies ColumnDef<LegalTermItem>,
              ]
            : []),
    ];

    function executePublish() {
        if (!pendingPublish) {
            return;
        }

        router.put(
            `/gestao/termos-legais/${pendingPublish.id}/publicar`,
            {},
            {
                preserveScroll: true,
                onStart: () => setActionProcessing(true),
                onFinish: () => setActionProcessing(false),
                onSuccess: () => setPendingPublish(null),
            },
        );
    }

    function executeDelete() {
        if (!pendingDelete) {
            return;
        }

        router.delete(`/gestao/termos-legais/${pendingDelete.id}`, {
            preserveScroll: true,
            onStart: () => setActionProcessing(true),
            onFinish: () => setActionProcessing(false),
            onSuccess: () => setPendingDelete(null),
        });
    }

    return (
        <>
            <Head title="Termos legais" />
            <PageHeader
                title="Termos legais"
                breadcrumbs={[{ label: 'Painel', href: '/gestao' }]}
                actions={
                    canMaintain ? (
                        <Button size="sm" onClick={() => setCreating(true)}>
                            Novo termo
                        </Button>
                    ) : undefined
                }
            />

            <Card>
                <CardHeader
                    title="Versões dos termos"
                    description="Termos legais aceitos pelos usuários (LGPD e futuros). O termo publicado é imutável: para alterar o texto, crie uma nova versão e publique — os aceites anteriores permanecem vinculados à versão aceita."
                />
                <CardContent>
                    <DataTable
                        columns={columns}
                        rows={termos}
                        rowKey={(termo) => termo.id}
                        density="compact"
                        emptyState={
                            <EmptyState
                                title="Nenhum termo cadastrado"
                                description="Os termos conhecidos são carregados pela carga inicial do sistema (seed). Novas versões nascem como rascunho e só valem após a publicação."
                            />
                        }
                    />
                </CardContent>
            </Card>

            {creating && <NewLegalTermModal tipos={tipos} onClose={() => setCreating(false)} />}

            {editing && <EditLegalTermModal termo={editing} onClose={() => setEditing(null)} />}

            {pendingPublish && (
                <ConfirmDialog
                    isOpen
                    onClose={() => setPendingPublish(null)}
                    onConfirm={executePublish}
                    title="Publicar termo legal"
                    description={`O novo texto passa a valer imediatamente para novos aceites. Confirmar publicação da versão ${pendingPublish.version} de "${pendingPublish.title}"?`}
                    confirmLabel="Publicar"
                    variant="warning"
                    processing={actionProcessing}
                />
            )}

            {pendingDelete && (
                <ConfirmDialog
                    isOpen
                    onClose={() => setPendingDelete(null)}
                    onConfirm={executeDelete}
                    title="Excluir rascunho"
                    description={`O rascunho da versão ${pendingDelete.version} de "${pendingDelete.title}" será excluído. Esta ação não pode ser desfeita.`}
                    confirmLabel="Excluir"
                    variant="danger"
                    processing={actionProcessing}
                />
            )}
        </>
    );
}

LegalTermsIndex.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
