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
    intencao: 'incluir' | 'excluir' | null;
}

/** Bloco de textos administráveis do escritório virtual (Settings::get). */
interface EscritorioVirtualTextos {
    pergunta_vinculada: string;
    mensagem_confirma_perda_sede: string;
    cnae_gatilho_id: number | null;
}

interface EtapaAtividadesProps {
    solicitacaoId: number;
    cnaes: CnaeItem[];
    max: number;
    /** Visibilidade do controle de exclusão (RN-AA-05b) — vem do backend, não é deduzida no front. */
    isAlteracaoAtividade: boolean;
    /** Confirmação de perda da condição de sede já gravada (RN-AA-04); null = ainda não perguntado. */
    confirmaPerdaCondicaoSede: boolean | null;
    /** Resposta da pergunta geral do passo do imóvel (RN-EV-01) — condiciona a pergunta vinculada. */
    wantsVirtualOfficeTenant: boolean | null;
    /** Resposta atual da pergunta vinculada (campo do imóvel, escrito por este passo — Task 3). */
    wantsVirtualOfficeHq: boolean;
    escritorioVirtual: EscritorioVirtualTextos;
    onSaved: () => void;
}

function toOption(cnae: CnaeItem): CnaeOption {
    return { id: cnae.id, formatted_code: cnae.formatted_code, description: cnae.description };
}

