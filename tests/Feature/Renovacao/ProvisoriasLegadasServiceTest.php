<?php

namespace Tests\Feature\Renovacao;

use App\Models\Cnae;
use App\Models\Company;
use App\Services\Cnpj\CnpjData;
use App\Services\Cnpj\CnpjLookup;
use App\Services\Cnpj\CnpjLookupException;
use App\Services\Legado\LegadoIndisponivelException;
use App\Services\Legado\LegadoViabilidadeClient;
use App\Services\Legado\TvlLegada;
use App\Services\Legado\TvlLegadaDetalhe;
use App\Services\Renovacao\ProvisoriasLegadasService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Mockery\MockInterface;
use Tests\TestCase;

class ProvisoriasLegadasServiceTest extends TestCase
{
    use FakeCadastroImobiliario;
    use LazilyRefreshDatabase;

    private const CNPJ = '11222333000181';

    private const OUTRO_CNPJ = '99888777000166';

    private const RAZAO = 'PADARIA ESTRELA LTDA';

    private const CODIGO_CNAE = '4721102';

    protected function setUp(): void
    {
        parent::setUp();

        Cnae::factory()->create(['code' => self::CODIGO_CNAE]);
        $this->cadastroRespondendo(self::RAZAO);
        $this->cadastroImobiliario('RUA DA ENGOMADEIRA, 508 — SALA 2', '508', '41200-000');
    }

    public function test_numero_da_tvl_e_numero_do_processo_chegam_a_mesma_tvl(): void
    {
        $empresa = $this->empresa();
        $this->mockClient(function (MockInterface $client): void {
            $client->shouldReceive('tvlPorNumero')->with(1234567)->andReturn($this->detalhe(1234567));
            $client->shouldReceive('tvlsDoProcesso')
                ->with('2024000123-00000456/2024')
                ->andReturn([$this->resumo(1234567)]);
        });

        $porTvl = $this->service()->buscarPorNumero($empresa, ' 1234567 ');
        $porProcesso = $this->service()->buscarPorNumero($empresa, '2024000123-00000456/2024');

        $this->assertFalse($porTvl['indisponivel']);
        $this->assertSame(1234567, $porTvl['itens'][0]['numero']);
        $this->assertSame($porTvl['itens'], $porProcesso['itens']);
        $this->assertSame([
            'numero' => 1234567,
            'empresa_id' => $empresa->id,
            'empresa' => $empresa->legal_name,
            'validade' => '2026-12-31',
            'situacao' => 'vigente',
            'dias_para_vencer' => 73,
            'endereco' => 'RUA DA ENGOMADEIRA, 508, 41200000',
            'elegivel' => true,
            'motivo' => null,
            'origem' => 'legado',
        ], $porTvl['itens'][0]);
    }

    public function test_formato_invalido_nao_chama_a_api(): void
    {
        $this->mockClient(function (MockInterface $client): void {
            $client->shouldNotReceive('tvlPorNumero');
            $client->shouldNotReceive('tvlsDoProcesso');
            $client->shouldNotReceive('provisoriasPorCnpj');
        });

        foreach (['', '123', '12345678', 'abcdefg', '2024000123-00000456-2024', '1234567; DROP'] as $entrada) {
            $this->assertSame(
                ['itens' => [], 'indisponivel' => false],
                $this->service()->buscarPorNumero($this->empresa(), $entrada),
            );
        }
    }

    public function test_tvl_de_outro_titular_e_descartada_sem_vestigio(): void
    {
        $this->mockClient(function (MockInterface $client): void {
            $client->shouldReceive('tvlPorNumero')->with(1234567)
                ->andReturn($this->detalhe(1234567, titular: self::OUTRO_CNPJ));
        });

        $resultado = $this->service()->buscarPorNumero($this->empresa(), '1234567');

        $this->assertSame(['itens' => [], 'indisponivel' => false], $resultado);
    }

