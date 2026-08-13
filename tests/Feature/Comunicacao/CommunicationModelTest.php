<?php

namespace Tests\Feature\Comunicacao;

use App\Enums\CommunicationChannel;
use App\Enums\CommunicationStatus;
use App\Enums\CommunicationType;
use App\Models\Communication;
use App\Models\User;
use App\Models\ViabilityRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Ledger imutável `communications` (HU-096): fonte de verdade do histórico de
 * comunicações POR PROCESSO. Prova os casts tipados (3 enums + meta array +
 * carimbos), as relações e os marcadores honestos que espelham o EmailLog —
 * cada um muda status + carimbo, NUNCA inventando "enviado". Inclui a guarda
 * anti-fachada: um markAsSent tardio não pode sobrescrever um status terminal
 * honesto (bloqueado/desativado).
 */
class CommunicationModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_cria_com_casts_tipados(): void
    {
        $communication = Communication::factory()->create([
            'meta' => ['pendencia_id' => 7, 'tentativa' => 1],
        ]);

        $fresh = $communication->fresh();

        $this->assertInstanceOf(CommunicationChannel::class, $fresh->channel);
        $this->assertInstanceOf(CommunicationType::class, $fresh->type);
        $this->assertInstanceOf(CommunicationStatus::class, $fresh->status);
        $this->assertSame(CommunicationStatus::NaFila, $fresh->status);
        $this->assertSame(['pendencia_id' => 7, 'tentativa' => 1], $fresh->meta);
        $this->assertInstanceOf(Carbon::class, $fresh->queued_at);
    }

    public function test_relacoes_resolvem_processo_e_destinatario(): void
    {
        $request = ViabilityRequest::factory()->create();
        $user = User::factory()->create();

        $communication = Communication::factory()->create([
            'viability_request_id' => $request->id,
            'recipient_user_id' => $user->id,
        ]);

        $this->assertTrue($communication->viabilityRequest->is($request));
        $this->assertTrue($communication->recipient->is($user));
    }

    public function test_mark_as_sent_grava_status_enviado_e_sent_at(): void
    {
        $communication = Communication::factory()->create();

        $communication->markAsSent();

        $this->assertSame(CommunicationStatus::Enviado, $communication->fresh()->status);
        $this->assertNotNull($communication->fresh()->sent_at);
    }

    public function test_mark_as_failed_grava_status_falhou_erro_e_failed_at(): void
    {
        $communication = Communication::factory()->create();

        $communication->markAsFailed('SMTP 550: mailbox unavailable');

        $fresh = $communication->fresh();
        $this->assertSame(CommunicationStatus::Falhou, $fresh->status);
        $this->assertSame('SMTP 550: mailbox unavailable', $fresh->error_message);
        $this->assertNotNull($fresh->failed_at);
    }

    public function test_mark_as_blocked_grava_status_bloqueado_e_motivo(): void
    {
        $communication = Communication::factory()->create();

        $communication->markAsBlocked('Gateway de WhatsApp indisponível');

        $fresh = $communication->fresh();
        $this->assertSame(CommunicationStatus::Bloqueado, $fresh->status);
        $this->assertSame('Gateway de WhatsApp indisponível', $fresh->error_message);
        $this->assertNull($fresh->sent_at);
    }

    public function test_mark_as_disabled_grava_status_desativado(): void
    {
        $communication = Communication::factory()->create();

        $communication->markAsDisabled();

        $fresh = $communication->fresh();
        $this->assertSame(CommunicationStatus::Desativado, $fresh->status);
        $this->assertNull($fresh->sent_at);
    }

    public function test_mark_as_sent_nao_sobrescreve_status_bloqueado(): void
    {
        $communication = Communication::factory()->create([
            'status' => CommunicationStatus::Bloqueado,
            'error_message' => 'Gateway de WhatsApp indisponível',
        ]);

        $communication->markAsSent();

        $fresh = $communication->fresh();
        $this->assertSame(CommunicationStatus::Bloqueado, $fresh->status);
        $this->assertNull($fresh->sent_at);
    }

    public function test_mark_as_sent_nao_sobrescreve_status_desativado(): void
    {
        $communication = Communication::factory()->create([
            'status' => CommunicationStatus::Desativado,
        ]);

        $communication->markAsSent();

        $fresh = $communication->fresh();
        $this->assertSame(CommunicationStatus::Desativado, $fresh->status);
        $this->assertNull($fresh->sent_at);
    }
}
