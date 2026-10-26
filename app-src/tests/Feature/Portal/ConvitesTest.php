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
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Convites para responder: só os do requerente (ou do solicitante declarado
 * do REGIN), abertos e dentro do prazo, na lista, no painel e no menu.
 */
class ConvitesTest extends TestCase
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
    private function processo(?int $requesterId, array $socios = []): ViabilityRequest
    {
        $processo = ViabilityRequest::factory()->withPrimaryCnae()->create([
            'origin' => ViabilityRequestOrigin::Regin,
            'requester_cidadao_id' => $requesterId,
        ]);
        $processo->forceFill([
            'status' => ViabilityRequestStatus::EmPendencia,
            'protocol_number' => 'VIA-2026-'.str_pad((string) $processo->id, 6, '0', STR_PAD_LEFT),
            'protocoled_at' => now(),
        ])->save();
        app(VinculosCpfProcesso::class)->sincronizar($processo, $socios);

        return $processo->refresh();
    }

    private function convite(ViabilityRequest $processo, ?\DateTimeInterface $prazo): AnalysisPendency
    {
        return AnalysisPendency::factory()->create([
            'viability_request_id' => $processo->id,
            'status' => AnalysisPendencyStatus::Aberta,
            'due_at' => $prazo,
        ]);
    }

    public function test_lista_so_convites_respondiveis_no_prazo(): void
    {
        $pessoa = $this->cidadao();
        $doRequerente = $this->processo($pessoa->id);
        $noPrazo = $this->convite($doRequerente, now()->addDays(3));
        $this->convite($this->processo($pessoa->id), now()->subDay());
        $this->convite($this->processo(null, [$pessoa->cpf]), now()->addDays(3));

        $this->actingAs($pessoa)->get(route('portal.convites.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('portal/convites/index')
                ->has('convites', 1)
                ->where('convites.0.id', $noPrazo->id)
                ->where('convites.0.solicitacao_id', $doRequerente->id)
                ->where('convites.0.protocol_number', $doRequerente->protocol_number));
    }

    public function test_badge_compartilhado_conta_os_convites(): void
    {
        $pessoa = $this->cidadao();
        $this->convite($this->processo($pessoa->id), now()->addDays(3));
        $this->convite($this->processo(null, [$pessoa->cpf]), now()->addDays(3));

        $this->actingAs($pessoa)->get(route('portal.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('convitesPendentes', 1)
                ->has('atencao.pendencias', 1));
    }

    public function test_painel_sem_convite_tem_atencao_vazia(): void
    {
        $this->actingAs($this->cidadao())->get(route('portal.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('convitesPendentes', 0)
                ->has('atencao.pendencias', 0)
                ->missing('atencao.rascunhos'));
    }
}
