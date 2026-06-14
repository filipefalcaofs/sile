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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Motor de enquadramento da LOUOS (HU-039/HU-040/HU-041) — dimensões
 * territoriais: Quadro 10 (permissão por zona) e Quadros 11/11A (condições pela
 * via). O motor RECEBE o território (TerritoryResult da Fase 4), não o consulta.
 *
 * Escopo honesto / anti-fachada (decisão do 05-CONTEXT + bloqueio da Fase 4): a
 * zona urbanística e o atributo de classificação viária LOUOS estão pendentes
 * SEDUR. Sem o dado real, o motor NUNCA inventa permissão/condição — degrada
 * para indisponivel com motivo explícito; quando a base chegar, a MESMA lógica
 * passa a decidir (muda a carga, não a lógica).
 */
class LouosEnquadramentoTerritorioTest extends TestCase
{
    use RefreshDatabase;

    private function service(): LouosEnquadramentoService
    {
        return new LouosEnquadramentoService(app(AuditService::class));
    }

    /**
     * Versão vigente do Quadro 7 + uma faixa para o CNAE, controlando o grupo de
     * uso que o Quadro 10 vai casar.
     */
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

    /**
     * Versão vigente do Quadro 7 SEM faixa para o CNAE — força o Quadro 7 a
     * nao_encontrado (pré-condição do Quadro 10).
     */
    private function quadro7SemFaixa(): void
    {
        RuleVersion::factory()->create([
            'domain' => RuleDomain::LouosQuadro7,
            'version' => 'lei-9148-2016-quadro7',
            'rules_version' => 'lei-9148-2016-quadro7',
        ]);
    }

