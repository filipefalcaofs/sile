<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Models\IntegrationCall;
use App\Support\Audit\AuditService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IntegrationCallController extends Controller
{
    public function __construct(private AuditService $audit) {}

    public function index(Request $request): Response
    {
        $direcao = $this->opcao($request->query('direcao'), ['entrada', 'saida']);
        $resultado = $this->opcao($request->query('resultado'), ['sucesso', 'erro']);
        $integracao = $this->integracao($request->query('integracao'));

        $chamadas = IntegrationCall::query()
            ->when($direcao !== null, fn ($query) => $query->where('direction', $direcao))
            ->when($resultado !== null, fn ($query) => $query->where('outcome', $resultado))
            ->when($integracao !== null, fn ($query) => $query->where('integration', $integracao))
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (IntegrationCall $chamada): array => [
                'id' => $chamada->id,
                'created_at' => $chamada->created_at?->toIso8601String(),
                'direcao' => $chamada->direction,
                'direcao_rotulo' => $chamada->direction === 'entrada' ? 'Entrada' : 'Saída',
                'integracao' => $chamada->integration,
                'integracao_rotulo' => IntegrationCall::rotulo($chamada->integration),
                'method' => $chamada->method,
                'url' => $chamada->url,
                'http_status' => $chamada->http_status,
                'resultado' => $chamada->outcome,
                'duracao_ms' => $chamada->duration_ms,
                'erro' => $chamada->error_message,
            ]);

        $this->audit->log(
            logName: 'auditoria',
            event: 'consulta-integracoes',
            description: 'Consulta do log de integrações externas',
            properties: array_filter([
                'direcao' => $direcao,
                'resultado' => $resultado,
                'integracao' => $integracao,
            ]),
            personalData: true,
        );

        return Inertia::render('gestao/auditoria/integracoes/index', [
            'chamadas' => $chamadas,
            'filtros' => [
                'direcao' => $direcao,
                'resultado' => $resultado,
                'integracao' => $integracao,
            ],
            'integracoes' => IntegrationCall::query()
                ->select('integration')
                ->distinct()
                ->orderBy('integration')
                ->pluck('integration')
                ->map(fn (string $slug): array => [
                    'value' => $slug,
                    'label' => IntegrationCall::rotulo($slug),
                ])
                ->values(),
        ]);
    }

    public function show(IntegrationCall $integrationCall): Response
    {
        $this->audit->log(
            logName: 'auditoria',
            event: 'consulta-integracao',
            description: 'Consulta do detalhe de uma chamada de integração',
            properties: [
                'integration_call_id' => $integrationCall->id,
                'integracao' => $integrationCall->integration,
                'direcao' => $integrationCall->direction,
            ],
            personalData: true,
        );

        return Inertia::render('gestao/auditoria/integracoes/show', [
            'chamada' => [
                'id' => $integrationCall->id,
                'created_at' => $integrationCall->created_at?->toIso8601String(),
                'direcao' => $integrationCall->direction,
                'direcao_rotulo' => $integrationCall->direction === 'entrada' ? 'Entrada' : 'Saída',
                'integracao' => $integrationCall->integration,
                'integracao_rotulo' => IntegrationCall::rotulo($integrationCall->integration),
                'method' => $integrationCall->method,
                'url' => $integrationCall->url,
                'http_status' => $integrationCall->http_status,
                'resultado' => $integrationCall->outcome,
                'duracao_ms' => $integrationCall->duration_ms,
                'erro' => $integrationCall->error_message,
                'truncado' => $integrationCall->truncated,
                'correlacao' => $integrationCall->correlation_id,
                'ip' => $integrationCall->ip_address,
                'agente' => $integrationCall->user_agent,
                'cabecalhos_pedido' => $integrationCall->request_headers,
                'corpo_pedido' => $integrationCall->request_body,
                'cabecalhos_resposta' => $integrationCall->response_headers,
                'corpo_resposta' => $integrationCall->response_body,
            ],
        ]);
    }

    /**
     * @param  list<string>  $permitidos
     */
    private function opcao(mixed $valor, array $permitidos): ?string
    {
        return is_string($valor) && in_array($valor, $permitidos, true) ? $valor : null;
    }

    private function integracao(mixed $valor): ?string
    {
        if (! is_string($valor) || ! preg_match('/^[a-z0-9._-]{1,120}$/', $valor)) {
            return null;
        }

        return $valor;
    }
}
