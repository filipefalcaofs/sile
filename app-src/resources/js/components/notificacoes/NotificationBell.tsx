import { Link, usePage } from '@inertiajs/react';
import { BellIcon } from '@/components/icons';
import type { SharedProps } from '@/types';

interface NotificationBellProps {
    /** Base do ambiente atual (`/portal` ou `/gestao`): define a rota da central. */
    homeHref: string;
}

/**
 * Sininho do cabeçalho (HU-090): lê a contagem REAL de não-lidas do shared prop
 * `notificacoes.nao_lidas` (HandleInertiaRequests) e leva à central do ambiente
 * atual. Sem contagem fictícia — quando não há não-lidas, mostra só o ícone.
 */
export default function NotificationBell({ homeHref }: NotificationBellProps) {
    const { notificacoes } = usePage<SharedProps>().props;
    const naoLidas = notificacoes?.nao_lidas ?? 0;
    const temNaoLidas = naoLidas > 0;
    const rotulo = temNaoLidas
        ? `Notificações: ${naoLidas} não lida(s)`
        : 'Notificações';
    const contador = naoLidas > 99 ? '99+' : String(naoLidas);

    return (
        <Link
            href={`${homeHref}/notificacoes`}
            aria-label={rotulo}
            title={rotulo}
            className="relative flex h-11 w-11 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white"
        >
            <BellIcon className="size-5" />
            {temNaoLidas && (
                <span
                    aria-hidden="true"
                    className="absolute -top-0.5 -right-0.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-error-500 px-1 text-[10px] font-semibold leading-none text-white"
                >
                    {contador}
                </span>
            )}
        </Link>
    );
}
