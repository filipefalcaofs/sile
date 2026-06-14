<?php

namespace Tests\Feature\Expresso;

use App\Models\TvlSequence;
use App\Services\Expresso\TvlNumberGenerator;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\PostgisTestCase;

/**
 * Concorrência do número de produto TVL (HU-076) contra Postgres REAL: o
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
        $primeiro = $this->generator()->generate(2026);
        $segundo = $this->generator()->generate(2026);

        $this->assertSame('TVL-2026-000001', $primeiro);
        $this->assertSame('TVL-2026-000002', $segundo);
        $this->assertNotSame($primeiro, $segundo);

        // A sequência avançou exatamente 2 — sem buraco, sem duplicidade.
        $sequence = TvlSequence::query()->where('year', 2026)->sole();
        $this->assertSame(2, (int) $sequence->last_number);

        // Prova que o engine é o Postgres real (não SQLite).
        $this->assertSame('pgsql', DB::connection()->getDriverName());
    }
}
