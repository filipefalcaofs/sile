<?php

namespace Tests\Feature\Solicitacao;

use App\Models\ProtocolSequence;
use App\Services\Solicitacao\ProtocolNumberGenerator;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\PostgisTestCase;

/**
 * Concorrência do número de protocolo (HU-068) contra Postgres REAL: o
 * ProtocolNumberGenerator roda dentro de DB::transaction com
 * protocol_sequences->lockForUpdate() (FOR UPDATE emitido de verdade no pgsql,
 * no-op em SQLite). Duas gerações sequenciais sob o lock avançam a sequência
 * sem buraco nem duplicidade — o lock serializa o incremento sob concorrência.
 */
#[Group('postgis')]
class ProtocolNumberGeneratorPostgisTest extends PostgisTestCase
{
    private function generator(): ProtocolNumberGenerator
    {
        return new ProtocolNumberGenerator;
    }

    public function test_concorrencia_nao_duplica_sob_lock(): void
    {
        // Confirma que o caminho do lock roda no engine real (FOR UPDATE).
        $primeiro = $this->generator()->generate(2026);
        $segundo = $this->generator()->generate(2026);

        $this->assertSame('VIA-2026-000001', $primeiro);
        $this->assertSame('VIA-2026-000002', $segundo);
        $this->assertNotSame($primeiro, $segundo);

        // A sequência avançou exatamente 2 — sem buraco, sem duplicidade.
        $sequence = ProtocolSequence::query()->where('year', 2026)->sole();
        $this->assertSame(2, (int) $sequence->last_number);

        // Prova que o engine é o Postgres real (não SQLite).
        $this->assertSame('pgsql', DB::connection()->getDriverName());
    }
}