    /**
     * Versão vigente do Quadro 10 + uma permissão por (zona, grupo de uso). O
     * subgrupo vazio replica o seed real (regra geral do grupo).
     */
    private function quadro10Permissao(
        string $zona,
        string $grupoUso,
        Quadro10Permissao $permissao,
        ?string $condicionanteRef = null,
    ): void {
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
            'condicionante_ref' => $condicionanteRef,
        ]);
    }

    /**
     * @param  array<string, mixed>  $zona
     */
    private function territorioComZona(array $zona): TerritoryResult
    {
        return new TerritoryResult(
            bairro: $this->dimVazia(),
            via: $this->dimVazia() + ['distancia_m' => null],
            zona: $zona,
            lote: $this->dimVazia(),
            restricoes: ['status' => 'nao_encontrado', 'itens' => [], 'motivo' => null, 'versao_camada' => null],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function dimVazia(): array
    {
        return [
            'status' => 'nao_encontrado',
            'nome' => null,
            'propriedades' => null,
            'motivo' => null,
            'versao_camada' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function zonaIndisponivel(string $motivo = 'Base de zoneamento pendente SEDUR'): array
    {
        return [
            'status' => 'indisponivel',
            'nome' => null,
            'propriedades' => null,
            'motivo' => $motivo,
            'versao_camada' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function zonaIdentificada(string $nome): array
    {
        return [
            'status' => 'identificado',
            'nome' => $nome,
            'propriedades' => ['NOME' => $nome],
            'motivo' => null,
            'versao_camada' => 'zoneamento-louos-2026',
        ];
    }

    public function test_zona_indisponivel_degrada_quadro10_sem_consultar_tabela(): void
    {
        // Quadro 7 identificado (enquadramento por área existe) E há permissão no
        // Quadro 10 que CASARIA se a tabela fosse consultada — a prova de que a
        // zona indisponível NÃO consulta a tabela nem inventa permissão.
        $this->quadro7Faixa('4712100', 'nR1', 'nR1-01');
        $this->quadro10Permissao('ZR-1', 'nR1', Quadro10Permissao::Permitido);

        $input = EnquadramentoInput::paraConsulta(100, '4712-1/00', $this->territorioComZona(
            $this->zonaIndisponivel('Base de zoneamento pendente SEDUR'),
        ));

        $quadro10 = $this->service()->enquadrar($input)->quadro10;

        // Degradação: indisponivel (não nao_encontrado, que seria o resultado de
        // uma consulta com zona nula) e sem permissão inventada.
        $this->assertSame(EnquadramentoResult::STATUS_INDISPONIVEL, $quadro10['status']);
        $this->assertNull($quadro10['permissao']);
        $this->assertSame('Base de zoneamento pendente SEDUR', $quadro10['motivo']);
        $this->assertNull($quadro10['versao_regra']);
    }

    public function test_territorio_nulo_degrada_quadro10(): void
    {
        $this->quadro7Faixa('4712100', 'nR1', 'nR1-01');
        $this->quadro10Permissao('ZR-1', 'nR1', Quadro10Permissao::Permitido);

        // Consulta sem ponto (território nulo): não há zona a avaliar.
        $quadro10 = $this->service()->enquadrar(
            EnquadramentoInput::paraConsulta(100, '4712-1/00'),
        )->quadro10;

        $this->assertSame(EnquadramentoResult::STATUS_INDISPONIVEL, $quadro10['status']);
        $this->assertNull($quadro10['permissao']);
        $this->assertSame('Permissão por zona pendente da base oficial (SEDUR)', $quadro10['motivo']);
    }

    public function test_zona_identificada_retorna_permissao_permitido(): void
    {
        $this->quadro7Faixa('4712100', 'nR1', 'nR1-01');
        $this->quadro10Permissao('ZR-1', 'nR1', Quadro10Permissao::Permitido);

        $input = EnquadramentoInput::paraConsulta(100, '4712-1/00', $this->territorioComZona(
            $this->zonaIdentificada('ZR-1'),
        ));

        $quadro10 = $this->service()->enquadrar($input)->quadro10;

        $this->assertSame(EnquadramentoResult::STATUS_IDENTIFICADO, $quadro10['status']);
        $this->assertSame(Quadro10Permissao::Permitido->value, $quadro10['permissao']);
        $this->assertSame('lei-9148-2016-quadro10', $quadro10['versao_regra']);
    }

    public function test_zona_identificada_retorna_proibido(): void
    {
        // RN-005: permissão proibida na zona (o consolidado vira nao_permitido no
        // 05-05). Aqui o Quadro 10 só precisa devolver 'proibido' fielmente.
        $this->quadro7Faixa('4712100', 'nR3', 'nR3-01');
        $this->quadro10Permissao('ZPAM', 'nR3', Quadro10Permissao::Proibido);

        $input = EnquadramentoInput::paraConsulta(100, '4712-1/00', $this->territorioComZona(
            $this->zonaIdentificada('ZPAM'),
        ));

        $quadro10 = $this->service()->enquadrar($input)->quadro10;

        $this->assertSame(EnquadramentoResult::STATUS_IDENTIFICADO, $quadro10['status']);
        $this->assertSame(Quadro10Permissao::Proibido->value, $quadro10['permissao']);
    }

    public function test_zona_identificada_sem_regra_retorna_nao_encontrado(): void
    {
        // Versão do Quadro 10 existe, mas sem linha para (zona, grupo de uso): o
        // motor NÃO inventa permissão — devolve nao_encontrado com a versão.
        $this->quadro7Faixa('4712100', 'nR1', 'nR1-01');
        $this->quadro10Permissao('ZR-1', 'nR2', Quadro10Permissao::Permitido);

        $input = EnquadramentoInput::paraConsulta(100, '4712-1/00', $this->territorioComZona(
            $this->zonaIdentificada('ZR-1'),
        ));

        $quadro10 = $this->service()->enquadrar($input)->quadro10;

        $this->assertSame(EnquadramentoResult::STATUS_NAO_ENCONTRADO, $quadro10['status']);
        $this->assertNull($quadro10['permissao']);
        $this->assertSame('lei-9148-2016-quadro10', $quadro10['versao_regra']);
    }

    public function test_sem_enquadramento_quadro7_nao_avalia_quadro10(): void
    {
        // Mesmo com zona identificada e permissão cadastrada, sem enquadramento
        // (Quadro 7 nao_encontrado) não há grupo de uso para verificar permissão.
        $this->quadro7SemFaixa();
        $this->quadro10Permissao('ZR-1', 'nR1', Quadro10Permissao::Permitido);

        $input = EnquadramentoInput::paraConsulta(100, '9999-9/99', $this->territorioComZona(
            $this->zonaIdentificada('ZR-1'),
        ));

        $quadro10 = $this->service()->enquadrar($input)->quadro10;

        $this->assertSame(EnquadramentoResult::STATUS_INDISPONIVEL, $quadro10['status']);
        $this->assertNull($quadro10['permissao']);
        $this->assertSame('Sem enquadramento (Quadro 7) não há permissão a verificar', $quadro10['motivo']);
    }
}
