import { Head, Link } from '@inertiajs/react';
import GestaoLayout from '@/layouts/gestao-layout';

interface HistoryEntry {
    id: number;
    valor_anterior: string | null;
    valor_novo: string | null;
    responsavel: string | null;
    data: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface ParameterHistoryProps {
    parameter: {
        key: string;
        description: string;
        sensitive: boolean;
    };
    entries: {
        data: HistoryEntry[];
        links: PaginationLink[];
    };
}

function HistoryValue({ value }: { value: string | null }) {
    if (value === '[criptografado]') {
        return (
            <span className="inline-block rounded-lg bg-neutral-200 px-2 py-0.5 text-xs font-medium text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300">
                [criptografado]
            </span>
        );
    }

    return <>{value ?? '—'}</>;
}

function HistoryTable({ entries }: { entries: ParameterHistoryProps['entries'] }) {
    if (entries.data.length === 0) {
        return (
            <p className="mt-3 text-sm text-neutral-500 dark:text-neutral-400">
                Nenhuma alteração registrada.
            </p>
        );
    }

    return (
        <>
            <div className="mt-3 overflow-x-auto">
                <table className="w-full text-left text-sm">
                    <thead>
                        <tr className="border-b border-neutral-200 text-neutral-500 dark:border-neutral-800 dark:text-neutral-400">
                            <th className="py-2 pr-4 font-medium">Data/hora</th>
                            <th className="py-2 pr-4 font-medium">Responsável</th>
                            <th className="py-2 pr-4 font-medium">Valor anterior</th>
                            <th className="py-2 font-medium">Valor novo</th>
                        </tr>
                    </thead>
                    <tbody>
                        {entries.data.map((entry) => (
                            <tr
                                key={entry.id}
                                className="border-b border-neutral-100 text-neutral-900 last:border-0 dark:border-neutral-800 dark:text-neutral-100"
                            >
                                <td className="py-2.5 pr-4">{new Date(entry.data).toLocaleString('pt-BR')}</td>
                                <td className="py-2.5 pr-4">{entry.responsavel ?? '—'}</td>
                                <td className="py-2.5 pr-4">
                                    <HistoryValue value={entry.valor_anterior} />
                                </td>
                                <td className="py-2.5">
                                    <HistoryValue value={entry.valor_novo} />
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            {entries.links.length > 3 && (
                <nav className="mt-4 flex flex-wrap gap-1">
                    {entries.links.map((link, index) =>
                        link.url ? (
                            <Link
                                key={index}
                                href={link.url}
                                className={`rounded-lg px-3 py-1.5 text-sm transition ${
                                    link.active
                                        ? 'bg-blue-700 font-medium text-white dark:bg-blue-600'
                                        : 'text-neutral-700 hover:bg-neutral-200 dark:text-neutral-300 dark:hover:bg-neutral-800'
                                }`}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ) : (
                            <span
                                key={index}
                                className="rounded-lg px-3 py-1.5 text-sm text-neutral-400 dark:text-neutral-600"
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ),
                    )}
                </nav>
            )}
        </>
    );
}

export default function ParameterHistory({ parameter, entries }: ParameterHistoryProps) {
    return (
        <GestaoLayout>
            <Head title={`Histórico — ${parameter.description}`} />
            <div className="flex flex-col gap-6">
                <div>
                    <h2 className="text-xl font-semibold tracking-tight text-neutral-900 dark:text-neutral-100">
                        Histórico — {parameter.description}
                    </h2>
                    <p className="mt-1 font-mono text-xs text-neutral-400 dark:text-neutral-500">{parameter.key}</p>
                </div>

                <section className="rounded-xl bg-white p-6 shadow-sm dark:bg-neutral-900">
                    <h3 className="text-base font-semibold text-neutral-900 dark:text-neutral-100">
                        Alterações registradas
                    </h3>
                    <p className="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                        Valor anterior, valor novo, responsável e data/hora de cada alteração.
                    </p>
                    <HistoryTable entries={entries} />
                </section>

                <div>
                    <Link
                        href="/gestao/parametros"
                        className="text-sm font-medium text-blue-700 hover:underline dark:text-blue-400"
                    >
                        Voltar para parâmetros
                    </Link>
                </div>
            </div>
        </GestaoLayout>
    );
}
