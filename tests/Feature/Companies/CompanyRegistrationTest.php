<?php

namespace Tests\Feature\Companies;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Procuration;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyRegistrationTest extends TestCase
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
     * Payload base de um cadastro de empresa válido.
     *
     * @return array<string, string>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'cnpj' => '00.000.000/0001-91',
            'legal_name' => 'Empresa Exemplo Ltda',
            'trade_name' => 'Exemplo',
            'legal_nature_code' => '2062',
            'legal_nature' => 'Sociedade Empresária Limitada',
            'size_code' => '01',
            'size' => 'MICRO EMPRESA',
            'street' => 'Rua Chile',
            'number' => '10',
            'neighborhood' => 'Centro',
            'city' => 'Salvador',
            'state' => 'BA',
            'zip_code' => '40020000',
            'email' => 'contato@exemplo.com',
            'phone' => '(71) 3333-0000',
        ], $overrides);
    }

    public function test_cadastro_cria_empresa_e_vinculo_responsavel_ativo(): void
    {
        $user = $this->portalUser();

        $this->actingAs($user)
            ->post('/portal/empresas', $this->payload())
            ->assertRedirect(route('portal.empresas.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('companies', [
            'cnpj' => '00000000000191',
            'source' => 'manual',
        ]);

        $this->assertDatabaseHas('company_user', [
            'user_id' => $user->id,
            'role' => 'responsavel',
            'ended_at' => null,
        ]);

        $link = CompanyUser::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($link);
        $this->assertNotNull($link->started_at);
    }

    public function test_cadastro_e_auditado(): void
    {
        $user = $this->portalUser();

        $this->actingAs($user)->post('/portal/empresas', $this->payload());

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => (new Company)->getMorphClass(),
            'event' => 'created',
            'result' => 'sucesso',
        ]);
    }

    public function test_cnpj_duplicado_e_bloqueado_com_mensagem_clara(): void
    {
        $user = $this->portalUser();

        Company::factory()->create(['cnpj' => '00000000000191']);

        $this->actingAs($user)
            ->post('/portal/empresas', $this->payload())
            ->assertSessionHasErrors('cnpj');

        $this->assertStringContainsString(
            'Já existe empresa cadastrada com este CNPJ.',
            session('errors')->first('cnpj'),
        );

        $this->assertDatabaseCount('companies', 1);
        $this->assertDatabaseCount('company_user', 0);
    }

    public function test_cnpj_invalido_e_rejeitado(): void
    {
        $user = $this->portalUser();

        $this->actingAs($user)
            ->post('/portal/empresas', $this->payload(['cnpj' => '11111111111111']))
            ->assertSessionHasErrors('cnpj');

        $this->assertDatabaseCount('companies', 0);
    }

    public function test_cnpj_alfanumerico_valido_e_aceito(): void
    {
        $user = $this->portalUser();

        $this->actingAs($user)
            ->post('/portal/empresas', $this->payload(['cnpj' => '12.ABC.345/01DE-35']))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('portal.empresas.index'));

        $this->assertDatabaseHas('companies', ['cnpj' => '12ABC34501DE35']);
    }

    public function test_telefone_e_cep_sao_normalizados_para_digitos(): void
    {
        $user = $this->portalUser();

        $this->actingAs($user)->post('/portal/empresas', $this->payload([
            'phone' => '(71) 3333-0000',
            'zip_code' => '40.020-000',
        ]));

        $this->assertDatabaseHas('companies', [
            'cnpj' => '00000000000191',
            'phone' => '7133330000',
            'zip_code' => '40020000',
        ]);
    }

    public function test_visitante_e_redirecionado(): void
    {
        $this->post('/portal/empresas', $this->payload())
            ->assertRedirect('/portal/login');

        $this->get('/portal/empresas/cadastrar')
            ->assertRedirect('/portal/login');

        $this->assertDatabaseCount('companies', 0);
    }

    public function test_em_representacao_cria_para_o_representado(): void
    {
        $grantor = $this->portalUser();
        $attorney = $this->portalUser();

        $proc = Procuration::factory()->create([
            'grantor_user_id' => $grantor->id,
            'attorney_user_id' => $attorney->id,
        ]);

        $this->actingAs($attorney)
            ->withSession(['acting_procuration_id' => $proc->id])
            ->post('/portal/empresas', $this->payload())
            ->assertRedirect(route('portal.empresas.index'));

        $company = Company::query()->where('cnpj', '00000000000191')->first();
        $this->assertNotNull($company);

        $this->assertDatabaseHas('company_user', [
            'company_id' => $company->id,
            'user_id' => $grantor->id,
            'role' => 'responsavel',
        ]);

        $this->assertDatabaseMissing('company_user', [
            'company_id' => $company->id,
            'user_id' => $attorney->id,
        ]);

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => $company->getMorphClass(),
            'event' => 'created',
            'acting_for_user_id' => $grantor->id,
        ]);
    }
}
