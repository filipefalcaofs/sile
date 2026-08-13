<?php

namespace Tests\Feature\Companies;

use App\Models\Activity;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyUpdateTest extends TestCase
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
     * Cria uma empresa com vínculo (ativo por padrão) do usuário informado.
     */
    private function companyLinkedTo(User $user, array $linkAttributes = []): Company
    {
        $company = Company::factory()->withPrimaryCnae()->create();

        CompanyUser::factory()->responsavel()->create(array_merge([
            'company_id' => $company->id,
            'user_id' => $user->id,
        ], $linkAttributes));

        return $company;
    }

    public function test_vinculado_ativo_ve_o_detalhe(): void
    {
        $user = $this->portalUser();
        $company = $this->companyLinkedTo($user);

        $this->actingAs($user)
            ->get("/portal/empresas/{$company->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('company', fn ($c) => $c
                    ->where('id', $company->id)
                    ->where('legal_name', $company->legal_name)
                    ->where('formatted_cnpj', $company->formatted_cnpj)
                    ->where('source.label', 'Cadastro manual')
                    ->etc()
                )
                ->has('cnaes')
                ->has('links')
                ->where('abilities.update', true)
            );
    }

    public function test_nao_vinculado_nao_ve_detalhe_e_evento_e_auditado(): void
    {
        $owner = $this->portalUser();
        $intruder = $this->portalUser();
        $company = $this->companyLinkedTo($owner);

        $this->actingAs($intruder)
            ->get("/portal/empresas/{$company->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'seguranca',
            'event' => 'acesso-negado',
            'result' => 'bloqueado',
        ]);
    }

    public function test_vinculado_ativo_atualiza_dados_complementares(): void
    {
        $user = $this->portalUser();
        $company = $this->companyLinkedTo($user);

        $this->actingAs($user)
            ->put("/portal/empresas/{$company->id}", [
                'legal_name' => $company->legal_name,
                'trade_name' => 'Nome Fantasia Novo',
                'email' => 'novo@exemplo.com',
                'street' => 'Avenida Sete de Setembro',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'trade_name' => 'Nome Fantasia Novo',
            'email' => 'novo@exemplo.com',
            'street' => 'Avenida Sete de Setembro',
        ]);
    }

    public function test_atualizacao_e_auditada_com_diff(): void
    {
        $user = $this->portalUser();
        $company = $this->companyLinkedTo($user);

        $this->actingAs($user)
            ->put("/portal/empresas/{$company->id}", [
                'legal_name' => $company->legal_name,
                'trade_name' => 'Fantasia Auditável',
            ])
            ->assertRedirect();

        $activity = Activity::where('event', 'updated')
            ->where('subject_type', (new Company)->getMorphClass())
            ->where('subject_id', $company->id)
            ->first();

        $this->assertNotNull($activity, 'Esperava activity de atualização da empresa');
        $this->assertArrayHasKey('trade_name', $activity->attribute_changes['attributes'] ?? []);
    }

    public function test_cnpj_e_imutavel(): void
    {
        $user = $this->portalUser();
        $company = $this->companyLinkedTo($user);
        $originalCnpj = $company->cnpj;

        $this->actingAs($user)
            ->put("/portal/empresas/{$company->id}", [
                'legal_name' => $company->legal_name,
                'cnpj' => '12345678000199',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame($originalCnpj, $company->refresh()->cnpj);
    }

    public function test_nao_vinculado_nao_atualiza(): void
    {
        $owner = $this->portalUser();
        $intruder = $this->portalUser();
        $company = $this->companyLinkedTo($owner);

        $this->actingAs($intruder)
            ->put("/portal/empresas/{$company->id}", [
                'legal_name' => 'Tentativa',
            ])
            ->assertForbidden();
    }

    public function test_vinculo_encerrado_nao_atualiza(): void
    {
        $user = $this->portalUser();
        $company = $this->companyLinkedTo($user, [
            'ended_at' => now(),
            'ended_reason' => 'Encerrado em teste',
        ]);

        // Update exige vínculo ATIVO → 403.
        $this->actingAs($user)
            ->put("/portal/empresas/{$company->id}", [
                'legal_name' => 'Tentativa',
            ])
            ->assertForbidden();

        // Mas o detalhe continua acessível (view aceita vínculo encerrado).
        $this->actingAs($user)
            ->get("/portal/empresas/{$company->id}")
            ->assertOk();
    }
}
