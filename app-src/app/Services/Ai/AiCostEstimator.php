<?php

namespace App\Services\Ai;

use App\Support\Settings;

/**
 * Estima o custo (R$) de uma chamada de IA a partir dos TOKENS reportados pelo
 * SDK (que não entrega custo). O preço é ADMINISTRÁVEL por modelo
 * (ai.preco_por_modelo: { modelo: preço por 1.000 tokens }).
 *
 * Anti-fachada (AI-SPEC): sem preço configurado para o modelo — ou modelo
 * desconhecido — o custo é null e NUNCA é inventado. A própria leitura passa por
 * Settings (HU-014): basta cadastrar o preço para o custo passar a ser estimado,
 * sem deploy.
 */
class AiCostEstimator
{
    public function estimate(?string $model, int $promptTokens, int $completionTokens): ?float
    {
        if ($model === null || $model === '') {
            return null;
        }

        /** @var array<string, int|float|string> $precos */
        $precos = (array) Settings::get('ai.preco_por_modelo', []);

        if (! array_key_exists($model, $precos)) {
            return null;
        }

        $precoPorMilTokens = (float) $precos[$model];
        $totalTokens = $promptTokens + $completionTokens;

        return $totalTokens / 1000 * $precoPorMilTokens;
    }
}
