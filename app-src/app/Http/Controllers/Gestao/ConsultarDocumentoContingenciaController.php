<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gestao\ConsultarCnpjContingenciaRequest;
use App\Models\Company;
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
