<?php

namespace Tests\Feature\Analise;

use App\Models\AnalysisRecord;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Analise\PrecedentRepository;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Analise\FakePrecedentRepository;
use Tests\TestCase;

/**
 * Endpoint de precedentes da ficha (HU-142): o painel de apoio à decisão consome
 * o PrecedentService (10-06) — decisões anteriores no mesmo imóvel (sem CPF, LGPD
 * RN-004) e estatística do CNAE na zona (degradação honesta sem zona). Gated por
 * analisar-processos (403 auditado) e a consulta é auditada (RN-002).
 *
 * Roda em SQLite com o FakePrecedentRepository injetado POPULADO via
 * $this->app->instance — o SQL espacial real é provado em PrecedentRepositoryPostgisTest.
 */
class PrecedenteEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function analista(): User
    {
        return User::factory()->analista()->withAcceptedLgpdTerm()->create();
    }

    /**
     * Ficha (rev. 1) de uma solicitação protocolada com requerente de CPF
     * conhecido (alvo da asserção LGPD) e a zona identificada no engine_snapshot.
     */
    private function fichaComZona(string $cpf): AnalysisRecord
    {
        $requester = User::factory()->create(['cpf' => $cpf]);

        $request = ViabilityRequest::factory()->protocoled()->create([
            'requester_user_id' => $requester->id,
            'address_street' => 'Rua das Laranjeiras',
            'address_number' => '100',
        ]);

        return AnalysisRecord::factory()->create([
            'viability_request_id' => $request->id,
            'revision' => 1,
            'engine_snapshot' => [
                'por_cnae' => [
                    [
                        'cnae' => '4712100',
                        'cnae_formatado' => '4712-1/00',
                        'is_primary' => true,
                        'consulta' => [
                            'territorio' => ['zona' => ['status' => 'identificado', 'nome' => 'ZR-1']],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function test_precedentes_exige_analisar_processos_e_audita_o_403(): void
    {
        $semPermissao = User::factory()->withAcceptedLgpdTerm()->create();
        $semPermissao->givePermissionTo('acessar-gestao');

        $request = ViabilityRequest::factory()->protocoled()->create();
        AnalysisRecord::factory()->create(['viability_request_id' => $request->id, 'revision' => 1]);

        $this->actingAs($semPermissao, 'gestao')
            ->getJson("/gestao/processos/{$request->id}/precedentes")
            ->assertForbidden();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'seguranca',
            'event' => 'acesso-negado',
            'result' => 'bloqueado',
        ]);
    }

    public function test_precedentes_retorna_imovel_sem_cpf_e_cnae_zona_auditado(): void
    {
        $cpf = '52998224725';
        $ficha = $this->fichaComZona($cpf);

        $fake = new FakePrecedentRepository;
        $fake->setPropertyPrecedents([
            [
                'viability_request_id' => 501,
                'protocol_number' => 'VIA-2025-000050',
                'outcome' => 'deferida',
                'decided_at' => '2025-03-01T10:00:00Z',
                'service_type' => 'Viabilidade de localização',
                'analyst' => 'Maria Analista',
            ],
        ]);
        $fake->setCnaeZoneStats('4712100', 'ZR-1', ['deferidos' => 14, 'indeferidos' => 2, 'total' => 16]);
        $this->app->instance(PrecedentRepository::class, $fake);

        $response = $this->actingAs($this->analista(), 'gestao')
            ->getJson("/gestao/processos/{$ficha->viability_request_id}/precedentes")
            ->assertOk();

        // CA-01: a decisão anterior do imóvel aparece.
        $this->assertCount(1, $response->json('imovel'));
        $this->assertSame('VIA-2025-000050', $response->json('imovel.0.protocol_number'));

        // CA-02: estatística do CNAE na zona disponível.
        $this->assertTrue($response->json('cnae_zona.disponivel'));
        $this->assertSame('ZR-1', $response->json('cnae_zona.zona'));
        $this->assertSame(14, $response->json('cnae_zona.deferidos'));

        // LGPD (RN-004): o payload NUNCA carrega o CPF do requerente.
        $this->assertStringNotContainsString($cpf, $response->getContent());

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'analise',
            'event' => 'precedentes',
            'result' => 'sucesso',
        ]);
    }

    public function test_precedentes_degrada_honesto_sem_zona(): void
    {
        $request = ViabilityRequest::factory()->protocoled()->create();

        // Ficha em modo manual (FA-01): sem snapshot do motor, não há zona.
        AnalysisRecord::factory()->semMotor()->create([
            'viability_request_id' => $request->id,
            'revision' => 1,
        ]);

        $fake = new FakePrecedentRepository;
        $this->app->instance(PrecedentRepository::class, $fake);

        $response = $this->actingAs($this->analista(), 'gestao')
            ->getJson("/gestao/processos/{$request->id}/precedentes")
            ->assertOk();

        // CA-03: sem precedentes, lista vazia explícita (nunca seção ocultada).
        $this->assertSame([], $response->json('imovel'));
        // Sem zona, estatística "indisponível" — nunca inventada.
        $this->assertFalse($response->json('cnae_zona.disponivel'));
    }
}
