<?php

namespace Tests\Feature\Relatorios;

use App\Enums\DecisionOutcome;
use App\Enums\ViabilityRequestStatus;
use App\Models\Cnae;
use App\Models\User;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Camada HTTP do Observatório de Saturação Locacional (Módulo 2): servido com
 * agregação REAL bairro×CNAE vs capacidade (parâmetro HU-014), gated por
 * consultar-relatorios e com a consulta auditada (RN-002). A exportação reusa o
 * contrato único (SolicitacoesReportSource via ?formato=). 403 sem permissão é
 * auditado no ponto único (CA-04).
 */
class SaturacaoControllerTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        Cache::flush();
        config([
            'sile.relatorios.saturacao.capacidades' => ['4712100' => 2],
            'sile.relatorios.saturacao.alerta_percentual' => 80,
            'sile.relatorios.saturacao.bloqueio_percentual' => 100,
        ]);
    }

    private function gestor(): User
    {
        return User::factory()->gestor()->withAcceptedLgpdTerm()->create();
    }

    private function analista(): User
    {
        return User::factory()->analista()->withAcceptedLgpdTerm()->create();
    }

    private function deferidaComCnae(string $bairro, Cnae $cnae): ViabilityRequest
    {
        $protocolo = 'VIA-'.now()->year.'-'.str_pad((string) ++$this->seq, 6, '0', STR_PAD_LEFT);

        $request = ViabilityRequest::factory()->create([
            'status' => ViabilityRequestStatus::Deferida,
            'protocol_number' => $protocolo,
            'protocoled_at' => now(),
            'address_neighborhood' => $bairro,
        ]);

        $request->cnaes()->attach($cnae->id, ['is_primary' => true]);

        ViabilityDecision::factory()->create([
            'viability_request_id' => $request->id,
            'outcome' => DecisionOutcome::Deferida,
            'decided_at' => now(),
            'tvl_product_number' => null,
        ]);

        return $request;
    }

    public function test_sem_consultar_relatorios_recebe_403_auditado(): void
    {
        $this->actingAs($this->analista(), 'gestao')
            ->get('/gestao/relatorios/saturacao')
            ->assertForbidden();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'seguranca',
            'event' => 'acesso-negado',
            'result' => 'bloqueado',
        ]);
    }

    public function test_saturacao_renderiza_inertia_com_agregacao_real_auditada(): void
    {
        $comercio = Cnae::factory()->create(['code' => '4712100']);

        $this->deferidaComCnae('Pituba', $comercio);
        $this->deferidaComCnae('Pituba', $comercio);
        $this->deferidaComCnae('Pituba', $comercio);

        $response = $this->actingAs($this->gestor(), 'gestao')
            ->get('/gestao/relatorios/saturacao')
            ->assertOk();

        $page = $response->viewData('page');
        $this->assertSame('gestao/relatorios/saturacao', $page['component']);

        $props = $page['props'];
        $this->assertSame(100.0, $props['limiares']['bloqueio']);

        $linha = collect($props['porBairroCnae'])->firstWhere('cnae', '4712100');
        $this->assertSame('Pituba', $linha['bairro']);
        $this->assertSame(3, $linha['ativos']);
        $this->assertSame(2, $linha['capacidade']);
        $this->assertSame('saturado', $linha['situacao']);

        $this->assertSame(1, $props['resumo']['saturados']);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'relatorios',
            'event' => 'consulta-saturacao',
            'result' => 'sucesso',
        ]);
    }

    public function test_saturacao_exporta_csv_pelo_contrato_unico(): void
    {
        $comercio = Cnae::factory()->create(['code' => '4712100']);
        $alvo = $this->deferidaComCnae('Pituba', $comercio);

        $response = $this->actingAs($this->gestor(), 'gestao')
            ->get('/gestao/relatorios/saturacao?formato=csv')
            ->assertOk();

        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString($alvo->protocol_number, $response->streamedContent());

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'relatorios',
            'event' => 'exporta-solicitacoes-csv',
            'result' => 'sucesso',
        ]);
    }
}
