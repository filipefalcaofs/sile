<?php

namespace Tests\Feature\Louos;

use App\Enums\RuleDomain;
use App\Enums\RuleVersionStatus;
use App\Models\LouosQuadro11CondicaoVia;
use App\Models\RuleVersion;
use App\Models\User;
use App\Models\Via;
use App\Services\Louos\LouosDraftService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\ViaSeeder;
use DomainException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * CRUD do cadastro de classes de via da LOUOS (relatório de usabilidade SEDUR
 * 19/09/2026, item 07 — "além do quadro de Zona, aba de Vias para
 * parametrizar"). Espelha o cadastro de zonas: a fonte de verdade é a própria
 * LOUOS — o ViaSeeder popula a partir dos valores distintos de `classe_via`
 * da versão VIGENTE do Quadro 11A, idempotente e preservando os campos
 * administrados no re-seed. O codigo é único e imutável na edição; a
 * desativação preserva o histórico e só bloqueia NOVAS publicações do Quadro
 * 11A que a referenciem. Tudo atrás de manter-louos e auditado (RN-002).
 */
class ViaCrudTest extends TestCase
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
        $this->actingAs($this->analista(), 'gestao')
            ->get('/gestao/louos/vias')
            ->assertForbidden();
    }

    public function test_cria_via(): void
    {
        $this->actingAs($this->administrador(), 'gestao')->post('/gestao/louos/vias', [
            'codigo' => 'VA III',
            'nome' => 'Via Arterial III',
            'ativo' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('vias', [
            'codigo' => 'VA III',
            'nome' => 'Via Arterial III',
            'ativo' => true,
        ]);
    }

    public function test_codigo_duplicado_e_rejeitado(): void
    {
        Via::factory()->create(['codigo' => 'VA I']);

        $this->actingAs($this->administrador(), 'gestao')->post('/gestao/louos/vias', [
            'codigo' => 'VA I',
            'nome' => 'Duplicada',
            'ativo' => true,
        ])->assertSessionHasErrors('codigo');

        $this->assertSame(1, Via::query()->count());
    }

    /**
     * O codigo é a chave que as linhas do Quadro 11A referenciam: imutável na
     * edição — trocar o código é desativar a via e cadastrar a nova.
     */
    public function test_codigo_e_imutavel_na_edicao(): void
    {
        $via = Via::factory()->create(['codigo' => 'VA I', 'nome' => 'Via Arterial I']);

        $this->actingAs($this->administrador(), 'gestao')->put("/gestao/louos/vias/{$via->id}", [
            'codigo' => 'TENTATIVA',
            'nome' => 'Via Arterial I (raiz)',
        ])->assertRedirect();

        $via->refresh();

        $this->assertSame('VA I', $via->codigo);
        $this->assertSame('Via Arterial I (raiz)', $via->nome);
    }

    public function test_edicao_sem_o_campo_situacao_nao_reativa_a_via(): void
    {
        $via = Via::factory()->inativa()->create();

        $this->actingAs($this->administrador(), 'gestao')->put("/gestao/louos/vias/{$via->id}", [
            'nome' => 'Nome novo',
        ])->assertRedirect();

        $via->refresh();

        $this->assertSame('Nome novo', $via->nome);
        $this->assertFalse($via->ativo);
    }

    public function test_toggle_desativa_sem_excluir_e_audita(): void
    {
        $via = Via::factory()->create(['ativo' => true]);

        $this->actingAs($this->administrador(), 'gestao')
            ->put("/gestao/louos/vias/{$via->id}/ativacao")
            ->assertRedirect();

        $this->assertFalse($via->fresh()->ativo);
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => Via::class,
            'subject_id' => $via->id,
        ]);
    }

    /**
     * A fonte da classe de via é a LOUOS: o seeder popula o cadastro a partir
     * dos valores distintos de `classe_via` da versão VIGENTE do Quadro 11A.
     */
    public function test_seeder_popula_vias_do_quadro11a_vigente(): void
    {
        $vigente = RuleVersion::factory()->create([
            'domain' => RuleDomain::LouosQuadro11a,
            'status' => RuleVersionStatus::Vigente,
            'version' => 'quadro11a-v1',
            'valid_from' => now()->subYear()->toDateString(),
        ]);
        LouosQuadro11CondicaoVia::factory()->create(['rule_version_id' => $vigente->id, 'classe_via' => 'VA I']);
        LouosQuadro11CondicaoVia::factory()->create(['rule_version_id' => $vigente->id, 'classe_via' => 'VL']);

        $this->seed(ViaSeeder::class);

        $this->assertSame(2, Via::query()->count());
        $this->assertDatabaseHas('vias', ['codigo' => 'VA I', 'ativo' => true]);
        $this->assertDatabaseHas('vias', ['codigo' => 'VL', 'ativo' => true]);
    }

    /**
     * Sem Quadro 11A vigente o cadastro fica VAZIO — honesto: não se inventa
     * classe de via sem fonte legal.
     */
    public function test_seeder_sem_quadro11a_vigente_fica_vazio(): void
    {
        $this->seed(ViaSeeder::class);

        $this->assertSame(0, Via::query()->count());
    }

    /**
     * Re-seed em deploy NUNCA reativa uma via desativada pela UI nem
     * sobrescreve o nome administrado.
     */
    public function test_reseed_preserva_os_campos_administrados(): void
    {
        $vigente = RuleVersion::factory()->create([
            'domain' => RuleDomain::LouosQuadro11a,
            'status' => RuleVersionStatus::Vigente,
            'version' => 'quadro11a-v1',
            'valid_from' => now()->subYear()->toDateString(),
        ]);
        LouosQuadro11CondicaoVia::factory()->create(['rule_version_id' => $vigente->id, 'classe_via' => 'VA I']);

        $this->seed(ViaSeeder::class);

        $via = Via::query()->where('codigo', 'VA I')->firstOrFail();
        $via->update(['ativo' => false, 'nome' => 'Editada pelo admin']);

        $this->seed(ViaSeeder::class);

        $this->assertSame(1, Via::query()->count());

        $via->refresh();

        $this->assertFalse($via->ativo);
        $this->assertSame('Editada pelo admin', $via->nome);
    }

    /**
     * Borda de publicação do Quadro 11A: a coluna `classe_via` é string livre —
     * um typo publicado degrada a leitura da via no motor. Toda classe do
     * rascunho precisa constar do cadastro de vias ATIVAS.
     */
    public function test_publicar_quadro11a_com_via_fora_do_cadastro_e_rejeitado(): void
    {
        $vigente = RuleVersion::factory()->create([
            'domain' => RuleDomain::LouosQuadro11a,
            'status' => RuleVersionStatus::Vigente,
            'version' => 'quadro11a-v1',
            'valid_from' => now()->subYear()->toDateString(),
        ]);
        LouosQuadro11CondicaoVia::factory()->create(['rule_version_id' => $vigente->id, 'classe_via' => 'VL']);
        Via::factory()->create(['codigo' => 'VL']);

        $service = app(LouosDraftService::class);
        $autor = User::factory()->create();
        $publicador = User::factory()->create();

        $draft = $service->abrirOuRetomar(RuleDomain::LouosQuadro11a, 'q11a-rascunho', $autor->id);
        $service->inserirLinha($draft, [
            'classe_via' => 'VA IX',
            'grupo_uso' => 'nR1',
            'condicoes' => ['Condição de teste'],
        ]);

        try {
            $service->publicar($draft, $publicador->id);
            $this->fail('Era esperado DomainException para classe de via fora do cadastro.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('VA IX', $e->getMessage());
            $this->assertStringNotContainsString('VL', $e->getMessage());
        }

        $this->assertSame(RuleVersionStatus::Rascunho, $draft->fresh()->status);
        $this->assertSame(RuleVersionStatus::Vigente, $vigente->fresh()->status);
    }
}
