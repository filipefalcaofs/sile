<?php

namespace Tests\Feature\Expresso;

use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Services\Regin\BapRegistry;
use App\Services\Regin\ReginParecerNotifier;
use App\Services\Regin\ReginUnavailableException;
use App\Services\Regin\UnavailableBapRegistry;
use App\Services\Regin\UnavailableReginParecerNotifier;
use App\Services\Sefaz\SefazUnavailableException;
use App\Services\Sefaz\SefazViabilidadeGateway;
use App\Services\Sefaz\UnavailableSefazViabilidadeGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Contratos das integrações BLOQUEADAS do fluxo expresso (Track C da Wave 1): o
 * parecer ao integrador Regin/Junta (HU-104), o envio à SEFAZ municipal (HU-110)
 * e o vínculo BAP do processo (HU-134) vivem atrás de interface, com um provider
 * Unavailable que degrada HONESTO — Regin e SEFAZ LANÇAM exceção própria (a
 * transmissão não ocorreu); o BAP retorna null (não há vínculo, nada entra em
 * aguardando_bap hoje). A Fase 13 liga a base oficial trocando SÓ o binding no
 * AppServiceProvider (mesmo padrão de PropertyRegistryLookup), sem tocar nenhum
 * call site. Nunca há adaptador falso simulando sucesso.
 */
class BlockedIntegrationContractsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Solicitação protocolada + sua decisão imutável — o payload que os contratos
     * de transmissão (Regin/SEFAZ) recebem.
     *
     * @return array{0: ViabilityRequest, 1: ViabilityDecision}
     */
    private function requestComDecisao(): array
    {
        $request = ViabilityRequest::factory()->protocoled()->create();
        $decision = ViabilityDecision::factory()->create(['viability_request_id' => $request->id]);

        return [$request, $decision];
    }

    public function test_binding_do_parecer_regin_resolve_o_provider_indisponivel(): void
    {
        $this->assertInstanceOf(
            UnavailableReginParecerNotifier::class,
            app(ReginParecerNotifier::class),
        );
    }

    public function test_parecer_regin_indisponivel_lanca_excecao_em_vez_de_simular(): void
    {
        [$request, $decision] = $this->requestComDecisao();

        $this->expectException(ReginUnavailableException::class);

        app(ReginParecerNotifier::class)->notifyParecer($request, $decision);
    }

    public function test_binding_do_envio_sefaz_resolve_o_provider_indisponivel(): void
    {
        $this->assertInstanceOf(
            UnavailableSefazViabilidadeGateway::class,
            app(SefazViabilidadeGateway::class),
        );
    }

    public function test_envio_sefaz_indisponivel_lanca_excecao_em_vez_de_simular(): void
    {
        [$request, $decision] = $this->requestComDecisao();

        $this->expectException(SefazUnavailableException::class);

        app(SefazViabilidadeGateway::class)->sendViabilidade($request, $decision);
    }

    public function test_binding_do_vinculo_bap_resolve_o_provider_indisponivel(): void
    {
        $this->assertInstanceOf(
            UnavailableBapRegistry::class,
            app(BapRegistry::class),
        );
    }

    public function test_vinculo_bap_indisponivel_retorna_null_sem_inventar_vinculo(): void
    {
        $request = ViabilityRequest::factory()->protocoled()->create();

        $this->assertNull(app(BapRegistry::class)->findLinkage($request));
    }
}
