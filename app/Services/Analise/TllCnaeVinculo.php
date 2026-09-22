<?php

namespace App\Services\Analise;

use App\Enums\RuleDomain;
use App\Models\RuleVersion;
use App\Models\TllValor;
use App\Models\TratamentoEnquadramento;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Lê o vínculo CNAE → TLL da planilha vigente (treatment_enquadramentos).
 * Não grava nada: a fonte oficial é a planilha 20.08.26 versionada.
 */
class TllCnaeVinculo
{
    public function vigenteId(): ?int
    {
        $id = RuleVersion::query()->vigente(RuleDomain::RiscoTratamento)->value('id');

        return $id === null ? null : (int) $id;
    }

    /**
     * @param  Collection<int, TllValor>|iterable<TllValor>  $valores
     * @return array<int, int>
     */
    public function contarPara(iterable $valores): array
    {
        $contagens = [];

        foreach ($valores as $valor) {
            $contagens[$valor->id] = 0;
        }

        $vigenteId = $this->vigenteId();

        if ($vigenteId === null || $contagens === []) {
            return $contagens;
        }

        $codigos = collect($valores)->pluck('codigo_tll')->unique()->values();
        $precisaIsenta = collect($valores)->contains(
            fn (TllValor $valor): bool => $this->eIsenta($valor->codigo_tll, (string) $valor->especificacao),
        );

        $linhas = TratamentoEnquadramento::query()
            ->where('rule_version_id', $vigenteId)
            ->where(function (Builder $query) use ($codigos, $precisaIsenta): void {
                $query->whereIn('codigo_tll', $codigos);

                if ($precisaIsenta) {
                    $query->orWhere('codigo_tll', '0.00');
                }
            })
            ->get(['cnae', 'codigo_tll', 'especificacao_tll']);

        foreach ($valores as $valor) {
            $contagens[$valor->id] = $linhas
                ->filter(fn (TratamentoEnquadramento $linha): bool => $this->casa($valor, $linha))
                ->unique('cnae')
                ->count();
        }

        return $contagens;
    }

    /**
     * @return LengthAwarePaginator<int, TratamentoEnquadramento>
     */
    public function listar(TllValor $valor, int $perPage = 25): LengthAwarePaginator
    {
        $vigenteId = $this->vigenteId();

        return TratamentoEnquadramento::query()
            ->select('cnae')
            ->selectRaw('min(denominacao) as denominacao')
            ->when(
                $vigenteId === null,
                fn (Builder $query) => $query->whereRaw('0 = 1'),
                function (Builder $query) use ($vigenteId, $valor): void {
                    $query->where('rule_version_id', $vigenteId);
                    $this->aplicarCasa($query, $valor);
                },
            )
            ->groupBy('cnae')
            ->orderBy('cnae')
            ->paginate($perPage);
    }

    /**
     * @param  Builder<TllValor>  $query
     */
    public function restringirBusca(Builder $query, string $term): void
    {
        $term = trim($term);

        if ($term === '') {
            return;
        }

        $query->where(function (Builder $outer) use ($term): void {
            $outer->whereLike('codigo_tll', "%{$term}%", caseSensitive: false)
                ->orWhereLike('especificacao', "%{$term}%", caseSensitive: false)
                ->orWhereLike('codigo_tll_sefaz', "%{$term}%", caseSensitive: false)
                ->orWhereLike('servico_sefaz', "%{$term}%", caseSensitive: false);

            $vigenteId = $this->vigenteId();

            if ($vigenteId === null) {
                return;
            }

            $variantes = $this->variantesCnae($term);

            $outer->orWhereExists(function ($exists) use ($vigenteId, $variantes): void {
                $exists->selectRaw('1')
                    ->from('treatment_enquadramentos')
                    ->whereColumn('treatment_enquadramentos.codigo_tll', 'tll_valores.codigo_tll')
                    ->where('treatment_enquadramentos.rule_version_id', $vigenteId)
                    ->where(function ($cnae) use ($variantes): void {
                        foreach ($variantes as $variante) {
                            $cnae->orWhere('cnae', 'like', '%'.$variante.'%');
                        }
                    });
            });
        });
    }

    /**
     * @return list<string>
     */
    public function variantesCnae(string $term): array
    {
        $variantes = [$term];
        $digitos = preg_replace('/\D/', '', $term) ?? '';

        if (strlen($digitos) === 7) {
            $variantes[] = $digitos;
            $variantes[] = substr($digitos, 0, 4).'-'.substr($digitos, 4, 1).'/'.substr($digitos, 5, 2);
        }

        return array_values(array_unique($variantes));
    }

    private function eIsenta(string $codigo, string $especificacao): bool
    {
        return $codigo === '0.00' || str_contains(mb_strtoupper($especificacao), 'ISENTA');
    }

    private function casa(TllValor $valor, TratamentoEnquadramento $linha): bool
    {
        $tllIsenta = $this->eIsenta($valor->codigo_tll, (string) $valor->especificacao);
        $linhaIsenta = $this->eIsenta((string) $linha->codigo_tll, (string) $linha->especificacao_tll);

        if ($tllIsenta) {
            return $linhaIsenta;
        }

        return $linha->codigo_tll === $valor->codigo_tll && ! $linhaIsenta;
    }

    /**
     * @param  Builder<TratamentoEnquadramento>  $query
     */
    private function aplicarCasa(Builder $query, TllValor $valor): void
    {
        if ($this->eIsenta($valor->codigo_tll, (string) $valor->especificacao)) {
            $query->where(function (Builder $inner): void {
                $inner->where('codigo_tll', '0.00')
                    ->orWhere(function (Builder $isenta): void {
                        $isenta->where('codigo_tll', '6.00')
                            ->whereRaw("upper(coalesce(especificacao_tll, '')) like '%ISENTA%'");
                    });
            });

            return;
        }

        $query->where('codigo_tll', $valor->codigo_tll)
            ->where('codigo_tll', '!=', '0.00')
            ->whereRaw("upper(coalesce(especificacao_tll, '')) not like '%ISENTA%'");
    }
}
