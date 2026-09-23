<?php

namespace Tests\Feature\Louos;

use App\Enums\Quadro10Permissao;
use App\Enums\RuleDomain;
use App\Models\LouosQuadro10Permissao;
use App\Models\RuleVersion;
use App\Services\Geo\TerritoryResult;
use App\Services\Louos\EnquadramentoInput;
use App\Services\Louos\EnquadramentoResult;
use App\Services\Louos\LouosEnquadramentoService;
use Database\Seeders\DecisionTextSeeder;
use Database\Seeders\TerritorialRuleSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Support\SeedsTratamentoPlanilha;
use Tests\TestCase;

/**
 * Observações (a)/(b) do Quadro 10 (RT-05 — pacote SEDUR 2026-08-28): o uso
 * cuja célula do quadro carrega o marcador de observação só é permitido nas
 * localizações da allow-list versionada (RT-05-A: via 578 + bairros Calçada e
 * São Cristóvão; RT-05-B: as oito ZCLMu). Fora da lista → proibido
 * (CA-RT-08); sem CODLOG/bairro para verificar → indisponível, e o
 * consolidado degrada para pendente (nunca permissão inventada).
 */
class LouosQuadro10ObservacaoTest extends TestCase
{
    use LazilyRefreshDatabase;
    use SeedsTratamentoPlanilha;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DecisionTextSeeder::class);
        $this->seed(TerritorialRuleSeeder::class);
        $this->seedTratamentoPlanilha();
    }

    private function service(): LouosEnquadramentoService
    {
        return app(LouosEnquadramentoService::class);
    }

    private function permissaoComObservacao(string $observacao): void
    {
        $version = RuleVersion::vigente(RuleDomain::LouosQuadro10)->first()
            ?? RuleVersion::factory()->create([
                'domain' => RuleDomain::LouosQuadro10,
                'version' => 'lei-9148-2016-quadro10',
                'rules_version' => 'lei-9148-2016-quadro10',
            ]);

        LouosQuadro10Permissao::factory()->create([
            'rule_version_id' => $version->id,
            'zona' => 'ZCMu-2',
            'grupo_uso' => 'nR1',
            'subgrupo' => '',
            'permissao' => Quadro10Permissao::Permitido,
            'observacao' => $observacao,
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $viaPropriedades
     */
    private function territorio(?array $viaPropriedades, ?string $bairro): TerritoryResult
    {
        $dim = fn (string $status, ?string $nome, ?array $propriedades = null): array => [
            'status' => $status,
            'nome' => $nome,
            'propriedades' => $propriedades,
            'motivo' => null,
            'versao_camada' => 'teste',
        ];

        return new TerritoryResult(
            bairro: $bairro !== null ? $dim('identificado', $bairro) : $dim('nao_encontrado', null),
            via: $viaPropriedades !== null
                ? $dim('identificado', 'Avenida Aliomar Baleeiro', $viaPropriedades) + ['distancia_m' => 1.0]
                : $dim('nao_encontrado', null) + ['distancia_m' => null],
            zona: $dim('identificado', 'ZCMu-2', ['NOME' => 'ZCMu-2']),
            lote: $dim('nao_encontrado', null),
            restricoes: ['status' => 'nao_encontrado', 'itens' => [], 'motivo' => null, 'versao_camada' => null],
        );
    }

    private function enquadrar(TerritoryResult $territorio): EnquadramentoResult
    {
        return $this->service()->enquadrar(
            EnquadramentoInput::paraConsulta(100, '4712-1/00', $territorio, [11 => true]),
        );
    }

    // CA-RT-08
    public function test_uso_com_observacao_fora_das_localizacoes_nao_e_permitido(): void
    {
        $this->permissaoComObservacao('(a)');

        $quadro10 = $this->enquadrar($this->territorio(['CODLOG' => '999'], 'Pituba'))->quadro10;

        $this->assertSame(EnquadramentoResult::STATUS_IDENTIFICADO, $quadro10['status']);
        $this->assertSame(Quadro10Permissao::Proibido->value, $quadro10['permissao']);
        $this->assertStringContainsString('(a)', (string) $quadro10['motivo']);
    }

    public function test_uso_com_observacao_na_via_listada_e_permitido(): void
    {
        $this->permissaoComObservacao('(a)');

        $quadro10 = $this->enquadrar($this->territorio(['CODLOG' => '578'], 'Pituba'))->quadro10;

        $this->assertSame(EnquadramentoResult::STATUS_IDENTIFICADO, $quadro10['status']);
        $this->assertSame(Quadro10Permissao::Permitido->value, $quadro10['permissao']);
    }

    public function test_uso_com_observacao_no_bairro_listado_e_permitido(): void
    {
        $this->permissaoComObservacao('(a)');

        $quadro10 = $this->enquadrar($this->territorio(['CODLOG' => '999'], 'Calçada'))->quadro10;

        $this->assertSame(Quadro10Permissao::Permitido->value, $quadro10['permissao']);
    }

    public function test_uso_com_observacao_sem_codlog_nem_bairro_degrada_para_pendente(): void
    {
        $this->permissaoComObservacao('(a)');

        $result = $this->enquadrar($this->territorio(null, null));

        $this->assertSame(EnquadramentoResult::STATUS_INDISPONIVEL, $result->quadro10['status']);
        $this->assertSame('pendente', $result->consolidado['resultado']);
    }

    public function test_linha_sem_observacao_segue_o_fluxo_normal(): void
    {
        $version = RuleVersion::vigente(RuleDomain::LouosQuadro10)->first()
            ?? RuleVersion::factory()->create([
                'domain' => RuleDomain::LouosQuadro10,
                'version' => 'lei-9148-2016-quadro10',
                'rules_version' => 'lei-9148-2016-quadro10',
            ]);

        LouosQuadro10Permissao::factory()->create([
            'rule_version_id' => $version->id,
            'zona' => 'ZCMu-2',
            'grupo_uso' => 'nR1',
            'subgrupo' => '',
            'permissao' => Quadro10Permissao::Permitido,
            'observacao' => null,
        ]);

        $quadro10 = $this->enquadrar($this->territorio(['CODLOG' => '999'], 'Pituba'))->quadro10;

        $this->assertSame(Quadro10Permissao::Permitido->value, $quadro10['permissao']);
    }
}
