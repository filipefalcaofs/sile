import { router } from '@inertiajs/react';
import { useState } from 'react';
import Alert from '@/components/ui/alert';
import Badge from '@/components/ui/badge';
import Button from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { ResultadoViabilidade } from '@/components/viabilidade/resultado-viabilidade';
import type { ResultadoConsulta } from '@/components/viabilidade/resultado-viabilidade';

export interface SimulacaoPorCnae {
    cnae: string;
    cnae_formatado: string;
    is_primary: boolean;
    tendencia: string;
    tendencia_label: string;
    consulta: ResultadoConsulta;
}

export interface SimulacaoData {
    resultado: string;
    resultado_label: string;
    por_cnae: SimulacaoPorCnae[];
    simulated_at: string | null;
}

interface EtapaSimulacaoProps {
    solicitacaoId: number;
    simulation: SimulacaoData | null;
    simulacaoEnabled: boolean;
    onContinue: () => void;
}

const RESULTADO_COLOR: Record<string, 'success' | 'warning' | 'error' | 'info'> = {
    permitido: 'success',
    permitido_com_condicoes: 'warning',
    nao_permitido: 'error',
    pendente: 'info',
};

/**
 * Etapa de simulação (HU-141): aciona a simulação real (POST solicitacoes.simular
 * — reusa o motor da Fase 7 por CNAE) e exibe a tendência consolidada e o
 * resultado por CNAE com o componente ResultadoViabilidade (Fase 7). É
 * ORIENTATIVA: nunca bloqueia o protocolo. Pendente sem zona aparece COM motivo
 * (nunca permitido/não permitido sem o dado oficial). O snapshot vem persistido
 * (RN-003) — o front não recomputa nada.
 */
export default function EtapaSimulacao({ solicitacaoId, simulation, simulacaoEnabled, onContinue }: EtapaSimulacaoProps) {
    const [processing, setProcessing] = useState(false);

    function simular() {
        router.post(
            `/portal/solicitacoes/${solicitacaoId}/simular`,
            {},
            {
                preserveScroll: true,
                preserveState: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
            },
        );
    }

    return (
        <div className="flex flex-col gap-4 md:gap-6">
            {!simulacaoEnabled && (
                <Alert
                    variant="info"
                    title="Simulação temporariamente indisponível"
                    message="A simulação de viabilidade está desativada no momento. Você pode prosseguir e protocolar normalmente — a simulação é apenas orientativa."
                />
            )}

            <Card>
                <CardHeader
                    title="Simular viabilidade"
                    description="A simulação aplica as regras reais da LOUOS e a classificação de risco às atividades informadas. É orientativa: não impede o protocolo (direito de petição)."
                />
                <CardContent>
                    <div className="flex flex-col gap-4">
                        {simulation?.simulated_at ? (
                            <div className="flex flex-wrap items-center gap-3">
                                <span className="text-sm text-gray-600 dark:text-gray-300">Tendência geral:</span>
                                <Badge color={RESULTADO_COLOR[simulation.resultado] ?? 'info'}>
                                    {simulation.resultado_label}
                                </Badge>
                            </div>
                        ) : (
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                Você ainda não simulou esta solicitação. A simulação é opcional e ajuda a antecipar a
                                tendência da viabilidade.
                            </p>
                        )}

                        {simulacaoEnabled && (
                            <div>
                                <Button size="sm" variant="outline" onClick={simular} disabled={processing} loading={processing}>
                                    {simulation?.simulated_at ? 'Simular novamente' : 'Simular viabilidade'}
                                </Button>
                            </div>
                        )}
                    </div>
                </CardContent>
            </Card>

            {simulation?.simulated_at &&
                simulation.por_cnae.map((item) => (
                    <Card key={item.cnae}>
                        <CardHeader
                            title={`Atividade ${item.cnae_formatado}`}
                            description={item.is_primary ? 'Atividade principal' : 'Atividade complementar'}
                        />
                        <CardContent>
                            <ResultadoViabilidade result={item.consulta} />
                        </CardContent>
                    </Card>
                ))}

            <div className="flex justify-end">
                <Button size="sm" onClick={onContinue}>
                    Continuar para a revisão
                </Button>
            </div>
        </div>
    );
}
