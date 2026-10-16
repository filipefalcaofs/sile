<?php

namespace Tests\Feature\Ai;

use App\Models\AiConfiguration;
use App\Models\Parameter;
use App\Services\Ai\AiFeatureGate;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
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
    use LazilyRefreshDatabase;

    private function gate(): AiFeatureGate
    {
        return app(AiFeatureGate::class);
    }

    public function test_toggle_desligado_indisponibiliza_mesmo_com_provedor_ativo(): void
    {
        config(['viabiliza.features.ia_ocr' => false]);
        AiConfiguration::factory()->create(['capability' => 'vision', 'active' => true]);

        $this->assertFalse($this->gate()->available('ocr', 'vision'));
    }

    public function test_toggle_ligado_sem_provedor_ativo_indisponibiliza(): void
    {
        config(['viabiliza.features.ia_ocr' => true]);

        $this->assertFalse($this->gate()->available('ocr', 'vision'));
    }

    public function test_toggle_ligado_com_provedor_inativo_indisponibiliza(): void
    {
        config(['viabiliza.features.ia_ocr' => true]);
        AiConfiguration::factory()->inactive()->create(['capability' => 'vision']);

        $this->assertFalse($this->gate()->available('ocr', 'vision'));
    }

    public function test_provedor_de_outra_capacidade_nao_satisfaz(): void
    {
        config(['viabiliza.features.ia_ocr' => true]);
        AiConfiguration::factory()->create(['capability' => 'text', 'active' => true]);

        $this->assertFalse($this->gate()->available('ocr', 'vision'));
    }

    public function test_toggle_ligado_com_provedor_ativo_da_capacidade_disponibiliza(): void
    {
        config(['viabiliza.features.ia_ocr' => true]);
        AiConfiguration::factory()->create(['capability' => 'vision', 'active' => true]);

        $this->assertTrue($this->gate()->available('ocr', 'vision'));
    }

    public function test_capacidade_padrao_e_texto(): void
    {
        config(['viabiliza.features.ia_resumo' => true]);
        AiConfiguration::factory()->create(['capability' => 'text', 'active' => true]);

        $this->assertTrue($this->gate()->available('resumo'));
        $this->assertNull($this->gate()->unavailableReason('resumo'));
    }

    public function test_motivo_explica_toggle_desligado_e_falta_de_provedor(): void
    {
        config(['viabiliza.features.ia_resumo' => false]);
        Cache::forget('viabiliza.parameters.features.ia_resumo');

        $motivo = (string) $this->gate()->unavailableReason('resumo');

        $this->assertStringContainsString('desligada', $motivo);
        $this->assertStringNotContainsString('features.ia_resumo', $motivo);
        $this->assertStringNotContainsString('features.', $motivo);

        config(['viabiliza.features.ia_resumo' => true]);
        Cache::forget('viabiliza.parameters.features.ia_resumo');

        $this->assertStringContainsString('provedor', (string) $this->gate()->unavailableReason('resumo'));
    }

    public function test_parametro_ainda_no_padrao_nao_bloqueia_provedor_ativo(): void
    {
        config(['viabiliza.features.ia_resumo' => false]);
        Cache::put('viabiliza.parameters.features.ia_resumo', false, 300);
        Parameter::factory()->create([
            'key' => 'features.ia_resumo',
            'group' => 'ia',
            'type' => 'boolean',
            'value' => null,
            'default_value' => '0',
            'validation_rules' => ['required', 'boolean'],
        ]);
        AiConfiguration::factory()->create(['capability' => 'text', 'active' => true]);

        $this->assertTrue($this->gate()->available('resumo'));
        $this->assertNull($this->gate()->unavailableReason('resumo'));
    }

    public function test_parametro_ligado_vence_cache_que_ainda_diz_desligado(): void
    {
        config(['viabiliza.features.ia_resumo' => false]);
        Cache::put('viabiliza.parameters.features.ia_resumo', false, 300);
        Parameter::factory()->create([
            'key' => 'features.ia_resumo',
            'group' => 'ia',
            'type' => 'boolean',
            'value' => '1',
            'default_value' => '0',
            'validation_rules' => ['required', 'boolean'],
        ]);
        AiConfiguration::factory()->create(['capability' => 'text', 'active' => true]);

        $this->assertTrue($this->gate()->available('resumo'));
    }

    public function test_parametro_explicitamente_desligado_bloqueia_mesmo_com_provedor(): void
    {
        config(['viabiliza.features.ia_resumo' => true]);
        Parameter::factory()->create([
            'key' => 'features.ia_resumo',
            'group' => 'ia',
            'type' => 'boolean',
            'value' => '0',
            'default_value' => '0',
            'validation_rules' => ['required', 'boolean'],
        ]);
        AiConfiguration::factory()->create(['capability' => 'text', 'active' => true]);

        $this->assertFalse($this->gate()->available('resumo'));
        $this->assertStringContainsString('desligada', (string) $this->gate()->unavailableReason('resumo'));
    }
}
