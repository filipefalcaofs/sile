<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gestao\StoreViaRequest;
use App\Http\Requests\Gestao\UpdateViaRequest;
use App\Models\Via;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Manutenção do cadastro de classes de via da LOUOS (relatório de usabilidade
 * SEDUR 19/09/2026, item 07 — aba de Vias além do quadro de Zona): dado
 * administrável (HU-014) cuja carga inicial vem da própria lei (ViaSeeder, a
 * partir do Quadro 11A vigente). A linha criada NÃO é fachada: a publicação
 * de rascunho do Quadro 11A (LouosDraftService::publicar) valida de verdade
 * contra este cadastro. O codigo é único e imutável na edição (padrão
 * código/CNAE); a desativação preserva o histórico (toggle, nunca exclui) e
 * só bloqueia NOVAS publicações. Auditoria automática via HasAuditoria no
 * model (RN-002).
 */
class ViaController extends Controller
{
    /**
     * Lista completa, sem paginação: o cadastro é pequeno (7 classes oficiais
     * no seed) e a ordenação alfabética por código é o dado relevante.
     */
    public function index(): Response
    {
        $vias = Via::query()
            ->orderBy('codigo')
            ->orderBy('id')
            ->get()
            ->map(fn (Via $via) => [
                'id' => $via->id,
                'codigo' => $via->codigo,
                'nome' => $via->nome,
                'ativo' => $via->ativo,
            ]);

        return Inertia::render('gestao/louos/vias', [
            'vias' => $vias,
        ]);
    }

    public function store(StoreViaRequest $request): RedirectResponse
    {
        Via::create($request->validated());

        return back()->with('status', 'Via cadastrada com sucesso.');
    }

    /**
     * Atualiza nome e situação. O codigo é imutável (não consta no
     * UpdateRequest, logo o valor enviado é descartado — padrão código/CNAE).
     */
    public function update(UpdateViaRequest $request, Via $via): RedirectResponse
    {
        $via->update($request->validated());

        return back()->with('status', 'Via atualizada com sucesso.');
    }

    /**
     * Liga/desliga a via. NUNCA exclui o registro: a desativação não toca os
     * quadros vigentes que a referenciam (histórico) — só bloqueia a
     * publicação de NOVOS rascunhos do Quadro 11A que a referenciem.
     */
    public function toggleActivation(Via $via): RedirectResponse
    {
        $via->update(['ativo' => ! $via->ativo]);

        $message = $via->ativo
            ? 'Via reativada — novas publicações do Quadro 11A podem referenciá-la.'
            : 'Via desativada — novas publicações do Quadro 11A que a referenciem serão bloqueadas. Os quadros vigentes são preservados.';

        return back()->with('status', $message);
    }
}
