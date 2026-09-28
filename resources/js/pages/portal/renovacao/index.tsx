import { Head, Link } from '@inertiajs/react';
import PageHeader from '@/components/app/page-header';
import Badge from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import EmptyState from '@/components/ui/empty-state';
import PortalLayout from '@/layouts/portal-layout';

interface TvlItem {
    id: number;
    elegivel: boolean;
    motivo: string | null;
    numero: string | null;
    validade: string | null;
    tipo: string | null;
    empresa: string | null;
    endereco: string | null;
}

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    const match = value.match(/^(\d{4})-(\d{2})-(\d{2})/);

    if (!match) {
        return value;
    }

    return `${match[3]}/${match[2]}/${match[1]}`;
}

export default function RenovacaoIndex({ tvls }: { tvls: TvlItem[] }) {
    return (
        <PortalLayout>
            <Head title="Renovar TVL" />
            <PageHeader title="Renovar TVL" breadcrumbs={[{ label: 'Meu painel', href: '/portal/painel' }]} />

            <p className="mb-6 max-w-2xl text-sm text-gray-600 dark:text-gray-400">
                Escolha o TVL provisório emitido por este portal. Endereço e atividades não mudam na renovação. TVL do SIGS continua sendo renovado no SIGS.
            </p>

            {tvls.length === 0 ? (
                <Card>
                    <CardContent>
                        <EmptyState
                            title="Nenhum TVL para renovar"
                            description="Quando uma viabilidade provisória emitida por este portal estiver deferida, ela aparece aqui. TVL do SIGS continua sendo renovado no SIGS."
                        />
                    </CardContent>
                </Card>
            ) : (
                <ul className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    {tvls.map((tvl) => (
                        <li key={tvl.id}>
                            <Card>
                                <CardContent className="flex h-full flex-col gap-4">
                                    <div className="flex flex-wrap items-start justify-between gap-2">
                                        <div>
                                            <p className="text-sm text-gray-500 dark:text-gray-400">TVL {tvl.numero}</p>
                                            <h2 className="text-base font-semibold text-gray-800 dark:text-white/90">{tvl.empresa}</h2>
                                        </div>
                                        <Badge color={tvl.elegivel ? 'success' : 'warning'} variant="light">
                                            {tvl.tipo ?? 'TVL'}
                                        </Badge>
                                    </div>
                                    <dl className="grid grid-cols-1 gap-2 text-sm">
                                        <div>
                                            <dt className="text-gray-500 dark:text-gray-400">Endereço</dt>
                                            <dd className="text-gray-800 dark:text-white/90">{tvl.endereco ?? '—'}</dd>
                                        </div>
                                        <div>
                                            <dt className="text-gray-500 dark:text-gray-400">Validade</dt>
                                            <dd className="text-gray-800 dark:text-white/90">{formatDate(tvl.validade)}</dd>
                                        </div>
                                    </dl>
                                    {tvl.elegivel ? (
                                        <Link
                                            href={`/portal/renovacao/${tvl.id}`}
                                            className="mt-auto inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-brand-500 px-5 py-3.5 text-sm font-medium text-white transition hover:bg-brand-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500"
                                        >
                                            Renovar
                                        </Link>
                                    ) : (
                                        <p className="mt-auto text-sm text-gray-600 dark:text-gray-300">{tvl.motivo}</p>
                                    )}
                                </CardContent>
                            </Card>
                        </li>
                    ))}
                </ul>
            )}
        </PortalLayout>
    );
}
