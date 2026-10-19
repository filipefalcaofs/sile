<?php

namespace Tests\Feature\Legado;

use App\Services\Legado\LegadoIndisponivelException;
use App\Services\Legado\LegadoViabilidadeClient;
use App\Services\Legado\TvlLegadaDetalhe;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LegadoViabilidadeClientTest extends TestCase
{
    private const BASE = 'https://legado.test/v1';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'viabiliza.integrations.legado_api.base_url' => self::BASE,
            'viabiliza.integrations.legado_api.token' => 'lg_token_de_teste',
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function resumo(int $numero = 123456, array $extra = []): array
    {
        return array_replace([
            'numero' => $numero,
            'tipo' => 'provisoria',
            'deferida' => true,
            'ativa' => true,
            'emissao' => '2025-01-10T00:00:00-03:00',
            'validade' => '2026-12-31T00:00:00-03:00',
            'situacao' => 'a_vencer',
            'dias_para_vencer' => 73,
            'renovada_depois' => false,
            'ultima_renovacao_em' => null,
            'titular' => ['documento' => '12345678000195', 'tipo' => 'cnpj', 'origem' => 'tvl'],
            'razao_social' => 'EMPRESA TESTE LTDA',
            'inscricao_imobiliaria' => '100200300',
            'endereco' => ['logradouro' => 'Rua A', 'numero' => '10', 'bairro' => 'Centro', 'cep' => '40000000'],
        ], $extra);
    }

    /**
     * @return array<string, mixed>
     */
    private function detalhe(int $numero = 123456): array
    {
        return $this->resumo($numero, [
            'ultima_renovacao_em' => '2026-03-01T10:00:00-03:00',
            'processo_numero' => '1234/2024',
            'complemento' => 'Sala 2',
            'zona' => 'ZM-1',
            'via' => 'Coletora',
            'flags' => [
                'sede' => true,
                'mei' => false,
                'escritorio_virtual' => false,
                'culto_religioso' => false,
                'edificio_comercial' => true,
            ],
            'condicionante_adicional' => 'Horário restrito',
            'atividades' => [['codigo' => '4711301', 'descricao' => 'Comércio varejista']],
            'renovacoes' => [['solicitada_em' => '2026-02-20T09:00:00-03:00', 'paga_em' => null]],
        ]);
    }

    private function cliente(): LegadoViabilidadeClient
    {
        return app(LegadoViabilidadeClient::class);
    }

    public function test_provisorias_por_cnpj_mapeia_titular_e_endereco(): void
    {
        Http::fake([
            self::BASE.'/provisorias*' => Http::response(['data' => [$this->resumo()]]),
        ]);

        $lista = $this->cliente()->provisoriasPorCnpj('12.345.678/0001-95');

        $this->assertCount(1, $lista);
        $tvl = $lista[0];
        $this->assertSame(123456, $tvl->numero);
        $this->assertSame('provisoria', $tvl->tipo);
        $this->assertTrue($tvl->deferida);
        $this->assertTrue($tvl->ativa);
        $this->assertInstanceOf(CarbonImmutable::class, $tvl->validade);
        $this->assertSame('2026-12-31', $tvl->validade->format('Y-m-d'));
        $this->assertSame('2025-01-10', $tvl->emissao->format('Y-m-d'));
        $this->assertSame('a_vencer', $tvl->situacao);
        $this->assertSame(73, $tvl->diasParaVencer);
        $this->assertFalse($tvl->renovadaDepois);
        $this->assertNull($tvl->ultimaRenovacaoEm);
        $this->assertSame('12345678000195', $tvl->titularDocumento);
        $this->assertSame('cnpj', $tvl->titularTipo);
        $this->assertSame('tvl', $tvl->titularOrigem);
        $this->assertSame('EMPRESA TESTE LTDA', $tvl->razaoSocial);
        $this->assertSame('100200300', $tvl->inscricaoImobiliaria);
        $this->assertSame(
            ['logradouro' => 'Rua A', 'numero' => '10', 'bairro' => 'Centro', 'cep' => '40000000'],
            $tvl->endereco,
        );

        Http::assertSent(fn (Request $r): bool => $r->method() === 'GET'
            && str_contains($r->url(), '/v1/provisorias?cnpj=12345678000195'));
    }

    public function test_provisorias_aceita_campos_nulos(): void
    {
        Http::fake([
            self::BASE.'/provisorias*' => Http::response(['data' => [$this->resumo(1, [
                'emissao' => null,
                'validade' => null,
                'situacao' => null,
                'dias_para_vencer' => null,
                'titular' => ['documento' => null, 'tipo' => null, 'origem' => null],
            ])]]),
        ]);

        $tvl = $this->cliente()->provisoriasPorCnpj('12345678000195')[0];

        $this->assertNull($tvl->validade);
        $this->assertNull($tvl->emissao);
        $this->assertNull($tvl->situacao);
        $this->assertNull($tvl->diasParaVencer);
        $this->assertNull($tvl->titularDocumento);
    }

    public function test_envia_bearer_e_accept_json(): void
    {
        Http::fake([self::BASE.'/provisorias*' => Http::response(['data' => []])]);

        $this->assertSame([], $this->cliente()->provisoriasPorCnpj('12345678000195'));

        Http::assertSent(fn (Request $r): bool => $r->hasHeader('Authorization', 'Bearer lg_token_de_teste')
            && $r->hasHeader('Accept', 'application/json'));
    }

    public function test_tvl_por_numero_mapeia_o_detalhe(): void
    {
        Http::fake([self::BASE.'/tvls/123456' => Http::response(['data' => $this->detalhe()])]);

        $tvl = $this->cliente()->tvlPorNumero(123456);

        $this->assertInstanceOf(TvlLegadaDetalhe::class, $tvl);
        $this->assertSame('1234/2024', $tvl->processoNumero);
        $this->assertSame('Sala 2', $tvl->complemento);
        $this->assertSame('ZM-1', $tvl->zona);
        $this->assertSame('Coletora', $tvl->via);
        $this->assertTrue($tvl->flags['sede']);
        $this->assertTrue($tvl->flags['edificio_comercial']);
        $this->assertFalse($tvl->flags['mei']);
        $this->assertSame('Horário restrito', $tvl->condicionanteAdicional);
        $this->assertSame([['codigo' => '4711301', 'descricao' => 'Comércio varejista']], $tvl->atividades);
        $this->assertSame([['solicitada_em' => '2026-02-20T09:00:00-03:00', 'paga_em' => null]], $tvl->renovacoes);
        $this->assertSame('2026-03-01', $tvl->ultimaRenovacaoEm->format('Y-m-d'));
        $this->assertSame('12345678000195', $tvl->titularDocumento);
        $this->assertSame('Rua A', $tvl->endereco['logradouro']);
    }

    public function test_tvl_por_numero_devolve_null_em_404(): void
    {
        Http::fake([self::BASE.'/tvls/999' => Http::response(['mensagem' => 'TVL não encontrada.'], 404)]);

        $this->assertNull($this->cliente()->tvlPorNumero(999));
    }

    public function test_tvls_do_processo_busca_o_processo_e_depois_cada_tvl(): void
    {
        Http::fake([
            self::BASE.'/processos*' => Http::response(['data' => [[
                'numero' => '1234/2024',
                'criado_em' => '2024-05-01T00:00:00-03:00',
                'status' => ['id' => 1, 'nome' => 'Concluído', 'fim' => true],
                'numeros_tvl' => [111, 222],
            ]]]),
            self::BASE.'/tvls/111' => Http::response(['data' => $this->detalhe(111)]),
            self::BASE.'/tvls/222' => Http::response(['data' => $this->detalhe(222)]),
        ]);

        $lista = $this->cliente()->tvlsDoProcesso('1234/2024');

        $this->assertSame([111, 222], array_map(fn ($t): int => $t->numero, $lista));
        Http::assertSent(fn (Request $r): bool => str_contains($r->url(), '/v1/processos?numero_processo=1234%2F2024'));
        Http::assertSentCount(3);
    }

    public function test_tvls_do_processo_devolve_lista_vazia_quando_nao_ha_processo(): void
    {
        Http::fake([self::BASE.'/processos*' => Http::response(['data' => []])]);

        $this->assertSame([], $this->cliente()->tvlsDoProcesso('0000/2000'));
        Http::assertSentCount(1);
    }

    public function test_tvls_do_processo_devolve_lista_vazia_em_404(): void
    {
        Http::fake([self::BASE.'/processos*' => Http::response(['mensagem' => 'não encontrado'], 404)]);

        $this->assertSame([], $this->cliente()->tvlsDoProcesso('0000/2000'));
    }

    public function test_503_duas_vezes_e_200_na_terceira_tem_sucesso(): void
    {
        Http::fake([
            self::BASE.'/provisorias*' => Http::sequence()
                ->push(['mensagem' => 'indisponível'], 503)
                ->push(['mensagem' => 'indisponível'], 503)
                ->push(['data' => [$this->resumo()]]),
        ]);

        $this->assertCount(1, $this->cliente()->provisoriasPorCnpj('12345678000195'));
        Http::assertSentCount(3);
    }

    public function test_503_nas_tres_tentativas_lanca_excecao(): void
    {
        Http::fake([self::BASE.'/provisorias*' => Http::response(['mensagem' => 'x'], 503)]);

        try {
            $this->cliente()->provisoriasPorCnpj('12345678000195');
            $this->fail('Esperava LegadoIndisponivelException.');
        } catch (LegadoIndisponivelException) {
            Http::assertSentCount(3);
        }
    }

    public function test_timeout_nas_tres_tentativas_lanca_excecao(): void
    {
        $chamadas = 0;

        Http::fake(function () use (&$chamadas): never {
            $chamadas++;

            throw new ConnectionException('timeout');
        });

        try {
            $this->cliente()->provisoriasPorCnpj('12345678000195');
            $this->fail('Esperava LegadoIndisponivelException.');
        } catch (LegadoIndisponivelException) {
            $this->assertSame(3, $chamadas);
        }
    }

    #[DataProvider('statusDeAcessoNegado')]
    public function test_401_e_403_lancam_excecao_sem_retentar(int $status): void
    {
        Http::fake([self::BASE.'/provisorias*' => Http::response(['mensagem' => 'negado'], $status)]);

        try {
            $this->cliente()->provisoriasPorCnpj('12345678000195');
            $this->fail("Esperava exceção para {$status}.");
        } catch (LegadoIndisponivelException) {
            Http::assertSentCount(1);
        }
    }

    /**
     * @return array<string, array{int}>
     */
    public static function statusDeAcessoNegado(): array
    {
        return ['401' => [401], '403' => [403]];
    }

    public function test_422_lanca_excecao_com_a_mensagem_da_api(): void
    {
        Http::fake([self::BASE.'/provisorias*' => Http::response([
            'mensagem' => 'Informe exatamente um de cnpj, inscricao ou numero.',
            'erros' => ['chave' => ['Informe exatamente um de cnpj, inscricao ou numero.']],
        ], 422)]);

        $this->expectException(LegadoIndisponivelException::class);
        $this->expectExceptionMessage('Informe exatamente um');

        $this->cliente()->provisoriasPorCnpj('12345678000195');
    }

    public function test_sem_base_url_ou_token_lanca_excecao_sem_chamar_a_rede(): void
    {
        Http::fake();

        config(['viabiliza.integrations.legado_api.base_url' => '']);

        try {
            $this->cliente()->provisoriasPorCnpj('12345678000195');
            $this->fail('Esperava LegadoIndisponivelException.');
        } catch (LegadoIndisponivelException) {
        }

        config([
            'viabiliza.integrations.legado_api.base_url' => self::BASE,
            'viabiliza.integrations.legado_api.token' => '',
        ]);

        try {
            $this->cliente()->tvlPorNumero(1);
            $this->fail('Esperava LegadoIndisponivelException.');
        } catch (LegadoIndisponivelException) {
        }

        Http::assertNothingSent();
    }
}
