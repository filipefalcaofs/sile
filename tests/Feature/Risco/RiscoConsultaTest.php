<?php

namespace Tests\Feature\Risco;

use App\Enums\RiscoMunicipal;
use App\Enums\RuleDomain;
use App\Enums\RuleVersionStatus;
use App\Models\Cnae;
use App\Models\RiskClassification;
use App\Models\RuleVersion;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Mantenedores de risco no console SEDUR (backend): consulta da tabela de
 * risco vigente (HU-052) com busca/filtro/auditoria e atualização VERSIONADA
 * por quatro olhos (HU-053/HU-020) — atualizar publica uma NOVA versão e
 * preserva a anterior, nunca edição destrutiva. Bloqueio por permissão é
 * auditado (CA-04). Gate cross-guard via permission: (PADRÃO 04-03).
 */
class RiscoConsultaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function analista(): User
    {
        return User::factory()->analista()->withAcceptedLgpdTerm()->create();
    }

    private function administrador(): User
    {
        return User::factory()->administrador()->withAcceptedLgpdTerm()->create();
    }

    /**
     * Tabela municipal vigente com 4 classificações (baixo_a×2, baixo_b×1,
     * alto×1) e os CNAEs correspondentes para a busca por denominação, mais a
     * versão sanitária vigente exibida na consulta.
     */
    private function seedTabelaVigente(): RuleVersion
    {
        $municipal = RuleVersion::factory()->create([
            'domain' => RuleDomain::RiscoMunicipal,
            'version' => 'decreto-32636-2020',
            'rules_version' => 'decreto-32636-2020',
        ]);

        RuleVersion::factory()->create([
            'domain' => RuleDomain::RiscoSanitario,
            'version' => 'visa-unificada-2026-04-30',
            'rules_version' => 'visa-unificada-2026-04-30',
        ]);

        $linhas = [
            ['0111301', 'Cultivo de arroz', RiscoMunicipal::BaixoA],
            ['4721102', 'Padaria e confeitaria com predominância de revenda', RiscoMunicipal::BaixoA],
            ['4711301', 'Comércio varejista de mercadorias em geral - hipermercados', RiscoMunicipal::BaixoB],
            ['2011800', 'Fabricação de cloro e álcalis', RiscoMunicipal::Alto],
        ];

        foreach ($linhas as [$code, $descricao, $nivel]) {
            Cnae::factory()->create(['code' => $code, 'description' => $descricao]);
            RiskClassification::factory()->create([
                'rule_version_id' => $municipal->id,
                'cnae_code' => $code,
                'risco_municipal' => $nivel,
            ]);
        }

        return $municipal;
    }

    public function test_analista_consulta_tabela_de_risco_vigente(): void
    {
        $this->seedTabelaVigente();

        $this->actingAs($this->analista(), 'gestao')
            ->get('/gestao/risco')
            ->assertOk()
            // A página React 'gestao/risco/index' foi entregue no 06-08: o
            // component é validado contra o arquivo em disco. O contrato das
            // props é validado abaixo.
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/risco/index')
                ->has('classificacoes.data', 4)
                ->where('versaoMunicipal.version', 'decreto-32636-2020')
                ->where('versaoSanitaria.version', 'visa-unificada-2026-04-30')
                ->where('resumoNiveis.baixo_a', 2)
                ->where('resumoNiveis.baixo_b', 1)
                ->where('resumoNiveis.alto', 1)
                ->has('perPageOptions'));
    }

    public function test_busca_e_filtro_por_nivel_funcionam(): void
    {
        $this->seedTabelaVigente();
        $analista = $this->analista();

        // Busca por denominação (case-insensitive).
        $this->actingAs($analista, 'gestao')
            ->get('/gestao/risco?search=arroz')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('classificacoes.data', 1)
                ->where('classificacoes.data.0.cnae_code', '0111301'));

        // Busca por código (prefixo de dígitos).
        $this->actingAs($analista, 'gestao')
            ->get('/gestao/risco?search=2011')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('classificacoes.data', 1)
                ->where('classificacoes.data.0.cnae_code', '2011800'));

        // Filtro por nível.
        $this->actingAs($analista, 'gestao')
            ->get('/gestao/risco?nivel=alto')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('classificacoes.data', 1)
                ->where('classificacoes.data.0.risco_municipal', 'alto'));
    }

    public function test_consulta_e_auditada(): void
    {
        $this->seedTabelaVigente();

        $this->actingAs($this->analista(), 'gestao')
            ->get('/gestao/risco')
            ->assertOk();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'risco',
            'event' => 'consulta-tabela',
            'result' => 'sucesso',
        ]);
    }

    public function test_sem_permissao_consultar_risco_recebe_403_auditado(): void
    {
        // Acessa a gestão mas NÃO tem consultar-risco: o gate específico barra
        // e audita (CA-04), espelhando o território (04-03).
        $semPermissao = User::factory()->withAcceptedLgpdTerm()->create();
        $semPermissao->givePermissionTo('acessar-gestao');

        $this->actingAs($semPermissao, 'gestao')
            ->get('/gestao/risco')
            ->assertForbidden();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'seguranca',
            'event' => 'acesso-negado',
            'result' => 'bloqueado',
        ]);
    }

    public function test_atualizar_publica_nova_versao_preservando_a_anterior(): void
    {
        $anterior = $this->seedTabelaVigente();
        $publisher = $this->administrador();
        $author = User::factory()->administrador()->withAcceptedLgpdTerm()->create();

        $this->actingAs($publisher, 'gestao')
            ->put('/gestao/risco/publicar', [
                'version' => 'decreto-32636-2020-rev2',
                'author_id' => $author->id,
                'alteracoes' => [
                    ['cnae_code' => '0111301', 'risco_municipal' => 'alto'],
                ],
            ])
            ->assertSessionHas('status');

        $nova = RuleVersion::query()->where('version', 'decreto-32636-2020-rev2')->first();

        // A anterior foi FECHADA (substituída, com valid_to) — nunca apagada.
        $this->assertSame(RuleVersionStatus::Substituida, $anterior->fresh()->status);
        $this->assertNotNull($anterior->fresh()->valid_to);

        // A nova é a vigente do domínio municipal.
        $this->assertNotNull($nova);
        $this->assertSame(RuleVersionStatus::Vigente, $nova->status);
        $this->assertNull($nova->valid_to);
        $this->assertSame($nova->id, RuleVersion::vigente(RuleDomain::RiscoMunicipal)->first()->id);

        // A nova versão CONTÉM as classificações copiadas + a alteração aplicada
        // (não-destrutivo, sem fachada).
        $this->assertSame(4, RiskClassification::query()->where('rule_version_id', $nova->id)->count());
        $this->assertSame(
            RiscoMunicipal::Alto,
            RiskClassification::query()
                ->where('rule_version_id', $nova->id)
                ->where('cnae_code', '0111301')
                ->first()->risco_municipal,
        );

        // A versão anterior preservou o nível original do mesmo CNAE.
        $this->assertSame(
            RiscoMunicipal::BaixoA,
            RiskClassification::query()
                ->where('rule_version_id', $anterior->id)
                ->where('cnae_code', '0111301')
                ->first()->risco_municipal,
        );

        // Quatro olhos: autor distinto do publicador, registrados na versão.
        $this->assertSame($author->id, $nova->created_by);
        $this->assertSame($publisher->id, $nova->published_by);

        // Publicação auditada (RN-002), reusando o contrato do RuleVersionService.
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'regras',
            'event' => 'publicacao-versao',
            'rules_version' => 'decreto-32636-2020-rev2',
        ]);
    }

    public function test_publicacao_pelo_mesmo_autor_e_bloqueada_por_quatro_olhos(): void
    {
        $this->seedTabelaVigente();
        $mesmo = $this->administrador();

        $this->actingAs($mesmo, 'gestao')
            ->put('/gestao/risco/publicar', [
                'version' => 'decreto-32636-2020-rev2',
                'author_id' => $mesmo->id,
                'alteracoes' => [],
            ])
            ->assertSessionHas('error');

        // Degradação controlada e comunicada (nunca silenciosa): nada publicado.
        $this->assertNull(RuleVersion::query()->where('version', 'decreto-32636-2020-rev2')->first());
        $this->assertSame(
            'decreto-32636-2020',
            RuleVersion::vigente(RuleDomain::RiscoMunicipal)->first()->version,
        );
    }

    public function test_sem_permissao_manter_risco_nao_publica(): void
    {
        $this->seedTabelaVigente();
        $author = User::factory()->administrador()->withAcceptedLgpdTerm()->create();

        // Analista tem consultar-risco mas NÃO manter-risco.
        $this->actingAs($this->analista(), 'gestao')
            ->put('/gestao/risco/publicar', [
                'version' => 'decreto-32636-2020-rev2',
                'author_id' => $author->id,
                'alteracoes' => [],
            ])
            ->assertForbidden();

        $this->assertNull(RuleVersion::query()->where('version', 'decreto-32636-2020-rev2')->first());
    }
}
