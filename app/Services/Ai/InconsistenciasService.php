<?php

namespace App\Services\Ai;

use App\Jobs\Ai\InconsistenciasJob;
use App\Models\ViabilityRequest;
use App\Models\ViabilityRequestDocument;

/**
 * Camada de serviço da detecção de inconsistências por IA (HU-115). Degradação
 * honesta: sem o toggle features.ia_inconsistencias ligado E um provedor de
 * visão ativo, NÃO despacha o job, NÃO chama o provedor e NÃO simula (anti-fachada).
 *
 * O resultado SINALIZA divergências documento × declaração para a ficha de
 * análise e, como sinal, para a malha fina/HU-149 — nunca decide nem aciona
 * punição automática (RN-004). COMPLEMENTA as validações determinísticas
 * (área×polígono HU-063, polígono×lote HU-037, Receita HU-105); a parte geo/
 * Receita permanece bloqueada (SEDUR/Fase 13) e degrada honesto na própria ficha,
 * sem ser simulada aqui (RN-005).
 */
class InconsistenciasService
{
    public function __construct(private readonly AiFeatureGate $gate) {}

    /**
     * Despacha a detecção de inconsistências do processo como SUGESTÃO revisável.
     *
     * @return bool true se a função estava disponível e o job foi despachado;
     *              false quando indisponível (toggle off ou sem provedor de visão).
     */
    public function processar(ViabilityRequest $processo, ?int $userId = null): bool
    {
        if (! $this->gate->available('inconsistencias', 'vision')) {
            return false;
        }

        InconsistenciasJob::dispatch(
            $processo->id,
            $this->fotoFachada($processo)?->id,
            $userId,
        );

        return true;
    }

    /**
     * Localiza a foto da fachada entre os anexos: a primeira imagem, priorizando
     * o documento com a exigência "foto-fachada" (HU-067). Devolve null quando o
     * processo não tem imagem anexada — sem inventar material (anti-fachada).
     */
    private function fotoFachada(ViabilityRequest $processo): ?ViabilityRequestDocument
    {
        return $processo->documents()
            ->with('requirement')
            ->get()
            ->sortByDesc(fn (ViabilityRequestDocument $doc): int => $doc->requirement?->code === 'foto-fachada' ? 1 : 0)
            ->first(fn (ViabilityRequestDocument $doc): bool => str_starts_with((string) $doc->mime_type, 'image/'));
    }
}