/** Checkbox "Excluir esta atividade" (RN-AA-05b) — só aparece na alteração de atividade. */
function ExclusaoCheckbox({ checked, onChange }: { checked: boolean; onChange: () => void }) {
    return (
        <label className="flex items-center gap-2 text-theme-xs text-gray-600 dark:text-gray-400">
            <input
                type="checkbox"
                checked={checked}
                onChange={onChange}
                className="size-4 rounded border-gray-300 text-error-500 focus:ring-error-500/30 dark:border-gray-700 dark:bg-gray-900"
            />
            Excluir esta atividade
        </label>
    );
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
 *
 * Task 3 (escritório virtual): na alteração de atividade, cada CNAE ganha um
 * controle de exclusão (RN-AA-05b — vira `exclusoes` no payload); marcar o
 * CNAE gatilho para exclusão exige a confirmação explícita de perda da
 * condição de sede (RN-AA-04, sem pré-seleção); e o gatilho entre as
 * atividades, com a pergunta geral do imóvel respondida "Não", exibe a
 * pergunta vinculada (RN-EV-01) — que escreve `wants_virtual_office_hq`,
 * campo do imóvel, aqui porque é neste passo que os CNAEs são conhecidos.
 */
export default function EtapaAtividades({
    solicitacaoId,
    cnaes,
    max,
    isAlteracaoAtividade,
    confirmaPerdaCondicaoSede,
    wantsVirtualOfficeTenant,
    wantsVirtualOfficeHq,
    escritorioVirtual,
    onSaved,
}: EtapaAtividadesProps) {
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

    // Marcação de exclusão por atividade (RN-AA-05b) — só existe visualmente
    // na alteração de atividade; os demais tipos nem exibem o controle.
    const [exclusoes, setExclusoes] = useState<number[]>(() =>
        cnaes.filter((cnae) => cnae.intencao === 'excluir').map((cnae) => cnae.id),
    );
    const [confirmaPerda, setConfirmaPerda] = useState<boolean | null>(confirmaPerdaCondicaoSede);
    const [prestaServicoVinculado, setPrestaServicoVinculado] = useState(wantsVirtualOfficeHq);

    const atividadeErrors = Object.entries(errors as Record<string, string>)
        .filter(
            ([key]) =>
                key === 'principal_cnae_id' ||
                key === 'complementares' ||
                key.startsWith('complementares.') ||
                key === 'exclusoes' ||
                key.startsWith('exclusoes.'),
        )
        .map(([, message]) => message);

    const noLimite = complementares.length >= max;

    const excludeIds = [
        ...(principal ? [principal.id] : []),
        ...complementares.map((cnae) => cnae.id),
    ];

    function toggleExclusao(id: number) {
        setExclusoes((current) => (current.includes(id) ? current.filter((item) => item !== id) : [...current, id]));
    }

    // O CNAE gatilho está entre as atividades atuais e foi marcado para
    // exclusão? É o gatilho da confirmação de perda da condição de sede.
    const gatilhoMarcadoParaExclusao =
        escritorioVirtual.cnae_gatilho_id !== null &&
        excludeIds.includes(escritorioVirtual.cnae_gatilho_id) &&
        exclusoes.includes(escritorioVirtual.cnae_gatilho_id);

    // A pergunta vinculada aparece quando o gatilho está entre as atividades
    // (independente de exclusão) e a pergunta geral do imóvel foi respondida
    // "Não" — quem já respondeu "Sim" já declarou a intenção de abrigado, não
    // de sede.
    const exibirPerguntaVinculada =
        escritorioVirtual.cnae_gatilho_id !== null &&
        excludeIds.includes(escritorioVirtual.cnae_gatilho_id) &&
        wantsVirtualOfficeTenant === false;

    function salvar() {
        if (!principal) {
            return;
        }

        // Filtra `exclusoes` pelos ids efetivamente submetidos (achado I1 da
        // revisão da Task 3): remover o complementar pelo botão de remover,
        // ou trocar o principal, tira o id de `excludeIds` mas não limpava a
        // marcação — o PUT seguinte caía no `after()` de pertinência
        // ("só se exclui o que a própria solicitação está submetendo") e
        // voltava com erro, sem o checkbox na tela para desmarcar.
        const exclusoesValidas = exclusoes.filter((id) => excludeIds.includes(id));

        router.put(
            `/portal/solicitacoes/${solicitacaoId}/atividades`,
            {
                principal_cnae_id: principal.id,
                complementares: complementares.map((cnae) => cnae.id),
                exclusoes: exclusoesValidas,
                // A chave só entra no payload quando há resposta: mandar
                // `null` explícito seria uma chave PRESENTE com valor nulo, e
                // o backend trata presença da chave como resposta — viraria
                // "não" por ausência mal disfarçada (a mesma armadilha já
                // corrigida antes neste projeto), mesmo sem o requerente ter
                // respondido nada.
                ...(confirmaPerda !== null ? { confirma_perda_condicao_sede: confirmaPerda } : {}),
                // Mesmo padrão: só entra quando a pergunta vinculada está
                // visível (achado I2 da revisão da Task 3) — do contrário o
                // estado local sobrevivia à pergunta sumir (ex.: gatilho
                // removido da lista) e escrevia `hq` com valor velho, vindo
                // das props do carregamento, numa solicitação que já não
                // declara mais o gatilho.
                ...(exibirPerguntaVinculada ? { wants_virtual_office_hq: prestaServicoVinculado } : {}),
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
                                    <div className="flex items-center gap-3">
                                        {isAlteracaoAtividade && (
                                            <ExclusaoCheckbox
                                                checked={exclusoes.includes(principal.id)}
                                                onChange={() => toggleExclusao(principal.id)}
                                            />
                                        )}
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
                                            <div className="flex items-center gap-3">
                                                {isAlteracaoAtividade && (
                                                    <ExclusaoCheckbox
                                                        checked={exclusoes.includes(cnae.id)}
                                                        onChange={() => toggleExclusao(cnae.id)}
                                                    />
                                                )}
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
                                            </div>
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

            {exibirPerguntaVinculada && (
                <Card>
                    <CardHeader title="Escritório virtual" />
                    <CardContent>
                        <label className="flex items-center gap-3 text-sm text-gray-700 dark:text-gray-300">
                            <input
                                type="checkbox"
                                checked={prestaServicoVinculado}
                                onChange={(event) => setPrestaServicoVinculado(event.target.checked)}
                                className="size-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500/30 dark:border-gray-700 dark:bg-gray-900"
                            />
                            {escritorioVirtual.pergunta_vinculada}
                        </label>
                    </CardContent>
                </Card>
            )}

            {gatilhoMarcadoParaExclusao && (
                <Card>
                    <CardHeader title="Confirmação necessária" />
                    <CardContent>
                        <p className="mb-3 text-sm text-gray-700 dark:text-gray-300">
                            {escritorioVirtual.mensagem_confirma_perda_sede}
                        </p>
                        {/* Sem pré-seleção: null é "ainda não respondido", nunca uma
                        recusa presumida. */}
                        <div className="flex items-center gap-6">
                            <label className="flex items-center gap-3 text-sm text-gray-700 dark:text-gray-300">
                                <input
                                    type="radio"
                                    name="confirma_perda_condicao_sede"
                                    checked={confirmaPerda === true}
                                    onChange={() => setConfirmaPerda(true)}
                                    className="size-4 border-gray-300 text-brand-500 focus:ring-brand-500/30 dark:border-gray-700 dark:bg-gray-900"
                                />
                                Sim
                            </label>
                            <label className="flex items-center gap-3 text-sm text-gray-700 dark:text-gray-300">
                                <input
                                    type="radio"
                                    name="confirma_perda_condicao_sede"
                                    checked={confirmaPerda === false}
                                    onChange={() => setConfirmaPerda(false)}
                                    className="size-4 border-gray-300 text-brand-500 focus:ring-brand-500/30 dark:border-gray-700 dark:bg-gray-900"
                                />
                                Não
                            </label>
                        </div>
                        {errors.confirma_perda_condicao_sede && (
                            <p className="mt-2 text-theme-xs text-error-500">{errors.confirma_perda_condicao_sede}</p>
                        )}
                    </CardContent>
                </Card>
            )}

            <div className="flex justify-end">
                <Button size="sm" onClick={salvar} disabled={processing || !principal} loading={processing}>
                    Salvar atividades e continuar
                </Button>
            </div>
        </div>
    );
}
