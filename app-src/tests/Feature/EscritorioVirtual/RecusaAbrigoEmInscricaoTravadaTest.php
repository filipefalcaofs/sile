<?php

namespace Tests\Feature\EscritorioVirtual;

use App\Models\Cnae;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Models\VirtualOfficeInscriptionLock;
use Database\Seeders\EscritorioVirtualCnaeSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Bloqueio da recusa de abrigo em inscricao travada (Constituicao §10.1,
 * RN-EV-01): numa inscricao com sede ativa, quem responde "Nao" a pergunta
 * geral ("deseja ser abrigado?") e indeferido no passo de atividades, com
 * orientacao para se abrigar da sede vinculada. Essa e uma regra distinta do
 * bloqueio de CNAE fora da Lista EV (AbrigadoCnaeBlockTest) e entra ANTES
 * dela — quem recusou nao deve receber tambem a lista de CNAEs reprovados.
 */
class RecusaAbrigoEmInscricaoTravadaTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        // Lista EV vigente (8211-3/00, 6204-0/00, etc. — snapshot SEDUR).
        $this->seed(EscritorioVirtualCnaeSeeder::class);
    }

    /**
     * Cidadão habilitado a navegar no portal (papel + termo LGPD aceito).
     */
    private function portalUser(): User
    {
        return User::factory()->cidadao()->withAcceptedLgpdTerm()->create();
    }

    /**
     * Rascunho de solicitação cujo requerente (beneficiário) é o usuário, com a
     * inscrição imobiliária informada (passo do imóvel, anterior às atividades).
     */
    private function draftFor(User $user, ?string $propertyRegistration, ?bool $wantsVirtualOfficeTenant = null): ViabilityRequest
    {
        return ViabilityRequest::factory()->draft()->create([
            'requester_user_id' => $user->id,
            'created_by_user_id' => $user->id,
            'property_registration' => $propertyRegistration,
            'wants_virtual_office_tenant' => $wantsVirtualOfficeTenant,
        ]);
    }

    /**
     * Trava a inscrição por uma sede de escritório virtual ATIVA (RN-EV-03).
     */
    private function sedeAtivaEm(string $propertyRegistration): void
    {
        VirtualOfficeInscriptionLock::create([
            'property_registration' => $propertyRegistration,
            'sede_viability_request_id' => ViabilityRequest::factory()->protocoled()->create()->id,
            'active' => true,
            'locked_at' => Carbon::now(),
        ]);
    }

    public function test_recusar_abrigo_em_inscricao_com_sede_ativa_bloqueia(): void
    {
        $user = $this->portalUser();
        $solicitacao = $this->draftFor($user, 'X', wantsVirtualOfficeTenant: false);
        $this->sedeAtivaEm('X');
        $minimercado = Cnae::factory()->create(['code' => '4712-1/00']);

        $this->actingAs($user)
            ->from(route('portal.solicitacoes.index'))
            ->put(route('portal.solicitacoes.atividades', $solicitacao), [
                'principal_cnae_id' => $minimercado->id,
            ])
            ->assertSessionHasErrors('principal_cnae_id');

        $errors = session('errors')->getBag('default');
        $this->assertSame(
            'Inscrição imobiliária vinculada a uma sede de escritório virtual. Para exercer atividades nesse local, deverá ser abrigado da sede vinculada.',
            $errors->first('principal_cnae_id'),
        );

        // Bloqueado → nenhum CNAE sincronizado.
        $this->assertSame(0, $solicitacao->cnaes()->count());
    }

    public function test_recusar_abrigo_em_inscricao_sem_sede_nao_bloqueia(): void
    {
        $user = $this->portalUser();
        $solicitacao = $this->draftFor($user, 'X', wantsVirtualOfficeTenant: false);
        $minimercado = Cnae::factory()->create(['code' => '4712-1/00']);

        $this->actingAs($user)
            ->from(route('portal.solicitacoes.index'))
            ->put(route('portal.solicitacoes.atividades', $solicitacao), [
                'principal_cnae_id' => $minimercado->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $solicitacao->cnaes()->count());
    }

    public function test_aceitar_abrigo_em_inscricao_com_sede_ativa_nao_bloqueia(): void
    {
        $user = $this->portalUser();
        $solicitacao = $this->draftFor($user, 'X', wantsVirtualOfficeTenant: true);
        $this->sedeAtivaEm('X');
        // Consultoria em TI (6204-0/00) ESTÁ na Lista EV.
        $consultoria = Cnae::factory()->create(['code' => '6204-0/00']);

        $this->actingAs($user)
            ->from(route('portal.solicitacoes.index'))
            ->put(route('portal.solicitacoes.atividades', $solicitacao), [
                'principal_cnae_id' => $consultoria->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $solicitacao->cnaes()->count());
    }
}
