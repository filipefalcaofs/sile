<?php

namespace Tests\Feature\Relatorios;

use App\Models\ExportFile;
use App\Models\Parameter;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Retenção dos arquivos de exportação (HU-131 / 15-15), espelhando o pruning de
 * access_logs da Fase 3.1: os ExportFiles além da janela parametrizada
 * (relatorios.export.retencao_dias) são podados por rotina agendada idempotente,
 * removendo o REGISTRO e o ARQUIVO no Storage (sem órfão — disco não cresce sem
 * limite). Alterar o parâmetro muda o corte sem deploy (Dimensão 5 / HU-014).
 */
class ExportRetencaoPruningTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function pruning_remove_exports_alem_da_retencao_com_registro_e_arquivo(): void
    {
        Storage::fake('local');

        $antigos = ExportFile::factory()->count(3)->create([
            'disk' => 'local',
            'created_at' => now()->subDays(30),
        ]);
        $recentes = ExportFile::factory()->count(2)->create([
            'disk' => 'local',
            'created_at' => now()->subDays(2),
        ]);

        // Cada registro tem um arquivo REAL no disco (alvo do pruning físico).
        foreach ($antigos->merge($recentes) as $export) {
            Storage::disk('local')->put($export->path, 'conteudo-de-evidencia');
        }

        $this->artisan('model:prune', ['--model' => [ExportFile::class]])->assertExitCode(0);

        // Registros: só os recentes sobrevivem à retenção padrão (7 dias).
        $this->assertSame(2, ExportFile::query()->count());

        // Arquivos: os antigos somem do Storage (sem órfão); os recentes ficam.
        foreach ($antigos as $export) {
            Storage::disk('local')->assertMissing($export->path);
        }
        foreach ($recentes as $export) {
            Storage::disk('local')->assertExists($export->path);
        }
    }

    #[Test]
    public function alterar_o_parametro_de_retencao_muda_a_janela_sem_deploy(): void
    {
        Storage::fake('local');

        $export = ExportFile::factory()->create([
            'disk' => 'local',
            'created_at' => now()->subDays(5),
        ]);
        Storage::disk('local')->put($export->path, 'conteudo');

        // Retenção padrão (7 dias): 5 < 7 → sobrevive (registro e arquivo).
        $this->artisan('model:prune', ['--model' => [ExportFile::class]]);
        $this->assertSame(1, ExportFile::query()->count());
        Storage::disk('local')->assertExists($export->path);

        // Parametrização HU-014 (efeito sem deploy): corta em 3 dias → 5 > 3.
        Parameter::factory()->integer('7')->create([
            'group' => 'relatorios',
            'key' => 'relatorios.export.retencao_dias',
            'value' => '3',
        ]);

        $this->artisan('model:prune', ['--model' => [ExportFile::class]]);
        $this->assertSame(0, ExportFile::query()->count());
        Storage::disk('local')->assertMissing($export->path);
    }

    #[Test]
    public function pruning_dos_exports_esta_agendado_diariamente_de_forma_idempotente(): void
    {
        $schedule = app(Schedule::class);

        $event = collect($schedule->events())
            ->first(fn ($e) => str_contains((string) $e->command, 'model:prune')
                && str_contains((string) $e->command, 'ExportFile'));

        $this->assertNotNull($event, 'A poda de retenção dos exports deve estar agendada no scheduler.');
        $this->assertSame('0 0 * * *', $event->expression); // diário
        $this->assertTrue($event->withoutOverlapping); // idempotente (duração variável)
        $this->assertTrue($event->onOneServer); // seguro em multi-instância
    }
}
