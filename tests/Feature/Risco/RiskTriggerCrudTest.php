<?php

namespace Tests\Feature\Risco;

use App\Enums\TipoGatilho;
use App\Models\RiskTrigger;
use App\Models\User;
use Database\Seeders\RiskTriggerSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Manutenção dos gatilhos semi-expresso (HU-049/HU-051, parametrização HU-014):
 * o administrador/gestor edita título/motivo e liga/desliga os gatilhos pela
 * retaguarda, atrás da permissão manter-gatilhos-risco. SEM criar/excluir —
 * o código é enum-bound (TipoGatilho) com comportamento no motor; uma linha
 * criada pela UI sem caso no enum seria fachada. Toda alteração é auditada
 * (RN-002 via HasAuditoria). Espelha o PropertyTypeCrudTest.
 */
class RiskTriggerCrudTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function administrador(): User
    {
        return User::factory()->administrador()->withAcceptedLgpdTerm()->create();
    }

    private function analista(): User
    {
        return User::factory()->analista()->withAcceptedLgpdTerm()->create();
    }

    public function test_lista_exige_permissao(): void
    {
        // Analista acessa a gestão mas NÃO tem manter-gatilhos-risco.
        $this->actingAs($this->analista(), 'gestao')
            ->get('/gestao/gatilhos-risco')
            ->assertForbidden();
    }

    public function test_edita_titulo_e_motivo_com_auditoria(): void
    {
        $this->seed(RiskTriggerSeeder::class);

        $gatilho = RiskTrigger::query()
            ->where('codigo', TipoGatilho::ZeisEspecial->value)
            ->firstOrFail();

        $this->actingAs($this->administrador(), 'gestao')
            ->put("/gestao/gatilhos-risco/{$gatilho->id}", [
                'titulo' => 'Localização em ZEIS (revisado)',
                'motivo' => 'Motivo atualizado pela gestão.',
            ])->assertRedirect();

        $gatilho->refresh();

        $this->assertSame('Localização em ZEIS (revisado)', $gatilho->titulo);
        $this->assertSame('Motivo atualizado pela gestão.', $gatilho->motivo);
        // codigo/categoria NUNCA editáveis pela tela.
        $this->assertSame(TipoGatilho::ZeisEspecial, $gatilho->codigo);
        $this->assertSame('semi_expresso', $gatilho->categoria);
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => RiskTrigger::class,
            'subject_id' => $gatilho->id,
            'event' => 'updated',
        ]);
    }

    public function test_toggle_desativa_gatilho_e_o_motor_para_de_aplicar(): void
    {
        $this->seed(RiskTriggerSeeder::class);

        $gatilho = RiskTrigger::query()
            ->where('codigo', TipoGatilho::ZeisEspecial->value)
            ->firstOrFail();

        $this->assertTrue($gatilho->ativo);

        $this->actingAs($this->administrador(), 'gestao')
            ->put("/gestao/gatilhos-risco/{$gatilho->id}/ativacao")
            ->assertRedirect();

        $this->assertFalse($gatilho->fresh()->ativo);
        // O motor consome apenas os ativos: o gatilho desligado sai do escopo.
        $this->assertFalse(
            RiskTrigger::ativos()->where('codigo', TipoGatilho::ZeisEspecial->value)->exists(),
        );
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => RiskTrigger::class,
            'subject_id' => $gatilho->id,
            'event' => 'updated',
        ]);
    }

    /**
     * Anti-fachada: o código do gatilho é enum-bound com comportamento no
     * motor — uma linha criada pela UI sem caso no enum não faria nada.
     * Por isso NÃO existem rotas de criação nem de exclusão.
     */
    public function test_nao_existe_rota_de_criacao_ou_exclusao(): void
    {
        $this->seed(RiskTriggerSeeder::class);

        $gatilho = RiskTrigger::query()->firstOrFail();
        $admin = $this->administrador();

        $this->actingAs($admin, 'gestao')
            ->post('/gestao/gatilhos-risco', [
                'codigo' => 'gatilho_inventado',
                'titulo' => 'Gatilho inventado',
                'motivo' => 'Sem caso no enum.',
            ])->assertMethodNotAllowed();

        $this->actingAs($admin, 'gestao')
            ->delete("/gestao/gatilhos-risco/{$gatilho->id}")
            ->assertMethodNotAllowed();
    }
}
