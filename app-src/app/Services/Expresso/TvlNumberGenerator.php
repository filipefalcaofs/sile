<?php

namespace App\Services\Expresso;

use App\Models\TvlSequence;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;

/**
 * Gera o número de produto TVL {prefixo}-{AAAA}-{NNNNNN} (HU-076 RN-007) de
 * forma concorrência-segura: o contador por ano (tvl_sequences) é travado com
 * lockForUpdate dentro de uma transação, então incrementado — duas gerações
 * concorrentes serializam no Postgres real (no-op em SQLite; a unicidade lógica
 * roda na suíte SQLite e a concorrência em @group postgis). A defesa final é o
 * unique(tvl_product_number); o gerador NÃO grava em viability_decisions — só
 * sequencia. O caller (09-05) usa o número dentro da transação da decisão, no
 * deferimento.
 *
 * Formato parametrizável (HU-014) com DEFAULT INLINE (não depende do banco nem
 * do seeder do 09-01): expresso.tvl.prefixo (TVL) e .padding (6).
 *
 * Espelha o ProtocolNumberGenerator.
 */
class TvlNumberGenerator
{
    public function generate(?int $year = null): string
    {
        $year ??= (int) now()->year;

        $number = DB::transaction(function () use ($year): int {
            $sequence = TvlSequence::query()
                ->where('year', $year)
                ->lockForUpdate()
                ->first()
                ?? TvlSequence::query()->create(['year' => $year, 'last_number' => 0]);

            $sequence->increment('last_number');

            return (int) $sequence->last_number;
        });

        $prefix = (string) Settings::get('expresso.tvl.prefixo', config('sile.expresso.tvl.prefixo', 'TVL'));
        $padding = (int) Settings::get('expresso.tvl.padding', config('sile.expresso.tvl.padding', 6));

        return sprintf('%s-%d-%s', $prefix, $year, str_pad((string) $number, $padding, '0', STR_PAD_LEFT));
    }
}
