<?php

namespace Tests\Feature\Viabilidade;

use App\Models\User;
use App\Models\ViabilityQuery;
use App\Services\Geo\AddressNotFoundException;
use App\Services\Geo\Geocoder;
use App\Services\Geo\GeocodeResult;
use Database\Seeders\LouosQuadro7Seeder;
use Database\Seeders\RiscoMunicipalSeeder;
use Database\Seeders\RiscoSanitarioSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Histórico AUTENTICADO da consulta prévia de viabilidade (HU-060): a consulta
 * grava um SNAPSHOT imutável em viability_queries SOMENTE quando o usuário está
 * autenticado (guard web). A consulta anônima segue auditada (RN-002, no
 * serviço), mas NÃO cria histórico pessoal; e nenhum caminho de erro vira
 * histórico (sem fachada).
 *
 * Os motores rodam com SEEDS REAIS (Quadro 7 da Lei 9.148/2016, risco do Decreto
 * 32.636/2020 e da VISA); o ponto/território, quando necessários, são injetados
 * por FAKES (Geocoder), reproduzindo os cenários sem PostGIS.
 */
class HistoricoConsultaTest extends TestCase
{
    use RefreshDatabase;

    private const CNAE_MINIMERCADO = '4712-1/00';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolesAndPermissionsSeeder::class,
            LouosQuadro7Seeder::class,
            RiscoMunicipalSeeder::class,
            RiscoSanitarioSeeder::class,
        ]);
    }

    /**
     * Cidadão do portal com termo LGPD aceito — precedente "Minhas empresas".
     */
    private function portalUser(): User
    {
        return User::factory()->cidadao()->withAcceptedLgpdTerm()->create();
    }

    public function test_consulta_autenticada_grava_snapshot_no_historico(): void
    {
        // HU-060/CA-01: autenticado (guard web), a consulta grava um snapshot
        // imutável da própria consulta — entrada, resultado e versões da época.
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/portal/viabilidade/cnae', [
                'cnae' => self::CNAE_MINIMERCADO,
                'area' => 120,
            ])
            ->assertOk();

        $this->assertSame(1, ViabilityQuery::count());

        $query = ViabilityQuery::query()->latest('id')->first();

        $this->assertSame($user->id, $query->user_id);
        $this->assertSame('cnae', $query->entry_type);
        // Sem território (via CNAE), o veredito é PROPAGADO como pendente.
        $this->assertSame('pendente', $query->result['veredito_locacional']['resultado']);
        $this->assertSame('pendente', $query->resultado);
        // Versões das regras da época gravadas (reprodução — RN-002/RN-004).
        $this->assertNotEmpty($query->rules_versions);
        $this->assertArrayHasKey('louos', $query->rules_versions);
        $this->assertNotEmpty($query->rules_versions['louos']);
    }

    public function test_consulta_anonima_nao_grava_historico_mas_e_auditada(): void
    {
        // Anônima é AUDITADA (RN-002), mas NÃO gera histórico pessoal: a regra de
        // gravar só-quando-autenticado é do controller.
        $this->postJson('/portal/viabilidade/cnae', [
            'cnae' => self::CNAE_MINIMERCADO,
            'area' => 120,
        ])->assertOk();

        $this->assertSame(0, ViabilityQuery::count());

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'viabilidade',
            'event' => 'consulta',
            'result' => 'sucesso',
        ]);
    }

    public function test_consulta_com_erro_nao_grava_historico(): void
    {
        // Anti-fachada: mesmo autenticado, um erro honesto (endereço não
        // localizado → 404) NUNCA vira histórico. Só o caminho de sucesso grava.
        $this->app->instance(Geocoder::class, new class implements Geocoder
        {
            public function geocode(string $address): GeocodeResult
            {
                throw new AddressNotFoundException($address);
            }
        });

        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/portal/viabilidade/endereco', [
                'endereco' => 'Endereço inexistente xyz',
                'cnae' => self::CNAE_MINIMERCADO,
                'area' => 120,
            ])
            ->assertStatus(404);

        $this->assertSame(0, ViabilityQuery::count());
    }

    public function test_historico_lista_so_as_consultas_do_dono(): void
    {
        // HU-060/CA-04 (escopo): o usuário vê SOMENTE as próprias consultas
        // (forUser — precedente "Minhas empresas"); as de terceiro nunca aparecem.
        $userA = $this->portalUser();
        $userB = $this->portalUser();

        ViabilityQuery::factory()->for($userA)->create();
        ViabilityQuery::factory()->for($userA)->create();
        ViabilityQuery::factory()->for($userB)->create();

        $this->actingAs($userA)
            ->get('/portal/viabilidade/historico')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('consultas.data', 2)
                ->where('consultas.total', 2)
                ->has('consultas.data.0', fn (Assert $item) => $item
                    ->has('id')
                    ->has('entry_type')
                    ->has('resultado')
                    ->has('resultado_label')
                    ->has('input')
                    ->has('created_at')
                    ->has('result')));
    }

    public function test_historico_exige_autenticacao(): void
    {
        // A rota de histórico é AUTENTICADA (auth:web + verified + lgpd): o
        // visitante é redirecionado ao login do portal.
        $this->get('/portal/viabilidade/historico')->assertRedirect('/portal/login');
    }

    public function test_consulta_do_historico_e_auditada(): void
    {
        // RN-002: a consulta do histórico (dado do próprio usuário) é auditada,
        // com causer = usuário — precedente do histórico de acessos.
        $user = $this->portalUser();
        ViabilityQuery::factory()->for($user)->create();

        $this->actingAs($user)
            ->get('/portal/viabilidade/historico')
            ->assertOk();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'viabilidade',
            'event' => 'consulta-historico',
            'causer_id' => $user->id,
            'result' => 'sucesso',
        ]);
    }

    public function test_historico_ordena_da_mais_recente(): void
    {
        // Ordenação da mais recente para a mais antiga (latest created_at, id).
        $user = $this->portalUser();

        $antiga = ViabilityQuery::factory()->for($user)->create(['created_at' => now()->subDay()]);
        $recente = ViabilityQuery::factory()->for($user)->create(['created_at' => now()]);

        $this->actingAs($user)
            ->get('/portal/viabilidade/historico')
            ->assertInertia(fn (Assert $page) => $page
                ->where('consultas.data.0.id', $recente->id)
                ->where('consultas.data.1.id', $antiga->id));
    }
}
