<?php

namespace Tests\Feature\Regin;

use App\Enums\ViabilityRequestOrigin;
use App\Jobs\ProtocolarRecebimentoReginJob;
use App\Models\Activity;
use App\Models\ReginRecebimento;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Regin\ReginProcessoProtocolador;
use App\Services\Regin\ReginRecebeService;
use App\Support\Audit\AuditService;
use Database\Seeders\RiscoMunicipalSeeder;
use Database\Seeders\RiscoSanitarioSeeder;
use Database\Seeders\RiskTriggerSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use RuntimeException;
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

    public function test_data_geracao_invalida_grava_nulo_e_devolve_3(): void
    {
        $response = $this->postJson('/api_integracao/recebe', $this->envioDto('43747', [
            'dataGeracao' => 'nao-e-uma-data',
        ]));

        $response->assertOk();
        $this->assertSame('3', $response->getContent());
        $this->assertDatabaseHas('regin_recebimentos', [
            'protocolo' => '43747',
            'data_geracao' => null,
        ]);
    }

    public function test_json_malformado_devolve_0_sem_gravar(): void
    {
        $response = $this->call(
            'POST',
            '/api_integracao/recebe',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'text/plain'],
            '{ isto nao e json',
        );

        $response->assertStatus(400);
        $this->assertSame('0', $response->getContent());
        $this->assertSame(0, ReginRecebimento::query()->count());
    }

    public function test_falha_na_auditoria_nao_derruba_o_ack_nem_apaga_o_recebimento(): void
    {
        $this->mock(AuditService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('log')->andThrow(new RuntimeException('trilha indisponível'));
        });

        $response = $this->postJson('/api_integracao/recebe', $this->envioDto('43747'));

        $response->assertOk();
        $this->assertSame('3', $response->getContent());
        $this->assertDatabaseHas('regin_recebimentos', [
            'protocolo' => '43747',
        ]);
    }

    public function test_erro_tecnico_devolve_500_em_texto_puro_sem_vazar_detalhe(): void
    {
        $this->mock(ReginRecebeService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('receive')->andThrow(new RuntimeException('SQLSTATE[08006] segredo do banco'));
        });

        $response = $this->postJson('/api_integracao/recebe', $this->envioDto('43747'));

        $response->assertStatus(500);
        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $this->assertSame('erro interno', $response->getContent());
        $this->assertStringNotContainsString('SQLSTATE', $response->getContent());
        $this->assertStringNotContainsString('<html', $response->getContent());
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

    /**
     * A protocolação faz escritas e chamadas externas (Cadastro Imobiliário,
     * GeoServer). Dentro da transação do envelope, uma query que falhe aborta
     * a transação inteira no PostgreSQL: a auditoria seguinte estoura e o
     * endpoint devolve 500 sem gravar nada — contrato REGIN quebrado.
     */
    public function test_protocolacao_roda_fora_da_transacao_do_envelope(): void
    {
        $nivel = -1;

        $this->mock(
            ReginProcessoProtocolador::class,
            function (MockInterface $mock) use (&$nivel): void {
                $mock->shouldReceive('protocolar')
                    ->once()
                    ->andReturnUsing(function () use (&$nivel) {
                        $nivel = DB::transactionLevel();

                        return null;
                    });
            },
        );

        // A suíte roda dentro de uma transação (RefreshDatabase), aberta de
        // forma preguiçosa na primeira query. O que importa é a protocolação
        // NÃO acrescentar um nível sobre o envelope.
        ReginRecebimento::query()->count();
        $nivelBase = DB::transactionLevel();

        $response = $this->postJson('/api_integracao/recebe', $this->envioDto('43747'));

        $response->assertOk();
        $this->assertSame('3', $response->getContent());
        $this->assertSame($nivelBase, $nivel);
    }

    /**
     * O ack 3 é do ENVELOPE (guia: "recebimento do processo na prefeitura").
     * A abertura do processo consulta o Cadastro Imobiliário e o GeoServer —
     * medido em ~10s no ambiente real — e não pode ficar no caminho da
     * resposta. Vai para a fila, como já faz o protocolo do portal.
     */
    public function test_ack_nao_espera_a_abertura_do_processo(): void
    {
        Queue::fake([ProtocolarRecebimentoReginJob::class]);

        $response = $this->postJson('/api_integracao/recebe', $this->envioDto('43747'));

        $response->assertOk();
        $this->assertSame('3', $response->getContent());

        $recebimento = ReginRecebimento::query()->where('protocolo', '43747')->firstOrFail();

        Queue::assertPushed(
            ProtocolarRecebimentoReginJob::class,
            fn (ProtocolarRecebimentoReginJob $job) => $job->reginRecebimentoId === $recebimento->id,
        );
    }

    public function test_duplicado_nao_enfileira_protocolacao(): void
    {
        $this->postJson('/api_integracao/recebe', $this->envioDto('43747'));

        Queue::fake([ProtocolarRecebimentoReginJob::class]);

        $segunda = $this->postJson('/api_integracao/recebe', $this->envioDto('43747'));

        $this->assertSame('5', $segunda->getContent());
        Queue::assertNothingPushed();
    }

    /**
     * Corrida real entre dois envios do mesmo protocolo: o SELECT prévio não
     * trava linha inexistente, então os dois passam pela verificação e os dois
     * tentam gravar. Quem decide é o índice único — a violação significa
     * duplicado (5), nunca 500. A corrida é reproduzida de forma determinística
     * inserindo a linha concorrente imediatamente antes do INSERT do model.
     */
    public function test_corrida_no_mesmo_protocolo_devolve_5_sem_segunda_linha(): void
    {
        ReginRecebimento::creating(function (ReginRecebimento $model): void {
            static $jaCorreu = false;

            if ($jaCorreu) {
                return;
            }

            $jaCorreu = true;

            DB::table('regin_recebimentos')->insert([
                'protocolo' => $model->protocolo,
                'envelope' => '{}',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $response = $this->postJson('/api_integracao/recebe', $this->envioDto('43747'));

        $response->assertOk();
        $this->assertSame('5', $response->getContent());
        $this->assertSame(1, ReginRecebimento::query()->where('protocolo', '43747')->count());
    }

    public function test_falha_ao_protocolar_preserva_o_envelope_e_registra_a_pendencia(): void
    {
        $this->mock(
            ReginProcessoProtocolador::class,
            function (MockInterface $mock): void {
                $mock->shouldReceive('protocolar')
                    ->once()
                    ->andThrow(new RuntimeException('motor de regras indisponível'));
            },
        );

        $response = $this->postJson('/api_integracao/recebe', $this->envioDto('43747'));

        $response->assertOk();
        $this->assertSame('3', $response->getContent());
        $this->assertDatabaseHas('regin_recebimentos', [
            'protocolo' => '43747',
            'viability_request_id' => null,
        ]);

        $falha = Activity::query()->where('result', 'falha')->first();

        $this->assertNotNull($falha);
        $this->assertSame('43747', $falha->properties['protocolo'] ?? null);
        $this->assertSame(RuntimeException::class, $falha->properties['excecao'] ?? null);
        $this->assertStringNotContainsString(
            'motor de regras indisponível',
            json_encode($falha->properties, JSON_THROW_ON_ERROR),
        );
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
