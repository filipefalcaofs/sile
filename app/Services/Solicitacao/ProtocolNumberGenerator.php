<?php

namespace App\Services\Solicitacao;

use App\Models\ProtocolSequence;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;

/**
 * Gera o número de protocolo {prefixo}-{AAAA}-{NNNNNN} (HU-068) de forma
 * concorrência-segura: o contador por ano (protocol_sequences) é travado com
 * lockForUpdate dentro de uma transação, então incrementado — duas gerações
 * concorrentes serializam no Postgres real (no-op em SQLite; a unicidade lógica
 * roda na suíte SQLite e a concorrência em @group postgis). A defesa final é o
 * unique(protocol_number); o gerador NÃO grava em viability_requests — só
 * sequencia. O caller (08-10) usa o número dentro da transação de protocolo.
 *
 * Formato parametrizável (HU-014) com DEFAULT INLINE (não depende do banco nem
 * do seeder do 08-02): solicitacao.protocolo.prefixo (VIA) e .padding (6).
 */
class ProtocolNumberGenerator
{
    public function generate(?int $year = null): string
    {
        $year ??= (int) now()->year;

        $number = DB::transaction(function () use ($year): int {
            $sequence = ProtocolSequence::query()
                ->where('year', $year)
                ->lockForUpdate()
                ->first()
                ?? ProtocolSequence::query()->create(['year' => $year, 'last_number' => 0]);

            $sequence->increment('last_number');

            return (int) $sequence->last_number;
        });

        $prefix = (string) Settings::get('solicitacao.protocolo.prefixo', config('sile.solicitacao.protocolo.prefixo', 'VIA'));
        $padding = (int) Settings::get('solicitacao.protocolo.padding', config('sile.solicitacao.protocolo.padding', 6));

        return sprintf('%s-%d-%s', $prefix, $year, str_pad((string) $number, $padding, '0', STR_PAD_LEFT));
    }
}
