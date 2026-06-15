import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import PageHeader from '@/components/app/page-header';
import NotificationCenter, { type NotificationList } from '@/components/notificacoes/notification-center';
import GestaoLayout from '@/layouts/gestao-layout';

interface GestaoNotificacoesProps {
    lista: NotificationList;
}

export default function GestaoNotificacoesIndex({ lista }: GestaoNotificacoesProps) {
    return (
        <>
            <Head title="Notificações" />
            <PageHeader title="Notificações" breadcrumbs={[{ label: 'Painel', href: '/gestao' }]} />
            <NotificationCenter lista={lista} basePath="/gestao" />
        </>
    );
}

GestaoNotificacoesIndex.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
