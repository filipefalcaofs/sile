<?php

namespace Tests\Feature\Solicitacao;

use App\Models\Parameter;
use App\Services\Solicitacao\ProtocolNumberGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gerador do número de protocolo (HU-068) provado em SQLite: formato
 * parametrizável {prefixo}-{AAAA}-{NNNNNN}, sequência reiniciada por ano e
 * unicidade lógica. A concorrência real sob lockForUpdate (no-op em SQLite) é
 * exercitada no ProtocolNumberGeneratorPostgisTest.
 */
class ProtocolNumberGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private function generator(): ProtocolNumberGenerator
    {
        return new ProtocolNumberGenerator;
    }

    public function test_gera_no_formato_parametrizado(): void
    {
        $this->assertSame('VIA-2026-000001', $this->generator()->generate(2026));
        $this->assertSame('VIA-2026-000002', $this->generator()->generate(2026));
    }

    public function test_reinicia_sequencia_por_ano(): void
    {
        $this->generator()->generate(2026);

        $this->assertSame('VIA-2027-000001', $this->generator()->generate(2027));
    }

    public function test_respeita_prefixo_e_padding_parametrizados(): void
    {
        // Grava os parâmetros diretamente (sem depender do seeder do 08-02) —
        // Parameter::saved invalida o cache da chave (efeito sem deploy, HU-014).
        Parameter::query()->create([
            'key' => 'solicitacao.protocolo.prefixo',
            'group' => 'solicitacao',
            'type' => 'string',
            'value' => 'TVL',
            'default_value' => 'VIA',
            'validation_rules' => ['required', 'string'],
            'description' => 'Prefixo do número de protocolo.',
        ]);
        Parameter::query()->create([
            'key' => 'solicitacao.protocolo.padding',
            'group' => 'solicitacao',
            'type' => 'integer',
            'value' => '4',
            'default_value' => '6',
            'validation_rules' => ['required', 'integer'],
            'description' => 'Casas do contador no número de protocolo.',
        ]);

        $this->assertSame('TVL-2026-0001', $this->generator()->generate(2026));
    }

    public function test_numeros_sao_unicos_em_sequencia(): void
    {
        $numeros = [];
        for ($i = 0; $i < 50; $i++) {
            $numeros[] = $this->generator()->generate(2026);
        }

        $this->assertCount(50, array_unique($numeros));
    }
}
