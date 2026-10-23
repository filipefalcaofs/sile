<?php

namespace App\Services\Sefaz;

/**
 * Consulta da viabilidade vinculada a um CNPJ na SEFAZ municipal (alteração de
 * atividade econômica). Não confundir com SefazViabilidadeGateway, que ENVIA.
 */
interface SefazViabilidadeConsultaGateway
{
    /**
     * @return ViabilidadeVinculada|null null quando o CNPJ não tem viabilidade vinculada
     *
     * @throws SefazConsultaIndisponivelException
     */
    public function consultarPorCnpj(string $cnpj): ?ViabilidadeVinculada;
}
