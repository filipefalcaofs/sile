<?php

namespace Tests\Feature\Analise;

use App\Models\StandardText;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CRUD da biblioteca de textos-padrão do parecer (HU-085): o administrador
 * mantém os trechos pré-aprovados (criar/editar por categoria, ativar/inativar)
 * pela retaguarda, atrás da permissão manter-parametros (decisão registrada:
 * reuso da permissão de admin de configuração — sem 6ª permissão; a leitura da
 * lista ativa pelo parecer fica sob analisar-processos em 10-09). Sem a
 * permissão a ação é bloqueada e auditada (CA-04). Editar o CONTEÚDO incrementa
 * a versão (RN-005, rastreabilidade); inativar preserva o histórico (não
 * exclui). Toda alteração é auditada (RN-002). Espelha o
 * ViabilityServiceTypeController.
 */
class StandardTextCrudTest extends TestCase
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

    public function test_lista_exige_permissao_manter_parametros(): void
    {
        // Analista acessa a gestão mas NÃO tem manter-parametros (HU-085 CA-04).
        $this->actingAs($this->analista(), 'gestao')
            ->get('/gestao/textos-padrao')
            ->assertForbidden();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'seguranca',
            'event' => 'acesso-negado',
            'result' => 'bloqueado',
        ]);
    }

    public function test_administrador_lista_textos_padrao(): void
    {
        StandardText::factory()->count(3)->create();

        $response = $this->actingAs($this->administrador(), 'gestao')
            ->get('/gestao/textos-padrao')
            ->assertOk();

        // A tela de console é construída em 10-17; aqui (backend) inspecionamos
        // as props do Inertia sem exigir o arquivo .tsx em disco.
        $page = $response->viewData('page');
        $this->assertSame('gestao/textos-padrao/index', $page['component']);
        $this->assertCount(3, $page['props']['standardTexts']['data']);
        $this->assertArrayHasKey('perPageOptions', $page['props']);
    }

    public function test_cria_texto_padrao_versao_inicial_1_auditado(): void
    {
        $this->actingAs($this->administrador(), 'gestao')
            ->post('/gestao/textos-padrao', [
                'category' => 'deferimento',
                'content' => 'A viabilidade é deferida nos termos da LOUOS.',
                'active' => '1',
            ])
            ->assertSessionHas('status');

        $text = StandardText::query()->where('category', 'deferimento')->first();
        $this->assertNotNull($text);
        $this->assertSame(1, $text->version);
        $this->assertTrue($text->active);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'textos-padrao',
            'event' => 'criar',
            'subject_type' => StandardText::class,
            'subject_id' => $text->id,
        ]);
    }

    public function test_editar_conteudo_incrementa_versao(): void
    {
        // RN-005: alterar o conteúdo evolui a versão (rastreabilidade do parecer).
        $text = StandardText::factory()->create([
            'category' => 'condicionante',
            'content' => 'Texto original.',
            'version' => 1,
        ]);

        $this->actingAs($this->administrador(), 'gestao')
            ->put("/gestao/textos-padrao/{$text->id}", [
                'category' => 'condicionante',
                'content' => 'Texto revisado com nova fundamentação.',
                'active' => '1',
            ])
            ->assertSessionHas('status');

        $text->refresh();
        $this->assertSame('Texto revisado com nova fundamentação.', $text->content);
        $this->assertSame(2, $text->version);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'textos-padrao',
            'event' => 'atualizar',
            'subject_id' => $text->id,
        ]);
    }

    public function test_editar_apenas_metadados_nao_incrementa_versao(): void
    {
        // Alterar só categoria/situação (conteúdo inalterado) preserva a versão.
        $text = StandardText::factory()->create([
            'category' => 'pendencia',
            'content' => 'Texto imutável nesta edição.',
            'active' => true,
            'version' => 1,
        ]);

        $this->actingAs($this->administrador(), 'gestao')
            ->put("/gestao/textos-padrao/{$text->id}", [
                'category' => 'pendencia',
                'content' => 'Texto imutável nesta edição.',
                'active' => '0',
            ])
            ->assertSessionHas('status');

        $text->refresh();
        $this->assertSame(1, $text->version);
        $this->assertFalse($text->active);
    }

    public function test_toggle_desativa_sem_excluir(): void
    {
        $text = StandardText::factory()->create(['active' => true, 'version' => 3]);

        $this->actingAs($this->administrador(), 'gestao')
            ->put("/gestao/textos-padrao/{$text->id}/ativacao")
            ->assertSessionHas('status');

        // Desativar preserva a linha e a versão (não exclui o histórico).
        $this->assertDatabaseHas('standard_texts', ['id' => $text->id]);
        $text->refresh();
        $this->assertFalse($text->active);
        $this->assertSame(3, $text->version);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'textos-padrao',
            'event' => 'ativacao',
            'subject_id' => $text->id,
        ]);
    }

    public function test_filtra_por_categoria(): void
    {
        StandardText::factory()->count(2)->create(['category' => 'deferimento']);
        StandardText::factory()->create(['category' => 'indeferimento']);

        $response = $this->actingAs($this->administrador(), 'gestao')
            ->get('/gestao/textos-padrao?category=deferimento')
            ->assertOk();

        $page = $response->viewData('page');
        $this->assertCount(2, $page['props']['standardTexts']['data']);
        $this->assertSame('deferimento', $page['props']['filters']['category']);
    }
}
