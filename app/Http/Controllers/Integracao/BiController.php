<?php

namespace App\Http\Controllers\Integracao;

use App\Http\Controllers\Controller;
use App\Services\Analise\ProcessoQueryService;
use App\Services\Integracao\TokenBiService;
use App\Services\Relatorios\Bi\BiCatalogo;
use App\Services\Relatorios\Bi\BiConsulta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

/**
 * API de indicadores para o BI da SEDUR. Entrega só dado agregado, em JSON, para
 * o BI montar os painéis: o login devolve um token Bearer, o catálogo descreve os
 * indicadores e cada indicador responde por seu slug.
 */
class BiController extends Controller
{
    public function __construct(
        private readonly TokenBiService $tokens,
        private readonly BiCatalogo $catalogo,
    ) {}

    public function especificacao(): Response
    {
        return response((string) file_get_contents(resource_path('api/openapi-api-integracao-bi.yaml')), 200, [
            'Content-Type' => 'application/yaml; charset=UTF-8',
        ]);
    }

    public function autenticar(Request $request): JsonResponse
    {
        if (! $this->tokens->configurado()) {
            return response()->json(['mensagem' => 'Integração indisponível.'], 503);
        }

        $token = $this->tokens->emitir((string) $request->input('usuario'), (string) $request->input('senha'));

        if ($token === null) {
            return response()->json(['mensagem' => 'Usuário ou senha inválidos.'], 401);
        }

        return response()->json([
            'token' => $token,
            'tipo' => 'Bearer',
            'expira_em_segundos' => $this->tokens->validadeSegundos(),
        ]);
    }

    public function catalogo(Request $request): JsonResponse
    {
        $grupo = $request->query('grupo');

        $indicadores = collect($this->catalogo->indicadores())
            ->when(is_string($grupo) && $grupo !== '', fn ($c) => $c->filter(fn (array $i): bool => $i['grupo'] === $grupo))
            ->map(fn (array $indicador, string $slug): array => [
                'slug' => $slug,
                'grupo' => $indicador['grupo'],
                'titulo' => $indicador['titulo'],
                'descricao' => $indicador['descricao'],
                'parametros' => $indicador['parametros'],
                'url' => route('integracao.bi.indicador', ['slug' => $slug]),
            ])
            ->values();

        return response()->json([
            'grupos' => BiCatalogo::GRUPOS,
            'filtros_comuns' => [
                'data_de' => 'Início do período (AAAA-MM-DD).',
                'data_ate' => 'Fim do período (AAAA-MM-DD).',
                'setor' => 'Código do setor.',
                'analista' => 'Código do analista.',
                'bairro' => 'Parte do nome do bairro.',
                'cnae' => 'Código do CNAE (7 dígitos).',
                'categoria' => 'malha_fina, sede_escritorio, expresso ou semi_expresso.',
            ],
            'parametros' => [
                'agrupamento' => 'dia, semana ou mes (padrão mes), nas séries.',
                'limite' => 'Máximo de linhas (1 a 5000).',
                'precisao' => 'Casas decimais da grade do mapa (2 a 5, padrão 3).',
                'min_decididas' => 'Mínimo de decisões para entrar no ranking de indeferimento (padrão 5).',
                'situacao' => 'Saturação: ok, saturando, saturado ou sem_capacidade.',
            ],
            'indicadores' => $indicadores,
        ]);
    }

    public function indicador(Request $request, string $slug): JsonResponse
    {
        $indicador = $this->catalogo->indicadores()[$slug] ?? null;

        if ($indicador === null) {
            return response()->json(['mensagem' => 'Indicador não encontrado.'], 404);
        }

        $validador = Validator::make($request->query(), [
            'data_de' => ['nullable', 'date_format:Y-m-d'],
            'data_ate' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:data_de'],
            'setor' => ['nullable', 'integer', 'min:1'],
            'analista' => ['nullable', 'integer', 'min:1'],
            'bairro' => ['nullable', 'string', 'max:120'],
            'cnae' => ['nullable', 'string', 'max:7'],
            'categoria' => ['nullable', 'in:'.implode(',', array_keys(ProcessoQueryService::CATEGORIAS))],
            'agrupamento' => ['nullable', 'in:'.implode(',', BiConsulta::AGRUPAMENTOS)],
            'limite' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'precisao' => ['nullable', 'integer', 'min:2', 'max:5'],
            'min_decididas' => ['nullable', 'integer', 'min:1'],
            'situacao' => ['nullable', 'in:ok,saturando,saturado,sem_capacidade'],
        ]);

        if ($validador->fails()) {
            return response()->json(['mensagem' => 'Parâmetros inválidos.', 'erros' => $validador->errors()], 422);
        }

        $consulta = BiConsulta::deArray($validador->validated());

        return response()->json([
            'indicador' => $slug,
            'grupo' => $indicador['grupo'],
            'titulo' => $indicador['titulo'],
            'gerado_em' => now()->toIso8601String(),
            'filtros' => $consulta->aplicados(),
            'dados' => ($indicador['resolver'])($consulta),
        ]);
    }
}
