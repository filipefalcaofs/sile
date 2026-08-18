<?php

namespace Tests\Feature\Solicitacao;

use App\Enums\CompanyLinkRole;
use App\Enums\GeoLayerType;
use App\Enums\ViabilityRequestStatus;
use App\Models\Cnae;
use App\Models\Company;
use App\Models\DocumentRequirement;
use App\Models\GeoLayer;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Models\ViabilityServiceType;
use App\Services\Geo\SpatialRepository;
use App\Services\Solicitacao\DocumentRequirementResolver;
use Database\Seeders\DocumentRequirementSeeder;
use Database\Seeders\LouosQuadro7Seeder;
use Database\Seeders\ParameterSeeder;
use Database\Seeders\RiscoMunicipalSeeder;
use Database\Seeders\RiscoSanitarioSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\ViabilityServiceTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Geo\FakeSpatialRepository;
use Tests\TestCase;

/**
 * Smoke end-to-end da Fase 8 pelo PORTAL (HU-061 a HU-070 + HU-141): exercita a
 * jornada inteira do cidadão pelos ENDPOINTS e SERVIÇOS REAIS — criar rascunho →
 * instruir imóvel/área → instruir atividades → anexar documento → simular →
 * protocolar → consultar → cancelar — asserindo o estado a cada passo. É a
 * proteção de regressão de integração do fluxo (os passos individuais têm testes
 * próprios; aqui provamos que encadeiam de verdade).
 *
 * Sem fachada (anti-fachada, regra nº1): documento obrigatório faltante BLOQUEIA
 * o protocolo (aviso, status intacto) e, sem zona oficial, a simulação fica
 * PENDENTE (propagada do motor LOUOS — nunca permitido/não permitido). O motor
 * roda com SEEDS REAIS (Quadro 7 da Lei 9.148/2016 + risco do Decreto
 * 32.636/2020 e da VISA); o território é injetado por FAKE (bairro identificado,
 * zona pendente) para reproduzir o cenário real sem PostGIS.
 */
class SolicitacaoSmokeTest extends TestCase
{
    use RefreshDatabase;

