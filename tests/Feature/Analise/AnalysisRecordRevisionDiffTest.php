<?php

namespace Tests\Feature\Analise;

use App\Enums\AnalysisRecordStatus;
use App\Models\AnalysisRecord;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Analise\AnalysisRecordDiff;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Diff entre revisões da ficha (HU-135 RN-007): comparar duas revisões e mostrar
 * só o que MUDOU (status por CNAE, parecer, condicionantes), nunca os campos
 * iguais. O AnalysisRecordDiff::between é puro e testável; o endpoint serve o
 * painel da ficha (10-17), gated por analisar-processos e auditado (RN-002).
 */
class AnalysisRecordRevisionDiffTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function analista(): User
    {
        return User::factory()->analista()->withAcceptedLgpdTerm()->create();
    }

    public function test_between_retorna_apenas_os_campos_que_mudaram(): void
    {
        $request = ViabilityRequest::factory()->protocoled()->create();

        $rev1 = AnalysisRecord::factory()->create([
            'viability_request_id' => $request->id,
            'revision' => 1,
            'parecer' => 'Parecer da revisão 1.',
            'conditions' => ['Manter recuo frontal'],
            'analysis_reasons' => ['Motivo A'],
            'address_confirmed' => false,
            'per_cnae' => [
                ['cnae' => '4712100', 'status_sugerido' => 'deferida', 'status_escolhido' => 'deferida'],
            ],
        ]);

        $rev2 = AnalysisRecord::factory()->create([
            'viability_request_id' => $request->id,
            'revision' => 2,
            'parecer' => 'Parecer revisado.',
            'conditions' => ['Manter recuo frontal'],
            'analysis_reasons' => ['Motivo A', 'Motivo B'],
            'address_confirmed' => true,
            'per_cnae' => [
                ['cnae' => '4712100', 'status_sugerido' => 'deferida', 'status_escolhido' => 'indeferida'],
            ],
        ]);

        $diff = (new AnalysisRecordDiff)->between($rev1, $rev2);

        // O parecer mudou.
        $this->assertArrayHasKey('parecer', $diff);
        $this->assertSame('Parecer da revisão 1.', $diff['parecer']['de']);
        $this->assertSame('Parecer revisado.', $diff['parecer']['para']);

        // O status escolhido do CNAE mudou.
        $this->assertArrayHasKey('per_cnae', $diff);
        $this->assertSame('deferida', $diff['per_cnae']['4712100']['status_escolhido']['de']);
        $this->assertSame('indeferida', $diff['per_cnae']['4712100']['status_escolhido']['para']);

        // As condicionantes NÃO mudaram → não aparecem no diff.
        $this->assertArrayNotHasKey('conditions', $diff);
        $this->assertArrayHasKey('analysis_reasons', $diff);
        $this->assertArrayHasKey('address_confirmed', $diff);
        $this->assertSame(false, $diff['address_confirmed']['de']);
        $this->assertSame(true, $diff['address_confirmed']['para']);
    }

    public function test_endpoint_diff_serve_o_painel_gated_e_auditado(): void
    {
        $request = ViabilityRequest::factory()->protocoled()->create();

        AnalysisRecord::factory()->finalizada()->create([
            'viability_request_id' => $request->id,
            'revision' => 1,
            'parecer' => 'Parecer da revisão 1.',
            'per_cnae' => [
                ['cnae' => '4712100', 'status_sugerido' => 'deferida', 'status_escolhido' => 'deferida'],
            ],
        ]);

        AnalysisRecord::factory()->create([
            'viability_request_id' => $request->id,
            'revision' => 2,
            'status' => AnalysisRecordStatus::Rascunho,
            'parecer' => 'Parecer revisado.',
            'per_cnae' => [
                ['cnae' => '4712100', 'status_sugerido' => 'deferida', 'status_escolhido' => 'indeferida'],
            ],
        ]);

        $response = $this->actingAs($this->analista(), 'gestao')
            ->getJson("/gestao/processos/{$request->id}/ficha/diff?de=1&para=2")
            ->assertOk();

        $diff = $response->json('diff');
        $this->assertSame('Parecer revisado.', $diff['parecer']['para']);
        $this->assertSame('indeferida', $diff['per_cnae']['4712100']['status_escolhido']['para']);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'analise',
            'event' => 'ficha-diff',
            'result' => 'sucesso',
        ]);
    }
}
