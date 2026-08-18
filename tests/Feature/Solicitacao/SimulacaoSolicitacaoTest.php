<?php

namespace Tests\Feature\Solicitacao;

use App\Enums\GeoLayerType;
use App\Enums\ViabilityRequestStatus;
use App\Models\Cnae;
use App\Models\GeoLayer;
use App\Models\Parameter;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Geo\SpatialRepository;
use App\Services\Viabilidade\ConsultaViabilidadeService;
use Database\Seeders\LouosQuadro7Seeder;
use Database\Seeders\RiscoMunicipalSeeder;
use Database\Seeders\RiscoSanitarioSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Geo\FakeSpatialRepository;
use Tests\TestCase;

/**
 * Simulação pré-protocolo (HU-141): o SimulacaoSolicitacaoService itera os CNAEs
 * da solicitação e chama o ConsultaViabilidadeService (Fase 7) PELO PONTO da
 * própria solicitação (centroide do polígono — sem geocodificar de novo),
 * PROPAGANDO o veredito do motor LOUOS (sem decisão paralela, RN-001). Persiste
 * o snapshot + versões + resultado e marca simulated_at (RN-003); é orientativa
 * e NÃO bloqueia o protocolo (RN-002); o toggle features.simulacao_solicitacao
 * degrada sem falha (RN-005). Sem zona oficial, o veredito por CNAE fica
 * "pendente" (propagado) — nunca permitido/não permitido inventado.
 *
 * Os motores rodam com SEEDS REAIS (Quadro 7 da Lei 9.148/2016, risco do Decreto
 * 32.636/2020 e da VISA); o território é injetado por FAKE (FakeSpatialRepository)
 * para reproduzir o cenário "bairro identificado, zona pendente" sem PostGIS.
 */
class SimulacaoSolicitacaoTest extends TestCase
{
    use RefreshDatabase;

    private const CNAE_MINIMERCADO = '4712-1/00';

    private const CNAE_MINIMERCADO_DIGITOS = '4712100';

    private const CNAE_SOFTWARE_DIGITOS = '6201501';

    protected function setUp(): void
    {
        parent::setUp();

        // Carga REAL dos motores da Fase 7 (mesma versão de regras do fluxo
        // oficial — RN-001): Quadro 7 (enquadramento por área), risco
        // municipal/sanitário (dimensões separadas) e papéis/permissões
        // (cidadão + termo LGPD do portal).
        $this->seed([
            RolesAndPermissionsSeeder::class,
            LouosQuadro7Seeder::class,
            RiscoMunicipalSeeder::class,
            RiscoSanitarioSeeder::class,
        ]);
    }

    /**
     * Cidadão habilitado a navegar no portal (papel + termo LGPD aceito).
     */
    private function portalUser(): User
    {
        return User::factory()->cidadao()->withAcceptedLgpdTerm()->create();
    }

    /**
     * Rascunho com polígono (centroide em Salvador), área e os CNAEs informados
     * (o primeiro como principal), cujo requerente é o usuário. Os códigos batem
     * com os seeds reais dos motores (minimercado/software).
     *
     * @param  list<string>  $cnaeCodes
     */
    private function draftFor(User $user, array $cnaeCodes): ViabilityRequest
    {
        $solicitacao = ViabilityRequest::factory()->draft()->create([
            'requester_user_id' => $user->id,
            'created_by_user_id' => $user->id,
            'used_area_m2' => 120.0,
        ]);

        foreach (array_values($cnaeCodes) as $index => $code) {
            $cnae = Cnae::factory()->create(['code' => $code]);
            $solicitacao->cnaes()->attach($cnae->id, ['is_primary' => $index === 0]);
        }

        return $solicitacao;
    }

    /**
     * Território com BAIRRO identificado e ZONA indisponível (base de zoneamento
     * pendente SEDUR): seeda a camada de bairro vigente e deixa a zona sem
     * camada → indisponível → veredito pendente (propagado do motor LOUOS).
     */
    private function fakeTerritorioBairroSemZona(): FakeSpatialRepository
    {
        GeoLayer::factory()->vigente()->create([
            'type' => GeoLayerType::Bairro,
            'version' => 'bairro-2024',
        ]);

        $fake = new FakeSpatialRepository;
        $fake->setContaining(GeoLayerType::Bairro, [
            'id' => 1,
            'properties' => ['NOME_BAIRRO' => 'Comércio'],
        ]);

        $this->app->instance(SpatialRepository::class, $fake);

        return $fake;
    }

