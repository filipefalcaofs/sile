<?php

namespace App\Models;

use App\Concerns\HasAuditoria;
use Database\Factories\TllValorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Valor da TLL (Taxa de Licença de Localização) por código e exercício (HU-071,
 * HU-014) — dado versionado/auditado (RN-002 via HasAuditoria). O código TLL
 * liga ao enquadramento da planilha vigente; o valor monetário, a taxa de
 * serviço e o mapeamento SEFAZ alimentam o cálculo do DAM (RN-004) e o bloco
 * `taxas` enviado à SEFAZ. active permite inativar sem excluir (histórico).
 */
#[Fillable([
    'codigo_tll',
    'especificacao',
    'exercicio',
    'valor',
    'taxa_servico',
    'codigo_tll_sefaz',
    'codigo_servico_sefaz',
    'servico_sefaz',
    'active',
    'rule_version_id',
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

    /**
     * Resolve a linha ativa do exercício. Quando o código tem mais de uma
     * especificação (6.00 ISENTA vs residual), a isenção só entra se o
     * enquadramento pedir `especificacao_tll = ISENTA`. Sem especificação,
     * usa a residual — nunca escolhe a isenta por acaso.
     */
    public static function resolver(string $codigoTll, int $exercicio, ?string $especificacao = null): ?self
    {
        $candidatos = static::query()->active()->paraExercicio($codigoTll, $exercicio)->get();

        if ($candidatos->isEmpty()) {
            return null;
        }

        if (is_string($especificacao) && $especificacao !== '') {
            $exato = $candidatos->firstWhere('especificacao', $especificacao);

            if ($exato instanceof self) {
                return $exato;
            }
        }

        if ($candidatos->count() === 1) {
            return $candidatos->first();
        }

        return $candidatos->first(
            fn (self $linha): bool => $linha->especificacao !== 'ISENTA',
        );
    }

    /**
     * Versão de regra do exercício que gerou esta linha (propagação). Null
     * quando o valor foi cadastrado manualmente pelo CRUD.
     *
     * @return BelongsTo<RuleVersion, $this>
     */
    public function versao(): BelongsTo
    {
        return $this->belongsTo(RuleVersion::class, 'rule_version_id');
    }
}
