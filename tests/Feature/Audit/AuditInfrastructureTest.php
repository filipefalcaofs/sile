<?php

namespace Tests\Feature\Audit;

use App\Models\Activity;
use App\Models\User;
use App\Support\Audit\AuditService;
use Illuminate\Auth\Access\AuthorizationException;
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

    public function test_model_com_has_auditoria_loga_somente_atributos_alterados(): void
    {
        $user = User::factory()->create(['name' => 'Nome Original']);

        $user->update([
            'name' => 'Nome Alterado',
            'password' => 'NovaSenhaForte1',
        ]);

        $activity = Activity::query()
            ->where('event', 'updated')
            ->where('subject_type', User::class)
            ->where('subject_id', $user->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($activity, 'Esperava activity de atualização do usuário');

        $changedAttributes = array_keys($activity->attribute_changes['attributes'] ?? []);

        $this->assertContains('name', $changedAttributes);
        $this->assertNotContains('email', $changedAttributes);

        $serialized = json_encode([$activity->attribute_changes, $activity->properties]);

        $this->assertStringNotContainsString('password', $serialized);
        $this->assertStringNotContainsString('remember_token', $serialized);
    }

    public function test_audit_service_registra_evento_explicito(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        app(AuditService::class)->log('seguranca', 'senha-alterada', 'Senha alterada pelo próprio usuário');

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'seguranca',
            'event' => 'senha-alterada',
            'description' => 'Senha alterada pelo próprio usuário',
            'result' => 'sucesso',
            'causer_id' => $user->id,
        ]);
    }

    public function test_audit_service_registra_bloqueio_com_resultado_bloqueado(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        app(AuditService::class)->logBlocked('seguranca', 'Tentativa de acesso sem permissão', ['rota' => '/gestao']);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'seguranca',
            'event' => 'acesso-negado',
            'result' => 'bloqueado',
            'causer_id' => $user->id,
        ]);
    }

    public function test_excecao_de_autorizacao_gera_auditoria_de_bloqueio(): void
    {
        Route::middleware(['web', 'auth'])->get('/_test/403', function () {
            throw new AuthorizationException;
        });

        $user = User::factory()->create();

        $this->actingAs($user)->get('/_test/403')->assertForbidden();

        $this->assertDatabaseHas('activity_log', [
            'event' => 'acesso-negado',
            'result' => 'bloqueado',
            'causer_id' => $user->id,
        ]);
    }
}
