<?php

namespace App\Services\Expresso;

use App\Enums\ResultadoViabilidade;
use App\Enums\ViabilityRequestStatus;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Solicitacao\ResolvedViability;
use App\Services\Solicitacao\SolicitacaoViabilityResolver;
use App\Services\Solicitacao\ViabilityRequestStateMachine;
use App\Support\Audit\AuditService;
use App\Support\Settings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Motor de decisão do fluxo expresso (HU-073 a HU-078) — o CORE VALUE do SILE.
 *
 * `decide()` é AUTORITATIVO: sob Cache::lock por solicitação + re-check de
 * status (idempotência), REEXECUTA o SolicitacaoViabilityResolver FRESCO —
 * nunca confia no simulation_snapshot pré-protocolo (orientativo, pode estar
 * stale). A partir do resolvido decide:
 *
 *  - toggle features.fluxo_expresso desligado → em_analise (degradação
 *    comunicada da funcionalidade acoplável, HU-014);
 *  - inelegível (algum CNAE encaminhado à análise pelo motor de risco) →
 *    em_analise (semi-expresso, HU-073/RN-008);
 *  - veredito consolidado pendente (sem a zona oficial — Quadro 10 indisponível)
 *    → em_analise SEM criar decisão e SEM evento (anti-fachada: o motor NÃO
 *    defere/indefere sem o dado real; liga sozinho quando a base entrar);
 *  - nao_permitido → INDEFERE; permitido(_com_condicoes) → DEFERE (RN-009),
 *    criando a ViabilityDecision imutável + número TVL (só no deferimento),
 *    transicionando pela StateMachine e auditando SÍNCRONO (HU-078) — tudo numa
 *    transação; o ResultadoEmitido é disparado SÓ após o commit.
 *
 * A auditoria autoritativa da decisão é síncrona e NÃO depende do evento (lição
 * da Fase 8): o evento é apenas a base desacoplada dos efeitos colaterais.
 */
class FluxoExpressoService
{
    public function __construct(
        private SolicitacaoViabilityResolver $resolver,
        private ViabilityRequestStateMachine $stateMachine,
        private TvlNumberGenerator $tvl,
        private AuditService $audit,
    ) {}

    /**
     * Decide a viabilidade da solicitação (deferir/indeferir/encaminhar) de forma
     * idempotente e autoritativa. Devolve o DecisionResult com o estado final, a
     * decisão criada (quando há) e se o ResultadoEmitido foi disparado.
     */
    public function decide(ViabilityRequest $request, ?User $actor = null): DecisionResult
    {
        $ttl = (int) config('sile.expresso.lock.ttl_segundos', 10);

        return Cache::lock("expresso:decisao:{$request->id}", $ttl)->block(
            $ttl,
            fn (): DecisionResult => $this->decidirSobLock($request, $actor),
        );
    }

    /**
     * Corpo da decisão executado já com o lock adquirido. RECARREGA o estado e
     * re-checa que ainda está protocolada (idempotência: se já saiu, não
     * redecide), aplica o toggle e a elegibilidade, e roteia para a análise ou a
     * emissão conforme o veredito consolidado FRESCO.
     */
    private function decidirSobLock(ViabilityRequest $request, ?User $actor): DecisionResult
    {
        $request->refresh();

        if ($request->status !== ViabilityRequestStatus::Protocolada) {
            return $this->resultadoIdempotente($request);
        }

        $toggleAtivo = (bool) Settings::get('features.fluxo_expresso', config('sile.features.fluxo_expresso', true));

        if (! $toggleAtivo) {
            return $this->encaminharAnalise($request, 'fluxo expresso desativado', $actor);
        }

        // Reexecução FRESCA dos motores — a decisão é autoritativa, não o snapshot.
        $resolved = $this->resolver->resolve($request);

        if (! $resolved->elegivelExpresso()) {
            return $this->encaminharAnalise($request, 'atividade fora do fluxo expresso (análise técnica)', $actor, $resolved);
        }

        // Sem a zona (Quadro 10), o veredito é pendente — encaminha à análise SEM
        // decidir nem emitir. Anti-fachada: jamais um deferimento/indeferimento
        // inventado (a base oficial liga o caminho de decisão).
        if ($resolved->consolidado === ResultadoViabilidade::Pendente->value) {
            return $this->encaminharAnalise(
                $request,
                'veredito locacional pendente — zona urbanística pendente SEDUR',
                $actor,
                $resolved,
            );
        }

        return $this->emitir($request, $resolved, $actor);
    }

