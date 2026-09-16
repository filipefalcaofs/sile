<?php

namespace Tests\Feature\Solicitacao;

use App\Enums\TipoImovelReconhecimento;
use App\Models\ViabilityRequest;
use App\Services\Regin\ReginTipoImovelApplier;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Tipo de imóvel na solicitação vem do REGIN, nunca do formulário do
 * requerente. Valor desconhecido é persistido cru (rastreável) sem código
 * normalizado — o motor é quem manda à análise, o applier não inventa.
 */
class TipoImovelReginTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_aplica_galpao_do_regin_e_grava_codigo_normalizado(): void
    {
        $request = ViabilityRequest::factory()->create();

        $tipo = app(ReginTipoImovelApplier::class)->apply($request, 'GALPÃO');

        $request->refresh();

        $this->assertTrue($tipo->dirigeRegra());
        $this->assertSame('GALPÃO', $request->tipo_imovel);
        $this->assertSame('galpao', $request->tipo_imovel_normalized);
    }

    public function test_valor_desconhecido_persiste_cru_sem_normalizado(): void
    {
        $request = ViabilityRequest::factory()->create();

        $tipo = app(ReginTipoImovelApplier::class)->apply($request, 'Loja de shopping (grafia nova)');

        $request->refresh();

        $this->assertSame(TipoImovelReconhecimento::Desconhecido, $tipo->reconhecimento);
        $this->assertSame('Loja de shopping (grafia nova)', $request->tipo_imovel);
        $this->assertNull($request->tipo_imovel_normalized);
    }

    public function test_ausencia_nao_inventa_tipo(): void
    {
        $request = ViabilityRequest::factory()->create();

        app(ReginTipoImovelApplier::class)->apply($request, null);

        $request->refresh();

        $this->assertNull($request->tipo_imovel);
        $this->assertNull($request->tipo_imovel_normalized);
    }

    public function test_cidadao_nao_preenche_tipo_imovel_via_fillable(): void
    {
        $request = ViabilityRequest::factory()->create();

        $request->fill(['tipo_imovel' => 'GALPÃO', 'tipo_imovel_normalized' => 'galpao']);

        $this->assertNull($request->tipo_imovel);
        $this->assertNull($request->tipo_imovel_normalized);
    }
}
