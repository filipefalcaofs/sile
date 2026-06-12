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

class PrimaryCnaeTest extends TestCase
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
     * Cria uma empresa com vínculo ativo de responsável do usuário informado.
     */
    private function companyLinkedTo(User $user): Company
    {
        $company = Company::factory()->create();

        CompanyUser::factory()->responsavel()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
        ]);

        return $company;
    }

    public function test_define_cnae_principal_unico(): void
    {
        $company = Company::factory()->create();
        $cnae = Cnae::factory()->create();

        app(CompanyCnaeService::class)->setPrimary($company, $cnae);

        $this->assertDatabaseHas('company_cnae', [
            'company_id' => $company->id,
            'cnae_id' => $cnae->id,
            'is_primary' => true,
        ]);
        $this->assertSame(1, $company->cnaes()->count());
        $this->assertSame(1, $company->primaryCnae()->count());
    }

    public function test_troca_de_principal_demove_o_antigo(): void
    {
        $company = Company::factory()->create();
        [$first, $second] = Cnae::factory()->count(2)->create();

        $service = app(CompanyCnaeService::class);
        $service->setPrimary($company, $first);
        $service->setPrimary($company, $second);

        // O antigo principal permanece vinculado, demovido a secundário.
        $this->assertDatabaseHas('company_cnae', [
            'company_id' => $company->id,
            'cnae_id' => $first->id,
            'is_primary' => false,
        ]);
        $this->assertSame(1, $company->primaryCnae()->count());
        $this->assertSame($second->id, $company->primaryCnae()->first()?->id);
    }

    public function test_troca_de_principal_e_auditada_com_anterior_e_novo(): void
    {
        $company = Company::factory()->create();
        [$first, $second] = Cnae::factory()->count(2)->create();

        $service = app(CompanyCnaeService::class);
        $service->setPrimary($company, $first);
        $service->setPrimary($company, $second);

        $activity = Activity::where('log_name', 'empresas')
            ->where('event', 'cnae-principal')
            ->latest('id')
            ->first();

        $this->assertNotNull($activity, 'Esperava activity da troca de CNAE principal');
        $this->assertSame('sucesso', $activity->result);
        $this->assertSame($company->id, $activity->properties['empresa_id']);
        $this->assertSame($first->code, $activity->properties['cnae_anterior']);
        $this->assertSame($second->code, $activity->properties['cnae_novo']);
    }

    public function test_endpoint_define_principal(): void
    {
        $user = $this->portalUser();
        $company = $this->companyLinkedTo($user);
        $cnae = Cnae::factory()->create();

        $this->actingAs($user)
            ->put("/portal/empresas/{$company->id}/cnae-principal", ['cnae_id' => $cnae->id])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('company_cnae', [
            'company_id' => $company->id,
            'cnae_id' => $cnae->id,
            'is_primary' => true,
        ]);
        $this->assertSame(1, $company->primaryCnae()->count());
    }

    public function test_cnae_inativo_e_rejeitado(): void
    {
        $user = $this->portalUser();
        $company = $this->companyLinkedTo($user);
        $inactive = Cnae::factory()->inactive()->create();

        $this->actingAs($user)
            ->put("/portal/empresas/{$company->id}/cnae-principal", ['cnae_id' => $inactive->id])
            ->assertSessionHasErrors([
                'cnae_id' => 'O CNAE informado não está ativo na tabela oficial.',
            ]);

        $this->assertSame(0, $company->cnaes()->count());
    }

    public function test_cnae_inexistente_e_rejeitado(): void
    {
        $user = $this->portalUser();
        $company = $this->companyLinkedTo($user);

        $this->actingAs($user)
            ->put("/portal/empresas/{$company->id}/cnae-principal", ['cnae_id' => 999999])
            ->assertSessionHasErrors('cnae_id');

        $this->assertSame(0, $company->cnaes()->count());
    }

    public function test_nao_vinculado_recebe_403(): void
    {
        $owner = $this->portalUser();
        $intruder = $this->portalUser();
        $company = $this->companyLinkedTo($owner);
        $cnae = Cnae::factory()->create();

        $this->actingAs($intruder)
            ->put("/portal/empresas/{$company->id}/cnae-principal", ['cnae_id' => $cnae->id])
            ->assertForbidden();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'seguranca',
            'event' => 'acesso-negado',
            'result' => 'bloqueado',
        ]);
        $this->assertSame(0, $company->cnaes()->count());
    }
}
