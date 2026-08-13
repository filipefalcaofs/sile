<?php

namespace Tests\Feature\Companies;

use App\Models\Activity;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EndCompanyLinkTest extends TestCase
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
        $company = Company::factory()->create();

        CompanyUser::factory()->responsavel()->create(array_merge([
            'company_id' => $company->id,
            'user_id' => $user->id,
        ], $linkAttributes));

        return $company;
    }

    /**
     * Acrescenta um segundo responsável ativo a uma empresa existente.
     */
    private function addResponsavel(Company $company, ?User $user = null): User
    {
        $user ??= $this->portalUser();

        CompanyUser::factory()->responsavel()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
        ]);

        return $user;
    }

    public function test_usuario_encerra_o_proprio_vinculo_com_motivo(): void
    {
        $user = $this->portalUser();
        $company = $this->companyLinkedTo($user);
        $this->addResponsavel($company); // garante outro responsável ativo

        $this->actingAs($user)
            ->delete("/portal/empresas/{$company->id}/vinculo", [
                'ended_reason' => 'Venda da participação',
            ])
            ->assertRedirect(route('portal.empresas.index'))
            ->assertSessionHas('status');

        $link = CompanyUser::query()
            ->where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->first();

        $this->assertNotNull($link->ended_at);
        $this->assertSame('Venda da participação', $link->ended_reason);

        // NUNCA delete físico: ambos os vínculos permanecem na tabela.
        $this->assertDatabaseCount('company_user', 2);
    }

    public function test_motivo_e_opcional(): void
    {
        $user = $this->portalUser();
        $company = $this->companyLinkedTo($user);
        $this->addResponsavel($company);

        $this->actingAs($user)
            ->delete("/portal/empresas/{$company->id}/vinculo")
            ->assertRedirect(route('portal.empresas.index'));

        $link = CompanyUser::query()
            ->where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->first();

        $this->assertNotNull($link->ended_at);
        $this->assertNull($link->ended_reason);
    }

    public function test_encerramento_e_auditado(): void
    {
        $user = $this->portalUser();
        $company = $this->companyLinkedTo($user);
        $this->addResponsavel($company);

        $this->actingAs($user)
            ->delete("/portal/empresas/{$company->id}/vinculo", [
                'ended_reason' => 'Motivo auditável',
            ]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'empresas',
            'event' => 'encerramento-vinculo',
            'result' => 'sucesso',
        ]);

        $activity = Activity::where('event', 'encerramento-vinculo')->first();
        $this->assertNotNull($activity);
        $this->assertSame($company->id, $activity->properties['empresa_id'] ?? null);
        $this->assertSame('Motivo auditável', $activity->properties['motivo'] ?? null);
    }

    public function test_ultimo_responsavel_ativo_e_bloqueado(): void
    {
        $user = $this->portalUser();
        $company = $this->companyLinkedTo($user); // único responsável ativo

        $this->actingAs($user)
            ->delete("/portal/empresas/{$company->id}/vinculo")
            ->assertSessionHasErrors('vinculo');

        $this->assertStringContainsString(
            'A empresa não pode ficar sem responsável ativo.',
            session('errors')->first('vinculo'),
        );

        $link = CompanyUser::query()
            ->where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->first();

        $this->assertNull($link->ended_at);
    }

    public function test_procurador_unico_pode_encerrar_se_ha_responsavel_ativo(): void
    {
        $responsavel = $this->portalUser();
        $procurador = $this->portalUser();

        $company = $this->companyLinkedTo($responsavel); // responsável ativo

        CompanyUser::factory()->procurador()->create([
            'company_id' => $company->id,
            'user_id' => $procurador->id,
        ]);

        // A proteção conta APENAS responsáveis: o procurador encerra normalmente.
        $this->actingAs($procurador)
            ->delete("/portal/empresas/{$company->id}/vinculo")
            ->assertRedirect(route('portal.empresas.index'));

        $link = CompanyUser::query()
            ->where('company_id', $company->id)
            ->where('user_id', $procurador->id)
            ->first();

        $this->assertNotNull($link->ended_at);
    }

    public function test_usuario_sem_vinculo_recebe_403(): void
    {
        $owner = $this->portalUser();
        $intruder = $this->portalUser();
        $company = $this->companyLinkedTo($owner);

        $this->actingAs($intruder)
            ->delete("/portal/empresas/{$company->id}/vinculo")
            ->assertForbidden();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'seguranca',
            'event' => 'acesso-negado',
            'result' => 'bloqueado',
        ]);
    }

    public function test_vinculo_ja_encerrado_nao_encerra_de_novo(): void
    {
        $user = $this->portalUser();
        $company = $this->companyLinkedTo($user, [
            'ended_at' => now(),
            'ended_reason' => 'Encerrado em teste',
        ]);

        $this->actingAs($user)
            ->delete("/portal/empresas/{$company->id}/vinculo")
            ->assertForbidden();
    }

    public function test_apos_encerrar_empresa_segue_na_listagem_com_situacao_encerrado(): void
    {
        $user = $this->portalUser();
        $company = $this->companyLinkedTo($user);
        $this->addResponsavel($company);

        $this->actingAs($user)
            ->delete("/portal/empresas/{$company->id}/vinculo");

        // Histórico preservado: a empresa continua na listagem com vínculo inativo.
        $this->actingAs($user)
            ->get('/portal/empresas')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('companies.data', 1)
                ->where('companies.data.0.id', $company->id)
                ->where('companies.data.0.link.active', false)
            );
    }
}
