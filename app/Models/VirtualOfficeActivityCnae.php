<?php

namespace App\Models;

use App\Enums\RuleDomain;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CNAE autorizado para ABRIGADO de escritório virtual numa versão de regra
 * (RN-EV-05/07). Fonte: endpoint SEDUR (import versionado). `permitido()`
 * consulta a versão VIGENTE do domínio, normalizando o código a dígitos.
 */
#[Fillable(['rule_version_id', 'cnae_code', 'cnae_description'])]
class VirtualOfficeActivityCnae extends Model
{
    /**
     * @return BelongsTo<RuleVersion, $this>
     */
    public function ruleVersion(): BelongsTo
    {
        return $this->belongsTo(RuleVersion::class);
    }

    public static function permitido(string $cnaeCode): bool
    {
        $digitos = preg_replace('/\D/', '', $cnaeCode) ?? '';

        $vigente = RuleVersion::vigente(RuleDomain::AtividadesEscritorioVirtual)->first();

        if ($vigente === null) {
            return false;
        }

        return static::query()
            ->where('rule_version_id', $vigente->id)
            ->where('cnae_code', $digitos)
            ->exists();
    }
}
