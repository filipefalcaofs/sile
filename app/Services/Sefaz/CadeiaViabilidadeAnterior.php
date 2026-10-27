<?php

namespace App\Services\Sefaz;

use App\Models\ViabilityRequest;

/**
 * Procura a viabilidade anterior da empresa nas fontes, em ordem: processo
 * anterior no Viabiliza, legado e SEFAZ. Usa a primeira que responder; fonte sem
 * integração é pulada, e uma falha de fonte disponível propaga.
 */
class CadeiaViabilidadeAnterior
{
    /**
     * @var list<FonteViabilidadeAnterior>
     */
    private array $fontes;

    public function __construct(
        ProcessoAnteriorViabilizaFonte $viabiliza,
        LegadoViabilidadeFonte $legado,
        SefazViabilidadeFonte $sefaz,
    ) {
        $this->fontes = [$viabiliza, $legado, $sefaz];
    }

    /**
     * @return array{viabilidade: ViabilidadeVinculada, fonte: string}|null null quando nenhuma fonte tem a empresa
     *
     * @throws SefazConsultaIndisponivelException
     */
    public function consultar(ViabilityRequest $processo, string $cnpj): ?array
    {
        foreach ($this->fontes as $fonte) {
            if (! $fonte->disponivel()) {
                continue;
            }

            $viabilidade = $fonte->consultar($processo, $cnpj);

            if ($viabilidade !== null) {
                return ['viabilidade' => $viabilidade, 'fonte' => $fonte->nome()];
            }
        }

        return null;
    }
}
