<?php

namespace App\Services\Tratamento;

use App\Services\Risco\TipoImovel;
use Carbon\CarbonInterface;

final readonly class TratamentoRamoInput
{
    /**
     * @param  array<int, bool|int|list<int>>  $respostas  Número da pergunta → sim/não, opção ou opções marcadas
     * @param  array<string, string>  $versoesOverride
     * @param  ?string  $categoriaEmpresa  `orgao_publico` ou `outra`; nulo = não informada.
     */
    public function __construct(
        public string $cnae,
        public array $respostas = [],
        public ?float $areaUtilizada = null,
        public ?TipoImovel $tipoImovel = null,
        public ?CarbonInterface $data = null,
        public array $versoesOverride = [],
        public ?string $categoriaEmpresa = null,
    ) {}
}
