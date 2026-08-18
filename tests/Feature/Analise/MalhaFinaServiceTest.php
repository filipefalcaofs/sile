<?php

namespace Tests\Feature\Analise;

use App\Enums\ViabilityRequestStatus;
use App\Models\Activity;
use App\Models\FineMeshReferral;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Analise\MalhaFinaException;
use App\Services\Analise\MalhaFinaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Malha fina (HU-136): encaminhamento humano provocado de QUALQUER processo para
 * revisão. É ORTOGONAL ao status — liga a flag in_fine_mesh e grava um
 * fine_mesh_referrals (motivo obrigatório, RN-002), SEM transicionar o status
 * (não chama a StateMachine). Funciona em qualquer status, inclusive deferida
 * (RN-001 — corrige o bug legado que recusava deferidos). É repetível e em lote
 * (RN-004), auditada SÍNCRONA por processo. Resolver dá baixa sem mexer no
 * status; ao baixar o último encaminhamento aberto, a flag in_fine_mesh cai.
 */
class MalhaFinaServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): MalhaFinaService
    {
        return app(MalhaFinaService::class);
    }

    private function processo(ViabilityRequestStatus $status = ViabilityRequestStatus::EmAnalise): ViabilityRequest
    {
        $request = ViabilityRequest::factory()->create();

        $request->forceFill(['status' => $status])->save();

        return $request;
    }

    public function test_encaminhar_liga_a_flag_e_cria_referral_sem_mexer_no_status(): void
    {
        // RN-002/RN-003: cria o fine_mesh_referrals (motivo + ator) e liga a flag
        // in_fine_mesh, mas NÃO altera o status (flag, não estado).
        $ator = User::factory()->create();
        $request = $this->processo(ViabilityRequestStatus::EmAnalise);

        $referral = $this->service()->encaminhar($request, $ator, 'Revisão de área construída divergente');

        $this->assertInstanceOf(FineMeshReferral::class, $referral);
        $this->assertSame('Revisão de área construída divergente', $referral->reason);
        $this->assertSame($ator->id, $referral->referred_by_user_id);
        $this->assertNull($referral->resolved_at);

        $fresh = $request->fresh();
        $this->assertTrue($fresh->in_fine_mesh);
        $this->assertSame(ViabilityRequestStatus::EmAnalise, $fresh->status);

        // RN-002: auditoria SÍNCRONA por processo, com o motivo e o status atual.
        $activity = Activity::query()
            ->where('log_name', 'analise')
            ->where('event', 'malha-fina-encaminhar')
            ->where('subject_id', $request->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame('Revisão de área construída divergente', $activity->properties['motivo']);
        $this->assertSame('em_analise', $activity->properties['status']);
        $this->assertSame($ator->id, $activity->properties['ator_id']);
    }

    public function test_encaminhar_processo_deferido_funciona_e_nao_muda_o_status(): void
    {
        // RN-001: malha fina atinge QUALQUER status, inclusive deferida (corrige o
        // bug legado). É flag + tabela, não transição — o desfecho não muda.
        $ator = User::factory()->create();
        $request = $this->processo(ViabilityRequestStatus::Deferida);

        $referral = $this->service()->encaminhar($request, $ator, 'Auditoria interna de processo deferido');

        $this->assertInstanceOf(FineMeshReferral::class, $referral);

        $fresh = $request->fresh();
        $this->assertTrue($fresh->in_fine_mesh);
        $this->assertSame(ViabilityRequestStatus::Deferida, $fresh->status);

        $activity = Activity::query()
            ->where('log_name', 'analise')
            ->where('event', 'malha-fina-encaminhar')
            ->where('subject_id', $request->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame('deferida', $activity->properties['status']);
    }

    public function test_motivo_vazio_lanca_erro_de_dominio_e_nao_cria_referral(): void
    {
        // RN-002: motivo é obrigatório — só espaços em branco também é vazio.
        $ator = User::factory()->create();
        $request = $this->processo();

        try {
            $this->service()->encaminhar($request, $ator, '   ');
            $this->fail('Esperava MalhaFinaException para motivo vazio.');
        } catch (MalhaFinaException) {
            // esperado
        }

        $this->assertSame(0, FineMeshReferral::query()->count());
        $this->assertFalse($request->fresh()->in_fine_mesh);
        $this->assertDatabaseMissing('activity_log', [
            'log_name' => 'analise',
            'event' => 'malha-fina-encaminhar',
            'subject_id' => $request->id,
        ]);
    }

    public function test_mesmo_processo_pode_ser_encaminhado_mais_de_uma_vez(): void
    {
        // RN-003: repetível — cada encaminhamento é uma linha em fine_mesh_referrals.
        $ator = User::factory()->create();
        $request = $this->processo();

        $this->service()->encaminhar($request, $ator, 'Primeira revisão');
        $this->service()->encaminhar($request, $ator, 'Segunda revisão');

        $this->assertSame(2, $request->fineMeshReferrals()->count());
        $this->assertTrue($request->fresh()->in_fine_mesh);
    }

    public function test_encaminhar_em_lote_cria_referral_e_audita_por_processo(): void
    {
        // RN-004: lote aplica o mesmo motivo a vários processos, auditando cada um.
        $ator = User::factory()->create();
        $requests = collect(range(1, 3))->map(fn () => $this->processo());

        $resumo = $this->service()->encaminharLote($requests, $ator, 'Encaminhamento em lote para auditoria');

        $this->assertSame(3, $resumo['ok']);
        $this->assertSame([], $resumo['falhas']);

        foreach ($requests as $request) {
            $this->assertTrue($request->fresh()->in_fine_mesh);
        }

        $this->assertSame(3, FineMeshReferral::query()->count());
        $this->assertSame(
            3,
            Activity::query()->where('log_name', 'analise')->where('event', 'malha-fina-encaminhar')->count(),
        );
    }

    public function test_resolver_marca_resolved_at_sem_mexer_no_status(): void
    {
        // Resolver dá baixa (resolved_at) sem transicionar o status; como era o
        // único encaminhamento aberto, a flag in_fine_mesh cai.
        $ator = User::factory()->create();
        $request = $this->processo(ViabilityRequestStatus::EmAnalise);
        $referral = $this->service()->encaminhar($request, $ator, 'Revisão pontual');

        $this->service()->resolver($referral, $ator);

        $this->assertNotNull($referral->fresh()->resolved_at);
        $this->assertSame(ViabilityRequestStatus::EmAnalise, $request->fresh()->status);
        $this->assertFalse($request->fresh()->in_fine_mesh);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'analise',
            'event' => 'malha-fina-resolver',
            'subject_id' => $request->id,
        ]);
    }

    public function test_flag_permanece_enquanto_houver_encaminhamento_aberto(): void
    {
        // Invariante: in_fine_mesh = existe encaminhamento aberto. Resolver um de
        // dois mantém a flag; resolver o último a baixa.
        $ator = User::factory()->create();
        $request = $this->processo();

        $primeiro = $this->service()->encaminhar($request, $ator, 'Revisão A');
        $segundo = $this->service()->encaminhar($request, $ator, 'Revisão B');

        $this->service()->resolver($primeiro, $ator);
        $this->assertTrue($request->fresh()->in_fine_mesh);

        $this->service()->resolver($segundo, $ator);
        $this->assertFalse($request->fresh()->in_fine_mesh);
    }
}
