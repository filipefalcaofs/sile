<?php

namespace Tests\Feature\Louos;

use App\Enums\Quadro10Permissao;
use App\Enums\RuleDomain;
use App\Enums\RuleVersionStatus;
use App\Models\LouosQuadro10Permissao;
use App\Models\RuleVersion;
use App\Models\User;
use App\Models\Zona;
use App\Services\Louos\LouosDraftService;
use Database\Seeders\LouosQuadro7Seeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use DomainException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Validação de zonas na borda de publicação do Quadro 10 (parametrização
 * 3.3): a coluna `zona` do Quadro 10 é string livre — um typo publicado vira
 * `nao_encontrado` no motor e degrada CADA processo daquela zona para
 * pendente, silenciosamente. Ao publicar um rascunho do domínio
 * LouosQuadro10, toda zona referenciada precisa constar do cadastro de
 * zonas ATIVAS; a rejeição é DomainException listando as zonas ausentes e
 * vira flash.error no controller (padrão FourEyesViolationException). A
 * validação NÃO se aplica aos demais quadros e NÃO toca a vigente — zona
 * desativada permanece nos quadros históricos e só bloqueia publicação
 * nova. Seeders publicam por RuleVersionService (fora deste caminho), sem
 * problema de bootstrap.
 */
class LouosDraftZonaValidationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function service(): LouosDraftService
    {
        return app(LouosDraftService::class);
    }

    /**
     * Vigente do Quadro 10 com uma permissão por zona informada.
     *
     * @param  list<string>  $zonas
     */
    private function vigenteQuadro10(array $zonas): RuleVersion
    {
        $vigente = RuleVersion::factory()->create([
            'domain' => RuleDomain::LouosQuadro10,
            'status' => RuleVersionStatus::Vigente,
            'version' => 'quadro10-v1',
            'valid_from' => now()->subYear()->toDateString(),
        ]);

        foreach ($zonas as $zona) {
            LouosQuadro10Permissao::factory()->create([
                'rule_version_id' => $vigente->id,
                'zona' => $zona,
                'grupo_uso' => 'nR1',
                'subgrupo' => '',
            ]);
        }

        return $vigente;
    }

    public function test_publicar_com_zona_ausente_do_cadastro_e_rejeitado(): void
    {
        $vigente = $this->vigenteQuadro10(['ZPR-1']);
        Zona::factory()->create(['codigo' => 'ZPR-1']);

        $autor = User::factory()->create();
        $publicador = User::factory()->create();

        $draft = $this->service()->abrirOuRetomar(RuleDomain::LouosQuadro10, 'q10-rascunho', $autor->id);
        $this->service()->inserirLinha($draft, [
            'zona' => 'ZPR-9',
            'grupo_uso' => 'nR3',
            'subgrupo' => '',
            'permissao' => Quadro10Permissao::Permitido->value,
        ]);

        try {
            $this->service()->publicar($draft, $publicador->id);
            $this->fail('Era esperado DomainException para zona fora do cadastro.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('ZPR-9', $e->getMessage());
            $this->assertStringNotContainsString('ZPR-1', $e->getMessage());
        }

        // Rascunho segue rascunho; a vigente permanece intacta.
        $this->assertSame(RuleVersionStatus::Rascunho, $draft->fresh()->status);
        $this->assertSame(RuleVersionStatus::Vigente, $vigente->fresh()->status);
        $this->assertSame($vigente->id, RuleVersion::vigente(RuleDomain::LouosQuadro10)->sole()->id);
    }

    public function test_publicar_com_todas_as_zonas_cadastradas_promove_o_rascunho(): void
    {
        $vigente = $this->vigenteQuadro10(['ZPR-1']);
        Zona::factory()->create(['codigo' => 'ZPR-1']);
        Zona::factory()->create(['codigo' => 'ZPR-9']);

        $autor = User::factory()->create();
        $publicador = User::factory()->create();

        $draft = $this->service()->abrirOuRetomar(RuleDomain::LouosQuadro10, 'q10-rascunho', $autor->id);
        $this->service()->inserirLinha($draft, [
            'zona' => 'ZPR-9',
            'grupo_uso' => 'nR3',
            'subgrupo' => '',
            'permissao' => Quadro10Permissao::Permitido->value,
        ]);

        $publicado = $this->service()->publicar($draft, $publicador->id);

        $this->assertSame(RuleVersionStatus::Vigente, $publicado->status);
        $this->assertSame(RuleVersionStatus::Substituida, $vigente->fresh()->status);
    }

    /**
     * Zona desativada NÃO é removida dos quadros vigentes (histórico
     * preservado); ela só bloqueia NOVA publicação que a referencie.
     */
    public function test_zona_desativada_bloqueia_publicacao_sem_tocar_a_vigente(): void
    {
        $vigente = $this->vigenteQuadro10(['ZPR-1', 'ZPR-2']);
        $totalVigente = LouosQuadro10Permissao::query()->where('rule_version_id', $vigente->id)->count();

        Zona::factory()->create(['codigo' => 'ZPR-1']);
        Zona::factory()->inativa()->create(['codigo' => 'ZPR-2']);

        $autor = User::factory()->create();
        $publicador = User::factory()->create();

        $draft = $this->service()->abrirOuRetomar(RuleDomain::LouosQuadro10, 'q10-rascunho', $autor->id);

        try {
            $this->service()->publicar($draft, $publicador->id);
            $this->fail('Era esperado DomainException para zona desativada.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('ZPR-2', $e->getMessage());
        }

        // A vigente continua vigente e com a zona desativada intacta.
        $this->assertSame(RuleVersionStatus::Vigente, $vigente->fresh()->status);
        $this->assertSame(
            $totalVigente,
            LouosQuadro10Permissao::query()->where('rule_version_id', $vigente->id)->count(),
        );
        $this->assertDatabaseHas('louos_quadro10_permissoes', [
            'rule_version_id' => $vigente->id,
            'zona' => 'ZPR-2',
        ]);
    }

    /**
     * A guarda é exclusiva do Quadro 10: os demais quadros publicam sem
     * nenhuma zona cadastrada.
     */
    public function test_validacao_nao_se_aplica_aos_demais_quadros(): void
    {
        $this->seed(LouosQuadro7Seeder::class);

        $this->assertSame(0, Zona::query()->count());

        $autor = User::factory()->create();
        $publicador = User::factory()->create();

        $draft = $this->service()->abrirOuRetomar(RuleDomain::LouosQuadro7, 'q7-rascunho', $autor->id);

        $publicado = $this->service()->publicar($draft, $publicador->id);

        $this->assertSame(RuleVersionStatus::Vigente, $publicado->status);
    }

    /**
     * O erro de validação vira flash.error no controller do rascunho —
     * mesmo padrão da violação de quatro olhos.
     */
    public function test_endpoint_de_publicacao_retorna_flash_error_com_a_zona(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->vigenteQuadro10(['ZPR-1']);
        Zona::factory()->create(['codigo' => 'ZPR-1']);

        $autor = User::factory()->administrador()->withAcceptedLgpdTerm()->create();
        $publicador = User::factory()->administrador()->withAcceptedLgpdTerm()->create();

        $draft = $this->service()->abrirOuRetomar(RuleDomain::LouosQuadro10, 'q10-rascunho', $autor->id);
        $this->service()->inserirLinha($draft, [
            'zona' => 'ZPR-9',
            'grupo_uso' => 'nR3',
            'subgrupo' => '',
            'permissao' => Quadro10Permissao::Permitido->value,
        ]);

        $this->actingAs($publicador, 'gestao')
            ->put('/gestao/louos/rascunho/publicar', ['quadro' => 'quadro10'])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertStringContainsString('ZPR-9', (string) session('error'));
        $this->assertSame(RuleVersionStatus::Rascunho, $draft->fresh()->status);
    }
}
