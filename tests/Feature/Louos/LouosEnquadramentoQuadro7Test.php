<?php

namespace Tests\Feature\Louos;

use App\Enums\ResultadoViabilidade;
use App\Enums\RuleDomain;
use App\Models\Activity;
use App\Models\LouosQuadro7Faixa;
use App\Models\RuleVersion;
use App\Services\Louos\EnquadramentoInput;
use App\Services\Louos\EnquadramentoResult;
use App\Services\Louos\LouosEnquadramentoService;
use App\Support\Audit\AuditService;
use Database\Seeders\LouosQuadro7Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Motor de enquadramento da LOUOS (HU-038/HU-046) — dimensão Quadro 7
 * (enquadramento por área): dado um CNAE principal e uma área, casa a faixa de
 * área da versão vigente e devolve grupo/subgrupo de uso, resolvendo a versão
 * de regra em 3 modos (vigente/na data/versão específica para sandbox) e
 * auditando a execução com a versão aplicada (RN-002). Sem fachada: CNAE sem
 * faixa devolve nao_encontrado (nunca um grupo inventado) e, enquanto o motor
 * está incompleto (zona/via não avaliados), o consolidado fica pendente.
 */
class LouosEnquadramentoQuadro7Test extends TestCase
{
    use RefreshDatabase;

    private function service(): LouosEnquadramentoService
    {
        return new LouosEnquadramentoService(app(AuditService::class));
    }

    private function versaoQuadro7(string $version = 'lei-9148-2016-quadro7'): RuleVersion
    {
        return RuleVersion::factory()->create([
            'domain' => RuleDomain::LouosQuadro7,
            'version' => $version,
            'rules_version' => $version,
        ]);
    }

    public function test_enquadra_cnae_por_area_na_faixa_correta(): void
    {
        // Dado real seedado: minimercado 4712-1/00 até 350 m² = nR1-01.
        $this->seed(LouosQuadro7Seeder::class);

        // O CNAE entra com máscara — o motor normaliza para 7 dígitos.
        $result = $this->service()->enquadrar(EnquadramentoInput::paraConsulta(100, '4712-1/00'));

        $this->assertSame(EnquadramentoResult::STATUS_IDENTIFICADO, $result->quadro7['status']);
        $this->assertSame('nR1', $result->quadro7['grupo']);
        $this->assertSame('nR1-01', $result->quadro7['subgrupo']);
        $this->assertSame('lei-9148-2016-quadro7', $result->versoes()['quadro7']);
    }

    public function test_area_acima_do_limite_cai_na_faixa_superior(): void
    {
        // Mesma carga real: acima de 350 m², o minimercado vira nR2-01.
        $this->seed(LouosQuadro7Seeder::class);

        $result = $this->service()->enquadrar(EnquadramentoInput::paraConsulta(500, '4712-1/00'));

        $this->assertSame(EnquadramentoResult::STATUS_IDENTIFICADO, $result->quadro7['status']);
        $this->assertSame('nR2', $result->quadro7['grupo']);
        $this->assertSame('nR2-01', $result->quadro7['subgrupo']);
    }

    public function test_limite_inferior_e_inclusivo(): void
    {
        // Faixas disjuntas: faixa 1 termina em 99 m², faixa 2 começa em 100 m².
        $version = $this->versaoQuadro7();
        LouosQuadro7Faixa::factory()->create([
            'rule_version_id' => $version->id,
            'cnae_code' => '5611201',
            'grupo' => 'nR1',
            'subgrupo' => 'nR1-03',
            'area_min' => 0,
            'area_max' => 99,
        ]);
        LouosQuadro7Faixa::factory()->create([
            'rule_version_id' => $version->id,
            'cnae_code' => '5611201',
            'grupo' => 'nR2',
            'subgrupo' => 'nR2-03',
            'area_min' => 100,
            'area_max' => 500,
        ]);

        // Área exatamente no início da faixa 2 (100): o limite inferior é
        // inclusivo, logo identifica a faixa 2 — com limite exclusivo, cairia
        // entre as faixas e voltaria nao_encontrado.
        $result = $this->service()->enquadrar(EnquadramentoInput::paraConsulta(100, '5611-2/01'));

        $this->assertSame(EnquadramentoResult::STATUS_IDENTIFICADO, $result->quadro7['status']);
        $this->assertSame('nR2', $result->quadro7['grupo']);
        $this->assertSame('nR2-03', $result->quadro7['subgrupo']);
    }

