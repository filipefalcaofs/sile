<?php

namespace App\Http\Controllers\Portal;

use App\Enums\ResultadoViabilidade;
use App\Http\Controllers\Controller;
use App\Models\ViabilityQuery;
use App\Support\Audit\AuditService;
use App\Support\Settings;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Histórico autenticado da consulta prévia de viabilidade (HU-060): lista
 * server-driven, paginada e ordenada da mais recente, das consultas do PRÓPRIO
 * usuário (escopo forUser — precedente "Minhas empresas"). A consulta a dado do
 * próprio usuário é auditada (RN-002, precedente do histórico de acessos). Cada
 * item carrega o snapshot completo (result) para a reprodução na UI (07-09).
 */
class HistoricoConsultaController extends Controller
{
    public function __construct(private AuditService $audit) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        $consultas = ViabilityQuery::forUser($user)
            ->latest('created_at')
            ->latest('id')
            ->paginate((int) Settings::get('ui.companies.per_page', 15))
            ->through(fn (ViabilityQuery $query) => [
                'id' => $query->id,
                'entry_type' => $query->entry_type,
                'resultado' => $query->resultado,
                'resultado_label' => $query->resultado !== null
                    ? ResultadoViabilidade::from($query->resultado)->label()
                    : null,
                'input' => $query->input,
                'created_at' => $query->created_at->toIso8601String(),
                'result' => $query->result,
            ]);

        $this->audit->log(
            'viabilidade',
            'consulta-historico',
            'Consulta do histórico de viabilidade',
            ['itens' => $consultas->total()],
            result: 'sucesso',
        );

        return Inertia::render('portal/viabilidade/historico', [
            'consultas' => $consultas,
        ]);
    }
}
