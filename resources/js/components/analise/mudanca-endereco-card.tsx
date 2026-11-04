import Badge from '@/components/ui/badge';
import { Card, CardContent, CardHeader } from '@/components/ui/card';

export interface CampoAlterado {
    campo: string;
    antes: string;
    depois: string;
}

export interface MudancaEndereco {
    servico: string;
    processo_anterior: string | null;
    campos: CampoAlterado[];
}

/**
 * Alteração de endereço da JUCEB: o serviço identificado e cada campo que mudou
 * em relação ao último processo da empresa no Viabiliza.
 */
export default function MudancaEnderecoCard({ mudanca }: { mudanca: MudancaEndereco }) {
    const descricao = mudanca.processo_anterior
        ? `Comparado com o processo anterior da empresa, nº ${mudanca.processo_anterior}.`
        : 'A empresa não tem processo anterior no Viabiliza para comparar.';

    return (
        <Card>
            <CardHeader title="Mudança de endereço" description={descricao} />
            <CardContent>
                <div className="flex flex-col gap-4">
                    <div className="flex flex-wrap items-center gap-2 text-theme-sm text-gray-700 dark:text-gray-300">
                        <span>Serviço identificado:</span>
                        <Badge size="sm" color="info">
                            {mudanca.servico}
                        </Badge>
                    </div>

                    {mudanca.processo_anterior && mudanca.campos.length === 0 && (
                        <p className="text-theme-sm text-gray-500 dark:text-gray-400">
                            Nenhum campo de endereço ou área mudou em relação ao processo anterior.
                        </p>
                    )}

                    {mudanca.campos.length > 0 && (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-theme-sm">
                                <thead>
                                    <tr className="border-b border-gray-200 text-theme-xs text-gray-500 dark:border-gray-800 dark:text-gray-400">
                                        <th className="py-2 pr-4 font-medium">Campo</th>
                                        <th className="py-2 pr-4 font-medium">Antes</th>
                                        <th className="py-2 font-medium">Agora</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {mudanca.campos.map((campo) => (
                                        <tr key={campo.campo} className="border-b border-gray-100 last:border-0 dark:border-gray-800">
                                            <td className="py-2 pr-4 font-medium text-gray-700 dark:text-gray-300">{campo.campo}</td>
                                            <td className="py-2 pr-4 text-gray-500 dark:text-gray-400">{campo.antes || '—'}</td>
                                            <td className="py-2 text-gray-800 dark:text-white/90">{campo.depois || '—'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}
