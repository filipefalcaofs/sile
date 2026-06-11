import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import PageHeader from '@/components/app/page-header';
import Input from '@/components/form/input';
import Label from '@/components/form/label';
import Switch from '@/components/form/switch';
import Badge from '@/components/ui/badge';
import Button from '@/components/ui/button';
import TableAction from '@/components/ui/table-action';
import GestaoLayout from '@/layouts/gestao-layout';

interface ParameterItem {
    key: string;
    type: string;
    description: string;
    sensitive: boolean;
    requires_connection_test: boolean;
    default_value: string | null;
    value: string | null;
    has_admin_value: boolean;
    updated_at: string | null;
}

interface ParametersIndexProps {
    groups: Record<string, ParameterItem[]>;
}

const GROUP_LABELS: Record<string, string> = {
    seguranca: 'Segurança',
    ui: 'Interface',
    features: 'Funcionalidades',
};

function groupLabel(group: string): string {
    return GROUP_LABELS[group] ?? group.charAt(0).toUpperCase() + group.slice(1);
}

const textareaStyles =
    'w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800';

function FieldError({ message }: { message?: string }) {
    if (!message) {
        return null;
    }

    return <p className="mt-1.5 text-theme-xs text-error-500">{message}</p>;
}

function BooleanField({ item, error }: { item: ParameterItem; error?: string }) {
    const initial = item.value ?? item.default_value ?? '';
    const initialChecked = initial !== '0';
    const [checked, setChecked] = useState(initialChecked);

    return (
        <div>
            <Label>Valor</Label>
            <input type="hidden" name="value" value={checked ? '1' : '0'} />
            <Switch
                label={checked ? 'Ativado' : 'Desativado'}
                defaultChecked={initialChecked}
                onChange={setChecked}
            />
            <FieldError message={error} />
        </div>
    );
}

function ValueField({ item, error }: { item: ParameterItem; error?: string }) {
    const fieldId = `value-${item.key}`;
    const initial = item.value ?? item.default_value ?? '';

    if (item.sensitive) {
        return (
            <div>
                <Label htmlFor={fieldId}>Novo valor</Label>
                <div className="w-full sm:max-w-sm">
                    <Input
                        id={fieldId}
                        type="password"
                        name="value"
                        defaultValue=""
                        placeholder="••••••"
                        autoComplete="new-password"
                        error={!!error}
                        hint={error}
                    />
                </div>
                <p className="mt-1.5 text-theme-xs text-gray-400 dark:text-gray-500">
                    Deixe em branco para manter o valor atual. O valor gravado nunca é exibido.
                </p>
            </div>
        );
    }

    if (item.type === 'boolean') {
        return <BooleanField item={item} error={error} />;
    }

    if (item.type === 'integer' || item.type === 'decimal') {
        return (
            <div>
                <Label htmlFor={fieldId}>Valor</Label>
                <div className="w-full sm:max-w-sm">
                    <Input
                        id={fieldId}
                        type="number"
                        name="value"
                        defaultValue={initial}
                        step={item.type === 'decimal' ? 'any' : 1}
                        error={!!error}
                        hint={error}
                    />
                </div>
            </div>
        );
    }

    if (item.type === 'text' || item.type === 'json') {
        return (
            <div>
                <Label htmlFor={fieldId}>Valor</Label>
                <textarea
                    id={fieldId}
                    name="value"
                    defaultValue={initial}
                    rows={4}
                    className={`${textareaStyles} sm:max-w-xl`}
                />
                <FieldError message={error} />
            </div>
        );
    }

    return (
        <div>
            <Label htmlFor={fieldId}>Valor</Label>
            <div className="w-full sm:max-w-sm">
                <Input
                    id={fieldId}
                    type="text"
                    name="value"
                    defaultValue={initial}
                    error={!!error}
                    hint={error}
                />
            </div>
        </div>
    );
}

function ParameterSection({ item }: { item: ParameterItem }) {
    return (
        <div>
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div className="flex flex-wrap items-center gap-2">
                        <h4 className="text-theme-sm font-medium text-gray-800 dark:text-white/90">
                            {item.description}
                        </h4>
                        {item.has_admin_value && <Badge size="sm">Administrado</Badge>}
                    </div>
                    <p className="mt-1 font-mono text-theme-xs text-gray-400 dark:text-gray-500">{item.key}</p>
                    <p className="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">
                        Padrão: {item.default_value ?? '—'}
                    </p>
                </div>
                <TableAction tone="brand" href={`/gestao/parametros/${item.key}/historico`}>
                    Histórico
                </TableAction>
            </div>

            <Form action={`/gestao/parametros/${item.key}`} method="put" className="mt-5">
                {({ errors, processing }) => (
                    <div className="flex flex-col gap-4">
                        <ValueField item={item} error={errors.value} />
                        <div>
                            <Button size="sm" type="submit" disabled={processing}>
                                {processing ? 'Salvando...' : 'Salvar'}
                            </Button>
                        </div>
                    </div>
                )}
            </Form>

            {item.requires_connection_test && (
                <p className="mt-3 text-theme-xs text-gray-400 dark:text-gray-500">
                    Teste de conexão disponível quando a integração for configurada (Fase 13).
                </p>
            )}
        </div>
    );
}

export default function ParametersIndex({ groups }: ParametersIndexProps) {
    return (
        <GestaoLayout>
            <Head title="Parâmetros do sistema" />
            <PageHeader title="Parâmetros do sistema" breadcrumbs={[{ label: 'Painel', href: '/gestao' }]} />

            <div className="flex flex-col gap-4 md:gap-6">
                <p className="text-sm text-gray-500 dark:text-gray-400">
                    Alterações valem imediatamente, sem novo deploy, e ficam registradas no histórico auditado
                </p>

                {Object.entries(groups).map(([group, items]) => (
                    <section
                        key={group}
                        className="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]"
                    >
                        <div className="px-6 py-5">
                            <h3 className="text-base font-medium text-gray-800 dark:text-white/90">
                                {groupLabel(group)}
                            </h3>
                        </div>
                        {items.map((item) => (
                            <div
                                key={item.key}
                                className="border-t border-gray-100 p-4 dark:border-gray-800 sm:p-6"
                            >
                                <ParameterSection item={item} />
                            </div>
                        ))}
                    </section>
                ))}
            </div>
        </GestaoLayout>
    );
}
