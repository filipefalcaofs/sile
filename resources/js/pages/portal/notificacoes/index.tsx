import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import PageHeader from '@/components/app/page-header';
import NotificationCenter, { type NotificationList } from '@/components/notificacoes/notification-center';
import PortalLayout from '@/layouts/portal-layout';

interface PortalNotificacoesProps {
    lista: NotificationList;
}

export default function PortalNotificacoesIndex({ lista }: PortalNotificacoesProps) {
    return (
        <>
            <Head title="Notificações" />
            <PageHeader title="Notificações" breadcrumbs={[{ label: 'Meu painel', href: '/portal/painel' }]} />
            <NotificationCenter lista={lista} basePath="/portal" />
        </>
    );
}

PortalNotificacoesIndex.layout = (page: ReactNode) => <PortalLayout>{page}</PortalLayout>;
