<?php

namespace Tests\Feature\Risco;

use App\Enums\ResultadoViabilidade;
use App\Enums\RuleDomain;
use App\Enums\TipoImovelReconhecimento;
use App\Enums\ViabilityRequestOrigin;
use App\Enums\ViabilityRequestStatus;
use App\Models\RuleVersion;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Regin\ReginProtocoloCatalog;
use App\Services\Regin\ReginProtocoloSimulacaoService;
use App\Services\Solicitacao\SolicitacaoViabilityResolver;
use App\Services\Tratamento\TratamentoRegrasImportService;
use Database\Seeders\LouosQuadro10Seeder;
use Database\Seeders\LouosQuadro11Seeder;
use Database\Seeders\PropertyTypeSeeder;
use Database\Seeders\RiscoMunicipalSeeder;
use Database\Seeders\RiscoSanitarioSeeder;
use Database\Seeders\RiskTriggerSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Simulação de homologação: os protocolos SEDUR entram no motor REAL como se
 * o tipo de imóvel e a área tivessem chegado do REGIN. Cada simulação cria
 * um processo real (Alto → análise; Baixo/Médio → expresso com TVL). A
 * origem no relatório continua rotulada como simulação — a integração REGIN
 * segue indisponível.
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
            PropertyTypeSeeder::class,
        ]);

        $this->actingAs(
            User::factory()->administrador()->withAcceptedLgpdTerm()->create(),
            'gestao',
        );
    }

    public function test_catalogo_traz_os_protocolos_da_pasta_de_validacao(): void
    {
        $catalogo = app(ReginProtocoloCatalog::class)->todos();

        $codigos = array_column($catalogo, 'codigo');

        $this->assertCount(43, $catalogo);
        $this->assertContains('43747', $codigos);
        $this->assertContains('abrigado-2108519', $codigos);
        $this->assertContains('sede-virtual', $codigos);
        $this->assertContains('13336', $codigos);
        $this->assertContains('8225', $codigos);
        $this->assertContains('61131', $codigos);
        $this->assertContains('8195', $codigos);
        $this->assertContains('8161', $codigos);
        $this->assertContains('2250', $codigos);
        $this->assertContains('855', $codigos);
        $this->assertContains('375', $codigos);
        $this->assertContains('244', $codigos);
        $this->assertContains('207', $codigos);
        $this->assertContains('dupla-r1-r20', $codigos);
        $this->assertContains('dupla-r8-r15', $codigos);
        $this->assertContains('dupla-r21-r22', $codigos);
        $this->assertContains('dupla-r46-r47', $codigos);
        $this->assertContains('dupla-r24-r48', $codigos);
        $this->assertContains('dupla-r6-r23', $codigos);
        $this->assertContains('dupla-r38-r4', $codigos);
        $this->assertContains('dupla-r49-r50', $codigos);
        $this->assertContains('regra-1-escritorio', $codigos);
        $this->assertContains('regra-24', $codigos);
        $this->assertContains('regra-25-artesanal', $codigos);
        $this->assertContains('regra-26', $codigos);
        $this->assertContains('regra-27', $codigos);
        $this->assertContains('regra-51', $codigos);
        $this->assertContains('regra-52-industrial', $codigos);
        $this->assertContains('dupla-r1-r24', $codigos);
        $this->assertContains('dupla-r25-r52', $codigos);
        $this->assertContains('dupla-r26-r51', $codigos);
    }

    public function test_novos_protocolos_trazem_cnae_zona_via_e_respostas_do_pdf(): void
    {
        $catalogo = app(ReginProtocoloCatalog::class);

        $lanchonete = $catalogo->porCodigo('8225');
        $this->assertSame('5921000030-00008225/2026', $lanchonete['processo']);
        $this->assertSame('Edificação Comercial', $lanchonete['tipo_imovel']);
        $this->assertSame(87.0, $lanchonete['area_utilizada']);
        $this->assertSame('ZCMe-1/02', $lanchonete['zona']);
        $this->assertSame('VL', $lanchonete['via']);
        $this->assertSame('5611-2/03', $lanchonete['atividades'][0]['cnae']);
        $this->assertTrue($lanchonete['atividades'][0]['perguntas'][0]['valor']);

        $residencial = $catalogo->porCodigo('13336');
        $this->assertSame('Edificação Residencial', $residencial['tipo_imovel']);
        $this->assertSame(8.0, $residencial['area_utilizada']);
        $this->assertSame('ZEIS 1', $residencial['zona']);
        $this->assertFalse($residencial['atividades'][0]['perguntas'][0]['valor']);

        $hospital = $catalogo->porCodigo('207');
        $this->assertCount(3, $hospital['atividades']);
        $this->assertSame('8610-1/01', $hospital['atividades'][0]['cnae']);
        $this->assertSame('P11', $hospital['atividades'][0]['perguntas'][0]['codigo']);
        $this->assertTrue($hospital['atividades'][0]['perguntas'][0]['valor']);
        $this->assertFalse($hospital['atividades'][2]['perguntas'][0]['valor']);
    }

    public function test_duplas_novas_tem_dois_cnaes_com_regras_distintas_e_nao_alteram_os_existentes(): void
    {
        $catalogo = app(ReginProtocoloCatalog::class);
        $todos = $catalogo->todos();

        $existente = $catalogo->porCodigo('8225');
        $this->assertSame('5921000030-00008225/2026', $existente['processo']);
        $this->assertCount(1, $existente['atividades']);
        $this->assertSame('5611-2/03', $existente['atividades'][0]['cnae']);

        $pares = [
            'dupla-r1-r20' => ['1011-2/01', '4721-1/03'],
            'dupla-r8-r15' => ['4713-0/04', '8591-1/00'],
            'dupla-r21-r22' => ['4520-0/01', '1731-1/00'],
            'dupla-r46-r47' => ['8122-2/00', '1041-4/00'],
            'dupla-r24-r48' => ['1063-5/00', '5620-1/01'],
            'dupla-r6-r23' => ['1020-1/01', '3811-4/00'],
            'dupla-r38-r4' => ['8411-6/00', '1822-9/01'],
            'dupla-r49-r50' => ['4639-7/02', '8630-5/02'],
        ];

        foreach ($pares as $codigo => $cnaes) {
            $protocolo = $catalogo->porCodigo($codigo);
            $this->assertCount(2, $protocolo['atividades'], $codigo);
            $this->assertSame($cnaes[0], $protocolo['atividades'][0]['cnae'], $codigo);
            $this->assertSame($cnaes[1], $protocolo['atividades'][1]['cnae'], $codigo);
            $this->assertNotSame(
                $this->regraAtividadeDaPlanilha($cnaes[0]),
                $this->regraAtividadeDaPlanilha($cnaes[1]),
                $codigo.' precisa de regras distintas na planilha',
            );
        }

        $this->assertSame(20, count(array_filter(
            $todos,
            static fn (array $p): bool => ! str_starts_with((string) $p['codigo'], 'dupla-')
                && ! str_starts_with((string) $p['codigo'], 'regra-'),
        )));
    }

    public function test_massa_das_regras_1_24_a_27_e_51_52_e_isolada_e_combinada(): void
    {
        $catalogo = app(ReginProtocoloCatalog::class);

        $this->assertSame('5611-2/03', $catalogo->porCodigo('8225')['atividades'][0]['cnae']);
        $this->assertSame(['1011-2/01', '4721-1/03'], array_column($catalogo->porCodigo('dupla-r1-r20')['atividades'], 'cnae'));

        $isolados = [
            'regra-1-escritorio' => ['4511-1/01', '1'],
            'regra-1-nr' => ['4511-1/01', '1'],
            'regra-1-id' => ['1013-9/01', '1'],
            'regra-1-galpao' => ['4511-1/01', '1'],
            'regra-24' => ['1064-3/00', '24|25'],
            'regra-25-artesanal' => ['1064-3/00', '24|25'],
            'regra-25-industrial' => ['1099-6/05', '24|25'],
            'regra-26' => ['4789-0/04', '26|27'],
            'regra-27' => ['4789-0/04', '26|27'],
            'regra-51' => ['1032-5/01', '51|52'],
            'regra-52-artesanal' => ['1032-5/01', '51|52'],
            'regra-52-industrial' => ['1053-8/00', '51|52'],
        ];

        foreach ($isolados as $codigo => [$cnae, $regra]) {
            $protocolo = $catalogo->porCodigo($codigo);
            $this->assertCount(1, $protocolo['atividades'], $codigo);
            $this->assertSame($cnae, $protocolo['atividades'][0]['cnae'], $codigo);
            $this->assertSame($regra, $this->regraAtividadeDaPlanilha($cnae), $codigo);
        }

        $this->assertSame('Galpão', $catalogo->porCodigo('regra-1-galpao')['tipo_imovel']);
        $this->assertFalse($catalogo->porCodigo('regra-1-escritorio')['atividades'][0]['perguntas'][0]['valor']);
        $this->assertTrue($catalogo->porCodigo('regra-1-nr')['atividades'][0]['perguntas'][0]['valor']);
        $this->assertFalse($catalogo->porCodigo('regra-24')['atividades'][0]['perguntas'][0]['valor']);
        $this->assertTrue($catalogo->porCodigo('regra-25-artesanal')['atividades'][0]['perguntas'][0]['valor']);
        $this->assertTrue($catalogo->porCodigo('regra-25-artesanal')['atividades'][0]['perguntas'][1]['valor']);
        $this->assertTrue($catalogo->porCodigo('regra-25-industrial')['atividades'][0]['perguntas'][0]['valor']);
        $this->assertFalse($catalogo->porCodigo('regra-25-industrial')['atividades'][0]['perguntas'][1]['valor']);
        $this->assertFalse($catalogo->porCodigo('regra-26')['atividades'][0]['perguntas'][0]['valor']);
        $this->assertTrue($catalogo->porCodigo('regra-27')['atividades'][0]['perguntas'][0]['valor']);
        $this->assertFalse($catalogo->porCodigo('regra-51')['atividades'][0]['perguntas'][0]['valor']);

        $combinados = [
            'dupla-r1-r24' => ['4530-7/03', '1064-3/00'],
            'dupla-r25-r52' => ['1099-6/05', '1032-5/01'],
            'dupla-r26-r51' => ['4789-0/04', '1061-9/02'],
        ];

        foreach ($combinados as $codigo => $cnaes) {
            $protocolo = $catalogo->porCodigo($codigo);
            $this->assertCount(2, $protocolo['atividades'], $codigo);
            $this->assertSame($cnaes, array_column($protocolo['atividades'], 'cnae'), $codigo);
            $this->assertNotSame(
                $this->regraAtividadeDaPlanilha($cnaes[0]),
                $this->regraAtividadeDaPlanilha($cnaes[1]),
                $codigo,
            );
        }
    }

    public function test_artesanal_das_regras_25_e_52_fica_pendente_no_quadro_10(): void
    {
        $this->seedPlanilhaTratamento();
        $this->seed([LouosQuadro10Seeder::class, LouosQuadro11Seeder::class]);

        foreach (['regra-25-artesanal', 'regra-52-artesanal'] as $codigo) {
            $entrada = $this->entradaQueFechaPendencias($codigo);

            $this->post('/gestao/risco/simulacao-regin', $entrada)
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('pendencias', null)
                    ->where('relatorio.codigo', $codigo));

            $processo = ViabilityRequest::query()
                ->where('external_reference', app(ReginProtocoloCatalog::class)->porCodigo($codigo)['processo'])
                ->latest('id')
                ->first();

            $this->assertNotNull($processo, $codigo);

            $resolvido = app(SolicitacaoViabilityResolver::class)->resolve($processo);

            $this->assertSame(ResultadoViabilidade::Pendente->value, $resolvido->consolidado, $codigo);
        }
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
        $this->assertSame('analise', $relatorio['consolidado']['fluxo']);
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
                ->has('protocolos', 43)
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
                ->where('relatorio.consolidado.fluxo', 'analise')
                ->where('relatorio.status', ViabilityRequestStatus::EmAnalise->value)
                ->has('relatorio.protocol_number')
                ->has('relatorio.processo_id')
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

        $processoId = ViabilityRequest::query()->value('id');

        $this->actingAs($gestor, 'gestao')
            ->delete('/gestao/risco/simulacao-regin/43747')
            ->assertRedirect(route('gestao.risco.simulacao-regin'));

        $this->assertNull(ViabilityRequest::query()->find($processoId));

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

    public function test_simulacao_baixo_cria_processo_expresso_com_tvl(): void
    {
        config(['sile.features.simulacao_protocolo' => true]);

        $relatorio = app(ReginProtocoloSimulacaoService::class)->simular('33072');

        $this->assertNotNull($relatorio['processo_id']);
        $this->assertMatchesRegularExpression('/^VIA-\d{4}-\d{6}$/', (string) $relatorio['protocol_number']);
        $this->assertSame(ViabilityRequestStatus::Deferida->value, $relatorio['status']);
        $this->assertNotEmpty($relatorio['tvl']);
        $this->assertStringContainsString('/gestao/processos/', (string) $relatorio['processo_url']);

        $processo = ViabilityRequest::query()->find($relatorio['processo_id']);

        $this->assertNotNull($processo);
        $this->assertSame(ViabilityRequestOrigin::Regin, $processo->origin);
        $this->assertSame('5921000030-00033072/2026', $processo->external_reference);
        $this->assertSame(ViabilityRequestStatus::Deferida, $processo->status);
        $this->assertSame('simulacao_protocolo', $processo->contingency_reason);
        $this->assertSame('expresso', $processo->simulation_resultado);
        $this->assertSame($relatorio['tvl'], $processo->decision?->tvl_product_number);
        $this->assertSame('6622300', $processo->primaryCnae()->value('code'));
    }

    public function test_simulacao_alto_encaminha_para_analise_sem_tvl(): void
    {
        $relatorio = app(ReginProtocoloSimulacaoService::class)->simular('53514');

        $this->assertNotNull($relatorio['processo_id']);
        $this->assertSame(ViabilityRequestStatus::EmAnalise->value, $relatorio['status']);
        $this->assertNull($relatorio['tvl']);

        $processo = ViabilityRequest::query()->find($relatorio['processo_id']);

        $this->assertNotNull($processo);
        $this->assertSame(ViabilityRequestStatus::EmAnalise, $processo->status);
        $this->assertNull($processo->decision);
        $this->assertTrue($processo->expressoQuedas()->exists());
        $this->assertTrue($processo->analysisRecords()->exists());
        $this->assertSame(5, $processo->cnaes()->count());
        $this->assertSame('0010010010', $processo->property_registration);
    }

    public function test_simulacao_grava_zona_urbanistica_e_nao_usa_como_bairro(): void
    {
        $relatorio = app(ReginProtocoloSimulacaoService::class)->simular('53528');

        $processo = ViabilityRequest::query()->find($relatorio['processo_id']);

        $this->assertNotNull($processo);
        $this->assertSame('ZCMe-1/03', $processo->zona_codigo);
        $this->assertSame('ZCMe-1/03', $relatorio['zona']);
        $this->assertNotSame('ZCMe-1/03', $processo->address_neighborhood);
    }

    public function test_ressimular_nao_duplica_o_processo(): void
    {
        $primeiro = app(ReginProtocoloSimulacaoService::class)->simular('43747');
        $segundo = app(ReginProtocoloSimulacaoService::class)->simular('43747');

        $this->assertSame($primeiro['processo_id'], $segundo['processo_id']);
        $this->assertSame($primeiro['protocol_number'], $segundo['protocol_number']);
        $this->assertSame(1, ViabilityRequest::query()->where('external_reference', '5921000030-00043747/2026')->count());
    }

    public function test_apagar_remove_o_processo_criado_pela_simulacao(): void
    {
        $relatorio = app(ReginProtocoloSimulacaoService::class)->simular('43747');
        $processoId = $relatorio['processo_id'];

        app(ReginProtocoloSimulacaoService::class)->apagar('43747');

        $this->assertNull(ViabilityRequest::query()->find($processoId));
    }

    public function test_33072_sem_resposta_da_planilha_nao_roda_e_pede_o_que_falta(): void
    {
        $this->seedPlanilhaTratamento();

        $this->post('/gestao/risco/simulacao-regin', ['codigo' => '33072'])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/risco/simulacao-regin')
                ->where('relatorio', null)
                ->where('pendencias.codigo', '33072')
                ->has('pendencias.perguntas', 1)
                ->where('pendencias.perguntas.0.numero', 11)
                ->where('pendencias.perguntas.0.cnae', '6622-3/00'));

        $this->assertSame(0, ViabilityRequest::query()->count());
    }

    public function test_43747_com_11a_vedado_indefere_mesmo_com_galpao(): void
    {
        $this->seedPlanilhaTratamento();
        $this->seed([LouosQuadro10Seeder::class, LouosQuadro11Seeder::class]);

        $entrada = $this->entradaQueFechaPendencias('43747');

        $this->post('/gestao/risco/simulacao-regin', $entrada)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/risco/simulacao-regin')
                ->where('pendencias', null)
                ->where('relatorio.codigo', '43747')
                ->where('relatorio.status', ViabilityRequestStatus::Indeferida->value)
                ->where('relatorio.tvl', null));

        $processo = ViabilityRequest::query()->first();

        $this->assertNotNull($processo);
        $this->assertSame(ViabilityRequestStatus::Indeferida, $processo->status);
        $this->assertNull($processo->decision?->tvl_product_number);
        $this->assertSame(
            ResultadoViabilidade::NaoPermitido->value,
            app(SolicitacaoViabilityResolver::class)->resolve($processo)->consolidado,
        );
    }

    public function test_33072_com_resposta_e_territorio_do_catalogo_defere(): void
    {
        $this->seedPlanilhaTratamento();
        $this->seed([LouosQuadro10Seeder::class, LouosQuadro11Seeder::class]);

        $this->post('/gestao/risco/simulacao-regin', [
            'codigo' => '33072',
            'respostas' => ['6622300' => ['11' => false]],
        ])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/risco/simulacao-regin')
                ->where('pendencias', null)
                ->where('relatorio.status', ViabilityRequestStatus::Deferida->value)
                ->where('relatorio.codigo', '33072'));

        $processo = ViabilityRequest::query()->first();

        $this->assertNotNull($processo);
        $this->assertSame(ViabilityRequestStatus::Deferida, $processo->status);
        $this->assertNotEmpty($processo->decision?->tvl_product_number);
    }

    public function test_nenhum_protocolo_do_catalogo_fica_com_veredito_pendente(): void
    {
        $this->seedPlanilhaTratamento();
        $this->seed([LouosQuadro10Seeder::class, LouosQuadro11Seeder::class]);

        $codigos = array_column(app(ReginProtocoloCatalog::class)->todos(), 'codigo');
        $linhas = [];
        $pendentesQuadro10PorChaveDeSubgrupo = [
            'regra-25-artesanal',
            'regra-52-artesanal',
        ];

        foreach ($codigos as $codigo) {
            if (in_array($codigo, $pendentesQuadro10PorChaveDeSubgrupo, true)) {
                continue;
            }
            $entrada = $this->entradaQueFechaPendencias($codigo);

            $this->post('/gestao/risco/simulacao-regin', $entrada)
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('pendencias', null)
                    ->where('relatorio.codigo', $codigo)
                    ->where('relatorio.status', fn (string $status): bool => in_array($status, [
                        ViabilityRequestStatus::Deferida->value,
                        ViabilityRequestStatus::Indeferida->value,
                        ViabilityRequestStatus::EmAnalise->value,
                    ], true)));

            $processo = ViabilityRequest::query()
                ->where('external_reference', app(ReginProtocoloCatalog::class)->porCodigo($codigo)['processo'])
                ->latest('id')
                ->first();

            $this->assertNotNull($processo, $codigo);

            $resolvido = app(SolicitacaoViabilityResolver::class)->resolve($processo);
            $motivos = [];

            foreach ($resolvido->por_cnae as $item) {
                $consulta = $item['consulta_array'] ?? [];
                $motivos[] = $item['cnae'].':'.$item['tendencia'].'/'.($consulta['enquadramento']['consolidado']['motivo'] ?? $consulta['enquadramento']['enquadramento']['motivo'] ?? '—');
            }

            $linhas[] = [
                'codigo' => $codigo,
                'status' => $processo->status->value,
                'tvl' => $processo->decision?->tvl_product_number,
                'consolidado' => $resolvido->consolidado,
                'motivos' => implode(' | ', $motivos),
            ];

            $this->assertNotSame(
                ResultadoViabilidade::Pendente->value,
                $resolvido->consolidado,
                $codigo.' ficou pendente: '.$linhas[array_key_last($linhas)]['motivos'],
            );
        }

        fwrite(STDERR, PHP_EOL.json_encode($linhas, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL);
    }

    /**
     * @return array<string, mixed>
     */
    private function entradaQueFechaPendencias(string $codigo): array
    {
        $service = app(ReginProtocoloSimulacaoService::class);
        $entrada = ['codigo' => $codigo];
        $pendencias = $service->pendencias($codigo, $entrada);

        if ($pendencias === null) {
            return $entrada;
        }

        foreach ($pendencias['perguntas'] as $pergunta) {
            if ($pergunta['valor'] !== null) {
                continue;
            }

            $digitos = (string) preg_replace('/\D/', '', (string) $pergunta['cnae']);
            $entrada['respostas'][$digitos][(string) $pergunta['numero']] = $this->respostaPadraoDaPergunta(
                (int) $pergunta['numero'],
                (string) $pergunta['cnae'],
                $codigo,
            );
        }

        if (in_array('tipo_imovel', $pendencias['campos'], true)) {
            $entrada['tipo_imovel'] = 'Edificação Comercial';
        }

        if (in_array('zona', $pendencias['campos'], true)) {
            $entrada['zona'] = (string) $pendencias['zona'];
        }

        if (in_array('via', $pendencias['campos'], true)) {
            $entrada['via'] = (string) $pendencias['via'];
        }

        return $entrada;
    }

    private function respostaPadraoDaPergunta(int $numero, string $cnae, string $codigo): bool
    {
        $noLocalDoCatalogo = [
            '33072' => ['6622-3/00' => false],
            '43747' => ['6202-3/00' => true],
            'abrigado-2108519' => ['8219-9/99' => false],
            'sede-virtual' => ['8211-3/00' => true],
            '53514' => true,
            '53528' => true,
            '54675' => true,
            '54709' => true,
            '54560' => [
                '1340-5/01' => true,
                '1412-6/02' => false,
                '1813-0/01' => true,
                '4781-4/00' => false,
            ],
            '54772' => [
                '3314-7/10' => false,
                '4751-2/01' => true,
                '6202-3/00' => true,
            ],
            '13336' => false,
            '8225' => true,
            '61131' => false,
            '8195' => false,
            '8161' => true,
            '2250' => [
                '2399-1/01' => true,
                '8211-3/00' => false,
            ],
            '855' => true,
            '375' => [
                '0161-0/01' => true,
                '0161-0/02' => true,
                '0161-0/03' => true,
                '4789-0/02' => true,
                '4930-2/02' => false,
                '8130-3/00' => true,
            ],
            '244' => true,
            '207' => [
                '8610-1/01' => true,
                '8610-1/02' => true,
                '8630-5/03' => false,
            ],
            'dupla-r1-r20' => true,
            'dupla-r8-r15' => true,
            'dupla-r21-r22' => true,
            'dupla-r46-r47' => true,
            'dupla-r24-r48' => true,
            'dupla-r6-r23' => true,
            'dupla-r38-r4' => true,
            'dupla-r49-r50' => true,
            'regra-1-escritorio' => false,
            'regra-1-nr' => true,
            'regra-1-id' => true,
            'regra-1-galpao' => true,
            'regra-24' => false,
            'regra-25-artesanal' => true,
            'regra-25-industrial' => true,
            'regra-26' => false,
            'regra-27' => true,
            'regra-51' => false,
            'regra-52-artesanal' => true,
            'regra-52-industrial' => true,
            'dupla-r1-r24' => [
                '4530-7/03' => true,
                '1064-3/00' => false,
            ],
            'dupla-r25-r52' => true,
            'dupla-r26-r51' => false,
        ];

        $mapa = $noLocalDoCatalogo[$codigo] ?? true;
        $noLocal = is_array($mapa) ? ($mapa[$cnae] ?? true) : $mapa;
        $artesanal = in_array($codigo, ['regra-25-artesanal', 'regra-52-artesanal'], true);

        return match ($numero) {
            2, 8, 11, 13, 19 => $noLocal,
            3 => $artesanal,
            5 => false,
            4 => $codigo === 'sede-virtual',
            default => $noLocal,
        };
    }

    private function regraAtividadeDaPlanilha(string $cnae): string
    {
        $path = database_path('data/regras-20-08-26/cnae-perguntas-regras.csv');
        $handle = fopen($path, 'r');
        $this->assertNotFalse($handle);

        $cabecalho = fgetcsv($handle);
        $this->assertIsArray($cabecalho);

        $regras = [];

        while (($linha = fgetcsv($handle)) !== false) {
            $row = array_combine($cabecalho, $linha);

            if (($row['cnae'] ?? '') !== $cnae) {
                continue;
            }

            if (($row['codigo_louos'] ?? '') === '07.12.13') {
                continue;
            }

            $regras[] = (string) $row['regras'];
        }

        fclose($handle);

        $unicas = array_values(array_unique($regras));
        $this->assertNotEmpty($unicas, "CNAE {$cnae} sem regra de atividade na planilha");

        return $unicas[0];
    }

    private function seedPlanilhaTratamento(): void
    {
        $versao = RuleVersion::factory()->create([
            'domain' => RuleDomain::RiscoTratamento,
            'version' => 'planilha-20-08-26',
        ]);

        (new TratamentoRegrasImportService)->import($versao, database_path('data/regras-20-08-26'));
    }
}
