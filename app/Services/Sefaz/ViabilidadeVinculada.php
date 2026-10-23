<?php

namespace App\Services\Sefaz;

/**
 * Viabilidade vinculada a um CNPJ na SEFAZ: o número e os CNAEs dela.
 */
final readonly class ViabilidadeVinculada
{
    /**
     * @param  list<string>  $cnaes  códigos como a SEFAZ devolve (ex.: 4711-3/02)
     */
    public function __construct(
        public string $numero,
        public array $cnaes,
    ) {}
}
