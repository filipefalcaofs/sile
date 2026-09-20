<?php

namespace App\Models;

use App\Concerns\HasAuditoria;
use Database\Factories\TllValorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Valor da TLL (Taxa de Licença de Localização) por código e exercício (HU-071,
 * HU-014) — dado versionado/auditado (RN-002 via HasAuditoria). O código TLL
 * liga ao enquadramento da planilha vigente; o valor monetário, a taxa de
 * serviço e o mapeamento SEFAZ alimentam o cálculo do DAM (RN-004) e o bloco
 * `taxas` enviado à SEFAZ. active permite inativar sem excluir (histórico).
 */
#[Fillable([
    'codigo_tll',
    'exercicio',
    'valor',
    'taxa_servico',
    'codigo_tll_sefaz',
    'codigo_servico_sefaz',
    'servico_sefaz',
    'active',
])]
class TllValor extends Model
{
    use HasAuditoria;

    /** @use HasFactory<TllValorFactory> */
    use HasFactory;

    protected $table = 'tll_valores';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'exercicio' => 'integer',
            'valor' => 'decimal:2',
            'taxa_servico' => 'decimal:2',
            'active' => 'boolean',
        ];
    }

    /**
     * Apenas os valores ativos — o que o cálculo do DAM efetivamente usa.
     *
     * @param  Builder<TllValor>  $query
     * @return Builder<TllValor>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    /**
     * Valor vigente de um código TLL num exercício (ativo). Null quando não
     * parametrizado — degradação honesta, nunca valor inventado.
     *
     * @param  Builder<TllValor>  $query
     * @return Builder<TllValor>
     */
    public function scopeParaExercicio(Builder $query, string $codigoTll, int $exercicio): Builder
    {
        return $query->where('codigo_tll', $codigoTll)->where('exercicio', $exercicio);
    }
}
