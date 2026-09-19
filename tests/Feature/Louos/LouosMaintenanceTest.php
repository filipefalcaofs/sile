<?php

namespace Tests\Feature\Louos;

use App\Enums\Quadro10Permissao;
use App\Enums\RuleDomain;
use App\Enums\RuleVersionStatus;
use App\Exceptions\FourEyesViolationException;
use App\Models\LouosQuadro10Permissao;
use App\Models\RuleVersion;
use App\Models\User;
use App\Services\Louos\LouosMaintenanceService;
use Database\Seeders\LouosQuadro10Seeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Manutenção versionada dos Quadros da LOUOS pelos mantenedores da SEDUR
 * (HU-015..018/HU-046): publicar uma nova versão NÃO sobrescreve a vigente —
 * copia o dado da versão vigente, aplica as alterações e publica por quatro
 * olhos (RuleVersionService reusado da Fase 6, sem recriar infra). A anterior é
 * preservada como histórico (fechada com valid_to), nunca apagada — disciplina
 * de versionamento sem fachada.
 */
class LouosMaintenanceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function service(): LouosMaintenanceService
    {
        return app(LouosMaintenanceService::class);
    }

    public function test_publicacao_quatro_olhos_rejeita_autor_igual_ao_publicador(): void
    {
        $this->seed(LouosQuadro10Seeder::class);

        $mesmo = User::factory()->create();

        $this->expectException(FourEyesViolationException::class);

        $this->service()->publishNewVersion(
            RuleDomain::LouosQuadro10,
            'lei-9148-2016-quadro10-rev2',
            [],
            $mesmo->id,
            $mesmo->id,
        );
    }

    public function test_publica_nova_versao_do_quadro10_com_alteracao_de_permissao(): void
    {
        $this->seed(LouosQuadro10Seeder::class);

        $anterior = RuleVersion::vigente(RuleDomain::LouosQuadro10)->first();
        $total = LouosQuadro10Permissao::query()->where('rule_version_id', $anterior->id)->count();

        $autor = User::factory()->create();
        $publicador = User::factory()->create();

        $nova = $this->service()->publishNewVersion(
            RuleDomain::LouosQuadro10,
            'lei-9148-2016-quadro10-rev2',
            [
                // ZPR 1 / nR1-08 deixa de ser proibido e passa a permitido condicionado.
                [
                    'zona' => 'ZPR 1',
                    'grupo_uso' => 'nR1',
                    'subgrupo' => 'nR1-08',
                    'permissao' => Quadro10Permissao::PermitidoCondicionado->value,
                    'condicionante_ref' => 'CU-09',
                ],
            ],
            $autor->id,
            $publicador->id,
        );

        $this->assertSame(RuleVersionStatus::Substituida, $anterior->fresh()->status);
        $this->assertSame($nova->id, RuleVersion::vigente(RuleDomain::LouosQuadro10)->first()->id);

        // Permissões copiadas (mesma contagem) + alteração aplicada.
        $this->assertSame($total, LouosQuadro10Permissao::query()->where('rule_version_id', $nova->id)->count());

        $alterada = LouosQuadro10Permissao::query()
            ->where('rule_version_id', $nova->id)
            ->where('zona', 'ZPR 1')
            ->where('grupo_uso', 'nR1')
            ->where('subgrupo', 'nR1-08')
            ->first();
        $this->assertSame(Quadro10Permissao::PermitidoCondicionado, $alterada->permissao);

        // A anterior preservou a proibição original.
        $original = LouosQuadro10Permissao::query()
            ->where('rule_version_id', $anterior->id)
            ->where('zona', 'ZPR 1')
            ->where('grupo_uso', 'nR1')
            ->where('subgrupo', 'nR1-08')
            ->first();
        $this->assertSame(Quadro10Permissao::Proibido, $original->permissao);
    }
}