    public function test_faixa_sem_teto_aceita_qualquer_area_acima(): void
    {
        // area_max nula = sem limite superior.
        $version = $this->versaoQuadro7();
        LouosQuadro7Faixa::factory()->create([
            'rule_version_id' => $version->id,
            'cnae_code' => '4711301',
            'grupo' => 'nR2',
            'subgrupo' => 'nR2-01',
            'area_min' => 0,
            'area_max' => null,
        ]);

        $result = $this->service()->enquadrar(EnquadramentoInput::paraConsulta(999999, '4711-3/01'));

        $this->assertSame(EnquadramentoResult::STATUS_IDENTIFICADO, $result->quadro7['status']);
        $this->assertSame('nR2', $result->quadro7['grupo']);
        $this->assertNull($result->quadro7['faixa']['area_max']);
    }

    public function test_cnae_sem_faixa_retorna_nao_encontrado(): void
    {
        // FA-02: versão vigente existe, mas o CNAE não tem faixa parametrizada —
        // o motor NÃO inventa grupo.
        $this->versaoQuadro7();

        $result = $this->service()->enquadrar(EnquadramentoInput::paraConsulta(100, '9999-9/99'));

        $this->assertSame(EnquadramentoResult::STATUS_NAO_ENCONTRADO, $result->quadro7['status']);
        $this->assertNull($result->quadro7['grupo']);
        $this->assertNull($result->quadro7['subgrupo']);
        $this->assertNull($result->quadro7['faixa']);
    }

    public function test_consolidado_fica_pendente_enquanto_motor_incompleto(): void
    {
        // Mesmo com o Quadro 7 identificado, sem zona (Quadro 10) e via (Quadro
        // 11/11A) o veredito não pode ser permitido/não permitido: fica pendente.
        $this->seed(LouosQuadro7Seeder::class);

        $result = $this->service()->enquadrar(EnquadramentoInput::paraConsulta(100, '4712-1/00'));

        $this->assertSame(ResultadoViabilidade::Pendente->value, $result->resultado());
        $this->assertTrue($result->pendente());
        $this->assertSame(EnquadramentoResult::STATUS_INDISPONIVEL, $result->quadro10['status']);
        $this->assertSame(EnquadramentoResult::STATUS_INDISPONIVEL, $result->quadro11['status']);
        $this->assertSame(EnquadramentoResult::STATUS_INDISPONIVEL, $result->quadro11a['status']);
    }

    public function test_enquadramento_e_auditado_com_versao_de_regras(): void
    {
        // CA-02/RN-002: toda execução do motor é auditada com a versão aplicada.
        $this->seed(LouosQuadro7Seeder::class);

        $this->service()->enquadrar(EnquadramentoInput::paraConsulta(100, '4712-1/00'));

        $activity = Activity::query()
            ->where('log_name', 'louos')
            ->where('event', 'enquadramento')
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame('sucesso', $activity->result);
        $this->assertSame('lei-9148-2016-quadro7', $activity->rules_version);
        $this->assertSame('4712100', $activity->properties['cnae']);
        $this->assertSame('identificado', $activity->properties['quadro7_status']);
        $this->assertSame(ResultadoViabilidade::Pendente->value, $activity->properties['resultado_consolidado']);
    }

    public function test_modo_sandbox_resolve_versao_especifica(): void
    {
        // HU-143: a versão vigente classifica o CNAE numa faixa; um rascunho
        // (versão específica) reclassifica a MESMA área noutra faixa. O override
        // por domínio resolve o rascunho sem afetar a vigente.
        $vigente = $this->versaoQuadro7('lei-9148-2016-quadro7');
        LouosQuadro7Faixa::factory()->create([
            'rule_version_id' => $vigente->id,
            'cnae_code' => '7020400',
            'grupo' => 'nR1',
            'subgrupo' => 'nR1-12',
            'area_min' => 0,
            'area_max' => null,
        ]);

        $rascunho = RuleVersion::factory()->rascunho()->create([
            'domain' => RuleDomain::LouosQuadro7,
            'version' => 'rascunho-sandbox',
            'rules_version' => 'rascunho-sandbox',
        ]);
        LouosQuadro7Faixa::factory()->create([
            'rule_version_id' => $rascunho->id,
            'cnae_code' => '7020400',
            'grupo' => 'nR2',
            'subgrupo' => 'nR2-12',
            'area_min' => 0,
            'area_max' => null,
        ]);

        $vigenteResult = $this->service()->enquadrar(EnquadramentoInput::paraConsulta(100, '7020-4/00'));
        $this->assertSame('nR1', $vigenteResult->quadro7['grupo']);
        $this->assertSame('lei-9148-2016-quadro7', $vigenteResult->versoes()['quadro7']);

        $sandboxResult = $this->service()->enquadrar(new EnquadramentoInput(
            area: 100,
            cnaePrincipal: '7020-4/00',
            versoesOverride: [RuleDomain::LouosQuadro7->value => 'rascunho-sandbox'],
        ));
        $this->assertSame('nR2', $sandboxResult->quadro7['grupo']);
        $this->assertSame('rascunho-sandbox', $sandboxResult->versoes()['quadro7']);
    }
}
