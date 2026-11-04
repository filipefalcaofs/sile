<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'rule_version_id',
    'cnae',
    'denominacao',
    'risco',
    'regra',
    'codigo_louos',
    'denominacao_louos',
    'subcategoria',
    'grupo',
    'ate_m2',
    'enquadramento2',
    'ate_m2_2',
    'enquadramento3',
    'acima_m2',
    'codigo_tll',
    'especificacao_tll',
    'classificacao',
    'vagas',
    'metadados',
])]
class TratamentoEnquadramento extends Model
{
    protected $table = 'treatment_enquadramentos';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ate_m2' => 'decimal:2',
            'ate_m2_2' => 'decimal:2',
            'acima_m2' => 'decimal:2',
            'vagas' => 'array',
            'metadados' => 'array',
        ];
    }

    /**
     * Subgrupo e grupo que a área utilizada alcança nas faixas da linha
     * (subcategoria até ate_m2, enquadramento2 acima dele, enquadramento3 a
     * partir de acima_m2). `faixa` diz qual das três valeu: a 2 eleva o baixo
     * risco a médio e a 3 leva a alto.
     *
     * @return array{subgrupo: string|null, grupo: string|null, faixa: int}
     */
    public function faixaDaArea(?float $area): array
    {
        if ($area !== null && filled($this->enquadramento3) && $this->acima_m2 !== null && $area >= (float) $this->acima_m2) {
            return ['subgrupo' => $this->enquadramento3, 'grupo' => self::grupoDoSubgrupo($this->enquadramento3), 'faixa' => 3];
        }

        if ($area !== null && filled($this->enquadramento2) && $this->ate_m2 !== null && $area > (float) $this->ate_m2) {
            return ['subgrupo' => $this->enquadramento2, 'grupo' => self::grupoDoSubgrupo($this->enquadramento2), 'faixa' => 2];
        }

        return ['subgrupo' => $this->subcategoria, 'grupo' => $this->grupo, 'faixa' => 1];
    }

    /**
     * Grupo de uso de um subgrupo (nR2-12 → nR2).
     */
    public static function grupoDoSubgrupo(string $subgrupo): string
    {
        $pos = strrpos($subgrupo, '-');

        return $pos === false ? $subgrupo : substr($subgrupo, 0, $pos);
    }

    /**
     * @return BelongsTo<RuleVersion, $this>
     */
    public function ruleVersion(): BelongsTo
    {
        return $this->belongsTo(RuleVersion::class);
    }
}
