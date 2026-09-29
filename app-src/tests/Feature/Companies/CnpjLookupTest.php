<?php

namespace Tests\Feature\Companies;

use App\Models\Activity;
use App\Models\Parameter;
use App\Models\User;
use App\Services\Cnpj\BrasilApiCnpjLookup;
use App\Services\Cnpj\CnpjData;
use App\Services\Cnpj\CnpjLookup;
use App\Services\Cnpj\CnpjLookupException;
use App\Services\Cnpj\CnpjNotFoundException;
use Database\Seeders\ParameterSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CnpjLookupTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    private function sedurEmpresa(): array
    {
        return [
            'cnpj' => '00000000000191',
            'razaoSocial' => 'BANCO DO BRASIL SA',
            'nomeFantasia' => 'DIRECAO GERAL',
            'telefone' => '6134939002',
            'endereco' => [
                'logradouro' => 'SAUN Quadra 5',
                'municipio' => 'Brasilia',
                'uf' => 'DF',
            ],
        ];
    }

    /**
     * Payload REAL da BrasilAPI (Banco do Brasil) registrado na pesquisa.
     * Cobre só o adaptador antigo, que não é mais o binding da aplicação.
     *
     * @return array<string, mixed>
     */
    private function brasilApiFixture(): array
    {
        return json_decode(
            (string) file_get_contents(base_path('tests/Fixtures/cnpj/brasilapi-banco-do-brasil.json')),
            true,
        );
    }

    /**
     * Cidadão habilitado a navegar no portal (papel + termo LGPD aceito).
     */
    private function portalUser(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        return User::factory()->cidadao()->withAcceptedLgpdTerm()->create();
    }

    // ----------------------------------------------------------------
    // Serviço (contrato CnpjLookup + provider BrasilAPI)
    // ----------------------------------------------------------------

    public function test_lookup_retorna_dto_com_dados_do_provider(): void
    {
        Http::fake(['*' => Http::response($this->sedurEmpresa(), 200)]);

        $data = app(CnpjLookup::class)->lookup('00000000000191');

        $this->assertInstanceOf(CnpjData::class, $data);
        $this->assertSame('00000000000191', $data->cnpj);
        $this->assertSame('BANCO DO BRASIL SA', $data->legalName);
        $this->assertSame('DIRECAO GERAL', $data->tradeName);
        $this->assertNull($data->primaryCnaeCode);
        $this->assertSame('6134939002', $data->phone);
        $this->assertStringContainsString('SAUN Quadra 5', (string) $data->street);
    }

    public function test_lookup_usa_url_parametrizada(): void
    {
        $this->seed(ParameterSeeder::class);

        Parameter::query()
            ->where('key', 'integrations.sedur_contato.base_url')
            ->first()
            ->update(['value' => 'https://sedur.test/k8s/hml/ws-contato']);

        Http::fake(['sedur.test/*' => Http::response($this->sedurEmpresa(), 200)]);

        $data = app(CnpjLookup::class)->lookup('00000000000191');

        $this->assertSame('BANCO DO BRASIL SA', $data->legalName);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'sedur.test/k8s/hml/ws-contato/pessoajuridica/cnpj/00000000000191'));
    }

    public function test_lookup_cacheia_somente_sucesso(): void
    {
        Http::fake(['*/00000000000191' => Http::response($this->brasilApiFixture(), 200)]);

        $service = app(BrasilApiCnpjLookup::class);
        $service->lookup('00000000000191');

        $this->assertIsArray(Cache::get('sile.cnpj_lookup.00000000000191'));

        $deNovo = $service->lookup('00000000000191');

        $this->assertSame('BANCO DO BRASIL SA', $deNovo->legalName);
        Http::assertSentCount(1);
    }

    public function test_falha_de_conexao_nao_e_cacheada(): void
    {
        // Primeira resposta falha (indisponibilidade), a segunda sucede:
        // se a falha tivesse sido cacheada, a 2ª chamada nunca chegaria à rede.
        // Http::retry(N) faz N tentativas TOTAIS (retries=2 → 2 por chamada):
        // a 1ª chamada esgota 2 conexões falhas e lança; a 2ª consome a 3ª
        // falha e encontra o sucesso na 4ª resposta da sequência.
        Http::fake([
            '*/00000000000191' => Http::sequence()
                ->pushFailedConnection()
                ->pushFailedConnection()
                ->pushFailedConnection()
                ->push($this->brasilApiFixture(), 200),
        ]);

        $service = app(BrasilApiCnpjLookup::class);

        try {
            $service->lookup('00000000000191');
            $this->fail('Esperava CnpjLookupException na indisponibilidade.');
        } catch (CnpjLookupException) {
            // esperado — falha nunca é cacheada
        }

        $data = $service->lookup('00000000000191');

        $this->assertSame('BANCO DO BRASIL SA', $data->legalName);
    }

    public function test_cnpj_inexistente_lanca_excecao_tipada(): void
    {
        Http::fake(['*' => Http::response(['message' => 'CNPJ não encontrado'], 404)]);

        $this->expectException(CnpjNotFoundException::class);

        app(CnpjLookup::class)->lookup('00000000000191');
    }

    // ----------------------------------------------------------------
    // Endpoint POST /portal/empresas/consultar-cnpj (Task 2)
    // ----------------------------------------------------------------

    public function test_consulta_com_sucesso_retorna_dados_para_o_formulario(): void
    {
        Http::fake(['*' => Http::response($this->sedurEmpresa(), 200)]);

        $this->actingAs($this->portalUser())
            ->postJson('/portal/empresas/consultar-cnpj', ['cnpj' => '00.000.000/0001-91'])
            ->assertOk()
            ->assertJsonPath('cnpj', '00000000000191')
            ->assertJsonPath('legal_name', 'BANCO DO BRASIL SA')
            ->assertJsonStructure([
                'cnpj',
                'legal_name',
                'trade_name',
                'legal_nature_code',
                'legal_nature',
                'size_code',
                'size',
                'primary_cnae_code',
                'secondary_cnae_codes',
                'street',
                'number',
                'complement',
                'neighborhood',
                'city',
                'state',
                'zip_code',
                'phone',
                'email',
                'registration_status',
            ]);
    }

    public function test_consulta_com_sucesso_e_auditada(): void
    {
        Http::fake(['*' => Http::response($this->sedurEmpresa(), 200)]);

        $this->actingAs($this->portalUser())
            ->postJson('/portal/empresas/consultar-cnpj', ['cnpj' => '00.000.000/0001-91'])
            ->assertOk();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'empresas',
            'event' => 'consulta-cnpj',
            'result' => 'sucesso',
        ]);

        $activity = Activity::query()
            ->where('event', 'consulta-cnpj')
            ->latest('id')
            ->first();

        $this->assertSame('00000000000191', $activity->properties['cnpj']);
        $this->assertSame('api.sedur.salvador.ba.gov.br', $activity->properties['provider']);
    }

    public function test_cnpj_invalido_e_rejeitado_sem_consultar(): void
    {
        Http::fake();

        $this->actingAs($this->portalUser())
            ->postJson('/portal/empresas/consultar-cnpj', ['cnpj' => '11111111111111'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('cnpj');

        Http::assertNothingSent();
    }

    public function test_cnpj_inexistente_responde_404_com_mensagem(): void
    {
        Http::fake(['*' => Http::response(['message' => 'CNPJ não encontrado'], 404)]);

        $this->actingAs($this->portalUser())
            ->postJson('/portal/empresas/consultar-cnpj', ['cnpj' => '00.000.000/0001-91'])
            ->assertStatus(404)
            ->assertJsonPath('message', 'CNPJ não encontrado na base da SEDUR.');

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'empresas',
            'event' => 'consulta-cnpj',
            'result' => 'falha',
        ]);
    }

    public function test_indisponibilidade_registra_falha_e_orienta_preenchimento_manual(): void
    {
        Http::fake(['*' => Http::failedConnection()]);

        $this->actingAs($this->portalUser())
            ->postJson('/portal/empresas/consultar-cnpj', ['cnpj' => '00.000.000/0001-91'])
            ->assertStatus(503)
            ->assertJsonPath('message', 'Serviço de consulta indisponível no momento. Preencha os dados manualmente.');

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'empresas',
            'event' => 'consulta-cnpj',
            'result' => 'falha',
        ]);
    }

    public function test_toggle_desligado_bloqueia_sem_request(): void
    {
        $user = $this->portalUser();

        $this->seed(ParameterSeeder::class);

        Parameter::query()
            ->where('key', 'features.cnpj_lookup')
            ->first()
            ->update(['value' => '0']);

        Http::fake();

        $this->actingAs($user)
            ->postJson('/portal/empresas/consultar-cnpj', ['cnpj' => '00.000.000/0001-91'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'A consulta automática de CNPJ está desativada. Preencha os dados manualmente.');

        Http::assertNothingSent();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'empresas',
            'event' => 'consulta-cnpj',
            'result' => 'bloqueado',
        ]);
    }

    public function test_visitante_nao_consulta(): void
    {
        $this->postJson('/portal/empresas/consultar-cnpj', ['cnpj' => '00.000.000/0001-91'])
            ->assertStatus(401);
    }
}
