<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gestao\ConsultarCpfContingenciaRequest;
use App\Services\Sedur\ConsultaCpfSedurService;
use App\Services\Sedur\SedurContatoIndisponivelException;
use App\Services\Sedur\SedurContatoNaoEncontradoException;
use Illuminate\Http\JsonResponse;

/**
 * Pré-consulta de CPF na SEDUR (ws-contato) para o operador conferir o nome
 * antes de prosseguir. Reutilizado na contingência e no atendimento presencial.
 */
class ConsultarCpfSedurController extends Controller
{
    public function __construct(private ConsultaCpfSedurService $consulta) {}

    public function contingencia(ConsultarCpfContingenciaRequest $request): JsonResponse
    {
        return $this->responder($request, 'contingencia');
    }

    public function atendimento(ConsultarCpfContingenciaRequest $request): JsonResponse
    {
        return $this->responder($request, 'atendimento');
    }

    private function responder(ConsultarCpfContingenciaRequest $request, string $auditLogName): JsonResponse
    {
        $cpf = $request->validated('cpf');

        try {
            $resultado = $this->consulta->consultar($cpf, $auditLogName);
        } catch (SedurContatoNaoEncontradoException) {
            return response()->json([
                'message' => 'CPF não encontrado na base da SEDUR.',
            ], 404);
        } catch (SedurContatoIndisponivelException) {
            return response()->json([
                'message' => 'A consulta cadastral da SEDUR está indisponível. Confira o CPF e siga com o cadastro local.',
            ], 503);
        }

        return response()->json($resultado->toArray());
    }
}
