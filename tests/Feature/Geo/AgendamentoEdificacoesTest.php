<?php

namespace Tests\Feature\Geo;

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class AgendamentoEdificacoesTest extends TestCase
{
    public function test_importacoes_agendadas_dependem_do_interruptor(): void
    {
        $eventos = collect(app(Schedule::class)->events())
            ->filter(fn ($evento): bool => str_contains((string) $evento->command, 'edificacoes') || str_contains((string) $evento->command, 'overture-salvador'));

        $this->assertCount(2, $eventos);

        config(['viabiliza.features.edificacoes_importacao_agendada' => false]);
        $this->assertTrue($eventos->every(fn ($evento): bool => ! $evento->filtersPass($this->app)));

        config(['viabiliza.features.edificacoes_importacao_agendada' => true]);
        cache()->flush();
        $this->assertTrue($eventos->every(fn ($evento): bool => $evento->filtersPass($this->app)));
    }
}
