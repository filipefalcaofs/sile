import { Link } from '@inertiajs/react';
import Badge from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';

export interface LegadoItem {
    numero: number;
    empresa_id: number;
    empresa: string;
    validade: string | null;
    situacao: 'vencida' | 'a_vencer' | 'vigente' | null;
    dias_para_vencer: number | null;
    endereco: string;
    elegivel: boolean;
    motivo: string | null;
    origem: 'legado';
}

export function formatarData(value: string | null): string {
    if (!value) {
        return '—';
    }

    const match = value.match(/^(\d{4})-(\d{2})-(\d{2})/);

    return match ? `${match[3]}/${match[2]}/${match[1]}` : value;
}

function situacaoTexto(item: LegadoItem): string | null {
    if (item.situacao === 'vencida') {
        return 'Vencida';
    }

    if (item.situacao === 'a_vencer') {
        const dias = item.dias_para_vencer;

        return dias === null ? 'A vencer' : `A vencer em ${dias} ${dias === 1 ? 'dia' : 'dias'}`;
    }

    return item.situacao === 'vigente' ? 'Vigente' : null;
}

function situacaoCor(situacao: LegadoItem['situacao']): 'error' | 'warning' | 'success' | 'light' {
    if (situacao === 'vencida') {
        return 'error';
    }

    if (situacao === 'a_vencer') {
        return 'warning';
    }

    return situacao === 'vigente' ? 'success' : 'light';
}

export default function LegadoItemCard({ item }: { item: LegadoItem }) {
    const situacao = situacaoTexto(item);

    return (
        <Card>
            <CardContent className="flex h-full flex-col gap-4">
                <div className="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <p className="text-sm text-gray-500 dark:text-gray-400">Viabilidade {item.numero}</p>
                        <h2 className="text-base font-semibold text-gray-800 dark:text-white/90">{item.empresa}</h2>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Badge color="info" variant="light">
                            Sistema anterior
                        </Badge>
                        {situacao && (
                            <Badge color={situacaoCor(item.situacao)} variant="light">
                                {situacao}
                            </Badge>
                        )}
                    </div>
                </div>
                <dl className="grid grid-cols-1 gap-2 text-sm">
                    <div>
                        <dt className="text-gray-500 dark:text-gray-400">Endereço</dt>
                        <dd className="text-gray-800 dark:text-white/90">{item.endereco || '—'}</dd>
                    </div>
                    <div>
                        <dt className="text-gray-500 dark:text-gray-400">Validade</dt>
                        <dd className="text-gray-800 dark:text-white/90">{formatarData(item.validade)}</dd>
                    </div>
                </dl>
                {item.elegivel ? (
                    <Link
                        href={`/portal/renovacao/legado/${item.empresa_id}/${item.numero}`}
                        className="mt-auto inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-brand-500 px-5 py-3.5 text-sm font-medium text-white transition hover:bg-brand-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500"
                    >
                        Renovar
                    </Link>
                ) : (
                    <p className="mt-auto text-sm text-gray-600 dark:text-gray-300">{item.motivo}</p>
                )}
            </CardContent>
        </Card>
    );
}
