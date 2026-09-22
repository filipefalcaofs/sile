<?php

namespace Tests\Feature\Comunicacao;

use App\Services\Whatsapp\UnavailableWhatsAppGateway;
use App\Services\Whatsapp\WhatsAppGateway;
use App\Services\Whatsapp\WhatsAppMessage;
use App\Services\Whatsapp\WhatsAppUnavailableException;
use Tests\TestCase;

/**
 * Contrato do canal WhatsApp (HU-095) — degradação HONESTA (entrega-funcional):
 * o provedor real (API comercial) é pendência da Fase 13, então o binding default
 * do WhatsAppGateway é o UnavailableWhatsAppGateway, que LANÇA
 * WhatsAppUnavailableException. A transmissão NÃO ocorre e NUNCA é simulada —
 * exatamente o padrão de Regin/SEFAZ/Bap. A Fase 13 troca SÓ este binding.
 */
class WhatsAppGatewayContractTest extends TestCase
{
    public function test_binding_default_resolve_o_gateway_indisponivel(): void
    {
        // Ao lado de Regin/SEFAZ/Bap: o container entrega o provider bloqueado.
        $this->assertInstanceOf(
            UnavailableWhatsAppGateway::class,
            app(WhatsAppGateway::class),
            'O binding default do WhatsAppGateway deve ser o provider indisponível (Fase 13 troca só este binding).'
        );
    }

    public function test_gateway_indisponivel_lanca_excecao_e_nunca_simula_envio(): void
    {
        $gateway = app(WhatsAppGateway::class);

        // Anti-fachada: send() jamais "dá certo" silenciosamente — lança a exceção
        // honesta (provedor/credencial pendentes). Não há registro de "enviado".
        $this->expectException(WhatsAppUnavailableException::class);

        $gateway->send(new WhatsAppMessage(to: '+5571999990000', body: 'Mensagem de teste'));
    }
}
