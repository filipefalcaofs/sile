<?php

namespace Tests\Feature\Ai;

use App\Services\Ai\AiCostEstimator;
use Tests\TestCase;

/**
 * Estimativa de custo de uma chamada de IA. O SDK só entrega TOKENS (sem custo
 * em R$); o custo é derivado de um preço por 1.000 tokens, ADMINISTRÁVEL por
 * modelo. Anti-fachada: sem preço configurado para o modelo, o custo é null —
 * NUNCA inventado.
 */
class AiCostEstimatorTest extends TestCase
{
    private function estimator(): AiCostEstimator
    {
        return app(AiCostEstimator::class);
    }

    public function test_sem_preco_configurado_o_custo_e_nulo(): void
    {
        config(['sile.ai.preco_por_modelo' => []]);

        $this->assertNull($this->estimator()->estimate('gpt-5.4-mini', 1000, 500));
    }

    public function test_modelo_ausente_no_mapa_devolve_nulo(): void
    {
        config(['sile.ai.preco_por_modelo' => ['outro-modelo' => 1.0]]);

        $this->assertNull($this->estimator()->estimate('gpt-5.4-mini', 1000, 500));
    }

    public function test_calcula_custo_por_mil_tokens_quando_ha_preco(): void
    {
        config(['sile.ai.preco_por_modelo' => ['gpt-5.4-mini' => 2.0]]);

        // (1000 + 500) / 1000 * 2.0 = 3.0
        $this->assertSame(3.0, $this->estimator()->estimate('gpt-5.4-mini', 1000, 500));
    }

    public function test_zero_tokens_com_preco_resulta_em_custo_zero(): void
    {
        config(['sile.ai.preco_por_modelo' => ['gpt-5.4-mini' => 2.0]]);

        $this->assertSame(0.0, $this->estimator()->estimate('gpt-5.4-mini', 0, 0));
    }

    public function test_modelo_nulo_devolve_nulo(): void
    {
        config(['sile.ai.preco_por_modelo' => ['gpt-5.4-mini' => 2.0]]);

        $this->assertNull($this->estimator()->estimate(null, 1000, 500));
    }
}
