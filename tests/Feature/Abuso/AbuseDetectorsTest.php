<?php

namespace Tests\Feature\Abuso;

use App\Enums\AbuseSeverity;
use App\Models\Company;
use App\Models\Parameter;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Abuso\AbuseFinding;
use App\Services\Abuso\DetectionWindow;
use App\Services\Abuso\Detectors\VolumeCnpjDetector;
use App\Services\Abuso\Detectors\VolumeContadorDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Detectores determinísticos de abuso (HU-149) sobre dado REAL — SEM IA. Cada
 * detector é uma Strategy (key + detect(Janela)) que varre solicitações reais na
 * janela parametrizável e emite um AbuseFinding SÓ acima do limiar, com
 * fingerprint ESTÁVEL (idempotência da 12-03) e evidence determinística. A janela
 * é respeitada: dado fora dela não conta para o limiar.
 */
class AbuseDetectorsTest extends TestCase
{
    use RefreshDatabase;

    private function janela(int $dias = 30): DetectionWindow
    {
        return DetectionWindow::lastDays($dias);
    }

    private function definirLimite(string $key, int $valor): void
    {
        Parameter::query()->create([
            'key' => $key,
            'group' => 'abuso',
            'type' => 'integer',
            'value' => (string) $valor,
            'default_value' => (string) $valor,
            'validation_rules' => ['required', 'integer'],
            'description' => 'Limite de teste.',
        ]);
    }

    /**
     * @param  iterable<AbuseFinding>  $findings
     * @return list<AbuseFinding>
     */
    private function lista(iterable $findings): array
    {
        return is_array($findings) ? array_values($findings) : iterator_to_array($findings, false);
    }

    public function test_volume_cnpj_emite_finding_acima_do_limiar_com_evidencia_e_subject(): void
    {
        // Limite default 5 (12-03): 6 solicitações do MESMO CNPJ na janela > 5 → alerta.
        $empresa = Company::factory()->create();
        ViabilityRequest::factory()->count(6)->create(['company_id' => $empresa->id]);

        // Outra empresa com 5 (não excede) — não deve gerar finding.
        $outra = Company::factory()->create();
        ViabilityRequest::factory()->count(5)->create(['company_id' => $outra->id]);

        $findings = $this->lista(app(VolumeCnpjDetector::class)->detect($this->janela()));

        $this->assertCount(1, $findings);
        $finding = $findings[0];
        $this->assertSame('volume_cnpj', $finding->ruleKey);
        $this->assertSame(6, $finding->evidence['total']);
        $this->assertSame($empresa->cnpj, $finding->evidence['cnpj']);
        $this->assertCount(6, $finding->evidence['ids']);
        $this->assertNotNull($finding->viabilityRequestId);
        $this->assertContains($finding->viabilityRequestId, $finding->evidence['ids']);
        $this->assertInstanceOf(Company::class, $finding->subject);
        $this->assertTrue($finding->subject->is($empresa));
    }

    public function test_volume_cnpj_fingerprint_e_estavel_para_a_mesma_ocorrencia(): void
    {
        // RN idempotência (12-03): mesma entrada → mesmo fingerprint, em chamadas
        // distintas (o índice único parcial dedupe o alerta aberto a partir dele).
        $empresa = Company::factory()->create();
        ViabilityRequest::factory()->count(6)->create(['company_id' => $empresa->id]);

        $primeiro = $this->lista(app(VolumeCnpjDetector::class)->detect($this->janela()))[0];
        $segundo = $this->lista(app(VolumeCnpjDetector::class)->detect($this->janela()))[0];

        $this->assertSame($primeiro->fingerprint, $segundo->fingerprint);
        $this->assertNotSame('', $primeiro->fingerprint);
    }

    public function test_volume_cnpj_respeita_a_janela_dado_antigo_nao_conta(): void
    {
        // 4 na janela + 4 fora (40 dias atrás). Com limite 5, só 4 contam → SEM
        // finding. Se a janela fosse ignorada, 8 > 5 dispararia (anti-fachada).
        $empresa = Company::factory()->create();
        ViabilityRequest::factory()->count(4)->create(['company_id' => $empresa->id]);
        ViabilityRequest::factory()->count(4)->create([
            'company_id' => $empresa->id,
            'created_at' => now()->subDays(40),
        ]);

        $findings = $this->lista(app(VolumeCnpjDetector::class)->detect($this->janela(30)));

        $this->assertCount(0, $findings);
    }

    public function test_volume_cnpj_escala_severidade_por_faixa(): void
    {
        // Faixa: acima de 2× o limite → Alta; entre limite+1 e 2× → Média.
        $this->definirLimite('abuso.volume_cnpj.limite', 2);

        $media = Company::factory()->create();
        ViabilityRequest::factory()->count(3)->create(['company_id' => $media->id]); // 3 ∈ (2, 4] → Média

        $alta = Company::factory()->create();
        ViabilityRequest::factory()->count(5)->create(['company_id' => $alta->id]); // 5 > 4 → Alta

        $porEmpresa = [];
        foreach (app(VolumeCnpjDetector::class)->detect($this->janela()) as $finding) {
            $porEmpresa[$finding->subject->id] = $finding->severity;
        }

        $this->assertSame(AbuseSeverity::Media, $porEmpresa[$media->id]);
        $this->assertSame(AbuseSeverity::Alta, $porEmpresa[$alta->id]);
    }

    public function test_volume_contador_emite_finding_acima_do_limiar_por_criador(): void
    {
        // Limite reduzido a 3 (efeito sem deploy): 4 solicitações criadas pelo MESMO
        // ator (created_by — o "contador"/representante, HU-150) > 3 → alerta.
        $this->definirLimite('abuso.volume_contador.limite', 3);

        $contador = User::factory()->create();
        ViabilityRequest::factory()->count(4)->create(['created_by_user_id' => $contador->id]);

        // Outro criador com 3 (não excede) — não deve gerar finding.
        $outro = User::factory()->create();
        ViabilityRequest::factory()->count(3)->create(['created_by_user_id' => $outro->id]);

        $findings = $this->lista(app(VolumeContadorDetector::class)->detect($this->janela()));

        $this->assertCount(1, $findings);
        $finding = $findings[0];
        $this->assertSame('volume_contador', $finding->ruleKey);
        $this->assertSame(4, $finding->evidence['total']);
        $this->assertSame($contador->id, $finding->evidence['created_by_user_id']);
        $this->assertInstanceOf(User::class, $finding->subject);
        $this->assertTrue($finding->subject->is($contador));
    }

    public function test_keys_dos_detectores(): void
    {
        $this->assertSame('volume_cnpj', app(VolumeCnpjDetector::class)->key());
        $this->assertSame('volume_contador', app(VolumeContadorDetector::class)->key());
    }
}
