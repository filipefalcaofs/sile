<?php

namespace App\Services\Geo;

use App\Enums\PropertyLocationSource;
use Throwable;

/**
 * Pontos do imóvel por endereço ou inscrição, em outras fontes que não o
 * Cadastro Imobiliário: endereçamento SEDUR (CODLOG + número), lote da
 * inscrição no SEMAP e geocodificação do endereço. Devolve só as fontes que
 * acharam, na ordem de confiança; falha de uma fonte nunca derruba as outras.
 */
class PontoAlternativoResolver
{
    public function __construct(
        private GeoServerEnderecamentoClient $enderecamento,
        private SemapLoteClient $semap,
        private Geocoder $geocoder,
    ) {}

    /**
     * @return list<PontoAlternativo>
     */
    public function candidatos(string $inscricao, ?string $codLog, ?string $numero, ?string $enderecoParaGeocode): array
    {
        $candidatos = [];

        if ($codLog !== null && $codLog !== '' && $numero !== null && $numero !== '') {
            $this->acrescentar($candidatos, fn (): ?PontoAlternativo => $this->doLote(
                $this->enderecamento->consultar($codLog, $numero),
                PropertyLocationSource::EnderecamentoSedur,
            ));
        }

        $this->acrescentar($candidatos, fn (): ?PontoAlternativo => $this->doLote(
            $this->semap->consultar($inscricao),
            PropertyLocationSource::Semap,
        ));

        if ($enderecoParaGeocode !== null && $enderecoParaGeocode !== '') {
            $this->acrescentar($candidatos, fn (): ?PontoAlternativo => $this->geocodificar($enderecoParaGeocode));
        }

        return $candidatos;
    }

    /**
     * @param  list<PontoAlternativo>  $candidatos
     * @param  callable(): ?PontoAlternativo  $buscar
     */
    private function acrescentar(array &$candidatos, callable $buscar): void
    {
        try {
            $ponto = $buscar();
        } catch (Throwable) {
            return;
        }

        if ($ponto !== null) {
            $candidatos[] = $ponto;
        }
    }

    private function doLote(EnderecamentoHit $hit, PropertyLocationSource $fonte): ?PontoAlternativo
    {
        if (! $hit->encontrado() || $hit->geometria === null) {
            return null;
        }

        $centroide = DistanciaGeografica::centroideDoPoligono($hit->geometria);

        return $centroide === null ? null : new PontoAlternativo($centroide['lat'], $centroide['lng'], $fonte);
    }

    private function geocodificar(string $endereco): ?PontoAlternativo
    {
        $geocode = $this->geocoder->geocode($endereco);

        if (! $geocode->estaEmSalvador()) {
            return null;
        }

        return new PontoAlternativo($geocode->latitude, $geocode->longitude, PropertyLocationSource::Geocodificacao);
    }
}
