<?php

namespace Tests\Feature\EscritorioVirtual;

use App\Models\Cnae;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Models\VirtualOfficeInscriptionLock;
use Database\Seeders\EscritorioVirtualCnaeSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Bloqueio de CNAE do ABRIGADO fora da Lista EV no cadastro (RN-EV-05/CA-04):
 * quando a inscrição da solicitação tem uma SEDE ativa
 * (VirtualOfficeInscriptionLock), TODOS os CNAEs submetidos no passo de
 * atividades precisam estar na Lista EV vigente; senão, o passo é bloqueado
 * com mensagem parametrizada. Inscrição sem sede ativa, ou property_registration
 * vazio → não é abrigado, sem bloqueio.
 */
class AbrigadoCnaeBlockTest extends TestCase
{
    use RefreshDatabase;

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
    private function draftFor(User $user, ?string $propertyRegistration): ViabilityRequest
    {
        return ViabilityRequest::factory()->draft()->create([
            'requester_user_id' => $user->id,
            'created_by_user_id' => $user->id,
            'property_registration' => $propertyRegistration,
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

    public function test_abrigado_bloqueia_cnae_fora_da_lista_ev(): void
    {
        $user = $this->portalUser();
        $solicitacao = $this->draftFor($user, 'X');
        $this->sedeAtivaEm('X');
        // Minimercado (4712-1/00) NÃO está na Lista EV.
        $minimercado = Cnae::factory()->create(['code' => '4712-1/00']);

        $this->actingAs($user)
            ->from(route('portal.solicitacoes.index'))
            ->put(route('portal.solicitacoes.atividades', $solicitacao), [
                'principal_cnae_id' => $minimercado->id,
            ])
            ->assertSessionHasErrors('principal_cnae_id');

        // Bloqueado → nenhum CNAE sincronizado.
        $this->assertSame(0, $solicitacao->cnaes()->count());
    }

    public function test_abrigado_aceita_cnae_da_lista_ev(): void
    {
        $user = $this->portalUser();
        $solicitacao = $this->draftFor($user, 'X');
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

    public function test_inscricao_sem_sede_ativa_nao_e_abrigado(): void
    {
        $user = $this->portalUser();
        // Inscrição informada, mas SEM sede ativa → não é abrigado.
        $solicitacao = $this->draftFor($user, 'X');
        $minimercado = Cnae::factory()->create(['code' => '4712-1/00']);

        $this->actingAs($user)
            ->from(route('portal.solicitacoes.index'))
            ->put(route('portal.solicitacoes.atividades', $solicitacao), [
                'principal_cnae_id' => $minimercado->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $solicitacao->cnaes()->count());
    }

    public function test_property_registration_nulo_nao_e_abrigado(): void
    {
        $user = $this->portalUser();
        // Sem inscrição informada → não há como ser abrigado.
        $solicitacao = $this->draftFor($user, null);
        $minimercado = Cnae::factory()->create(['code' => '4712-1/00']);

        $this->actingAs($user)
            ->from(route('portal.solicitacoes.index'))
            ->put(route('portal.solicitacoes.atividades', $solicitacao), [
                'principal_cnae_id' => $minimercado->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $solicitacao->cnaes()->count());
    }
}
