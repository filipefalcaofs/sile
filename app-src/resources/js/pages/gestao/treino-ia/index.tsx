import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import PageHeader from '@/components/app/page-header';
import { Card, CardContent } from '@/components/ui/card';
import EmptyState from '@/components/ui/empty-state';
import GestaoLayout from '@/layouts/gestao-layout';

interface Execucao {
    execucao: string;
    gerada_em: string | null;
    lotes: number;
    variantes: string[];
    mediana_iou_por_variante: Record<string, number | null>;
}

interface TreinoIaIndexProps {
    execucoes: Execucao[];
}

function iou(valor: number | null | undefined): string {
    return valor === null || valor === undefined ? '—' : valor.toFixed(3);
}

export default function TreinoIaIndex({ execucoes }: TreinoIaIndexProps) {
    return (
        <>
            <Head title="Treino da IA de contorno" />
            <PageHeader title="Treino da IA de contorno" breadcrumbs={[{ label: 'Painel', href: '/gestao' }]} />
            <Card>
                <CardContent>
                    {execucoes.length === 0 ? (
                        <EmptyState
                            title="Nenhuma galeria gerada"
                            description="As galerias aparecem aqui depois que a avaliação do treino roda no servidor (python -m treino_ia galeria)."
                        />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-theme-sm">
                                <thead>
                                    <tr className="text-left text-gray-500 dark:text-gray-400">
                                        <th className="py-2 pr-4">Execução</th>
                                        <th className="py-2 pr-4">Gerada em</th>
                                        <th className="py-2 pr-4">Lotes</th>
                                        <th className="py-2 pr-4">IoU mediana por variante</th>
                                        <th className="py-2" />
                                    </tr>
                                </thead>
                                <tbody>
                                    {execucoes.map((e) => (
                                        <tr key={e.execucao} className="border-t border-gray-100 dark:border-gray-800">
                                            <td className="py-2 pr-4 font-medium text-gray-800 dark:text-white/90">{e.execucao}</td>
                                            <td className="py-2 pr-4">{e.gerada_em ?? '—'}</td>
                                            <td className="py-2 pr-4">{e.lotes}</td>
                                            <td className="py-2 pr-4">
                                                {e.variantes.map((v) => `${v}: ${iou(e.mediana_iou_por_variante[v])}`).join(' · ')}
                                            </td>
                                            <td className="py-2">
                                                <a
                                                    href={`/gestao/treino-ia/${encodeURIComponent(e.execucao)}/index.html`}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="text-brand-500 hover:underline"
                                                >
                                                    Abrir galeria
                                                </a>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </CardContent>
            </Card>
        </>
    );
}

TreinoIaIndex.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
