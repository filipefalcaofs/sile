<?php

namespace App\Services\Tratamento;

use App\Services\Risco\TipoImovel;

/**
 * O que o ramo curado da planilha olha para decidir: respostas, categoria da
 * empresa, tipo do imóvel e faixa de área.
 */
final readonly class ContextoRamo
{
    /**
     * @param  array<int, bool|int|list<int>>  $respostas
     */
    public function __construct(
        public array $respostas,
        public ?string $categoriaEmpresa,
        public ?TipoImovel $tipoImovel,
        public ?string $faixa,
    ) {}
}
