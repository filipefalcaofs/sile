<?php

namespace App\Services\Risco;

/**
 * Catálogo parametrizável dos valores de tipo de imóvel que o REGIN pode
 * enviar. Os três que dirigem regra (galpão, container, edificação residencial)
 * vieram da SEDUR em 2026-08-31; o ramo comum inicial replica grafias dos
 * protocolos. Trocar o catálogo não muda o normalizador.
 *
 * @phpstan-type AliasMap array<string, list<string>>
 */
final readonly class TipoImovelCatalog
{
    /**
     * @param  AliasMap  $dirigemRegra
     * @param  AliasMap  $ramoComum
     */
    public function __construct(
        public array $dirigemRegra,
        public array $ramoComum,
    ) {}

    public static function sedur200826(): self
    {
        return new self(
            dirigemRegra: [
                'galpao' => ['galpao'],
                'container' => ['container'],
                'edificacao_residencial' => ['edificacao residencial'],
            ],
            ramoComum: [
                'edificacao_comercial' => ['edificacao comercial'],
                'sala' => ['sala'],
            ],
        );
    }

    public function codigoQueDirige(string $normalized): ?string
    {
        return $this->match($this->dirigemRegra, $normalized);
    }

    public function codigoRamoComum(string $normalized): ?string
    {
        return $this->match($this->ramoComum, $normalized);
    }

    /**
     * @param  AliasMap  $mapa
     */
    private function match(array $mapa, string $normalized): ?string
    {
        foreach ($mapa as $codigo => $aliases) {
            $formas = [...$aliases, str_replace('_', ' ', $codigo), $codigo];

            if (in_array($normalized, $formas, true)) {
                return $codigo;
            }
        }

        return null;
    }
}
