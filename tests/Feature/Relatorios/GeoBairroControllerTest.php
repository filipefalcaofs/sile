<?php

namespace Tests\Feature\Relatorios;

use App\Enums\DecisionOutcome;
use App\Enums\ViabilityRequestStatus;
use App\Models\User;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Camada HTTP do painel geoeconômico por bairro (Geo BI interno — Módulo 1):
 * servido com agregação REAL, gated por consultar-relatorios e com a consulta
 * auditada (RN-002). A exportação reusa o contrato único (SolicitacoesReportSource
 * via ?formato=), sem reimplementar filtro (RN-005). O 403 sem a permissão é
 * auditado no ponto único (CA-04).
 */
class GeoBairroControllerTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function gestor(): User
    {
        return User::factory()->gestor()->withAcceptedLgpdTerm()->create();
    }

    private function analista(): User
    {
        return User::factory()->analista()->withAcceptedLgpdTerm()->create();
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function protocolada(array $attrs = []): ViabilityRequest
    {
        $protocolo = 'VIA-'.now()->year.'-'.str_pad((string) ++$this->seq, 6, '0', STR_PAD_LEFT);

        return ViabilityRequest::factory()->create(array_merge([
            'status' => ViabilityRequestStatus::Protocolada,
            'protocol_number' => $protocolo,
            'protocoled_at' => now(),
        ], $attrs));
    }

    private function decisao(ViabilityRequest $request, DecisionOutcome $outcome): void
    {
        $factory = ViabilityDecision::factory();

        if ($outcome === DecisionOutcome::Indeferida) {
            $factory = $factory->indeferida();
        }

        $factory->create([
            'viability_request_id' => $request->id,
            'outcome' => $outcome,
            'decided_at' => now(),
            'tvl_product_number' => null,
        ]);
    }

    public function test_sem_consultar_relatorios_recebe_403_auditado(): void
    {
        $this->actingAs($this->analista(), 'gestao')
            ->get('/gestao/relatorios/geo-bairro')
            ->assertForbidden();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'seguranca',
            'event' => 'acesso-negado',
            'result' => 'bloqueado',
        ]);
    }

    public function test_geo_bairro_renderiza_inertia_com_agregacao_real_auditada(): void
    {
        $pituba1 = $this->protocolada(['address_neighborhood' => 'Pituba']);
        $this->protocolada(['address_neighborhood' => 'Pituba']);
        $barra = $this->protocolada(['address_neighborhood' => 'Barra']);
        $this->protocolada(['address_neighborhood' => null]);

        $this->decisao($pituba1, DecisionOutcome::Deferida);
        $this->decisao($barra, DecisionOutcome::Indeferida);

        $response = $this->actingAs($this->gestor(), 'gestao')
            ->get('/gestao/relatorios/geo-bairro')
            ->assertOk();

        $page = $response->viewData('page');
        $this->assertSame('gestao/relatorios/geo-bairro', $page['component']);

        $props = $page['props'];
        $this->assertSame('bairro', $props['porBairro']['degradacao']);

        $pituba = collect($props['porBairro']['itens'])->firstWhere('bairro', 'Pituba');
        $this->assertSame(2, $pituba['total']);
        $this->assertSame(1, $pituba['deferidas']);

        $this->assertSame(4, $props['resumo']['total']);
        $this->assertSame(2, $props['resumo']['bairros_distintos']);
        $this->assertSame(1, $props['resumo']['sem_bairro']);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'relatorios',
            'event' => 'consulta-geo-bairro',
            'result' => 'sucesso',
        ]);
    }

    public function test_geo_bairro_exporta_csv_pelo_contrato_unico(): void
    {
        $alvo = $this->protocolada(['address_neighborhood' => 'Pituba']);

        $response = $this->actingAs($this->gestor(), 'gestao')
            ->get('/gestao/relatorios/geo-bairro?formato=csv')
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
