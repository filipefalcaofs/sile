<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gestao\ConsultarCnpjContingenciaRequest;
use App\Http\Requests\Gestao\ConsultarCpfContingenciaRequest;
use App\Models\Company;
use App\Models\User;
use App\Services\Sedur\SedurContatoClient;
use App\Services\Sedur\SedurContatoIndisponivelException;
use App\Services\Sedur\SedurContatoNaoEncontradoException;
use App\Support\Audit\AuditService;
use Illuminate\Http\JsonResponse;

/**
 * Pré-consulta de CPF e CNPJ na contingência, na base cadastral da SEDUR
 * (ws-contato). Devolve só o nome para o operador conferir. Telefone, e-mail
 * e endereço não saem nesta resposta. O protocolo continua exigindo cidadão
 * e empresa já cadastrados no Viabiliza.
 */
class ConsultarDocumentoContingenciaController extends Controller
{
    public function __construct(
        private SedurContatoClient $contato,
        private AuditService $audit,
    ) {}

    public function cpf(ConsultarCpfContingenciaRequest $request): JsonResponse
    {
        $cpf = $request->validated('cpf');

        try {
            $pessoa = $this->contato->consultarCpf($cpf);
        } catch (SedurContatoNaoEncontradoException) {
            $this->audit->log(
                'contingencia',
                'consulta-cpf',
                'Consulta de CPF: não encontrado',
                ['cpf' => $cpf, 'motivo' => 'nao-encontrado'],
                result: 'falha',
                personalData: true,
            );

            return response()->json([
                'message' => 'CPF não encontrado na base da SEDUR.',
            ], 404);
        } catch (SedurContatoIndisponivelException) {
            $this->audit->log(
                'contingencia',
                'consulta-cpf',
                'Consulta de CPF: serviço indisponível',
                ['cpf' => $cpf, 'motivo' => 'indisponibilidade'],
                result: 'falha',
                personalData: true,
            );

            return response()->json([
                'message' => 'A consulta cadastral da SEDUR está indisponível. Confira o CPF e siga com o cadastro local.',
            ], 503);
        }

        $this->audit->log(
            'contingencia',
            'consulta-cpf',
            'Consulta de CPF realizada',
            ['cpf' => $cpf],
            result: 'sucesso',
            personalData: true,
        );

        return response()->json([
            'nome' => $pessoa->nome !== '' ? $pessoa->nome : null,
            'cadastrado' => User::query()->where('cpf', $cpf)->exists(),
        ]);
    }

    public function cnpj(ConsultarCnpjContingenciaRequest $request): JsonResponse
    {
        $cnpj = $request->validated('cnpj');

        try {
            $empresa = $this->contato->consultarCnpj($cnpj);
        } catch (SedurContatoNaoEncontradoException) {
            $this->audit->log(
                'contingencia',
                'consulta-cnpj',
                'Consulta de CNPJ: não encontrado',
                ['cnpj' => $cnpj, 'motivo' => 'nao-encontrado'],
                result: 'falha',
            );

            return response()->json([
                'message' => 'CNPJ não encontrado na base da SEDUR.',
            ], 404);
        } catch (SedurContatoIndisponivelException) {
            $this->audit->log(
                'contingencia',
                'consulta-cnpj',
                'Consulta de CNPJ: serviço indisponível',
                ['cnpj' => $cnpj, 'motivo' => 'indisponibilidade'],
                result: 'falha',
            );

            return response()->json([
                'message' => 'A consulta cadastral da SEDUR está indisponível. Confira o CNPJ e siga com o cadastro local.',
            ], 503);
        }

        $this->audit->log(
            'contingencia',
            'consulta-cnpj',
            'Consulta de CNPJ realizada',
            ['cnpj' => $cnpj],
            result: 'sucesso',
        );

        return response()->json([
            'razao_social' => $empresa->razaoSocial !== '' ? $empresa->razaoSocial : null,
            'nome_fantasia' => $empresa->nomeFantasia,
            'cadastrado' => Company::query()->where('cnpj', $cnpj)->exists(),
        ]);
    }
}
