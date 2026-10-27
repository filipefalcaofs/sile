<?php

namespace App\Services\Sefaz;

use App\Models\ViabilityRequest;

/**
 * Origem da viabilidade anterior da empresa, base da comparação de atividades.
 * As fontes são consultadas em ordem (CadeiaViabilidadeAnterior).
 */
interface FonteViabilidadeAnterior
{
    /**
     * Identificador gravado na auditoria da comparação.
     */
    public function nome(): string;

    /**
     * Falso quando a fonte ainda não tem integração: a cadeia a ignora, em vez
     * de tratar como falha e fazer o processo esperar.
     */
    public function disponivel(): bool;

    /**
     * @return ViabilidadeVinculada|null null quando a empresa não tem viabilidade nesta fonte
     *
     * @throws SefazConsultaIndisponivelException
     */
    public function consultar(ViabilityRequest $processo, string $cnpj): ?ViabilidadeVinculada;
}
