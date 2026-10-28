<?php

namespace Tests\Feature\Portal;

use App\Enums\AnalysisPendencyStatus;
use App\Enums\ViabilityRequestOrigin;
use App\Enums\ViabilityRequestStatus;
use App\Models\AnalysisPendency;
use App\Models\Cidadao;
use App\Models\ViabilityRequest;
use App\Services\Solicitacao\VinculosCpfProcesso;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Convite: responde o requerente ou, no processo do REGIN, o CPF que a JUCEB
 * mandou como solicitante. O sócio do REGIN só consulta.
 */
class ConviteSoDoRequerenteTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function cidadao(): Cidadao
    {
        return Cidadao::factory()->withAcceptedLgpdTerm()->create();
    }

    /**
     * @param  list<string>  $socios
     */
    private function processoEmConvite(?int $requesterId = null, ?string $solicitanteCpf = null, array $socios = []): ViabilityRequest
    {
        $processo = ViabilityRequest::factory()->withPrimaryCnae()->create([
            'origin' => ViabilityRequestOrigin::Regin,
            'requester_cidadao_id' => $requesterId,
            'solicitante_cpf' => $solicitanteCpf,
        ]);
        $processo->forceFill([
            'status' => ViabilityRequestStatus::EmPendencia,
            'protocol_number' => 'VIA-'.now()->year.'-'.str_pad((string) $processo->id, 6, '0', STR_PAD_LEFT),
            'protocoled_at' => now(),
        ])->save();

        app(VinculosCpfProcesso::class)->sincronizar($processo, $socios);

        return $processo->refresh();
    }

    private function convite(ViabilityRequest $processo): AnalysisPendency
    {
        return AnalysisPendency::factory()->create([
            'viability_request_id' => $processo->id,
            'status' => AnalysisPendencyStatus::Aberta,
        ]);
    }

    private function assertResponde(Cidadao $user, ViabilityRequest $processo): void
    {
        $convite = $this->convite($processo);

        $this->actingAs($user)->get(route('portal.solicitacoes.pendencias', $processo))->assertOk();
        $this->actingAs($user)
            ->post(route('portal.solicitacoes.pendencias.responder', [$processo, $convite]), ['response' => 'Segue o documento pedido.'])
            ->assertRedirect(route('portal.solicitacoes.show', $processo));

        $this->assertSame(AnalysisPendencyStatus::Respondida, $convite->refresh()->status);
    }

    public function test_requerente_ve_e_responde_convite(): void
    {
        $requerente = $this->cidadao();

        $this->assertResponde($requerente, $this->processoEmConvite(requesterId: $requerente->id));
    }

    public function test_solicitante_declarado_do_regin_responde_convite(): void
    {
        $solicitante = $this->cidadao();

        $this->assertResponde($solicitante, $this->processoEmConvite(solicitanteCpf: $solicitante->cpf));
    }

    public function test_socio_do_regin_nao_ve_detalhe_nem_responde(): void
    {
        $socio = $this->cidadao();
        $processo = $this->processoEmConvite(socios: [$socio->cpf]);
        $convite = $this->convite($processo);

        $this->actingAs($socio)->get(route('portal.solicitacoes.show', $processo))->assertForbidden();
        $this->actingAs($socio)->get(route('portal.solicitacoes.pendencias', $processo))->assertForbidden();
        $this->actingAs($socio)
            ->post(route('portal.solicitacoes.pendencias.responder', [$processo, $convite]), ['response' => 'Tentativa do sócio.'])
            ->assertForbidden();

        $this->assertSame(AnalysisPendencyStatus::Aberta, $convite->refresh()->status);
    }

    public function test_escopo_respondivel_por_exclui_socio(): void
    {
        $pessoa = $this->cidadao();
        $comoRequerente = $this->processoEmConvite(requesterId: $pessoa->id);
        $comoSolicitante = $this->processoEmConvite(solicitanteCpf: $pessoa->cpf);
        $this->processoEmConvite(socios: [$pessoa->cpf]);
        $this->processoEmConvite();

        $ids = ViabilityRequest::query()->respondivelPor($pessoa->id, $pessoa->cpf)->orderBy('id')->pluck('id')->all();

        $this->assertSame([$comoRequerente->id, $comoSolicitante->id], $ids);
    }

    public function test_escopo_sem_conta_nem_cpf_e_vazio(): void
    {
        $this->processoEmConvite();

        $this->assertSame(0, ViabilityRequest::query()->respondivelPor(null, null)->count());
    }
}
