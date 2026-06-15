import type { PageProps } from '@inertiajs/core';

export interface SharedProps extends PageProps {
    auth: {
        user: { id: number; name: string; email: string } | null;
        roles: string[];
        permissions: string[];
    };
    actingFor: { id: number; name: string } | null;
    attendingFor: { id: number; name: string } | null;
    /** Badge do sininho (HU-090): contagem real de não-lidas do usuário autenticado. */
    notificacoes: { nao_lidas: number };
    flash: {
        status?: string;
        error?: string | null;
    };
}
