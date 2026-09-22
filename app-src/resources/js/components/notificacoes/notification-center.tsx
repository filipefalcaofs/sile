import { router } from '@inertiajs/react';
import { useState } from 'react';
import { BellIcon, CheckCircleIcon } from '@/components/icons';
import Badge from '@/components/ui/badge';
import Button from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import EmptyState from '@/components/ui/empty-state';
import Pagination, { type PaginationLink } from '@/components/ui/pagination';

interface NotificationData {
    title?: string;
    summary?: string;
    url?: string;
}

export interface NotificationItem {
    id: string;
    type: string;
    data: NotificationData;
    lida: boolean;
    read_at: string | null;
    created_at: string | null;
}

export interface NotificationList {
    data: NotificationItem[];
    links: PaginationLink[];
    from: number | null;
    to: number | null;
    total: number;
}

interface NotificationCenterProps {
    lista: NotificationList;
    /** Base do ambiente (`/portal` ou `/gestao`) para as rotas de marcação. */
    basePath: string;
}

/** Formata ISO 8601 como 'DD/MM/YYYY HH:MM' sem depender do fuso do navegador. */
function formatDateTime(value: string | null): string {
    if (!value) {
        return '—';
    }

    const match = value.match(/^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})/);

    if (!match) {
        return value;
    }

    const [, year, month, day, hour, minute] = match;

    return `${day}/${month}/${year} ${hour}:${minute}`;
}

function NotificationCard({
    notificacao,
    basePath,
    onMarkAsRead,
    marking,
}: {
    notificacao: NotificationItem;
    basePath: string;
    onMarkAsRead: (id: string) => void;
    marking: boolean;
}) {
    const titulo = notificacao.data.title ?? 'Notificação';

    return (
        <li
            className={`flex flex-col gap-3 rounded-xl border p-4 sm:flex-row sm:items-start sm:justify-between ${
                notificacao.lida
                    ? 'border-gray-200 dark:border-gray-800'
                    : 'border-brand-200 bg-brand-50/40 dark:border-brand-500/30 dark:bg-brand-500/5'
            }`}
        >
            <div className="flex items-start gap-3">
                {!notificacao.lida && (
                    <span
                        aria-hidden="true"
                        className="mt-1.5 size-2 shrink-0 rounded-full bg-brand-500"
                    />
                )}
                <div className={notificacao.lida ? 'sm:pl-5' : ''}>
                    <p className="text-sm font-medium text-gray-800 dark:text-white/90">{titulo}</p>
                    {notificacao.data.summary && (
                        <p className="mt-0.5 text-theme-sm text-gray-500 dark:text-gray-400">
                            {notificacao.data.summary}
                        </p>
                    )}
                    <p className="mt-1 text-theme-xs text-gray-400 dark:text-gray-500">
                        {formatDateTime(notificacao.created_at)}
                    </p>
                </div>
            </div>

            <div className="flex shrink-0 items-center gap-2">
                {notificacao.data.url && (
                    <a
                        href={notificacao.data.url}
                        className="inline-flex items-center rounded-lg px-3 py-2 text-theme-xs font-medium text-brand-500 ring-1 ring-inset ring-brand-200 transition hover:bg-brand-50 dark:text-brand-400 dark:ring-brand-500/30 dark:hover:bg-brand-500/10"
                    >
                        Abrir
                    </a>
                )}
                {notificacao.lida ? (
                    <Badge size="sm" color="light">
                        Lida
                    </Badge>
                ) : (
                    <Button
                        size="xs"
                        variant="outline"
                        onClick={() => onMarkAsRead(notificacao.id)}
                        loading={marking}
                        startIcon={<CheckCircleIcon className="size-4" />}
                    >
                        Marcar como lida
                    </Button>
                )}
            </div>
        </li>
    );
}

/**
 * Central de notificações in-app (HU-090): lista server-driven do canal database
 * nativo do usuário, separando não-lidas e lidas, com marcação de uma e de todas
 * via os endpoints reais do 11-08 (Inertia POST → back recarrega lista e badge).
 * Estado vazio honesto; nada é simulado.
 */
export default function NotificationCenter({ lista, basePath }: NotificationCenterProps) {
    const [markingId, setMarkingId] = useState<string | null>(null);
    const [markingAll, setMarkingAll] = useState(false);

    const naoLidas = lista.data.filter((item) => !item.lida);
    const lidas = lista.data.filter((item) => item.lida);
    const temNaoLidas = naoLidas.length > 0;

    function marcarComoLida(id: string) {
        router.post(
            `${basePath}/notificacoes/${id}/ler`,
            {},
            {
                preserveScroll: true,
                onStart: () => setMarkingId(id),
                onFinish: () => setMarkingId(null),
            },
        );
    }

    function marcarTodas() {
        router.post(
            `${basePath}/notificacoes/ler-todas`,
            {},
            {
                preserveScroll: true,
                onStart: () => setMarkingAll(true),
                onFinish: () => setMarkingAll(false),
            },
        );
    }

    return (
        <Card>
            <CardHeader
                title="Suas notificações"
                description="Acompanhe os avisos do seu processo. Marque como lida para limpar o sininho."
                actions={
                    temNaoLidas ? (
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={marcarTodas}
                            loading={markingAll}
                            startIcon={<CheckCircleIcon className="size-4" />}
                        >
                            Marcar todas como lidas
                        </Button>
                    ) : undefined
                }
            />
            <CardContent>
                {lista.data.length === 0 ? (
                    <EmptyState
                        icon={<BellIcon className="size-6" />}
                        title="Nenhuma notificação"
                        description="Você ainda não recebeu notificações. Os avisos do seu processo aparecerão aqui."
                    />
                ) : (
                    <div className="flex flex-col gap-6">
                        {temNaoLidas && (
                            <section aria-label="Não lidas">
                                <h4 className="mb-3 text-theme-xs font-semibold tracking-wide text-gray-400 uppercase dark:text-gray-500">
                                    Não lidas ({naoLidas.length})
                                </h4>
                                <ul className="flex flex-col gap-3">
                                    {naoLidas.map((notificacao) => (
                                        <NotificationCard
                                            key={notificacao.id}
                                            notificacao={notificacao}
                                            basePath={basePath}
                                            onMarkAsRead={marcarComoLida}
                                            marking={markingId === notificacao.id}
                                        />
                                    ))}
                                </ul>
                            </section>
                        )}

                        {lidas.length > 0 && (
                            <section aria-label="Lidas">
                                <h4 className="mb-3 text-theme-xs font-semibold tracking-wide text-gray-400 uppercase dark:text-gray-500">
                                    Lidas
                                </h4>
                                <ul className="flex flex-col gap-3">
                                    {lidas.map((notificacao) => (
                                        <NotificationCard
                                            key={notificacao.id}
                                            notificacao={notificacao}
                                            basePath={basePath}
                                            onMarkAsRead={marcarComoLida}
                                            marking={markingId === notificacao.id}
                                        />
                                    ))}
                                </ul>
                            </section>
                        )}

                        <Pagination
                            links={lista.links}
                            meta={{ from: lista.from, to: lista.to, total: lista.total }}
                        />
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
