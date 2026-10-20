<?php

namespace Tests\Feature\Renovacao;

use App\Services\Realty\PropertyCadastroCampos;
use App\Services\Realty\PropertyNotFoundException;
use App\Services\Realty\PropertyRegistryLookup;
use App\Services\Realty\PropertyRegistryResult;
use App\Services\Realty\PropertyRegistryUnavailableException;
use App\Services\Renovacao\EnderecoDoImovel;
use App\Services\Renovacao\EnderecoImovelIndisponivelException;
use App\Services\Renovacao\EnderecoImovelNaoEncontradoException;
use App\Services\Renovacao\ResolvedorEnderecoImovel;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ResolvedorEnderecoImovelTest extends TestCase
{
    /** @var list<string> */
    private array $chamadas = [];

    private function fakeCadastro(?PropertyCadastroCampos $cadastro = null, ?string $falha = null): void
    {
        $this->chamadas = [];
        $teste = $this;

        $this->app->instance(PropertyRegistryLookup::class, new class($teste, $cadastro, $falha) implements PropertyRegistryLookup
        {
            public function __construct(
                private ResolvedorEnderecoImovelTest $teste,
                private ?PropertyCadastroCampos $cadastro,
                private ?string $falha,
            ) {}

            public function resolve(string $inscricao): PropertyRegistryResult
            {
                $this->teste->registrarChamada($inscricao);

                if ($this->falha === 'indisponivel') {
                    throw new PropertyRegistryUnavailableException($inscricao);
                }

                if ($this->falha === 'nao_encontrada') {
                    throw new PropertyNotFoundException($inscricao);
                }

                if ($this->falha === 'inesperada') {
                    throw new \RuntimeException('detalhe interno '.$inscricao);
                }

                return new PropertyRegistryResult(
                    latitude: null,
                    longitude: null,
                    inscricao: $inscricao,
                    cadastro: $this->cadastro,
                );
            }
        });
    }

    public function registrarChamada(string $inscricao): void
    {
        $this->chamadas[] = $inscricao;
    }

    private function campos(?string $endereco = null, ?string $numero = null, ?string $bairro = null, ?string $cep = null): PropertyCadastroCampos
    {
        $vazios = PropertyCadastroCampos::vazios()->toArray();
        $dados = array_merge($vazios, [
            'endereco' => $endereco,
            'numero_porta' => $numero,
            'bairro' => $bairro,
            'cep' => $cep,
        ]);

        return new PropertyCadastroCampos(...$dados);
    }

    private function resolvedor(): ResolvedorEnderecoImovel
    {
        return $this->app->make(ResolvedorEnderecoImovel::class);
    }

    public function test_mapeia_endereco_numero_porta_bairro_e_cep(): void
    {
        $this->fakeCadastro($this->campos('Rua das Flores', '123', 'Pituba', '41830-000'));

        $endereco = $this->resolvedor()->resolver('0514808100');

        $this->assertSame('Rua das Flores', $endereco->logradouro);
        $this->assertSame('123', $endereco->numero);
        $this->assertSame('Pituba', $endereco->bairro);
        $this->assertSame('41830-000', $endereco->cep);
    }

    public function test_inscricao_vazia_lanca_nao_encontrada_com_m_sem_inscricao_sem_consultar(): void
    {
        $this->fakeCadastro($this->campos('Rua A', '1'));

        foreach ([null, '', '   ', '--.'] as $inscricao) {
            try {
                $this->resolvedor()->resolver($inscricao);
                $this->fail('Deveria lançar exceção.');
            } catch (EnderecoImovelNaoEncontradoException $e) {
                $this->assertSame(ResolvedorEnderecoImovel::M_SEM_INSCRICAO, $e->getMessage());
            }
        }

        $this->assertSame([], $this->chamadas);
    }

    public function test_inscricao_com_mascara_chega_ao_cadastro_so_com_digitos(): void
    {
        $this->fakeCadastro($this->campos('Rua A', '1'));

        $this->resolvedor()->resolver(' 051.480-81 ');

        $this->assertSame(['05148081'], $this->chamadas);
    }

    public function test_property_not_found_vira_nao_encontrada(): void
    {
        $this->fakeCadastro(null, 'nao_encontrada');

        try {
            $this->resolvedor()->resolver('123');
            $this->fail('Deveria lançar exceção.');
        } catch (EnderecoImovelNaoEncontradoException $e) {
            $this->assertSame(ResolvedorEnderecoImovel::M_NAO_ENCONTRADA, $e->getMessage());
        }
    }

    public function test_cadastro_nulo_vira_nao_encontrada(): void
    {
        $this->fakeCadastro(null);

        try {
            $this->resolvedor()->resolver('123');
            $this->fail('Deveria lançar exceção.');
        } catch (EnderecoImovelNaoEncontradoException $e) {
            $this->assertSame(ResolvedorEnderecoImovel::M_NAO_ENCONTRADA, $e->getMessage());
        }
    }

    public function test_campos_todos_vazios_viram_nao_encontrada(): void
    {
        $this->fakeCadastro($this->campos('  ', '', null, "\t"));

        try {
            $this->resolvedor()->resolver('123');
            $this->fail('Deveria lançar exceção.');
        } catch (EnderecoImovelNaoEncontradoException $e) {
            $this->assertSame(ResolvedorEnderecoImovel::M_NAO_ENCONTRADA, $e->getMessage());
        }
    }

    public function test_cadastro_fora_do_ar_vira_indisponivel(): void
    {
        $this->fakeCadastro(null, 'indisponivel');

        try {
            $this->resolvedor()->resolver('123');
            $this->fail('Deveria lançar exceção.');
        } catch (EnderecoImovelIndisponivelException $e) {
            $this->assertSame(ResolvedorEnderecoImovel::M_INDISPONIVEL, $e->getMessage());
        }
    }

    public function test_falha_inesperada_do_cadastro_vira_indisponivel_e_loga_so_a_classe(): void
    {
        $this->fakeCadastro(null, 'inesperada');
        Log::spy();

        try {
            $this->resolvedor()->resolver('0123456789');
            $this->fail('Deveria lançar exceção.');
        } catch (EnderecoImovelIndisponivelException $e) {
            $this->assertSame(ResolvedorEnderecoImovel::M_INDISPONIVEL, $e->getMessage());
        }

        Log::shouldHaveReceived('warning')->once()->withArgs(function (string $mensagem, array $contexto = []): bool {
            return ! str_contains($mensagem.json_encode($contexto), '0123456789')
                && ! str_contains($mensagem.json_encode($contexto), 'detalhe interno')
                && str_contains(json_encode($contexto), 'RuntimeException');
        });
    }

    public function test_mesma_inscricao_consulta_o_cadastro_uma_so_vez(): void
    {
        $this->fakeCadastro($this->campos('Rua A', '1'));

        $this->resolvedor()->resolver('051.480-81');
        $this->resolvedor()->resolver('05148081');

        $this->assertCount(1, $this->chamadas);
    }

    public function test_endereco_no_formato_real_vira_logradouro_limpo(): void
    {
        $this->fakeCadastro($this->campos('Rua das Flores, 123 — Edifício Centro Médico', '123', 'Pituba', '41830-000'));

        $endereco = $this->resolvedor()->resolver('123');

        $this->assertSame('Rua das Flores', $endereco->logradouro);
        $this->assertSame('123', $endereco->numero);
        $this->assertSame('Rua das Flores, 123, Pituba, 41830-000', $endereco->texto());
    }

    public function test_endereco_sem_complemento_remove_so_o_numero(): void
    {
        $this->fakeCadastro($this->campos('Rua das Flores, 123', '123', null, null));

        $endereco = $this->resolvedor()->resolver('123');

        $this->assertSame('Rua das Flores', $endereco->logradouro);
        $this->assertSame('Rua das Flores, 123', $endereco->texto());
    }

    public function test_endereco_sem_numero_fica_como_esta(): void
    {
        $this->fakeCadastro($this->campos('Rua das Flores', null, 'Pituba', null));

        $endereco = $this->resolvedor()->resolver('123');

        $this->assertSame('Rua das Flores', $endereco->logradouro);
        $this->assertNull($endereco->numero);
    }

    public function test_numero_porta_diferente_do_texto_so_remove_o_complemento(): void
    {
        $this->fakeCadastro($this->campos('Rua das Flores, 456 — Edifício Centro', '123', null, null));

        $endereco = $this->resolvedor()->resolver('123');

        $this->assertSame('Rua das Flores, 456', $endereco->logradouro);
        $this->assertSame('123', $endereco->numero);
    }

    public function test_corta_so_no_primeiro_travessao(): void
    {
        $this->fakeCadastro($this->campos('Rua das Flores, 123 — Edifício Centro — Bloco B', '123', null, null));

        $this->assertSame('Rua das Flores', $this->resolvedor()->resolver('123')->logradouro);
    }

    public function test_numero_no_sufixo_ignora_maiusculas_e_espacos(): void
    {
        $this->fakeCadastro($this->campos('  Rua B, 12A  ', ' 12a ', null, null));

        $this->assertSame('Rua B', $this->resolvedor()->resolver('123')->logradouro);
    }

    public function test_resolvedor_e_scoped(): void
    {
        $a = $this->resolvedor();
        $this->assertSame($a, $this->resolvedor());

        $this->app->forgetScopedInstances();

        $this->assertNotSame($a, $this->resolvedor());
    }

    public function test_texto_junta_campos_preenchidos(): void
    {
        $endereco = new EnderecoDoImovel('Rua das Flores', '123', 'Pituba', '41830-000');

        $this->assertSame('Rua das Flores, 123, Pituba, 41830-000', $endereco->texto());
    }

    public function test_texto_pula_campos_vazios(): void
    {
        $endereco = new EnderecoDoImovel('Rua das Flores', null, '  ', '41830-000');

        $this->assertSame('Rua das Flores, 41830-000', $endereco->texto());
    }
}
