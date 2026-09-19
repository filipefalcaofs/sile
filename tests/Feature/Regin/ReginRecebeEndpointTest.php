<?php

namespace Tests\Feature\Regin;

use App\Enums\ViabilityRequestOrigin;
use App\Models\Activity;
use App\Models\ReginRecebimento;
use App\Models\User;
use App\Models\ViabilityRequest;
use Database\Seeders\RiscoMunicipalSeeder;
use Database\Seeders\RiscoSanitarioSeeder;
use Database\Seeders\RiskTriggerSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ReginRecebeEndpointTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_get_devolve_codigo_3_em_texto_puro(): void
    {
        $response = $this->get('/api_integracao/recebe');

        $response->assertOk();
        $this->assertSame('3', $response->getContent());
    }

    public function test_post_persiste_e_devolve_3(): void
    {
        $response = $this->postJson('/api_integracao/recebe', $this->envioDto('43747'));

        $response->assertOk();
        $this->assertSame('3', $response->getContent());
        $this->assertDatabaseHas('regin_recebimentos', [
            'protocolo' => '43747',
            'cod_funcao' => 103,
            'cnpj_empresa' => '13927801000149',
        ]);
    }

    public function test_post_duplicado_devolve_5_sem_criar_segunda_linha(): void
    {
        $payload = $this->envioDto('43747');

        $this->postJson('/api_integracao/recebe', $payload);
        $segunda = $this->postJson('/api_integracao/recebe', $payload);

        $segunda->assertOk();
        $this->assertSame('5', $segunda->getContent());
        $this->assertSame(1, ReginRecebimento::query()->where('protocolo', '43747')->count());
    }

    public function test_post_sem_protocolo_nao_devolve_3_nem_5(): void
    {
        $payload = $this->envioDto('43747');
        unset($payload['protocolo']);

        $response = $this->postJson('/api_integracao/recebe', $payload);

        $this->assertNotSame('3', $response->getContent());
        $this->assertNotSame('5', $response->getContent());
        $this->assertSame(0, ReginRecebimento::query()->count());
    }

    public function test_post_audita_recebimento_sem_ruc_no_log(): void
    {
        $this->postJson('/api_integracao/recebe', $this->envioDto('43747', [
            'json' => [
                'RUC' => ['nome' => 'Não deve ir ao log'],
                'INFORMACOES_COMPLEMENTARES' => ['RES_AREA' => 834],
            ],
        ]));

        $activity = Activity::query()->where('description', 'Processo recebido via REGIN')->first();

        $this->assertNotNull($activity);
        $this->assertTrue((bool) $activity->personal_data);
        $this->assertSame('43747', $activity->properties['protocolo'] ?? null);
        $this->assertSame('3', $activity->properties['codigo'] ?? null);
        $this->assertStringNotContainsString('Não deve ir ao log', json_encode($activity->properties, JSON_THROW_ON_ERROR));
    }

    public function test_post_de_protocolo_conhecido_cria_processo_pelo_motor(): void
    {
        $this->seed([
            RolesAndPermissionsSeeder::class,
            RiscoMunicipalSeeder::class,
            RiscoSanitarioSeeder::class,
            RiskTriggerSeeder::class,
        ]);

        $this->actingAs(
            User::factory()->administrador()->withAcceptedLgpdTerm()->create(),
            'gestao',
        );

        $response = $this->postJson('/api_integracao/recebe', $this->envioDto('43747'));

        $response->assertOk();
        $this->assertSame('3', $response->getContent());

        $recebimento = ReginRecebimento::query()->where('protocolo', '43747')->first();
        $this->assertNotNull($recebimento?->viability_request_id);

        $processo = ViabilityRequest::query()->find($recebimento->viability_request_id);
        $this->assertNotNull($processo);
        $this->assertSame(ViabilityRequestOrigin::Regin, $processo->origin);
        $this->assertSame('43747', $processo->external_reference);
        $this->assertSame('regin_recebe', $processo->contingency_reason);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function envioDto(string $protocolo, array $overrides = []): array
    {
        return array_replace_recursive([
            'cnpjDestino' => '13927801000149',
            'cnpjEmpresa' => '13927801000149',
            'cnpjOrigem' => '13927801000149',
            'codFuncao' => 103,
            'dataGeracao' => '2026-09-16T16:00:00.000Z',
            'json' => [
                'rowset' => [
                    'RUC_GENERAL' => [
                        'RGE_CGC_CPF' => '13927801000149',
                        'RGE_NOMB' => 'Empresa REGIN Teste',
                    ],
                    'RUC_ESTAB' => [
                        'RES_AREA' => '834',
                        'RES_DIRECCION' => 'Rua das Flores',
                        'RES_NUME' => '100',
                        'RES_URBANIZACION' => 'Centro',
                        'RES_ZONA_POSTAL' => '40000000',
                    ],
                    'GROUPRUC_ACTV_ECON' => [
                        'RUC_ACTV_ECON' => [
                            ['RAE_TAE_COD_ACTVD' => '4712100', 'RAE_CALIF_ACTV' => '1'],
                        ],
                    ],
                ],
            ],
            'nire' => '00000000000',
            'protocolo' => $protocolo,
            'servico' => 'WsProSol098',
        ], $overrides);
    }
}
