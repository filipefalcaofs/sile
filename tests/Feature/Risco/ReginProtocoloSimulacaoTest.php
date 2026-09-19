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

        foreach ($codigos as $codigo) {
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
        ];

        $mapa = $noLocalDoCatalogo[$codigo] ?? true;
        $noLocal = is_array($mapa) ? ($mapa[$cnae] ?? true) : $mapa;

        return match ($numero) {
            2, 8, 11, 13 => $noLocal,
            3, 5 => false,
            4 => $codigo === 'sede-virtual',
            default => $noLocal,
        };
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
