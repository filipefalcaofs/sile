<?php

namespace App\Services\Sedur;

use App\Models\User;
use App\Support\Audit\AuditService;

/**
 * Pré-consulta de CPF na base cadastral da SEDUR (ws-contato). Devolve só o
 * nome para o operador conferir, sem e-mail ou telefone (LGPD).
 */
class ConsultaCpfSedurService
{
    public function __construct(
        private SedurContatoClient $contato,
        private AuditService $audit,
    ) {}

    public function consultar(string $cpf, string $auditLogName): ConsultaCpfSedurResult
    {
        try {
            $pessoa = $this->contato->consultarCpf($cpf);
        } catch (SedurContatoNaoEncontradoException $e) {
            $this->audit->log(
                $auditLogName,
                'consulta-cpf',
                'Consulta de CPF: não encontrado',
                ['cpf' => $cpf, 'motivo' => 'nao-encontrado'],
                result: 'falha',
                personalData: true,
            );

            throw $e;
        } catch (SedurContatoIndisponivelException $e) {
            $this->audit->log(
                $auditLogName,
                'consulta-cpf',
                'Consulta de CPF: serviço indisponível',
                ['cpf' => $cpf, 'motivo' => 'indisponibilidade'],
                result: 'falha',
                personalData: true,
            );

            throw $e;
        }

        $this->audit->log(
            $auditLogName,
            'consulta-cpf',
            'Consulta de CPF realizada',
            ['cpf' => $cpf],
            result: 'sucesso',
            personalData: true,
        );

        return new ConsultaCpfSedurResult(
            nome: $pessoa->nome !== '' ? $pessoa->nome : null,
            cadastrado: User::query()->where('cpf', $cpf)->exists(),
        );
    }
}
