<?php

namespace App\Services\Geo;

/**
 * Contrato de geocodificação (endereço → coordenada). O provider concreto é
 * administrável por parâmetro (URL/base) e substituível por binding — a Fase 13
 * troca a implementação por um self-host ou pela base geográfica da SEDUR sem
 * tocar nenhum call site (mesmo padrão de CnpjLookup).
 */
interface Geocoder
{
    /**
     * Converte um endereço em coordenada + endereço normalizado + confiança.
     *
     * @throws AddressNotFoundException quando o provider não localiza o endereço
     * @throws GeocoderException em indisponibilidade ou erro do provider
     */
    public function geocode(string $address): GeocodeResult;
}
