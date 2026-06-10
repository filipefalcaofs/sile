import { Head, Link } from '@inertiajs/react';
import GestaoLayout from '@/layouts/gestao-layout';

interface AccessLogItem {
    id: number;
    event: string;
    ip_address: string | null;
    channel: string | null;
    created_at: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface AcessosProps {
    targetUser: {
        id: number;
        name: string;
        email: string;
    };
    logs: {
        data: AccessLogItem[];
        links: PaginationLink[];
    };
}

const eventConfig: Record<string, { label: string; styles: string }> = {
    login: {
        label: 'Login',
        styles: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-100',
    },
    logout: {
        label: 'Saída',
        styles: 'bg-neutral-200 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300',
    },
    falha: {
        label: 'Tentativa falha',
        styles: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-100',
    },
    bloqueio: {
        label: 'Bloqueio temporário',
        styles: 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-100',
    },
};

function EventBadge({ event }: { event: string }) {
    const config = eventConfig[event] ?? {
        label: event,
        styles: 'bg-neutral-200 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300',
    };

    return (
        <span className={`inline-block rounded-lg px-2 py-0.5 text-xs font-medium ${config.styles}`}>
            {config.label}
        </span>
    );
}

function AccessLogTable({ logs }: { logs: AcessosProps['logs'] }) {
    if (logs.data.length === 0) {
        return (
            <p className="mt-3 text-sm text-neutral-500 dark:text-neutral-400">
                Nenhum acesso registrado ainda.
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
                            <th className="py-2 pr-4 font-medium">Evento</th>
                            <th className="py-2 pr-4 font-medium">IP</th>
                            <th className="py-2 font-medium">Canal</th>
                        </tr>
                    </thead>
                    <tbody>
                        {logs.data.map((log) => (
                            <tr
                                key={log.id}
                                className="border-b border-neutral-100 text-neutral-900 last:border-0 dark:border-neutral-800 dark:text-neutral-100"
                            >
                                <td className="py-2.5 pr-4">
                                    {new Date(log.created_at).toLocaleString('pt-BR')}
                                </td>
                                <td className="py-2.5 pr-4">
                                    <EventBadge event={log.event} />
                                </td>
                                <td className="py-2.5 pr-4">{log.ip_address ?? '—'}</td>
                                <td className="py-2.5">{log.channel ?? '—'}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            {logs.links.length > 3 && (
                <nav className="mt-4 flex flex-wrap gap-1">
                    {logs.links.map((link, index) =>
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

export default function Acessos({ targetUser, logs }: AcessosProps) {
    return (
        <GestaoLayout>
            <Head title={`Acessos de ${targetUser.name}`} />
            <div className="flex flex-col gap-6">
                <h2 className="text-xl font-semibold tracking-tight text-neutral-900 dark:text-neutral-100">
                    Acessos de {targetUser.name}
                </h2>

                <section className="rounded-xl bg-white p-6 shadow-sm dark:bg-neutral-900">
                    <h3 className="text-base font-semibold text-neutral-900 dark:text-neutral-100">
                        Histórico de acessos
                    </h3>
                    <p className="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                        Logins, saídas, tentativas falhas e bloqueios registrados na conta de{' '}
                        {targetUser.email}.
                    </p>
                    <AccessLogTable logs={logs} />
                </section>
            </div>
        </GestaoLayout>
    );
}
