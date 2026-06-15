import { Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import PageHeader from '@/components/app/page-header';
import HistoricoComunicacoes, { type Comunicacao } from '@/components/comunicacoes/HistoricoComunicacoes';
import { ArrowRightIcon } from '@/components/icons';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import PortalLayout from '@/layouts/portal-layout';

interface ComunicacoesProps {
    processo: { id: number; protocol_number: string | null };
    comunicacoes: Comunicacao[];
}

export default function PortalComunicacoes({ processo, comunicacoes }: ComunicacoesProps) {
    const titulo = processo.protocol_number ?? 'Solicitação de viabilidade';

    return (
        <>
            <Head title={`Comunicações ${titulo}`} />
            <PageHeader
                title="Histórico de comunicações"
                breadcrumbs={[
                    { label: 'Meu painel', href: '/portal/painel' },
                    { label: 'Minhas solicitações', href: '/portal/solicitacoes' },
                    { label: titulo, href: `/portal/solicitacoes/${processo.id}` },
                ]}
            />

            <div className="flex flex-col gap-4 md:gap-6">
                <Link
                    href={`/portal/solicitacoes/${processo.id}`}
                    className="inline-flex items-center gap-1.5 text-theme-sm font-medium text-brand-500 transition hover:text-brand-600 dark:text-brand-400"
                >
                    <ArrowRightIcon className="size-4 rotate-180" />
                    Voltar ao acompanhamento
                </Link>

                <Card>
                    <CardHeader
                        title="Comunicações do processo"
                        description="Todos os avisos enviados sobre esta solicitação, por canal e situação, do mais recente ao mais antigo."
                    />
                    <CardContent>
                        <HistoricoComunicacoes comunicacoes={comunicacoes} />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

PortalComunicacoes.layout = (page: ReactNode) => <PortalLayout>{page}</PortalLayout>;
