<?php

namespace Tests\Feature\Scheduling;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class ReprocessarEtapasAgendamentoTest extends TestCase
{
    public function test_o_reprocessamento_das_etapas_roda_a_cada_quinze_minutos_sem_sobrepor(): void
    {
        $evento = collect(app(Schedule::class)->events())
            ->first(fn (Event $e): bool => str_contains((string) $e->command, 'processos:reprocessar-etapas'));

        $this->assertNotNull($evento, 'Comando não agendado.');
        $this->assertSame('*/15 * * * *', $evento->expression);
        $this->assertTrue($evento->withoutOverlapping);
        $this->assertTrue($evento->onOneServer);
    }
}
