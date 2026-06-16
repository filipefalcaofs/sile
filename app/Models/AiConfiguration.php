<?php

namespace App\Models;

use Database\Factories\AiConfigurationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Configuração de provedor de IA administrável (Fase 14, Onda 0 — HU-014 aplicada à IA).
 *
 * A `api_key` é SEMPRE criptografada no banco (RN-009, mesmo mecanismo do
 * Parameter sensível) e NUNCA reexibida em claro pela interface — a exibição usa
 * `masked_api_key`. A invariante "uma configuração padrão por capacidade" é
 * garantida por `setAsDefault()` em transação (o fluxo de gravação a invoca, em
 * vez de gravar `is_default` solto).
 */
#[Fillable(['name', 'provider', 'capability', 'base_url', 'api_key', 'model', 'temperature', 'max_tokens', 'timeout_ms', 'active', 'is_default'])]
class AiConfiguration extends Model
{
    /** @use HasFactory<AiConfigurationFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'temperature' => 'decimal:2',
            'max_tokens' => 'integer',
            'timeout_ms' => 'integer',
            'active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    /**
     * Credencial cifrada no banco e legível em claro apenas internamente (RN-009).
     *
     * @return Attribute<string|null, string|null>
     */
    protected function apiKey(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value !== null ? Crypt::decryptString($value) : null,
            set: fn (?string $value) => $value !== null && $value !== '' ? Crypt::encryptString($value) : null,
        );
    }

    /**
     * Representação mascarada da credencial para exibição (nunca o miolo):
     * primeiros 4 + reticências + últimos 4. Credencial ausente devolve null.
     *
     * @return Attribute<string|null, never>
     */
    protected function maskedApiKey(): Attribute
    {
        return Attribute::get(function (): ?string {
            $key = $this->api_key;

            if ($key === null || $key === '') {
                return null;
            }

            if (mb_strlen($key) <= 8) {
                return str_repeat('•', mb_strlen($key));
            }

            return mb_substr($key, 0, 4).'…'.mb_substr($key, -4);
        });
    }

    /**
     * Define esta configuração como padrão da sua capacidade, desmarcando a
     * anterior na mesma capacidade — em transação (invariante "uma só padrão").
     */
    public function setAsDefault(): void
    {
        DB::transaction(function (): void {
            static::query()
                ->where('capability', $this->capability)
                ->whereKeyNot($this->getKey())
                ->update(['is_default' => false]);

            $this->forceFill(['is_default' => true])->save();
        });
    }
}
