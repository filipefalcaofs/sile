<?php

namespace Tests\Feature\EscritorioVirtual;

use App\Models\Cnae;
use App\Models\Company;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Models\VirtualOfficeInscriptionLock;
use Database\Seeders\EscritorioVirtualCnaeSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * RN-C-01 (sede duplicada) dispara quando a intenção é Sede e a inscrição já
 * tem vínculo de sede ativo — mas para a própria sede titular esse vínculo é
 * ela mesma: sem exceção, ela nunca conseguiria alterar as próprias
 * atividades, nem excluir o CNAE gatilho que a caracteriza (Task 3). A
 * titularidade é decidida pela mesma comparação usada em
 * `FluxoExpressoService::titularDaSede()` (empresa da solicitação × empresa
 * da solicitação que detém o vínculo).
 */
class SedeTitularNaoEBloqueadaTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(EscritorioVirtualCnaeSeeder::class);
    }

    private function portalUser(): User
    {
        return User::factory()->cidadao()->withAcceptedLgpdTerm()->create();
    }

    /**
     * Trava a inscrição por uma sede de escritório virtual ATIVA (RN-EV-03),
     * cuja solicitação titular pertence a $company.
     */
    private function sedeAtivaEm(string $propertyRegistration, Company $company): void
    {
        VirtualOfficeInscriptionLock::create([
            'property_registration' => $propertyRegistration,
            'sede_viability_request_id' => ViabilityRequest::factory()->protocoled()->create([
                'company_id' => $company->id,
            ])->id,
            'active' => true,
            'locked_at' => Carbon::now(),
        ]);
    }

    /**
     * A sede titular do vínculo — sua solicitação é da MESMA empresa que
     * detém o lock ativo da inscrição — precisa poder submeter o passo de
     * atividades com intenção Sede sem cair na regra de sede duplicada.
     */
    public function test_sede_titular_pode_alterar_as_proprias_atividades(): void
    {
        $user = $this->portalUser();
        $company = Company::factory()->create();
        $solicitacao = ViabilityRequest::factory()->draft()->create([
            'requester_user_id' => $user->id,
            'created_by_user_id' => $user->id,
            'company_id' => $company->id,
            'property_registration' => 'X',
            'wants_virtual_office_hq' => true,
        ]);
        $this->sedeAtivaEm('X', $company);
        $consultoria = Cnae::factory()->create(['code' => '6920-6/01']);

        $this->actingAs($user)
            ->from(route('portal.solicitacoes.index'))
            ->put(route('portal.solicitacoes.atividades', $solicitacao), [
                'principal_cnae_id' => $consultoria->id,
            ])
            ->assertSessionHasNoErrors();
    }

    /**
     * Guarda: outra empresa (não titular do vínculo) na MESMA inscrição, com
     * intenção Sede, continua bloqueada pela sede duplicada (RN-C-01). Sem
     * este caso, a correção acima poderia liberar qualquer empresa a
     * constituir sede numa inscrição já travada por outra.
     */
    public function test_empresa_de_terceiro_continua_bloqueada_por_sede_duplicada(): void
    {
        $user = $this->portalUser();
        $terceiro = Company::factory()->create();
        $titular = Company::factory()->create();
        $solicitacao = ViabilityRequest::factory()->draft()->create([
            'requester_user_id' => $user->id,
            'created_by_user_id' => $user->id,
            'company_id' => $terceiro->id,
            'property_registration' => 'X',
            'wants_virtual_office_hq' => true,
        ]);
        $this->sedeAtivaEm('X', $titular);
        $consultoria = Cnae::factory()->create(['code' => '6920-6/01']);

        $response = $this->actingAs($user)
            ->from(route('portal.solicitacoes.index'))
            ->put(route('portal.solicitacoes.atividades', $solicitacao), [
                'principal_cnae_id' => $consultoria->id,
            ]);

        $response->assertSessionHasErrors('principal_cnae_id');

        $errors = $this->app['session']->get('errors')->getBag('default')->get('principal_cnae_id');
        $this->assertSame(
            config('sile.analise.escritorio_virtual.mensagem_sede_duplicada'),
            $errors[0],
        );
    }
}
