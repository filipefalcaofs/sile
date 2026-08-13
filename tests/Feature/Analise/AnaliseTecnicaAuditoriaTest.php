<?php

namespace Tests\Feature\Analise;

use App\Enums\ViabilityRequestStatus;
use App\Events\ResultadoEmitido;
use App\Models\Activity;
use App\Models\AnalysisRecord;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Analise\AnaliseTecnicaDecisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Auditoria SÍNCRONA da decisão técnica HUMANA (HU-078, RN-002/RN-005): a trilha
 * autoritativa é gravada DENTRO da transação da decisão e NÃO depende do evento
 * ResultadoEmitido (lição das Fases 8/9 — o evento só carrega efeitos colaterais
 * desacoplados). Mesmo com o evento fakeado (listeners de notificação/Regin/SEFAZ
 * desligados), o log 'analise'/'decisao' (com a versão de regra, o por-CNAE e o
 * decided_by do analista) e a auditoria da transição (solicitacoes/transicao)
 * continuam gravados. O decided_by do analista (≠ null) distingue a decisão
 * humana da automática do fluxo expresso (decided_by null = sistema).
 */
class AnaliseTecnicaAuditoriaTest extends TestCase
{
    use RefreshDatabase;

    private function service(): AnaliseTecnicaDecisionService
    {
        return app(AnaliseTecnicaDecisionService::class);
    }

    /**
     * Ficha FINALIZADA deferível (todas as CNAEs deferidas) de um processo em
     * análise, com as versões de regra do motor preenchidas (factory default).
     */
    private function fichaFinalizadaDeferivel(): AnalysisRecord
    {
        $request = ViabilityRequest::factory()->protocoled()->create();
        $request->forceFill(['status' => ViabilityRequestStatus::EmAnalise])->save();

        return AnalysisRecord::factory()->finalizada()->create([
            'viability_request_id' => $request->id,
            'revision' => 1,
            'per_cnae' => [
                ['cnae' => '4712100', 'status_sugerido' => 'deferida', 'status_escolhido' => 'deferida'],
            ],
        ]);
    }

    public function test_auditoria_da_decisao_e_sincrona_e_independe_do_evento(): void
    {
        // Fakeando ResultadoEmitido, seus LISTENERS (notificação/Regin/SEFAZ) não
        // rodam. A auditoria da decisão, porém, é gravada na própria transação
        // (não por listener) — então continua presente: prova de que a auditoria
        // autoritativa NÃO depende do evento (HU-078, lição da Fase 8).
        Event::fake([ResultadoEmitido::class]);
        $ficha = $this->fichaFinalizadaDeferivel();
        $analista = User::factory()->create();

        $this->service()->decide($ficha, $analista);

        $activity = Activity::query()
            ->where('log_name', 'analise')
            ->where('event', 'decisao')
            ->where('result', 'deferida')
            ->latest('id')
            ->first();

        $this->assertNotNull($activity, 'A auditoria da decisão deve ser gravada mesmo com o evento fakeado (HU-078).');
        $this->assertNotNull($activity->rules_version);
        $this->assertNotSame('', $activity->rules_version);
        $this->assertSame($ficha->viability_request_id, $activity->properties['viability_request_id']);
        $this->assertArrayHasKey('por_cnae', $activity->properties);
        $this->assertNotEmpty($activity->properties['por_cnae']);
        $this->assertSame($analista->id, $activity->properties['decided_by']);

        Event::assertDispatched(ResultadoEmitido::class);
    }

    public function test_transicao_da_decisao_tambem_e_auditada(): void
    {
        Event::fake([ResultadoEmitido::class]);
        $ficha = $this->fichaFinalizadaDeferivel();

        $this->service()->decide($ficha, User::factory()->create());

        $transicao = Activity::query()
            ->where('log_name', 'solicitacoes')
            ->where('event', 'transicao')
            ->latest('id')
            ->first();

        $this->assertNotNull($transicao);
        $this->assertSame('em_analise', $transicao->properties['from']);
        $this->assertSame('deferida', $transicao->properties['to']);
    }

    public function test_decisao_registra_decided_by_do_analista_nao_nulo(): void
    {
        // Distingue a decisão HUMANA (decided_by = analista) da automática do
        // fluxo expresso (decided_by null = sistema).
        Event::fake([ResultadoEmitido::class]);
        $ficha = $this->fichaFinalizadaDeferivel();
        $analista = User::factory()->create();

        $result = $this->service()->decide($ficha, $analista);

        $this->assertNotNull($result->decision->decided_by_user_id);
        $this->assertSame($analista->id, $result->decision->decided_by_user_id);
    }
}
