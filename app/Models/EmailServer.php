<?php

namespace App\Models;

use Database\Factories\EmailServerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Servidor de e-mail administrável (HU-014 / parametrização — ConfigEmail).
 *
 * A `password` SMTP é SEMPRE criptografada no banco (RN-009) e NUNCA reexibida
 * em claro — a interface usa `masked_password`. A invariante "um só servidor
 * padrão" é garantida por `setAsDefault()` em transação. A ponte de runtime
 * (MailConfigServiceProvider) aplica o servidor padrão ativo em `config('mail.*')`
 * — sem servidor cadastrado, mantém o mailer do `.env` (degradação segura).
 */
#[Fillable(['name', 'driver', 'host', 'port', 'encryption', 'timeout', 'username', 'password', 'from_address', 'from_name', 'active', 'is_default'])]
class EmailServer extends Model
{
    /** @use HasFactory<EmailServerFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'port' => 'integer',
            'timeout' => 'integer',
            'active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    /**
     * Senha SMTP cifrada no banco e legível em claro apenas internamente (RN-009).
     *
     * @return Attribute<string|null, string|null>
     */
    protected function password(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value !== null ? Crypt::decryptString($value) : null,
            set: fn (?string $value) => $value !== null && $value !== '' ? Crypt::encryptString($value) : null,
        );
    }

    /**
     * Representação mascarada da senha para exibição (nunca o miolo):
     * primeiros 4 + reticências + últimos 4. Senha ausente devolve null.
     *
     * @return Attribute<string|null, never>
     */
    protected function maskedPassword(): Attribute
    {
        return Attribute::get(function (): ?string {
            $password = $this->password;

            if ($password === null || $password === '') {
                return null;
            }

            if (mb_strlen($password) <= 8) {
                return str_repeat('•', mb_strlen($password));
            }

            return mb_substr($password, 0, 4).'…'.mb_substr($password, -4);
        });
    }

    /**
     * Define este servidor como padrão, desmarcando o anterior — em transação
     * (invariante "um só padrão").
     */
    public function setAsDefault(): void
    {
        DB::transaction(function (): void {
            static::query()
                ->whereKeyNot($this->getKey())
                ->update(['is_default' => false]);

            $this->forceFill(['is_default' => true])->save();
        });
    }
}
