<?php

namespace Tests\Feature\Analise;

use App\Models\AnalysisRecord;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Analise\PrecedentRepository;
use App\Services\Analise\PrecedentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Analise\FakePrecedentRepository;
use Tests\TestCase;

/**
 * Precedentes na ficha de análise (HU-142): o PrecedentService monta o painel
 * de apoio à decisão — decisões anteriores no mesmo imóvel (CA-01) e estatística
 * do CNAE na zona (CA-02) — aplicando os parâmetros (janela/itens, RN-003),
 * minimizando dados pessoais (LGPD, RN-004: nunca CPF) e degradando honesto
 * quando não há zona (estatística "indisponível", nunca inventada) ou quando não
 * há histórico (listas vazias explícitas, CA-03 — não ocultar).
 *
 * Roda em SQLite com o FakePrecedentRepository injetado POPULADO via
 * $this->app->instance — o SQL espacial real (ST_Intersects) é provado em
 * PrecedentRepositoryPostgisTest (@group postgis). Espelha o
 * SolicitacaoViabilityResolverTest, que injeta o FakeSpatialRepository.
 */
class PrecedentServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): PrecedentService
    {
        return app(PrecedentService::class);
    }

    /**
     * Snapshot do motor (shape do ResolvedViability::toSnapshot da Fase 9) com a
     * zona no caminho EXATO por_cnae[i].consulta.territorio.zona.
     *
     * @param  array<string, mixed>  $zona
     * @return array<string, mixed>
     */
    private function snapshotComZona(array $zona, string $cnae = '4712100'): array
    {
        return [
            'ponto' => ['lat' => -12.9711, 'lng' => -38.5108],
            'area_m2' => 120.5,
            'por_cnae' => [
                [
                    'cnae' => $cnae,
                    'cnae_formatado' => '4712-1/00',
                    'is_primary' => true,
                    'tendencia' => 'permitido',
                    'tendencia_label' => 'Permitido',
                    'consulta' => [
                        'territorio' => [
                            'zona' => $zona,
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Ficha (rev. 1) de uma solicitação protocolada com requerente de CPF
     * conhecido (alvo da asserção LGPD) e endereço (fallback portável).
     *
     * @param  array<string, mixed>|null  $engineSnapshot
     */
    private function fichaComSnapshot(?array $engineSnapshot, string $cpf = '52998224725'): AnalysisRecord
    {
        $requester = User::factory()->create(['cpf' => $cpf]);

        $request = ViabilityRequest::factory()->protocoled()->create([
            'requester_user_id' => $requester->id,
            'address_street' => 'Rua das Laranjeiras',
            'address_number' => '100',
        ]);

        return AnalysisRecord::factory()->create([
            'viability_request_id' => $request->id,
            'engine_snapshot' => $engineSnapshot,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function doisPrecedentes(): array
    {
        return [
            [
                'viability_request_id' => 501,
                'protocol_number' => 'VIA-2025-000050',
                'outcome' => 'deferida',
                'decided_at' => '2025-03-01T10:00:00Z',
                'service_type' => 'Viabilidade de localização',
                'analyst' => 'Maria Analista',
            ],
            [
                'viability_request_id' => 502,
                'protocol_number' => 'VIA-2025-000051',
                'outcome' => 'indeferida',
                'decided_at' => '2025-02-01T10:00:00Z',
                'service_type' => 'Viabilidade de localização',
                'analyst' => 'João Analista',
            ],
        ];
    }

    public function test_precedentes_do_imovel_sem_cpf_no_payload(): void
    {
        $cpf = '52998224725';
        $ficha = $this->fichaComSnapshot(
            $this->snapshotComZona(['status' => 'indisponivel', 'nome' => null]),
            $cpf,
        );

        $fake = new FakePrecedentRepository;
        $fake->setPropertyPrecedents($this->doisPrecedentes());
        $this->app->instance(PrecedentRepository::class, $fake);

        $payload = $this->service()->forRecord($ficha);

        // CA-01: as duas decisões anteriores do imóvel aparecem.
        $this->assertCount(2, $payload['imovel']);
        $this->assertSame('VIA-2025-000050', $payload['imovel'][0]['protocol_number']);
        $this->assertSame('deferida', $payload['imovel'][0]['outcome']);

        // LGPD (RN-004): o payload NUNCA carrega o CPF do requerente.
        $this->assertStringNotContainsString($cpf, json_encode($payload, JSON_THROW_ON_ERROR));
    }

    public function test_max_itens_parametrizado_limita_a_lista(): void
    {
        // RN-003: a quantidade exibida é parametrizável (HU-014). Settings::get
        // cai no fallback de config quando não há linha em parameters.
        config(['sile.analise.precedentes.max_itens' => 1]);

        $ficha = $this->fichaComSnapshot(
            $this->snapshotComZona(['status' => 'indisponivel', 'nome' => null]),
        );

        $fake = new FakePrecedentRepository;
        $fake->setPropertyPrecedents($this->doisPrecedentes());
        $this->app->instance(PrecedentRepository::class, $fake);

        $payload = $this->service()->forRecord($ficha);

        $this->assertCount(1, $payload['imovel']);
        // O serviço repassou o limite parametrizado ao repositório.
        $this->assertSame(1, $fake->propertyCalls[0]['limit']);
    }

    public function test_estatistica_do_cnae_na_zona_quando_ha_zona(): void
    {
        config(['sile.analise.precedentes.janela_meses' => 12]);

        $ficha = $this->fichaComSnapshot(
            $this->snapshotComZona(['status' => 'identificado', 'nome' => 'ZR-1'], '4712100'),
        );

        $fake = new FakePrecedentRepository;
        $fake->setCnaeZoneStats('4712100', 'ZR-1', ['deferidos' => 14, 'indeferidos' => 2, 'total' => 16]);
        $this->app->instance(PrecedentRepository::class, $fake);

        $payload = $this->service()->forRecord($ficha);

        // CA-02: estatística deferidos × indeferidos do CNAE na zona.
        $this->assertTrue($payload['cnae_zona']['disponivel']);
        $this->assertSame('4712100', $payload['cnae_zona']['cnae']);
        $this->assertSame('ZR-1', $payload['cnae_zona']['zona']);
        $this->assertSame(14, $payload['cnae_zona']['deferidos']);
        $this->assertSame(2, $payload['cnae_zona']['indeferidos']);
        $this->assertSame(16, $payload['cnae_zona']['total']);
        $this->assertSame(12, $payload['cnae_zona']['janela_meses']);
    }

    public function test_degrada_honesto_sem_zona_no_snapshot(): void
    {
        $ficha = $this->fichaComSnapshot(
            $this->snapshotComZona(['status' => 'indisponivel', 'nome' => null, 'motivo' => 'base de zoneamento pendente']),
        );

        $fake = new FakePrecedentRepository;
        // Mesmo que o repositório respondesse, a falta de zona impede a consulta.
        $fake->setCnaeZoneStats('4712100', 'ZR-1', ['deferidos' => 99, 'indeferidos' => 99, 'total' => 198]);
        $this->app->instance(PrecedentRepository::class, $fake);

        $payload = $this->service()->forRecord($ficha);

        // RN-004: sem zona, estatística "indisponível" — nunca inventada.
        $this->assertFalse($payload['cnae_zona']['disponivel']);
        $this->assertArrayHasKey('motivo', $payload['cnae_zona']);
        // O serviço NÃO consultou a estatística do CNAE sem zona.
        $this->assertSame([], $fake->cnaeZoneCalls);
    }

    public function test_sem_precedentes_retorna_listas_vazias_explicitas(): void
    {
        $ficha = $this->fichaComSnapshot(
            $this->snapshotComZona(['status' => 'identificado', 'nome' => 'ZR-1'], '4712100'),
        );

        // Fake vazio: sem precedentes do imóvel, sem estatística do CNAE.
        $fake = new FakePrecedentRepository;
        $this->app->instance(PrecedentRepository::class, $fake);

        $payload = $this->service()->forRecord($ficha);

        // CA-03: o painel indica explicitamente "sem precedentes" — lista vazia
        // presente, nunca seção ocultada / erro.
        $this->assertArrayHasKey('imovel', $payload);
        $this->assertSame([], $payload['imovel']);
        $this->assertTrue($payload['cnae_zona']['disponivel']);
        $this->assertSame(0, $payload['cnae_zona']['total']);
    }
}
