<?php

namespace Tests\Feature\Ai;

use App\Models\AiConfiguration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AiConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_chave_de_api_e_gravada_criptografada_e_lida_em_claro_pelo_model(): void
    {
        $config = AiConfiguration::factory()->create(['api_key' => 'sk-1234567890ABCD']);

        $rawNoBanco = DB::table('ai_configurations')->where('id', $config->id)->value('api_key');

        $this->assertNotSame('sk-1234567890ABCD', $rawNoBanco, 'A chave não pode ser gravada em texto claro.');
        $this->assertSame('sk-1234567890ABCD', Crypt::decryptString($rawNoBanco));
        $this->assertSame('sk-1234567890ABCD', $config->fresh()->api_key);
    }

    public function test_a_chave_mascarada_nunca_expoe_o_miolo_da_credencial(): void
    {
        $config = AiConfiguration::factory()->create(['api_key' => 'sk-1234567890ABCD']);

        $masked = $config->masked_api_key;

        $this->assertStringNotContainsString('567890', $masked);
        $this->assertStringStartsWith('sk-1', $masked);
        $this->assertStringEndsWith('ABCD', $masked);
    }

    public function test_a_chave_mascarada_de_credencial_vazia_e_nula(): void
    {
        $config = AiConfiguration::factory()->create(['api_key' => null]);

        $this->assertNull($config->masked_api_key);
    }

    public function test_definir_como_padrao_desmarca_o_padrao_anterior_da_mesma_capacidade(): void
    {
        $primeira = AiConfiguration::factory()->create(['capability' => 'text', 'is_default' => true]);
        $segunda = AiConfiguration::factory()->create(['capability' => 'text', 'is_default' => false]);

        $segunda->setAsDefault();

        $this->assertFalse($primeira->fresh()->is_default);
        $this->assertTrue($segunda->fresh()->is_default);
    }

    public function test_definir_padrao_nao_afeta_outra_capacidade(): void
    {
        $texto = AiConfiguration::factory()->create(['capability' => 'text', 'is_default' => true]);
        $visao = AiConfiguration::factory()->create(['capability' => 'vision', 'is_default' => false]);

        $visao->setAsDefault();

        $this->assertTrue($texto->fresh()->is_default, 'Padrão de outra capacidade não pode ser desmarcado.');
        $this->assertTrue($visao->fresh()->is_default);
    }
}
