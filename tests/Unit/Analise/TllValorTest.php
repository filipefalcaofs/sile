<?php

namespace Tests\Unit\Analise;

use App\Enums\RuleDomain;
use App\Models\RuleVersion;
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

    public function test_codigo_exercicio_e_especificacao_sao_unicos(): void
    {
        TllValor::factory()->create([
            'codigo_tll' => '6.00',
            'exercicio' => 2026,
            'especificacao' => 'ISENTA',
        ]);

        $this->expectException(QueryException::class);

        TllValor::factory()->create([
            'codigo_tll' => '6.00',
            'exercicio' => 2026,
            'especificacao' => 'ISENTA',
        ]);
    }

    public function test_mesmo_codigo_e_exercicio_com_especificacao_distinta_e_permitido(): void
    {
        TllValor::factory()->create([
            'codigo_tll' => '6.00',
            'exercicio' => 2026,
            'especificacao' => 'ISENTA',
            'valor' => 0,
            'codigo_tll_sefaz' => 'T45026667',
        ]);
        TllValor::factory()->create([
            'codigo_tll' => '6.00',
            'exercicio' => 2026,
            'especificacao' => 'Estabelecimentos não Classificados nos Itens 3.00 a 5.00',
            'valor' => 833.06,
            'codigo_tll_sefaz' => 'T44992336',
        ]);

        $this->assertSame(2, TllValor::query()->where('codigo_tll', '6.00')->where('exercicio', 2026)->count());
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

    public function test_dominio_tll_valores_existe_e_e_sensivel(): void
    {
        $this->assertSame('tll_valores', RuleDomain::TllValores->value);
        $this->assertTrue(RuleDomain::TllValores->isSensitive());
        $this->assertSame('Tabela de valores TLL por exercício', RuleDomain::TllValores->label());
    }

    public function test_linha_tll_referencia_a_versao_do_exercicio(): void
    {
        $versao = RuleVersion::factory()->create([
            'domain' => RuleDomain::TllValores,
            'version' => '2027',
        ]);
        $valor = TllValor::factory()->create([
            'exercicio' => 2027,
            'rule_version_id' => $versao->id,
        ]);

        $this->assertTrue($valor->versao->is($versao));
    }
}
