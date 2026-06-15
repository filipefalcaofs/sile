<?php

namespace Tests\Feature\Lgpd;

use App\Enums\ViabilityRequestStatus;
use App\Models\User;
use App\Models\ViabilityRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Marcação honesta de personal_data=true (LGPD HU-102) nos call sites REAIS de
 * leitura/gestão de dado pessoal de terceiro: detalhe do processo de um cidadão,
 * histórico de acessos de OUTRA conta e gestão de usuários. Mudança ADITIVA — só
 * acrescenta o parâmetro à auditoria já existente, sem mudar evento/descrição; a
 * anti-regressão das suítes desses controllers é coberta em
 * ProcessoConsultaTest/AccessHistoryTest. É a base de medição real do painel LGPD.
 */
class PersonalDataMarkingTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function processo(): ViabilityRequest
    {
        $protocolo = 'VIA-'.now()->year.'-'.str_pad((string) ++$this->seq, 6, '0', STR_PAD_LEFT);

        return ViabilityRequest::factory()->create([
            'status' => ViabilityRequestStatus::EmAnalise,
            'protocol_number' => $protocolo,
            'protocoled_at' => now(),
        ]);
    }

    public function test_detalhe_de_processo_marca_acesso_a_dado_pessoal(): void
    {
        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create();
        $processo = $this->processo();

        $this->actingAs($analista, 'gestao')
            ->get("/gestao/processos/{$processo->id}")
            ->assertOk();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'analise',
            'event' => 'consulta-processo',
            'result' => 'sucesso',
            'causer_id' => $analista->id,
            'personal_data' => true,
        ]);
    }

    public function test_consulta_de_acessos_de_terceiro_marca_acesso_a_dado_pessoal(): void
    {
        $admin = User::factory()->administrador()->withAcceptedLgpdTerm()->create();
        $target = User::factory()->create();

        $this->actingAs($admin, 'gestao')
            ->get("/gestao/acessos/{$target->id}")
            ->assertOk();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'acessos',
            'event' => 'consulta-acessos',
            'result' => 'sucesso',
            'causer_id' => $admin->id,
            'personal_data' => true,
        ]);
    }

    public function test_alteracao_de_papel_de_usuario_marca_acesso_a_dado_pessoal(): void
    {
        $admin = User::factory()->administrador()->withAcceptedLgpdTerm()->create();
        $target = User::factory()->cidadao()->create();

        $this->actingAs($admin, 'gestao')
            ->put("/gestao/usuarios/{$target->id}/papel", ['role' => 'analista'])
            ->assertRedirect();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'usuarios',
            'event' => 'papel-alterado',
            'result' => 'sucesso',
            'causer_id' => $admin->id,
            'personal_data' => true,
        ]);
    }

    public function test_inativacao_de_usuario_marca_acesso_a_dado_pessoal(): void
    {
        $admin = User::factory()->administrador()->withAcceptedLgpdTerm()->create();
        $target = User::factory()->cidadao()->create();

        $this->actingAs($admin, 'gestao')
            ->put("/gestao/usuarios/{$target->id}/inativacao")
            ->assertRedirect();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'usuarios',
            'event' => 'usuario-inativado',
            'result' => 'sucesso',
            'causer_id' => $admin->id,
            'personal_data' => true,
        ]);
    }
}
