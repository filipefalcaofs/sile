<?php

namespace Tests\Feature\Scheduling;

use App\Models\AccessLog;
use App\Models\Activity;
use App\Models\Parameter;
use App\Support\Audit\AuditService;
use Database\Seeders\ParameterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PruneAccessLogsTest extends TestCase
{
    use RefreshDatabase;

    public function test_pruning_remove_acessos_alem_da_retencao_e_preserva_recentes(): void
    {
        $this->seed(ParameterSeeder::class);

        AccessLog::factory()->count(3)->create(['created_at' => now()->subDays(400)]);
        AccessLog::factory()->count(2)->create(['created_at' => now()->subDays(10)]);

        $this->artisan('model:prune', ['--model' => [AccessLog::class]])->assertExitCode(0);

        $this->assertSame(2, AccessLog::query()->count());
    }

    public function test_model_prune_global_nao_remove_a_trilha_de_auditoria(): void
    {
        $this->seed(ParameterSeeder::class);

        // Trilha de auditoria antiga (1000 dias): se Activity fosse Prunable, sumiria.
        app(AuditService::class)->log('teste', 'evento-antigo', 'descricao');
        $activityId = Activity::query()->latest('id')->first()->id;
        Activity::query()->whereKey($activityId)->update(['created_at' => now()->subDays(1000)]);

        // access_log antigo: garante que o prune global atua de fato sobre um Prunable.
        AccessLog::factory()->create(['created_at' => now()->subDays(1000)]);

        // model:prune SEM --model poda TODOS os Prunable descobertos (só AccessLog).
        $this->artisan('model:prune')->assertExitCode(0);

        // Asserir por CHAVE (não por count): o listener de auditoria da Task 2
        // grava novas Activities ao podar — contar o total seria frágil.
        $this->assertTrue(Activity::query()->whereKey($activityId)->exists());
        $this->assertSame(0, AccessLog::query()->count()); // prova que o prune global rodou
    }

    public function test_alterar_parametro_muda_a_janela_de_retencao(): void
    {
        $this->seed(ParameterSeeder::class);

        AccessLog::factory()->create(['created_at' => now()->subDays(60)]);

        $this->artisan('model:prune', ['--model' => [AccessLog::class]]);
        $this->assertSame(1, AccessLog::query()->count()); // 60 < 365 → sobrevive

        Parameter::query()->where('key', 'retencao.access_logs.dias')->first()->update(['value' => '30']);

        $this->artisan('model:prune', ['--model' => [AccessLog::class]]);
        $this->assertSame(0, AccessLog::query()->count()); // 60 > 30 → podado (efeito sem deploy)
    }
}
