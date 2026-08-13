<?php

namespace Tests\Feature\Cnae;

use App\Enums\RuleDomain;
use App\Models\Cnae;
use App\Models\RiskCondicionante;
use App\Models\RuleVersion;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CnaeCondicionanteMaintenanceTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function admin(): User
    {
        return User::factory()->administrador()->withAcceptedLgpdTerm()->create();
    }

    public function test_administrador_cria_pergunta_de_condicionante_no_cnae(): void
    {
        RuleVersion::factory()->create(['domain' => RuleDomain::RiscoSanitario]);
        $cnae = Cnae::factory()->create(['code' => '0111301']);

        $this->actingAs($this->admin(), 'gestao')
            ->post("/gestao/cnaes/{$cnae->id}/condicionantes", [
                'pergunta' => 'O produto é artesanal?',
                'regra_reclassificacao' => [
                    'resposta_gatilho' => false,
                    'reclassifica_para' => 'alto',
                    'fundamento' => 'Produto não artesanal eleva o risco.',
                ],
                'texto_parecer' => null,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('risk_condicionantes', [
            'cnae_code' => '0111301',
            'pergunta' => 'O produto é artesanal?',
        ]);
    }

    public function test_condicionante_ignora_cnae_code_enviado_e_usa_o_da_rota(): void
    {
        RuleVersion::factory()->create(['domain' => RuleDomain::RiscoSanitario]);
        $cnae = Cnae::factory()->create(['code' => '0111301']);
        Cnae::factory()->create(['code' => '9999999']);

        $this->actingAs($this->admin(), 'gestao')
            ->post("/gestao/cnaes/{$cnae->id}/condicionantes", [
                'cnae_code' => '9999999',
                'pergunta' => 'Pergunta qualquer?',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('risk_condicionantes', [
            'cnae_code' => '0111301',
            'pergunta' => 'Pergunta qualquer?',
        ]);
    }

    public function test_administrador_edita_e_remove_pergunta(): void
    {
        $versao = RuleVersion::factory()->create(['domain' => RuleDomain::RiscoSanitario]);
        $cnae = Cnae::factory()->create(['code' => '0111301']);
        $condicionante = RiskCondicionante::factory()->create([
            'rule_version_id' => $versao->id,
            'cnae_code' => '0111301',
            'pergunta' => 'Pergunta original?',
        ]);

        $this->actingAs($this->admin(), 'gestao')
            ->put("/gestao/cnaes/{$cnae->id}/condicionantes/{$condicionante->id}", [
                'pergunta' => 'Pergunta editada?',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('Pergunta editada?', $condicionante->refresh()->pergunta);

        $this->actingAs($this->admin(), 'gestao')
            ->delete("/gestao/cnaes/{$cnae->id}/condicionantes/{$condicionante->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('risk_condicionantes', ['id' => $condicionante->id]);
    }

    public function test_edicao_de_pergunta_de_outro_cnae_e_bloqueada(): void
    {
        $versao = RuleVersion::factory()->create(['domain' => RuleDomain::RiscoSanitario]);
        $cnaeA = Cnae::factory()->create(['code' => '0111301']);
        $cnaeB = Cnae::factory()->create(['code' => '9999999']);
        $condicionante = RiskCondicionante::factory()->create([
            'rule_version_id' => $versao->id,
            'cnae_code' => '9999999',
        ]);

        $this->actingAs($this->admin(), 'gestao')
            ->put("/gestao/cnaes/{$cnaeA->id}/condicionantes/{$condicionante->id}", ['pergunta' => 'Tentativa?'])
            ->assertNotFound();

        $this->assertSame('9999999', $condicionante->refresh()->cnae_code);
        $this->assertNotNull($cnaeB);
    }

    public function test_exclusao_de_pergunta_de_outro_cnae_e_bloqueada(): void
    {
        $versao = RuleVersion::factory()->create(['domain' => RuleDomain::RiscoSanitario]);
        $cnaeA = Cnae::factory()->create(['code' => '0111301']);
        Cnae::factory()->create(['code' => '9999999']);
        $condicionante = RiskCondicionante::factory()->create([
            'rule_version_id' => $versao->id,
            'cnae_code' => '9999999',
        ]);

        $this->actingAs($this->admin(), 'gestao')
            ->delete("/gestao/cnaes/{$cnaeA->id}/condicionantes/{$condicionante->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('risk_condicionantes', ['id' => $condicionante->id]);
    }

    public function test_analista_nao_mantem_condicionantes(): void
    {
        RuleVersion::factory()->create(['domain' => RuleDomain::RiscoSanitario]);
        $cnae = Cnae::factory()->create();
        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create();

        $this->actingAs($analista, 'gestao')
            ->post("/gestao/cnaes/{$cnae->id}/condicionantes", ['pergunta' => 'Pergunta?'])
            ->assertForbidden();
    }
}
