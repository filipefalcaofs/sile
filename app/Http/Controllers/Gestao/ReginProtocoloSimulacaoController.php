<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gestao\SimularProtocoloReginRequest;
use App\Services\Regin\ReginProtocoloSimulacaoService;
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

    /**
     * @return array{protocolos: list<array<string, mixed>>, aviso: string, relatorio: null}
     */
    private function baseProps(): array
    {
        return [
            'protocolos' => $this->simulacao->listar(),
            'aviso' => ReginProtocoloSimulacaoService::AVISO,
            'relatorio' => null,
        ];
    }
}
