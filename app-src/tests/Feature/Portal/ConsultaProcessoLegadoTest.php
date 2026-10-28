<?php

namespace Tests\Feature\Portal;

use App\Enums\ViabilityRequestStatus;
use App\Models\Cidadao;
use App\Models\ViabilityRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Consulta de processo também no sistema anterior (Simplifica), quando o
 * número não é de processo do Viabiliza.
 */
class ConsultaProcessoLegadoTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const PROCESSO = '0000123456-20230101/0001';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        config([
            'viabiliza.integrations.legado_api.base_url' => 'https://legado.test/api',
            'viabiliza.integrations.legado_api.token' => 'token-teste',
        ]);
    }

    private function cidadao(): Cidadao
    {
        return Cidadao::factory()->withAcceptedLgpdTerm()->create();
    }

    /**
     * @return array<string, mixed>
     */
    private function processoLegado(): array
    {
        return ['data' => [
            'numero' => self::PROCESSO,
            'criado_em' => '2023-01-10T09:30:00-03:00',
            'status' => ['id' => 5, 'nome' => 'Emissão TVL - Deferido', 'fim' => true],
            'tvls' => [[
                'numero' => 2104040,
                'razao_social' => 'AMP CONSULTORIA E ACESSORIA LTDA',
                'titular' => ['documento' => '57461624000123', 'tipo' => 'cnpj', 'origem' => 'tvl'],
            ]],
            'historico' => [
                ['em' => '2023-01-10T09:30:00-03:00', 'titulo' => self::PROCESSO, 'mensagem' => 'Prezado Fulano, CPF 111.222.333-44', 'status' => 'Em análise'],
                ['em' => '2023-01-20T15:00:00-03:00', 'titulo' => self::PROCESSO, 'mensagem' => 'Texto interno', 'status' => 'Emissão TVL - Deferido'],
            ],
        ]];
    }

    private function consultar(string $numero): TestResponse
    {
        return $this->actingAs($this->cidadao())->get(route('portal.consulta', ['numero' => $numero]));
    }

    public function test_numero_do_processo_do_simplifica_mostra_o_processo_com_a_tramitacao(): void
    {
        Http::fake(['legado.test/api/v1/processos/*' => Http::response($this->processoLegado())]);

        $resposta = $this->consultar(self::PROCESSO)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('encontrado', true)
            ->where('processo.origem', 'legado')
            ->where('processo.protocol_number', self::PROCESSO)
            ->where('processo.numero_viabilidade', '2104040')
            ->where('processo.empresa.razao_social', 'AMP CONSULTORIA E ACESSORIA LTDA')
            ->where('processo.empresa.cnpj', '57.461.624/0001-23')
            ->where('processo.status.public_label', 'Emissão TVL - Deferido')
            ->where('processo.pode_responder', false)
            ->has('timeline.etapas', 2)
            ->where('timeline.etapas.0.rotulo', 'Em análise')
            ->where('timeline.etapas.1.rotulo', 'Emissão TVL - Deferido')
            ->where('timeline.status_atual.public_label', 'Emissão TVL - Deferido'));

        $props = json_encode($resposta->viewData('page')['props']);
        $this->assertStringNotContainsString('Prezado Fulano', (string) $props);
        $this->assertStringNotContainsString('Texto interno', (string) $props);
    }

    public function test_numero_de_tvl_do_simplifica_leva_ao_processo(): void
    {
        Http::fake([
            'legado.test/api/v1/tvls/2104040' => Http::response(['data' => [
                'numero' => 2104040, 'tipo' => 'provisoria', 'deferida' => true, 'ativa' => true,
                'processo_numero' => self::PROCESSO,
                'titular' => ['documento' => '57461624000123'],
                'razao_social' => 'AMP CONSULTORIA E ACESSORIA LTDA',
                'endereco' => ['logradouro' => null, 'numero' => null, 'bairro' => null, 'cep' => null],
                'atividades' => [],
            ]]),
            'legado.test/api/v1/processos/*' => Http::response($this->processoLegado()),
        ]);

        $this->consultar('2104040')->assertInertia(fn (Assert $page) => $page
            ->where('encontrado', true)
            ->where('processo.origem', 'legado')
            ->where('processo.protocol_number', self::PROCESSO));
    }

    public function test_processo_do_viabiliza_tem_prioridade(): void
    {
        Http::fake();
        $processo = ViabilityRequest::factory()->create();
        $processo->forceFill(['status' => ViabilityRequestStatus::EmAnalise, 'protocol_number' => '2026.000123', 'protocoled_at' => now()])->save();

        $this->consultar('2026.000123')->assertInertia(fn (Assert $page) => $page
            ->where('processo.origem', 'viabiliza')
            ->where('processo.protocol_number', '2026.000123'));

        Http::assertNothingSent();
    }

    public function test_simplifica_fora_do_ar_avisa(): void
    {
        Http::fake(['legado.test/*' => Http::response([], 503)]);

        $this->consultar(self::PROCESSO)->assertInertia(fn (Assert $page) => $page
            ->where('encontrado', false)
            ->where('legado_indisponivel', true));
    }

    public function test_numero_que_nao_existe_em_nenhum_dos_dois(): void
    {
        Http::fake(['legado.test/*' => Http::response(['mensagem' => 'Não encontrado'], 404)]);

        $this->consultar(self::PROCESSO)->assertInertia(fn (Assert $page) => $page
            ->where('encontrado', false)
            ->where('legado_indisponivel', false));
    }

    public function test_consulta_do_simplifica_e_auditada(): void
    {
        Http::fake(['legado.test/api/v1/processos/*' => Http::response($this->processoLegado())]);

        $this->consultar(self::PROCESSO)->assertOk();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'solicitacoes',
            'event' => 'consulta-processo-legado',
        ]);
    }
}
