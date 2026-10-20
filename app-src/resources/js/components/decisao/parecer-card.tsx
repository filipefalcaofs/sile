import Badge from '@/components/ui/badge';
import { Card, CardContent, CardHeader } from '@/components/ui/card';

export interface ParecerDecisao {
    outcome: 'deferida' | 'indeferida' | string;
    outcome_label: string;
    motivo: string | null;
    fundamentacao: string[];
    decidido_em: string | null;
    /** Tipo do deferimento (Definitiva, Provisória, Pré-operacional) e validade em AAAA-MM-DD. */
    tipo?: string | null;
    valido_ate?: string | null;
}

function dataCurta(iso: string): string {
    const [ano, mes, dia] = iso.split('-');

    return `${dia}/${mes}/${ano}`;
}

interface ParecerCardProps {
    parecer: ParecerDecisao;
}

/**
 * Parecer da decisão: resultado, motivo e fundamentação legal. No
 * indeferimento o motivo é obrigatório e aparece primeiro.
 */
export default function ParecerCard({ parecer }: ParecerCardProps) {
    const indeferida = parecer.outcome === 'indeferida';

    return (
        <Card>
            <CardHeader
                title="Parecer"
                actions={
                    <Badge size="sm" color={indeferida ? 'error' : 'success'}>
                        {parecer.outcome_label}
                    </Badge>
                }
            />
            <CardContent>
                <div className="flex flex-col gap-5">
                    {(parecer.tipo || parecer.valido_ate) && (
                        <p className="text-sm text-gray-700 dark:text-gray-300">
                            {parecer.tipo && <span>Tipo: {parecer.tipo}</span>}
                            {parecer.tipo && parecer.valido_ate && <span> · </span>}
                            {parecer.valido_ate && <span>Válido até {dataCurta(parecer.valido_ate)}</span>}
                        </p>
                    )}
                    {parecer.motivo && (
                        <section aria-labelledby="parecer-motivo">
                            <h4 id="parecer-motivo" className="text-sm font-medium text-gray-800 dark:text-white/90">
                                {indeferida ? 'Motivo do indeferimento' : 'Motivo'}
                            </h4>
                            <p className="mt-2 whitespace-pre-line text-sm leading-relaxed text-gray-700 dark:text-gray-300">
                                {parecer.motivo}
                            </p>
                        </section>
                    )}

                    {parecer.fundamentacao.length > 0 && (
                        <section aria-labelledby="parecer-fundamentacao">
                            <h4
                                id="parecer-fundamentacao"
                                className="text-sm font-medium text-gray-800 dark:text-white/90"
                            >
                                Fundamentação legal
                            </h4>
                            <ul className="mt-2 list-disc space-y-1 pl-5 text-sm text-gray-700 dark:text-gray-300">
                                {parecer.fundamentacao.map((linha) => (
                                    <li key={linha}>{linha}</li>
                                ))}
                            </ul>
                        </section>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}
