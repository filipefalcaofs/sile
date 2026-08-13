<?php

namespace Tests\Feature\Louos;

use App\Enums\Quadro10Permissao;
use App\Enums\ResultadoViabilidade;
use App\Enums\RuleDomain;
use App\Models\Activity;
use App\Models\LouosQuadro10Permissao;
use App\Models\LouosQuadro7Faixa;
use App\Models\Parameter;
use App\Models\RuleVersion;
use App\Services\Geo\TerritoryResult;
use App\Services\Louos\EnquadramentoInput;
use App\Services\Louos\LouosEnquadramentoService;
use App\Support\Audit\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Consolidação final do parecer LOUOS (HU-044) + fundamentação legal (HU-045) +
 * condicionantes de vagas (HU-042) + restrições especiais ZEIS (HU-043). O motor
 * combina as dimensões dos Quadros 7/10/11/11A num veredito único e fundamentado.
 *
 * Anti-fachada (regra central): sem zona (Quadro 10 indisponível) ou sem
 * enquadramento (Quadro 7 não encontrado), o consolidado é SEMPRE `pendente` —
 * o motor jamais declara permitido/nao_permitido sem o dado real. Vagas e
 * restrições alimentam condicionantes (permitido_com_condicoes), sem bloquear
 * sozinhas.
 */
class LouosConsolidacaoTest extends TestCase
{
    use RefreshDatabase;

    private function service(): LouosEnquadramentoService
    {
        return new LouosEnquadramentoService(app(AuditService::class));
    }

