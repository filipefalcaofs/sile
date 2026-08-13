<?php

namespace Tests\Feature\Companies;

use App\Services\Cnpj\CnpjLookup;
use App\Services\Cnpj\CnpjLookupException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
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
        // retries/backoff_ms sao constantes tecnicas de HTTP em config/sile.php
        // (fora do catalogo HU-014); o BrasilApiCnpjLookup as le via Settings::get
        // com fallback de config. retries e o numero TOTAL de tentativas passado a
        // Http::retry($times): retries=1 => 1 unica tentativa por lookup (uma falha
        // de conexao ja e fatal); backoff zerado evita espera no teste.
        config([
            'sile.integrations.cnpj_lookup.retries' => 1,
            'sile.integrations.cnpj_lookup.backoff_ms' => 0,
        ]);
        Cache::flush();

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
