<?php

namespace App\Services\Cnpj;

/**
 * Contrato de consulta de dados cadastrais de CNPJ na base aberta da Receita
 * Federal. O provider concreto é administrável por parâmetro (URL) e
 * substituível por binding — a Fase 13 (HU-105) troca a implementação pelo
 * convênio oficial RFB sem tocar nenhum call site.
 */
interface CnpjLookup
{
    /**
     * Consulta os dados cadastrais do CNPJ informado (14 dígitos normalizados).
     *
     * @throws CnpjNotFoundException quando o provider responde 404 (CNPJ inexistente)
     * @throws CnpjLookupException em indisponibilidade ou erro do provider
     */
    public function lookup(string $cnpj): CnpjData;
}
