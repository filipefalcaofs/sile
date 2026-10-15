<?php

namespace App\Services\Solicitacao;

use App\Models\Cidadao;
use App\Services\Sedur\ConsultaCpfSedurService;
use App\Services\Sedur\SedurContatoIndisponivelException;
use App\Services\Sedur\SedurContatoNaoEncontradoException;

/**
 * Resolve o requerente de um pedido registrado pelo servidor (atendimento
 * presencial e contingência). CPF já cadastrado é reaproveitado; sem cadastro,
 * o nome vem da base cadastral da SEDUR e nasce um registro de balcão sem senha.
 */
class RequerenteDeBalcao
{
    public function __construct(private ConsultaCpfSedurService $consulta) {}

    public function localizar(string $cpf): ?Cidadao
    {
        return Cidadao::query()->where('cpf', $cpf)->first();
    }

    /**
     * @throws SedurContatoNaoEncontradoException
     * @throws SedurContatoIndisponivelException
     */
    public function nomeNaSedur(string $cpf, string $auditLogName): string
    {
        $nome = $this->consulta->consultar($cpf, $auditLogName)->nome;

        if ($nome === null || $nome === '') {
            throw new SedurContatoNaoEncontradoException('CPF não encontrado na base da SEDUR.');
        }

        return $nome;
    }

    /**
     * @throws SedurContatoNaoEncontradoException
     * @throws SedurContatoIndisponivelException
     */
    public function resolver(string $cpf, string $auditLogName): Cidadao
    {
        return $this->localizar($cpf)
            ?? Cidadao::criarRegistroDeBalcao($cpf, $this->nomeNaSedur($cpf, $auditLogName), null, null);
    }

    public static function mensagemDeFalha(SedurContatoNaoEncontradoException|SedurContatoIndisponivelException $e): string
    {
        return $e instanceof SedurContatoNaoEncontradoException
            ? 'CPF sem cadastro no Viabiliza e não encontrado na base da SEDUR.'
            : 'CPF sem cadastro no Viabiliza e a consulta da SEDUR está indisponível. Tente novamente.';
    }
}
