<?php

namespace App\Models;

use Database\Factories\ParameterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/**
 * Registry de parâmetros administráveis do SILE (HU-014).
 *
 * `value` é armazenado como string crua e tipado na leitura por typedValue().
 * Quando `sensitive`, o valor é criptografado no banco (RN-009) — por isso
 * `sensitive` fica fora do fillable: a flag é definida apenas em seed/factory,
 * antes de `value`, e nunca muda por interface.
 *
 * Sem trait de auditoria automática: o diff de atributos vazaria valores
 * sensíveis em claro. O histórico (RN-008) é gravado por auditoria explícita
 * no fluxo de gravação.
 */
#[Fillable(['key', 'group', 'type', 'value', 'default_value', 'validation_rules', 'description', 'requires_connection_test'])]
class Parameter extends Model
{
    /** @use HasFactory<ParameterFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'validation_rules' => 'array',
            'sensitive' => 'boolean',
            'requires_connection_test' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        $forget = fn (self $parameter) => Cache::forget("sile.parameters.{$parameter->key}");

        static::saved($forget);
        static::deleted($forget);
    }

    /**
     * Criptografia condicional: apenas parâmetros sensíveis são cifrados.
     *
     * @return Attribute<string|null, string|null>
     */
    protected function value(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value !== null && $this->sensitive
                ? Crypt::decryptString($value)
                : $value,
            set: fn (?string $value) => $value !== null && $this->sensitive
                ? Crypt::encryptString($value)
                : $value,
        );
    }

    /**
     * Valor administrado (ou default do catálogo) convertido para o tipo declarado.
     */
    public function typedValue(): mixed
    {
        $raw = $this->value ?? $this->default_value;

        if ($raw === null) {
            return null;
        }

        return match ($this->type) {
            'integer' => (int) $raw,
            'decimal' => (float) $raw,
            'boolean' => filter_var($raw, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($raw, true),
            default => $raw,
        };
    }
}
