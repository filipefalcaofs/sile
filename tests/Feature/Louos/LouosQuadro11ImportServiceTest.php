<?php

namespace Tests\Feature\Louos;

use App\Enums\RuleDomain;
use App\Models\LouosQuadro11CondicaoVia;
use App\Models\RuleVersion;
use App\Services\Louos\LouosQuadro11ImportService;
use Database\Seeders\LouosQuadro11Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Carga dos Quadros 11 e 11A da LOUOS (condições de instalação pela via —
 * HU-017/HU-018/HU-040/HU-041) como dado MODELADO derivado da Lei nº 9.148/2016
 * (atributo viário pendente SEDUR). Um único CSV serve aos dois domínios: o
 * import filtra pela coluna `quadro` e o seeder publica DUAS versões vigentes
 * (louos_quadro11 e louos_quadro11a). As condições são JSON → array. Tabular →
 * roda em SQLite.
 */
class LouosQuadro11ImportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_importa_apenas_as_linhas_do_quadro_informado(): void
    {
        $csvPath = database_path('data/louos/quadro11-condicoes-via.csv');

        $version11 = RuleVersion::factory()->create([
            'domain' => RuleDomain::LouosQuadro11,
            'version' => 'teste-11',
        ]);
        $version11a = RuleVersion::factory()->create([
            'domain' => RuleDomain::LouosQuadro11a,
            'version' => 'teste-11a',
        ]);

        $service = app(LouosQuadro11ImportService::class);
        $report11 = $service->import($version11, $csvPath, '11');
        $report11a = $service->import($version11a, $csvPath, '11a');

        $this->assertSame([], $report11['rejeitados']);
        $this->assertSame([], $report11a['rejeitados']);
        $this->assertGreaterThan(0, $report11['total']);
        $this->assertGreaterThan(0, $report11a['total']);

        $this->assertSame(
            $report11['total'],
            LouosQuadro11CondicaoVia::query()->where('rule_version_id', $version11->getKey())->count(),
        );
        $this->assertSame(
            $report11a['total'],
            LouosQuadro11CondicaoVia::query()->where('rule_version_id', $version11a->getKey())->count(),
        );

        $condicao = LouosQuadro11CondicaoVia::query()
            ->where('rule_version_id', $version11->getKey())
            ->firstOrFail();

        $this->assertIsArray($condicao->condicoes);
    }

    public function test_seeder_publica_as_duas_versoes_vigentes(): void
    {
        $this->seed(LouosQuadro11Seeder::class);

        $this->assertSame(1, RuleVersion::vigente(RuleDomain::LouosQuadro11)->count());
        $this->assertSame(1, RuleVersion::vigente(RuleDomain::LouosQuadro11a)->count());

        $version11 = RuleVersion::vigente(RuleDomain::LouosQuadro11)->first();
        $version11a = RuleVersion::vigente(RuleDomain::LouosQuadro11a)->first();

        $this->assertGreaterThan(
            0,
            LouosQuadro11CondicaoVia::query()->where('rule_version_id', $version11->getKey())->count(),
        );
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
        $this->assertSame(1, RuleVersion::vigente(RuleDomain::LouosQuadro11)->count());
        $this->assertSame(1, RuleVersion::vigente(RuleDomain::LouosQuadro11a)->count());
    }
}
