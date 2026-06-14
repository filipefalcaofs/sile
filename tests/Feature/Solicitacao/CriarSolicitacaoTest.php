<?php

namespace Tests\Feature\Solicitacao;

use App\Enums\CompanyLinkRole;
use App\Models\Company;
use App\Models\Parameter;
use App\Models\Procuration;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Models\ViabilityServiceType;
use Database\Seeders\ParameterSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Criar o RASCUNHO da solicitação de viabilidade (HU-061): origem portal
 * direto + tipo de serviço (08-03) + empresa do requerente (Fase 3). O
 * requerente é o usuário EFETIVO (representado quando "em nome de") e o
 * created_by é o ator real. Detecção de reincidência por CNPJ ALERTA sem
 * bloquear (RN-007). Tudo auditado (RN-002) e atrás do toggle
 * features.solicitacao_viabilidade (degradação comunicada quando off).
 */
class CriarSolicitacaoTest extends TestCase
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
     * Empresa com vínculo ATIVO do usuário (Fase 3).
     */
    private function companyLinkedTo(User $user): Company
    {
        $company = Company::factory()->create();

        $company->links()->create([
            'user_id' => $user->id,
            'role' => CompanyLinkRole::Responsavel,
            'started_at' => now(),
        ]);

        return $company;
    }

    public function test_cidadao_cria_rascunho(): void
    {
        $user = $this->portalUser();
        $company = $this->companyLinkedTo($user);
        $serviceType = ViabilityServiceType::factory()->create();

        $this->actingAs($user)
            ->post('/portal/solicitacoes', [
                'service_type_id' => $serviceType->id,
                'company_id' => $company->id,
            ])
            ->assertRedirect(route('portal.solicitacoes.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('viability_requests', [
            'company_id' => $company->id,
            'service_type_id' => $serviceType->id,
            'requester_user_id' => $user->id,
            'created_by_user_id' => $user->id,
            'origin' => 'portal',
            'status' => 'rascunho',
        ]);
    }

    public function test_requer_tipo_de_servico_ativo(): void
    {
        $user = $this->portalUser();
        $company = $this->companyLinkedTo($user);
        $inactive = ViabilityServiceType::factory()->inactive()->create();

        $this->actingAs($user)
            ->from('/portal/solicitacoes')
            ->post('/portal/solicitacoes', [
                'service_type_id' => $inactive->id,
                'company_id' => $company->id,
            ])
            ->assertSessionHasErrors('service_type_id');

        // Tipo inexistente também é rejeitado.
        $this->actingAs($user)
            ->from('/portal/solicitacoes')
            ->post('/portal/solicitacoes', [
                'service_type_id' => 999999,
                'company_id' => $company->id,
            ])
            ->assertSessionHasErrors('service_type_id');

        $this->assertDatabaseCount('viability_requests', 0);
    }

    public function test_requer_empresa_vinculada_ao_efetivo(): void
    {
        $user = $this->portalUser();
        $serviceType = ViabilityServiceType::factory()->create();
        $strangerCompany = Company::factory()->create();

        $this->actingAs($user)
            ->from('/portal/solicitacoes')
            ->post('/portal/solicitacoes', [
                'service_type_id' => $serviceType->id,
                'company_id' => $strangerCompany->id,
            ])
            ->assertSessionHasErrors('company_id');

        $this->assertDatabaseCount('viability_requests', 0);
    }

    public function test_em_nome_de_cria_para_o_representado(): void
    {
        $grantor = $this->portalUser();
        $attorney = $this->portalUser();

        $proc = Procuration::factory()->create([
            'grantor_user_id' => $grantor->id,
            'attorney_user_id' => $attorney->id,
        ]);

        // A empresa é do REPRESENTADO; o procurador opera em nome dele.
        $company = $this->companyLinkedTo($grantor);
        $serviceType = ViabilityServiceType::factory()->create();

        $this->actingAs($attorney)
            ->withSession(['acting_procuration_id' => $proc->id])
            ->post('/portal/solicitacoes', [
                'service_type_id' => $serviceType->id,
                'company_id' => $company->id,
            ])
            ->assertRedirect(route('portal.solicitacoes.index'));

        $request = ViabilityRequest::query()->where('company_id', $company->id)->first();
        $this->assertNotNull($request);
        $this->assertSame($grantor->id, $request->requester_user_id);
        $this->assertSame($attorney->id, $request->created_by_user_id);

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => $request->getMorphClass(),
            'event' => 'created',
            'acting_for_user_id' => $grantor->id,
        ]);
    }

    public function test_alerta_de_duplicidade_nao_bloqueia(): void
    {
        $user = $this->portalUser();
        $company = $this->companyLinkedTo($user);
        $serviceType = ViabilityServiceType::factory()->create();

        // Processo anterior recente (protocolada) da MESMA empresa.
        $previous = ViabilityRequest::factory()->protocoled()->create([
            'company_id' => $company->id,
            'requester_user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->post('/portal/solicitacoes', [
                'service_type_id' => $serviceType->id,
                'company_id' => $company->id,
            ])
            ->assertRedirect(route('portal.solicitacoes.index'))
            ->assertSessionHas('duplicateAlert');

        // Não bloqueou: a nova solicitação foi criada mesmo assim.
        $this->assertSame(2, ViabilityRequest::query()->where('company_id', $company->id)->count());

        // O alerta aponta o processo anterior (link real ao protocolo).
        $alert = session('duplicateAlert');
        $this->assertSame($previous->id, $alert['request_id']);
        $this->assertSame($previous->protocol_number, $alert['protocol_number']);
    }

    public function test_auditoria_da_criacao(): void
    {
        $user = $this->portalUser();
        $company = $this->companyLinkedTo($user);
        $serviceType = ViabilityServiceType::factory()->create();

        $this->actingAs($user)->post('/portal/solicitacoes', [
            'service_type_id' => $serviceType->id,
            'company_id' => $company->id,
        ]);

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => (new ViabilityRequest)->getMorphClass(),
            'event' => 'created',
            'result' => 'sucesso',
        ]);
    }

    public function test_toggle_desligado_degrada_comunicado(): void
    {
        $this->seed(ParameterSeeder::class);
        Parameter::query()
            ->where('key', 'features.solicitacao_viabilidade')
            ->first()
            ->update(['value' => '0']);

        $user = $this->portalUser();
        $company = $this->companyLinkedTo($user);
        $serviceType = ViabilityServiceType::factory()->create();

        $this->actingAs($user)
            ->from('/portal/solicitacoes')
            ->post('/portal/solicitacoes', [
                'service_type_id' => $serviceType->id,
                'company_id' => $company->id,
            ])
            ->assertSessionHas('status');

        // Degradação comunicada: nada criado, nunca falha silenciosa.
        $this->assertDatabaseCount('viability_requests', 0);
    }

    public function test_minhas_solicitacoes_lista_somente_do_dono(): void
    {
        $user = $this->portalUser();
        $other = $this->portalUser();

        $companyA = $this->companyLinkedTo($user);
        $companyB = $this->companyLinkedTo($other);

        $mine = ViabilityRequest::factory()->protocoled()->create([
            'company_id' => $companyA->id,
            'requester_user_id' => $user->id,
            'protocol_number' => 'VIA-2026-000010',
        ]);
        ViabilityRequest::factory()->protocoled()->create([
            'company_id' => $companyB->id,
            'requester_user_id' => $other->id,
            'protocol_number' => 'VIA-2026-000011',
        ]);

        $this->actingAs($user)
            ->get('/portal/solicitacoes')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('solicitacoes.data', 1)
                ->where('solicitacoes.data.0.id', $mine->id)
                ->has('filters')
                ->where('solicitacaoEnabled', true));
    }
}
