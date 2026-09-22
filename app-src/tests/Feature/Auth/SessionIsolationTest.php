<?php

namespace Tests\Feature\Auth;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;

/**
 * Isolamento físico das sessões: console (/gestao) e portal usam cookies de
 * sessão distintos, com paths disjuntos. Logar/deslogar em um ambiente é
 * invisível ao outro no nível do navegador.
 */
class SessionIsolationTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        // A suíte roda com SESSION_DRIVER=array (phpunit.xml), que não emite
        // o cookie de sessão (sessionIsPersistent() é falso). Para observar o
        // nome/path do cookie por ambiente — comportamento real em produção
        // (driver database) — fixamos um driver persistente nestes testes.
        config(['session.driver' => 'file']);
    }

    private function cookieNamed(TestResponse $response, string $name): ?Cookie
    {
        return collect($response->headers->getCookies())
            ->first(fn (Cookie $cookie) => $cookie->getName() === $name);
    }

    public function test_console_emite_cookie_de_sessao_proprio_com_path_gestao(): void
    {
        $response = $this->get('/gestao/login');

        $cookie = $this->cookieNamed($response, 'sile_gestao_session');

        $this->assertNotNull($cookie, 'Cookie de sessão do console não foi emitido.');
        $this->assertSame('/gestao', $cookie->getPath());
    }

    public function test_portal_emite_cookie_de_sessao_proprio_com_path_portal(): void
    {
        $response = $this->get('/portal/login');

        $cookie = $this->cookieNamed($response, 'sile_portal_session');

        $this->assertNotNull($cookie, 'Cookie de sessão do portal não foi emitido.');
        $this->assertSame('/portal', $cookie->getPath());
    }
}
