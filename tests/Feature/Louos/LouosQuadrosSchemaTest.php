<?php

namespace Tests\Feature\Louos;

use App\Enums\Quadro10Permissao;
use App\Models\LouosQuadro10Permissao;
use App\Models\LouosQuadro11CondicaoVia;
use App\Models\LouosQuadro7Faixa;
use App\Models\RuleVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * As tabelas tipadas dos Quadros da LOUOS (05-01) são TABULARES (sem PostGIS) e
 * rodam em SQLite, espelhando risk_classifications: cada linha referencia uma
 * versão de regra (rule_version_id) reusada da Fase 6. Trava o cast do enum de
 * permissão (Quadro 10) e o array de condições por via (Quadro 11/11A).
 */
class LouosQuadrosSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_tabelas_louos_migram_em_sqlite(): void
    {
        $versao = RuleVersion::factory()->create();

        $faixa = LouosQuadro7Faixa::factory()->create(['rule_version_id' => $versao->id]);
        $permissao = LouosQuadro10Permissao::factory()->create(['rule_version_id' => $versao->id]);
        $condicaoVia = LouosQuadro11CondicaoVia::factory()->create(['rule_version_id' => $versao->id]);

        $this->assertDatabaseHas('louos_quadro7_faixas', ['id' => $faixa->id]);
        $this->assertDatabaseHas('louos_quadro10_permissoes', ['id' => $permissao->id]);
        $this->assertDatabaseHas('louos_quadro11_condicoes_via', ['id' => $condicaoVia->id]);

        $this->assertInstanceOf(RuleVersion::class, $faixa->ruleVersion);
        $this->assertInstanceOf(RuleVersion::class, $permissao->ruleVersion);
        $this->assertInstanceOf(RuleVersion::class, $condicaoVia->ruleVersion);
        $this->assertSame($versao->id, $faixa->ruleVersion->id);
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

    public function test_area_max_nula_representa_sem_limite_superior(): void
    {
        $faixa = LouosQuadro7Faixa::factory()->create(['area_max' => null]);

        $this->assertNull($faixa->fresh()->area_max);
    }
}
