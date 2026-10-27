<?php

namespace App\Services\Sefaz;

use App\Models\ViabilityRequest;

/**
 * Viabilidade vinculada ao CNPJ na SEFAZ. Sem provider real no binding, a fonte
 * é ignorada; com provider, uma falha dele propaga para o processo esperar.
 */
class SefazViabilidadeFonte implements FonteViabilidadeAnterior
{
    public function __construct(private SefazViabilidadeConsultaGateway $sefaz) {}

    public function nome(): string
    {
        return 'sefaz';
    }

    public function disponivel(): bool
    {
        return ! $this->sefaz instanceof UnavailableSefazViabilidadeConsultaGateway;
    }

    public function consultar(ViabilityRequest $processo, string $cnpj): ?ViabilidadeVinculada
    {
        return $this->sefaz->consultarPorCnpj($cnpj);
    }
}
