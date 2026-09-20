<?php

namespace Tests\Unit\Analise;

use App\Models\TllValor;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Model da tabela de valores TLL por exercício (HU-071/HU-014): dado
 * versionado/auditado, um valor por código por exercício (unique), inativação
 * sem exclusão.
 */
class TllValorTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_cria_valor_tll_com_a_estrutura_completa(): void
    {
        $valor = TllValor::factory()->create([
            'codigo_tll' => '1.01',
            'exercicio' => 2026,
            'valor' => 1111.78,
            'taxa_servico' => 50.00,
            'codigo_tll_sefaz' => 'T45020425',
            'codigo_servico_sefaz' => 'S2253362',
            'servico_sefaz' => 'Inclusão de Atividade em Viabilidade MEI',
        ]);

        $this->assertSame('1.01', $valor->codigo_tll);
        $this->assertSame(2026, $valor->exercicio);
        $this->assertSame('1111.78', (string) $valor->valor);
        $this->assertSame('50.00', (string) $valor->taxa_servico);
        $this->assertTrue($valor->active);
    }

    public function test_codigo_e_exercicio_sao_unicos(): void
    {
        TllValor::factory()->create(['codigo_tll' => '1.01', 'exercicio' => 2026]);

        $this->expectException(QueryException::class);

        TllValor::factory()->create(['codigo_tll' => '1.01', 'exercicio' => 2026]);
    }

    public function test_mesmo_codigo_em_exercicios_distintos_e_permitido(): void
    {
        TllValor::factory()->create(['codigo_tll' => '1.01', 'exercicio' => 2026]);
        TllValor::factory()->create(['codigo_tll' => '1.01', 'exercicio' => 2027]);

        $this->assertSame(2, TllValor::query()->where('codigo_tll', '1.01')->count());
    }

    public function test_scope_active_e_para_exercicio(): void
    {
        TllValor::factory()->create(['codigo_tll' => '1.01', 'exercicio' => 2026, 'active' => true]);
        TllValor::factory()->create(['codigo_tll' => '2.02', 'exercicio' => 2026, 'active' => false, 'valor' => 999]);
        TllValor::factory()->create(['codigo_tll' => '2.02', 'exercicio' => 2027, 'active' => true]);

        $this->assertSame(2, TllValor::query()->active()->count());
        $this->assertSame(1, TllValor::query()->active()->paraExercicio('1.01', 2026)->count());
        $this->assertSame(0, TllValor::query()->active()->paraExercicio('1.01', 2027)->count());
        $this->assertSame(0, TllValor::query()->active()->paraExercicio('2.02', 2026)->count(), 'O inativo não entra.');
    }
}
