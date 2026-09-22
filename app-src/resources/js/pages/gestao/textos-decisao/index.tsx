import { Head, useForm, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import PageHeader from '@/components/app/page-header';
import Input from '@/components/form/input';
import Label from '@/components/form/label';
import { PencilIcon } from '@/components/icons';
import Alert from '@/components/ui/alert';
import Badge from '@/components/ui/badge';
import Button from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import DataTable from '@/components/ui/data-table/data-table';
import type { ColumnDef } from '@/components/ui/data-table/types';
import EmptyState from '@/components/ui/empty-state';
import { Modal } from '@/components/ui/modal';
import TableAction from '@/components/ui/table-action';
import GestaoLayout from '@/layouts/gestao-layout';
import type { SharedProps } from '@/types';

interface DecisionTextItem {
    key: string;
    template: string;
    description: string;
}

interface DecisionTextGroup {
    prefixo: string;
    label: string;
    textos: DecisionTextItem[];
}

interface DecisionTextsIndexProps {
    grupos: DecisionTextGroup[];
}

function EditDecisionTextModal({ texto, onClose }: { texto: DecisionTextItem; onClose: () => void }) {
    const form = useForm({
        template: texto.template,
        description: texto.description,
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        form.put(`/gestao/textos-decisao/${texto.key}`, {
            preserveScroll: true,
            onSuccess: () => onClose(),
        });
    }

    return (
        <Modal isOpen onClose={onClose} className="m-4 max-h-[90vh] max-w-[640px] overflow-y-auto p-6 lg:p-8">
            <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90">Editar texto decisório</h4>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                <span className="font-mono">{texto.key}</span> — a chave é fixa e vinculada ao motor de decisão; não
                pode ser alterada.
            </p>

            <div className="mt-4">
                <Alert
                    variant="warning"
                    title="Texto de documento oficial"
                    message="Este texto é emitido em documentos oficiais (TVL/parecer). Placeholders `:nome` são substituídos pelo motor; removê-los remove a informação do documento."
                />
            </div>

            <form onSubmit={handleSubmit} className="mt-6">
                <div className="flex flex-col gap-5">
                    <div>
                        <Label htmlFor={`edit-template-${texto.key}`}>Texto emitido</Label>
                        <textarea
                            id={`edit-template-${texto.key}`}
                            rows={5}
                            required
                            value={form.data.template}
                            onChange={(e) => form.setData('template', e.target.value)}
                            className="w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                        />
                        {form.errors.template && <p className="mt-1.5 text-xs text-error-500">{form.errors.template}</p>}
                    </div>
                    <div>
                        <Label htmlFor={`edit-description-${texto.key}`}>Descrição e placeholders disponíveis</Label>
                        <Input
                            id={`edit-description-${texto.key}`}
                            type="text"
                            name="description"
                            required
                            value={form.data.description}
                            onChange={(e) => form.setData('description', e.target.value)}
                            error={!!form.errors.description}
                            hint={form.errors.description}
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

export default function DecisionTextsIndex({ grupos }: DecisionTextsIndexProps) {
    const { auth } = usePage<SharedProps>().props;
    const canMaintain = auth.permissions.includes('manter-parametros');

    const [editing, setEditing] = useState<DecisionTextItem | null>(null);

    const columns: ColumnDef<DecisionTextItem>[] = [
        {
            id: 'key',
            header: 'Chave',
            cellClassName: 'whitespace-nowrap',
            cell: (texto) => (
                <Badge size="sm" color="primary">
                    <span className="font-mono">{texto.key}</span>
                </Badge>
            ),
        },
        {
            id: 'template',
            header: 'Texto emitido',
            cell: (texto) => (
                <span className="block max-w-md truncate" title={texto.template}>
                    {texto.template}
                </span>
            ),
        },
        {
            id: 'description',
            header: 'Descrição e placeholders',
            cell: (texto) => (
                <span className="block max-w-sm truncate text-gray-500 dark:text-gray-400" title={texto.description}>
                    {texto.description}
                </span>
            ),
        },
        ...(canMaintain
            ? [
                  {
                      id: 'actions',
                      header: 'Ações',
                      align: 'end',
                      cellClassName: 'whitespace-nowrap',
                      cell: (texto) => (
                          <div className="flex justify-end gap-2">
                              <TableAction
                                  tone="brand"
                                  icon={<PencilIcon className="size-4.5" />}
                                  label="Editar"
                                  onClick={() => setEditing(texto)}
                              />
                          </div>
                      ),
                  } satisfies ColumnDef<DecisionTextItem>,
              ]
            : []),
    ];

    return (
        <>
            <Head title="Textos decisórios" />
            <PageHeader title="Textos decisórios" breadcrumbs={[{ label: 'Painel', href: '/gestao' }]} />

            <div className="flex flex-col gap-6">
                {grupos.length === 0 && (
                    <Card>
                        <CardContent>
                            <EmptyState
                                title="Nenhum texto cadastrado"
                                description="Os textos decisórios são carregados pela carga inicial do sistema (seed), vinculados ao motor de decisão."
                            />
                        </CardContent>
                    </Card>
                )}

                {grupos.map((grupo) => (
                    <Card key={grupo.prefixo}>
                        <CardHeader
                            title={grupo.label}
                            description={`Chaves ${grupo.prefixo}.* — textos lidos pelo motor na emissão dos documentos. Sem criação ou exclusão pela tela: a chave é vinculada ao código do motor.`}
                        />
                        <CardContent>
                            <DataTable
                                columns={columns}
                                rows={grupo.textos}
                                rowKey={(texto) => texto.key}
                                density="compact"
                                emptyState={
                                    <EmptyState
                                        title="Nenhum texto neste grupo"
                                        description="Os textos decisórios são carregados pela carga inicial do sistema (seed)."
                                    />
                                }
                            />
                        </CardContent>
                    </Card>
                ))}
            </div>

            {editing && <EditDecisionTextModal texto={editing} onClose={() => setEditing(null)} />}
        </>
    );
}

DecisionTextsIndex.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
