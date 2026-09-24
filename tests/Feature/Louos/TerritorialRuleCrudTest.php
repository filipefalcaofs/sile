<?php

namespace Tests\Feature\Louos;

use App\Enums\RuleDomain;
use App\Enums\RuleVersionStatus;
use App\Models\RuleVersion;
use App\Models\TerritorialRule;
use App\Models\User;
use Database\Seeders\DecisionTextSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\TerritorialRuleSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Manutenção versionada das regras territoriais no hub Motor de regras:
 * edição grava no rascunho (a vigente não é tocada), publicação é quatro
 * olhos (autor ≠ publicador), descarte preserva a vigente e a reativação
 * fecha a vigente sem apagar. Gate: consultar-louos na aba, manter-louos nas
 * escritas.
 */
class TerritorialRuleCrudTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(DecisionTextSeeder::class);
        $this->seed(TerritorialRuleSeeder::class);
    }

    private function administrador(): User
    {
        return User::factory()->administrador()->withAcceptedLgpdTerm()->create();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payloadRt01(array $overrides = []): array
    {
        return array_merge([
            'codigo' => 'RT-01',
            'version' => 'rt-2026-09-23',
            'titulo' => 'Logradouro 212 — Avenida Lafayette Coutinho',
            'ativo' => true,
            'cod_log' => '212',
            'logradouro_nome' => 'Avenida Lafayette Coutinho',
            'camada' => null,
            'camada_filtro' => null,
            'zona_alvo' => null,
            'motivo_analise' => null,
            'base_legal' => 'art. 172-A da Lei Municipal nº 9.148/2016, com redação dada pela Lei Municipal nº 9.879/2025',
            'decision_text_id' => null,
            'cnaes' => ['4520006', '4723700'],
            'addresses' => [],
        ], $overrides);
    }

    public function test_aba_territoriais_lista_as_regras_da_versao_vigente(): void
    {
        $this->actingAs($this->administrador(), 'gestao')
            ->get('/gestao/louos?aba=territoriais')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/louos/index')
                ->where('aba', 'territoriais')
                ->where('territoriais.versao_vigente', 'sedur-2026-08-28-regras-territoriais')
                ->has('territoriais.regras', 9)
                ->where('territoriais.regras.0.codigo', 'RT-01')
                ->has('territoriais.regras.0.cnaes', 21)
                ->where('territoriais.rascunho', null));
    }

    public function test_edicao_grava_no_rascunho_sem_tocar_a_vigente(): void
    {
        $autor = $this->administrador();

        $this->actingAs($autor, 'gestao')
            ->post('/gestao/louos/territoriais/rascunho', $this->payloadRt01(['cnaes' => ['4520006']]))
            ->assertRedirect('/gestao/louos?aba=territoriais');

        $vigente = RuleVersion::vigente(RuleDomain::Territorial)->sole();
        $rascunho = RuleVersion::query()
            ->where('domain', RuleDomain::Territorial->value)
            ->where('status', RuleVersionStatus::Rascunho->value)
            ->sole();

        // A vigente segue com os 21 CNAEs oficiais; o rascunho tem a edição.
        $rt01Vigente = TerritorialRule::query()->where('rule_version_id', $vigente->id)->where('codigo', 'RT-01')->sole();
        $rt01Rascunho = TerritorialRule::query()->where('rule_version_id', $rascunho->id)->where('codigo', 'RT-01')->sole();

        $this->assertSame(21, $rt01Vigente->cnaes()->count());
        $this->assertSame(['4520006'], $rt01Rascunho->cnaes()->pluck('cnae')->all());
        $this->assertSame($autor->id, $rascunho->created_by);

        // O rascunho nasce como cópia integral da vigente (9 regras).
        $this->assertSame(9, TerritorialRule::query()->where('rule_version_id', $rascunho->id)->count());
    }

    public function test_autor_nao_publica_o_proprio_rascunho(): void
    {
        $autor = $this->administrador();

        $this->actingAs($autor, 'gestao')
            ->post('/gestao/louos/territoriais/rascunho', $this->payloadRt01());

        $rascunho = RuleVersion::query()
            ->where('domain', RuleDomain::Territorial->value)
            ->where('status', RuleVersionStatus::Rascunho->value)
            ->sole();

        $this->actingAs($autor, 'gestao')
            ->put("/gestao/louos/territoriais/versoes/{$rascunho->id}/publicar")
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(RuleVersionStatus::Rascunho, $rascunho->fresh()->status);
    }

    public function test_outro_mantenedor_publica_e_a_vigente_anterior_fecha(): void
    {
        $autor = $this->administrador();
        $publicador = $this->administrador();

        $this->actingAs($autor, 'gestao')
            ->post('/gestao/louos/territoriais/rascunho', $this->payloadRt01(['cnaes' => ['4520006']]));

        $rascunho = RuleVersion::query()
            ->where('domain', RuleDomain::Territorial->value)
            ->where('status', RuleVersionStatus::Rascunho->value)
            ->sole();

        $anterior = RuleVersion::vigente(RuleDomain::Territorial)->sole();

        $this->actingAs($publicador, 'gestao')
            ->put("/gestao/louos/territoriais/versoes/{$rascunho->id}/publicar")
            ->assertRedirect('/gestao/louos?aba=territoriais');

        $this->assertSame(RuleVersionStatus::Vigente, $rascunho->fresh()->status);
        $this->assertSame(RuleVersionStatus::Substituida, $anterior->fresh()->status);
        $this->assertNotNull($anterior->fresh()->valid_to);

        // O motor lê a NOVA vigente: RT-01 com 1 CNAE.
        $rt01 = TerritorialRule::query()->where('rule_version_id', $rascunho->id)->where('codigo', 'RT-01')->sole();
        $this->assertSame(['4520006'], $rt01->cnaes()->pluck('cnae')->all());
    }

    public function test_descartar_rascunho_preserva_a_vigente(): void
    {
        $autor = $this->administrador();

        $this->actingAs($autor, 'gestao')
            ->post('/gestao/louos/territoriais/rascunho', $this->payloadRt01());

        $rascunho = RuleVersion::query()
            ->where('domain', RuleDomain::Territorial->value)
            ->where('status', RuleVersionStatus::Rascunho->value)
            ->sole();

        $this->actingAs($autor, 'gestao')
            ->delete("/gestao/louos/territoriais/rascunho/{$rascunho->id}")
            ->assertRedirect('/gestao/louos?aba=territoriais');

        $this->assertNull(RuleVersion::query()->find($rascunho->id));
        $this->assertSame(9, TerritorialRule::query()
            ->where('rule_version_id', RuleVersion::vigente(RuleDomain::Territorial)->sole()->id)
            ->count());
    }

    public function test_escrita_exige_manter_louos(): void
    {
        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create();

        $this->actingAs($analista, 'gestao')
            ->post('/gestao/louos/territoriais/rascunho', $this->payloadRt01())
            ->assertForbidden();
    }

    public function test_aba_territoriais_exige_consultar_louos(): void
    {
        // Usuário com consultar-cnaes (aba Enquadramento) mas SEM
        // consultar-louos — a aba territorial re-checa a permissão do domínio.
        $consultorCnaes = User::factory()->withAcceptedLgpdTerm()->create();
        $consultorCnaes->givePermissionTo(['acessar-gestao', 'consultar-cnaes']);

        $this->actingAs($consultorCnaes, 'gestao')
            ->get('/gestao/louos?aba=territoriais')
            ->assertForbidden();

        $this->actingAs($consultorCnaes, 'gestao')
            ->get('/gestao/louos?aba=enquadramento')
            ->assertOk();
    }

    public function test_validacao_rejeita_cnae_fora_do_formato(): void
    {
        $this->actingAs($this->administrador(), 'gestao')
            ->post('/gestao/louos/territoriais/rascunho', $this->payloadRt01(['cnaes' => ['4520-0/06']]))
            ->assertSessionHasErrors('cnaes.0');
    }
}
