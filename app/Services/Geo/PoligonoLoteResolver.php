<?php

namespace App\Services\Geo;

use App\Models\ViabilityRequest;

/**
 * Percorre as fontes de geometria na ordem de precedência e registra TODAS no
 * processo — inclusive as que não acharam nada. Para na primeira com polígono:
 * fonte mais forte encontrada dispensa consultar as mais fracas.
 *
 * Ambíguo e não encontrado caem para a próxima: a ambiguidade do endereçamento
 * (CODLOG + número com blocos distintos) é exatamente o que a inscrição do
 * SEMAP desempata.
 */
class PoligonoLoteResolver
{
    /**
     * @param  iterable<PoligonoLoteFonte>  $fontes
     */
    public function __construct(
        private iterable $fontes,
        private PoligonoLoteService $service,
    ) {}

    public function resolver(ViabilityRequest $request): ?PoligonoLoteHit
    {
        foreach ($this->fontes as $fonte) {
            $hit = $this->service->registrar($request, $fonte->buscar($request));

            if ($hit->temPoligono()) {
                return $hit;
            }
        }

        return null;
    }
}
