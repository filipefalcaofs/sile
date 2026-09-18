<?php

namespace Tests\Feature\Gestao;

use App\Models\LegalTerm;
use App\Models\LegalTermAcceptance;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * CRUD dos termos legais versionados (parametrização HU-014, item 2.3): o
 * administrador mantém os termos (LGPD e futuros) pela retaguarda, atrás da
 * permissão manter-parametros (reuso — textos administráveis do mesmo
 * mantenedor). Integridade LGPD: a versão é computada (max+1 por tipo, nunca
 * informada pelo usuário) e o termo PUBLICADO é imutável — sem update, sem
 * destroy — porque o aceite do usuário referencia aquela versão exata;
 * editar seria fraude de registro. Rascunho (published_at null) é editável
 * e excluível. Espelha o PropertyTypeCrudTest.
 */
class LegalTermCrudTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const MENSAGEM_IMUTAVEL = 'Termos publicados são imutáveis — crie uma nova versão.';

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
        // Analista acessa a gestão mas NÃO tem manter-parametros.
        $this->actingAs($this->analista(), 'gestao')
            ->get('/gestao/termos-legais')
            ->assertForbidden();
    }

    public function test_cria_rascunho_com_proxima_versao_do_tipo(): void
    {
        // O aceite LGPD do administrador já garante lgpd v1 publicado.
        $this->actingAs($this->administrador(), 'gestao')->post('/gestao/termos-legais', [
            'type' => 'lgpd',
            'title' => 'Termo de Consentimento LGPD — revisão',
            'content' => 'Novo texto do termo de consentimento.',
        ])->assertRedirect();

        $rascunho = LegalTerm::query()->where('type', 'lgpd')->where('version', 2)->firstOrFail();
        $this->assertNull($rascunho->published_at);
        $this->assertSame('Termo de Consentimento LGPD — revisão', $rascunho->title);

        // O vigente continua sendo a v1 até a publicação do rascunho.
        $this->assertSame(1, LegalTerm::current('lgpd')?->version);
    }

    public function test_versao_nunca_informada_pelo_usuario(): void
    {
        $this->actingAs($this->administrador(), 'gestao')->post('/gestao/termos-legais', [
            'type' => 'lgpd',
            'version' => 99,
            'title' => 'Tentativa de forçar versão',
            'content' => 'Conteúdo qualquer.',
        ])->assertRedirect();

        $this->assertDatabaseMissing('legal_terms', ['type' => 'lgpd', 'version' => 99]);
        $this->assertDatabaseHas('legal_terms', ['type' => 'lgpd', 'version' => 2]);
    }

    public function test_publicar_torna_o_termo_vigente(): void
    {
        $admin = $this->administrador();
        $vigenteAntigo = LegalTerm::current('lgpd');
        $rascunho = LegalTerm::factory()->create(['type' => 'lgpd', 'version' => 2]);

        $this->actingAs($admin, 'gestao')
            ->put("/gestao/termos-legais/{$rascunho->id}/publicar")
            ->assertRedirect();

        $rascunho->refresh();
        $this->assertNotNull($rascunho->published_at);
        $this->assertSame($rascunho->id, LegalTerm::current('lgpd')?->id);

        // Aceites anteriores permanecem vinculados à versão aceita (histórico).
        $this->assertSame(
            $vigenteAntigo?->id,
            LegalTermAcceptance::query()->where('user_id', $admin->id)->value('legal_term_id'),
        );
    }

    public function test_publicar_termo_ja_publicado_e_rejeitado(): void
    {
        $publicado = LegalTerm::factory()->published()->create(['type' => 'lgpd', 'version' => 2]);
        $marcacaoOriginal = $publicado->published_at;

        $this->actingAs($this->administrador(), 'gestao')
            ->put("/gestao/termos-legais/{$publicado->id}/publicar")
            ->assertRedirect()
            ->assertSessionHas('error', self::MENSAGEM_IMUTAVEL);

        $this->assertTrue($marcacaoOriginal->equalTo($publicado->fresh()->published_at));
    }

    public function test_termo_publicado_nao_pode_ser_editado_nem_excluido(): void
    {
        $publicado = LegalTerm::current('lgpd') ?? LegalTerm::factory()->published()->create(['type' => 'lgpd', 'version' => 1]);
        $admin = $this->administrador();

        $this->actingAs($admin, 'gestao')->put("/gestao/termos-legais/{$publicado->id}", [
            'title' => 'Título adulterado',
            'content' => 'Conteúdo adulterado.',
        ])->assertRedirect()->assertSessionHas('error', self::MENSAGEM_IMUTAVEL);

        $this->actingAs($admin, 'gestao')
            ->delete("/gestao/termos-legais/{$publicado->id}")
            ->assertRedirect()
            ->assertSessionHas('error', self::MENSAGEM_IMUTAVEL);

        $publicado->refresh();
        $this->assertNotSame('Título adulterado', $publicado->title);
        $this->assertDatabaseHas('legal_terms', ['id' => $publicado->id]);
    }

    public function test_rascunho_pode_ser_editado_e_excluido(): void
    {
        $admin = $this->administrador();
        $rascunho = LegalTerm::factory()->create(['type' => 'lgpd', 'version' => 2, 'title' => 'Rascunho']);

        $this->actingAs($admin, 'gestao')->put("/gestao/termos-legais/{$rascunho->id}", [
            'type' => 'tentativa_de_troca',
            'title' => 'Rascunho revisado',
            'content' => 'Conteúdo revisado.',
        ])->assertRedirect();

        $rascunho->refresh();
        $this->assertSame('Rascunho revisado', $rascunho->title);
        $this->assertSame('Conteúdo revisado.', $rascunho->content);
        // O type é imutável na edição (padrão CPF/CNAE): enviado e descartado.
        $this->assertSame('lgpd', $rascunho->type);

        $this->actingAs($admin, 'gestao')
            ->delete("/gestao/termos-legais/{$rascunho->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('legal_terms', ['id' => $rascunho->id]);
    }

    public function test_validacao_do_tipo_no_cadastro(): void
    {
        $this->actingAs($this->administrador(), 'gestao')->post('/gestao/termos-legais', [
            'type' => 'LGPD Inválido!',
            'title' => 'Título',
            'content' => 'Conteúdo.',
        ])->assertSessionHasErrors('type');
    }
}