    private const CNAE_MINIMERCADO = '4712100';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolesAndPermissionsSeeder::class,
            ParameterSeeder::class,
            LouosQuadro7Seeder::class,
            RiscoMunicipalSeeder::class,
            RiscoSanitarioSeeder::class,
            ViabilityServiceTypeSeeder::class,
            DocumentRequirementSeeder::class,
        ]);

        Storage::fake('local');
    }

    public function test_jornada_completa_do_portal_protocola_consulta_e_cancela(): void
    {
        [$user, $company, $serviceType] = $this->ator();
        $cnae = Cnae::factory()->create(['code' => self::CNAE_MINIMERCADO]);

        // 1) Criar o rascunho (origem portal direto).
        $this->actingAs($user)
            ->post(route('portal.solicitacoes.store'), [
                'service_type_id' => $serviceType->id,
                'company_id' => $company->id,
            ])
            ->assertRedirect(route('portal.solicitacoes.index'))
            ->assertSessionHasNoErrors();

        $solicitacao = ViabilityRequest::query()
            ->where('company_id', $company->id)
            ->where('requester_user_id', $user->id)
            ->latest('id')
            ->firstOrFail();
        $this->assertSame(ViabilityRequestStatus::Rascunho, $solicitacao->status);

        // 2) Instruir o imóvel (polígono + área + ponto de referência).
        $this->actingAs($user)
            ->put(route('portal.solicitacoes.imovel', $solicitacao), [
                'property_polygon_geojson' => $this->poligono(),
                'used_area_m2' => 120.0,
                'address_reference' => 'Em frente à praça da Sé.',
                'is_public_area' => false,
            ])
            ->assertSessionHasNoErrors();

        $solicitacao->refresh();
        $this->assertNotNull($solicitacao->property_polygon_geojson);
        $this->assertSame('120.00', (string) $solicitacao->used_area_m2);

        // 3) Instruir a atividade principal (CNAE real ATIVO).
        $this->actingAs($user)
            ->put(route('portal.solicitacoes.atividades', $solicitacao), [
                'principal_cnae_id' => $cnae->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue($solicitacao->primaryCnae()->whereKey($cnae->id)->exists());

        // 4) Anexar a foto da fachada (cobre o requisito-base — sem ela o
        //    protocolo BLOQUEIA, provado no teste dedicado abaixo).
        $fachada = DocumentRequirement::query()->where('code', DocumentRequirementResolver::CODE_FACHADA)->firstOrFail();
        $this->actingAs($user)
            ->post(route('portal.solicitacoes.documentos.store', $solicitacao), [
                'file' => UploadedFile::fake()->create('fachada.jpg', 120, 'image/jpeg'),
                'requirement_id' => $fachada->id,
            ])
            ->assertSessionHasNoErrors();

        $documento = $solicitacao->documents()->where('requirement_id', $fachada->id)->firstOrFail();
        Storage::disk('local')->assertExists($documento->path);

        // 5) Simular (orientativa): com bairro identificado e zona pendente, a
        //    tendência consolidada é PENDENTE — nunca permitido/não permitido.
        $this->fakeTerritorioBairroSemZona();
        $this->actingAs($user)
            ->post(route('portal.solicitacoes.simular', $solicitacao))
            ->assertSessionHasNoErrors();

        $solicitacao->refresh();
        $this->assertSame('pendente', $solicitacao->simulation_resultado);
        foreach ($solicitacao->simulation_snapshot['por_cnae'] as $item) {
            $this->assertSame('pendente', $item['tendencia']);
            $this->assertNotSame('permitido', $item['tendencia']);
            $this->assertNotSame('nao_permitido', $item['tendencia']);
        }

        // 6) Protocolar (a simulação não bloqueia o direito de petição).
        $this->actingAs($user)
            ->post(route('portal.solicitacoes.protocolar', $solicitacao))
            ->assertRedirect(route('portal.solicitacoes.index'))
            ->assertSessionHas('status')
            ->assertSessionHasNoErrors();

        $solicitacao->refresh();
        $this->assertSame(ViabilityRequestStatus::Protocolada, $solicitacao->status);
        $this->assertMatchesRegularExpression('/^VIA-\d{4}-\d{6}$/', (string) $solicitacao->protocol_number);

        // 7) Consultar o protocolo (autenticado): timeline real + status amigável.
        $this->actingAs($user)
            ->get(route('portal.solicitacoes.show', $solicitacao))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('portal/solicitacoes/protocolo')
                ->where('solicitacao.protocol_number', $solicitacao->protocol_number)
                ->where('solicitacao.status.public_label', ViabilityRequestStatus::Protocolada->publicLabel())
                ->has('timeline.etapas', 1));

        // 8) Cancelar (protocolada é cancelável antes da decisão — motivo obrigatório).
        $this->actingAs($user)
            ->from(route('portal.solicitacoes.index'))
            ->delete(route('portal.solicitacoes.cancelar', $solicitacao), [
                'reason' => 'Desisti do pedido (smoke).',
            ])
            ->assertRedirect(route('portal.solicitacoes.index'))
            ->assertSessionHasNoErrors();

        $solicitacao->refresh();
        $this->assertSame(ViabilityRequestStatus::Cancelada, $solicitacao->status);
        $this->assertNotNull($solicitacao->cancelled_at);
    }

    public function test_documento_obrigatorio_bloqueia_o_protocolo(): void
    {
        // Anti-fachada: rascunho completo (imóvel, área, CNAE) SEM a foto da
        // fachada (requisito-base do seed) → protocolar é bloqueado com aviso,
        // status intacto e nenhum número consumido.
        [$user, $company, $serviceType] = $this->ator();
        $cnae = Cnae::factory()->create(['code' => self::CNAE_MINIMERCADO]);

        $solicitacao = ViabilityRequest::factory()->draft()->create([
            'company_id' => $company->id,
            'service_type_id' => $serviceType->id,
            'requester_user_id' => $user->id,
            'created_by_user_id' => $user->id,
            'used_area_m2' => 120.0,
            'is_public_area' => false,
        ]);
        $solicitacao->cnaes()->attach($cnae->id, ['is_primary' => true]);

        $this->actingAs($user)
            ->from(route('portal.solicitacoes.index'))
            ->post(route('portal.solicitacoes.protocolar', $solicitacao))
            ->assertRedirect(route('portal.solicitacoes.index'))
            ->assertSessionHas('error');

        $this->assertStringContainsString('Foto da fachada', (string) session('error'));

        $solicitacao->refresh();
        $this->assertSame(ViabilityRequestStatus::Rascunho, $solicitacao->status);
        $this->assertNull($solicitacao->protocol_number);
    }

    public function test_sem_zona_a_simulacao_fica_pendente(): void
    {
        // Anti-fachada: o motor de risco real classifica (encaminha expresso para
        // o minimercado), mas sem zona oficial a tendência locacional fica
        // PENDENTE — nunca um permitido/não permitido inventado.
        [$user] = $this->ator();
        $solicitacao = ViabilityRequest::factory()->draft()->create([
            'requester_user_id' => $user->id,
            'created_by_user_id' => $user->id,
            'used_area_m2' => 120.0,
        ]);
        $cnae = Cnae::factory()->create(['code' => self::CNAE_MINIMERCADO]);
        $solicitacao->cnaes()->attach($cnae->id, ['is_primary' => true]);

        $this->fakeTerritorioBairroSemZona();

        $this->actingAs($user)
            ->post(route('portal.solicitacoes.simular', $solicitacao))
            ->assertSessionHasNoErrors();

        $solicitacao->refresh();
        $this->assertSame('pendente', $solicitacao->simulation_resultado);
        $this->assertSame(
            'classificado',
            $solicitacao->simulation_snapshot['por_cnae'][0]['consulta']['risco']['municipal']['status'],
        );
    }

    /**
     * Cidadão habilitado (papel + termo LGPD) com empresa vinculada e um tipo de
     * serviço ativo do catálogo seedado.
     *
     * @return array{0: User, 1: Company, 2: ViabilityServiceType}
     */
    private function ator(): array
    {
        $user = User::factory()->cidadao()->withAcceptedLgpdTerm()->create();

        $company = Company::factory()->create();
        $company->links()->create([
            'user_id' => $user->id,
            'role' => CompanyLinkRole::Responsavel,
            'started_at' => now(),
        ]);

        $serviceType = ViabilityServiceType::query()->where('active', true)->orderBy('id')->firstOrFail();

        return [$user, $company, $serviceType];
    }

    /**
     * Polígono de 4 pontos (quadrilátero fechado) em Salvador — fonte portável.
     *
     * @return array{type: string, coordinates: array<int, array<int, array<int, float>>>}
     */
    private function poligono(): array
    {
        return [
            'type' => 'Polygon',
            'coordinates' => [[
                [-38.5108, -12.9711],
                [-38.5108, -12.9709],
                [-38.5106, -12.9709],
                [-38.5106, -12.9711],
                [-38.5108, -12.9711],
            ]],
        ];
    }

    /**
     * Território com BAIRRO identificado e ZONA indisponível (base de zoneamento
     * pendente SEDUR): bairro vigente + repositório espacial fake — reproduz o
     * cenário real do projeto sem PostGIS, levando o veredito a pendente.
     */
    private function fakeTerritorioBairroSemZona(): void
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
    }
}
