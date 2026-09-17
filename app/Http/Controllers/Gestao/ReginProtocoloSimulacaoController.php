<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gestao\SimularProtocoloReginRequest;
use App\Services\Regin\ReginProtocoloSimulacaoService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Homologação do motor com protocolos SEDUR: tipo de imóvel e área entram
 * como se tivessem chegado do REGIN. A tela declara a simulação — não finge
 * a integração.
 */
class ReginProtocoloSimulacaoController extends Controller
{
    public function __construct(private ReginProtocoloSimulacaoService $simulacao) {}

    public function index(): Response
    {
        return Inertia::render('gestao/risco/simulacao-regin', $this->baseProps());
    }

    public function simulate(SimularProtocoloReginRequest $request): Response
    {
        return Inertia::render('gestao/risco/simulacao-regin', [
            ...$this->baseProps(),
            'relatorio' => $this->simulacao->simular((string) $request->validated('codigo')),
        ]);
    }

    public function destroy(string $codigo): RedirectResponse
    {
        $this->simulacao->apagar($codigo);

        return redirect()
            ->route('gestao.risco.simulacao-regin')
            ->with('status', 'Resultado da simulação apagado. Pode rodar de novo.');
    }

    /**
     * @return array{protocolos: list<array<string, mixed>>, aviso: string, relatorio: ?array<string, mixed>, execucoes: list<array<string, mixed>>}
     */
    private function baseProps(): array
    {
        return [
            'protocolos' => $this->simulacao->listar(),
            'aviso' => ReginProtocoloSimulacaoService::AVISO,
            'relatorio' => $this->simulacao->ultima(),
            'execucoes' => $this->simulacao->execucoes(),
        ];
    }
}
