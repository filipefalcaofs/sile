<?php

namespace Tests\Feature\Solicitacao;

use App\Models\Company;
use App\Models\ViabilityRequest;
use App\Services\Solicitacao\DuplicateRequestDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Detecção de duplicidade/reincidência por CNPJ (HU-061 RN-007): o detector
 * APONTA o processo anterior da mesma empresa para gerar alerta + link; NUNCA
 * bloqueia (direito de petição). A janela de recência é uma constante de
 * config (definição é pendência SEDUR — default honesto). Resolução por
 * inscrição imobiliária degrada (lote bloqueado): só por CNPJ nesta fase.
 */
class DuplicateRequestDetectorTest extends TestCase
{
    use RefreshDatabase;

    private function detector(): DuplicateRequestDetector
    {
        return app(DuplicateRequestDetector::class);
    }

    public function test_sem_processo_anterior_retorna_null(): void
    {
        $company = Company::factory()->create();

        $this->assertNull($this->detector()->detect($company));
    }

    public function test_aponta_processo_recente_da_mesma_empresa(): void
    {
        $company = Company::factory()->create();
        $previous = ViabilityRequest::factory()->protocoled()->create([
            'company_id' => $company->id,
        ]);

        $alert = $this->detector()->detect($company);

        $this->assertNotNull($alert);
        $this->assertSame($previous->id, $alert['request_id']);
        $this->assertSame($previous->protocol_number, $alert['protocol_number']);
        $this->assertSame('protocolada', $alert['status']);
        $this->assertArrayHasKey('created_at', $alert);
    }

    public function test_ignora_solicitacoes_de_outra_empresa(): void
    {
        $company = Company::factory()->create();
        $other = Company::factory()->create();
        ViabilityRequest::factory()->protocoled()->create(['company_id' => $other->id]);

        $this->assertNull($this->detector()->detect($company));
    }

    public function test_ignora_canceladas(): void
    {
        $company = Company::factory()->create();
        ViabilityRequest::factory()->cancelled()->create(['company_id' => $company->id]);

        $this->assertNull($this->detector()->detect($company));
    }

    public function test_rascunho_antigo_fora_da_janela_nao_alerta(): void
    {
        $company = Company::factory()->create();
        $old = ViabilityRequest::factory()->draft()->create(['company_id' => $company->id]);
        ViabilityRequest::query()->whereKey($old->id)->update([
            'created_at' => now()->subDays(400),
        ]);

        $this->assertNull($this->detector()->detect($company));
    }

    public function test_protocolada_antiga_ainda_alerta_por_ser_ativa(): void
    {
        $company = Company::factory()->create();
        $old = ViabilityRequest::factory()->protocoled()->create(['company_id' => $company->id]);
        ViabilityRequest::query()->whereKey($old->id)->update([
            'created_at' => now()->subDays(400),
        ]);

        $alert = $this->detector()->detect($company);

        $this->assertNotNull($alert);
        $this->assertSame($old->id, $alert['request_id']);
    }

    public function test_exclui_a_propria_solicitacao(): void
    {
        $company = Company::factory()->create();
        $current = ViabilityRequest::factory()->draft()->create(['company_id' => $company->id]);

        $this->assertNull($this->detector()->detect($company, $current->id));
    }

    public function test_retorna_a_mais_recente(): void
    {
        $company = Company::factory()->create();
        $older = ViabilityRequest::factory()->protocoled()->create(['company_id' => $company->id]);
        ViabilityRequest::query()->whereKey($older->id)->update(['created_at' => now()->subDays(5)]);
        $newer = ViabilityRequest::factory()->draft()->create(['company_id' => $company->id]);

        $alert = $this->detector()->detect($company);

        $this->assertNotNull($alert);
        $this->assertSame($newer->id, $alert['request_id']);
    }
}
