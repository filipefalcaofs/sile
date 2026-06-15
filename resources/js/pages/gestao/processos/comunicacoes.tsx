import { Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import PageHeader from '@/components/app/page-header';
import HistoricoComunicacoes, { type Comunicacao } from '@/components/comunicacoes/HistoricoComunicacoes';
import { ArrowRightIcon } from '@/components/icons';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import GestaoLayout from '@/layouts/gestao-layout';

interface ComunicacoesProps {
    processo: { id: number; protocol_number: string | null };
    comunicacoes: Comunicacao[];
}

export default function GestaoComunicacoes({ processo, comunicacoes }: ComunicacoesProps) {
    const titulo = processo.protocol_number ?? 'Processo';

    return (
        <>
            <Head title={`Comunicações ${titulo}`} />
            <PageHeader
                title="Histórico de comunicações"
                breadcrumbs={[
                    { label: 'Painel', href: '/gestao' },
                    { label: 'Processos', href: '/gestao/processos' },
                    { label: titulo, href: `/gestao/processos/${processo.id}` },
                ]}
            />

            <div className="flex flex-col gap-4 md:gap-6">
                <Link
                    href={`/gestao/processos/${processo.id}`}
                    className="inline-flex items-center gap-1.5 text-theme-sm font-medium text-brand-500 transition hover:text-brand-600 dark:text-brand-400"
                >
                    <ArrowRightIcon className="size-4 rotate-180" />
                    Voltar ao processo
                </Link>

                <Card>
                    <CardHeader
                        title="Comunicações do processo"
                        description="Linha do tempo unificada dos avisos do processo (e-mail, in-app e demais canais), com a situação real de cada envio."
                    />
                    <CardContent>
                        <HistoricoComunicacoes comunicacoes={comunicacoes} />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

GestaoComunicacoes.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
