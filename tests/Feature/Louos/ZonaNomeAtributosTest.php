<?php

namespace Tests\Feature\Louos;

use App\Enums\Quadro10Permissao;
use App\Enums\RuleDomain;
use App\Models\LouosQuadro10Permissao;
use App\Models\LouosQuadro7Faixa;
use App\Models\RuleVersion;
use App\Services\Geo\TerritoryResult;
use App\Services\Louos\EnquadramentoInput;
use App\Services\Louos\EnquadramentoResult;
use App\Services\Louos\LouosEnquadramentoService;
use App\Support\Audit\AuditService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Parâmetro geo.zona.atributos_nome (HU-014, item 3.4): a lista de atributos
 * da feição da camada de zona candidatos a nome/código da zona NÃO é fixa no
 * código — o admin ajusta sem deploy quando a base oficial da SEDUR chegar.
 */
class ZonaNomeAtributosTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function service(): LouosEnquadramentoService
    {
        return new LouosEnquadramentoService(app(AuditService::class));
    }

    private function quadro7Faixa(string $cnae, string $grupo, string $subgrupo): void
    {
        $version = RuleVersion::factory()->create([
            'domain' => RuleDomain::LouosQuadro7,
            'version' => 'lei-9148-2016-quadro7',
            'rules_version' => 'lei-9148-2016-quadro7',
        ]);

        LouosQuadro7Faixa::factory()->create([
            'rule_version_id' => $version->id,
            'cnae_code' => $cnae,
            'grupo' => $grupo,
            'subgrupo' => $subgrupo,
            'area_min' => 0,
            'area_max' => null,
        ]);
    }

    private function quadro10Permissao(string $zona, string $grupoUso, Quadro10Permissao $permissao): void
    {
        $version = RuleVersion::vigente(RuleDomain::LouosQuadro10)->first()
            ?? RuleVersion::factory()->create([
                'domain' => RuleDomain::LouosQuadro10,
                'version' => 'lei-9148-2016-quadro10',
                'rules_version' => 'lei-9148-2016-quadro10',
            ]);

        LouosQuadro10Permissao::factory()->create([
            'rule_version_id' => $version->id,
            'zona' => $zona,
            'grupo_uso' => $grupoUso,
            'subgrupo' => '',
            'permissao' => $permissao,
        ]);
    }

    /**
     * @param  array<string, mixed>  $propriedades
     */
    private function territorioComZonaSemNome(array $propriedades): TerritoryResult
    {
        $dimVazia = [
            'status' => 'nao_encontrado',
            'nome' => null,
            'propriedades' => null,
            'motivo' => null,
            'versao_camada' => null,
        ];

        return new TerritoryResult(
            bairro: $dimVazia,
            via: $dimVazia + ['distancia_m' => null],
            zona: [
                'status' => 'identificado',
                'nome' => null,
                'propriedades' => $propriedades,
                'motivo' => null,
                'versao_camada' => 'zoneamento-louos-2026',
            ],
            lote: $dimVazia,
            restricoes: ['status' => 'nao_encontrado', 'itens' => [], 'motivo' => null, 'versao_camada' => null],
        );
    }

    public function test_lista_customizada_de_atributos_e_honrada(): void
    {
        // A base oficial chega com o nome da zona em NM_ZONA — o admin aponta
        // o parâmetro e o motor passa a ler, sem deploy.
        config(['sile.geo.zona.atributos_nome' => ['NM_ZONA']]);

        $this->quadro7Faixa('4712100', 'nR1', 'nR1-01');
        $this->quadro10Permissao('ZR-9', 'nR1', Quadro10Permissao::Permitido);

        $input = EnquadramentoInput::paraConsulta(100, '4712-1/00', $this->territorioComZonaSemNome(
            ['NM_ZONA' => 'ZR-9'],
        ));

        $quadro10 = $this->service()->enquadrar($input)->quadro10;

        $this->assertSame(EnquadramentoResult::STATUS_IDENTIFICADO, $quadro10['status']);
        $this->assertSame(Quadro10Permissao::Permitido->value, $quadro10['permissao']);
        $this->assertStringContainsString('ZR-9', (string) $quadro10['motivo']);
    }

    public function test_lista_padrao_resolve_zona_pelos_atributos_usuais(): void
    {
        // Sem configuração customizada, o fallback (config/sile.php) mantém os
        // 4 atributos atuais: ZONA, zona, SIGLA_ZONA, SUBZONA.
        $this->quadro7Faixa('4712100', 'nR1', 'nR1-01');
        $this->quadro10Permissao('ZPAM', 'nR1', Quadro10Permissao::Proibido);

        $input = EnquadramentoInput::paraConsulta(100, '4712-1/00', $this->territorioComZonaSemNome(
            ['SIGLA_ZONA' => 'ZPAM'],
        ));

        $quadro10 = $this->service()->enquadrar($input)->quadro10;

        $this->assertSame(EnquadramentoResult::STATUS_IDENTIFICADO, $quadro10['status']);
        $this->assertSame(Quadro10Permissao::Proibido->value, $quadro10['permissao']);
    }
}
