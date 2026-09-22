<?php

namespace App\Services\Ai;

use App\Jobs\Ai\ExplicacaoCidadaoJob;
use App\Models\ViabilityRequest;

/**
 * Camada de serviço da explicação ao cidadão por IA (HU-119). Degradação honesta
 * dupla: (1) sem o toggle features.ia_explicacao ligado E um provedor de TEXTO
 * ativo; ou (2) sem uma ViabilityDecision registrada para o processo — em
 * qualquer dos casos NÃO despacha o job, NÃO chama o provedor e NÃO simula. Sem
 * decisão não há o que explicar: a IA não inventa desfecho para "destravar" a
 * tela (anti-fachada). A explicação resultante é SEMPRE sugestão revisável, fiel
 * à decisão registrada (HU-099); nunca decide nem reabre o mérito. O
 * RunAiAgentJob ainda re-checa o portão no handle (corrida entre enfileirar e
 * processar).
 */
class ExplicacaoCidadaoService
{
    public function __construct(private readonly AiFeatureGate $gate) {}

    /**
     * Despacha a explicação ao cidadão como SUGESTÃO revisável da decisão.
     *
     * @return bool true se a função estava disponível (toggle + provedor) E havia
     *              decisão registrada, com o job despachado; false quando
     *              indisponível — caso em que o cidadão vê a decisão sem a versão
     *              em linguagem cidadã.
     */
    public function processar(ViabilityRequest $processo, ?int $userId = null): bool
    {
        if (! $this->gate->available('explicacao', 'text')) {
            return false;
        }

        if (! $processo->decision()->exists()) {
            return false;
        }

        ExplicacaoCidadaoJob::dispatch($processo->id, $userId);

        return true;
    }
}
