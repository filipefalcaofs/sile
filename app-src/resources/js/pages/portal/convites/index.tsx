import { Head } from '@inertiajs/react';
import PageHeader from '@/components/app/page-header';
import { BellIcon } from '@/components/icons';
import ConvitesLista, { type ConviteResumo } from '@/components/processo/convites-lista';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import EmptyState from '@/components/ui/empty-state';
import PortalLayout from '@/layouts/portal-layout';

export default function Convites({ convites }: { convites: ConviteResumo[] }) {
    return (
        <PortalLayout>
            <Head title="Responder convite" />
            <PageHeader title="Responder convite" breadcrumbs={[{ label: 'Meu painel', href: '/portal/painel' }]} />

            <Card>
                <CardHeader
                    title="Convites para responder"
                    description="Pedidos da SEDUR nos processos em que você é o requerente. Responda dentro do prazo para a análise seguir."
                />
                <CardContent>
                    {convites.length === 0 ? (
                        <EmptyState
                            icon={<BellIcon className="size-6" aria-hidden="true" />}
                            title="Nenhum convite para responder"
                            description="Quando a SEDUR pedir uma informação ou um documento em um processo seu, o convite aparece aqui."
                        />
                    ) : (
                        <ConvitesLista convites={convites} />
                    )}
                </CardContent>
            </Card>
        </PortalLayout>
    );
}
