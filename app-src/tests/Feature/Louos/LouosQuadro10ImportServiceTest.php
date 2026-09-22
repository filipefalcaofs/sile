<?php

namespace Tests\Feature\Louos;

use App\Enums\Quadro10Permissao;
use App\Enums\RuleDomain;
use App\Models\LouosQuadro10Permissao;
use App\Models\RuleVersion;
use App\Services\Louos\LouosQuadro10ImportService;
use Database\Seeders\LouosQuadro10Seeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Carga do Quadro 10 da LOUOS (permissão por zona — HU-016/HU-039) como dado
 * MODELADO derivado da Lei nº 9.148/2016 (carga oficial pendente SEDUR). O
 * import valida a permissão contra o enum Quadro10Permissao e rejeita valor
 * desconhecido SEM inserir — teste-âncora que espelha a não-sobreposição do
 * Quadro 7. Tabular → roda em SQLite.
 */
class LouosQuadro10ImportServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const HEADER = 'zona,grupo_uso,subgrupo,permissao,condicionante_ref,base_legal';

    public function test_rejeita_permissao_desconhecida_sem_inserir(): void
    {
        $version = RuleVersion::factory()->create([
            'domain' => RuleDomain::LouosQuadro10,
            'version' => 'teste-quadro10',
        ]);

        $csv = $this->csvTemporario([
            self::HEADER,
            'ZPR-1,nR1,,permitido,,Quadro 10 da Lei nº 9.148/2016',
            'ZPR-1,nR3,,talvez,,Quadro 10 da Lei nº 9.148/2016',
        ]);

        try {
            $report = app(LouosQuadro10ImportService::class)->import($version, $csv);
        } finally {
            unlink($csv);
        }

        $this->assertNotEmpty($report['rejeitados']);
        $this->assertStringContainsString('talvez', implode(' ', $report['rejeitados']));

        // A permissão válida do mesmo arquivo entra; a desconhecida não.
        $this->assertSame(1, LouosQuadro10Permissao::query()->count());
        $this->assertFalse(
            LouosQuadro10Permissao::query()->where('grupo_uso', 'nR3')->exists(),
            'A linha com permissão desconhecida não pode ser inserida.',
        );
    }

    public function test_importa_permissoes_validas(): void
    {
        $version = RuleVersion::factory()->create([
            'domain' => RuleDomain::LouosQuadro10,
            'version' => 'teste-quadro10',
        ]);

        $csv = $this->csvTemporario([
            self::HEADER,
            'ZPR-1,nR1,,permitido,,Quadro 10 da Lei nº 9.148/2016',
            'ZM-1,nR2,,permitido_condicionado,CU-01,Quadro 10 da Lei nº 9.148/2016',
            'ZPAM,nR3,,proibido,,Quadro 10 da Lei nº 9.148/2016',
        ]);

        try {
            $report = app(LouosQuadro10ImportService::class)->import($version, $csv);
        } finally {
            unlink($csv);
        }

        $this->assertSame([], $report['rejeitados']);
        $this->assertSame(3, LouosQuadro10Permissao::query()->count());

        $permissoes = LouosQuadro10Permissao::all()->pluck('permissao');

        $this->assertTrue($permissoes->contains(Quadro10Permissao::Permitido));
        $this->assertTrue($permissoes->contains(Quadro10Permissao::PermitidoCondicionado));
        $this->assertTrue($permissoes->contains(Quadro10Permissao::Proibido));
    }

    public function test_aceita_sinais_oficiais_s_n_sc_da_matriz(): void
    {
        $version = RuleVersion::factory()->create([
            'domain' => RuleDomain::LouosQuadro10,
            'version' => 'teste-quadro10-oficial',
        ]);

        $csv = $this->csvTemporario([
            self::HEADER,
            'ZPR 1,nR1,nR1-01,S,,Quadro 10 da Lei nº 9.148/2016',
            'ZPR 1,nR1,nR1-08,S (c),Quadro 12,Quadro 10 da Lei nº 9.148/2016',
            'ZIT,nR2,nR2-01,N,,Quadro 10 da Lei nº 9.148/2016',
        ]);

        try {
            $report = app(LouosQuadro10ImportService::class)->import($version, $csv);
        } finally {
            unlink($csv);
        }

        $this->assertSame([], $report['rejeitados']);
        $this->assertSame(3, LouosQuadro10Permissao::query()->count());
        $this->assertTrue(
            LouosQuadro10Permissao::query()
                ->where('zona', 'ZPR 1')
                ->where('subgrupo', 'nR1-01')
                ->where('permissao', Quadro10Permissao::Permitido)
                ->exists(),
        );
        $this->assertTrue(
            LouosQuadro10Permissao::query()
                ->where('zona', 'ZPR 1')
                ->where('subgrupo', 'nR1-08')
                ->where('permissao', Quadro10Permissao::PermitidoCondicionado)
                ->exists(),
        );
        $this->assertTrue(
            LouosQuadro10Permissao::query()
                ->where('zona', 'ZIT')
                ->where('permissao', Quadro10Permissao::Proibido)
                ->exists(),
        );
    }

    public function test_csv_oficial_do_quadro10_importa_matriz_completa(): void
    {
        $version = RuleVersion::factory()->create([
            'domain' => RuleDomain::LouosQuadro10,
            'version' => 'lei-9148-2016-quadro10-oficial',
        ]);

        $report = app(LouosQuadro10ImportService::class)->import(
            $version,
            database_path('data/louos/oficial/quadro10-permissoes.csv'),
        );

        $this->assertSame([], $report['rejeitados']);
        $this->assertSame(1323, $report['total']);
        $this->assertSame(1323, LouosQuadro10Permissao::query()->where('rule_version_id', $version->id)->count());
        $this->assertSame(21, LouosQuadro10Permissao::query()->where('rule_version_id', $version->id)->distinct()->count('zona'));
        $this->assertTrue(
            LouosQuadro10Permissao::query()
                ->where('zona', 'ZPR 2')
                ->where('subgrupo', 'nR1-08')
                ->where('permissao', Quadro10Permissao::PermitidoCondicionado)
                ->exists(),
            'nR1-08 em ZPR 2 é S(c) na matriz oficial.',
        );
    }

    public function test_seeder_publica_versao_vigente_e_e_idempotente(): void
    {
        $this->seed(LouosQuadro10Seeder::class);
        $contagem = LouosQuadro10Permissao::query()->count();

        $this->seed(LouosQuadro10Seeder::class);

        $this->assertGreaterThan(0, $contagem);
        $this->assertSame($contagem, LouosQuadro10Permissao::query()->count());
        $this->assertSame(1, RuleVersion::vigente(RuleDomain::LouosQuadro10)->count());
    }

    /**
     * @param  array<int, string>  $linhas
     */
    private function csvTemporario(array $linhas): string
    {
        $caminho = tempnam(sys_get_temp_dir(), 'quadro10-');
        file_put_contents($caminho, implode("\n", $linhas));

        return $caminho;
    }
}
