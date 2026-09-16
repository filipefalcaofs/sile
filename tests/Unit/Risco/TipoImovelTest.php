<?php

namespace Tests\Unit\Risco;

use App\Enums\TipoImovelReconhecimento;
use App\Services\Risco\TipoImovel;
use App\Services\Risco\TipoImovelCatalog;
use Tests\TestCase;

/**
 * Tipo de imóvel chega do REGIN (PO Lisa / SEDUR 2026-08-31 e 2026-09-15).
 * Só galpão, container e edificação residencial dirigem regra. Valor
 * conhecido fora desses três cai no ramo comum. Valor que o catálogo não
 * reconhece NUNCA vira "não é galpão" — o motor manda à análise.
 */
class TipoImovelTest extends TestCase
{
    private function catalogo(): TipoImovelCatalog
    {
        return TipoImovelCatalog::sedur200826();
    }

    public function test_ausente_quando_regin_nao_enviou_valor(): void
    {
        $tipo = TipoImovel::fromRegin(null, $this->catalogo());

        $this->assertSame(TipoImovelReconhecimento::Ausente, $tipo->reconhecimento);
        $this->assertNull($tipo->normalized);
        $this->assertFalse($tipo->dirigeRegra());
        $this->assertFalse($tipo->permiteDecisaoAutomatica());
    }

    public function test_galpao_container_e_residencial_dirigem_regra(): void
    {
        foreach (['GALPÃO', 'Container', 'Edificação Residencial'] as $raw) {
            $tipo = TipoImovel::fromRegin($raw, $this->catalogo());

            $this->assertTrue($tipo->dirigeRegra(), $raw);
            $this->assertTrue($tipo->permiteDecisaoAutomatica(), $raw);
            $this->assertSame(TipoImovelReconhecimento::DirigeRegra, $tipo->reconhecimento);
        }
    }

    public function test_valor_conhecido_que_nao_dirige_regra_cai_no_ramo_comum(): void
    {
        $tipo = TipoImovel::fromRegin('Edificação Comercial', $this->catalogo());

        $this->assertFalse($tipo->dirigeRegra());
        $this->assertTrue($tipo->permiteDecisaoAutomatica());
        $this->assertSame(TipoImovelReconhecimento::RamoComum, $tipo->reconhecimento);
        $this->assertSame('edificacao_comercial', $tipo->normalized);
    }

    public function test_valor_desconhecido_nao_e_tratado_como_nao_galpao(): void
    {
        $tipo = TipoImovel::fromRegin('Loja de shopping (grafia nova)', $this->catalogo());

        $this->assertSame(TipoImovelReconhecimento::Desconhecido, $tipo->reconhecimento);
        $this->assertFalse($tipo->dirigeRegra());
        $this->assertFalse($tipo->permiteDecisaoAutomatica());
    }
}
