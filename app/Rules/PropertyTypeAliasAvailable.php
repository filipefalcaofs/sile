<?php

namespace App\Rules;

use App\Services\Risco\TipoImovel;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

/**
 * Verifica se uma grafia alternativa não está em uso por outro tipo de imóvel.
 * A normalização aplica as mesmas regras do motor (TipoImovel::normalize) para
 * garantir que o match em produção seja idêntico ao check de unicidade aqui.
 */
class PropertyTypeAliasAvailable implements ValidationRule
{
    public function __construct(private readonly ?int $ignoreTypeId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $normalized = TipoImovel::normalize((string) $value);

        $query = DB::table('property_type_aliases')->where('alias', $normalized);

        if ($this->ignoreTypeId !== null) {
            $query->where('property_type_id', '!=', $this->ignoreTypeId);
        }

        if ($query->exists()) {
            $fail('A grafia ":input" já está em uso por outro tipo de imóvel.');
        }
    }
}
