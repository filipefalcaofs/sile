<?php

namespace Tests\Feature\Servicos;

use App\Models\ServicoNomenclatura;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Tela de parametrização do De x Para dos serviços: a SEDUR muda o nome
 * exibido e o Exibir sem deploy, atrás de manter-nomenclatura-servicos.
 */
class ServicoNomenclaturaCrudTest extends TestCase
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

    public function test_lista_exige_permissao(): void
    {
        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create();

        $this->actingAs($analista, 'gestao')
            ->get('/gestao/servicos-nomenclatura')
            ->assertForbidden();
    }

    public function test_administrador_lista_e_busca_por_de_ou_para(): void
    {
        ServicoNomenclatura::garantir('Alteração de nome empresarial');

        $this->actingAs($this->administrador(), 'gestao')
            ->get('/gestao/servicos-nomenclatura?search=localiza')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/servicos-nomenclatura/index')
                ->has('nomenclaturas.data', 2)
                ->where('nomenclaturas.data.0.para', 'Viabilidade de Localização')
                ->where('filters.sort', 'de')
                ->has('sugestoesDe')
                ->has('perPageOptions'));

        $this->actingAs($this->administrador(), 'gestao')
            ->get('/gestao/servicos-nomenclatura?exibir=0')
            ->assertInertia(fn (Assert $page) => $page
                ->has('nomenclaturas.data', 1)
                ->where('nomenclaturas.data.0.de', 'Alteração de nome empresarial'));
    }

    public function test_cria_linha(): void
    {
        $this->actingAs($this->administrador(), 'gestao')
            ->post('/gestao/servicos-nomenclatura', ['de' => 'Baixa de inscrição', 'para' => 'Encerramento', 'exibir' => '1'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('servico_nomenclaturas', [
            'de' => 'Baixa de inscrição',
            'chave' => 'baixa de inscricao',
            'para' => 'Encerramento',
            'exibir' => true,
        ]);
    }

    public function test_nao_cria_de_repetido_por_caixa_ou_acento(): void
    {
        $this->actingAs($this->administrador(), 'gestao')
            ->post('/gestao/servicos-nomenclatura', ['de' => 'INSCRICAO DE PRIMEIRO ESTABELECIMENTO', 'para' => 'Outro', 'exibir' => '1'])
            ->assertSessionHasErrors(['de' => 'Já existe uma linha para este serviço.']);
    }

    public function test_exige_de_e_para(): void
    {
        $this->actingAs($this->administrador(), 'gestao')
            ->post('/gestao/servicos-nomenclatura', ['de' => '', 'para' => ''])
            ->assertSessionHasErrors(['de', 'para']);
    }

    public function test_atualiza_para_e_exibir_sem_mudar_de(): void
    {
        $linha = ServicoNomenclatura::garantir('Alteração de nome empresarial');

        $this->actingAs($this->administrador(), 'gestao')
            ->put("/gestao/servicos-nomenclatura/{$linha->id}", ['de' => 'Outro nome', 'para' => 'Nome Empresarial', 'exibir' => '1'])
            ->assertSessionHasNoErrors();

        $linha->refresh();
        $this->assertSame('Alteração de nome empresarial', $linha->de);
        $this->assertSame('Nome Empresarial', $linha->para);
        $this->assertTrue($linha->exibir);
    }

    public function test_alterna_exibir(): void
    {
        $linha = ServicoNomenclatura::garantir('Alteração de nome empresarial');

        $this->actingAs($this->administrador(), 'gestao')
            ->put("/gestao/servicos-nomenclatura/{$linha->id}/exibir")
            ->assertSessionHas('status');

        $this->assertTrue($linha->refresh()->exibir);
    }

    public function test_migration_concede_a_permissao_a_quem_cadastra_tipos_de_servico(): void
    {
        $perfil = Role::create(['name' => 'Perfil da SEDUR', 'guard_name' => 'web']);
        $perfil->givePermissionTo('manter-tipos-servico');
        $semTipos = Role::create(['name' => 'Sem tipos', 'guard_name' => 'web']);

        (require database_path('migrations/2026_10_20_190321_cria_permissao_manter_nomenclatura_servicos.php'))->up();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertTrue($perfil->fresh()->hasPermissionTo('manter-nomenclatura-servicos'));
        $this->assertFalse($semTipos->fresh()->hasPermissionTo('manter-nomenclatura-servicos'));
        $this->assertTrue(Role::findByName('administrador', 'web')->hasPermissionTo('manter-nomenclatura-servicos'));
    }
}