    public function test_tvl_de_outro_titular_nao_aparece_na_listagem_da_empresa(): void
    {
        $this->mockClient(function (MockInterface $client): void {
            $client->shouldReceive('provisoriasPorCnpj')->with(self::CNPJ)
                ->andReturn([$this->resumo(1111111), $this->resumo(2222222)]);
            $client->shouldReceive('tvlPorNumero')->with(1111111)->andReturn($this->detalhe(1111111));
            $client->shouldReceive('tvlPorNumero')->with(2222222)
                ->andReturn($this->detalhe(2222222, titular: self::OUTRO_CNPJ));
        });

        $resultado = $this->service()->paraEmpresas(collect([$this->empresa()]));

        $this->assertSame([1111111], array_column($resultado['itens'], 'numero'));
    }

    public function test_tvl_sem_cnpj_gravado_passa_so_quando_a_conferencia_passa(): void
    {
        $this->mockClient(function (MockInterface $client): void {
            $client->shouldReceive('tvlPorNumero')->with(1234567)
                ->andReturn($this->detalhe(1234567, titular: null));
        });

        $this->assertCount(1, $this->service()->buscarPorNumero($this->empresa(), '1234567')['itens']);

        $this->cadastroRespondendo('OUTRA EMPRESA LTDA');

        $this->assertSame([], $this->service()->buscarPorNumero($this->empresa(), '1234567')['itens']);
    }

    public function test_tvl_ja_renovada_continua_visivel_com_o_motivo(): void
    {
        $this->mockClient(function (MockInterface $client): void {
            $client->shouldReceive('tvlPorNumero')->with(1234567)
                ->andReturn($this->detalhe(1234567, renovadaDepois: true));
        });

        $item = $this->service()->buscarPorNumero($this->empresa(), '1234567')['itens'][0];

        $this->assertFalse($item['elegivel']);
        $this->assertSame('Esta viabilidade já foi renovada.', $item['motivo']);
    }

    public function test_falha_da_api_marca_indisponivel(): void
    {
        Log::spy();
        $this->mockClient(function (MockInterface $client): void {
            $client->shouldReceive('tvlPorNumero')->andThrow(new LegadoIndisponivelException('fora do ar'));
        });

        $resultado = $this->service()->buscarPorNumero($this->empresa(), '1234567');

        $this->assertSame(['itens' => [], 'indisponivel' => true], $resultado);
        Log::shouldHaveReceived('warning')->once()->withArgs(
            fn (string $mensagem, array $contexto): bool => ! str_contains($mensagem.json_encode($contexto), self::CNPJ),
        );
    }

    public function test_payload_malformado_marca_indisponivel(): void
    {
        $this->mockClient(function (MockInterface $client): void {
            $client->shouldReceive('tvlPorNumero')->andThrow(new \ErrorException('Undefined array key "numero"'));
            $client->shouldReceive('provisoriasPorCnpj')->andThrow(new \TypeError('payload inválido'));
        });

        $this->assertTrue($this->service()->buscarPorNumero($this->empresa(), '1234567')['indisponivel']);

        $resultado = $this->service()->paraEmpresas(collect([$this->empresa()]));

        $this->assertSame(['itens' => [], 'indisponivel' => true], $resultado);
    }

    public function test_colecao_vazia_nao_chama_o_cliente(): void
    {
        $this->mockClient(function (MockInterface $client): void {
            $client->shouldNotReceive('provisoriasPorCnpj');
            $client->shouldNotReceive('tvlPorNumero');
        });

        $this->assertSame(
            ['itens' => [], 'indisponivel' => false],
            $this->service()->paraEmpresas(new Collection),
        );
    }

    public function test_falha_de_uma_empresa_mantem_os_itens_da_outra(): void
    {
        $boa = $this->empresa();
        $ruim = Company::factory()->create(['cnpj' => self::OUTRO_CNPJ]);

        $this->mockClient(function (MockInterface $client): void {
            $client->shouldReceive('provisoriasPorCnpj')->with(self::OUTRO_CNPJ)
                ->andThrow(new LegadoIndisponivelException('fora do ar'));
            $client->shouldReceive('provisoriasPorCnpj')->with(self::CNPJ)->andReturn([$this->resumo(1111111)]);
            $client->shouldReceive('tvlPorNumero')->with(1111111)->andReturn($this->detalhe(1111111));
        });

        $resultado = $this->service()->paraEmpresas(collect([$ruim, $boa]));

        $this->assertTrue($resultado['indisponivel']);
        $this->assertSame([1111111], array_column($resultado['itens'], 'numero'));
        $this->assertSame($boa->id, $resultado['itens'][0]['empresa_id']);
    }

