import { Form, Head, Link } from '@inertiajs/react';
import PortalLayout from '@/layouts/portal-layout';

interface ProcurationItem {
    id: number;
    name: string;
    email: string;
    starts_at: string;
    expires_at: string | null;
    revoked_at: string | null;
    is_active: boolean;
}

interface ProcuracoesIndexProps {
    granted: ProcurationItem[];
    received: ProcurationItem[];
}

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString('pt-BR');
}

function situationLabel(item: ProcurationItem): string {
    if (item.revoked_at) {
        return 'Revogada';
    }

    return item.is_active ? 'Ativa' : 'Expirada';
}

function SituationBadge({ item }: { item: ProcurationItem }) {
    const label = situationLabel(item);
    const styles =
        label === 'Ativa'
            ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-100'
            : label === 'Revogada'
              ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-100'
              : 'bg-neutral-200 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300';

    return (
        <span className={`inline-block rounded-lg px-2 py-0.5 text-xs font-medium ${styles}`}>
            {label}
        </span>
    );
}

export default function ProcuracoesIndex({ granted, received }: ProcuracoesIndexProps) {
    return (
        <PortalLayout>
            <Head title="Minhas procurações" />
            <div className="flex flex-col gap-6">
                <h2 className="text-xl font-semibold tracking-tight text-neutral-900 dark:text-neutral-100">
                    Minhas procurações
                </h2>

                <section className="rounded-xl bg-white p-6 shadow-sm dark:bg-neutral-900">
                    <h3 className="text-base font-semibold text-neutral-900 dark:text-neutral-100">
                        Vincular procurador
                    </h3>
                    <p className="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                        O procurador precisa ter conta no SILE. Informe o e-mail cadastrado e,
                        se desejar, uma data de validade para a procuração.
                    </p>

                    <Form action="/portal/procuracoes" method="post" className="mt-4">
                        {({ errors, processing }) => (
                            <div className="flex flex-col gap-4 sm:flex-row sm:items-end">
                                <div className="flex-1">
                                    <label
                                        htmlFor="attorney_email"
                                        className="block text-sm font-medium text-neutral-700 dark:text-neutral-300"
                                    >
                                        E-mail do procurador
                                    </label>
                                    <input
                                        id="attorney_email"
                                        type="email"
                                        name="attorney_email"
                                        required
                                        className="mt-1 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-neutral-900 focus:border-blue-600 focus:ring-2 focus:ring-blue-600/20 focus:outline-none dark:border-neutral-700 dark:bg-neutral-950 dark:text-neutral-100"
                                    />
                                    {errors.attorney_email && (
                                        <p className="mt-1 text-sm text-red-600 dark:text-red-400">
                                            {errors.attorney_email}
                                        </p>
                                    )}
                                </div>
                                <div>
                                    <label
                                        htmlFor="expires_at"
                                        className="block text-sm font-medium text-neutral-700 dark:text-neutral-300"
                                    >
                                        Validade (opcional)
                                    </label>
                                    <input
                                        id="expires_at"
                                        type="date"
                                        name="expires_at"
                                        className="mt-1 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-neutral-900 focus:border-blue-600 focus:ring-2 focus:ring-blue-600/20 focus:outline-none dark:border-neutral-700 dark:bg-neutral-950 dark:text-neutral-100"
                                    />
                                    {errors.expires_at && (
                                        <p className="mt-1 text-sm text-red-600 dark:text-red-400">
                                            {errors.expires_at}
                                        </p>
                                    )}
                                </div>
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-blue-600 dark:hover:bg-blue-500"
                                >
                                    {processing ? 'Vinculando...' : 'Vincular'}
                                </button>
                            </div>
                        )}
                    </Form>
                </section>

                <section className="rounded-xl bg-white p-6 shadow-sm dark:bg-neutral-900">
                    <h3 className="text-base font-semibold text-neutral-900 dark:text-neutral-100">
                        Procurações outorgadas
                    </h3>
                    {granted.length === 0 ? (
                        <p className="mt-3 text-sm text-neutral-500 dark:text-neutral-400">
                            Você ainda não outorgou procurações.
                        </p>
                    ) : (
                        <div className="mt-3 overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead>
                                    <tr className="border-b border-neutral-200 text-neutral-500 dark:border-neutral-800 dark:text-neutral-400">
                                        <th className="py-2 pr-4 font-medium">Procurador</th>
                                        <th className="py-2 pr-4 font-medium">E-mail</th>
                                        <th className="py-2 pr-4 font-medium">Início</th>
                                        <th className="py-2 pr-4 font-medium">Validade</th>
                                        <th className="py-2 pr-4 font-medium">Situação</th>
                                        <th className="py-2 font-medium">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {granted.map((item) => (
                                        <tr
                                            key={item.id}
                                            className="border-b border-neutral-100 text-neutral-900 last:border-0 dark:border-neutral-800 dark:text-neutral-100"
                                        >
                                            <td className="py-2.5 pr-4">{item.name}</td>
                                            <td className="py-2.5 pr-4">{item.email}</td>
                                            <td className="py-2.5 pr-4">{formatDate(item.starts_at)}</td>
                                            <td className="py-2.5 pr-4">{formatDate(item.expires_at)}</td>
                                            <td className="py-2.5 pr-4">
                                                <SituationBadge item={item} />
                                            </td>
                                            <td className="py-2.5">
                                                {item.is_active ? (
                                                    <Link
                                                        href={`/portal/procuracoes/${item.id}`}
                                                        method="delete"
                                                        as="button"
                                                        className="rounded-lg border border-red-300 px-3 py-1 text-xs font-medium text-red-700 transition hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-950"
                                                    >
                                                        Revogar
                                                    </Link>
                                                ) : (
                                                    <span className="text-neutral-400 dark:text-neutral-500">—</span>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>

                <section className="rounded-xl bg-white p-6 shadow-sm dark:bg-neutral-900">
                    <h3 className="text-base font-semibold text-neutral-900 dark:text-neutral-100">
                        Procurações recebidas
                    </h3>
                    {received.length === 0 ? (
                        <p className="mt-3 text-sm text-neutral-500 dark:text-neutral-400">
                            Você ainda não recebeu procurações.
                        </p>
                    ) : (
                        <div className="mt-3 overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead>
                                    <tr className="border-b border-neutral-200 text-neutral-500 dark:border-neutral-800 dark:text-neutral-400">
                                        <th className="py-2 pr-4 font-medium">Outorgante</th>
                                        <th className="py-2 pr-4 font-medium">E-mail</th>
                                        <th className="py-2 pr-4 font-medium">Situação</th>
                                        <th className="py-2 font-medium">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {received.map((item) => (
                                        <tr
                                            key={item.id}
                                            className="border-b border-neutral-100 text-neutral-900 last:border-0 dark:border-neutral-800 dark:text-neutral-100"
                                        >
                                            <td className="py-2.5 pr-4">{item.name}</td>
                                            <td className="py-2.5 pr-4">{item.email}</td>
                                            <td className="py-2.5 pr-4">
                                                <SituationBadge item={item} />
                                            </td>
                                            <td className="py-2.5">
                                                {item.is_active ? (
                                                    <Link
                                                        href="/portal/representacao"
                                                        method="post"
                                                        data={{ procuration_id: item.id }}
                                                        as="button"
                                                        className="rounded-lg border border-blue-300 px-3 py-1 text-xs font-medium text-blue-700 transition hover:bg-blue-50 dark:border-blue-800 dark:text-blue-400 dark:hover:bg-blue-950"
                                                    >
                                                        Atuar em nome de
                                                    </Link>
                                                ) : (
                                                    <span className="text-neutral-400 dark:text-neutral-500">—</span>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>
            </div>
        </PortalLayout>
    );
}
