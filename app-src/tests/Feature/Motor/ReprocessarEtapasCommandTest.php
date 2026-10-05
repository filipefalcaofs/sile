<?php

namespace Tests\Feature\Motor;

use App\Enums\EtapaMotor;
use App\Models\ViabilityRequest;
use App\Models\ViabilityRequestEtapa;
use App\Services\Motor\ReprocessarEtapasService;
use App\Services\Motor\ReprocessoResultado;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery;
use Tests\TestCase;

class ReprocessarEtapasCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_elege_so_etapa_indisponivel_vencida_e_nao_esgotada(): void
    {
        $vencida = ViabilityRequestEtapa::factory()->indisponivel()->create([
            'etapa' => EtapaMotor::Zona,
            'proxima_tentativa_em' => now()->subMinute(),
        ]);
        ViabilityRequestEtapa::factory()->indisponivel()->create([
            'etapa' => EtapaMotor::Zona,
            'proxima_tentativa_em' => now()->addHour(),
        ]);
        ViabilityRequestEtapa::factory()->indisponivel()->create([
            'etapa' => EtapaMotor::Zona,
            'proxima_tentativa_em' => null,
            'esgotada_em' => now(),
        ]);

        $servico = Mockery::mock(ReprocessarEtapasService::class);
        $servico->shouldReceive('reprocessar')
            ->once()
            ->withArgs(fn (ViabilityRequest $p) => $p->id === $vencida->viability_request_id)
            ->andReturn(new ReprocessoResultado(true, null, 'em_analise', 'ok'));
        $this->app->instance(ReprocessarEtapasService::class, $servico);

        $this->artisan('processos:reprocessar-etapas')->assertSuccessful();
    }

    public function test_opcao_processo_reprocessa_so_aquele_processo_ignorando_a_agenda(): void
    {
        $etapa = ViabilityRequestEtapa::factory()->indisponivel()->create(['proxima_tentativa_em' => now()->addDay()]);

        $servico = Mockery::mock(ReprocessarEtapasService::class);
        $servico->shouldReceive('reprocessar')->once()->andReturn(new ReprocessoResultado(true, null, 'em_analise', 'ok'));
        $this->app->instance(ReprocessarEtapasService::class, $servico);

        $this->artisan('processos:reprocessar-etapas', ['--processo' => $etapa->viability_request_id])->assertSuccessful();
    }

    public function test_sem_candidatos_informa_e_sai_com_sucesso(): void
    {
        $this->artisan('processos:reprocessar-etapas')
            ->expectsOutput('Nenhum processo com etapa indisponível a reprocessar.')
            ->assertSuccessful();
    }
}
