<?php

namespace App\Http\Controllers\Integracao;

use App\Http\Controllers\Controller;
use App\Services\Integracao\ConsultaTvlService;
use App\Services\Integracao\TokenTvlService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Consulta de Viabilidade pela SEFAZ/SIGS (alvará e DAM). O corpo segue o WS SIGS
 * `buscarPorNumeroTVLRegin`; o HTTP diz o essencial (404 não encontrado,
 * 401 sem token válido, 503 integração desligada) e `resposta.status` carrega o enum
 * SIGS (00/01/02/99). Toda chamada passa pelo registro de integrações.
 */
class ConsultaTvlController extends Controller
{
    public function __construct(
        private readonly ConsultaTvlService $consulta,
        private readonly TokenTvlService $tokens,
    ) {}

    public function documentacao(): RedirectResponse
    {
        return redirect()->route('regin.docs');
    }

    public function especificacao(): Response
    {
        return response((string) file_get_contents(resource_path('api/openapi-api-integracao-tvl.yaml')), 200, [
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

    public function show(Request $request, string $numero): JsonResponse
    {
        if (! $this->tokens->configurado()) {
            return $this->erro($numero, 503);
        }

        if (! $this->tokens->valido((string) $request->bearerToken())) {
            return $this->erro($numero, 401);
        }

        try {
            $corpo = $this->consulta->consultar($numero);
        } catch (Throwable $exception) {
            Log::error('sefaz.tvl', ['tvl' => $numero, 'excecao' => $exception::class]);

            return $this->erro($numero, 500);
        }

        if ($corpo === null) {
            return response()->json($this->consulta->semResposta($numero, ConsultaTvlService::NAO_ENCONTRADO), 404);
        }

        return $this->json($corpo);
    }

    /**
     * @param  array<string, mixed>  $corpo
     */
    private function json(array $corpo): JsonResponse
    {
        $json = (string) json_encode($corpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
        $json = (string) preg_replace('/"'.preg_quote(ConsultaTvlService::MARCA_DECIMAL, '/').'(\d+\.\d{2})@@"/', '$1', $json);

        return JsonResponse::fromJsonString($json);
    }

    private function erro(string $numero, int $http): JsonResponse
    {
        return response()->json($this->consulta->semResposta($numero, ConsultaTvlService::ERRO), $http);
    }
}
