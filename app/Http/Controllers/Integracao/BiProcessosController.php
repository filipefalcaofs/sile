<?php

namespace App\Http\Controllers\Integracao;

use App\Enums\ViabilityRequestStatus;
use App\Http\Controllers\Controller;
use App\Services\Relatorios\Bi\BiProcessosCompletos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Todos os processos com a ficha completa e a tramitação inteira, em páginas
 * por id crescente, e um processo avulso pelo id ou pelo protocolo.
 */
class BiProcessosController extends Controller
{
    public function __construct(private readonly BiProcessosCompletos $processos) {}

    public function index(Request $request): JsonResponse
    {
        $validador = Validator::make($request->query(), [
            'apos_id' => ['nullable', 'integer', 'min:0'],
            'limite' => ['nullable', 'integer', 'min:1', 'max:'.BiProcessosCompletos::LIMITE_MAXIMO],
            'atualizado_desde' => ['nullable', 'date'],
            'data_de' => ['nullable', 'date_format:Y-m-d'],
            'data_ate' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:data_de'],
            'situacao' => ['nullable', Rule::enum(ViabilityRequestStatus::class)],
        ]);

        if ($validador->fails()) {
            return response()->json(['mensagem' => 'Parâmetros inválidos.', 'erros' => $validador->errors()], 422);
        }

        $parametros = $validador->validated();
        $pagina = $this->processos->pagina($parametros);

        return response()->json([
            'gerado_em' => now()->toIso8601String(),
            'quantidade' => count($pagina['processos']),
            'processos' => $pagina['processos'],
            'paginacao' => [
                'limite' => (int) ($parametros['limite'] ?? BiProcessosCompletos::LIMITE_PADRAO),
                'ultimo_id' => $pagina['ultimo_id'],
                'tem_mais' => $pagina['tem_mais'],
                'proxima_url' => $pagina['tem_mais']
                    ? route('integracao.bi.processos', [...$parametros, 'apos_id' => $pagina['ultimo_id']])
                    : null,
            ],
        ]);
    }

    public function show(string $processo): JsonResponse
    {
        $dados = $this->processos->um($processo);

        if ($dados === null) {
            return response()->json(['mensagem' => 'Processo não encontrado.'], 404);
        }

        return response()->json(['gerado_em' => now()->toIso8601String(), 'processo' => $dados]);
    }
}
