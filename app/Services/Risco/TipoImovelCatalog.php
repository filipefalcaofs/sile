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
     * na escrita dos models — efeito sem deploy, padrão Settings. O cache
     * guarda só array: `serializable_classes=false` no store database recusa
     * objeto PHP e devolveria __PHP_Incomplete_Class (500 no Portainer).
     * O fallback sedur200826() só vale com o banco INALCANÇÁVEL (build Docker,
     * CI, testes Unit); banco alcançável e vazio degrada tudo para análise.
     */
    public static function vigente(): self
    {
        try {
            $ttl = (int) config('sile.parameters.cache_ttl', 300);
            $payload = Cache::remember(PropertyType::CACHE_KEY, $ttl, fn (): array => self::payloadDoBanco());

            if (! self::payloadValido($payload)) {
                Cache::forget(PropertyType::CACHE_KEY);
                $payload = self::payloadDoBanco();
                Cache::put(PropertyType::CACHE_KEY, $payload, $ttl);
            }

            return new self(
                dirigemRegra: $payload['dirigemRegra'],
                ramoComum: $payload['ramoComum'],
            );
        } catch (QueryException|\Exception $e) {
            // O fallback muda o que o motor reconhece (roteamento): precisa
            // ser observável, nunca silencioso.
            report($e);

            return self::sedur200826();
        }
    }

    /**
     * @return array{dirigemRegra: AliasMap, ramoComum: AliasMap}
     */
    private static function payloadDoBanco(): array
    {
        $dirigemRegra = [];
        $ramoComum = [];

        foreach (PropertyType::query()->active()->with('aliases')->get() as $tipo) {
            $mapa = $tipo->drives_rule ? 'dirigemRegra' : 'ramoComum';
            ${$mapa}[$tipo->code] = $tipo->aliases->pluck('alias')->all();
        }

        return [
            'dirigemRegra' => $dirigemRegra,
            'ramoComum' => $ramoComum,
        ];
    }

    private static function payloadValido(mixed $payload): bool
    {
        return is_array($payload)
            && array_key_exists('dirigemRegra', $payload)
            && array_key_exists('ramoComum', $payload)
            && is_array($payload['dirigemRegra'])
            && is_array($payload['ramoComum']);
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
