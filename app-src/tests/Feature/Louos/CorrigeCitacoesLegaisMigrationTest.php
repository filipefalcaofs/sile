<?php

namespace Tests\Feature\Louos;

use App\Enums\Quadro10Permissao;
use App\Enums\RuleDomain;
use App\Models\DecisionText;
use App\Models\LouosQuadro10Permissao;
use App\Models\RuleVersion;
use App\Models\TerritorialRule;
use Database\Seeders\DecisionTextSeeder;
use Database\Seeders\TerritorialRuleSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Migration de dados das citações legais: bancos já seedados com o texto
 * antigo recebem a citação corrigida; o que a gestão editou fica intacto.
 */
class CorrigeCitacoesLegaisMigrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_10_14_194452_corrige_citacoes_legais_viabilidade.php');
    }

    public function test_atualiza_citacoes_antigas_e_preserva_edicoes_da_gestao(): void
    {
        $this->seed(DecisionTextSeeder::class);
        $this->seed(TerritorialRuleSeeder::class);

        TerritorialRule::query()->where('codigo', 'RT-01')->update([
            'base_legal' => 'art. 172-A da Lei Municipal nº 9.148/2016, com redação dada pela Lei Municipal nº 9.879/2025',
        ]);
        TerritorialRule::query()->where('codigo', 'RT-02-ITAIGARA')->update(['base_legal' => '§3º do art. 162 do PDDU']);
        TerritorialRule::query()->where('codigo', 'RT-02-VELA-BRANCA')->update(['base_legal' => 'Texto revisado pela gestão']);
        DecisionText::query()->where('key', 'base_legal.risco_municipal')->update(['template' => 'Decreto Municipal nº 41.758/2026']);

        $this->migration()->up();

        $this->assertSame(
            'art. 172-A da Lei Municipal nº 9.148/2016 (LOUOS), incluído pelo art. 11 da Lei Municipal nº 9.879/2025',
            TerritorialRule::query()->where('codigo', 'RT-01')->value('base_legal'),
        );
        $this->assertStringStartsWith('art. 18, §3º,', (string) TerritorialRule::query()->where('codigo', 'RT-02-ITAIGARA')->value('base_legal'));
        $this->assertSame('Texto revisado pela gestão', TerritorialRule::query()->where('codigo', 'RT-02-VELA-BRANCA')->value('base_legal'));
        $this->assertSame(
            'Decreto Municipal nº 32.636/2020, com os Anexos na redação do Decreto Municipal nº 41.758/2026',
            DecisionText::query()->where('key', 'base_legal.risco_municipal')->value('template'),
        );
    }

    public function test_liga_observacao_das_cargas_oficiais_sem_sobrescrever_anotacao(): void
    {
        $version = RuleVersion::factory()->create([
            'domain' => RuleDomain::LouosQuadro10,
            'version' => 'lei-9148-2016-quadro10',
        ]);

        $semObservacao = LouosQuadro10Permissao::factory()->create([
            'rule_version_id' => $version->id,
            'zona' => 'ZCMu 2',
            'subgrupo' => 'ID2-05',
            'permissao' => Quadro10Permissao::PermitidoCondicionado,
            'condicionante_ref' => 'Observação (a) do Quadro 10 da Lei nº 9.148/2016',
            'base_legal' => 'Lei nº 9.148/2016 — Quadro 10',
        ]);
        $anotada = LouosQuadro10Permissao::factory()->create([
            'rule_version_id' => $version->id,
            'zona' => 'ZCLMu',
            'subgrupo' => 'ID2-05',
            'permissao' => Quadro10Permissao::PermitidoCondicionado,
            'condicionante_ref' => 'Observação (b) do Quadro 10 da Lei nº 9.148/2016',
            'observacao' => 'Anotação do mantenedor',
        ]);

        $this->migration()->up();

        $this->assertSame('(a)', $semObservacao->fresh()->observacao);
        $this->assertSame('art. 116, I, e Quadro 10 do Anexo 01 da Lei nº 9.148/2016', $semObservacao->fresh()->base_legal);
        $this->assertSame('Anotação do mantenedor', $anotada->fresh()->observacao);

        $this->migration()->down();

        $this->assertNull($semObservacao->fresh()->observacao);
        $this->assertSame('Lei nº 9.148/2016 — Quadro 10', $semObservacao->fresh()->base_legal);
    }
}
