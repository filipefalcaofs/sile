<?php

namespace App\Services\Renovacao;

use App\Services\Realty\PropertyNotFoundException;
use App\Services\Realty\PropertyRegistryLookup;
use App\Services\Realty\PropertyRegistryUnavailableException;

/**
 * Transforma a inscrição imobiliária no endereço do imóvel pelo cadastro.
 * Resultados bem-sucedidos ficam guardados por inscrição durante a requisição
 * (registrar como scoped); falhas não são guardadas nem engolidas.
 */
class ResolvedorEnderecoImovel
{
    public const M_SEM_INSCRICAO = 'A viabilidade anterior não tem inscrição imobiliária registrada para conferir o endereço. Procure a SEDUR.';

    public const M_NAO_ENCONTRADA = 'A inscrição imobiliária da viabilidade anterior não foi encontrada no cadastro. Procure a SEDUR.';

    public const M_INDISPONIVEL = 'Não foi possível consultar o endereço do imóvel agora. Tente novamente em instantes.';

    /** @var array<string, EnderecoDoImovel> */
    private array $resolvidos = [];

    public function __construct(private readonly PropertyRegistryLookup $cadastro) {}

    /**
     * @throws EnderecoImovelNaoEncontradoException
     * @throws EnderecoImovelIndisponivelException
     */
    public function resolver(?string $inscricao): EnderecoDoImovel
    {
        $digitos = preg_replace('/\D+/', '', (string) $inscricao) ?? '';

        if ($digitos === '') {
            throw new EnderecoImovelNaoEncontradoException(self::M_SEM_INSCRICAO);
        }

        return $this->resolvidos[$digitos] ??= $this->consultar($digitos);
    }

    private function consultar(string $inscricao): EnderecoDoImovel
    {
        try {
            $resultado = $this->cadastro->resolve($inscricao);
        } catch (PropertyNotFoundException) {
            throw new EnderecoImovelNaoEncontradoException(self::M_NAO_ENCONTRADA);
        } catch (PropertyRegistryUnavailableException) {
            throw new EnderecoImovelIndisponivelException(self::M_INDISPONIVEL);
        }

        $numero = $this->limpar($resultado->cadastro?->numero_porta);

        $endereco = new EnderecoDoImovel(
            $this->logradouroLimpo($this->limpar($resultado->cadastro?->endereco), $numero),
            $numero,
            $this->limpar($resultado->cadastro?->bairro),
            $this->limpar($resultado->cadastro?->cep),
        );

        if ($endereco->texto() === '') {
            throw new EnderecoImovelNaoEncontradoException(self::M_NAO_ENCONTRADA);
        }

        return $endereco;
    }

    /**
     * O cadastro devolve "Logradouro, NÚMERO — Complemento"; fica só o logradouro.
     */
    private function logradouroLimpo(?string $endereco, ?string $numero): ?string
    {
        if ($endereco === null) {
            return null;
        }

        $logradouro = trim(explode(' — ', $endereco, 2)[0]);

        if ($numero !== null) {
            $sufixo = ', '.$numero;
            $tamanho = mb_strlen($sufixo);

            if (mb_strlen($logradouro) > $tamanho && mb_strtolower(mb_substr($logradouro, -$tamanho)) === mb_strtolower($sufixo)) {
                $logradouro = trim(mb_substr($logradouro, 0, -$tamanho));
            }
        }

        return $this->limpar($logradouro);
    }

    private function limpar(?string $valor): ?string
    {
        $valor = trim((string) $valor);

        return $valor === '' ? null : $valor;
    }
}