    /**
     * Versão vigente do Quadro 7 + uma faixa sem teto para o CNAE, fixando o
     * grupo/subgrupo de uso que o Quadro 10 vai casar.
     */
    private function seedQuadro7(string $cnae, string $grupo, string $subgrupo): void
    {
        $version = RuleVersion::vigente(RuleDomain::LouosQuadro7)->first()
            ?? RuleVersion::factory()->create([
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
     * nao_encontrado (precedência 1 do consolidado).
     */
    private function seedQuadro7SemFaixa(): void
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
    private function seedQuadro10(
        string $zona,
        string $grupoUso,
        Quadro10Permissao $permissao,
        ?string $condicionanteRef = null,
        string $baseLegal = 'Quadro 10 da Lei nº 9.148/2016',
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
            'base_legal' => $baseLegal,
        ]);
    }

    /**
     * @param  array<string, mixed>  $zona
     * @param  array<string, mixed>|null  $restricoes
     */
    private function territorio(array $zona, ?array $restricoes = null): TerritoryResult
    {
        return new TerritoryResult(
            bairro: $this->dimVazia(),
            via: $this->dimVazia() + ['distancia_m' => null],
            zona: $zona,
            lote: $this->dimVazia(),
            restricoes: $restricoes ?? ['status' => 'nao_encontrado', 'itens' => [], 'motivo' => null, 'versao_camada' => null],
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

    /**
     * @return array<string, mixed>
     */
    private function zonaIndisponivel(string $motivo): array
    {
        return [
            'status' => 'indisponivel',
            'nome' => null,
            'propriedades' => null,
            'motivo' => $motivo,
            'versao_camada' => null,
        ];
    }

    public function test_proibido_consolida_nao_permitido(): void
    {
        // HU-044/RN-005: enquadramento + zona com permissão 'proibido' → o veredito
        // consolidado é nao_permitido (a permissão da zona decide).
        $this->seedQuadro7('4712100', 'nR3', 'nR3-01');
        $this->seedQuadro10('ZPAM', 'nR3', Quadro10Permissao::Proibido);

        $result = $this->service()->enquadrar(EnquadramentoInput::paraConsulta(
            100,
            '4712-1/00',
            $this->territorio($this->zonaIdentificada('ZPAM')),
        ));

        $this->assertSame(ResultadoViabilidade::NaoPermitido->value, $result->resultado());
        $this->assertStringContainsStringIgnoringCase('proibida', (string) $result->consolidado['motivo']);
    }

    public function test_permitido_sem_condicoes_consolida_permitido(): void
    {
        // Enquadramento + zona 'permitido' + sem condições pela via / restrições →
        // veredito permitido.
        $this->seedQuadro7('4712100', 'nR1', 'nR1-01');
        $this->seedQuadro10('ZR-1', 'nR1', Quadro10Permissao::Permitido);

        $result = $this->service()->enquadrar(EnquadramentoInput::paraConsulta(
            100,
            '4712-1/00',
            $this->territorio($this->zonaIdentificada('ZR-1')),
        ));

        $this->assertSame(ResultadoViabilidade::Permitido->value, $result->resultado());
    }

    public function test_permitido_condicionado_consolida_com_condicoes(): void
    {
        // RN-006: zona 'permitido_condicionado' → permitido_com_condicoes, com a
        // condicionante urbanística do Quadro 10 na ficha.
        $this->seedQuadro7('4712100', 'nR1', 'nR1-01');
        $this->seedQuadro10('ZR-1', 'nR1', Quadro10Permissao::PermitidoCondicionado, 'CU-01');

        $result = $this->service()->enquadrar(EnquadramentoInput::paraConsulta(
            100,
            '4712-1/00',
            $this->territorio($this->zonaIdentificada('ZR-1')),
        ));

        $this->assertSame(ResultadoViabilidade::PermitidoComCondicoes->value, $result->resultado());
        $this->assertNotEmpty($result->consolidado['condicionantes']);
    }

    public function test_sem_zona_consolida_pendente(): void
    {
        // ANTI-FACHADA CENTRAL: existe permissão no Quadro 10 que CASARIA, mas a
        // zona é indisponível (base pendente SEDUR) → o consolidado é pendente,
        // jamais permitido/nao_permitido. A fundamentação não cita o Quadro 10
        // (regra não aplicada — honesto).
        $this->seedQuadro7('4712100', 'nR1', 'nR1-01');
        $this->seedQuadro10('ZR-1', 'nR1', Quadro10Permissao::Permitido);

        $result = $this->service()->enquadrar(EnquadramentoInput::paraConsulta(
            100,
            '4712-1/00',
            $this->territorio($this->zonaIndisponivel('Base de zoneamento pendente SEDUR')),
        ));

        $this->assertSame(ResultadoViabilidade::Pendente->value, $result->resultado());
        $this->assertSame('Base de zoneamento pendente SEDUR', $result->consolidado['motivo']);

        foreach ($result->consolidado['fundamentacao'] as $referencia) {
            $this->assertStringNotContainsString('Quadro 10', (string) $referencia);
        }
    }

    public function test_sem_enquadramento_consolida_pendente(): void
    {
        // Quadro 7 nao_encontrado → pendente (sem grupo de uso não há permissão a
        // consolidar), mesmo com zona identificada.
        $this->seedQuadro7SemFaixa();
        $this->seedQuadro10('ZR-1', 'nR1', Quadro10Permissao::Permitido);

        $result = $this->service()->enquadrar(EnquadramentoInput::paraConsulta(
            100,
            '9999-9/99',
            $this->territorio($this->zonaIdentificada('ZR-1')),
        ));

        $this->assertSame(ResultadoViabilidade::Pendente->value, $result->resultado());
        $this->assertStringContainsStringIgnoringCase('análise técnica', (string) $result->consolidado['motivo']);
    }

    public function test_fundamentacao_cita_louos_e_quadros_aplicados(): void
    {
        // HU-045: o caso permitido cita a Lei 9.148/2016 e os quadros efetivamente
        // aplicados (Quadro 7 e Quadro 10), com as versões registradas em versoes().
        $this->seedQuadro7('4712100', 'nR1', 'nR1-01');
        $this->seedQuadro10('ZR-1', 'nR1', Quadro10Permissao::Permitido);

        $result = $this->service()->enquadrar(EnquadramentoInput::paraConsulta(
            100,
            '4712-1/00',
            $this->territorio($this->zonaIdentificada('ZR-1')),
        ));

        $fundamentacao = implode(' | ', array_map(strval(...), $result->consolidado['fundamentacao']));

        $this->assertStringContainsString('Lei nº 9.148/2016', $fundamentacao);
        $this->assertStringContainsString('Quadro 7', $fundamentacao);
        $this->assertStringContainsString('Quadro 10', $fundamentacao);

        $this->assertSame('lei-9148-2016-quadro7', $result->versoes()['quadro7']);
        $this->assertSame('lei-9148-2016-quadro10', $result->versoes()['quadro10']);
    }

    public function test_resultado_e_auditado_com_fundamentacao_e_versao(): void
    {
        // CA-02/RN-002: a execução é auditada com o resultado consolidado, a
        // fundamentação legal e as versões das regras aplicadas.
        $this->seedQuadro7('4712100', 'nR1', 'nR1-01');
        $this->seedQuadro10('ZR-1', 'nR1', Quadro10Permissao::Permitido);

        $this->service()->enquadrar(EnquadramentoInput::paraConsulta(
            100,
            '4712-1/00',
            $this->territorio($this->zonaIdentificada('ZR-1')),
        ));

        $activity = Activity::query()
            ->where('log_name', 'louos')
            ->where('event', 'enquadramento')
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame(ResultadoViabilidade::Permitido->value, $activity->properties['resultado_consolidado']);
        $this->assertIsArray($activity->properties['fundamentacao']);
        $this->assertNotEmpty($activity->properties['fundamentacao']);
        $this->assertStringContainsString(
            'Lei nº 9.148/2016',
            implode(' | ', array_map(strval(...), $activity->properties['fundamentacao'])),
        );
        $this->assertSame('lei-9148-2016-quadro7', $activity->properties['versoes']['quadro7']);
        $this->assertSame('lei-9148-2016-quadro10', $activity->properties['versoes']['quadro10']);
    }

    public function test_vagas_nao_parametrizadas_geram_condicionante_informativa_sem_bloquear(): void
    {
        // HU-042 (degradação honesta): sem exigência de vagas parametrizada para o
        // grupo, o motor gera uma condicionante INFORMATIVA (conforme null) que
        // alimenta a ficha — mas NÃO rebaixa o veredito (não bloqueia).
        $this->seedQuadro7('4712100', 'nR1', 'nR1-01');
        $this->seedQuadro10('ZR-1', 'nR1', Quadro10Permissao::Permitido);

        $result = $this->service()->enquadrar(EnquadramentoInput::paraConsulta(
            100,
            '4712-1/00',
            $this->territorio($this->zonaIdentificada('ZR-1')),
        ));

        $this->assertSame(ResultadoViabilidade::Permitido->value, $result->resultado());

        $vagas = $this->condicionantePorTipo($result->consolidado['condicionantes'], 'vagas');
        $this->assertNotNull($vagas);
        $this->assertNull($vagas['conforme']);
    }

    public function test_vagas_nao_conformes_geram_permitido_com_condicoes(): void
    {
        // HU-042: com exigência parametrizada acima do declarado, o imóvel é Não
        // Conforme quanto a vagas → permitido_com_condicoes (insumo da análise,
        // HU-135). Não conforme NÃO vira nao_permitido.
        $this->seedQuadro7('4712100', 'nR1', 'nR1-01');
        $this->seedQuadro10('ZR-1', 'nR1', Quadro10Permissao::Permitido);
        $this->parametrizarVagas(['nR1' => ['vagas' => 5]]);

        $result = $this->service()->enquadrar(new EnquadramentoInput(
            area: 100,
            cnaePrincipal: '4712-1/00',
            territory: $this->territorio($this->zonaIdentificada('ZR-1')),
            vagasDeclaradas: ['vagas' => 2],
        ));

        $this->assertSame(ResultadoViabilidade::PermitidoComCondicoes->value, $result->resultado());

        $vagas = $this->condicionantePorTipo($result->consolidado['condicionantes'], 'vagas');
        $this->assertNotNull($vagas);
        $this->assertFalse($vagas['conforme']);
        $this->assertSame(5, $vagas['exigido']['vagas']);
        $this->assertSame(2, $vagas['declarado']['vagas']);
    }

    public function test_restricao_zeis_incidente_gera_permitido_com_condicoes(): void
    {
        // HU-043: restrição territorial incidente (ZEIS, camada ambiental da Fase
        // 4) entra como condicionante e rebaixa para permitido_com_condicoes — sem
        // decidir sozinha (o roteamento à análise é do motor de risco, Fase 6).
        $this->seedQuadro7('4712100', 'nR1', 'nR1-01');
        $this->seedQuadro10('ZR-1', 'nR1', Quadro10Permissao::Permitido);

        $result = $this->service()->enquadrar(EnquadramentoInput::paraConsulta(
            100,
            '4712-1/00',
            $this->territorio($this->zonaIdentificada('ZR-1'), $this->restricoesZeis()),
        ));

        $this->assertSame(ResultadoViabilidade::PermitidoComCondicoes->value, $result->resultado());

        $restricao = $this->condicionantePorTipo($result->consolidado['condicionantes'], 'restricao');
        $this->assertNotNull($restricao);
        $this->assertStringContainsString('ZEIS', (string) $restricao['nome']);
    }

    public function test_vagas_parametrizadas_sem_deploy(): void
    {
        // HU-042/HU-014: a mesma entrada muda de veredito ao gravar o parâmetro
        // louos.vagas.exigencia_por_grupo — efeito sem deploy (cache invalidado na
        // gravação do Parameter).
        $this->seedQuadro7('4712100', 'nR1', 'nR1-01');
        $this->seedQuadro10('ZR-1', 'nR1', Quadro10Permissao::Permitido);

        $input = fn (): EnquadramentoInput => new EnquadramentoInput(
            area: 100,
            cnaePrincipal: '4712-1/00',
            territory: $this->territorio($this->zonaIdentificada('ZR-1')),
            vagasDeclaradas: ['vagas' => 2],
        );

        // Sem exigência parametrizada: condicionante informativa, veredito permitido.
        $antes = $this->service()->enquadrar($input());
        $this->assertSame(ResultadoViabilidade::Permitido->value, $antes->resultado());

        // Grava a exigência (5 vagas > 2 declaradas) — sem novo deploy.
        $this->parametrizarVagas(['nR1' => ['vagas' => 5]]);

        $depois = $this->service()->enquadrar($input());
        $this->assertSame(ResultadoViabilidade::PermitidoComCondicoes->value, $depois->resultado());
    }

    /**
     * Grava o parâmetro de exigência de vagas por grupo (HU-042/HU-014). O hook
     * Parameter::saved invalida o cache da chave — efeito sem deploy.
     *
     * @param  array<string, mixed>  $exigencia
     */
    private function parametrizarVagas(array $exigencia): void
    {
        Parameter::query()->create([
            'key' => 'louos.vagas.exigencia_por_grupo',
            'group' => 'louos',
            'type' => 'json',
            'value' => json_encode($exigencia),
            'default_value' => '{}',
            'validation_rules' => ['required', 'json'],
            'description' => 'Exigência de vagas por grupo de uso (HU-042).',
        ]);
    }

    /**
     * Restrições territoriais com uma ZEIS incidente (camada ambiental da Fase 4,
     * shape de TerritoryResult::restricoes).
     *
     * @return array<string, mixed>
     */
    private function restricoesZeis(): array
    {
        return [
            'status' => 'identificado',
            'itens' => [
                ['nome' => 'ZEIS — Zona Especial de Interesse Social', 'propriedades' => ['TIPO' => 'ZEIS']],
            ],
            'motivo' => null,
            'versao_camada' => 'restricoes-pddu-2016',
        ];
    }

    /**
     * Primeira condicionante de um tipo na lista consolidada (null se ausente).
     *
     * @param  list<array<string, mixed>>  $condicionantes
     * @return array<string, mixed>|null
     */
    private function condicionantePorTipo(array $condicionantes, string $tipo): ?array
    {
        foreach ($condicionantes as $condicionante) {
            if (($condicionante['tipo'] ?? null) === $tipo) {
                return $condicionante;
            }
        }

        return null;
    }
}
