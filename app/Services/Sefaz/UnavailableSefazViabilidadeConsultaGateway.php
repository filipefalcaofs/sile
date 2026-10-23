<?php

namespace App\Services\Sefaz;

/**
 * Provider padrão da consulta: o endpoint da SEFAZ ainda não foi contratado,
 * então a consulta degrada honestamente e nunca devolve viabilidade inventada.
 */
class UnavailableSefazViabilidadeConsultaGateway implements SefazViabilidadeConsultaGateway
{
    public function consultarPorCnpj(string $cnpj): ?ViabilidadeVinculada
    {
        throw new SefazConsultaIndisponivelException($cnpj, 'Consulta de viabilidade por CNPJ à SEFAZ sem endpoint contratado.');
    }
}
