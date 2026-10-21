import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import Badge from '@/components/ui/badge';
import Button from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import EmptyState from '@/components/ui/empty-state';

export interface DicaTempo {
    titulo: string;
    acao: string;
    evidencia: string;
    impacto: 'baixa' | 'media' | 'alta';
}

export interface PainelDicasTempo {
    disponivel: boolean;
    motivo: string | null;
    periodo: { de: string | null; ate: string | null };
    ultima: {
        resumo: string;
        dicas: DicaTempo[];
        confianca: string | null;
        status: string;
        periodo: { de: string | null; ate: string | null };
        gerado_em: string;
    } | null;
}

interface DicasTempoCardProps {
    dicas: PainelDicasTempo;
}

const INTERVALO_MS = 3000;
const LIMITE_ESPERA_MS = 120000;

const impactoVisual: Record<DicaTempo['impacto'], { rotulo: string; cor: 'error' | 'warning' | 'light' }> = {
    alta: { rotulo: 'Impacto alto', cor: 'error' },
    media: { rotulo: 'Impacto médio', cor: 'warning' },
    baixa: { rotulo: 'Impacto baixo', cor: 'light' },
};

function formatarData(iso: string | null): string {
    if (iso === null) {
        return '';
    }

    const [ano, mes, dia] = iso.split('-');

    return `${dia}/${mes}/${ano}`;
}

function descreverPeriodo(periodo: { de: string | null; ate: string | null }): string {
    if (periodo.de === null && periodo.ate === null) {
        return 'todo o histórico';
    }

    return `${periodo.de === null ? 'início' : formatarData(periodo.de)} a ${periodo.ate === null ? 'hoje' : formatarData(periodo.ate)}`;
}

function formatarGeradoEm(iso: string): string {
    return new Date(iso).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' });
}

/**
 * Dicas por IA para reduzir o tempo de viabilidade. A geração é assíncrona:
 * depois do pedido, a tela recarrega só o painel até a nova geração chegar ou
 * o limite de espera acabar. Sugestão ao gestor, nunca decisão.
 */
export default function DicasTempoCard({ dicas }: DicasTempoCardProps) {
    const [aguardando, setAguardando] = useState(false);
    const referencia = useRef<string | null>(null);
    const inicio = useRef(0);

    const geradoEm = dicas.ultima?.gerado_em ?? null;

    useEffect(() => {
        if (!aguardando) {
            return;
        }

        if (geradoEm !== referencia.current) {
            setAguardando(false);

            return;
        }

        if (Date.now() - inicio.current > LIMITE_ESPERA_MS) {
            setAguardando(false);

            return;
        }

        const timer = window.setTimeout(() => router.reload({ only: ['dicas'] }), INTERVALO_MS);

        return () => window.clearTimeout(timer);
    }, [aguardando, geradoEm]);

    function gerar() {
        const params: Record<string, string> = {};

        if (dicas.periodo.de !== null) {
            params.data_de = dicas.periodo.de;
        }

        if (dicas.periodo.ate !== null) {
            params.data_ate = dicas.periodo.ate;
        }

        router.post('/gestao/relatorios/dicas-tempo', params, {
            preserveScroll: true,
            onSuccess: (page) => {
                const avisoDeFalha = (page.props as { flash?: { warning?: string | null } }).flash?.warning;

                if (avisoDeFalha) {
                    return;
                }

                referencia.current = geradoEm;
                inicio.current = Date.now();
                setAguardando(true);
            },
        });
    }

    const acao = dicas.disponivel ? (
        <Button
            size="sm"
            variant={dicas.ultima === null ? 'primary' : 'outline'}
            onClick={gerar}
            loading={aguardando}
            disabled={aguardando}
        >
            {aguardando ? 'Gerando...' : dicas.ultima === null ? 'Gerar dicas' : 'Gerar de novo'}
        </Button>
    ) : undefined;

    return (
        <Card>
            <CardHeader
                title="Dicas para reduzir o tempo"
                description={`Sugestões da IA a partir dos gargalos por etapa, da cauda de processos lentos, dos indeferimentos e do benchmark nacional (${descreverPeriodo(dicas.periodo)}).`}
                actions={acao}
            />
            <CardContent>
                {!dicas.disponivel ? (
                    <EmptyState title="Dicas indisponíveis" description={dicas.motivo ?? 'A função de IA não está disponível.'} />
                ) : dicas.ultima === null ? (
                    <EmptyState
                        title={aguardando ? 'Gerando as dicas...' : 'Nenhuma dica gerada ainda'}
                        description={
                            aguardando
                                ? 'A IA está analisando o período. Isso leva alguns segundos.'
                                : 'Clique em "Gerar dicas" para a IA analisar o período e sugerir onde ganhar tempo.'
                        }
                    />
                ) : (
                    <div className={aguardando ? 'opacity-60' : ''}>
                        <p className="text-theme-sm text-gray-800 dark:text-white/90">{dicas.ultima.resumo}</p>
                        <ol className="mt-4 space-y-3">
                            {dicas.ultima.dicas.map((dica) => (
                                <li key={dica.titulo} className="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <p className="text-theme-sm font-semibold text-gray-800 dark:text-white/90">{dica.titulo}</p>
                                        <Badge size="sm" color={impactoVisual[dica.impacto].cor}>
                                            {impactoVisual[dica.impacto].rotulo}
                                        </Badge>
                                    </div>
                                    <p className="mt-1 text-theme-sm text-gray-700 dark:text-gray-300">{dica.acao}</p>
                                    <p className="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">Por quê: {dica.evidencia}</p>
                                </li>
                            ))}
                        </ol>
                        <p className="mt-4 text-theme-xs text-gray-500 dark:text-gray-400">
                            Gerado em {formatarGeradoEm(dicas.ultima.gerado_em)} para {descreverPeriodo(dicas.ultima.periodo)}.
                            {dicas.ultima.status === 'escalada_humano' && ' A IA indicou baixa confiança: valide antes de agir.'} Sugestão
                            gerada por IA a partir de dados agregados, sem dado pessoal; avalie antes de aplicar.
                        </p>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
