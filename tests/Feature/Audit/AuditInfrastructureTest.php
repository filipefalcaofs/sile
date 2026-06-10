<?php

namespace Tests\Feature\Audit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuditInfrastructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_activity_persiste_colunas_sile_de_origem_e_resultado(): void
    {
        Route::middleware('web')->get('/_test/audit', function () {
            activity('teste')->log('Ação de teste');

            return response()->noContent();
        });

        $this->get('/_test/audit')->assertNoContent();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'teste',
            'description' => 'Ação de teste',
            'ip_address' => '127.0.0.1',
            'channel' => 'portal',
            'result' => 'sucesso',
        ]);
    }

    public function test_activity_aceita_acting_for_user_id_via_context(): void
    {
        $outroUser = User::factory()->create();

        Route::middleware('web')->get('/_test/audit-em-nome-de', function () use ($outroUser) {
            Context::add('acting_for_user_id', $outroUser->id);

            activity('teste')->log('Ação em nome de outro usuário');

            return response()->noContent();
        });

        $this->get('/_test/audit-em-nome-de')->assertNoContent();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'teste',
            'description' => 'Ação em nome de outro usuário',
            'acting_for_user_id' => $outroUser->id,
        ]);
    }

    public function test_activity_persiste_result_e_rules_version_explicitos(): void
    {
        \App\Models\Activity::create([
            'description' => 'Execução de regra',
            'result' => 'falha',
            'rules_version' => 'louos-v1',
        ]);

        $this->assertDatabaseHas('activity_log', [
            'description' => 'Execução de regra',
            'result' => 'falha',
            'rules_version' => 'louos-v1',
        ]);
    }
}
