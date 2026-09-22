<?php

namespace Tests\Feature\Analise;

use App\Enums\RuleDomain;
use App\Enums\RuleVersionStatus;
use App\Models\RuleVersion;
use App\Models\TllValor;
use App\Models\TratamentoEnquadramento;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Vínculo CNAE na tela de tarifas TLL: derivado da planilha vigente
 * (treatment_enquadramentos), somente leitura — não grava CNAE em tll_valores.
 */
class TllCnaeVinculoTest extends TestCase
{
    use LazilyRefreshDatabase;

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
     * @param  list<array<string, string>>  $linhas
     */
    private function publicarPlanilha(array $linhas, RuleVersionStatus $status = RuleVersionStatus::Vigente): RuleVersion
    {
        $versao = RuleVersion::factory()->create([
            'domain' => RuleDomain::RiscoTratamento,
            'version' => 'planilha-vinculo-tll',
            'status' => $status,
        ]);

        foreach ($linhas as $i => $linha) {
            TratamentoEnquadramento::query()->create([
                'rule_version_id' => $versao->id,
                'cnae' => $linha['cnae'],
                'denominacao' => $linha['denominacao'] ?? 'Atividade',
                'risco' => 'baixo',
                'codigo_louos' => $linha['codigo_louos'] ?? sprintf('07.12.%02d', $i + 1),
                'subcategoria' => $linha['subcategoria'] ?? 'nR1-12',
                'grupo' => 'nR1',
                'codigo_tll' => $linha['codigo_tll'],
                'especificacao_tll' => $linha['especificacao_tll'] ?? null,
            ]);
        }

        return $versao;
    }

    public function test_lista_expoe_contagem_de_cnaes_da_planilha_vigente(): void
    {
        $this->publicarPlanilha([
            ['cnae' => '0111-3/01', 'codigo_tll' => '1.01', 'codigo_louos' => '07.12.13'],
            ['cnae' => '0111-3/01', 'codigo_tll' => '1.18', 'codigo_louos' => '08A.02.03'],
            ['cnae' => '4712-1/00', 'codigo_tll' => '1.01', 'codigo_louos' => '07.12.13'],
        ]);

        TllValor::factory()->create(['codigo_tll' => '1.01', 'especificacao' => 'Administração, Organização e Planejamento']);
        TllValor::factory()->create(['codigo_tll' => '1.18', 'especificacao' => 'Estabelecimentos não classificados']);

        $page = $this->actingAs($this->administrador(), 'gestao')
            ->get('/gestao/tll')
            ->assertOk()
            ->viewData('page');

        $porCodigo = collect($page['props']['valores']['data'])->keyBy('codigo_tll');

        $this->assertSame(2, $porCodigo['1.01']['cnaes_count']);
        $this->assertSame(1, $porCodigo['1.18']['cnaes_count']);
    }

    public function test_rascunho_da_planilha_nao_entra_na_contagem(): void
    {
        $this->publicarPlanilha([
            ['cnae' => '0111-3/01', 'codigo_tll' => '1.01'],
        ], RuleVersionStatus::Rascunho);

        TllValor::factory()->create(['codigo_tll' => '1.01']);

        $page = $this->actingAs($this->administrador(), 'gestao')
            ->get('/gestao/tll')
            ->assertOk()
            ->viewData('page');

        $this->assertSame(0, $page['props']['valores']['data'][0]['cnaes_count']);
    }

    public function test_busca_por_cnae_mascara_e_digitos_devolve_os_tll_resolvidos(): void
    {
        $this->publicarPlanilha([
            ['cnae' => '0111-3/01', 'codigo_tll' => '1.01', 'codigo_louos' => '07.12.13'],
            ['cnae' => '0111-3/01', 'codigo_tll' => '1.18', 'codigo_louos' => '08A.02.03'],
            ['cnae' => '4712-1/00', 'codigo_tll' => '2.02', 'codigo_louos' => '07.12.13'],
        ]);

        TllValor::factory()->create(['codigo_tll' => '1.01']);
        TllValor::factory()->create(['codigo_tll' => '1.18']);
        TllValor::factory()->create(['codigo_tll' => '2.02']);

        $admin = $this->administrador();

        $mascarado = $this->actingAs($admin, 'gestao')
            ->get('/gestao/tll?search='.urlencode('0111-3/01'))
            ->assertOk()
            ->viewData('page');

        $this->assertEqualsCanonicalizing(
            ['1.01', '1.18'],
            collect($mascarado['props']['valores']['data'])->pluck('codigo_tll')->all(),
        );

        $digitos = $this->actingAs($admin, 'gestao')
            ->get('/gestao/tll?search=0111301')
            ->assertOk()
            ->viewData('page');

        $this->assertEqualsCanonicalizing(
            ['1.01', '1.18'],
            collect($digitos['props']['valores']['data'])->pluck('codigo_tll')->all(),
        );
    }

    public function test_endpoint_lista_cnaes_distintos_e_separa_isenta_do_residual(): void
    {
        $this->publicarPlanilha([
            ['cnae' => '0111-3/01', 'denominacao' => 'Cultivo de arroz', 'codigo_tll' => '1.01', 'codigo_louos' => '07.12.13'],
            ['cnae' => '4712-1/00', 'denominacao' => 'Comércio varejista', 'codigo_tll' => '1.01', 'codigo_louos' => '07.12.13'],
            ['cnae' => '8411-6/00', 'denominacao' => 'Administração pública', 'codigo_tll' => '6.00', 'especificacao_tll' => 'Estabelecimentos não classificados'],
            ['cnae' => '9499-5/00', 'denominacao' => 'Atividade isenta', 'codigo_tll' => '0.00', 'especificacao_tll' => 'ISENTA'],
        ]);

        $admin = $this->administrador();
        $tarifa101 = TllValor::factory()->create(['codigo_tll' => '1.01']);
        $isenta = TllValor::factory()->create(['codigo_tll' => '6.00', 'especificacao' => 'ISENTA']);
        $residual = TllValor::factory()->create([
            'codigo_tll' => '6.00',
            'especificacao' => 'Estabelecimentos não Classificados nos Itens 3.00 a 5.00',
        ]);

        $this->actingAs($admin, 'gestao')
            ->getJson("/gestao/tll/{$tarifa101->id}/cnaes")
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonFragment(['cnae' => '0111-3/01', 'denominacao' => 'Cultivo de arroz'])
            ->assertJsonFragment(['cnae' => '4712-1/00', 'denominacao' => 'Comércio varejista']);

        $this->actingAs($admin, 'gestao')
            ->getJson("/gestao/tll/{$isenta->id}/cnaes")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonFragment(['cnae' => '9499-5/00']);

        $this->actingAs($admin, 'gestao')
            ->getJson("/gestao/tll/{$residual->id}/cnaes")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonFragment(['cnae' => '8411-6/00']);
    }

    public function test_endpoint_de_cnaes_exige_permissao_e_nao_grava_vinculo(): void
    {
        $valor = TllValor::factory()->create(['codigo_tll' => '1.01']);

        $this->actingAs($this->analista(), 'gestao')
            ->getJson("/gestao/tll/{$valor->id}/cnaes")
            ->assertForbidden();

        $this->assertDatabaseMissing('treatment_enquadramentos', ['codigo_tll' => '1.01']);
        $this->assertFalse(Schema::hasColumn('tll_valores', 'cnae'));
    }
}
