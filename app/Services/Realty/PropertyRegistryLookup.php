<?php

namespace App\Services\Realty;

/**
 * Contrato de resolução de inscrição imobiliária em coordenada do imóvel a
 * partir da base de lotes / Cadastro Multifinalitário. O provider concreto é
 * substituível por binding — a Fase 13 (HU-106) liga a base oficial
 * (Cadastro/SEFAZ) sem tocar nenhum call site (mesmo padrão de CnpjLookup/Geocoder).
 */
interface PropertyRegistryLookup
{
    /**
     * Resolve uma inscrição imobiliária em coordenada do imóvel (base de
     * lotes / Cadastro Multifinalitário). O provider concreto é trocável por
     * binding — a Fase 13 (HU-106) liga a base oficial sem tocar call sites.
     *
     * @throws PropertyNotFoundException quando a inscrição não existe na base
     * @throws PropertyRegistryUnavailableException quando a base está indisponível/pendente
     */
    public function resolve(string $inscricao): PropertyRegistryResult;
}
