<?php

namespace Tests\Feature\Companies;

use App\Models\Activity;
use App\Models\Cnae;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\User;
use App\Services\CompanyCnaeService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecondaryCnaesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /**
     * Cidadão habilitado a navegar no portal (papel + termo LGPD aceito).
     */
    private function portalUser(): User
    {
        return User::factory()->cidadao()->withAcceptedLgpdTerm()->create();
    }

    /**
     * Cria uma empresa com CNAE principal e vínculo ativo de responsável.
     */
    private function companyLinkedTo(User $user, Cnae $primaryCnae): Company
    {
        $company = Company::factory()->withPrimaryCnae($primaryCnae)->create();

        CompanyUser::factory()->responsavel()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
        ]);

        return $company;
    }

    public function test_sincroniza_conjunto_exato_de_secundarios(): void
    {
        $primary = Cnae::factory()->create();
        $company = Company::factory()->withPrimaryCnae($primary)->create();
        [$b, $c, $d] = Cnae::factory()->count(3)->create();

        $service = app(CompanyCnaeService::class);
        $service->syncSecondaries($company, [$b->id, $c->id]);
        $service->syncSecondaries($company, [$c->id, $d->id]);

        $secondaryIds = $company->cnaes()->wherePivot('is_primary', false)->pluck('cnaes.id');

        $this->assertEqualsCanonicalizing([$c->id, $d->id], $secondaryIds->all());
        $this->assertSame(1, $company->primaryCnae()->count());
        $this->assertSame($primary->id, $company->primaryCnae()->first()?->id);
    }

    public function test_remocao_total_de_secundarios_e_permitida(): void
    {
        $primary = Cnae::factory()->create();
        $company = Company::factory()->withPrimaryCnae($primary)->create();
        $b = Cnae::factory()->create();

        $service = app(CompanyCnaeService::class);
        $service->syncSecondaries($company, [$b->id]);
        $service->syncSecondaries($company, []);

        $this->assertSame(0, $company->cnaes()->wherePivot('is_primary', false)->count());
        $this->assertSame($primary->id, $company->primaryCnae()->first()?->id);
    }

    public function test_sincronizacao_e_auditada_com_antes_e_depois(): void
    {
        $primary = Cnae::factory()->create();
        $company = Company::factory()->withPrimaryCnae($primary)->create();
        [$b, $c] = Cnae::factory()->count(2)->create();

        app(CompanyCnaeService::class)->syncSecondaries($company, [$b->id, $c->id]);

        $activity = Activity::where('log_name', 'empresas')
            ->where('event', 'cnaes-secundarios')
            ->latest('id')
            ->first();

        $this->assertNotNull($activity, 'Esperava activity da sincronização de secundários');
        $this->assertSame('sucesso', $activity->result);
        $this->assertSame($company->id, $activity->properties['empresa_id']);
        $this->assertSame([], $activity->properties['antes']);
        $this->assertEqualsCanonicalizing([$b->code, $c->code], $activity->properties['depois']);
    }

    public function test_endpoint_sincroniza_secundarios(): void
    {
        $user = $this->portalUser();
        $primary = Cnae::factory()->create();
        $company = $this->companyLinkedTo($user, $primary);
        [$b, $c] = Cnae::factory()->count(2)->create();

        $this->actingAs($user)
            ->put("/portal/empresas/{$company->id}/cnaes-secundarios", ['cnaes' => [$b->id, $c->id]])
            ->assertRedirect()
            ->assertSessionHas('status');

        $secondaryIds = $company->cnaes()->wherePivot('is_primary', false)->pluck('cnaes.id');

        $this->assertEqualsCanonicalizing([$b->id, $c->id], $secondaryIds->all());
        $this->assertSame($primary->id, $company->primaryCnae()->first()?->id);
    }

    public function test_secundario_duplicado_e_rejeitado(): void
    {
        $user = $this->portalUser();
        $company = $this->companyLinkedTo($user, Cnae::factory()->create());
        $b = Cnae::factory()->create();

        $this->actingAs($user)
            ->put("/portal/empresas/{$company->id}/cnaes-secundarios", ['cnaes' => [$b->id, $b->id]])
            ->assertSessionHasErrors(['cnaes.0' => 'Há CNAE duplicado na seleção.']);

        $this->assertSame(0, $company->cnaes()->wherePivot('is_primary', false)->count());
    }

    public function test_secundario_igual_ao_principal_e_rejeitado(): void
    {
        $user = $this->portalUser();
        $primary = Cnae::factory()->create();
        $company = $this->companyLinkedTo($user, $primary);

        $this->actingAs($user)
            ->put("/portal/empresas/{$company->id}/cnaes-secundarios", ['cnaes' => [$primary->id]])
            ->assertSessionHasErrors([
                'cnaes' => 'O CNAE principal não pode ser incluído entre os secundários.',
            ]);

        $this->assertSame(0, $company->cnaes()->wherePivot('is_primary', false)->count());
    }

    public function test_nao_vinculado_recebe_403(): void
    {
        $owner = $this->portalUser();
        $intruder = $this->portalUser();
        $company = $this->companyLinkedTo($owner, Cnae::factory()->create());
        $b = Cnae::factory()->create();

        $this->actingAs($intruder)
            ->put("/portal/empresas/{$company->id}/cnaes-secundarios", ['cnaes' => [$b->id]])
            ->assertForbidden();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'seguranca',
            'event' => 'acesso-negado',
            'result' => 'bloqueado',
        ]);
    }

    public function test_busca_de_cnaes_retorna_somente_ativos(): void
    {
        $active = Cnae::factory()->create([
            'code' => '5611201',
            'description' => 'Restaurantes e similares',
        ]);
        Cnae::factory()->inactive()->create([
            'code' => '5611202',
            'description' => 'Restaurante inativo de teste',
        ]);

        $this->actingAs($this->portalUser())
            ->get('/portal/cnaes?search=restaurante')
            ->assertOk()
            ->assertExactJson([
                [
                    'id' => $active->id,
                    'formatted_code' => '5611-2/01',
                    'description' => 'Restaurantes e similares',
                ],
            ]);
    }

    public function test_busca_de_cnaes_por_codigo(): void
    {
        $cnae = Cnae::factory()->create([
            'code' => '5611201',
            'description' => 'Restaurantes e similares',
        ]);
        Cnae::factory()->create([
            'code' => '4711302',
            'description' => 'Supermercados',
        ]);

        $this->actingAs($this->portalUser())
            ->get('/portal/cnaes?search=5611')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['id' => $cnae->id]);
    }

    public function test_busca_de_cnaes_exige_autenticacao(): void
    {
        $this->get('/portal/cnaes')->assertRedirect('/portal/login');
    }
}
