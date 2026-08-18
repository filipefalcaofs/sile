<?php

namespace Tests\Feature\Analise;

use App\Enums\AnalysisPendencyStatus;
use App\Enums\AnalysisRecordStatus;
use App\Models\AnalysisDivergence;
use App\Models\AnalysisPendency;
use App\Models\AnalysisRecord;
use App\Models\FineMeshReferral;
use App\Models\Sector;
use App\Models\StandardText;
use App\Models\TvlDocument;
use App\Models\User;
use App\Models\ViabilityRequest;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Domínio da análise técnica (Fase 10): relations, casts (jsonb→array, enums,
 * datetimes, booleans), a revisão IMUTÁVEL/versionada da ficha (RN-003) e as
 * unicidades de banco (revisão por processo, verification_code do TVL). Roda em
 * SQLite com RefreshDatabase — espelha o ViabilityDecisionTest.
 */
class AnaliseModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_setor_tem_analistas_n_n_e_state_inativo(): void
    {
        $sector = Sector::factory()->hasAttached(User::factory(), [], 'analysts')->create();

        $this->assertCount(1, $sector->analysts);
        $analyst = $sector->analysts->first();
        $this->assertTrue($analyst->sectors->contains($sector));

        $this->assertTrue(Sector::factory()->ativo()->create()->active);
        $this->assertFalse(Sector::factory()->inativo()->create()->active);
    }

    public function test_viability_request_resolve_setor_e_analista(): void
    {
        $sector = Sector::factory()->create();
        $analyst = User::factory()->create();
        $request = ViabilityRequest::factory()->protocoled()->create();

        $request->forceFill([
            'sector_id' => $sector->id,
            'assigned_user_id' => $analyst->id,
            'assigned_at' => now(),
        ])->save();

        $fresh = $request->fresh();
        $this->assertTrue($fresh->sector->is($sector));
        $this->assertTrue($fresh->assignedTo->is($analyst));
        $this->assertInstanceOf(Carbon::class, $fresh->assigned_at);
        $this->assertTrue($sector->requests->contains($request));
    }

    public function test_analysis_record_rascunho_casta_arrays_e_relaciona_divergencias(): void
    {
        $record = AnalysisRecord::factory()->create();

        $this->assertSame(AnalysisRecordStatus::Rascunho, $record->status);
        $this->assertTrue($record->engine_available);
        $this->assertIsArray($record->engine_snapshot);
        $this->assertIsArray($record->engine_rules_versions);
        $this->assertIsArray($record->per_cnae);
        $this->assertIsArray($record->conditions);
        $this->assertIsArray($record->parking);
        $this->assertNull($record->finalized_at);
        $this->assertFalse($record->isFinalizada());

        $divergence = AnalysisDivergence::factory()->create(['analysis_record_id' => $record->id]);

        $this->assertTrue($record->fresh()->divergences->contains($divergence));
        $this->assertTrue($divergence->analysisRecord->is($record));
        $this->assertTrue($record->viabilityRequest->exists);
    }

    public function test_revisao_e_unica_por_processo(): void
    {
        $request = ViabilityRequest::factory()->protocoled()->create();
        AnalysisRecord::factory()->create(['viability_request_id' => $request->id, 'revision' => 1]);

        // unique(viability_request_id, revision): uma segunda revisão 1 para o
        // mesmo processo é rejeitada pelo banco (versionamento sem colisão).
        $this->expectException(QueryException::class);

        AnalysisRecord::factory()->create(['viability_request_id' => $request->id, 'revision' => 1]);
    }

    public function test_current_analysis_record_retorna_a_maior_revisao(): void
    {
        $request = ViabilityRequest::factory()->protocoled()->create();
        $rev1 = AnalysisRecord::factory()->create(['viability_request_id' => $request->id, 'revision' => 1]);
        $rev2 = AnalysisRecord::factory()->create(['viability_request_id' => $request->id, 'revision' => 2]);

        $fresh = $request->fresh();
        $this->assertCount(2, $fresh->analysisRecords);
        $this->assertTrue($fresh->currentAnalysisRecord->is($rev2));
        $this->assertFalse($fresh->currentAnalysisRecord->is($rev1));
    }

    public function test_state_finalizada_e_imutavel_marca_a_ficha(): void
    {
        $record = AnalysisRecord::factory()->finalizada()->create();

        $this->assertSame(AnalysisRecordStatus::Finalizada, $record->status);
        $this->assertTrue($record->isFinalizada());
        $this->assertInstanceOf(Carbon::class, $record->finalized_at);
        $this->assertNotNull($record->parecer);
    }

    public function test_state_sem_motor_degrada_honesto(): void
    {
        $record = AnalysisRecord::factory()->semMotor()->create();

        $this->assertFalse($record->engine_available);
        $this->assertNull($record->engine_snapshot);
    }

    public function test_pendencia_states_e_cast_de_status(): void
    {
        $request = ViabilityRequest::factory()->protocoled()->create();

        $aberta = AnalysisPendency::factory()->create(['viability_request_id' => $request->id]);
        $respondida = AnalysisPendency::factory()->respondida()->create(['viability_request_id' => $request->id]);
        $expirada = AnalysisPendency::factory()->expirada()->create(['viability_request_id' => $request->id]);

        $this->assertSame(AnalysisPendencyStatus::Aberta, $aberta->status);
        $this->assertInstanceOf(Carbon::class, $aberta->due_at);
        $this->assertTrue($aberta->viabilityRequest->is($request));
        $this->assertTrue($aberta->requestedBy->exists);

        $this->assertSame(AnalysisPendencyStatus::Respondida, $respondida->status);
        $this->assertInstanceOf(Carbon::class, $respondida->responded_at);
        $this->assertNotNull($respondida->response);

        $this->assertSame(AnalysisPendencyStatus::Expirada, $expirada->status);
    }

    public function test_malha_fina_state_resolvido(): void
    {
        $request = ViabilityRequest::factory()->protocoled()->create();

        $aberto = FineMeshReferral::factory()->create(['viability_request_id' => $request->id]);
        $this->assertFalse($aberto->isResolved());
        $this->assertNull($aberto->resolved_at);
        $this->assertTrue($aberto->viabilityRequest->is($request));
        $this->assertTrue($aberto->referredBy->exists);

        $resolvido = FineMeshReferral::factory()->resolvido()->create(['viability_request_id' => $request->id]);
        $this->assertTrue($resolvido->isResolved());
        $this->assertInstanceOf(Carbon::class, $resolvido->resolved_at);
    }

    public function test_standard_text_state_inativo_e_casts(): void
    {
        $ativo = StandardText::factory()->create();
        $this->assertTrue($ativo->active);
        $this->assertIsInt($ativo->version);

        $this->assertFalse(StandardText::factory()->inativo()->create()->active);
    }

    public function test_tvl_document_pertence_a_decisao_e_verification_code_e_unico(): void
    {
        $doc = TvlDocument::factory()->create();

        $this->assertTrue($doc->viabilityDecision->exists);
        $this->assertSame('local', $doc->disk);
        $this->assertInstanceOf(Carbon::class, $doc->generated_at);
        $this->assertTrue($doc->generatedBy->exists);

        // verification_code unique: a mesma decisão pode ter várias emissões
        // (reimpressões), mas o código de validação é único por documento.
        // Reusa a decisão do primeiro documento — não cria outra (evita um 2º
        // processo protocolado com protocol_number repetido).
        TvlDocument::factory()->create([
            'viability_decision_id' => $doc->viability_decision_id,
            'verification_code' => 'TVL-VERIF-DUP',
        ]);

        $this->expectException(QueryException::class);

        TvlDocument::factory()->create([
            'viability_decision_id' => $doc->viability_decision_id,
            'verification_code' => 'TVL-VERIF-DUP',
        ]);
    }
}
