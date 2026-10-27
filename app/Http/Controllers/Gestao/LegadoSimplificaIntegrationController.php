<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Services\Sefaz\LegadoConexaoTester;
use App\Services\Sefaz\LegadoSimplificaAjustes;
use App\Support\Audit\AuditService;
use Illuminate\Http\RedirectResponse;

class LegadoSimplificaIntegrationController extends Controller
{
    public function __construct(
        private AuditService $audit,
        private LegadoSimplificaAjustes $ajustes,
    ) {}

    public function test(LegadoConexaoTester $tester): RedirectResponse
    {
        $resultado = $tester->testar();

        $this->audit->log(
            'config-legado-simplifica',
            'testar',
            'Teste de conexão da API do legado Simplifica',
            ['url' => $this->ajustes->url()],
            result: $resultado->ok ? 'sucesso' : 'falha',
        );

        if (! $resultado->ok) {
            return back()->withErrors(['legado_connection' => $resultado->mensagem]);
        }

        return back()->with('status', $resultado->mensagem);
    }
}