    /**
     * Re-check do lock: a solicitação já saiu de protocolada (decidida em outra
     * passada ou encaminhada). Devolve a decisão existente (sem redisparar o
     * evento) ou o estado atual, sem nova transição — no-op idempotente.
     */
    private function resultadoIdempotente(ViabilityRequest $request): DecisionResult
    {
        $decision = $request->decision()->first();

        if ($decision !== null) {
            return DecisionResult::decidida($decision, emitted: false);
        }

        return new DecisionResult($request->status, null, null, emitted: false);
    }

    /**
     * Encaminha a solicitação à análise técnica (HU-073): transição
     * protocolada→em_analise (timeline + auditoria de transição) e auditoria
     * SÍNCRONA da decisão (resultado 'analise', com o motivo e — quando houve
     * resolução — o por-CNAE e as versões de regra), numa transação. NÃO cria
     * ViabilityDecision e NÃO dispara ResultadoEmitido.
     */
    private function encaminharAnalise(
        ViabilityRequest $request,
        string $reason,
        ?User $actor,
        ?ResolvedViability $resolved = null,
    ): DecisionResult {
        DB::transaction(function () use ($request, $reason, $actor, $resolved): void {
            $this->stateMachine->transition(
                $request,
                ViabilityRequestStatus::EmAnalise,
                $actor,
                reason: $reason,
                publicLabel: ViabilityRequestStatus::EmAnalise->publicLabel(),
            );

            $this->audit->log(
                'expresso',
                'decisao',
                "Encaminhamento à análise técnica da solicitação #{$request->id}: {$reason}",
                properties: [
                    'viability_request_id' => $request->id,
                    'protocol_number' => $request->protocol_number,
                    'resultado' => 'analise',
                    'motivo' => $reason,
                    'consolidado' => $resolved?->consolidado,
                    'por_cnae' => $resolved !== null ? $this->perCnaeResumo($resolved) : [],
                ],
                subject: $request,
                result: 'analise',
                rulesVersion: $resolved !== null ? $this->rulesVersionRepresentativa($resolved->rules_versions) : null,
            );
        });

        return DecisionResult::paraAnalise($reason);
    }

    /**
     * Emite a decisão vinculante (defere/indefere). Implementado na Task 2 do
     * plano 09-05.
     */
    private function emitir(ViabilityRequest $request, ResolvedViability $resolved, ?User $actor): DecisionResult
    {
        throw new \RuntimeException('Emissão da decisão (defere/indefere) é implementada na Task 2 do 09-05.');
    }

    /**
     * Por-CNAE resumido para a auditoria (código, tendência locacional e
     * encaminhamento de risco) — explicabilidade compacta da decisão (RN-005).
     *
     * @return list<array<string, mixed>>
     */
    private function perCnaeResumo(ResolvedViability $resolved): array
    {
        return array_map(static fn (array $item): array => [
            'cnae' => $item['cnae'],
            'tendencia' => $item['tendencia'],
            'fluxo' => $item['fluxo'],
        ], $resolved->por_cnae);
    }

    /**
     * Versão de regra representativa da decisão (RN-005) para a coluna
     * rules_version da auditoria: a primeira versão real aplicada, na ordem em
     * que governa o veredito (Quadro 10 → 7 → 11/11A → risco → território). O
     * mapa completo de versões fica na ViabilityDecision.
     *
     * @param  array<string, array<string, ?string>>  $rulesVersions
     */
    private function rulesVersionRepresentativa(array $rulesVersions): ?string
    {
        $ordem = [
            ['louos', 'quadro10'],
            ['louos', 'quadro7'],
            ['louos', 'quadro11'],
            ['louos', 'quadro11a'],
            ['risco', 'municipal'],
            ['risco', 'sanitario'],
            ['territorio', 'zona'],
            ['territorio', 'bairro'],
        ];

        foreach ($ordem as [$grupo, $chave]) {
            $versao = $rulesVersions[$grupo][$chave] ?? null;

            if (is_string($versao) && $versao !== '') {
                return $versao;
            }
        }

        return null;
    }
}
