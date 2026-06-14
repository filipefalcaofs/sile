<?php

namespace Tests\Feature\Risco;

use App\Enums\RuleDomain;
use App\Models\RiskCondicionante;
use App\Models\RuleVersion;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * CRUD das condicionantes-pergunta de risco (HU-019): o administrador mantém
 * as condicionantes da versão sanitária vigente (pergunta + regra de
 * reclassificação), com auditoria automática (HasAuditoria, CA-02). Sem a
 * permissão manter-risco a ação é bloqueada (CA-04). Espelha o CnaeController.
 */
class RiscoCondicionanteMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function administrador(): User
    {
        return User::factory()->administrador()->withAcceptedLgpdTerm()->create();
    }

    private function analista(): User
    {
        return User::factory()->analista()->withAcceptedLgpdTerm()->create();
    }

    /**
     * Versão sanitária vigente, com uma versão antiga preservada (que não deve
     * receber novas condicionantes nem aparecer na consulta vigente).
     */
    private function versaoSanitariaVigente(): RuleVersion
    {
        RuleVersion::factory()->substituida()->create([
            'domain' => RuleDomain::RiscoSanitario,
            'version' => 'visa-antiga',
            'rules_version' => 'visa-antiga',
        ]);

        return RuleVersion::factory()->create([
            'domain' => RuleDomain::RiscoSanitario,
            'version' => 'visa-unificada-2026-04-30',
            'rules_version' => 'visa-unificada-2026-04-30',
        ]);
    }

    public function test_analista_consulta_condicionantes_da_versao_vigente(): void
    {
        $vigente = $this->versaoSanitariaVigente();
        RiskCondicionante::factory()->count(3)->create(['rule_version_id' => $vigente->id]);

        // Condicionante de uma versão antiga não aparece na consulta vigente.
        $antiga = RuleVersion::query()->where('version', 'visa-antiga')->first();
        RiskCondicionante::factory()->create(['rule_version_id' => $antiga->id]);

        $this->actingAs($this->analista(), 'gestao')
            ->get('/gestao/risco/condicionantes')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/risco/condicionantes', false)
                ->has('condicionantes.data', 3)
                ->where('versaoSanitaria.version', 'visa-unificada-2026-04-30'));
    }

    public function test_administrador_cria_condicionante_pergunta(): void
    {
        $vigente = $this->versaoSanitariaVigente();

        $this->actingAs($this->administrador(), 'gestao')
            ->post('/gestao/risco/condicionantes', [
                'cnae_code' => '1031-7/00',
                'pergunta' => 'O resultado do exercício da atividade econômica será diferente de produto artesanal?',
                'tipo_resposta' => 'booleano_sim_nao',
                'regra_reclassificacao' => [
                    'resposta_gatilho' => true,
                    'reclassifica_para' => 'alto',
                    'fundamento' => 'Caso o produto seja diferente de artesanal, será considerado Alto Risco.',
                ],
                'texto_parecer' => 'Verificar a natureza artesanal do produto.',
            ])
            ->assertSessionHas('status');

        // Criada na versão sanitária VIGENTE (cnae_code normalizado para dígitos).
        $condicionante = RiskCondicionante::query()->where('cnae_code', '1031700')->first();
        $this->assertNotNull($condicionante);
        $this->assertSame($vigente->id, $condicionante->rule_version_id);
        $this->assertSame('alto', $condicionante->regra_reclassificacao['reclassifica_para']);

        // Auditoria automática do model (CA-02).
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => RiskCondicionante::class,
            'subject_id' => $condicionante->id,
            'event' => 'created',
        ]);
    }

    public function test_administrador_edita_e_remove_condicionante(): void
    {
        $vigente = $this->versaoSanitariaVigente();
        $condicionante = RiskCondicionante::factory()->create([
            'rule_version_id' => $vigente->id,
            'cnae_code' => '1031700',
            'pergunta' => 'Pergunta original?',
        ]);

        $this->actingAs($this->administrador(), 'gestao')
            ->put("/gestao/risco/condicionantes/{$condicionante->id}", [
                'cnae_code' => '1031700',
                'pergunta' => 'Pergunta revisada?',
                'tipo_resposta' => 'booleano_sim_nao',
                'regra_reclassificacao' => [
                    'resposta_gatilho' => true,
                    'reclassifica_para' => 'medio',
                    'fundamento' => 'Reclassifica para Médio Risco.',
                ],
            ])
            ->assertSessionHas('status');

        $this->assertSame('Pergunta revisada?', $condicionante->fresh()->pergunta);
        $this->assertSame('medio', $condicionante->fresh()->regra_reclassificacao['reclassifica_para']);

        $this->actingAs($this->administrador(), 'gestao')
            ->delete("/gestao/risco/condicionantes/{$condicionante->id}")
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('risk_condicionantes', ['id' => $condicionante->id]);
    }

    public function test_validacao_rejeita_reclassifica_para_invalido(): void
    {
        $this->versaoSanitariaVigente();

        $this->actingAs($this->administrador(), 'gestao')
            ->post('/gestao/risco/condicionantes', [
                'pergunta' => 'Pergunta com nível inválido?',
                'tipo_resposta' => 'booleano_sim_nao',
                'regra_reclassificacao' => [
                    'resposta_gatilho' => true,
                    'reclassifica_para' => 'altissimo',
                    'fundamento' => 'Nível inexistente.',
                ],
            ])
            ->assertSessionHasErrors('regra_reclassificacao.reclassifica_para');

        $this->assertDatabaseCount('risk_condicionantes', 0);
    }

    public function test_sem_permissao_manter_risco_bloqueia(): void
    {
        $this->versaoSanitariaVigente();

        // Analista tem consultar-risco mas NÃO manter-risco (CA-04).
        $this->actingAs($this->analista(), 'gestao')
            ->post('/gestao/risco/condicionantes', [
                'pergunta' => 'Tentativa sem permissão?',
                'tipo_resposta' => 'booleano_sim_nao',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('risk_condicionantes', 0);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'seguranca',
            'event' => 'acesso-negado',
            'result' => 'bloqueado',
        ]);
    }
}
