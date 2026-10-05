import { router } from '@inertiajs/react';
import { useState } from 'react';
import Badge from '@/components/ui/badge';
import Button from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';

export interface EtapaMotor {
    etapa: string;
    rotulo: string;
    servico: string;
    situacao: 'ok' | 'indisponivel' | 'definitiva' | 'pulada';
    situacao_rotulo: string;
    motivo: string | null;
    tentativas: number;
    proxima_tentativa_em: string | null;
    esgotada: boolean;
}

interface Props {
    processoId: number;
    etapas: EtapaMotor[];
    podeReprocessar: boolean;
}

const MARCA: Record<EtapaMotor['situacao'], string> = {
    ok: '✓',
    indisponivel: '✗',
    definitiva: '–',
    pulada: '–',
};

const COR: Record<EtapaMotor['situacao'], 'success' | 'error' | 'light'> = {
    ok: 'success',
    indisponivel: 'error',
    definitiva: 'light',
    pulada: 'light',
};

export function temEtapaIndisponivel(etapas: EtapaMotor[]): boolean {
    return etapas.some((etapa) => etapa.situacao === 'indisponivel');
}

export function EtapasMotorCard({ processoId, etapas, podeReprocessar }: Props) {
    const [processando, setProcessando] = useState(false);

    if (etapas.length === 0) {
        return null;
    }

    function reprocessar() {
        router.post(
            `/gestao/processos/${processoId}/reprocessar`,
            {},
            {
                preserveScroll: true,
                onStart: () => setProcessando(true),
                onFinish: () => setProcessando(false),
            },
        );
    }

    return (
        <Card>
            <CardHeader
                title="Etapas do motor"
                description="Onde o motor parou por serviço de terceiro indisponível. O reprocesso refaz só o que falhou."
            />
            <CardContent>
                <ul className="divide-y divide-gray-200 dark:divide-gray-800">
                    {etapas.map((etapa) => (
                        <li key={etapa.etapa} className="flex flex-wrap items-center gap-3 py-2">
                            <span aria-hidden="true" className="w-4 text-center font-medium">
                                {MARCA[etapa.situacao]}
                            </span>
                            <span className="min-w-48 font-medium">{etapa.rotulo}</span>
                            <Badge color={COR[etapa.situacao]} size="sm">
                                {etapa.situacao_rotulo}
                            </Badge>
                            {etapa.motivo && (
                                <span className="text-sm text-gray-500 dark:text-gray-400">{etapa.motivo}</span>
                            )}
                            {etapa.situacao === 'indisponivel' && (
                                <span className="text-sm text-gray-500 dark:text-gray-400">
                                    {etapa.esgotada
                                        ? 'Tentativas automáticas esgotadas'
                                        : `Tentativas: ${etapa.tentativas}`}
                                </span>
                            )}
                        </li>
                    ))}
                </ul>
                {podeReprocessar && temEtapaIndisponivel(etapas) && (
                    <div className="mt-4">
                        <Button type="button" onClick={reprocessar} disabled={processando}>
                            {processando ? 'Reprocessando…' : 'Reprocessar'}
                        </Button>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
