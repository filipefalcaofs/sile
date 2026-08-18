<?php

namespace Tests\Feature\Auditoria;

use App\Models\User;
use App\Support\Audit\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditServicePersonalDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_log_com_personal_data_true_marca_acesso_a_dado_pessoal(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $activity = app(AuditService::class)->log(
            'lgpd',
            'consulta-dado-pessoal',
            'Acesso a dado pessoal do titular',
            personalData: true,
        );

        $this->assertTrue((bool) $activity->fresh()->personal_data);

        $this->assertDatabaseHas('activity_log', [
            'id' => $activity->id,
            'log_name' => 'lgpd',
            'event' => 'consulta-dado-pessoal',
            'personal_data' => true,
        ]);
    }

    public function test_log_sem_parametro_nao_marca_dado_pessoal_por_padrao(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $activity = app(AuditService::class)->log(
            'seguranca',
            'evento-comum',
            'Evento sem acesso a dado pessoal',
        );

        $this->assertFalse((bool) $activity->fresh()->personal_data);
    }

    public function test_log_preserva_result_e_rules_version_ao_gravar_personal_data(): void
    {
        $activity = app(AuditService::class)->log(
            'lgpd',
            'consulta-dado-pessoal',
            'Acesso auditado a dado pessoal',
            result: 'sucesso',
            rulesVersion: 'lgpd-v1',
            personalData: true,
        );

        $this->assertDatabaseHas('activity_log', [
            'id' => $activity->id,
            'result' => 'sucesso',
            'rules_version' => 'lgpd-v1',
            'personal_data' => true,
        ]);
    }
}
