<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

/**
 * Em produção o HTTPS termina no proxy da prefeitura e chega ao app em http.
 * Redirect com http:// faz o navegador esconder o header x-inertia e o
 * Inertia exibe o JSON cru da página (logout no termo LGPD).
 */
class ProxyReversoHttpsTest extends TestCase
{
    public function test_redirect_atras_do_proxy_https_mantem_https(): void
    {
        $response = $this->withServerVariables(['REMOTE_ADDR' => '10.20.30.40'])
            ->withHeaders([
                'X-Forwarded-Proto' => 'https',
                'X-Forwarded-Host' => 'viabiliza.salvador.ba.gov.br',
                'X-Forwarded-Port' => '443',
            ])
            ->get('/gestao/termo-lgpd');

        $response->assertRedirect('https://viabiliza.salvador.ba.gov.br/gestao/login');
    }

    public function test_requisicao_sem_proxy_continua_no_esquema_original(): void
    {
        $response = $this->get('http://localhost/gestao/termo-lgpd');

        $response->assertRedirect('http://localhost/gestao/login');
    }
}
