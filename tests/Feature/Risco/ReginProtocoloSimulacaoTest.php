<?php

namespace Tests\Feature\Risco;

use App\Enums\TipoImovelReconhecimento;
use App\Models\User;
use App\Services\Regin\ReginProtocoloCatalog;
use App\Services\Regin\ReginProtocoloSimulacaoService;
use Database\Seeders\RiscoMunicipalSeeder;
use Database\Seeders\RiscoSanitarioSeeder;
use Database\Seeders\RiskTriggerSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Simulação de homologação: os protocolos SEDUR entram no motor REAL como se
 * o tipo de imóvel e a área tivessem chegado do REGIN. A origem é rotulada
 * como simulação — a integração REGIN continua stub.
 */
class ReginProtocoloSimulacaoTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolesAndPermissionsSeeder::class,
            RiscoMunicipalSeeder::class,
            RiscoSanitarioSeeder::class,
            RiskTriggerSeeder::class,
        ]);
    }

    public function test_catalogo_traz_os_dez_protocolos_da_pasta_de_validacao(): void
    {
        $catalogo = app(ReginProtocoloCatalog::class)->todos();

        $codigos = array_column($catalogo, 'codigo');

        $this->assertCount(10, $catalogo);
        $this->assertContains('43747', $codigos);
        $this->assertContains('abrigado-2108519', $codigos);
        $this->assertContains('sede-virtual', $codigos);
    }

    public function test_galpao_do_43747_dirige_regra_e_classifica_pelo_motor_real(): void
    {
        $relatorio = app(ReginProtocoloSimulacaoService::class)->simular('43747');

        $this->assertSame('simulacao_protocolo', $relatorio['origem']);
        $this->assertStringContainsString('REGIN', $relatorio['aviso']);
        $this->assertSame('Galpão', $relatorio['tipo_imovel']);
        $this->assertSame('galpao', $relatorio['tipo_imovel_normalized']);
        $this->assertSame(TipoImovelReconhecimento::DirigeRegra->value, $relatorio['tipo_imovel_reconhecimento']);
        $this->assertTrue($relatorio['tipo_imovel_dirige_regra']);
        $this->assertSame(834.0, $relatorio['area_utilizada']);
        $this->assertNotEmpty($relatorio['por_cnae']);
        $this->assertSame('6202-3/00', $relatorio['por_cnae'][0]['cnae']);
        $this->assertArrayHasKey('fluxo', $relatorio['por_cnae'][0]['risco']['encaminhamento']);
        $this->assertContains($relatorio['por_cnae'][0]['risco']['municipal']['status'], ['classificado', 'nao_classificado']);
        $this->assertSame('6202-3/00', $relatorio['consolidado']['cnae']);
        $this->assertSame('baixo_a', $relatorio['consolidado']['nivel']);
        $this->assertSame('expresso', $relatorio['consolidado']['fluxo']);
    }

    public function test_conjunto_e_classificado_pelo_cnae_de_maior_risco(): void
    {
        $relatorio = app(ReginProtocoloSimulacaoService::class)->simular('53514');

        $this->assertCount(5, $relatorio['por_cnae']);
        $this->assertSame('4771-7/01', $relatorio['consolidado']['cnae']);
        $this->assertSame('alto', $relatorio['consolidado']['nivel']);
        $this->assertSame('Alto', $relatorio['consolidado']['nivel_label']);
        $this->assertSame('analise', $relatorio['consolidado']['fluxo']);
        $this->assertStringContainsString('conjunto', mb_strtolower((string) $relatorio['consolidado']['motivo']));
    }

    public function test_abrigado_sem_tipo_nao_inventa_galpao(): void
    {
        $relatorio = app(ReginProtocoloSimulacaoService::class)->simular('abrigado-2108519');

        $this->assertNull($relatorio['tipo_imovel']);
        $this->assertSame(TipoImovelReconhecimento::Ausente->value, $relatorio['tipo_imovel_reconhecimento']);
        $this->assertFalse($relatorio['tipo_imovel_dirige_regra']);
        $this->assertFalse($relatorio['tipo_imovel_permite_decisao_automatica']);
    }

    public function test_edificacao_comercial_cai_no_ramo_comum(): void
    {
        $relatorio = app(ReginProtocoloSimulacaoService::class)->simular('33072');

        $this->assertSame('edificacao_comercial', $relatorio['tipo_imovel_normalized']);
        $this->assertSame(TipoImovelReconhecimento::RamoComum->value, $relatorio['tipo_imovel_reconhecimento']);
        $this->assertFalse($relatorio['tipo_imovel_dirige_regra']);
        $this->assertTrue($relatorio['tipo_imovel_permite_decisao_automatica']);
    }

    public function test_protocolo_desconhecido_e_rejeitado(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(ReginProtocoloSimulacaoService::class)->simular('nao-existe');
    }

    public function test_tela_lista_protocolos_e_simula_o_43747(): void
    {
        $gestor = User::factory()->administrador()->withAcceptedLgpdTerm()->create();

        $this->actingAs($gestor, 'gestao')
            ->get('/gestao/risco/simulacao-regin')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/risco/simulacao-regin')
                ->has('protocolos', 10)
                ->has('execucoes', 0)
                ->where('aviso', fn ($aviso) => is_string($aviso) && str_contains($aviso, 'REGIN')));

        $this->actingAs($gestor, 'gestao')
            ->post('/gestao/risco/simulacao-regin', ['codigo' => '43747'])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/risco/simulacao-regin')
                ->where('relatorio.tipo_imovel_normalized', 'galpao')
                ->where('relatorio.area_utilizada', 834)
                ->has('relatorio.por_cnae', 1)
                ->where('relatorio.consolidado.cnae', '6202-3/00')
                ->where('relatorio.consolidado.fluxo', 'expresso')
                ->has('relatorio.por_cnae.0.risco.municipal.nivel_label')
                ->has('relatorio.por_cnae.0.risco.sanitario.status')
                ->has('relatorio.por_cnae.0.risco.encaminhamento.motivo')
                ->has('relatorio.por_cnae.0.risco.encaminhamento.dimensao_decisiva')
                ->has('relatorio.por_cnae.0.risco.fundamentacao')
                ->has('relatorio.por_cnae.0.risco.versoes'));
    }

    public function test_resultado_persiste_e_reaparece_depois_do_get(): void
    {
        $gestor = User::factory()->administrador()->withAcceptedLgpdTerm()->create();

        $this->actingAs($gestor, 'gestao')
            ->post('/gestao/risco/simulacao-regin', ['codigo' => '43747'])
            ->assertOk();

        $this->actingAs($gestor, 'gestao')
            ->get('/gestao/risco/simulacao-regin')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('relatorio.codigo', '43747')
                ->has('execucoes', 1)
                ->where('execucoes.0.codigo', '43747'));
    }

    public function test_nova_simulacao_nao_mistura_processo_real_e_substitui_o_mesmo_codigo(): void
    {
        $gestor = User::factory()->administrador()->withAcceptedLgpdTerm()->create();

        $this->actingAs($gestor, 'gestao')->post('/gestao/risco/simulacao-regin', ['codigo' => '43747']);
        $this->actingAs($gestor, 'gestao')->post('/gestao/risco/simulacao-regin', ['codigo' => '53514']);
        $this->actingAs($gestor, 'gestao')->post('/gestao/risco/simulacao-regin', ['codigo' => '43747']);

        $this->actingAs($gestor, 'gestao')
            ->get('/gestao/risco/simulacao-regin')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('relatorio.codigo', '43747')
                ->has('execucoes', 2));
    }

    public function test_apagar_remove_o_resultado_para_refazer(): void
    {
        $gestor = User::factory()->administrador()->withAcceptedLgpdTerm()->create();

        $this->actingAs($gestor, 'gestao')->post('/gestao/risco/simulacao-regin', ['codigo' => '43747']);

        $this->actingAs($gestor, 'gestao')
            ->delete('/gestao/risco/simulacao-regin/43747')
            ->assertRedirect(route('gestao.risco.simulacao-regin'));

        $this->actingAs($gestor, 'gestao')
            ->get('/gestao/risco/simulacao-regin')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('relatorio', null)
                ->has('execucoes', 0));
    }

    public function test_comando_simula_protocolo_no_motor_real(): void
    {
        $this->artisan('risco:simular-protocolo', ['codigo' => '43747'])
            ->expectsOutputToContain('Galpão')
            ->expectsOutputToContain('galpao')
            ->expectsOutputToContain('834')
            ->expectsOutputToContain('6202-3/00')
            ->expectsOutputToContain('simulação')
            ->expectsOutputToContain('conjunto')
            ->assertSuccessful();
    }
}
