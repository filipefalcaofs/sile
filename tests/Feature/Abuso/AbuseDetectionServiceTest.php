<?php

namespace Tests\Feature\Abuso;

use App\Enums\AbuseSeverity;
use App\Enums\ViabilityRequestStatus;
use App\Models\AbuseAlert;
use App\Models\Activity;
use App\Models\FineMeshReferral;
use App\Models\Parameter;
use App\Models\ViabilityRequest;
use App\Services\Abuso\AbuseDetectionService;
use App\Services\Abuso\AbuseFinding;
use App\Services\Abuso\Contracts\AbuseDetector;
use App\Services\Abuso\DetectionWindow;
use App\Services\Analise\MalhaFinaService;
use App\Support\Audit\AuditService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Motor de detecção de abuso (HU-149). NUNCA pune (RN-001/CA-02): gera ALERTA
 * idempotente e, acima do limiar `abuso.severidade_malha_fina`, encaminha à malha
 * fina pelo caminho de SISTEMA — SEM transicionar o status do processo. NO-OP
 * honesto quando o toggle features.deteccao_abuso está OFF (default). Auditado.
 */
class AbuseDetectionServiceTest extends TestCase
{
    use RefreshDatabase;

    private function ligarDeteccao(): void
    {
        // Caminho REAL do toggle (parâmetro HU-014): Parameter::saved invalida o
        // cache da chave (efeito sem deploy). Default do sistema é OFF.
        Parameter::query()->create([
            'key' => 'features.deteccao_abuso',
            'group' => 'features',
            'type' => 'boolean',
            'value' => '1',
            'default_value' => '0',
            'validation_rules' => ['required', 'boolean'],
            'description' => 'Toggle de teste da detecção de abuso.',
        ]);
    }

    private function processo(ViabilityRequestStatus $status = ViabilityRequestStatus::Protocolada): ViabilityRequest
    {
        $request = ViabilityRequest::factory()->create();
        $request->forceFill(['status' => $status])->save();

        return $request;
    }

    private function finding(AbuseSeverity $severity, ViabilityRequest $request, string $fingerprint = 'fp-1'): AbuseFinding
    {
        return new AbuseFinding(
            ruleKey: 'volume_cnpj',
            severity: $severity,
            fingerprint: $fingerprint,
            evidence: ['total' => 9, 'ids' => [$request->id]],
            viabilityRequestId: $request->id,
            subject: $request->company,
            windowStart: CarbonImmutable::now()->subDays(30),
            windowEnd: CarbonImmutable::now(),
        );
    }

    /**
     * Detector FAKE controlado (via instância) — testa a lógica do serviço de
     * forma determinística, sem depender dos 2 detectores reais.
     *
     * @param  list<AbuseFinding>  $findings
     */
    private function detectorFake(string $key, array $findings): AbuseDetector
    {
        return new class($key, $findings) implements AbuseDetector
        {
            /**
             * @param  list<AbuseFinding>  $findings
             */
            public function __construct(private string $key, private array $findings) {}

            public function key(): string
            {
                return $this->key;
            }

            public function detect(DetectionWindow $window): iterable
            {
                return $this->findings;
            }
        };
    }

    private function service(AbuseDetector ...$detectors): AbuseDetectionService
    {
        return new AbuseDetectionService($detectors, app(MalhaFinaService::class), app(AuditService::class));
    }

    public function test_toggle_off_e_no_op_honesto_nao_grava_nem_audita(): void
    {
        // features.deteccao_abuso nasce OFF (12-03): detectar() não grava NADA e
        // retorna resumo zerado — prova de no-op honesto (não fachada).
        $request = $this->processo();
        $finding = $this->finding(AbuseSeverity::Alta, $request);

        $resumo = $this->service($this->detectorFake('volume_cnpj', [$finding]))->detectar();

        $this->assertFalse($resumo['executado']);
        $this->assertSame(0, $resumo['criados']);
        $this->assertSame(0, AbuseAlert::query()->count());
        $this->assertSame(0, FineMeshReferral::query()->count());
        $this->assertDatabaseMissing('activity_log', [
            'log_name' => 'abuso',
            'event' => 'abuso-detectar',
        ]);
    }

