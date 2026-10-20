<?php

namespace Tests\Feature\Renovacao;

use App\Services\Realty\PropertyCadastroCampos;
use App\Services\Realty\PropertyNotFoundException;
use App\Services\Realty\PropertyRegistryLookup;
use App\Services\Realty\PropertyRegistryResult;
use App\Services\Realty\PropertyRegistryUnavailableException;

/**
 * Substitui o PropertyRegistryLookup no container por um fake que devolve o
 * endereço pedido, falha como não encontrado ou como indisponível, e registra
 * as inscrições consultadas.
 */
trait FakeCadastroImobiliario
{
    /**
     * @param  ?string  $falha  "nao_encontrada" ou "indisponivel"
     * @return \ArrayObject<int, string> inscrições consultadas
     */
    protected function cadastroImobiliario(
        ?string $endereco = null,
        ?string $numero = null,
        ?string $cep = null,
        ?string $falha = null,
        bool $semCadastro = false,
    ): \ArrayObject {
        $chamadas = new \ArrayObject;

        $campos = $semCadastro ? null : new PropertyCadastroCampos(...array_merge(
            PropertyCadastroCampos::vazios()->toArray(),
            ['endereco' => $endereco, 'numero_porta' => $numero, 'cep' => $cep],
        ));

        $this->app->instance(PropertyRegistryLookup::class, new class($campos, $falha, $chamadas) implements PropertyRegistryLookup
        {
            public function __construct(
                private ?PropertyCadastroCampos $campos,
                private ?string $falha,
                private \ArrayObject $chamadas,
            ) {}

            public function resolve(string $inscricao): PropertyRegistryResult
            {
                $this->chamadas->append($inscricao);

                if ($this->falha === 'indisponivel') {
                    throw new PropertyRegistryUnavailableException($inscricao);
                }

                if ($this->falha === 'nao_encontrada') {
                    throw new PropertyNotFoundException($inscricao);
                }

                return new PropertyRegistryResult(
                    latitude: null,
                    longitude: null,
                    inscricao: $inscricao,
                    cadastro: $this->campos,
                );
            }
        });

        return $chamadas;
    }
}
