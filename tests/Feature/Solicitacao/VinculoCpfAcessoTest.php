<?php

namespace Tests\Feature\Solicitacao;

use App\Enums\AnalysisPendencyStatus;
use App\Enums\ViabilityRequestOrigin;
use App\Enums\ViabilityRequestStatus;
use App\Models\AnalysisPendency;
use App\Models\Procuration;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Solicitacao\VinculosCpfProcesso;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class VinculoCpfAcessoTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function cidadao(array $atributos = []): User
    {
        return User::factory()->cidadao()->withAcceptedLgpdTerm()->create($atributos);
    }

    /**
     * Processo do REGIN sem requerente, com os sócios dados, em convite.
     *
     * @param  list<string>  $socios
     */
    private function processoRegin(array $socios, ?int $requesterId = null): ViabilityRequest
    {
        $processo = ViabilityRequest::factory()->withPrimaryCnae()->create([
            'origin' => ViabilityRequestOrigin::Regin,
            'requester_user_id' => $requesterId,
        ]);
        $processo->forceFill([
            'status' => ViabilityRequestStatus::EmPendencia,
            'protocol_number' => 'VIA-'.now()->year.'-'.str_pad((string) $processo->id, 6, '0', STR_PAD_LEFT),
            'protocoled_at' => now(),
        ])->save();

        app(VinculosCpfProcesso::class)->sincronizar($processo, $socios);

        return $processo->refresh();
    }

    public function test_socio_ve_o_processo_e_responde_convite(): void
    {
        $socio = $this->cidadao();
        $processo = $this->processoRegin([$socio->cpf]);
        $pendencia = AnalysisPendency::factory()->create(['viability_request_id' => $processo->id, 'status' => AnalysisPendencyStatus::Aberta]);

        $this->actingAs($socio)->get(route('portal.solicitacoes.show', $processo))->assertOk();
        $this->actingAs($socio)
            ->post(route('portal.solicitacoes.pendencias.responder', [$processo, $pendencia]), ['response' => 'Segue o documento pedido.'])
            ->assertRedirect(route('portal.solicitacoes.show', $processo));

        $this->assertSame(AnalysisPendencyStatus::Respondida, $pendencia->refresh()->status);
        $this->assertNull($processo->refresh()->requester_user_id);
    }

    public function test_socio_nao_edita_nem_cancela(): void
    {
        $socio = $this->cidadao();
        $processo = $this->processoRegin([$socio->cpf]);

        $this->assertFalse(Gate::forUser($socio)->allows('update', $processo));
        $this->assertFalse(Gate::forUser($socio)->allows('cancel', $processo));
        $this->assertFalse(Gate::forUser($socio)->allows('protocol', $processo));
    }

    public function test_cpf_sem_vinculo_nao_ve(): void
    {
        $estranho = $this->cidadao();
        $processo = $this->processoRegin(['52998224725']);

        $this->actingAs($estranho)->get(route('portal.solicitacoes.show', $processo))->assertForbidden();
    }

    public function test_conta_sem_cpf_nao_casa_vinculo(): void
    {
        $semCpf = $this->cidadao();
        $semCpf->forceFill(['cpf' => ''])->saveQuietly();
        $processo = $this->processoRegin([]);
        $processo->cpfsVinculados()->create(['cpf' => '', 'papel' => 'socio']);

        $this->assertFalse(Gate::forUser($semCpf->refresh())->allows('view', $processo));
    }

    public function test_lista_inclui_processo_do_socio_sem_acoes(): void
    {
        $socio = $this->cidadao();
        $processo = $this->processoRegin([$socio->cpf]);

        $this->actingAs($socio)->get(route('portal.solicitacoes.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('solicitacoes.data', 1)
                ->where('solicitacoes.data.0.id', $processo->id)
                ->where('solicitacoes.data.0.editable', false)
                ->where('solicitacoes.data.0.cancelable', false));
    }

    public function test_processo_do_socio_que_tambem_e_solicitante_aparece_uma_vez(): void
    {
        $socio = $this->cidadao();
        $this->processoRegin([$socio->cpf], $socio->id);

        $this->actingAs($socio)->get(route('portal.solicitacoes.index'))
            ->assertInertia(fn (Assert $page) => $page->has('solicitacoes.data', 1));
    }

    public function test_representado_usa_o_cpf_do_outorgante(): void
    {
        $outorgante = $this->cidadao();
        $procurador = $this->cidadao();
        $procuracao = Procuration::factory()->create(['grantor_user_id' => $outorgante->id, 'attorney_user_id' => $procurador->id]);
        $doOutorgante = $this->processoRegin([$outorgante->cpf]);
        $this->processoRegin([$procurador->cpf]);

        $this->actingAs($procurador)->withSession(['acting_procuration_id' => $procuracao->id])
            ->get(route('portal.solicitacoes.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('solicitacoes.data', 1)
                ->where('solicitacoes.data.0.id', $doOutorgante->id));
    }
}
