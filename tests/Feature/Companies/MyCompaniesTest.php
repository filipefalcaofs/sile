<?php

namespace Tests\Feature\Companies;

use App\Models\Cnae;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Parameter;
use App\Models\Procuration;
use App\Models\User;
use Database\Seeders\ParameterSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * HU-027 — consultar empresas vinculadas. As asserções Inertia NÃO usam
 * ->component(): as páginas React desta fase só nascem no 03-07 (wave 6) e a
 * checagem de componente exige o arquivo de página em disco. A verificação
 * visual do componente fica registrada para o 03-07.
 */
class MyCompaniesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function portalUser(): User
    {
        return User::factory()->cidadao()->withAcceptedLgpdTerm()->create();
    }

    /**
     * Cria uma empresa vinculada ao usuário (responsável ativo por padrão).
     */
    private function companyFor(User $user, array $companyAttributes = [], ?callable $linkState = null): Company
    {
        $company = Company::factory()->create($companyAttributes);

        $factory = CompanyUser::factory()->for($company)->for($user);

        if ($linkState !== null) {
            $factory = $linkState($factory);
        }

        $factory->create();

        return $company;
    }

    public function test_lista_somente_empresas_do_usuario(): void
    {
        $userA = $this->portalUser();
        $userB = $this->portalUser();

        $this->companyFor($userA);
        $this->companyFor($userA);
        $this->companyFor($userB);

        $this->actingAs($userA)
            ->get('/portal/empresas')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('companies.data', 2)
                ->has('companies.data.0', fn (Assert $item) => $item
                    ->has('id')
                    ->has('legal_name')
                    ->has('trade_name')
                    ->has('formatted_cnpj')
                    ->has('source', fn (Assert $source) => $source->has('value')->has('label'))
                    ->has('primary_cnae')
                    ->has('link', fn (Assert $link) => $link->has('role_label')->has('active')->etc())));
    }

    public function test_item_traz_cnae_principal_e_situacao_do_vinculo(): void
    {
        $user = $this->portalUser();
        $cnae = Cnae::factory()->create(['code' => '5611201']);
        $company = Company::factory()->withPrimaryCnae($cnae)->create();
        CompanyUser::factory()->for($company)->for($user)->create();

        $this->actingAs($user)
            ->get('/portal/empresas')
            ->assertInertia(fn (Assert $page) => $page
                ->where('companies.data.0.primary_cnae.formatted_code', $cnae->formatted_code)
                ->where('companies.data.0.link.active', true));
    }

    public function test_vinculo_encerrado_aparece_com_situacao_encerrado(): void
    {
        $user = $this->portalUser();
        $this->companyFor($user, linkState: fn ($f) => $f->ended());

        $this->actingAs($user)
            ->get('/portal/empresas')
            ->assertInertia(fn (Assert $page) => $page
                ->has('companies.data', 1)
                ->where('companies.data.0.link.active', false));
    }

    public function test_busca_filtra_por_razao_social_sem_case(): void
    {
        $user = $this->portalUser();
        $this->companyFor($user, ['legal_name' => 'Padaria Central']);
        $this->companyFor($user, ['legal_name' => 'Mercado Sul']);

        $this->actingAs($user)
            ->get('/portal/empresas?search=padaria')
            ->assertInertia(fn (Assert $page) => $page
                ->has('companies.data', 1)
                ->where('companies.data.0.legal_name', 'Padaria Central'));
    }

    public function test_busca_por_cnpj_com_digitos(): void
    {
        $user = $this->portalUser();
        $this->companyFor($user, ['cnpj' => '00000000000191', 'legal_name' => 'Empresa Alvo']);
        $this->companyFor($user, ['legal_name' => 'Outra Empresa']);

        $this->actingAs($user)
            ->get('/portal/empresas?search=00.000.000')
            ->assertInertia(fn (Assert $page) => $page
                ->has('companies.data', 1)
                ->where('companies.data.0.legal_name', 'Empresa Alvo'));
    }

    public function test_paginacao_e_parametrizada(): void
    {
        $this->seed(ParameterSeeder::class);
        Parameter::query()->where('key', 'ui.companies.per_page')->first()->update(['value' => '5']);

        $user = $this->portalUser();
        for ($i = 0; $i < 6; $i++) {
            $this->companyFor($user);
        }

        $this->actingAs($user)
            ->get('/portal/empresas')
            ->assertInertia(fn (Assert $page) => $page
                ->has('companies.data', 5)
                ->where('companies.per_page', 5)
                ->where('companies.total', 6));
    }

    public function test_visitante_e_redirecionado(): void
    {
        $this->get('/portal/empresas')->assertRedirect('/portal/login');
    }

    public function test_procurador_em_representacao_ve_empresas_do_representado(): void
    {
        $grantor = $this->portalUser();
        $attorney = $this->portalUser();
        $this->companyFor($grantor);

        $proc = Procuration::factory()->create([
            'grantor_user_id' => $grantor->id,
            'attorney_user_id' => $attorney->id,
        ]);

        // Com a representação ativa: vê a empresa do representado.
        $this->actingAs($attorney)
            ->withSession(['acting_procuration_id' => $proc->id])
            ->get('/portal/empresas')
            ->assertInertia(fn (Assert $page) => $page->has('companies.data', 1));

        // Sem a sessão de representação: não vê nada (não tem empresas próprias).
        // flushSession remove o acting_procuration_id que o test client retém
        // entre requests — simula o procurador sem representação ativa.
        $this->flushSession();

        $this->actingAs($attorney)
            ->get('/portal/empresas')
            ->assertInertia(fn (Assert $page) => $page->has('companies.data', 0));
    }

    public function test_consulta_propria_nao_exige_log_adicional(): void
    {
        // HU-027 CA-02: GET é consulta às PRÓPRIAS empresas — não há ação de
        // negócio a auditar além do padrão. Asserção apenas de resposta + props.
        $user = $this->portalUser();
        $this->companyFor($user);

        $this->actingAs($user)
            ->get('/portal/empresas')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('companies')
                ->has('filters')
                ->has('perPageOptions')
                ->has('totalCompanies'));
    }
}
