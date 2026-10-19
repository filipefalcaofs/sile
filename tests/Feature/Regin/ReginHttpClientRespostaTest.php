<?php

namespace Tests\Feature\Regin;

use App\Enums\DecisionOutcome;
use App\Events\ResultadoEmitido;
use App\Models\Activity;
use App\Models\Parameter;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Services\Regin\ReginHttpClient;
use App\Services\Regin\ReginUnavailableException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * A JUCEB confirma o parecer com RECEBIDO_SUCESSO, em texto puro ou como a string
 * JSON que a API devolve (com aspas). Qualquer outro corpo é indisponibilidade.
 */
class ReginHttpClientRespostaTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'integrations.regin.em_producao' => ['boolean', '0'],
            'integrations.regin.url_homologacao' => ['string', 'http://10.57.247.9:8080/api_integracao'],
            'integrations.regin.usuario' => ['string', 'sedur_integracao'],
        ] as $key => [$type, $value]) {
            Parameter::factory()->create([
                'key' => $key,
                'group' => 'integracoes',
                'type' => $type,
                'value' => $value,
                'default_value' => $value,
            ]);
        }

        Parameter::factory()->sensitive()->create([
            'key' => 'integrations.regin.senha',
            'group' => 'integracoes',
            'type' => 'string',
            'value' => 'segredo',
            'default_value' => null,
        ]);
    }

    private function responderCom(string $corpo): void
    {
        Http::fake([
            '*/acesso/auth' => Http::response(['token' => 'jwt-1']),
            '*/recebe' => Http::response($corpo, 200),
        ]);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function corposQueConfirmam(): array
    {
        return [
            'texto puro' => ['RECEBIDO_SUCESSO'],
            'string JSON' => ['"RECEBIDO_SUCESSO"'],
            'string JSON com espaços e quebra de linha' => ["  \"RECEBIDO_SUCESSO\"\n"],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function corposQueNaoConfirmam(): array
    {
        return [
            'erro em texto' => ['ERRO'],
            'erro como string JSON' => ['"ERRO"'],
            'corpo vazio' => [''],
            'objeto JSON' => ['{"status":"RECEBIDO_SUCESSO"}'],
        ];
    }

    #[DataProvider('corposQueConfirmam')]
    public function test_aceita_recebido_sucesso_em_texto_e_em_string_json(string $corpo): void
    {
        $this->responderCom($corpo);

        app(ReginHttpClient::class)->enviarParecer(['protocolo' => 'BAP1']);

        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/recebe'));
    }

    #[DataProvider('corposQueNaoConfirmam')]
    public function test_recusa_corpo_que_nao_e_recebido_sucesso(string $corpo): void
    {
        $this->responderCom($corpo);

        $this->expectException(ReginUnavailableException::class);
        $this->expectExceptionMessage('não confirmou');

        app(ReginHttpClient::class)->enviarParecer(['protocolo' => 'BAP1']);
    }

    public function test_parecer_confirmado_com_string_json_e_auditado_como_sucesso(): void
    {
        $this->responderCom('"RECEBIDO_SUCESSO"');

        $processo = ViabilityRequest::factory()->protocoled()->create(['external_reference' => 'BAP8000001']);
        $decisao = ViabilityDecision::factory()->create([
            'viability_request_id' => $processo->id,
            'outcome' => DecisionOutcome::Deferida,
        ]);

        event(new ResultadoEmitido($processo, $decisao));

        $resultados = Activity::query()
            ->where('log_name', 'integracoes')
            ->where('event', 'regin-parecer')
            ->pluck('result')
            ->all();

        $this->assertSame(['sucesso'], $resultados);
    }
}
