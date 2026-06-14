<?php

namespace Tests\Feature\Solicitacao;

use App\Models\Cnae;
use App\Models\Parameter;
use App\Models\User;
use App\Models\ViabilityRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Informar a atividade principal (HU-064) e os CNAEs complementares (HU-065)
 * do rascunho da solicitação: define exatamente um CNAE principal (is_primary)
 * e os complementares (até o limite parametrizável, default 99) a partir da
 * tabela oficial, só CNAEs ativos, garantindo unicidade (request, cnae) —
 * espelhando o pivot company_cnae da Fase 3. Mudar a lista invalida a simulação
 * anterior (HU-063 RN-005) e é auditado (RN-002); só o dono em rascunho edita.
 */
class InformarAtividadesTest extends TestCase
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
     * Rascunho de solicitação cujo requerente (beneficiário) é o usuário.
     */
    private function draftFor(User $user): ViabilityRequest
    {
        return ViabilityRequest::factory()->draft()->create([
            'requester_user_id' => $user->id,
            'created_by_user_id' => $user->id,
        ]);
    }

    public function test_define_principal_e_complementares(): void
    {
        $user = $this->portalUser();
        $solicitacao = $this->draftFor($user);
        $principal = Cnae::factory()->create();
        $complementares = Cnae::factory()->count(2)->create();

        $this->actingAs($user)
            ->from(route('portal.solicitacoes.index'))
            ->put(route('portal.solicitacoes.atividades', $solicitacao), [
                'principal_cnae_id' => $principal->id,
                'complementares' => $complementares->pluck('id')->all(),
            ])
            ->assertRedirect(route('portal.solicitacoes.index'))
            ->assertSessionHas('status');

        // Exatamente um principal (is_primary=true) e unicidade dos vínculos.
        $this->assertDatabaseHas('viability_request_cnaes', [
            'viability_request_id' => $solicitacao->id,
            'cnae_id' => $principal->id,
            'is_primary' => true,
        ]);

        foreach ($complementares as $complementar) {
            $this->assertDatabaseHas('viability_request_cnaes', [
                'viability_request_id' => $solicitacao->id,
                'cnae_id' => $complementar->id,
                'is_primary' => false,
            ]);
        }

        $this->assertSame(1, $solicitacao->primaryCnae()->count());
        $this->assertSame(3, $solicitacao->cnaes()->count());

        // Auditoria explícita do conjunto (RN-002).
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'solicitacoes',
            'event' => 'solicitacao-cnaes',
            'subject_type' => $solicitacao->getMorphClass(),
            'subject_id' => $solicitacao->id,
        ]);
    }

    public function test_respeita_limite_parametrizavel(): void
    {
        $user = $this->portalUser();
        $solicitacao = $this->draftFor($user);
        $principal = Cnae::factory()->create();
        $tresComplementares = Cnae::factory()->count(3)->create()->pluck('id')->all();

        // Limite administrável reduzido para 2 (HU-014/HU-065).
        $parameter = Parameter::factory()->create([
            'key' => 'solicitacao.cnaes_complementares.max',
            'group' => 'solicitacao',
            'type' => 'integer',
            'value' => '2',
        ]);

        // Com limite 2, três complementares excedem → erro comunicado (não silencioso).
        $this->actingAs($user)
            ->from(route('portal.solicitacoes.index'))
            ->put(route('portal.solicitacoes.atividades', $solicitacao), [
                'principal_cnae_id' => $principal->id,
                'complementares' => $tresComplementares,
            ])
            ->assertSessionHasErrors('complementares');

        $this->assertSame(0, $solicitacao->cnaes()->count());

        // Revertido ao default (99): os mesmos três passam a ser aceitos.
        $parameter->delete();

        $this->actingAs($user)
            ->from(route('portal.solicitacoes.index'))
            ->put(route('portal.solicitacoes.atividades', $solicitacao), [
                'principal_cnae_id' => $principal->id,
                'complementares' => $tresComplementares,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(4, $solicitacao->cnaes()->count());
    }

    public function test_so_aceita_cnaes_ativos(): void
    {
        $user = $this->portalUser();
        $solicitacao = $this->draftFor($user);
        $inactivePrincipal = Cnae::factory()->inactive()->create();
        $activePrincipal = Cnae::factory()->create();
        $inactiveComplementar = Cnae::factory()->inactive()->create();

        // Atividade principal inativa → erro de validação.
        $this->actingAs($user)
            ->from(route('portal.solicitacoes.index'))
            ->put(route('portal.solicitacoes.atividades', $solicitacao), [
                'principal_cnae_id' => $inactivePrincipal->id,
            ])
            ->assertSessionHasErrors('principal_cnae_id');

        // CNAE complementar inativo → erro de validação.
        $this->actingAs($user)
            ->from(route('portal.solicitacoes.index'))
            ->put(route('portal.solicitacoes.atividades', $solicitacao), [
                'principal_cnae_id' => $activePrincipal->id,
                'complementares' => [$inactiveComplementar->id],
            ])
            ->assertSessionHasErrors('complementares.0');

        $this->assertSame(0, $solicitacao->cnaes()->count());
    }

    public function test_principal_unico_e_sem_duplicados(): void
    {
        $user = $this->portalUser();
        $solicitacao = $this->draftFor($user);
        $principal = Cnae::factory()->create();
        $outro = Cnae::factory()->create();

        // A atividade principal não pode estar entre os complementares.
        $this->actingAs($user)
            ->from(route('portal.solicitacoes.index'))
            ->put(route('portal.solicitacoes.atividades', $solicitacao), [
                'principal_cnae_id' => $principal->id,
                'complementares' => [$principal->id, $outro->id],
            ])
            ->assertSessionHasErrors('complementares');

        // CNAE complementar repetido → erro.
        $this->actingAs($user)
            ->from(route('portal.solicitacoes.index'))
            ->put(route('portal.solicitacoes.atividades', $solicitacao), [
                'principal_cnae_id' => $principal->id,
                'complementares' => [$outro->id, $outro->id],
            ])
            ->assertSessionHasErrors('complementares.0');

        $this->assertSame(0, $solicitacao->cnaes()->count());
    }

    public function test_mudanca_zera_simulacao(): void
    {
        $user = $this->portalUser();
        $solicitacao = ViabilityRequest::factory()->draft()->create([
            'requester_user_id' => $user->id,
            'created_by_user_id' => $user->id,
            'simulation_resultado' => 'permitido',
            'simulated_at' => now(),
        ]);
        $principal = Cnae::factory()->create();

        $this->actingAs($user)
            ->from(route('portal.solicitacoes.index'))
            ->put(route('portal.solicitacoes.atividades', $solicitacao), [
                'principal_cnae_id' => $principal->id,
            ]);

        $solicitacao->refresh();

        $this->assertNull($solicitacao->simulated_at);
        $this->assertNull($solicitacao->simulation_resultado);
    }

    public function test_terceiro_nao_edita_atividades(): void
    {
        $owner = $this->portalUser();
        $stranger = $this->portalUser();
        $solicitacao = $this->draftFor($owner);
        $principal = Cnae::factory()->create();

        $this->actingAs($stranger)
            ->put(route('portal.solicitacoes.atividades', $solicitacao), [
                'principal_cnae_id' => $principal->id,
            ])
            ->assertForbidden();

        $this->assertSame(0, $solicitacao->cnaes()->count());
    }

    public function test_solicitacao_protocolada_bloqueada_pela_policy(): void
    {
        $owner = $this->portalUser();
        $solicitacao = ViabilityRequest::factory()->protocoled()->create([
            'requester_user_id' => $owner->id,
            'created_by_user_id' => $owner->id,
        ]);
        $principal = Cnae::factory()->create();

        $this->actingAs($owner)
            ->put(route('portal.solicitacoes.atividades', $solicitacao), [
                'principal_cnae_id' => $principal->id,
            ])
            ->assertForbidden();
    }
}
