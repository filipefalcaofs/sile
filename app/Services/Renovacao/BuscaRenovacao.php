<?php

namespace App\Services\Renovacao;

use App\Enums\ViabilityRequestStatus;
use App\Models\ViabilityRequest;
use Illuminate\Database\Eloquent\Builder;

/**
 * Busca única da renovação: CNPJ (14 dígitos) ou número da viabilidade (7
 * dígitos, número do Viabiliza ou processo do sistema anterior). Procura
 * primeiro nas TVLs emitidas pelo Viabiliza e depois no sistema anterior,
 * sem conferir vínculo de quem busca com a empresa.
 */
class BuscaRenovacao
{
    public function __construct(
        private readonly RenovacaoElegibilidadeService $elegibilidade,
        private readonly ProvisoriasLegadasService $legado,
    ) {}

    /**
     * @return array{itens: list<array<string, mixed>>, indisponivel: bool, termo_invalido: bool}
     */
    public function buscar(string $termo): array
    {
        $termo = trim($termo);
        $digitos = preg_replace('/\D/', '', $termo) ?? '';

        if (strlen($digitos) === 14 && preg_match('/^[\d.\/\s-]+$/', $termo) === 1) {
            $legado = $this->legado->porCnpj($digitos);

            return [
                'itens' => [...$this->doViabiliza(fn (Builder $query) => $query->whereHas('company', fn (Builder $empresa) => $empresa->where('cnpj', $digitos))), ...$legado['itens']],
                'indisponivel' => $legado['indisponivel'],
                'termo_invalido' => false,
            ];
        }

        $numero = strtoupper(preg_replace('/\s+/', '', $termo) ?? '');

        if (preg_match('#^[A-Z0-9][A-Z0-9./-]{0,59}$#', $numero) !== 1 || preg_match('/\d/', $numero) !== 1) {
            return ['itens' => [], 'indisponivel' => false, 'termo_invalido' => true];
        }

        $locais = $this->doViabiliza(fn (Builder $query) => $query->whereHas('decision', fn (Builder $decisao) => $decisao->whereRaw('upper(tvl_product_number) = ?', [$numero])));
        $legado = $this->ehNumeroDoLegado($numero) ? $this->legado->porNumero($numero) : ['itens' => [], 'indisponivel' => false];

        return [
            'itens' => [...$locais, ...$legado['itens']],
            'indisponivel' => $legado['indisponivel'],
            'termo_invalido' => false,
        ];
    }

    private function ehNumeroDoLegado(string $numero): bool
    {
        return preg_match('/^\d{7}$/', $numero) === 1 || preg_match('/^\d{10}-\d{8}\/\d{4}$/', $numero) === 1;
    }

    /**
     * @param  callable(Builder<ViabilityRequest>): Builder<ViabilityRequest>  $filtro
     * @return list<array<string, mixed>>
     */
    private function doViabiliza(callable $filtro): array
    {
        $query = ViabilityRequest::query()
            ->where('status', ViabilityRequestStatus::Deferida)
            ->whereHas('decision', fn (Builder $decisao) => $decisao->whereNotNull('tvl_product_number'))
            ->with(['decision', 'company'])
            ->orderBy('id');

        return $filtro($query)
            ->get()
            ->map(function (ViabilityRequest $origem): array {
                $avaliacao = $this->elegibilidade->avaliar($origem);

                return [
                    'origem' => 'viabiliza',
                    'id' => $origem->id,
                    'numero' => (string) $origem->decision?->tvl_product_number,
                    'empresa' => $origem->company?->legal_name,
                    'cnpj' => $origem->company?->formatted_cnpj,
                    'endereco' => collect([$origem->address_street, $origem->address_number, $origem->address_neighborhood])->filter()->implode(', '),
                    'validade' => $origem->decision?->tvl_valid_until?->toDateString(),
                    'situacao' => null,
                    'dias_para_vencer' => null,
                    'tipo' => $origem->decision?->tvl_kind?->label(),
                    'elegivel' => $avaliacao->elegivel,
                    'motivo' => $avaliacao->motivo,
                    'url' => $avaliacao->elegivel ? "/portal/renovacao/{$origem->id}" : null,
                ];
            })
            ->values()
            ->all();
    }
}
