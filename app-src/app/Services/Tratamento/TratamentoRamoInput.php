<?php

namespace App\Services\Tratamento;

use App\Services\Risco\TipoImovel;
use Carbon\CarbonInterface;

final readonly class TratamentoRamoInput
{
    /**
     * @param  array<int, bool>  $respostas  Número da pergunta → sim/não
     * @param  array<string, string>  $versoesOverride
     */
    public function __construct(
        public string $cnae,
        public array $respostas = [],
        public ?float $areaUtilizada = null,
        public ?TipoImovel $tipoImovel = null,
        public ?CarbonInterface $data = null,
        public array $versoesOverride = [],
    ) {}
}
