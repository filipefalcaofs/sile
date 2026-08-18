import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { CloseIcon } from '@/components/icons';
import Alert from '@/components/ui/alert';
import Badge from '@/components/ui/badge';
import Button from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import type { SharedProps } from '@/types';
// Reusa o CnaePicker da Fase 3 (busca incremental só de CNAEs ativos na tabela
// oficial pela rota portal.cnaes.search — GET /portal/cnaes?search=...).
import CnaePicker, { type CnaeOption } from '@/pages/portal/empresas/cnae-picker';

interface CnaeItem {
    id: number;
    formatted_code: string;
    description: string;
    is_primary: boolean;
}

interface EtapaAtividadesProps {
    solicitacaoId: number;
    cnaes: CnaeItem[];
    max: number;
    onSaved: () => void;
}

function toOption(cnae: CnaeItem): CnaeOption {
    return { id: cnae.id, formatted_code: cnae.formatted_code, description: cnae.description };
}

function CnaeLine({ cnae, action }: { cnae: CnaeOption; action?: React.ReactNode }) {
    return (
        <div className="flex items-center justify-between gap-3 rounded-xl border border-gray-200 px-4 py-3 dark:border-gray-800">
            <div className="flex flex-col">
                <span className="text-sm font-medium text-gray-800 dark:text-white/90">{cnae.formatted_code}</span>
                <span className="text-theme-xs text-gray-500 dark:text-gray-400">{cnae.description}</span>
            </div>
            {action}
        </div>
    );
}

/**
 * Etapa de atividades (HU-064/065): define o CNAE principal e os complementares
 * (até o limite parametrizável) reusando o picker da Fase 3 (só CNAEs ativos da
 * tabela oficial). Envia ao PUT solicitacoes.atividades (08-07), que grava o
 * pivot e invalida a simulação anterior (RN-005).
 */
export default function EtapaAtividades({ solicitacaoId, cnaes, max, onSaved }: EtapaAtividadesProps) {
    const { errors } = usePage<SharedProps>().props;

    const [principal, setPrincipal] = useState<CnaeOption | null>(() => {
        const found = cnaes.find((cnae) => cnae.is_primary);

        return found ? toOption(found) : null;
    });
    const [complementares, setComplementares] = useState<CnaeOption[]>(() =>
        cnaes.filter((cnae) => !cnae.is_primary).map(toOption),
    );
    const [processing, setProcessing] = useState(false);
    const [trocarPrincipal, setTrocarPrincipal] = useState(false);

    const atividadeErrors = Object.entries(errors as Record<string, string>)
        .filter(([key]) => key === 'principal_cnae_id' || key === 'complementares' || key.startsWith('complementares.'))
        .map(([, message]) => message);

    const noLimite = complementares.length >= max;

    const excludeIds = [
        ...(principal ? [principal.id] : []),
        ...complementares.map((cnae) => cnae.id),
    ];

    function salvar() {
        if (!principal) {
            return;
        }

        router.put(
            `/portal/solicitacoes/${solicitacaoId}/atividades`,
            {
                principal_cnae_id: principal.id,
                complementares: complementares.map((cnae) => cnae.id),
            },
            {
                preserveScroll: true,
                preserveState: true,
                onStart: () => setProcessing(true),
                onSuccess: onSaved,
                onFinish: () => setProcessing(false),
            },
        );
    }

    return (
        <div className="flex flex-col gap-4 md:gap-6">
            {atividadeErrors.length > 0 && (
                <Alert variant="error" title="Não foi possível salvar as atividades" message={atividadeErrors.join(' ')} />
            )}

            <Card>
                <CardHeader
                    title="Atividade principal"
                    description="O CNAE principal determina o enquadramento da atividade. Só CNAEs ativos da tabela oficial são aceitos."
                />
                <CardContent>
                    <div className="flex flex-col gap-3">
                        {principal && !trocarPrincipal ? (
                            <CnaeLine
                                cnae={principal}
                                action={
                                    <div className="flex items-center gap-2">
                                        <Badge size="sm">Principal</Badge>
                                        <Button size="xs" variant="outline" onClick={() => setTrocarPrincipal(true)}>
                                            Alterar
                                        </Button>
                                    </div>
                                }
                            />
                        ) : (
                            <CnaePicker
                                onSelect={(cnae) => {
                                    setPrincipal(cnae);
                                    setTrocarPrincipal(false);
                                }}
                                excludeIds={excludeIds}
                                placeholder="Buscar o CNAE principal..."
                            />
                        )}
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader
                    title="Atividades complementares"
                    description={`CNAEs complementares (opcional). Limite de ${max} complementares.`}
                />
                <CardContent>
                    <div className="flex flex-col gap-4">
                        {complementares.length > 0 ? (
                            <div className="flex flex-col gap-2">
                                {complementares.map((cnae) => (
                                    <CnaeLine
                                        key={cnae.id}
                                        cnae={cnae}
                                        action={
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    setComplementares((current) =>
                                                        current.filter((item) => item.id !== cnae.id),
                                                    )
                                                }
                                                aria-label={`Remover ${cnae.formatted_code}`}
                                                title="Remover da seleção"
                                                className="flex size-8 items-center justify-center rounded-lg text-gray-400 transition hover:bg-error-50 hover:text-error-600 dark:hover:bg-error-500/10 dark:hover:text-error-400"
                                            >
                                                <CloseIcon className="size-4" />
                                            </button>
                                        }
                                    />
                                ))}
                            </div>
                        ) : (
                            <p className="rounded-xl border border-dashed border-gray-300 px-4 py-6 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                Nenhuma atividade complementar selecionada.
                            </p>
                        )}

                        {noLimite ? (
                            <Alert
                                variant="info"
                                title="Limite de complementares atingido"
                                message={`Você já selecionou o máximo de ${max} CNAEs complementares.`}
                            />
                        ) : (
                            <CnaePicker
                                onSelect={(cnae) => setComplementares((current) => [...current, cnae])}
                                excludeIds={excludeIds}
                                placeholder="Adicionar CNAE complementar..."
                            />
                        )}
                    </div>
                </CardContent>
            </Card>

            <div className="flex justify-end">
                <Button size="sm" onClick={salvar} disabled={processing || !principal} loading={processing}>
                    Salvar atividades e continuar
                </Button>
            </div>
        </div>
    );
}
