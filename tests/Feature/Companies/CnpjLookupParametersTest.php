<?php

namespace Tests\Feature\Companies;

use App\Models\Parameter;
use App\Services\Cnpj\CnpjLookup;
use App\Services\Cnpj\CnpjLookupException;
use Database\Seeders\ParameterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CnpjLookupParametersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    /**
     * Payload REAL da BrasilAPI (Banco do Brasil) registrado na pesquisa.
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

    public function test_numero_de_tentativas_segue_o_parametro_retries(): void
    {
        $this->seed(ParameterSeeder::class);

        // O parametro retries e o numero TOTAL de tentativas passado a
        // Http::retry($times) (nao "tentativas extras"). retries=1 => 1 unica
        // tentativa por lookup: uma falha de conexao ja e fatal.
        Parameter::query()->where('key', 'integrations.cnpj_lookup.retries')->first()->update(['value' => '1']);
        // backoff zerado: sem espera entre as tentativas durante o teste.
        Parameter::query()->where('key', 'integrations.cnpj_lookup.backoff_ms')->first()->update(['value' => '0']);

        // Provar o numero de tentativas por EXAUSTAO DE SEQUENCIA (e nao por
        // Http::assertSentCount, que conta 0 em falha de conexao). Empilha
        // 1 falha + 1 sucesso: sob retries=1 a 1a chamada esgota a unica
        // tentativa na falha e lanca; a 2a encontra o sucesso. Sob o default de
        // config (retries=2 => 2 tentativas) a 1a chamada sobreviveria a falha,
        // sucederia na 2a tentativa e NAO lancaria.
        Http::fake([
            '*/00000000000191' => Http::sequence()
                ->pushFailedConnection()
                ->push($this->brasilApiFixture(), 200),
        ]);

        $service = app(CnpjLookup::class);

        try {
            $service->lookup('00000000000191');
            $this->fail('Esperava CnpjLookupException: com retries=1 a unica tentativa deve esgotar na falha.');
        } catch (CnpjLookupException) {
            // esperado: o numero de tentativas seguiu o parametro retries=1
        }

        $data = $service->lookup('00000000000191');

        $this->assertSame('BANCO DO BRASIL SA', $data->legalName);
    }
}
