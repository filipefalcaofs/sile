<?php

namespace Tests\Feature\Geo;

use App\Services\Geo\ClassificacaoViariaHit;
use App\Services\Geo\GeoServerClassificacaoViariaClient;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * A camada de classificação viária da LOUOS publica só as vias classificadas
 * (1.168 logradouros medidos em 2026-10-03) — a VIA LOCAL não está lá. A
 * hierarquia da via local vem da camada de logradouros, no atributo
 * HIERARQUIA (decisão 2 da ata de 2026-10-02).
 *
 * Exemplos reais medidos: CODLOG 4886 (Rua da Matriz) está na classificação
 * como "VC - II"; CODLOG 9539 (Praça Gago Coutinho) não está lá e aparece na
 * camada de logradouros como "VL".
 */
class ClassificacaoViariaViaLocalTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['sile.features.geoserver_via_classe' => true]);
    }

    /**
     * @param  list<array<string, mixed>>  $features
     * @return array<string, mixed>
     */
    private function colecao(array $features): array
    {
        return ['type' => 'FeatureCollection', 'features' => $features];
    }

    public function test_via_classificada_nao_consulta_a_camada_de_logradouros(): void
    {
        Http::fake([
            '*VM_L_CLASSIF_VIARIA*' => Http::response($this->colecao([
                ['properties' => ['CODLOG' => 4886, 'CLASSIFICACAO_VIARIA' => 'VC - II']],
            ])),
            '*VM_LOGRADOURO*' => Http::response($this->colecao([])),
        ]);

        $hit = app(GeoServerClassificacaoViariaClient::class)->consultar('4886');

        $this->assertSame(ClassificacaoViariaHit::IDENTIFICADO, $hit->status);
        $this->assertSame('VC II', $hit->classe);
        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'VM_LOGRADOURO'));
    }

    public function test_via_local_vem_da_hierarquia_da_camada_de_logradouros(): void
    {
        Http::fake([
            '*VM_L_CLASSIF_VIARIA*' => Http::response($this->colecao([])),
            '*VM_LOGRADOURO*' => Http::response($this->colecao([
                ['properties' => ['CODLOG' => 9539, 'NOME_LOGRADOURO' => 'Praça Gago Coutinho', 'HIERARQUIA' => 'VL']],
            ])),
        ]);

        $hit = app(GeoServerClassificacaoViariaClient::class)->consultar('9539');

        $this->assertSame(ClassificacaoViariaHit::IDENTIFICADO, $hit->status);
        $this->assertSame('VL', $hit->classe);
        $this->assertSame('VL', $hit->classeBruta);
    }

    public function test_hierarquia_com_hifen_vira_a_grafia_da_lei(): void
    {
        Http::fake([
            '*VM_L_CLASSIF_VIARIA*' => Http::response($this->colecao([])),
            '*VM_LOGRADOURO*' => Http::response($this->colecao([
                ['properties' => ['CODLOG' => 4886, 'HIERARQUIA' => 'VC-II']],
            ])),
        ]);

        $this->assertSame('VC II', app(GeoServerClassificacaoViariaClient::class)->consultar('4886')->classe);
    }

    public function test_sem_linha_nas_duas_camadas_nao_inventa_classe(): void
    {
        Http::fake([
            '*VM_L_CLASSIF_VIARIA*' => Http::response($this->colecao([])),
            '*VM_LOGRADOURO*' => Http::response($this->colecao([])),
        ]);

        $hit = app(GeoServerClassificacaoViariaClient::class)->consultar('9999999');

        $this->assertSame(ClassificacaoViariaHit::NAO_ENCONTRADO, $hit->status);
        $this->assertNull($hit->classe);
    }

    public function test_hierarquia_fora_das_classes_do_quadro_11a_nao_vira_classe(): void
    {
        Http::fake([
            '*VM_L_CLASSIF_VIARIA*' => Http::response($this->colecao([])),
            '*VM_LOGRADOURO*' => Http::response($this->colecao([
                ['properties' => ['CODLOG' => 123, 'HIERARQUIA' => 'XPTO']],
            ])),
        ]);

        $hit = app(GeoServerClassificacaoViariaClient::class)->consultar('123');

        $this->assertSame(ClassificacaoViariaHit::INDISPONIVEL, $hit->status);
        $this->assertSame('XPTO', $hit->classeBruta);
    }

    public function test_falha_na_camada_de_logradouros_nao_derruba_a_consulta(): void
    {
        Http::fake([
            '*VM_L_CLASSIF_VIARIA*' => Http::response($this->colecao([])),
            '*VM_LOGRADOURO*' => Http::response('erro', 500),
        ]);

        $hit = app(GeoServerClassificacaoViariaClient::class)->consultar('9539');

        $this->assertSame(ClassificacaoViariaHit::NAO_ENCONTRADO, $hit->status);
        $this->assertNull($hit->classe);
    }
}
