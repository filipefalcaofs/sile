<?php

namespace Tests\Feature\Viabilidade;

use App\Models\User;
use App\Models\ViabilityQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * O histórico de consultas (HU-060) é uma tabela imutável e TABULAR (json, sem
 * PostGIS) que roda em SQLite. Trava o snapshot com casts array (input/result/
 * rules_versions), o escopo por dono (forUser — precedente "Minhas empresas") e
 * a imutabilidade (só created_at, sem updated_at).
 */
class ViabilityQuerySchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_grava_e_recupera_snapshot_com_casts_array(): void
    {
        $query = ViabilityQuery::factory()->create([
            'input' => ['endereco' => 'Praça Municipal, 1', 'cnae' => '4712100', 'area' => 120.0],
            'result' => ['veredito_locacional' => ['resultado' => 'pendente']],
            'rules_versions' => ['louos' => ['quadro7' => 'v1'], 'risco' => ['municipal' => 'v2'], 'territorio' => []],
        ]);

        $recarregada = $query->fresh();

        $this->assertIsArray($recarregada->input);
        $this->assertIsArray($recarregada->result);
        $this->assertIsArray($recarregada->rules_versions);
        $this->assertSame('4712100', $recarregada->input['cnae']);
        $this->assertSame('pendente', $recarregada->result['veredito_locacional']['resultado']);
        $this->assertSame('v1', $recarregada->rules_versions['louos']['quadro7']);
    }

    public function test_consulta_anonima_grava_sem_dono(): void
    {
        $query = ViabilityQuery::factory()->anonima()->create();

        $this->assertNull($query->user_id);
        $this->assertNotNull($query->ip_address);
    }

    public function test_escopo_for_user_retorna_so_do_dono(): void
    {
        $dono = User::factory()->create();
        $outro = User::factory()->create();

        ViabilityQuery::factory()->count(2)->create(['user_id' => $dono->id]);
        $doOutro = ViabilityQuery::factory()->create(['user_id' => $outro->id]);

        $this->assertSame(2, ViabilityQuery::forUser($dono)->count());
        $this->assertEquals([$dono->id], ViabilityQuery::forUser($dono)->pluck('user_id')->unique()->values()->all());
        $this->assertFalse(ViabilityQuery::forUser($dono)->whereKey($doOutro->id)->exists());
    }

    public function test_tabela_e_imutavel_sem_updated_at(): void
    {
        $this->assertTrue(Schema::hasColumn('viability_queries', 'created_at'));
        $this->assertFalse(Schema::hasColumn('viability_queries', 'updated_at'));
    }
}
