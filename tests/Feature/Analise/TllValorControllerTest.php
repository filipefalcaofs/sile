<?php

namespace Tests\Feature\Analise;

use App\Models\TllValor;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * CRUD administrável da tabela de valores TLL por exercício (HU-071/HU-014):
 * o administrador mantém os valores (criar/editar/ativar-inativar) pela
 * retaguarda, atrás da permissão manter-parametros (reuso — como feriados). A
 * chave (código TLL, exercício) é única; inativar preserva o histórico (sem
 * destroy). Toda alteração é auditada (RN-002 via HasAuditoria) e o 403 sem a
 * permissão é auditado no ponto único.
 */
class TllValorControllerTest extends TestCase
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

    public function test_lista_exige_permissao_manter_parametros(): void
    {
        $this->actingAs($this->analista(), 'gestao')
            ->get('/gestao/tll')
            ->assertForbidden();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'seguranca',
            'event' => 'acesso-negado',
            'result' => 'bloqueado',
        ]);
    }

    public function test_administrador_lista_os_valores(): void
    {
        TllValor::factory()->count(3)->create();

        $response = $this->actingAs($this->administrador(), 'gestao')
            ->get('/gestao/tll')
            ->assertOk();

        $page = $response->viewData('page');
        $this->assertSame('gestao/tll/index', $page['component']);
        $this->assertCount(3, $page['props']['valores']['data']);
        $this->assertArrayHasKey('perPageOptions', $page['props']);
    }

    public function test_cria_valor_auditado(): void
    {
        $this->actingAs($this->administrador(), 'gestao')
            ->post('/gestao/tll', [
                'codigo_tll' => '1.01',
                'exercicio' => '2026',
                'valor' => '1111.78',
                'taxa_servico' => '50.00',
                'codigo_tll_sefaz' => 'T45020425',
                'codigo_servico_sefaz' => 'S2253362',
                'servico_sefaz' => 'Inclusão de Atividade em Viabilidade MEI',
                'active' => '1',
            ])
            ->assertSessionHas('status');

        $valor = TllValor::query()->where('codigo_tll', '1.01')->where('exercicio', 2026)->first();
        $this->assertNotNull($valor);
        $this->assertSame('1111.78', (string) $valor->valor);
        $this->assertSame('T45020425', $valor->codigo_tll_sefaz);

        // RN-002: o cadastro é auditado (HasAuditoria → created).
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => $valor->getMorphClass(),
            'subject_id' => $valor->id,
            'event' => 'created',
        ]);
    }

    public function test_codigo_e_exercicio_duplicados_sao_rejeitados(): void
    {
        TllValor::factory()->create(['codigo_tll' => '1.01', 'exercicio' => 2026]);

        $this->actingAs($this->administrador(), 'gestao')
            ->post('/gestao/tll', [
                'codigo_tll' => '1.01',
                'exercicio' => '2026',
                'valor' => '999.00',
            ])
            ->assertSessionHasErrors('codigo_tll');

        $this->assertSame(1, TllValor::query()->where('codigo_tll', '1.01')->where('exercicio', 2026)->count());
    }

    public function test_edita_valor(): void
    {
        $valor = TllValor::factory()->create(['codigo_tll' => '1.01', 'exercicio' => 2026, 'valor' => 1000.00]);

        $this->actingAs($this->administrador(), 'gestao')
            ->put("/gestao/tll/{$valor->id}", [
                'codigo_tll' => '1.01',
                'exercicio' => '2026',
                'valor' => '1500.00',
                'taxa_servico' => '60.00',
                'active' => '1',
            ])
            ->assertSessionHas('status');

        $this->assertSame('1500.00', (string) $valor->fresh()->valor);
    }

    public function test_inativa_preserva_o_historico(): void
    {
        $valor = TllValor::factory()->create(['active' => true]);

        $this->actingAs($this->administrador(), 'gestao')
            ->put("/gestao/tll/{$valor->id}/ativacao")
            ->assertSessionHas('status');

        $this->assertFalse($valor->fresh()->active);

        // Não existe rota destrutiva — o histórico é preservado.
        $this->assertFalse(Route::has('gestao.tll.destroy'));
    }
}
