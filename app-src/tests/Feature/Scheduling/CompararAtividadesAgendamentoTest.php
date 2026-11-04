<?php

namespace Tests\Feature\Scheduling;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class CompararAtividadesAgendamentoTest extends TestCase
{
    public function test_a_comparacao_das_atividades_roda_de_hora_em_hora_sem_sobrepor(): void
    {
        $evento = collect(app(Schedule::class)->events())
            ->first(fn (Event $e): bool => str_contains((string) $e->command, 'processos:comparar-atividades-anteriores'));

        $this->assertNotNull($evento, 'Comando não agendado.');
        $this->assertSame('0 * * * *', $evento->expression);
        $this->assertTrue($evento->withoutOverlapping);
        $this->assertTrue($evento->onOneServer);
    }
}
