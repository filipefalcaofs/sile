<?php

namespace Tests\Feature\Louos;

use App\Enums\RuleDomain;
use App\Enums\RuleVersionStatus;
use App\Models\LouosQuadro10Permissao;
use App\Models\RuleVersion;
use App\Models\User;
use App\Models\Zona;
use Database\Seeders\LouosQuadro10Seeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\ZonaSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * CRUD do cadastro de zonas urbanísticas da LOUOS (parametrização 3.3): a
 * fonte de verdade da zona é a própria LOUOS — o ZonaSeeder popula a partir
 * dos valores distintos de `zona` da versão VIGENTE do Quadro 10 (sem
 * depender de arquivo), de forma idempotente e preservando os campos
 * administrados (nome/macrozona/ativo) no re-seed. O codigo é único e
 * imutável na edição (padrão código/CNAE); a desativação preserva o
 * histórico (toggle, nunca exclui) e só bloqueia NOVAS publicações do
 * Quadro 10. Tudo atrás de manter-louos (reuso: a zona é artefato da LOUOS,
 * mesmo mantenedor dos quadros) e auditado (RN-002). Espelha o
 * GeoServerLayerCrudTest.
 */
class ZonaCrudTest extends TestCase
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
        // Analista acessa a gestão e consulta os Quadros, mas NÃO tem manter-louos.
        $this->actingAs($this->analista(), 'gestao')
            ->get('/gestao/louos/zonas')
            ->assertForbidden();
    }

    public function test_cria_zona(): void
    {
        $this->actingAs($this->administrador(), 'gestao')->post('/gestao/louos/zonas', [
            'codigo' => 'ZPR 4',
            'nome' => 'Zona Preferencial Residencial 4',
            'macrozona' => 'Macrozona Norte',
            'ativo' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('zonas', [
            'codigo' => 'ZPR 4',
            'nome' => 'Zona Preferencial Residencial 4',
            'macrozona' => 'Macrozona Norte',
            'ativo' => true,
        ]);
    }

    public function test_codigo_duplicado_e_rejeitado(): void
    {
        Zona::factory()->create(['codigo' => 'ZPR 1']);

        $this->actingAs($this->administrador(), 'gestao')->post('/gestao/louos/zonas', [
            'codigo' => 'ZPR 1',
            'nome' => 'Duplicada',
            'ativo' => true,
        ])->assertSessionHasErrors('codigo');

        $this->assertSame(1, Zona::query()->count());
    }

    /**
     * O codigo é a chave que o Quadro 10 referencia: imutável na edição
     * (padrão código/CNAE) — trocar o código é desativar a zona e cadastrar
     * a nova, preservando a trilha.
     */
    public function test_codigo_e_imutavel_na_edicao(): void
    {
        $zona = Zona::factory()->create(['codigo' => 'ZPR 1', 'nome' => 'Zona Preferencial Residencial 1']);

        $this->actingAs($this->administrador(), 'gestao')->put("/gestao/louos/zonas/{$zona->id}", [
            'codigo' => 'TENTATIVA',
            'nome' => 'ZPR 1 (raiz)',
            'macrozona' => 'Macrozona Central',
        ])->assertRedirect();

        $zona->refresh();

        $this->assertSame('ZPR 1', $zona->codigo);
        $this->assertSame('ZPR 1 (raiz)', $zona->nome);
        $this->assertSame('Macrozona Central', $zona->macrozona);
    }

    /**
     * Um PUT que não envia `ativo` PRESERVA a situação atual — não reativa
     * uma zona desativada por acidente (o que voltaria a permitir publicar
     * quadros que a referenciam).
     */
    public function test_edicao_sem_o_campo_situacao_nao_reativa_a_zona(): void
    {
        $zona = Zona::factory()->inativa()->create();

        $this->actingAs($this->administrador(), 'gestao')->put("/gestao/louos/zonas/{$zona->id}", [
            'nome' => 'Nome novo',
        ])->assertRedirect();

        $zona->refresh();

        $this->assertSame('Nome novo', $zona->nome);
        $this->assertFalse($zona->ativo);
    }

    public function test_toggle_desativa_sem_excluir_e_audita(): void
    {
        $zona = Zona::factory()->create(['ativo' => true]);

        $this->actingAs($this->administrador(), 'gestao')
            ->put("/gestao/louos/zonas/{$zona->id}/ativacao")
            ->assertRedirect();

        $this->assertFalse($zona->fresh()->ativo);
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => Zona::class,
            'subject_id' => $zona->id,
        ]);
    }

    /**
     * A fonte da zona é a LOUOS: o seeder popula o cadastro a partir dos
     * valores distintos de `zona` da versão VIGENTE do Quadro 10 — a matriz
     * oficial da Lei 9.148/2016 tem 21 zonas (1.323 células).
     */
    public function test_seeder_popula_zonas_do_quadro10_vigente(): void
    {
        $this->seed(LouosQuadro10Seeder::class);
        $this->seed(ZonaSeeder::class);

        $this->assertSame(21, Zona::query()->count());
        $this->assertDatabaseHas('zonas', ['codigo' => 'ZPR 1', 'ativo' => true]);
        $this->assertDatabaseHas('zonas', ['codigo' => 'ZEIS 1', 'ativo' => true]);
    }

    /**
     * Sem Quadro 10 vigente o cadastro fica VAZIO — honesto: não se inventa
     * zona sem fonte legal.
     */
    public function test_seeder_sem_quadro10_vigente_fica_vazio(): void
    {
        $this->seed(ZonaSeeder::class);

        $this->assertSame(0, Zona::query()->count());
    }

    /**
     * Re-seed em deploy NUNCA reativa uma zona desativada pela UI nem
     * sobrescreve nome/macrozona administrados (padrão GeoServerLayerSeeder):
     * o seeder só cria as ausentes; as existentes ficam intactas.
     */
    public function test_reseed_preserva_os_campos_administrados(): void
    {
        $vigente = RuleVersion::factory()->create([
            'domain' => RuleDomain::LouosQuadro10,
            'status' => RuleVersionStatus::Vigente,
            'version' => 'quadro10-v1',
            'valid_from' => now()->subYear()->toDateString(),
        ]);
        LouosQuadro10Permissao::factory()->create(['rule_version_id' => $vigente->id, 'zona' => 'ZPR-1']);
        LouosQuadro10Permissao::factory()->create(['rule_version_id' => $vigente->id, 'zona' => 'ZPR-2']);

        $this->seed(ZonaSeeder::class);

        $this->assertSame(2, Zona::query()->count());

        $zona = Zona::query()->where('codigo', 'ZPR-1')->firstOrFail();
        $zona->update(['ativo' => false, 'nome' => 'Editada pelo admin', 'macrozona' => 'Macrozona X']);

        $this->seed(ZonaSeeder::class);

        $this->assertSame(2, Zona::query()->count());

        $zona->refresh();

        $this->assertFalse($zona->ativo);
        $this->assertSame('Editada pelo admin', $zona->nome);
        $this->assertSame('Macrozona X', $zona->macrozona);
    }
}
