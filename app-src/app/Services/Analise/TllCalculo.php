<?php

namespace App\Services\Analise;

/**
 * Resultado do cálculo do valor do DAM da TLL (HU-071 RN-004) — o valor final
 * e o detalhamento (qual código TLL de maior valor, a taxa de serviço e se o
 * fator multiplicador foi aplicado), para auditoria e para o bloco `taxas` da
 * SEFAZ.
 */
final readonly class TllCalculo
{
    public function __construct(
        public string $valor,
        public string $codigo_tll,
        public string $valor_tll,
        public string $taxa_servico,
        public bool $fator_aplicado,
        public int $exercicio,
    ) {}
}
