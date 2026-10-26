<?php

namespace Tests\Feature\PortalServicos;

use App\Enums\ViabilityRequestOrigin;
use App\Enums\ViabilityRequestStatus;
use App\Models\Cidadao;
use App\Models\IntegrationCall;
use App\Models\Parameter;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Services\Solicitacao\VinculosCpfProcesso;
use Database\Seeders\ParameterSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class ConsultaProcessosPortalServicosTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const CPF = '52998224725';

    private const OUTRO_CPF = '11144477735';

    private const TOKEN = 'tok-portal-consulta-123456';

    private const URL = '/api_integracao/portal-servicos/processos';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ParameterSeeder::class);
        $this->ligarEntrada();
    }

    private function ligarEntrada(string $valor = '1'): void
    {
        Parameter::query()->where('key', 'features.login_portal_servicos')->update(['value' => $valor]);
        Cache::flush();
    }

    private function sessaoDe(string $cpf = self::CPF): void
    {
        Http::fake(['*/v1/acesso/buscar-sessao' => Http::response(['cpf' => $cpf])]);
    }

    private function consultar(string $query = '')
    {
        return $this->getJson(self::URL.$query, ['Authorization' => 'Bearer '.self::TOKEN]);
    }

    private function processo(array $atributos = [], ViabilityRequestStatus $status = ViabilityRequestStatus::EmAnalise): ViabilityRequest
    {
        $processo = ViabilityRequest::factory()->create($atributos);
        $processo->forceFill([
            'status' => $status,
            'protocol_number' => $atributos['protocol_number'] ?? 'VIA-2026-'.str_pad((string) $processo->id, 6, '0', STR_PAD_LEFT),
            'protocoled_at' => now()->subDays(10 - $processo->id),
        ])->save();

        return $processo->refresh();
    }

    public function test_devolve_os_processos_do_cpf_da_sessao(): void
    {
        $this->sessaoDe();
        $dono = Cidadao::factory()->create(['cpf' => self::CPF]);
        $proprio = $this->processo(['requester_cidadao_id' => $dono->id]);
        $comoSocio = $this->processo(['requester_cidadao_id' => null, 'origin' => ViabilityRequestOrigin::Regin]);
        app(VinculosCpfProcesso::class)->sincronizar($comoSocio, [self::CPF]);

        $response = $this->consultar()->assertOk();

        $this->assertEqualsCanonicalizing(
            [$proprio->protocol_number, $comoSocio->protocol_number],
            array_column($response->json('data'), 'numero_processo'),
        );
    }

    public function test_formato_do_item(): void
    {
        $this->sessaoDe();
        $processo = $this->processo(['requester_cidadao_id' => null, 'external_reference' => 'BAP2600011111'], ViabilityRequestStatus::EmPendencia);
        ViabilityDecision::factory()->create(['viability_request_id' => $processo->id, 'tvl_product_number' => 'TVL-2026-000777']);
        app(VinculosCpfProcesso::class)->sincronizar($processo, [self::CPF]);

        $item = $this->consultar()->assertOk()->json('data.0');

        $this->assertSame($processo->protocol_number, $item['numero_processo']);
        $this->assertSame('TVL-2026-000777', $item['numero_viabilidade']);
        $this->assertSame('BAP2600011111', $item['bap']);
        $this->assertSame($processo->serviceType->name, $item['servico']);
        $this->assertSame($processo->company->legal_name, $item['empresa']);
        $this->assertSame('em_pendencia', $item['situacao']);
        $this->assertSame(ViabilityRequestStatus::EmPendencia->publicLabel(), $item['situacao_descricao']);
        $this->assertTrue($item['convite_pendente']);
        $this->assertSame('socio', $item['vinculo']);
        $this->assertNotNull($item['protocolado_em']);
        $this->assertNotNull($item['atualizado_em']);
        $this->assertStringContainsString('/portal/entrar/portal-servicos?destino=processo&numero='.$processo->protocol_number, $item['detalhar_url']);
        $this->assertArrayHasKey('cnpj', $item);
    }

    public function test_sem_rascunho_e_sem_processo_de_outro_cpf(): void
    {
        $this->sessaoDe();
        $dono = Cidadao::factory()->create(['cpf' => self::CPF]);
        $outro = Cidadao::factory()->create(['cpf' => self::OUTRO_CPF]);
        ViabilityRequest::factory()->create(['requester_cidadao_id' => $dono->id]);
        $this->processo(['requester_cidadao_id' => $outro->id]);

        $this->consultar()->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_rascunho_cancelado_fica_fora(): void
    {
        $this->sessaoDe();
        $dono = Cidadao::factory()->create(['cpf' => self::CPF]);
        ViabilityRequest::factory()->create(['requester_cidadao_id' => $dono->id, 'status' => ViabilityRequestStatus::Cancelada, 'protocol_number' => null, 'protocoled_at' => null]);

        $this->consultar()->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_cpf_de_empresa_a_constituir_nao_sai_no_campo_cnpj(): void
    {
        $this->sessaoDe();
        $processo = $this->processo(['requester_cidadao_id' => null]);
        $processo->company->forceFill(['cnpj' => '00449085040'])->save();
        app(VinculosCpfProcesso::class)->sincronizar($processo, [self::CPF]);

        $this->assertNull($this->consultar()->assertOk()->json('data.0.cnpj'));
    }

    public function test_limite_de_chamadas_e_por_sessao_e_nao_por_ip(): void
    {
        $limite = fn (string $token) => RateLimiter::limiter('portal-servicos-processos')(
            Request::create(self::URL, 'GET', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token, 'REMOTE_ADDR' => '10.0.0.1'])
        );

        $this->assertNotSame($limite('token-a')->key, $limite('token-b')->key);
        $this->assertStringNotContainsString('token-a', (string) $limite('token-a')->key);
    }

    public function test_parametro_cpf_e_ignorado(): void
    {
        $this->sessaoDe();
        $outro = Cidadao::factory()->create(['cpf' => self::OUTRO_CPF]);
        $this->processo(['requester_cidadao_id' => $outro->id]);

        $this->consultar('?cpf='.self::OUTRO_CPF)->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_processo_em_dois_vinculos_aparece_uma_vez(): void
    {
        $this->sessaoDe();
        $dono = Cidadao::factory()->create(['cpf' => self::CPF]);
        $processo = $this->processo(['requester_cidadao_id' => $dono->id, 'solicitante_cpf' => self::CPF]);
        app(VinculosCpfProcesso::class)->sincronizar($processo, [self::CPF]);

        $data = $this->consultar()->assertOk()->assertJsonCount(1, 'data')->json('data');

        $this->assertSame('solicitante', $data[0]['vinculo']);
    }

    public function test_sem_token_e_token_recusado_dao_401(): void
    {
        Http::fake(['*/v1/acesso/buscar-sessao' => Http::response([], 401)]);

        $this->getJson(self::URL)->assertUnauthorized()->assertJson(['message' => 'Sessão do Portal de Serviços inválida.']);
        $this->consultar()->assertUnauthorized()->assertJson(['message' => 'Sessão do Portal de Serviços inválida.']);
    }

    public function test_chave_desligada_e_bff_fora_dao_503(): void
    {
        Http::fake(['*/v1/acesso/buscar-sessao' => fn () => throw new ConnectionException('timeout')]);

        $this->consultar()->assertStatus(503)->assertJson(['message' => 'Não foi possível confirmar a sessão agora.']);

        $this->ligarEntrada('0');
        $this->consultar()->assertStatus(503)->assertJson(['message' => 'Consulta indisponível.']);
    }

    public function test_token_nao_aparece_no_log_de_integracoes(): void
    {
        $this->sessaoDe();

        $this->consultar()->assertOk();

        $registros = IntegrationCall::query()->get();
        $this->assertNotEmpty($registros);
        $this->assertSame(['portal_servicos'], $registros->pluck('integration')->unique()->values()->all());
        foreach ($registros as $registro) {
            $this->assertStringNotContainsString(self::TOKEN, (string) json_encode($registro->request_headers));
            $this->assertStringNotContainsString(self::TOKEN, (string) $registro->url);
        }
    }
}
