<?php

namespace Tests\Feature\Louos;

use App\Enums\RuleDomain;
use App\Models\Activity;
use App\Models\LouosQuadro7Faixa;
use App\Models\RuleVersion;
use App\Services\Louos\LouosQuadro7ImportService;
use Database\Seeders\LouosQuadro7Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Carga do Quadro 7 da LOUOS (HU-015/HU-038) como dado versionado derivado da
 * Lei nº 9.148/2016 (modelo "Enquadramento TVL" do SAPS): CNAE + faixa de área
 * → grupo/subgrupo de uso. O import valida que as faixas de um mesmo CNAE NÃO se
 * sobrepõem (rejeita sem inserir) e o upsert preserva anotações dos mantenedores
 * no re-import. Tabular → roda em SQLite.
 */
class LouosQuadro7ImportServiceTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = 'cnae,grupo,subgrupo,area_min,area_max,observacao';

    public function test_importa_faixas_do_csv_real(): void
    {
        $this->seed(LouosQuadro7Seeder::class);

        $activity = Activity::query()
            ->where('log_name', 'louos')
            ->where('event', 'importacao-quadro7')
            ->first();

        $this->assertNotNull($activity, 'Esperava o relatório do import do Quadro 7 registrado na auditoria.');
        $this->assertSame('lei-9148-2016-quadro7', $activity->rules_version);

        $totalFaixas = $activity->properties->get('total_faixas');

        $this->assertGreaterThan(0, $totalFaixas, 'O Quadro 7 deveria carregar faixas reais derivadas da Lei 9.148/2016.');
        $this->assertSame(
            $totalFaixas,
            LouosQuadro7Faixa::query()->count(),
            'A contagem de faixas no banco diverge do relatório do import.',
        );
        $this->assertSame([], $activity->properties->get('rejeitados'));
    }

    public function test_rejeita_faixas_sobrepostas_do_mesmo_cnae(): void
    {
        $version = RuleVersion::factory()->create([
            'domain' => RuleDomain::LouosQuadro7,
            'version' => 'teste-quadro7',
        ]);

        $csv = $this->csvTemporario([
            self::HEADER,
            '4712-1/00,nR1,nR1-01,0,350,',
            '4712-1/00,nR2,nR2-01,300,,',
        ]);

        try {
            $report = app(LouosQuadro7ImportService::class)->import($version, $csv);
        } finally {
            unlink($csv);
        }

        $this->assertNotEmpty($report['rejeitados']);
        $this->assertStringContainsString('sobrepost', implode(' ', $report['rejeitados']));
        $this->assertSame(
            0,
            LouosQuadro7Faixa::query()->count(),
            'Nenhuma faixa de um CNAE com sobreposição pode ser inserida.',
        );
    }

    public function test_rejeita_faixa_com_area_min_maior_que_area_max(): void
    {
        $version = RuleVersion::factory()->create([
            'domain' => RuleDomain::LouosQuadro7,
            'version' => 'teste-quadro7',
        ]);

        $csv = $this->csvTemporario([
            self::HEADER,
            '5611-2/01,nR1,nR1-03,500,250,',
        ]);

        try {
            $report = app(LouosQuadro7ImportService::class)->import($version, $csv);
        } finally {
            unlink($csv);
        }

        $this->assertNotEmpty($report['rejeitados']);
        $this->assertSame(0, LouosQuadro7Faixa::query()->count());
    }

    public function test_upsert_preserva_observacao_no_reimport(): void
    {
        $this->seed(LouosQuadro7Seeder::class);

        $version = RuleVersion::vigente(RuleDomain::LouosQuadro7)->first();
        $this->assertNotNull($version);

        $faixa = LouosQuadro7Faixa::query()->firstOrFail();
        $faixa->update(['observacao' => 'Anotação do mantenedor']);

        app(LouosQuadro7ImportService::class)->import(
            $version,
            database_path('data/louos/quadro7-faixas.csv'),
        );

        $this->assertSame('Anotação do mantenedor', $faixa->fresh()->observacao);
    }

    public function test_importacao_e_idempotente(): void
    {
        $this->seed(LouosQuadro7Seeder::class);
        $primeiraContagem = LouosQuadro7Faixa::query()->count();

        $this->seed(LouosQuadro7Seeder::class);

        $this->assertSame($primeiraContagem, LouosQuadro7Faixa::query()->count());
        $this->assertSame(1, RuleVersion::vigente(RuleDomain::LouosQuadro7)->count());
    }

    /**
     * @param  array<int, string>  $linhas
     */
    private function csvTemporario(array $linhas): string
    {
        $caminho = tempnam(sys_get_temp_dir(), 'quadro7-');
        file_put_contents($caminho, implode("\n", $linhas));

        return $caminho;
    }
}
