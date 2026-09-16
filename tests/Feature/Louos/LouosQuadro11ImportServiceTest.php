<?php

namespace Tests\Feature\Louos;

use App\Enums\RuleDomain;
use App\Models\LouosQuadro11CondicaoVia;
use App\Models\RuleVersion;
use App\Services\Louos\LouosQuadro11ImportService;
use Database\Seeders\LouosQuadro11Seeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\TestCase;

/**
 * Importação do Quadro 11A da LOUOS (condições de instalação pela via —
 * HU-017/HU-018/HU-040/HU-041). O "Quadro 11" não existe na publicação
 * oficial da SEDUR — apenas 11A e 11B. Formato CSV: classe_via, grupo_uso,
 * condicoes (lista separada por ';'), base_legal. Tabular → roda em SQLite.
 */
class LouosQuadro11ImportServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_importa_linhas_no_formato_novo(): void
    {
        $csv = "classe_via,grupo_uso,condicoes,base_legal\n"
            ."Arterial I,nR1-01,\"Estacionamento nos fundos; acesso único\",Art. 92\n"
            ."Local,nR1-01,,\n";

        $path = tempnam(sys_get_temp_dir(), 'quadro11a_').'.csv';
        File::put($path, $csv);

        $version = RuleVersion::factory()->create([
            'domain' => RuleDomain::LouosQuadro11a,
            'version' => 'teste-formato-novo',
        ]);

        $service = app(LouosQuadro11ImportService::class);
        $report = $service->import($version, $path);

        $this->assertSame(2, $report['lidos']);
        $this->assertSame(2, $report['importados']);
        $this->assertSame(0, $report['atualizados']);
        $this->assertSame([], $report['rejeitados']);

        File::delete($path);
    }

    public function test_condicoes_separadas_por_ponto_e_virgula_viram_array(): void
    {
        $csv = "classe_via,grupo_uso,condicoes,base_legal\n"
            ."Arterial I,nR1-01,\"Estacionamento nos fundos; acesso único\",Art. 92\n";

        $path = tempnam(sys_get_temp_dir(), 'quadro11a_').'.csv';
        File::put($path, $csv);

        $version = RuleVersion::factory()->create([
            'domain' => RuleDomain::LouosQuadro11a,
            'version' => 'teste-condicoes-array',
        ]);

        app(LouosQuadro11ImportService::class)->import($version, $path);

        $condicao = LouosQuadro11CondicaoVia::query()
            ->where('rule_version_id', $version->getKey())
            ->firstOrFail();

        $this->assertSame(['Estacionamento nos fundos', 'acesso único'], $condicao->condicoes);

        File::delete($path);
    }

    public function test_linha_sem_condicoes_grava_null(): void
    {
        $csv = "classe_via,grupo_uso,condicoes,base_legal\n"
            ."Local,nR1-01,,\n";

        $path = tempnam(sys_get_temp_dir(), 'quadro11a_').'.csv';
        File::put($path, $csv);

        $version = RuleVersion::factory()->create([
            'domain' => RuleDomain::LouosQuadro11a,
            'version' => 'teste-sem-condicoes',
        ]);

        app(LouosQuadro11ImportService::class)->import($version, $path);

        $condicao = LouosQuadro11CondicaoVia::query()
            ->where('rule_version_id', $version->getKey())
            ->firstOrFail();

        $this->assertNull($condicao->condicoes);

        File::delete($path);
    }

    public function test_reimport_faz_upsert(): void
    {
        $csv = "classe_via,grupo_uso,condicoes,base_legal\n"
            ."Arterial I,nR1-01,\"Estacionamento nos fundos; acesso único\",Art. 92\n"
            ."Local,nR1-01,,\n";

        $path = tempnam(sys_get_temp_dir(), 'quadro11a_').'.csv';
        File::put($path, $csv);

        $version = RuleVersion::factory()->create([
            'domain' => RuleDomain::LouosQuadro11a,
            'version' => 'teste-upsert',
        ]);

        $service = app(LouosQuadro11ImportService::class);
        $service->import($version, $path);
        $report = $service->import($version, $path);

        $this->assertSame(0, $report['importados']);
        $this->assertSame(2, $report['atualizados']);
        $this->assertSame(
            2,
            LouosQuadro11CondicaoVia::query()->where('rule_version_id', $version->getKey())->count(),
        );

        File::delete($path);
    }

    public function test_cabecalho_antigo_com_coluna_quadro_lanca_excecao(): void
    {
        $csv = "quadro,classe_via,grupo_uso,condicoes,base_legal\n"
            ."11a,Arterial I,nR1-01,\"Condição A\",Art. 92\n";

        $path = tempnam(sys_get_temp_dir(), 'quadro11a_').'.csv';
        File::put($path, $csv);

        $version = RuleVersion::factory()->create([
            'domain' => RuleDomain::LouosQuadro11a,
            'version' => 'teste-cabecalho-antigo',
        ]);

        $this->expectException(RuntimeException::class);

        app(LouosQuadro11ImportService::class)->import($version, $path);

        File::delete($path);
    }

    public function test_seeder_publica_apenas_versao_do_11a(): void
    {
        $this->seed(LouosQuadro11Seeder::class);

        $this->assertSame(0, RuleVersion::vigente(RuleDomain::LouosQuadro11)->count());
        $this->assertSame(1, RuleVersion::vigente(RuleDomain::LouosQuadro11a)->count());

        $version11a = RuleVersion::vigente(RuleDomain::LouosQuadro11a)->first();

        $this->assertGreaterThan(
            0,
            LouosQuadro11CondicaoVia::query()->where('rule_version_id', $version11a->getKey())->count(),
        );
    }

    public function test_seeder_e_idempotente(): void
    {
        $this->seed(LouosQuadro11Seeder::class);
        $contagem = LouosQuadro11CondicaoVia::query()->count();

        $this->seed(LouosQuadro11Seeder::class);

        $this->assertGreaterThan(0, $contagem);
        $this->assertSame($contagem, LouosQuadro11CondicaoVia::query()->count());
        $this->assertSame(0, RuleVersion::vigente(RuleDomain::LouosQuadro11)->count());
        $this->assertSame(1, RuleVersion::vigente(RuleDomain::LouosQuadro11a)->count());
    }
}
