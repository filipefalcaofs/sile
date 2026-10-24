<?php

namespace Tests\Feature\Analise;

use App\Enums\AnalysisStatus;
use App\Models\AnalysisRecord;
use App\Models\User;
use App\Models\ViabilityRequest;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * HU.183 — correção dos dados antigos antes da trava de um rascunho por
 * analista: duplicados viram "substituida", a cópia de conclusão intocada volta
 * a ser base sem dono e as decisões passam a apontar para a ficha usada.
 */
class FichaPorAnalistaMigrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_10_24_011850_ficha_de_analise_por_analista.php');
    }

    private function processo(?AnalysisStatus $status = null): ViabilityRequest
    {
        $processo = ViabilityRequest::factory()->protocoled()->create(['protocol_number' => fake()->unique()->numerify('VIA-2026-######')]);

        if ($status !== null) {
            $processo->forceFill(['analysis_status' => $status])->save();
        }

        return $processo;
    }

    private function ficha(ViabilityRequest $processo, int $revisao, string $status, ?int $analista, array $extra = []): int
    {
        return DB::table('analysis_records')->insertGetId(array_merge([
            'viability_request_id' => $processo->id,
            'revision' => $revisao,
            'status' => $status,
            'analyst_user_id' => $analista,
            'engine_available' => true,
            'parecer' => "Revisão {$revisao}",
            'finalized_at' => $status === 'finalizada' ? now()->subDay() : null,
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ], $extra));
    }

    public function test_rascunhos_duplicados_do_mesmo_analista_ficam_substituidos(): void
    {
        $this->migration()->down();

        $analista = User::factory()->create();
        $processo = $this->processo();
        $r2 = $this->ficha($processo, 2, 'rascunho', $analista->id);
        $r3 = $this->ficha($processo, 3, 'rascunho', $analista->id);
        $r4 = $this->ficha($processo, 4, 'rascunho', $analista->id);

        $this->migration()->up();

        $this->assertSame('substituida', DB::table('analysis_records')->where('id', $r2)->value('status'));
        $this->assertSame('substituida', DB::table('analysis_records')->where('id', $r3)->value('status'));
        $this->assertSame('rascunho', DB::table('analysis_records')->where('id', $r4)->value('status'));
        $this->assertSame('Revisão 2', DB::table('analysis_records')->where('id', $r2)->value('parecer'));
        $this->assertSame(2, DB::table('activity_log')->where('event', 'ficha-substituida')->count());
    }

    public function test_copia_de_conclusao_intocada_fica_sem_dono_e_aponta_a_origem(): void
    {
        $this->migration()->down();

        $analista = User::factory()->create();
        $processo = $this->processo(AnalysisStatus::ParaConclusao);
        $origem = $this->ficha($processo, 1, 'finalizada', $analista->id);
        $copia = $this->ficha($processo, 2, 'rascunho', $analista->id);

        $this->migration()->up();

        $linha = DB::table('analysis_records')->where('id', $copia)->first();
        $this->assertNull($linha->analyst_user_id);
        $this->assertSame($origem, (int) $linha->based_on_record_id);
        $this->assertSame('Revisão 1', AnalysisRecord::find($origem)->parecer);
    }

    public function test_copia_de_conclusao_ja_editada_fica_com_quem_editou(): void
    {
        $this->migration()->down();

        $analista = User::factory()->create();
        $processo = $this->processo(AnalysisStatus::ParaConclusao);
        $this->ficha($processo, 1, 'finalizada', $analista->id);
        $copia = $this->ficha($processo, 2, 'rascunho', $analista->id, ['updated_at' => now()]);

        $this->migration()->up();

        $this->assertSame($analista->id, (int) DB::table('analysis_records')->where('id', $copia)->value('analyst_user_id'));
    }

    public function test_decisao_aponta_a_revisao_registrada_na_auditoria(): void
    {
        $this->migration()->down();

        $analista = User::factory()->create();
        $processo = $this->processo();
        $this->ficha($processo, 1, 'finalizada', $analista->id);
        $r2 = $this->ficha($processo, 2, 'finalizada', $analista->id);
        $decisao = $this->decisao($processo, $analista);

        DB::table('activity_log')->insert([
            'log_name' => 'analise',
            'description' => 'Decisão técnica',
            'subject_type' => (new ViabilityRequest)->getMorphClass(),
            'subject_id' => $processo->id,
            'event' => 'decisao',
            'properties' => json_encode(['revision' => 2]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->migration()->up();

        $this->assertSame($r2, (int) DB::table('viability_decisions')->where('id', $decisao)->value('analysis_record_id'));
    }

    public function test_sem_auditoria_vincula_so_quando_ha_uma_unica_ficha_finalizada(): void
    {
        $this->migration()->down();

        $analista = User::factory()->create();
        $unica = $this->processo();
        $ficha = $this->ficha($unica, 1, 'finalizada', $analista->id);
        $decisaoUnica = $this->decisao($unica, $analista);

        $duas = $this->processo();
        $this->ficha($duas, 1, 'finalizada', $analista->id);
        $this->ficha($duas, 2, 'finalizada', $analista->id);
        $decisaoAmbigua = $this->decisao($duas, $analista);

        $this->migration()->up();

        $this->assertSame($ficha, (int) DB::table('viability_decisions')->where('id', $decisaoUnica)->value('analysis_record_id'));
        $this->assertNull(DB::table('viability_decisions')->where('id', $decisaoAmbigua)->value('analysis_record_id'));
    }

    private function decisao(ViabilityRequest $processo, User $analista): int
    {
        return DB::table('viability_decisions')->insertGetId([
            'viability_request_id' => $processo->id,
            'flow' => 'analise_tecnica',
            'outcome' => 'deferida',
            'consolidated_result' => 'permitido',
            'per_cnae' => '[]',
            'rules_versions' => '[]',
            'fundamentacao' => '[]',
            'decided_by_user_id' => $analista->id,
            'decided_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