    public function test_duplicadas_sao_colapsadas_e_detalhe_nulo_e_ignorado(): void
    {
        $this->mockClient(function (MockInterface $client): void {
            $client->shouldReceive('provisoriasPorCnpj')->with(self::CNPJ)
                ->andReturn([$this->resumo(1111111), $this->resumo(1111111), $this->resumo(3333333)]);
            $client->shouldReceive('tvlPorNumero')->with(1111111)->andReturn($this->detalhe(1111111));
            $client->shouldReceive('tvlPorNumero')->with(3333333)->andReturn(null);
        });

        $resultado = $this->service()->paraEmpresas(collect([$this->empresa()]));

        $this->assertSame([1111111], array_column($resultado['itens'], 'numero'));
    }

    public function test_consulta_cadastral_fora_do_ar_marca_indisponivel_e_nao_descarta_em_silencio(): void
    {
        Log::spy();
        $this->cadastroFalhando();
        $this->mockClient(function (MockInterface $client): void {
            $client->shouldReceive('provisoriasPorCnpj')->with(self::CNPJ)->andReturn([$this->resumo(1111111)]);
            $client->shouldReceive('tvlPorNumero')->with(1111111)->andReturn($this->detalhe(1111111, titular: null));
        });

        $lista = $this->service()->paraEmpresas(collect([$this->empresa()]));
        $busca = $this->service()->buscarPorNumero($this->empresa(), '1111111');

        $this->assertSame(['itens' => [], 'indisponivel' => true], $lista);
        $this->assertSame(['itens' => [], 'indisponivel' => true], $busca);
        Log::shouldHaveReceived('warning')->twice()->withArgs(
            fn (string $mensagem, array $contexto): bool => ! str_contains($mensagem.json_encode($contexto), self::CNPJ),
        );
    }

    public function test_cadastro_imobiliario_fora_do_ar_marca_indisponivel_e_nao_descarta_a_tvl(): void
    {
        Log::spy();
        $this->cadastroImobiliario(falha: 'indisponivel');
        $this->mockClient(function (MockInterface $client): void {
            $client->shouldReceive('provisoriasPorCnpj')->with(self::CNPJ)->andReturn([$this->resumo(1111111)]);
            $client->shouldReceive('tvlPorNumero')->with(1111111)->andReturn($this->detalhe(1111111, titular: null));
        });

        $lista = $this->service()->paraEmpresas(collect([$this->empresa()]));
        $busca = $this->service()->buscarPorNumero($this->empresa(), '1111111');

        $this->assertSame(['itens' => [], 'indisponivel' => true], $lista);
        $this->assertSame(['itens' => [], 'indisponivel' => true], $busca);
        Log::shouldHaveReceived('warning')->twice()->withArgs(
            fn (string $mensagem, array $contexto): bool => ! str_contains($mensagem.json_encode($contexto), self::CNPJ),
        );
    }

    public function test_tvl_sem_cnpj_gravado_e_imovel_de_outro_endereco_e_descartada(): void
    {
        $this->cadastroImobiliario('AV SETE DE SETEMBRO, 10', '10', '40060-001');
        $this->mockClient(function (MockInterface $client): void {
            $client->shouldReceive('tvlPorNumero')->with(1234567)
                ->andReturn($this->detalhe(1234567, titular: null));
        });

        $this->assertSame(
            ['itens' => [], 'indisponivel' => false],
            $this->service()->buscarPorNumero($this->empresa(), '1234567'),
        );
    }

