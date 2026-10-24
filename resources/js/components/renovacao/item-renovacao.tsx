import { Link } from '@inertiajs/react';
import { formatarData } from '@/components/renovacao/legado-item';
import Badge from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';

export interface ItemRenovacao {
    origem: 'viabiliza' | 'legado';
    id: number | null;
    numero: string;
    empresa: string | null;
    cnpj: string | null;
    endereco: string | null;
    validade: string | null;
    situacao: 'vencida' | 'a_vencer' | 'vigente' | null;
    dias_para_vencer: number | null;
    tipo?: string | null;
    elegivel: boolean;
    motivo: string | null;
    url: string | null;
}

function situacao(item: ItemRenovacao): { texto: string; cor: 'error' | 'warning' | 'success' } | null {
    if (item.situacao === 'vencida') {
        return { texto: 'Vencida', cor: 'error' };
    }

    if (item.situacao === 'a_vencer') {
        const dias = item.dias_para_vencer;

        return { texto: dias === null ? 'A vencer' : `A vencer em ${dias} ${dias === 1 ? 'dia' : 'dias'}`, cor: 'warning' };
    }

    return item.situacao === 'vigente' ? { texto: 'Vigente', cor: 'success' } : null;
}

/** Viabilidade encontrada na busca da renovação, do Viabiliza ou do sistema anterior. */
export default function ItemRenovacaoCard({ item }: { item: ItemRenovacao }) {
    const selo = situacao(item);

    return (
        <Card>
            <CardContent className="flex h-full flex-col gap-4">
                <div className="flex flex-wrap items-start justify-between gap-2">
                    <div className="min-w-0">
                        <p className="text-sm text-gray-500 dark:text-gray-400">Viabilidade {item.numero}</p>
                        <h2 className="text-base font-semibold text-gray-800 dark:text-white/90">{item.empresa ?? '—'}</h2>
                        {item.cnpj && <p className="text-theme-xs text-gray-500 dark:text-gray-400">{item.cnpj}</p>}
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Badge color={item.origem === 'legado' ? 'info' : 'primary'} variant="light">
                            {item.origem === 'legado' ? 'Sistema anterior' : 'Viabiliza'}
                        </Badge>
                        {selo && (
                            <Badge color={selo.cor} variant="light">
                                {selo.texto}
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
                        <dd className="text-gray-800 dark:text-white/90">
                            {formatarData(item.validade)}
                            {item.tipo ? ` (${item.tipo})` : ''}
                        </dd>
                    </div>
                </dl>
                {item.elegivel && item.url ? (
                    <Link
                        href={item.url}
                        className="mt-auto inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-brand-500 px-5 py-3.5 text-sm font-medium text-white transition hover:bg-brand-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500"
                    >
                        Renovar<span className="sr-only"> a viabilidade {item.numero}</span>
                    </Link>
                ) : (
                    <p className="mt-auto text-sm text-gray-600 dark:text-gray-300">{item.motivo}</p>
                )}
            </CardContent>
        </Card>
    );
}
