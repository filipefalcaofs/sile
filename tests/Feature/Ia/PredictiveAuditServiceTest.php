<?php

namespace Tests\Feature\Ia;

use App\Enums\AbuseAlertStatus;
use App\Enums\AbuseSeverity;
use App\Enums\DecisionOutcome;
use App\Enums\ViabilityRequestStatus;
use App\Models\Company;
use App\Models\PredictiveAnomaly;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Services\Ia\PredictiveAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Auditoria Preditiva de Processos Expressos (Módulo 3). Varre os deferimentos
 * automáticos do fluxo expresso na janela, pontua sinais determinísticos
 * (volume por CNPJ, inscrição repetida, requerente que prosseguiu apesar de
 * alerta) e cria anomalias acima do limiar de score. ANTI-FACHADA: nasce
 * DESLIGADA (no-op honesto), NUNCA pune nem transiciona o status do processo —
 * só gera alerta e, na severidade alta, encaminha à malha fina (ortogonal).
 */
class PredictiveAuditServiceTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config([
            'sile.features.ia_auditoria_preditiva' => true,
            'sile.ia.auditoria_preditiva.janela_dias' => 30,
            'sile.ia.auditoria_preditiva.limiar_score' => 70,
            'sile.abuso.volume_cnpj.limite' => 2,
        ]);
    }

    private function service(): PredictiveAuditService
    {
        return app(PredictiveAuditService::class);
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function deferidoExpresso(Company $company, array $attrs = []): ViabilityRequest
    {
        $protocolo = 'VIA-'.now()->year.'-'.str_pad((string) ++$this->seq, 6, '0', STR_PAD_LEFT);

        $request = ViabilityRequest::factory()->create(array_merge([
            'company_id' => $company->id,
            'status' => ViabilityRequestStatus::Deferida,
            'protocol_number' => $protocolo,
            'protocoled_at' => now()->subDays(2),
            'applicant_proceeded_despite' => false,
        ], $attrs));

        ViabilityDecision::factory()->create([
            'viability_request_id' => $request->id,
            'flow' => 'expresso',
            'outcome' => DecisionOutcome::Deferida,
            'decided_at' => now()->subDay(),
            'tvl_product_number' => null,
        ]);

        return $request;
    }

    public function test_toggle_desligado_e_noop_sem_gravar(): void
    {
        config(['sile.features.ia_auditoria_preditiva' => false]);
        Cache::flush();

        $company = Company::factory()->create();
        $this->deferidoExpresso($company, ['applicant_proceeded_despite' => true]);
        $this->deferidoExpresso($company, ['applicant_proceeded_despite' => true]);

        $resumo = $this->service()->executar();

        $this->assertFalse($resumo['executado']);
        $this->assertSame(0, PredictiveAnomaly::query()->count());
    }

    public function test_gera_anomalia_alta_e_encaminha_malha_fina_sem_punir(): void
    {
        $company = Company::factory()->create();

        // 2 deferimentos do mesmo CNPJ (volume >= 2 => sinal de volume) e o req1
        // ainda prosseguiu apesar de alerta (sinal extra) => score alto.
        $req1 = $this->deferidoExpresso($company, ['applicant_proceeded_despite' => true]);
        $this->deferidoExpresso($company, ['applicant_proceeded_despite' => false]);

        $resumo = $this->service()->executar();

        $this->assertTrue($resumo['executado']);
        $this->assertSame(1, $resumo['criadas']);
        $this->assertSame(1, $resumo['encaminhadas']);

        $anomalia = PredictiveAnomaly::query()->where('viability_request_id', $req1->id)->first();
        $this->assertNotNull($anomalia);
        $this->assertSame(AbuseSeverity::Alta, $anomalia->severity);
        $this->assertSame(AbuseAlertStatus::Aberto, $anomalia->status);
        $this->assertGreaterThanOrEqual(70, $anomalia->score);
        $this->assertNotNull($anomalia->fine_mesh_referral_id);

        // ANTI-FACHADA: nunca pune — o status do processo permanece deferido e a
        // malha fina é ortogonal (flag), não muda o desfecho.
        $this->assertSame(ViabilityRequestStatus::Deferida, $req1->fresh()->status);
        $this->assertTrue($req1->fresh()->in_fine_mesh);
    }

    public function test_processo_sem_sinais_suficientes_nao_vira_anomalia(): void
    {
        // CNPJ com 1 único deferimento e sem prosseguir-apesar => abaixo do limiar.
        $company = Company::factory()->create();
        $this->deferidoExpresso($company, ['applicant_proceeded_despite' => false]);

        $resumo = $this->service()->executar();

        $this->assertSame(0, $resumo['criadas']);
        $this->assertSame(0, PredictiveAnomaly::query()->count());
    }

    public function test_idempotente_nao_duplica_anomalia_aberta(): void
    {
        $company = Company::factory()->create();
        $req1 = $this->deferidoExpresso($company, ['applicant_proceeded_despite' => true]);
        $this->deferidoExpresso($company, ['applicant_proceeded_despite' => false]);

        $this->service()->executar();
        $resumo = $this->service()->executar();

        $this->assertSame(0, $resumo['criadas']);
        $this->assertSame(1, $resumo['reaproveitadas']);
        $this->assertSame(1, PredictiveAnomaly::query()->where('viability_request_id', $req1->id)->count());
    }
}