    public function test_consulta_cadastral_e_feita_uma_vez_por_item_sem_cnpj_gravado(): void
    {
        $chamadas = new \ArrayObject;
        $this->cadastroRespondendo(self::RAZAO, $chamadas);
        $this->mockClient(function (MockInterface $client): void {
            $client->shouldReceive('tvlPorNumero')->with(1234567)->andReturn($this->detalhe(1234567, titular: null));
        });

        $resultado = $this->service()->buscarPorNumero($this->empresa(), '1234567');

        $this->assertCount(1, $resultado['itens']);
        $this->assertCount(1, $chamadas);
    }

    public function test_empresa_com_cnpj_invalido_nao_chama_o_cliente(): void
    {
        $this->mockClient(function (MockInterface $client): void {
            $client->shouldNotReceive('provisoriasPorCnpj');
        });
        $empresa = Company::factory()->create(['cnpj' => '123']);

        $this->assertSame(
            ['itens' => [], 'indisponivel' => false],
            $this->service()->paraEmpresas(collect([$empresa])),
        );
    }

    private function service(): ProvisoriasLegadasService
    {
        return app(ProvisoriasLegadasService::class);
    }

    private function empresa(): Company
    {
        return Company::query()->where('cnpj', self::CNPJ)->first()
            ?? Company::factory()->create(['cnpj' => self::CNPJ]);
    }

    private function mockClient(callable $expectativas): void
    {
        $this->mock(LegadoViabilidadeClient::class, $expectativas);
    }

    private function cadastroFalhando(): void
    {
        $this->app->instance(CnpjLookup::class, new class implements CnpjLookup
        {
            public function lookup(string $cnpj): CnpjData
            {
                throw new CnpjLookupException('fora do ar');
            }
        });
    }

    private function cadastroRespondendo(string $razao, ?\ArrayObject $chamadas = null): void
    {
        $dados = new CnpjData(
            cnpj: self::CNPJ,
            legalName: $razao,
            tradeName: null,
            legalNatureCode: null,
            legalNature: null,
            sizeCode: null,
            size: null,
            primaryCnaeCode: null,
            secondaryCnaeCodes: [],
            street: 'RUA DA ENGOMADEIRA',
            number: '508',
            complement: null,
            neighborhood: null,
            city: null,
            state: null,
            zipCode: '41200000',
            phone: null,
            email: null,
            registrationStatus: null,
        );

        $this->app->instance(CnpjLookup::class, new class($dados, $chamadas) implements CnpjLookup
        {
            public function __construct(private CnpjData $dados, private ?\ArrayObject $chamadas) {}

            public function lookup(string $cnpj): CnpjData
            {
                $this->chamadas?->append($cnpj);

                return $this->dados;
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(int $numero, ?string $titular, bool $renovadaDepois): array
    {
        return [
            'numero' => $numero,
            'tipo' => 'provisoria',
            'deferida' => true,
            'ativa' => true,
            'validade' => '2026-12-31',
            'situacao' => 'vigente',
            'dias_para_vencer' => 73,
            'renovada_depois' => $renovadaDepois,
            'renovacoes' => $renovadaDepois ? [['solicitada_em' => '2026-09-10T00:00:00-03:00', 'paga_em' => null]] : [],
            'titular' => ['documento' => $titular],
            'razao_social' => self::RAZAO,
            'inscricao_imobiliaria' => '0514808100',
            'endereco' => ['logradouro' => 'RUA DA ENGOMADEIRA', 'numero' => '508', 'bairro' => '', 'cep' => '41200000'],
            'atividades' => [['codigo' => self::CODIGO_CNAE, 'descricao' => 'Padaria']],
        ];
    }

    private function detalhe(
        int $numero,
        ?string $titular = self::CNPJ,
        bool $renovadaDepois = false,
    ): TvlLegadaDetalhe {
        return TvlLegadaDetalhe::fromArray($this->payload($numero, $titular, $renovadaDepois));
    }

    private function resumo(int $numero): TvlLegada
    {
        return TvlLegada::fromArray($this->payload($numero, self::CNPJ, false));
    }
}
