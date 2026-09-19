<?php

namespace Tests\Feature\Louos;

use App\Enums\Quadro10Permissao;
use App\Models\LouosQuadro10Permissao;
use App\Models\LouosQuadro11CondicaoVia;
use App\Models\RuleVersion;
use App\Models\TratamentoEnquadramento;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Support\SeedsTratamentoPlanilha;
use Tests\TestCase;

/**
 * As tabelas tipadas dos Quadros da LOUOS (05-01) são TABULARES (sem PostGIS) e
 * rodam em SQLite, espelhando risk_classifications: cada linha referencia uma
 * versão de regra (rule_version_id) reusada da Fase 6. Trava o cast do enum de
 * permissão (Quadro 10) e o array de condições por via (Quadro 11/11A).
 */
class LouosQuadrosSchemaTest extends TestCase
{
    use LazilyRefreshDatabase;
    use SeedsTratamentoPlanilha;

    public function test_tabelas_louos_migram_em_sqlite(): void
    {
        $versao = RuleVersion::factory()->create();

        $permissao = LouosQuadro10Permissao::factory()->create(['rule_version_id' => $versao->id]);
        $condicaoVia = LouosQuadro11CondicaoVia::factory()->create(['rule_version_id' => $versao->id]);
        $tratamento = $this->seedTratamentoPlanilha();

        $this->assertDatabaseHas('louos_quadro10_permissoes', ['id' => $permissao->id]);
        $this->assertDatabaseHas('louos_quadro11_condicoes_via', ['id' => $condicaoVia->id]);
        $this->assertTrue(
            TratamentoEnquadramento::query()->where('rule_version_id', $tratamento->id)->exists(),
        );

        $this->assertInstanceOf(RuleVersion::class, $permissao->ruleVersion);
        $this->assertInstanceOf(RuleVersion::class, $condicaoVia->ruleVersion);
        $this->assertSame($versao->id, $permissao->ruleVersion->id);
    }

    public function test_permissao_do_quadro10_e_castada_para_enum(): void
    {
        $permissao = LouosQuadro10Permissao::factory()->create([
            'permissao' => Quadro10Permissao::PermitidoCondicionado,
        ]);

        $this->assertInstanceOf(Quadro10Permissao::class, $permissao->fresh()->permissao);
        $this->assertSame(Quadro10Permissao::PermitidoCondicionado, $permissao->fresh()->permissao);
    }

    public function test_condicoes_do_quadro11_sao_array(): void
    {
        $condicaoVia = LouosQuadro11CondicaoVia::factory()->create([
            'condicoes' => ['recuo_frontal_m' => 5, 'exige_carga_descarga' => true],
        ]);

        $this->assertIsArray($condicaoVia->fresh()->condicoes);
        $this->assertSame(5, $condicaoVia->fresh()->condicoes['recuo_frontal_m']);
    }
}
