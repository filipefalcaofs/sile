<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shape de apresentação da explicabilidade passo a passo da decisão (HU-099):
 * envolve o array projetado pelo DecisionExplanationService (NUNCA a decisão
 * crua) e o normaliza para a UI da retaguarda (12-10). Expõe `por_cnae` (lista
 * de passos ordenados, cada um com a flag `registrado`), `fundamentacao`,
 * `rules_versions`, `legado` (bool) e o `desfecho`.
 *
 * SOMENTE LEITURA e PROJEÇÃO: este recurso não recomputa nada — apresenta o que
 * o serviço leu do registro da decisão. As listas viram sempre arrays (SSR não
 * pode quebrar com shape associativo legado). Consumido como prop do Inertia, o
 * controller chama ->resolve() para entregar o array puro.
 *
 * @property array<string, mixed> $resource
 */
class DecisionExplanationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $payload = (array) $this->resource;

        return [
            'legado' => (bool) ($payload['legado'] ?? false),
            'desfecho' => $payload['desfecho'] ?? null,
            'por_cnae' => array_values(array_map(
                fn (mixed $cnae): array => $this->cnae(is_array($cnae) ? $cnae : []),
                (array) ($payload['por_cnae'] ?? []),
            )),
            'fundamentacao' => array_values((array) ($payload['fundamentacao'] ?? [])),
            'rules_versions' => (array) ($payload['rules_versions'] ?? []),
        ];
    }

    /**
     * @param  array<string, mixed>  $cnae
     * @return array<string, mixed>
     */
    private function cnae(array $cnae): array
    {
        return [
            'cnae' => $cnae['cnae'] ?? null,
            'cnae_formatado' => $cnae['cnae_formatado'] ?? null,
            'is_primary' => (bool) ($cnae['is_primary'] ?? false),
            'origem' => $cnae['origem'] ?? null,
            'passos' => array_values(array_map(
                fn (mixed $passo): array => $this->passo(is_array($passo) ? $passo : []),
                (array) ($cnae['passos'] ?? []),
            )),
        ];
    }

    /**
     * @param  array<string, mixed>  $passo
     * @return array<string, mixed>
     */
    private function passo(array $passo): array
    {
        $apresentado = [
            'passo' => $passo['passo'] ?? null,
            'titulo' => $passo['titulo'] ?? null,
            'registrado' => (bool) ($passo['registrado'] ?? false),
            'entrada' => $passo['entrada'] ?? null,
            'resultado_parcial' => $passo['resultado_parcial'] ?? null,
            'motivo' => $passo['motivo'] ?? null,
            'versao_regra' => $passo['versao_regra'] ?? null,
        ];

        if (array_key_exists('fundamentacao', $passo)) {
            $apresentado['fundamentacao'] = array_values((array) $passo['fundamentacao']);
        }

        return $apresentado;
    }
}
