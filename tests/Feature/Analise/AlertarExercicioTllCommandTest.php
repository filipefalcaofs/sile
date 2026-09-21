<?php

namespace Tests\Feature\Analise;

use App\Enums\RuleDomain;
use App\Enums\RuleVersionStatus;
use App\Models\RuleVersion;
use App\Models\User;
use App\Notifications\TllExercicioFaltanteNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Alerta de dezembro/janeiro quando falta tabela TLL vigente: só notifica
 * gestores, nunca grava valor nem publica. Idempotente pela auditoria.
 */
class AlertarExercicioTllCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_notifica_gestor_quando_falta_versao_vigente(): void
    {
        Carbon::setTestNow('2026-12-15');
        Notification::fake();
        $gestor = User::factory()->gestor()->create();

        $this->artisan('tll:alertar-exercicio')->assertSuccessful();

        Notification::assertSentTo($gestor, TllExercicioFaltanteNotification::class);
    }

    public function test_segunda_execucao_nao_reenvia(): void
    {
        Carbon::setTestNow('2026-12-15');
        Notification::fake();
        $gestor = User::factory()->gestor()->create();

        $this->artisan('tll:alertar-exercicio')->assertSuccessful();
        $this->artisan('tll:alertar-exercicio')->assertSuccessful();

        Notification::assertSentToTimes($gestor, TllExercicioFaltanteNotification::class, 1);
    }

    public function test_fora_de_dezembro_e_janeiro_nao_alerta(): void
    {
        Carbon::setTestNow('2026-06-15');
        Notification::fake();
        $gestor = User::factory()->gestor()->create();

        $this->artisan('tll:alertar-exercicio')->assertSuccessful();

        Notification::assertNotSentTo($gestor, TllExercicioFaltanteNotification::class);
    }

    public function test_com_versoes_vigentes_nao_alerta(): void
    {
        Carbon::setTestNow('2026-12-15');
        Notification::fake();
        $gestor = User::factory()->gestor()->create();

        RuleVersion::factory()->create([
            'domain' => RuleDomain::TllValores,
            'version' => '2026',
            'status' => RuleVersionStatus::Vigente,
            'valid_to' => null,
        ]);
        RuleVersion::factory()->create([
            'domain' => RuleDomain::TllValores,
            'version' => '2027',
            'status' => RuleVersionStatus::Vigente,
            'valid_to' => null,
        ]);

        $this->artisan('tll:alertar-exercicio')->assertSuccessful();

        Notification::assertNotSentTo($gestor, TllExercicioFaltanteNotification::class);
    }
}
