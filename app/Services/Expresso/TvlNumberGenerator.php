<?php

namespace App\Services\Expresso;

use App\Models\TvlSequence;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;

/**
 * Gera o número de produto TVL {prefixo}-{N} (HU-072 RN-007), com N sequencial
 * único e SEM reinício anual, a partir de viabiliza.expresso.tvl.inicio (3000000). O
 * N é o mesmo número que segue à SEFAZ (ViabilityDecision::numeroSefaz), então
 * não pode repetir entre anos. O contador (tvl_sequences, linha CONTADOR_UNICO)
 * é travado com lockForUpdate dentro de uma transação, então incrementado — duas
 * gerações concorrentes serializam no Postgres real (no-op em SQLite; a
 * unicidade lógica roda na suíte SQLite e a concorrência em @group postgis). A
 * defesa final é o unique(tvl_product_number); o gerador NÃO grava em
 * viability_decisions — só sequencia. O caller usa o número dentro da transação
 * da decisão, no deferimento.
 *
 * Prefixo parametrizável (HU-014) com DEFAULT INLINE (não depende do banco nem
 * do seeder): expresso.tvl.prefixo (TVL) e .padding (6, sem efeito acima de 6
 * dígitos).
 *
 * Espelha o ProtocolNumberGenerator.
 */
class TvlNumberGenerator
{
    /** Chave (coluna year) da linha única do contador, que não reinicia por ano. */
    public const CONTADOR_UNICO = 0;

    public function generate(): string
    {
        $number = DB::transaction(function (): int {
            $sequence = TvlSequence::query()
                ->where('year', self::CONTADOR_UNICO)
                ->lockForUpdate()
                ->first()
                ?? TvlSequence::query()->create([
                    'year' => self::CONTADOR_UNICO,
                    'last_number' => self::numeroInicial() - 1,
                ]);

            $sequence->increment('last_number');

            return (int) $sequence->last_number;
        });

        return $this->format($number);
    }

    public function format(int $number): string
    {
        $prefix = (string) Settings::get('expresso.tvl.prefixo', config('viabiliza.expresso.tvl.prefixo', 'TVL'));
        $padding = (int) Settings::get('expresso.tvl.padding', config('viabiliza.expresso.tvl.padding', 6));

        return sprintf('%s-%s', $prefix, str_pad((string) $number, $padding, '0', STR_PAD_LEFT));
    }

    public static function numeroInicial(): int
    {
        return (int) config('viabiliza.expresso.tvl.inicio', 3_000_000);
    }
}
