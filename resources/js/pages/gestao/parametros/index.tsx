import { Form, Head, Link } from '@inertiajs/react';
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

function FieldError({ message }: { message?: string }) {
    if (!message) {
        return null;
    }

    return <p className="mt-1 text-xs text-red-600 dark:text-red-400">{message}</p>;
}

const inputStyles =
    'mt-1 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-neutral-900 focus:border-blue-600 focus:ring-2 focus:ring-blue-600/20 focus:outline-none sm:max-w-sm dark:border-neutral-700 dark:bg-neutral-950 dark:text-neutral-100';

function ValueField({ item }: { item: ParameterItem }) {
    const fieldId = `value-${item.key}`;
    const initial = item.value ?? item.default_value ?? '';

    if (item.sensitive) {
        return (
            <div>
                <label htmlFor={fieldId} className="block text-sm font-medium text-neutral-700 dark:text-neutral-300">
                    Novo valor
                </label>
                <input
                    id={fieldId}
                    type="password"
                    name="value"
                    defaultValue=""
                    placeholder="••••••"
                    autoComplete="new-password"
                    className={inputStyles}
                />
                <p className="mt-1 text-xs text-neutral-400 dark:text-neutral-500">
                    Deixe em branco para manter o valor atual. O valor gravado nunca é exibido.
                </p>
            </div>
        );
    }

    if (item.type === 'boolean') {
        return (
            <div>
                <label htmlFor={fieldId} className="block text-sm font-medium text-neutral-700 dark:text-neutral-300">
                    Valor
                </label>
                <select id={fieldId} name="value" defaultValue={initial} className={inputStyles}>
                    <option value="1">Ativado</option>
                    <option value="0">Desativado</option>
                </select>
            </div>
        );
    }

    if (item.type === 'integer' || item.type === 'decimal') {
        return (
            <div>
                <label htmlFor={fieldId} className="block text-sm font-medium text-neutral-700 dark:text-neutral-300">
                    Valor
                </label>
                <input
                    id={fieldId}
                    type="number"
                    name="value"
                    defaultValue={initial}
                    step={item.type === 'decimal' ? 'any' : 1}
                    className={inputStyles}
                />
            </div>
        );
    }

    if (item.type === 'text' || item.type === 'json') {
        return (
            <div>
                <label htmlFor={fieldId} className="block text-sm font-medium text-neutral-700 dark:text-neutral-300">
                    Valor
                </label>
                <textarea id={fieldId} name="value" defaultValue={initial} rows={4} className={`${inputStyles} sm:max-w-xl`} />
            </div>
        );
    }

    return (
        <div>
            <label htmlFor={fieldId} className="block text-sm font-medium text-neutral-700 dark:text-neutral-300">
                Valor
            </label>
            <input id={fieldId} type="text" name="value" defaultValue={initial} className={inputStyles} />
        </div>
    );
}

function ParameterCard({ item }: { item: ParameterItem }) {
    return (
        <article className="rounded-xl bg-white p-6 shadow-sm dark:bg-neutral-900">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div className="flex flex-wrap items-center gap-2">
                        <h4 className="text-sm font-semibold text-neutral-900 dark:text-neutral-100">
                            {item.description}
                        </h4>
                        {item.has_admin_value && (
                            <span className="inline-block rounded-lg bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800 dark:bg-blue-900 dark:text-blue-100">
                                Administrado
                            </span>
                        )}
                    </div>
                    <p className="mt-1 font-mono text-xs text-neutral-400 dark:text-neutral-500">{item.key}</p>
                    <p className="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                        Padrão: {item.default_value ?? '—'}
                    </p>
                </div>
                <Link
                    href={`/gestao/parametros/${item.key}/historico`}
                    className="rounded-lg border border-blue-300 px-3 py-1 text-xs font-medium text-blue-700 transition hover:bg-blue-50 dark:border-blue-800 dark:text-blue-400 dark:hover:bg-blue-950"
                >
                    Histórico
                </Link>
            </div>

            <Form
                action={`/gestao/parametros/${item.key}`}
                method="put"
                className="mt-4 border-t border-neutral-100 pt-4 dark:border-neutral-800"
            >
                {({ errors, processing }) => (
                    <div className="flex flex-col gap-3">
                        <ValueField item={item} />
                        <FieldError message={errors.value} />
                        <div>
                            <button
                                type="submit"
                                disabled={processing}
                                className="rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-blue-600 dark:hover:bg-blue-500"
                            >
                                {processing ? 'Salvando...' : 'Salvar'}
                            </button>
                        </div>
                    </div>
                )}
            </Form>

            {item.requires_connection_test && (
                <p className="mt-3 text-xs text-neutral-400 dark:text-neutral-500">
                    Teste de conexão disponível quando a integração for configurada (Fase 13).
                </p>
            )}
        </article>
    );
}

export default function ParametersIndex({ groups }: ParametersIndexProps) {
    return (
        <GestaoLayout>
            <Head title="Parâmetros do sistema" />
            <div className="flex flex-col gap-6">
                <div>
                    <h2 className="text-xl font-semibold tracking-tight text-neutral-900 dark:text-neutral-100">
                        Parâmetros do sistema
                    </h2>
                    <p className="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                        Alterações valem imediatamente, sem novo deploy, e ficam registradas no histórico auditado
                    </p>
                </div>

                {Object.entries(groups).map(([group, items]) => (
                    <section key={group} className="flex flex-col gap-4">
                        <h3 className="text-base font-semibold text-neutral-900 dark:text-neutral-100">
                            {groupLabel(group)}
                        </h3>
                        {items.map((item) => (
                            <ParameterCard key={item.key} item={item} />
                        ))}
                    </section>
                ))}
            </div>
        </GestaoLayout>
    );
}
