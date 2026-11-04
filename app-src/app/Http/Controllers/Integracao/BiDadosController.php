<?php

namespace App\Http\Controllers\Integracao;

use App\Http\Controllers\Controller;
use App\Services\Relatorios\Bi\BiDadosBrutos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Dados brutos para o BI montar os próprios indicadores: o catálogo descreve as
 * tabelas e os campos, e cada tabela responde em páginas por id crescente, com
 * o link da próxima página.
 */
class BiDadosController extends Controller
{
    public function __construct(private readonly BiDadosBrutos $dados) {}

    public function catalogo(): JsonResponse
    {
        $tabelas = [];

        foreach ($this->dados->catalogo() as $nome => $tabela) {
            $tabelas[] = [
                'tabela' => $nome,
                ...$tabela,
                'url' => route('integracao.bi.dados.tabela', ['tabela' => $nome]),
            ];
        }

        return response()->json([
            'paginacao' => 'Cada resposta traz até `limite` linhas (padrão '.BiDadosBrutos::LIMITE_PADRAO.', máximo '.BiDadosBrutos::LIMITE_MAXIMO.'). Para a próxima página, chame `proxima_url` (ou repita a chamada com `apos_id` = `ultimo_id`) até `tem_mais` ser falso.',
            'carga_incremental' => 'Informe `atualizado_desde` (AAAA-MM-DD ou data e hora ISO) para trazer só o que mudou desde a última carga.',
            'privacidade' => 'Com a liberação de dados identificados (padrão), saem CPF, nomes, contatos, CNPJ, endereço, coordenada exata e textos livres; sem ela, ficam fora. Senha, token e 2FA nunca saem.',
            'tabelas' => $tabelas,
        ]);
    }

    public function tabela(Request $request, string $tabela): JsonResponse
    {
        if (! $this->dados->existe($tabela)) {
            return response()->json(['mensagem' => 'Tabela não encontrada.'], 404);
        }

        $validador = Validator::make($request->query(), [
            'apos_id' => ['nullable', 'integer', 'min:0'],
            'limite' => ['nullable', 'integer', 'min:1', 'max:'.BiDadosBrutos::LIMITE_MAXIMO],
            'atualizado_desde' => ['nullable', 'date'],
            'processo_id' => ['nullable', 'integer', 'min:1'],
            'data_de' => ['nullable', 'date_format:Y-m-d'],
            'data_ate' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:data_de'],
        ]);

        if ($validador->fails()) {
            return response()->json(['mensagem' => 'Parâmetros inválidos.', 'erros' => $validador->errors()], 422);
        }

        $parametros = $validador->validated();
        $pagina = $this->dados->pagina($tabela, $parametros);

        $proxima = $pagina['tem_mais']
            ? route('integracao.bi.dados.tabela', ['tabela' => $tabela, ...$parametros, 'apos_id' => $pagina['ultimo_id']])
            : null;

        return response()->json([
            'tabela' => $tabela,
            'gerado_em' => now()->toIso8601String(),
            'quantidade' => count($pagina['dados']),
            'dados' => $pagina['dados'],
            'paginacao' => [
                'limite' => (int) ($parametros['limite'] ?? BiDadosBrutos::LIMITE_PADRAO),
                'ultimo_id' => $pagina['ultimo_id'],
                'tem_mais' => $pagina['tem_mais'],
                'proxima_url' => $proxima,
            ],
        ]);
    }
}
