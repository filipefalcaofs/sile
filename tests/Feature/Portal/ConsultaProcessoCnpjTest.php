<?php

namespace Tests\Feature\Portal;

use App\Enums\ViabilityRequestStatus;
use App\Models\Company;
use App\Models\User;
use App\Models\ViabilityRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Consulta de processo pelo CNPJ: lista os processos da empresa no Viabiliza
 * e no sistema anterior (Simplifica), cada um levando à consulta do número.
 */
class ConsultaProcessoCnpjTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const CNPJ = '57461624000123';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        config([
            'viabiliza.integrations.legado_api.base_url' => 'https://legado.test/api',
            'viabiliza.integrations.legado_api.token' => 'token-teste',
        ]);
    }

    private function consultar(string $numero): TestResponse
    {
        return $this->actingAs(User::factory()->cidadao()->withAcceptedLgpdTerm()->create())
            ->get(route('portal.consulta', ['numero' => $numero]));
    }

    private function processoDaEmpresa(string $protocolo, ViabilityRequestStatus $status = ViabilityRequestStatus::EmAnalise): ViabilityRequest
    {
        $empresa = Company::query()->firstOrCreate(['cnpj' => self::CNPJ], Company::factory()->raw(['cnpj' => self::CNPJ, 'legal_name' => 'AMP CONSULTORIA E ACESSORIA LTDA']));
        $processo = ViabilityRequest::factory()->create(['company_id' => $empresa->id]);
        $processo->forceFill(['status' => $status, 'protocol_number' => $protocolo, 'protocoled_at' => now()->subDay()])->save();

        return $processo;
    }

    private function legadoComUmProcesso(): void
    {
        Http::fake(['legado.test/api/v1/processos?*' => Http::response(['data' => [[
            'numero' => '5921000030-00028619/2026',
            'criado_em' => '2026-04-29T11:13:00-03:00',
            'status' => ['id' => 5, 'nome' => 'Emissão TVL - Deferido', 'fim' => true],
            'numeros_tvl' => [2104040],
        ]]])]);
    }

    public function test_cnpj_lista_processos_do_viabiliza_e_do_simplifica(): void
    {
        $this->legadoComUmProcesso();
        $this->processoDaEmpresa('VIA-2026-000001');
        $this->processoDaEmpresa('VIA-2026-000002', ViabilityRequestStatus::Rascunho);

        $this->consultar('57.461.624/0001-23')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('encontrado', true)
            ->where('processo', null)
            ->has('lista', 2)
            ->where('lista.0.origem', 'viabiliza')
            ->where('lista.0.numero', 'VIA-2026-000001')
            ->where('lista.0.empresa', 'AMP CONSULTORIA E ACESSORIA LTDA')
            ->where('lista.1.origem', 'legado')
            ->where('lista.1.numero', '5921000030-00028619/2026')
            ->where('lista.1.situacao', 'Emissão TVL - Deferido'));

        Http::assertSent(fn ($request) => str_contains($request->url(), '/v1/processos') && $request['documento'] === self::CNPJ);
    }

    public function test_cnpj_sem_processos_em_nenhum_dos_dois(): void
    {
        Http::fake(['legado.test/*' => Http::response(['data' => []])]);

        $this->consultar(self::CNPJ)->assertInertia(fn (Assert $page) => $page
            ->where('encontrado', false)
            ->where('lista', null));
    }

    public function test_simplifica_fora_do_ar_mantem_o_viabiliza_e_avisa(): void
    {
        Http::fake(['legado.test/*' => Http::response([], 503)]);
        $this->processoDaEmpresa('VIA-2026-000001');

        $this->consultar(self::CNPJ)->assertInertia(fn (Assert $page) => $page
            ->where('encontrado', true)
            ->has('lista', 1)
            ->where('legado_indisponivel', true));
    }

    public function test_cnpj_nao_e_tratado_como_numero_de_processo(): void
    {
        Http::fake(['legado.test/*' => Http::response(['data' => []])]);
        $this->processoDaEmpresa(self::CNPJ);

        $this->consultar(self::CNPJ)->assertInertia(fn (Assert $page) => $page
            ->where('processo', null)
            ->has('lista', 1));
    }
}
