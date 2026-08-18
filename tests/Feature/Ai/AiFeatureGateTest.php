<?php

namespace Tests\Feature\Ai;

use App\Models\AiConfiguration;
use App\Services\Ai\AiFeatureGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Portão de disponibilidade de uma função de IA (degradação honesta, anti-fachada):
 * a função só está disponível quando o toggle features.ia_{função} está ligado E
 * existe uma AiConfiguration ATIVA da capacidade exigida. Desligado ou sem
 * provedor cadastrado ⇒ false: a camada de serviço NÃO despacha, NÃO chama o
 * provedor e NÃO simula resultado.
 */
class AiFeatureGateTest extends TestCase
{
    use RefreshDatabase;

    private function gate(): AiFeatureGate
    {
        return app(AiFeatureGate::class);
    }

    public function test_toggle_desligado_indisponibiliza_mesmo_com_provedor_ativo(): void
    {
        config(['sile.features.ia_ocr' => false]);
        AiConfiguration::factory()->create(['capability' => 'vision', 'active' => true]);

        $this->assertFalse($this->gate()->available('ocr', 'vision'));
    }

    public function test_toggle_ligado_sem_provedor_ativo_indisponibiliza(): void
    {
        config(['sile.features.ia_ocr' => true]);

        $this->assertFalse($this->gate()->available('ocr', 'vision'));
    }

    public function test_toggle_ligado_com_provedor_inativo_indisponibiliza(): void
    {
        config(['sile.features.ia_ocr' => true]);
        AiConfiguration::factory()->inactive()->create(['capability' => 'vision']);

        $this->assertFalse($this->gate()->available('ocr', 'vision'));
    }

    public function test_provedor_de_outra_capacidade_nao_satisfaz(): void
    {
        config(['sile.features.ia_ocr' => true]);
        AiConfiguration::factory()->create(['capability' => 'text', 'active' => true]);

        $this->assertFalse($this->gate()->available('ocr', 'vision'));
    }

    public function test_toggle_ligado_com_provedor_ativo_da_capacidade_disponibiliza(): void
    {
        config(['sile.features.ia_ocr' => true]);
        AiConfiguration::factory()->create(['capability' => 'vision', 'active' => true]);

        $this->assertTrue($this->gate()->available('ocr', 'vision'));
    }

    public function test_capacidade_padrao_e_texto(): void
    {
        config(['sile.features.ia_resumo' => true]);
        AiConfiguration::factory()->create(['capability' => 'text', 'active' => true]);

        $this->assertTrue($this->gate()->available('resumo'));
    }
}
