<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Models\ViabilityRequest;
use App\Services\Analise\EnviarParaAnaliseService;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tela T06 — Enviar processo de TVL para análise (relatório de teste SEDUR).
 * Gatilho MANUAL da retaguarda que leva um processo à fila da análise técnica.
 *
 * Regras fechadas (SEDUR): QUALQUER status pode ser enviado (OPEN-F-2 — sem
 * trava de status); vale para sede E abrigado (OPEN-F-3 — a tela não distingue
 * tipo); "não encontrado" é resposta de DOMÍNIO (404) com mensagem PARAMETRIZADA
 * (nunca 500); o envio é IDEMPOTENTE (CA-E-03 — não duplica a tramitação); e é
 * gated pela permissão dedicada enviar-tvl-analise (403 auditado no ponto único,
 * bootstrap/app.php). A regra e a auditoria (RN-002) vivem no
 * {@see EnviarParaAnaliseService}; aqui só a casca gated + o preview mínimo (PII
 * reduzida: protocolo, serviço, status e requerente).
 *
 * A tela respeita o toggle features.enviar_tvl_analise (HU-014): desligada, a
 * página comunica a indisponibilidade e as ações de pesquisar/enviar degradam de
 * forma honesta (404), sem falha silenciosa.
 */
class EnviarParaAnaliseController extends Controller
{
    public function __construct(private EnviarParaAnaliseService $service) {}

    /** Página (Inertia): campo de pesquisa + preview + confirmação. */
    public function index(): Response
    {
        return Inertia::render('gestao/processos/enviar-para-analise', [
            'featureEnabled' => $this->habilitada(),
            'mensagens' => [
                'naoEncontrado' => $this->mensagemNaoEncontrado(),
                'confirmacao' => $this->mensagemConfirmacao(),
            ],
        ]);
    }

    /**
     * Pesquisa o processo pelo número de protocolo. Encontrado → 200 com o
     * preview mínimo; inexistente → 404 (resposta de domínio) com a mensagem
     * PARAMETRIZADA (CA-E-01). Somente leitura — nada muda de estado.
     */
    public function pesquisar(Request $request): JsonResponse
    {
        abort_unless($this->habilitada(), 404);

        $processo = $this->encontrar($request);

        if ($processo === null) {
            return response()->json([
                'encontrado' => false,
                'mensagem' => $this->mensagemNaoEncontrado(),
            ], 404);
        }

        return response()->json([
            'encontrado' => true,
            'processo' => $this->preview($processo),
        ]);
    }

    /**
     * Envia o processo à análise após a confirmação. Idempotente: já em análise
     * → aviso "já está em análise" sem duplicar (CA-E-03). Inexistente → aviso de
     * domínio, sem mudança de estado (CA-E-01). Efetivado → status operacional +
     * auditoria (CA-E-02).
     */
    public function enviar(Request $request): RedirectResponse
    {
        abort_unless($this->habilitada(), 404);

        $processo = $this->encontrar($request);

        if ($processo === null) {
            return back()->with('error', $this->mensagemNaoEncontrado());
        }

        $enviado = $this->service->enviar($processo, $request->user());

        if (! $enviado) {
            return back()->with('status', "O processo {$processo->protocol_number} já está em análise — nada foi duplicado.");
        }

        return back()->with('status', "Processo {$processo->protocol_number} enviado para análise.");
    }

    /** Localiza o processo pelo protocolo informado (match exato, trim). */
    private function encontrar(Request $request): ?ViabilityRequest
    {
        $protocolo = trim($request->string('protocolo')->toString());

        if ($protocolo === '') {
            return null;
        }

        return ViabilityRequest::query()
            ->with(['serviceType:id,name', 'requester:id,name'])
            ->where('protocol_number', $protocolo)
            ->first();
    }

    /**
     * Preview mínimo do processo (PII reduzida): protocolo, serviço, status atual
     * e requerente. Expõe também se já está em análise (para o aviso idempotente).
     *
     * @return array<string, mixed>
     */
    private function preview(ViabilityRequest $processo): array
    {
        return [
            'id' => $processo->id,
            'protocolo' => $processo->protocol_number,
            'servico' => $processo->serviceType?->name,
            'status' => $processo->status->label(),
            'requerente' => $processo->requester?->name,
            'ja_em_analise' => $processo->analysis_status !== null,
            'situacao_analise' => $processo->analysis_status?->label(),
        ];
    }

    private function habilitada(): bool
    {
        return (bool) Settings::get(
            'features.enviar_tvl_analise',
            config('sile.features.enviar_tvl_analise', true),
        );
    }

    private function mensagemNaoEncontrado(): string
    {
        return (string) Settings::get(
            'analise.enviar_analise.mensagem_nao_encontrado',
            'Nenhum processo encontrado para o protocolo informado. Confira o número e tente novamente.',
        );
    }

    private function mensagemConfirmacao(): string
    {
        return (string) Settings::get(
            'analise.enviar_analise.mensagem_confirmacao',
            'Confirma o envio deste processo para a análise técnica? A ação é registrada na auditoria.',
        );
    }
}
