<?php

namespace App\Services\Ai;

use App\Models\AiConfiguration;
use App\Support\Settings;

/**
 * Portão de disponibilidade das funções de IA (Fase 14) — degradação honesta,
 * anti-fachada. Uma função só está disponível quando AMBOS são verdadeiros:
 *  1. o toggle features.ia_{função} está ligado (HU-014, administrável);
 *  2. existe uma AiConfiguration ATIVA da capacidade exigida (text|vision|embeddings).
 *
 * OFF ou sem provedor ⇒ a camada de serviço NÃO instancia o agente, NÃO despacha
 * o job, NÃO chama o provedor e NÃO simula resultado. O job re-checa este portão
 * no handle (a configuração pode mudar entre enfileirar e processar).
 */
class AiFeatureGate
{
    public function available(string $function, string $capability = 'text'): bool
    {
        if (! Settings::enabled("ia_{$function}")) {
            return false;
        }

        return AiConfiguration::query()
            ->where('capability', $capability)
            ->where('active', true)
            ->exists();
    }
}
