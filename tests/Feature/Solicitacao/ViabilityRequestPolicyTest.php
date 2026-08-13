<?php

namespace Tests\Feature\Solicitacao;

use App\Models\Procuration;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Policies\ViabilityRequestPolicy;
use App\Support\Representation\CurrentRepresentation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Autorização da solicitação de viabilidade (HU-061 CA-04) integrada à
 * representação "em nome de" (Fase 1, [01-07]): só o dono/representado opera a
 * própria solicitação; edição/protocolo só em rascunho; cancelamento enquanto
 * não decidido. Espelha o padrão da CompanyPolicy ([03-04]).
 */
class ViabilityRequestPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function policy(): ViabilityRequestPolicy
    {
        return new ViabilityRequestPolicy;
    }

    public function test_dono_ve_e_opera_o_proprio_rascunho(): void
    {
        $owner = User::factory()->create();
        $request = ViabilityRequest::factory()->draft()->create([
            'requester_user_id' => $owner->id,
        ]);

        $this->assertTrue($this->policy()->view($owner, $request));
        $this->assertTrue($this->policy()->update($owner, $request));
        $this->assertTrue($this->policy()->protocol($owner, $request));
        $this->assertTrue($this->policy()->cancel($owner, $request));
    }

    public function test_terceiro_nao_ve_nem_opera(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $request = ViabilityRequest::factory()->draft()->create([
            'requester_user_id' => $owner->id,
        ]);

        $this->assertFalse($this->policy()->view($other, $request));
        $this->assertFalse($this->policy()->update($other, $request));
        $this->assertFalse($this->policy()->protocol($other, $request));
        $this->assertFalse($this->policy()->cancel($other, $request));
    }

    public function test_update_e_protocolo_so_em_rascunho(): void
    {
        $owner = User::factory()->create();
        $protocoled = ViabilityRequest::factory()->protocoled()->create([
            'requester_user_id' => $owner->id,
        ]);

        // O dono ainda VÊ a protocolada, mas não pode editá-la nem protocolá-la.
        $this->assertTrue($this->policy()->view($owner, $protocoled));
        $this->assertFalse($this->policy()->update($owner, $protocoled));
        $this->assertFalse($this->policy()->protocol($owner, $protocoled));
        // Cancelar ainda é possível na protocolada (antes da decisão).
        $this->assertTrue($this->policy()->cancel($owner, $protocoled));

        $cancelled = ViabilityRequest::factory()->cancelled()->create([
            'requester_user_id' => $owner->id,
        ]);
        $this->assertFalse($this->policy()->update($owner, $cancelled));
        $this->assertFalse($this->policy()->protocol($owner, $cancelled));
        $this->assertFalse($this->policy()->cancel($owner, $cancelled));
    }

    public function test_representado_opera_pelo_procurador(): void
    {
        $grantor = User::factory()->create();
        $attorney = User::factory()->create();

        $proc = Procuration::factory()->create([
            'grantor_user_id' => $grantor->id,
            'attorney_user_id' => $attorney->id,
        ]);

        $request = ViabilityRequest::factory()->draft()->create([
            'requester_user_id' => $grantor->id,
        ]);

        // Sem representação ativa, o procurador é "terceiro".
        $this->assertFalse($this->policy()->view($attorney, $request));

        // Com representação ativa, opera as solicitações do representado.
        app(CurrentRepresentation::class)->set($proc);

        $this->assertTrue($this->policy()->view($attorney, $request));
        $this->assertTrue($this->policy()->update($attorney, $request));
        $this->assertTrue($this->policy()->protocol($attorney, $request));
    }

    public function test_gate_resolve_a_policy_por_auto_discovery(): void
    {
        $owner = User::factory()->create();
        $request = ViabilityRequest::factory()->draft()->create([
            'requester_user_id' => $owner->id,
        ]);

        $this->assertTrue($owner->can('view', $request));
        $this->assertTrue($owner->can('update', $request));
    }
}
