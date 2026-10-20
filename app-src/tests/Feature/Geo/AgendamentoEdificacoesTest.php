<?php

namespace Tests\Feature\Geo;

use App\Enums\FonteEdificacao;
use App\Models\BuildingFootprintImport;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class AgendamentoEdificacoesTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_importacoes_agendadas_dependem_do_interruptor(): void
    {
        $eventos = $this->eventos();

        $this->assertCount(2, $eventos);

        config(['viabiliza.features.edificacoes_importacao_agendada' => false]);
        $this->assertTrue($eventos->every(fn (Event $evento): bool => ! $evento->filtersPass($this->app)));

        config(['viabiliza.features.edificacoes_importacao_agendada' => true]);
        cache()->flush();
        $this->assertTrue($eventos->every(fn (Event $evento): bool => $evento->filtersPass($this->app)));
    }

    public function test_verificacao_e_diaria_de_madrugada(): void
    {
        $this->assertSame('0 3 * * *', $this->evento('overture')->expression);
        $this->assertSame('30 3 * * *', $this->evento('microsoft')->expression);
    }

    public function test_fonte_com_carga_concluida_recente_nao_roda(): void
    {
        $this->ligarAgendamento();
        BuildingFootprintImport::factory()->create(['source' => FonteEdificacao::Overture, 'started_at' => now()->subDays(10)]);

        $this->assertFalse($this->evento('overture')->filtersPass($this->app));
        $this->assertTrue($this->evento('microsoft')->filtersPass($this->app));
    }

    public function test_carga_ignorada_recente_conta_como_em_dia(): void
    {
        $this->ligarAgendamento();
        BuildingFootprintImport::factory()->create([
            'source' => FonteEdificacao::Microsoft,
            'status' => BuildingFootprintImport::IGNORADA,
            'started_at' => now()->subDays(60),
        ]);

        $this->assertFalse($this->evento('microsoft')->filtersPass($this->app));
    }

    public function test_carga_concluida_antiga_vence_a_fonte(): void
    {
        $this->ligarAgendamento();
        BuildingFootprintImport::factory()->create(['source' => FonteEdificacao::Overture, 'started_at' => now()->subDays(26)]);
        BuildingFootprintImport::factory()->create(['source' => FonteEdificacao::Microsoft, 'started_at' => now()->subDays(86)]);

        $this->assertTrue($this->evento('overture')->filtersPass($this->app));
        $this->assertTrue($this->evento('microsoft')->filtersPass($this->app));
    }

    public function test_carga_que_falhou_ou_morreu_nao_impede_nova_tentativa(): void
    {
        $this->ligarAgendamento();
        BuildingFootprintImport::factory()->create([
            'source' => FonteEdificacao::Overture,
            'status' => BuildingFootprintImport::FALHOU,
            'started_at' => now()->subDay(),
        ]);
        BuildingFootprintImport::factory()->create([
            'source' => FonteEdificacao::Overture,
            'status' => BuildingFootprintImport::EM_ANDAMENTO,
            'started_at' => now()->subDays(2),
            'finished_at' => null,
        ]);

        $this->assertTrue($this->evento('overture')->filtersPass($this->app));
    }

    private function ligarAgendamento(): void
    {
        config(['viabiliza.features.edificacoes_importacao_agendada' => true]);
        cache()->flush();
    }

    /**
     * @return Collection<int, Event>
     */
    private function eventos(): Collection
    {
        return collect(app(Schedule::class)->events())
            ->filter(fn (Event $evento): bool => str_contains((string) $evento->command, 'edificacoes') || str_contains((string) $evento->command, 'overture-salvador'))
            ->values();
    }

    private function evento(string $fonte): Event
    {
        return $this->eventos()->firstOrFail(fn (Event $evento): bool => str_contains((string) $evento->command, $fonte));
    }
}
