<?php

namespace App\Models;

use App\Enums\RuleDomain;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CNAE autorizado num anexo do Decreto 35.062/2021 numa versão de regra
 * (RN-EV-05/07). Fonte: endpoint SEDUR (import versionado). Anexo A vale
 * para a SEDE, Anexo B para o ABRIGADO; `permitidoNoAnexo()` consulta a
 * versão VIGENTE do domínio, normalizando o código a dígitos.
 */
#[Fillable(['rule_version_id', 'anexo', 'cnae_code', 'cnae_description'])]
class VirtualOfficeActivityCnae extends Model
{
    public const ANEXO_A = 'A';

    public const ANEXO_B = 'B';

    /**
     * @return BelongsTo<RuleVersion, $this>
     */
    public function ruleVersion(): BelongsTo
    {
        return $this->belongsTo(RuleVersion::class);
    }

    /**
     * O CNAE consta do anexo informado na versao VIGENTE do dominio.
     * Anexo A = atividades da SEDE; Anexo B = atividades do ABRIGADO.
     */
    public static function permitidoNoAnexo(string $cnaeCode, string $anexo): bool
    {
        $digitos = preg_replace('/\D/', '', $cnaeCode) ?? '';

        $vigente = RuleVersion::vigente(RuleDomain::AtividadesEscritorioVirtual)->first();

        if ($vigente === null) {
            return false;
        }

        return static::query()
            ->where('rule_version_id', $vigente->id)
            ->where('anexo', $anexo)
            ->where('cnae_code', $digitos)
            ->exists();
    }

    /**
     * Semantica historica: "permitido em escritorio virtual" e a pergunta do
     * ABRIGADO (Anexo B). Preservado para os call sites existentes.
     */
    public static function permitido(string $cnaeCode): bool
    {
        return static::permitidoNoAnexo($cnaeCode, self::ANEXO_B);
    }
}
