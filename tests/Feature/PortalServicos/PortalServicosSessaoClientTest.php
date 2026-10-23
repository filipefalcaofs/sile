<?php

namespace Tests\Feature\PortalServicos;

use App\Models\IntegrationCall;
use App\Models\Parameter;
use App\Services\PortalServicos\PortalServicos;
use App\Services\PortalServicos\PortalServicosIndisponivelException;
use App\Services\PortalServicos\PortalServicosSessaoClient;
use App\Services\PortalServicos\SessaoPortalServicosInvalidaException;
use Database\Seeders\ParameterSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PortalServicosSessaoClientTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const TOKEN = 'tok-123-abcdefghijklmnop';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ParameterSeeder::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function fixture(): array
    {
        return json_decode((string) file_get_contents(base_path('tests/Fixtures/portal-servicos/buscar-sessao.json')), true);
    }

    private function setParameter(string $key, string $value): void
    {
        Parameter::query()->where('key', $key)->firstOrFail()->update(['value' => $value]);
        Cache::flush();
    }

    private function cliente(): PortalServicosSessaoClient
    {
        return app(PortalServicosSessaoClient::class);
    }

    public function test_sessao_valida_devolve_cpf_nome_email_e_nivel(): void
    {
        Http::fake(['*/v1/acesso/buscar-sessao' => Http::response($this->fixture())]);

        $sessao = $this->cliente()->buscarSessao(self::TOKEN);

        $this->assertSame('52998224725', $sessao->cpf);
        $this->assertSame('Fulana de Tal', $sessao->nome);
        $this->assertSame('fulana@exemplo.com', $sessao->email);
        $this->assertSame('prata', $sessao->nivel);
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer '.self::TOKEN)
            && str_ends_with($request->url(), '/v1/acesso/buscar-sessao'));
    }

    public function test_cpf_formatado_ou_em_campo_documento_e_aceito(): void
    {
        Http::fakeSequence('*/v1/acesso/buscar-sessao')
            ->push(['cpf' => '529.982.247-25'])
            ->push(['documento' => '529.982.247-25', 'nivel' => 'GOLD']);

        $formatado = $this->cliente()->buscarSessao(self::TOKEN);
        $documento = $this->cliente()->buscarSessao(self::TOKEN);

        $this->assertSame('52998224725', $formatado->cpf);
        $this->assertNull($formatado->nome);
        $this->assertNull($formatado->nivel);
        $this->assertSame('52998224725', $documento->cpf);
        $this->assertSame('ouro', $documento->nivel);
    }

    public function test_corpo_sem_cpf_e_sessao_invalida(): void
    {
        Http::fake(['*/v1/acesso/buscar-sessao' => Http::response(['nome' => 'Sem CPF'])]);

        $this->expectException(SessaoPortalServicosInvalidaException::class);

        $this->cliente()->buscarSessao(self::TOKEN);
    }

    public function test_401_e_sessao_invalida(): void
    {
        Http::fake(['*/v1/acesso/buscar-sessao' => Http::response(['message' => 'Unauthorized'], 401)]);

        $this->expectException(SessaoPortalServicosInvalidaException::class);

        $this->cliente()->buscarSessao(self::TOKEN);
    }

    public function test_500_e_indisponibilidade(): void
    {
        Http::fake(['*/v1/acesso/buscar-sessao' => Http::response('erro', 500)]);

        $this->expectException(PortalServicosIndisponivelException::class);

        $this->cliente()->buscarSessao(self::TOKEN);
    }

    public function test_falha_de_conexao_e_indisponibilidade(): void
    {
        Http::fake(['*/v1/acesso/buscar-sessao' => fn () => throw new ConnectionException('timeout')]);

        $this->expectException(PortalServicosIndisponivelException::class);

        $this->cliente()->buscarSessao(self::TOKEN);
    }

    public function test_chamada_fica_no_log_de_integracoes_sem_o_token(): void
    {
        Http::fake(['*/v1/acesso/buscar-sessao' => Http::response($this->fixture())]);

        $this->cliente()->buscarSessao(self::TOKEN);

        $chamada = IntegrationCall::query()->where('integration', 'portal_servicos')->sole();
        $this->assertStringNotContainsString(self::TOKEN, (string) json_encode($chamada->request_headers));
        $this->assertStringNotContainsString(self::TOKEN, $chamada->url);
    }

    public function test_entrada_indisponivel_com_chave_desligada(): void
    {
        $this->assertFalse(PortalServicos::entradaDisponivel());

        $this->setParameter('features.login_portal_servicos', '1');

        $this->assertTrue(PortalServicos::entradaDisponivel());
    }

    public function test_url_de_login_leva_a_entrada_com_preencher_token(): void
    {
        $url = PortalServicos::urlLogin(PortalServicos::urlEntrada('renovacao'));

        $this->assertStringStartsWith('https://sso-homo.salvador.ba.gov.br/govbr/authorize/?client_id=SedurMais-PortalServicos-Hml&state=', $url);

        $state = urldecode(substr($url, strpos($url, '&state=') + 7));
        $this->assertStringContainsString('/portal/entrar/portal-servicos?', $state);
        $this->assertStringContainsString('token=PREENCHER_TOKEN', $state);
        $this->assertStringContainsString('destino=renovacao', $state);
    }
}