    public function test_motor_expoe_consulta_por_ponto_conhecido_propagando_veredito(): void
    {
        // Refactor mínimo da Fase 7: a solicitação JÁ tem o ponto (centroide do
        // polígono), então o motor expõe uma entrada por ponto+CNAE que NÃO
        // geocodifica de novo e propaga o veredito do motor LOUOS.
        $this->fakeTerritorioBairroSemZona();

        $result = app(ConsultaViabilidadeService::class)
            ->consultarPorPontoConhecido(-12.9710, -38.5107, self::CNAE_MINIMERCADO, 120.0);

        // Identificou o território a partir do ponto, sem geocodificar.
        $this->assertNull($result->geocode);
        $this->assertNotNull($result->territory);
        $this->assertSame('identificado', $result->territory->bairro['status']);

        // Risco real (Decreto 32.636/2020) + Quadro 7 por área; veredito
        // PROPAGADO (pendente sem zona — nunca recomputado aqui).
        $this->assertSame('classificado', $result->risco->municipal['status']);
        $this->assertSame('identificado', $result->enquadramento->quadro7['status']);
        $this->assertSame('pendente', $result->vereditoLocacional()['resultado']);
        $this->assertSame('ponto', $result->entrada['tipo']);
    }

    public function test_simula_por_cnae_propagando_veredito(): void
    {
        // CA-01/RN-001: solicitação com 2 CNAEs + área + polígono → snapshot por
        // CNAE com a tendência PROPAGADA do motor real; versões das regras presentes.
        $this->fakeTerritorioBairroSemZona();
        $user = $this->portalUser();
        $solicitacao = $this->draftFor($user, [self::CNAE_MINIMERCADO_DIGITOS, self::CNAE_SOFTWARE_DIGITOS]);

        $this->actingAs($user)
            ->from(route('portal.solicitacoes.index'))
            ->post(route('portal.solicitacoes.simular', $solicitacao))
            ->assertRedirect(route('portal.solicitacoes.index'))
            ->assertSessionHas('simulacao');

        $solicitacao->refresh();
        $snapshot = $solicitacao->simulation_snapshot;

        // Snapshot por CNAE: um por atividade, com a tendência do motor.
        $this->assertIsArray($snapshot);
        $this->assertCount(2, $snapshot['por_cnae']);
        $codigos = array_column($snapshot['por_cnae'], 'cnae');
        $this->assertContains(self::CNAE_MINIMERCADO_DIGITOS, $codigos);
        $this->assertContains(self::CNAE_SOFTWARE_DIGITOS, $codigos);

        // Versões de TODAS as regras (RN-002): território + louos + risco.
        $versoes = $solicitacao->simulation_rules_versions;
        $this->assertArrayHasKey('territorio', $versoes);
        $this->assertArrayHasKey('louos', $versoes);
        $this->assertArrayHasKey('risco', $versoes);

        // Cada CNAE traz o resultado completo do motor (insumo do 08-13).
        foreach ($snapshot['por_cnae'] as $item) {
            $this->assertArrayHasKey('consulta', $item);
            $this->assertSame('classificado', $item['consulta']['risco']['municipal']['status']);
        }
    }

    public function test_sem_zona_veredito_pendente(): void
    {
        // Anti-fachada: sem zona oficial, a tendência por CNAE é PENDENTE
        // (propagada do motor) — nunca permitido/não permitido inventado.
        $this->fakeTerritorioBairroSemZona();
        $user = $this->portalUser();
        $solicitacao = $this->draftFor($user, [self::CNAE_MINIMERCADO_DIGITOS]);

        $this->actingAs($user)
            ->post(route('portal.solicitacoes.simular', $solicitacao));

        $solicitacao->refresh();

        $this->assertSame('pendente', $solicitacao->simulation_resultado);

        foreach ($solicitacao->simulation_snapshot['por_cnae'] as $item) {
            $this->assertSame('pendente', $item['tendencia']);
            $this->assertNotSame('permitido', $item['tendencia']);
            $this->assertNotSame('nao_permitido', $item['tendencia']);
        }
    }

    public function test_persiste_snapshot_e_simulated_at(): void
    {
        // RN-003: após simular, snapshot + versões + resultado + simulated_at
        // ficam REGISTRADOS no processo (o protocolo lê o snapshot, não reprocessa).
        $this->fakeTerritorioBairroSemZona();
        $user = $this->portalUser();
        $solicitacao = $this->draftFor($user, [self::CNAE_MINIMERCADO_DIGITOS]);

        $this->assertNull($solicitacao->simulated_at);

        $this->actingAs($user)
            ->post(route('portal.solicitacoes.simular', $solicitacao));

        $solicitacao->refresh();

        $this->assertNotNull($solicitacao->simulation_snapshot);
        $this->assertNotNull($solicitacao->simulation_rules_versions);
        $this->assertNotNull($solicitacao->simulation_resultado);
        $this->assertNotNull($solicitacao->simulated_at);
    }

