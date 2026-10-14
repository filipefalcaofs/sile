<?php

namespace Tests\Feature\Louos;

use App\Enums\Quadro10Permissao;
use App\Enums\RuleDomain;
use App\Models\LouosQuadro10Permissao;
use App\Models\RuleVersion;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Cargas do Quadro 10 feitas pelo importador antigo gravaram a observação com
 * a letra maiúscula ("Observação (A) ..."), que a migration da 0.64.0 não
 * reconhecia: a observação (a)/(b) ficava sem a verificação da allow-list.
 */
class LigaObservacaoQuadro10MigrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function migration(): object
    {
        $arquivo = collect(glob(database_path('migrations/*_liga_observacao_quadro10_letra_maiuscula.php')))->first();

        $this->assertNotNull($arquivo, 'Migration liga_observacao_quadro10_letra_maiuscula não encontrada.');

        return require $arquivo;
    }

    private function permissao(string $zona, string $condicionanteRef, ?string $observacao = null): LouosQuadro10Permissao
    {
        $version = RuleVersion::query()->where('domain', RuleDomain::LouosQuadro10)->first()
            ?? RuleVersion::factory()->create(['domain' => RuleDomain::LouosQuadro10, 'version' => 'lei-9148-2016-quadro10']);

        return LouosQuadro10Permissao::factory()->create([
            'rule_version_id' => $version->id,
            'zona' => $zona,
            'grupo_uso' => 'ID2',
            'subgrupo' => 'ID2-05',
            'permissao' => Quadro10Permissao::PermitidoCondicionado,
            'condicionante_ref' => $condicionanteRef,
            'observacao' => $observacao,
        ]);
    }

    public function test_liga_observacao_gravada_com_letra_maiuscula(): void
    {
        $a = $this->permissao('ZCMu 2', 'Observação (A) do Quadro 10 da Lei nº 9.148/2016');
        $b = $this->permissao('ZCLMu', 'Observação (B) do Quadro 10 da Lei nº 9.148/2016');

        $this->migration()->up();

        $this->assertSame('(a)', $a->fresh()->observacao);
        $this->assertSame('(b)', $b->fresh()->observacao);
    }

    public function test_nao_toca_observacao_c_nem_anotacao_do_mantenedor(): void
    {
        $c = $this->permissao('ZPR 2', 'Observação (C) do Quadro 10 da Lei nº 9.148/2016');
        $anotada = $this->permissao('ZCMu 2', 'Observação (A) do Quadro 10 da Lei nº 9.148/2016', 'Conferido com a SEDUR');

        $this->migration()->up();

        $this->assertNull($c->fresh()->observacao);
        $this->assertSame('Conferido com a SEDUR', $anotada->fresh()->observacao);
    }

    public function test_down_desfaz_so_o_que_a_migration_ligou(): void
    {
        $a = $this->permissao('ZCMu 2', 'Observação (A) do Quadro 10 da Lei nº 9.148/2016');
        $anotada = $this->permissao('ZCLMu', 'Observação (B) do Quadro 10 da Lei nº 9.148/2016', 'Conferido com a SEDUR');

        $migration = $this->migration();
        $migration->up();
        $migration->down();

        $this->assertNull($a->fresh()->observacao);
        $this->assertSame('Conferido com a SEDUR', $anotada->fresh()->observacao);
    }
}
