<?php

namespace App\Models;

use App\Concerns\HasAuditoria;
use Database\Factories\ServicoNomenclaturaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * De x Para do nome de um serviço: o evento como a JUCEB envia (de) e o nome
 * exibido no Viabiliza (para), parametrizado pela SEDUR. `exibir` decide se o
 * serviço aparece quando o processo traz mais de um evento. A chave é o `de`
 * normalizado, para não duplicar por caixa, acento ou espaço.
 */
#[Fillable(['de', 'para', 'exibir'])]
class ServicoNomenclatura extends Model
{
    use HasAuditoria;

    /** @use HasFactory<ServicoNomenclaturaFactory> */
    use HasFactory;

    protected $table = 'servico_nomenclaturas';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'exibir' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (ServicoNomenclatura $linha): void {
            $linha->chave = self::chaveDe((string) $linha->de);
        });
    }

    public static function chaveDe(string $nome): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', mb_strtolower(Str::ascii($nome))));
    }

    /**
     * Linha do evento; cria com De = Para e oculta quando o evento é novo. Se
     * outro recebimento criou a mesma linha ao mesmo tempo, devolve a dele em
     * vez de falhar o protocolo (savepoint para não abortar a transação aberta).
     */
    public static function garantir(string $nome): self
    {
        $nome = trim($nome);
        $chave = self::chaveDe($nome);
        $existente = self::query()->where('chave', $chave)->first();

        if ($existente !== null) {
            return $existente;
        }

        try {
            return DB::transaction(fn (): self => self::create(['de' => $nome, 'para' => $nome, 'exibir' => false]));
        } catch (UniqueConstraintViolationException) {
            return self::query()->where('chave', $chave)->firstOrFail();
        }
    }
}
