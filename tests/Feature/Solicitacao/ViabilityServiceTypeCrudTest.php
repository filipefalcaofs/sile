<?php

namespace Tests\Feature\Solicitacao;

use App\Models\User;
use App\Models\ViabilityServiceType;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * CRUD dos tipos de serviço da solicitação (HU-061 RN-005): o administrador/
 * gestor mantém os tipos de serviço — dado administrável (tabela + CRUD), não
 * registry de código — pela retaguarda, atrás da permissão manter-tipos-servico.
 * Sem ela a ação é bloqueada e auditada (CA-04). O code é único e imutável na
 * edição (padrão CNAE/CNPJ); desativar preserva o histórico (não exclui o que
 * já foi usado em solicitações). Toda alteração é auditada (RN-002). Espelha o
 * CnaeController.
 */
class ViabilityServiceTypeCrudTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_lista_exige_permissao_manter_tipos_servico(): void
    {
        // Analista acessa a gestão mas NÃO tem manter-tipos-servico (CA-04).
        $this->actingAs($this->analista(), 'gestao')
            ->get('/gestao/tipos-servico')
            ->assertForbidden();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'seguranca',
            'event' => 'acesso-negado',
            'result' => 'bloqueado',
        ]);
    }

    public function test_administrador_lista_tipos_de_servico(): void
    {
        ViabilityServiceType::factory()->count(3)->create();

        $this->actingAs($this->administrador(), 'gestao')
            ->get('/gestao/tipos-servico')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('serviceTypes.data', 3)
                ->has('perPageOptions')
                ->where('filters.sort', 'code'));
    }

    public function test_cria_tipo_de_servico_auditado(): void
    {
        $this->actingAs($this->administrador(), 'gestao')
            ->post('/gestao/tipos-servico', [
                'code' => 'primeiro-estabelecimento',
                'name' => 'Viabilidade de primeiro estabelecimento',
                'flow_hint' => 'Abertura',
                'active' => '1',
            ])
            ->assertSessionHas('status');

        $tipo = ViabilityServiceType::query()->where('code', 'primeiro-estabelecimento')->first();
        $this->assertNotNull($tipo);
        $this->assertSame('Viabilidade de primeiro estabelecimento', $tipo->name);
        $this->assertTrue($tipo->active);

        // Auditoria automática do model (RN-002, HasAuditoria).
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => ViabilityServiceType::class,
            'subject_id' => $tipo->id,
            'event' => 'created',
        ]);
    }

    public function test_code_duplicado_e_rejeitado(): void
    {
        ViabilityServiceType::factory()->create(['code' => 'renovacao']);

        $this->actingAs($this->administrador(), 'gestao')
            ->post('/gestao/tipos-servico', [
                'code' => 'renovacao',
                'name' => 'Outro tipo com o mesmo code',
            ])
            ->assertSessionHasErrors('code');

        $this->assertSame(1, ViabilityServiceType::query()->where('code', 'renovacao')->count());
    }

    public function test_code_e_imutavel_na_edicao(): void
    {
        $tipo = ViabilityServiceType::factory()->create([
            'code' => 'original',
            'name' => 'Nome original',
        ]);

        $this->actingAs($this->administrador(), 'gestao')
            ->put("/gestao/tipos-servico/{$tipo->id}", [
                'code' => 'tentativa-de-troca',
                'name' => 'Nome revisado',
                'active' => '1',
            ])
            ->assertSessionHas('status');

        $tipo->refresh();
        // O code enviado é ignorado na edição (padrão CNPJ/CNAE).
        $this->assertSame('original', $tipo->code);
        $this->assertSame('Nome revisado', $tipo->name);
    }

    public function test_desativar_preserva_registro(): void
    {
        $tipo = ViabilityServiceType::factory()->create(['active' => true]);

        $this->actingAs($this->administrador(), 'gestao')
            ->put("/gestao/tipos-servico/{$tipo->id}/ativacao")
            ->assertSessionHas('status');

        // Toggle desativa SEM remover a linha — histórico preservado.
        $this->assertDatabaseHas('viability_service_types', ['id' => $tipo->id]);
        $this->assertFalse($tipo->fresh()->active);
    }
}
