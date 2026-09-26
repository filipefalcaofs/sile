<?php

namespace Tests\Feature\Realty;

use App\Services\Realty\LocalizacaoInscricao;
use App\Services\Realty\LocalizacaoInscricaoService;
use App\Services\Realty\PropertyNotFoundException;
use App\Services\Realty\PropertyRegistryLookup;
use App\Services\Realty\PropertyRegistryResult;
use App\Services\Realty\PropertyRegistryUnavailableException;
use Tests\TestCase;

class LocalizacaoInscricaoServiceTest extends TestCase
{
    /**
     * @param  array<string, mixed>|null  $raw
     */
    private function fakeCadastro(?array $raw, ?float $lat = null, ?float $lng = null, ?string $falha = null): object
    {
        $fake = new class($raw, $lat, $lng, $falha) implements PropertyRegistryLookup
        {
            public int $chamadas = 0;

            /**
             * @param  array<string, mixed>|null  $raw
             */
            public function __construct(
                private ?array $raw,
                private ?float $lat,
                private ?float $lng,
                private ?string $falha,
            ) {}

            public function resolve(string $inscricao): PropertyRegistryResult
            {
                $this->chamadas++;

                if ($this->falha === 'indisponivel') {
                    throw new PropertyRegistryUnavailableException($inscricao);
                }

                if ($this->falha === 'nao_encontrada') {
                    throw new PropertyNotFoundException($inscricao);
                }

                return new PropertyRegistryResult(
                    latitude: $this->lat,
                    longitude: $this->lng,
                    inscricao: $inscricao,
                    source: 'SEFAZ via SEDUR',
                    raw: $this->raw ?? [],
                );
            }
        };

        $this->app->instance(PropertyRegistryLookup::class, $fake);

        return $fake;
    }

    public function test_localiza_inscricao_com_ponto_e_logradouro_tributario(): void
    {
        $this->fakeCadastro(
            ['CodigoLogradouroTributario' => '0212', 'LogradouroTributario' => 'Avenida Lafayete Coutinho'],
            -12.977769,
            -38.516404,
        );

        $localizacao = app(LocalizacaoInscricaoService::class)->localizar('344672');

        $this->assertSame(LocalizacaoInscricao::LOCALIZADA, $localizacao->status);
        $this->assertSame(-12.977769, $localizacao->lat);
        $this->assertSame(-38.516404, $localizacao->lng);
        $this->assertSame('212', $localizacao->codLogTributario);
        $this->assertSame('Avenida Lafayete Coutinho', $localizacao->logradouroTributario);
        $this->assertTrue($localizacao->temPonto());
    }

    public function test_inscricao_sem_coordenada_devolve_sem_coordenada_mas_mantem_o_logradouro(): void
    {
        $this->fakeCadastro(['CodigoLogradouroTributario' => '212']);

        $localizacao = app(LocalizacaoInscricaoService::class)->localizar('344672');

        $this->assertSame(LocalizacaoInscricao::SEM_COORDENADA, $localizacao->status);
        $this->assertFalse($localizacao->temPonto());
        $this->assertSame('212', $localizacao->codLogTributario);
    }

    public function test_cadastro_indisponivel_devolve_indisponivel(): void
    {
        $this->fakeCadastro(null, falha: 'indisponivel');

        $localizacao = app(LocalizacaoInscricaoService::class)->localizar('344672');

        $this->assertSame(LocalizacaoInscricao::INDISPONIVEL, $localizacao->status);
        $this->assertFalse($localizacao->temPonto());
    }

    public function test_inscricao_inexistente_devolve_nao_encontrada(): void
    {
        $this->fakeCadastro(null, falha: 'nao_encontrada');

        $localizacao = app(LocalizacaoInscricaoService::class)->localizar('344672');

        $this->assertSame(LocalizacaoInscricao::NAO_ENCONTRADA, $localizacao->status);
    }

    public function test_sem_inscricao_nao_consulta_a_sefaz(): void
    {
        $cadastro = $this->fakeCadastro(['CodigoLogradouroTributario' => '212'], -12.9, -38.5);

        $localizacao = app(LocalizacaoInscricaoService::class)->localizar(null);

        $this->assertSame(LocalizacaoInscricao::SEM_INSCRICAO, $localizacao->status);
        $this->assertSame(0, $cadastro->chamadas);
    }

    public function test_consulta_a_sefaz_uma_vez_por_inscricao_na_mesma_execucao(): void
    {
        $cadastro = $this->fakeCadastro(['CodigoLogradouroTributario' => '212'], -12.9, -38.5);

        $servico = app(LocalizacaoInscricaoService::class);
        $servico->localizar('344.672');
        $servico->localizar('344672');

        $this->assertSame(1, $cadastro->chamadas);
    }
}
