<?php

namespace App\Services\Risco;

use App\Models\PropertyType;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;

/**
 * Catálogo parametrizável dos valores de tipo de imóvel que o REGIN pode
 * enviar. O catálogo oficial vem do banco (administrável sem deploy); o
 * sedur200826() embutido é fallback de emergência (banco inalcançável) e
 * fixture de testes unitários. Trocar o catálogo não muda o normalizador.
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

    /**
     * Catálogo vigente: banco (cadastro administrável) com cache invalidado
     * na escrita dos models — efeito sem deploy, padrão Settings. O fallback
     * sedur200826() só vale com o banco INALCANÇÁVEL (build Docker, CI, testes
     * Unit); banco alcançável e vazio degrada tudo para análise (honesto).
     */
    public static function vigente(): self
    {
        try {
            return Cache::remember(
                PropertyType::CACHE_KEY,
                (int) config('sile.parameters.cache_ttl', 300),
                function () {
                    $dirigemRegra = [];
                    $ramoComum = [];

                    foreach (PropertyType::query()->active()->with('aliases')->get() as $tipo) {
                        $mapa = $tipo->drives_rule ? 'dirigemRegra' : 'ramoComum';
                        ${$mapa}[$tipo->code] = $tipo->aliases->pluck('alias')->all();
                    }

                    return new self(dirigemRegra: $dirigemRegra, ramoComum: $ramoComum);
                },
            );
        } catch (QueryException|\Exception) {
            return self::sedur200826();
        }
    }

    /**
     * Catálogo embutido SEDUR 2026-08-26 — fallback de emergência quando o
     * banco está inalcançável e fixture de testes unitários (in-memory, sem DB).
     */
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
