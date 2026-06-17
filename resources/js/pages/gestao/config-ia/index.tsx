import { Form, Head, router, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import PageHeader from '@/components/app/page-header';
import Input from '@/components/form/input';
import Label from '@/components/form/label';
import Select from '@/components/form/select';
import { PencilIcon, PlugInIcon, TrashIcon } from '@/components/icons';
import Badge from '@/components/ui/badge';
import Button from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import ConfirmDialog from '@/components/ui/confirm-dialog';
import EmptyState from '@/components/ui/empty-state';
import { Modal } from '@/components/ui/modal';
import GestaoLayout from '@/layouts/gestao-layout';
import type { SharedProps } from '@/types';

interface AiConfigurationItem {
    id: number;
    name: string;
    provider: string;
    capability: string;
    base_url: string | null;
    model: string;
    temperature: string | number;
    max_tokens: number;
    timeout_ms: number;
    masked_api_key: string | null;
    active: boolean;
    is_default: boolean;
}

interface ConfigIaIndexProps {
    configurations: AiConfigurationItem[];
}

const PROVIDER_OPTIONS = [
    { value: 'openai', label: 'OpenAI' },
    { value: 'anthropic', label: 'Anthropic' },
    { value: 'gemini', label: 'Google Gemini' },
    { value: 'azure', label: 'Azure OpenAI' },
    { value: 'compativel', label: 'Compatível (OpenAI-compatible)' },
];

const CAPABILITY_OPTIONS = [
    { value: 'text', label: 'Texto' },
    { value: 'vision', label: 'Visão (multimodal)' },
    { value: 'embeddings', label: 'Embeddings' },
];

function OptionLabel({ value, options }: { value: string; options: { value: string; label: string }[] }) {
    return <>{options.find((option) => option.value === value)?.label ?? value}</>;
}

function ConfigFormModal({ config, isOpen, onClose }: { config?: AiConfigurationItem; isOpen: boolean; onClose: () => void }) {
    const editing = config !== undefined;
    const [provider, setProvider] = useState(config?.provider ?? 'openai');
    const [capability, setCapability] = useState(config?.capability ?? 'text');
    const [active, setActive] = useState(config?.active ?? true);
    const [isDefault, setIsDefault] = useState(config?.is_default ?? false);

    return (
        <Modal isOpen={isOpen} onClose={onClose} className="m-4 max-h-[90vh] max-w-[640px] overflow-y-auto p-6 lg:p-8">
            <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90">
                {editing ? 'Editar configuração de IA' : 'Nova configuração de IA'}
            </h4>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                A configuração padrão ativa de cada capacidade é usada pelas funções de IA. A credencial é criptografada e nunca reexibida.
            </p>

            <Form
                action={editing ? `/gestao/config-ia/${config.id}` : '/gestao/config-ia'}
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
                            <h5 className="text-sm font-semibold text-gray-700 dark:text-gray-300">Provedor</h5>
                            <div className="grid gap-5 sm:grid-cols-2">
                                <div>
                                    <Label htmlFor="name">Nome</Label>
                                    <Input id="name" name="name" type="text" required defaultValue={config?.name} placeholder="OpenAI Texto" error={!!errors.name} hint={errors.name} />
                                </div>
                                <div>
                                    <Label htmlFor="provider">Provedor</Label>
                                    <Select id="provider" name="provider" value={provider} onChange={setProvider} options={PROVIDER_OPTIONS} />
                                </div>
                                <div>
                                    <Label htmlFor="capability">Capacidade</Label>
                                    <Select id="capability" name="capability" value={capability} onChange={setCapability} options={CAPABILITY_OPTIONS} />
                                </div>
                                <div>
                                    <Label htmlFor="model">Modelo</Label>
                                    <Input id="model" name="model" type="text" required defaultValue={config?.model} placeholder="gpt-5.4-mini" error={!!errors.model} hint={errors.model} />
                                </div>
                                <div className="sm:col-span-2">
                                    <Label htmlFor="base_url">URL base</Label>
                                    <Input id="base_url" name="base_url" type="url" required defaultValue={config?.base_url ?? 'https://api.openai.com/v1'} placeholder="https://api.openai.com/v1" error={!!errors.base_url} hint={errors.base_url} />
                                </div>
                                <div className="sm:col-span-2">
                                    <Label htmlFor="api_key">Chave de API</Label>
                                    <Input
                                        id="api_key"
                                        name="api_key"
                                        type="password"
                                        autoComplete="new-password"
                                        placeholder={editing ? 'deixe em branco para manter' : ''}
                                        error={!!errors.api_key}
                                        hint={errors.api_key}
                                    />
                                </div>
                            </div>
                        </section>

                        <section className="flex flex-col gap-5">
                            <h5 className="text-sm font-semibold text-gray-700 dark:text-gray-300">Parâmetros</h5>
                            <div className="grid gap-5 sm:grid-cols-3">
                                <div>
                                    <Label htmlFor="temperature">Temperatura</Label>
                                    <Input id="temperature" name="temperature" type="number" step="0.01" min="0" max="2" required defaultValue={config?.temperature ?? 0.1} error={!!errors.temperature} hint={errors.temperature} />
                                </div>
                                <div>
                                    <Label htmlFor="max_tokens">Máximo de tokens</Label>
                                    <Input id="max_tokens" name="max_tokens" type="number" required defaultValue={config?.max_tokens ?? 4096} error={!!errors.max_tokens} hint={errors.max_tokens} />
                                </div>
                                <div>
                                    <Label htmlFor="timeout_ms">Tempo limite (ms)</Label>
                                    <Input id="timeout_ms" name="timeout_ms" type="number" required defaultValue={config?.timeout_ms ?? 60000} error={!!errors.timeout_ms} hint={errors.timeout_ms} />
                                </div>
                            </div>
                        </section>

                        <div className="flex flex-wrap items-center gap-6">
                            <label className="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                <input type="checkbox" checked={active} onChange={(event) => setActive(event.target.checked)} className="size-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500" />
                                Ativa
                            </label>
                            <label className="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                <input type="checkbox" checked={isDefault} onChange={(event) => setIsDefault(event.target.checked)} className="size-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500" />
                                Padrão da capacidade
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

function ConfigCard({
    config,
    canMaintain,
    testing,
    onTest,
    onEdit,
    onDelete,
}: {
    config: AiConfigurationItem;
    canMaintain: boolean;
    testing: boolean;
    onTest: () => void;
    onEdit: () => void;
    onDelete: () => void;
}) {
    return (
        <Card className={config.is_default ? 'ring-1 ring-brand-500/40' : undefined}>
            <div className="p-5">
                <div className="flex items-start justify-between gap-2">
                    {config.is_default ? (
                        <Badge size="sm" color="info">
                            Padrão
                        </Badge>
                    ) : (
                        <span />
                    )}
                    <Badge size="sm" color={config.active ? 'success' : 'warning'}>
                        {config.active ? 'Ativa' : 'Inativa'}
                    </Badge>
                </div>

                <div className="mt-3">
                    <h4 className="text-base font-semibold text-gray-800 dark:text-white/90">{config.name}</h4>
                    <p className="text-theme-xs uppercase text-gray-400">
                        <OptionLabel value={config.provider} options={PROVIDER_OPTIONS} /> · <OptionLabel value={config.capability} options={CAPABILITY_OPTIONS} />
                    </p>
                </div>

                <dl className="mt-4 space-y-1.5 text-sm text-gray-600 dark:text-gray-400">
                    <div>{config.model}</div>
                    <div className="truncate">{config.base_url ?? '—'}</div>
                    <div>Chave: {config.masked_api_key ?? 'não definida'}</div>
                </dl>

                {canMaintain && (
                    <div className="mt-5 flex items-center gap-4 border-t border-gray-100 pt-4 dark:border-gray-800">
                        <button type="button" onClick={onTest} disabled={testing} className="inline-flex items-center gap-1.5 text-sm font-medium text-gray-600 transition hover:text-brand-500 disabled:opacity-50 dark:text-gray-300">
                            <PlugInIcon className="size-4.5" />
                            {testing ? 'Testando...' : 'Testar'}
                        </button>
                        <button type="button" onClick={onEdit} className="inline-flex items-center gap-1.5 text-sm font-medium text-gray-600 transition hover:text-brand-500 dark:text-gray-300">
                            <PencilIcon className="size-4.5" />
                            Editar
                        </button>
                        <button type="button" onClick={onDelete} className="ml-auto inline-flex items-center text-gray-400 transition hover:text-error-500" aria-label="Excluir configuração">
                            <TrashIcon className="size-4.5" />
                        </button>
                    </div>
                )}
            </div>
        </Card>
    );
}

export default function ConfigIaIndex({ configurations }: ConfigIaIndexProps) {
    const { auth } = usePage<SharedProps>().props;
    const canMaintain = auth.permissions.includes('manter-config-ia');

    const [showCreate, setShowCreate] = useState(false);
    const [editing, setEditing] = useState<AiConfigurationItem | null>(null);
    const [pendingDelete, setPendingDelete] = useState<AiConfigurationItem | null>(null);
    const [deleteProcessing, setDeleteProcessing] = useState(false);
    const [testingId, setTestingId] = useState<number | null>(null);

    function executeTest(config: AiConfigurationItem) {
        router.post(`/gestao/config-ia/${config.id}/testar`, {}, {
            preserveScroll: true,
            onStart: () => setTestingId(config.id),
            onFinish: () => setTestingId(null),
        });
    }

    function executeDelete() {
        if (!pendingDelete) {
            return;
        }

        router.delete(`/gestao/config-ia/${pendingDelete.id}`, {
            preserveScroll: true,
            onStart: () => setDeleteProcessing(true),
            onFinish: () => setDeleteProcessing(false),
            onSuccess: () => setPendingDelete(null),
        });
    }

    return (
        <>
            <Head title="Configuração de IA" />
            <PageHeader title="Configuração de IA" breadcrumbs={[{ label: 'Painel', href: '/gestao' }]} />

            <Card>
                <CardHeader
                    title="Configuração de IA"
                    description="Gerencie os provedores de IA (OpenAI, Anthropic, Google e compatíveis) usados pelas funções de inteligência artificial."
                    actions={
                        canMaintain ? (
                            <Button size="sm" onClick={() => setShowCreate(true)}>
                                Nova configuração
                            </Button>
                        ) : undefined
                    }
                />
                <CardContent>
                    {configurations.length === 0 ? (
                        <EmptyState
                            title="Nenhuma configuração de IA cadastrada"
                            description="Enquanto não houver um provedor configurado, as funções de IA permanecem indisponíveis."
                            action={
                                canMaintain ? (
                                    <Button size="sm" onClick={() => setShowCreate(true)}>
                                        Nova configuração
                                    </Button>
                                ) : undefined
                            }
                        />
                    ) : (
                        <div className="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                            {configurations.map((config) => (
                                <ConfigCard
                                    key={config.id}
                                    config={config}
                                    canMaintain={canMaintain}
                                    testing={testingId === config.id}
                                    onTest={() => executeTest(config)}
                                    onEdit={() => setEditing(config)}
                                    onDelete={() => setPendingDelete(config)}
                                />
                            ))}
                        </div>
                    )}
                </CardContent>
            </Card>

            {canMaintain && <ConfigFormModal isOpen={showCreate} onClose={() => setShowCreate(false)} />}

            {editing && <ConfigFormModal config={editing} isOpen onClose={() => setEditing(null)} />}

            {pendingDelete && (
                <ConfirmDialog
                    isOpen
                    onClose={() => setPendingDelete(null)}
                    onConfirm={executeDelete}
                    title="Excluir configuração de IA"
                    description={`Confirma a exclusão de "${pendingDelete.name}"? Esta ação não pode ser desfeita.`}
                    confirmLabel="Excluir"
                    variant="danger"
                    processing={deleteProcessing}
                />
            )}
        </>
    );
}

ConfigIaIndex.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