    public function test_simulacao_nao_bloqueia_o_fluxo(): void
    {
        // RN-002 (direito de petição): a simulação devolve o resultado mas NÃO
        // muda o status nem registra ciência — não impede edição/protocolo.
        $this->fakeTerritorioBairroSemZona();
        $user = $this->portalUser();
        $solicitacao = $this->draftFor($user, [self::CNAE_MINIMERCADO_DIGITOS]);

        $this->actingAs($user)
            ->post(route('portal.solicitacoes.simular', $solicitacao))
            ->assertSessionHasNoErrors();

        $solicitacao->refresh();

        $this->assertSame(ViabilityRequestStatus::Rascunho, $solicitacao->status);
        $this->assertFalse((bool) $solicitacao->applicant_proceeded_despite);
    }

    public function test_toggle_desligado_degrada_sem_falha(): void
    {
        // RN-005: com features.simulacao_solicitacao desligado, degrada de forma
        // comunicada — sem simular, sem persistir, sem erro.
        Parameter::factory()->create([
            'key' => 'features.simulacao_solicitacao',
            'group' => 'features',
            'type' => 'boolean',
            'value' => '0',
        ]);

        $user = $this->portalUser();
        $solicitacao = $this->draftFor($user, [self::CNAE_MINIMERCADO_DIGITOS]);

        $this->actingAs($user)
            ->from(route('portal.solicitacoes.index'))
            ->post(route('portal.solicitacoes.simular', $solicitacao))
            ->assertRedirect(route('portal.solicitacoes.index'))
            ->assertSessionHas('status')
            ->assertSessionHasNoErrors();

        $solicitacao->refresh();

        $this->assertNull($solicitacao->simulation_snapshot);
        $this->assertNull($solicitacao->simulated_at);
    }

    public function test_auditoria_da_simulacao(): void
    {
        // RN-002: a simulação é auditada (solicitacoes/simulacao) com as versões
        // das regras e o resultado consolidado.
        $this->fakeTerritorioBairroSemZona();
        $user = $this->portalUser();
        $solicitacao = $this->draftFor($user, [self::CNAE_MINIMERCADO_DIGITOS]);

        $this->actingAs($user)
            ->post(route('portal.solicitacoes.simular', $solicitacao));

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'solicitacoes',
            'event' => 'simulacao',
            'subject_type' => $solicitacao->getMorphClass(),
            'subject_id' => $solicitacao->id,
            'result' => 'sucesso',
        ]);
    }

    public function test_sem_poligono_simula_por_cnae_sem_territorio(): void
    {
        // Degradação honesta: sem polígono não há ponto — simula por CNAE (risco
        // real + Quadro 7), sem território, veredito pendente. Não inventa local.
        $user = $this->portalUser();
        $solicitacao = ViabilityRequest::factory()->draft()->create([
            'requester_user_id' => $user->id,
            'created_by_user_id' => $user->id,
            'used_area_m2' => 120.0,
            'property_polygon_geojson' => null,
        ]);
        $cnae = Cnae::factory()->create(['code' => self::CNAE_MINIMERCADO_DIGITOS]);
        $solicitacao->cnaes()->attach($cnae->id, ['is_primary' => true]);

        $this->actingAs($user)
            ->post(route('portal.solicitacoes.simular', $solicitacao))
            ->assertSessionHasNoErrors();

        $solicitacao->refresh();
        $item = $solicitacao->simulation_snapshot['por_cnae'][0];

        // Sem ponto: território nulo no snapshot (não inventa local), risco real.
        $this->assertNull($item['consulta']['territorio']);
        $this->assertSame('pendente', $item['tendencia']);
        $this->assertSame('classificado', $item['consulta']['risco']['municipal']['status']);
    }

    public function test_terceiro_nao_simula(): void
    {
        // CA-04: somente o dono (em rascunho) simula — terceiro é bloqueado.
        $owner = $this->portalUser();
        $stranger = $this->portalUser();
        $solicitacao = $this->draftFor($owner, [self::CNAE_MINIMERCADO_DIGITOS]);

        $this->actingAs($stranger)
            ->post(route('portal.solicitacoes.simular', $solicitacao))
            ->assertForbidden();

        $solicitacao->refresh();
        $this->assertNull($solicitacao->simulated_at);
    }
}
