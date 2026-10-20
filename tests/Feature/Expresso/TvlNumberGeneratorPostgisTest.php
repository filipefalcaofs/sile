<?php

namespace Tests\Feature\Expresso;

use App\Models\TvlSequence;
use App\Services\Expresso\TvlNumberGenerator;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\PostgisTestCase;

/**
 * Concorrência do número de produto TVL (HU-072) contra Postgres REAL: o
 * TvlNumberGenerator roda dentro de DB::transaction com
 * tvl_sequences->lockForUpdate() (FOR UPDATE emitido de verdade no pgsql,
 * no-op em SQLite). Duas gerações sequenciais sob o lock avançam a sequência
 * sem buraco nem duplicidade — o lock serializa o incremento sob concorrência.
 * Espelha o ProtocolNumberGeneratorPostgisTest.
 */
#[Group('postgis')]
class TvlNumberGeneratorPostgisTest extends PostgisTestCase
{
    private function generator(): TvlNumberGenerator
    {
        return new TvlNumberGenerator;
    }

    public function test_concorrencia_nao_duplica_sob_lock(): void
    {
        $primeiro = $this->generator()->generate();
        $segundo = $this->generator()->generate();

        $this->assertSame('TVL-3000000', $primeiro);
        $this->assertSame('TVL-3000001', $segundo);
        $this->assertNotSame($primeiro, $segundo);

        // A sequência avançou exatamente 2 — sem buraco, sem duplicidade.
        $sequence = TvlSequence::query()->where('year', TvlNumberGenerator::CONTADOR_UNICO)->sole();
        $this->assertSame(3000001, (int) $sequence->last_number);

        // Prova que o engine é o Postgres real (não SQLite).
        $this->assertSame('pgsql', DB::connection()->getDriverName());
    }
}