    public function test_toggle_on_cria_alerta_e_segunda_execucao_e_idempotente(): void
    {
        // ON + padrão presente → cria 1 alerta; reprocessar a mesma janela NÃO
        // duplica (índice único parcial por rule_key+fingerprint aberto, 12-03).
        $this->ligarDeteccao();
        $request = $this->processo();
        $finding = $this->finding(AbuseSeverity::Media, $request, 'fp-estavel');

        $service = $this->service($this->detectorFake('volume_cnpj', [$finding]));

        $primeiro = $service->detectar();
        $this->assertTrue($primeiro['executado']);
        $this->assertSame(1, $primeiro['criados']);
        $this->assertSame(1, AbuseAlert::query()->count());

        $segundo = $service->detectar();
        $this->assertSame(0, $segundo['criados']);
        $this->assertSame(1, $segundo['reaproveitados']);
        $this->assertSame(1, AbuseAlert::query()->count());
    }

    public function test_severity_acima_do_limiar_encaminha_a_malha_fina_sem_mudar_status(): void
    {
        // CA-01/CA-02/RN-001: alta (>= abuso.severidade_malha_fina = alta) → cria o
        // fine_mesh_referrals pelo caminho de sistema e grava fine_mesh_referral_id;
        // liga in_fine_mesh, mas o STATUS do processo NÃO muda (anti-fachada).
        $this->ligarDeteccao();
        $request = $this->processo(ViabilityRequestStatus::Protocolada);
        $finding = $this->finding(AbuseSeverity::Alta, $request, 'fp-alta');

        $resumo = $this->service($this->detectorFake('volume_cnpj', [$finding]))->detectar();

        $this->assertSame(1, $resumo['criados']);
        $this->assertSame(1, $resumo['encaminhados']);

        $alert = AbuseAlert::query()->firstOrFail();
        $this->assertNotNull($alert->fine_mesh_referral_id);

        $referral = FineMeshReferral::query()->firstOrFail();
        $this->assertSame($request->id, $referral->viability_request_id);
        $this->assertNull($referral->referred_by_user_id);

        $fresh = $request->fresh();
        $this->assertSame(ViabilityRequestStatus::Protocolada, $fresh->status);
        $this->assertTrue($fresh->in_fine_mesh);
    }

    public function test_severity_abaixo_do_limiar_gera_alerta_sem_malha_fina(): void
    {
        // Baixa < alta → só ALERTA; nenhum encaminhamento, processo intocado.
        $this->ligarDeteccao();
        $request = $this->processo();
        $finding = $this->finding(AbuseSeverity::Baixa, $request, 'fp-baixa');

        $resumo = $this->service($this->detectorFake('volume_cnpj', [$finding]))->detectar();

        $this->assertSame(1, $resumo['criados']);
        $this->assertSame(0, $resumo['encaminhados']);
        $this->assertSame(0, FineMeshReferral::query()->count());
        $this->assertNull(AbuseAlert::query()->firstOrFail()->fine_mesh_referral_id);
        $this->assertFalse($request->fresh()->in_fine_mesh);
    }

    public function test_ciclo_e_auditado_quando_ligado(): void
    {
        // RN-002/RN-003: o ciclo de detecção é auditado (quantos criados/encaminhados).
        $this->ligarDeteccao();
        $request = $this->processo();
        $finding = $this->finding(AbuseSeverity::Alta, $request, 'fp-audit');

        $this->service($this->detectorFake('volume_cnpj', [$finding]))->detectar();

        $activity = Activity::query()
            ->where('log_name', 'abuso')
            ->where('event', 'abuso-detectar')
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame(1, $activity->properties['criados']);
        $this->assertSame(1, $activity->properties['encaminhados']);
    }

    public function test_encaminhar_sistema_cria_referral_de_sistema_e_audita_sem_mudar_status(): void
    {
        // Caminho ADITIVO de sistema (HU-149): referred_by_user_id null = sistema;
        // funciona em qualquer status (RN-001), inclusive deferida, sem transicionar.
        $request = $this->processo(ViabilityRequestStatus::Deferida);

        $referral = app(MalhaFinaService::class)->encaminharSistema($request, 'suspeita de abuso: volume_cnpj');

        $this->assertInstanceOf(FineMeshReferral::class, $referral);
        $this->assertNull($referral->referred_by_user_id);
        $this->assertSame('suspeita de abuso: volume_cnpj', $referral->reason);

        $fresh = $request->fresh();
        $this->assertTrue($fresh->in_fine_mesh);
        $this->assertSame(ViabilityRequestStatus::Deferida, $fresh->status);

        $activity = Activity::query()
            ->where('log_name', 'analise')
            ->where('event', 'malha-fina-encaminhar')
            ->where('subject_id', $request->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame('sistema', $activity->properties['ator']);
        $this->assertSame('deferida', $activity->properties['status']);
    }
}
