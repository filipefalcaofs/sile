import { Form, Head, router, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import PageHeader from '@/components/app/page-header';
import Input from '@/components/form/input';
import Label from '@/components/form/label';
import Select from '@/components/form/select';
import { MailIcon, PencilIcon, TrashIcon } from '@/components/icons';
import Badge from '@/components/ui/badge';
import Button from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import ConfirmDialog from '@/components/ui/confirm-dialog';
import EmptyState from '@/components/ui/empty-state';
import { Modal } from '@/components/ui/modal';
import GestaoLayout from '@/layouts/gestao-layout';
import type { SharedProps } from '@/types';

interface EmailServerItem {
    id: number;
    name: string;
    driver: string;
    host: string;
    port: number;
    encryption: string;
    timeout: number;
    username: string | null;
    masked_password: string | null;
    from_address: string;
    from_name: string;
    active: boolean;
    is_default: boolean;
}

interface ConfigEmailIndexProps {
    servers: EmailServerItem[];
}

const ENCRYPTION_OPTIONS = [
    { value: 'tls', label: 'TLS (porta 587)' },
    { value: 'ssl', label: 'SSL (porta 465)' },
    { value: 'none', label: 'Nenhuma' },
];

function EncryptionLabel({ value }: { value: string }) {
    return <>{ENCRYPTION_OPTIONS.find((option) => option.value === value)?.label ?? value}</>;
}

function ServerFormModal({ server, isOpen, onClose }: { server?: EmailServerItem; isOpen: boolean; onClose: () => void }) {
    const editing = server !== undefined;
    const [encryption, setEncryption] = useState(server?.encryption ?? 'tls');
    const [active, setActive] = useState(server?.active ?? true);
    const [isDefault, setIsDefault] = useState(server?.is_default ?? false);

    return (
        <Modal isOpen={isOpen} onClose={onClose} className="m-4 max-h-[90vh] max-w-[640px] overflow-y-auto p-6 lg:p-8">
            <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90">
                {editing ? 'Editar servidor de e-mail' : 'Novo servidor de e-mail'}
            </h4>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                O servidor padrão ativo é usado pelo sistema para enviar as notificações.
            </p>

            <Form
                action={editing ? `/gestao/config-email/${server.id}` : '/gestao/config-email'}
                method={editing ? 'put' : 'post'}
                resetOnSuccess={!editing}
                onSuccess={onClose}
                className="mt-6"
            >
                {({ errors, processing }) => (
                    <div className="flex flex-col gap-6">
                        <input type="hidden" name="active" value={active ? '1' : '0'} />
                        <input type="hidden" name="is_default" value={isDefault ? '1' : '0'} />

                        <section className="flex flex-col gap-5">
                            <h5 className="text-sm font-semibold text-gray-700 dark:text-gray-300">Servidor</h5>
                            <div className="grid gap-5 sm:grid-cols-2">
                                <div>
                                    <Label htmlFor="name">Nome</Label>
                                    <Input id="name" name="name" type="text" required defaultValue={server?.name} placeholder="SEDUR SMTP" error={!!errors.name} hint={errors.name} />
                                </div>
                                <div>
                                    <Label htmlFor="driver">Driver</Label>
                                    <Select id="driver" name="driver" value="smtp" onChange={() => {}} options={[{ value: 'smtp', label: 'SMTP' }]} />
                                </div>
                                <div>
                                    <Label htmlFor="host">Host SMTP</Label>
                                    <Input id="host" name="host" type="text" required defaultValue={server?.host} placeholder="smtp.gmail.com" error={!!errors.host} hint={errors.host} />
                                </div>
                                <div>
                                    <Label htmlFor="port">Porta</Label>
                                    <Input id="port" name="port" type="number" required defaultValue={server?.port ?? 587} error={!!errors.port} hint={errors.port} />
                                </div>
                                <div>
                                    <Label htmlFor="encryption">Criptografia</Label>
                                    <Select id="encryption" name="encryption" value={encryption} onChange={setEncryption} options={ENCRYPTION_OPTIONS} />
                                </div>
                                <div>
                                    <Label htmlFor="timeout">Timeout (segundos)</Label>
                                    <Input id="timeout" name="timeout" type="number" required defaultValue={server?.timeout ?? 30} error={!!errors.timeout} hint={errors.timeout} />
                                </div>
                            </div>
                        </section>

                        <section className="flex flex-col gap-5">
                            <h5 className="text-sm font-semibold text-gray-700 dark:text-gray-300">Credenciais e remetente</h5>
                            <div className="grid gap-5 sm:grid-cols-2">
                                <div>
                                    <Label htmlFor="username">Usuário</Label>
                                    <Input id="username" name="username" type="text" defaultValue={server?.username ?? ''} error={!!errors.username} hint={errors.username} />
                                </div>
                                <div>
                                    <Label htmlFor="password">Senha</Label>
                                    <Input
                                        id="password"
                                        name="password"
                                        type="password"
                                        autoComplete="new-password"
                                        placeholder={editing ? 'deixe em branco para manter' : ''}
                                        error={!!errors.password}
                                        hint={errors.password}
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="from_address">E-mail do remetente</Label>
                                    <Input id="from_address" name="from_address" type="email" required defaultValue={server?.from_address} error={!!errors.from_address} hint={errors.from_address} />
                                </div>
                                <div>
                                    <Label htmlFor="from_name">Nome do remetente</Label>
                                    <Input id="from_name" name="from_name" type="text" required defaultValue={server?.from_name} error={!!errors.from_name} hint={errors.from_name} />
                                </div>
                            </div>
                        </section>

                        <div className="flex flex-wrap items-center gap-6">
                            <label className="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                <input type="checkbox" checked={active} onChange={(event) => setActive(event.target.checked)} className="size-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500" />
                                Ativo
                            </label>
                            <label className="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                <input type="checkbox" checked={isDefault} onChange={(event) => setIsDefault(event.target.checked)} className="size-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500" />
                                Servidor padrão
                            </label>
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

function TestConnectionModal({ server, onClose }: { server: EmailServerItem; onClose: () => void }) {
    return (
        <Modal isOpen onClose={onClose} className="m-4 max-w-[480px] p-6 lg:p-8">
            <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90">Testar conexão</h4>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Enviaremos um e-mail de teste real usando o servidor "{server.name}". Informe o destinatário.
            </p>

            <Form action={`/gestao/config-email/${server.id}/testar`} method="post" onSuccess={onClose} className="mt-6">
                {({ errors, processing }) => (
                    <div className="flex flex-col gap-5">
                        <div>
                            <Label htmlFor="recipient">E-mail destinatário</Label>
                            <Input id="recipient" name="recipient" type="email" required placeholder="voce@exemplo.com" error={!!errors.recipient} hint={errors.recipient} />
                        </div>
                        <div className="flex items-center justify-end gap-3">
                            <Button size="sm" variant="outline" onClick={onClose} disabled={processing}>
                                Cancelar
                            </Button>
                            <Button size="sm" type="submit" disabled={processing}>
                                {processing ? 'Enviando...' : 'Enviar teste'}
                            </Button>
                        </div>
                    </div>
                )}
            </Form>
        </Modal>
    );
}

function ServerCard({
    server,
    canMaintain,
    onTest,
    onEdit,
    onDelete,
}: {
    server: EmailServerItem;
    canMaintain: boolean;
    onTest: () => void;
    onEdit: () => void;
    onDelete: () => void;
}) {
    return (
        <Card className={server.is_default ? 'ring-1 ring-brand-500/40' : undefined}>
            <div className="p-5">
                <div className="flex items-start justify-between gap-2">
                    {server.is_default ? (
                        <Badge size="sm" color="info">
                            Padrão
                        </Badge>
                    ) : (
                        <span />
                    )}
                    <Badge size="sm" color={server.active ? 'success' : 'warning'}>
                        {server.active ? 'Ativo' : 'Inativo'}
                    </Badge>
                </div>

                <div className="mt-3">
                    <h4 className="text-base font-semibold text-gray-800 dark:text-white/90">{server.name}</h4>
                    <p className="text-theme-xs uppercase text-gray-400">{server.driver}</p>
                </div>

                <dl className="mt-4 space-y-1.5 text-sm text-gray-600 dark:text-gray-400">
                    <div>
                        {server.host}:{server.port}
                    </div>
                    <div>{server.from_address}</div>
                    <div>{server.username ?? '—'}</div>
                    <div>
                        <EncryptionLabel value={server.encryption} />
                    </div>
                </dl>

                {canMaintain && (
                    <div className="mt-5 flex items-center gap-4 border-t border-gray-100 pt-4 dark:border-gray-800">
                        <button type="button" onClick={onTest} className="inline-flex items-center gap-1.5 text-sm font-medium text-gray-600 transition hover:text-brand-500 dark:text-gray-300">
                            <MailIcon className="size-4.5" />
                            Testar
                        </button>
                        <button type="button" onClick={onEdit} className="inline-flex items-center gap-1.5 text-sm font-medium text-gray-600 transition hover:text-brand-500 dark:text-gray-300">
                            <PencilIcon className="size-4.5" />
                            Editar
                        </button>
                        <button type="button" onClick={onDelete} className="ml-auto inline-flex items-center text-gray-400 transition hover:text-error-500" aria-label="Excluir servidor">
                            <TrashIcon className="size-4.5" />
                        </button>
                    </div>
                )}
            </div>
        </Card>
    );
}

export default function ConfigEmailIndex({ servers }: ConfigEmailIndexProps) {
    const { auth } = usePage<SharedProps>().props;
    const canMaintain = auth.permissions.includes('manter-config-email');

    const [showCreate, setShowCreate] = useState(false);
    const [editing, setEditing] = useState<EmailServerItem | null>(null);
    const [testing, setTesting] = useState<EmailServerItem | null>(null);
    const [pendingDelete, setPendingDelete] = useState<EmailServerItem | null>(null);
    const [deleteProcessing, setDeleteProcessing] = useState(false);

    function executeDelete() {
        if (!pendingDelete) {
            return;
        }

        router.delete(`/gestao/config-email/${pendingDelete.id}`, {
            preserveScroll: true,
            onStart: () => setDeleteProcessing(true),
            onFinish: () => setDeleteProcessing(false),
            onSuccess: () => setPendingDelete(null),
        });
    }

    return (
        <>
            <Head title="Configuração de e-mail" />
            <PageHeader title="Configuração de e-mail" breadcrumbs={[{ label: 'Painel', href: '/gestao' }]} />

            <Card>
                <CardHeader
                    title="Configuração de e-mail"
                    description="Gerencie os servidores de e-mail usados pelo sistema para envio de notificações."
                    actions={
                        canMaintain ? (
                            <Button size="sm" onClick={() => setShowCreate(true)}>
                                Novo servidor
                            </Button>
                        ) : undefined
                    }
                />
                <CardContent>
                    {servers.length === 0 ? (
                        <EmptyState
                            title="Nenhum servidor de e-mail cadastrado"
                            description="Enquanto não houver um servidor padrão, o sistema usa a configuração de e-mail do ambiente."
                            action={
                                canMaintain ? (
                                    <Button size="sm" onClick={() => setShowCreate(true)}>
                                        Novo servidor
                                    </Button>
                                ) : undefined
                            }
                        />
                    ) : (
                        <div className="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                            {servers.map((server) => (
                                <ServerCard
                                    key={server.id}
                                    server={server}
                                    canMaintain={canMaintain}
                                    onTest={() => setTesting(server)}
                                    onEdit={() => setEditing(server)}
                                    onDelete={() => setPendingDelete(server)}
                                />
                            ))}
                        </div>
                    )}
                </CardContent>
            </Card>

            {canMaintain && <ServerFormModal isOpen={showCreate} onClose={() => setShowCreate(false)} />}

            {editing && <ServerFormModal server={editing} isOpen onClose={() => setEditing(null)} />}

            {testing && <TestConnectionModal server={testing} onClose={() => setTesting(null)} />}

            {pendingDelete && (
                <ConfirmDialog
                    isOpen
                    onClose={() => setPendingDelete(null)}
                    onConfirm={executeDelete}
                    title="Excluir servidor de e-mail"
                    description={`Confirma a exclusão de "${pendingDelete.name}"? Esta ação não pode ser desfeita.`}
                    confirmLabel="Excluir"
                    variant="danger"
                    processing={deleteProcessing}
                />
            )}
        </>
    );
}

ConfigEmailIndex.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
