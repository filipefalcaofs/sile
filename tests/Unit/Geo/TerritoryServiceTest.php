<?php

namespace Tests\Unit\Geo;

use App\Enums\GeoLayerType;
use App\Models\Activity;
use App\Models\GeoLayer;
use App\Services\Geo\TerritoryService;
use App\Support\Audit\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Geo\FakeSpatialRepository;
use Tests\TestCase;

/**
 * Identificação territorial por ponto (HU-031 a HU-035) provada SEM PostGIS,
 * com o fake do repositório espacial: bairro/via/restrições identificados a
 * partir da camada vigente; zona/lote com base pendente retornam indisponível
 * (degradação comunicada) SEM disparar consulta espacial — sem fachada; a
 * versão de cada camada é registrada (RN-004) e a identificação é auditada
 * (RN-002).
 */
class TerritoryServiceTest extends TestCase
{
    use RefreshDatabase;

    private FakeSpatialRepository $fake;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fake = new FakeSpatialRepository;
    }

    private function service(): TerritoryService
    {
        return new TerritoryService($this->fake, app(AuditService::class));
    }

    private function seedVigente(GeoLayerType $type): GeoLayer
    {
        return GeoLayer::factory()->vigente()->create([
            'type' => $type,
            'version' => $type->value.'-2024',
            'feature_count' => 1,
        ]);
    }

    private function seedPendente(GeoLayerType $type): GeoLayer
    {
        return GeoLayer::factory()->pendenteFonte()->create([
            'type' => $type,
            'version' => $type->value.'-pendente',
        ]);
    }

    public function test_identifica_o_bairro_que_contem_o_ponto(): void
    {
        $this->seedVigente(GeoLayerType::Bairro);
        $this->fake->setContaining(GeoLayerType::Bairro, [
            'id' => 10,
            'properties' => ['NOME_BAIRRO' => 'Pelourinho'],
        ]);

        $result = $this->service()->identify(-12.97, -38.51);

        $this->assertSame('identificado', $result->bairro['status']);
        $this->assertSame('Pelourinho', $result->bairro['nome']);
        $this->assertSame('bairro-2024', $result->bairro['versao_camada']);
    }

    public function test_bairro_sem_correspondencia_retorna_nao_encontrado(): void
    {
        $this->seedVigente(GeoLayerType::Bairro);
        // fake não registrado para bairro → ponto fora de qualquer bairro.

        $result = $this->service()->identify(-12.97, -38.51);

        $this->assertSame('nao_encontrado', $result->bairro['status']);
        // O caminho de consulta FOI percorrido (contraste com zona/lote).
        $this->assertContains('bairro', $this->fake->containingCalls);
    }

    public function test_identifica_a_via_mais_proxima(): void
    {
        $this->seedVigente(GeoLayerType::Via);
        $this->fake->setNearest(GeoLayerType::Via, [
            'id' => 5,
            'properties' => ['NOME_LOGRADOURO' => 'Rua Chile'],
            'distancia_m' => 12.3,
        ]);

        $result = $this->service()->identify(-12.97, -38.51);

        $this->assertSame('identificado', $result->via['status']);
        $this->assertSame('Rua Chile', $result->via['nome']);
        $this->assertSame(12.3, $result->via['distancia_m']);
        $this->assertSame('via-2024', $result->via['versao_camada']);
    }

    public function test_identifica_as_restricoes_incidentes(): void
    {
        $this->seedVigente(GeoLayerType::Restricao);
        $this->fake->setIntersecting(GeoLayerType::Restricao, [
            ['id' => 1, 'properties' => ['NOME' => 'ZEIS Centro']],
            ['id' => 2, 'properties' => ['NOME' => 'APA Lagoa']],
        ]);

        $result = $this->service()->identify(-12.97, -38.51);

        $this->assertSame('identificado', $result->restricoes['status']);
        $this->assertCount(2, $result->restricoes['itens']);
        $this->assertSame('ZEIS Centro', $result->restricoes['itens'][0]['nome']);
    }

    public function test_zona_com_base_pendente_retorna_indisponivel_sem_consulta_espacial(): void
    {
        $this->seedPendente(GeoLayerType::Zona);

        $result = $this->service()->identify(-12.97, -38.51);

        $this->assertSame('indisponivel', $result->zona['status']);
        $this->assertStringContainsString('pendente SEDUR', (string) $result->zona['motivo']);
        $this->assertSame('zona-pendente', $result->zona['versao_camada']);
        // Sem fachada: NENHUMA consulta espacial disparada para zona.
        $this->assertNotContains('zona', $this->fake->containingCalls);
    }

    public function test_lote_com_base_pendente_retorna_indisponivel_sem_consulta_espacial(): void
    {
        $this->seedPendente(GeoLayerType::Lote);

        $result = $this->service()->identify(-12.97, -38.51);

        $this->assertSame('indisponivel', $result->lote['status']);
        $this->assertStringContainsString('pendente SEDUR', (string) $result->lote['motivo']);
        $this->assertNotContains('lote', $this->fake->containingCalls);
    }

    public function test_identificacao_e_auditada_com_as_versoes_das_camadas(): void
    {
        $this->seedVigente(GeoLayerType::Bairro);
        $this->seedPendente(GeoLayerType::Zona);
        $this->fake->setContaining(GeoLayerType::Bairro, [
            'id' => 10,
            'properties' => ['NOME_BAIRRO' => 'Pelourinho'],
        ]);

        $this->service()->identify(-12.97, -38.51);

        $activity = Activity::query()
            ->where('log_name', 'territorio')
            ->where('event', 'identificacao')
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame('sucesso', $activity->result);
        $this->assertSame('bairro-2024', $activity->properties['versoes_por_camada']['bairro']);
        $this->assertSame('zona-pendente', $activity->properties['versoes_por_camada']['zona']);
        $this->assertSame('indisponivel', $activity->properties['resumo_status']['zona']);
        $this->assertSame('identificado', $activity->properties['resumo_status']['bairro']);
    }
}
