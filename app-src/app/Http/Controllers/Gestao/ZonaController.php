<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gestao\StoreZonaRequest;
use App\Http\Requests\Gestao\UpdateZonaRequest;
use App\Models\Zona;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Manutenção do cadastro de zonas urbanísticas da LOUOS (parametrização
 * 3.3): dado administrável (HU-014) cuja carga inicial vem da própria lei
 * (ZonaSeeder, a partir do Quadro 10 vigente). A linha criada NÃO é fachada:
 * a publicação de rascunho do Quadro 10 (LouosDraftService::publicar)
 * valida de verdade contra este cadastro — uma zona nova da lei entra por
 * cadastro e passa a permitir publicações que a referenciam. O codigo é
 * único e imutável na edição (padrão código/CNAE); a desativação preserva
 * o histórico (toggle, nunca exclui) e só bloqueia NOVAS publicações.
 * Auditoria automática via HasAuditoria no model (RN-002).
 */
class ZonaController extends Controller
{
    /**
     * Lista completa, sem paginação: o cadastro é pequeno (21 zonas oficiais
     * no seed) e a ordenação alfabética por código é o dado relevante.
     */
    public function index(): Response
    {
        $zonas = Zona::query()
            ->orderBy('codigo')
            ->orderBy('id')
            ->get()
            ->map(fn (Zona $zona) => [
                'id' => $zona->id,
                'codigo' => $zona->codigo,
                'nome' => $zona->nome,
                'macrozona' => $zona->macrozona,
                'ativo' => $zona->ativo,
            ]);

        return Inertia::render('gestao/louos/zonas', [
            'zonas' => $zonas,
        ]);
    }

    public function store(StoreZonaRequest $request): RedirectResponse
    {
        Zona::create($request->validated());

        return back()->with('status', 'Zona cadastrada com sucesso.');
    }

    /**
     * Atualiza nome, macrozona e situação. O codigo é imutável (não consta
     * no UpdateRequest, logo o valor enviado é descartado — padrão
     * código/CNAE).
     */
    public function update(UpdateZonaRequest $request, Zona $zona): RedirectResponse
    {
        $zona->update($request->validated());

        return back()->with('status', 'Zona atualizada com sucesso.');
    }

    /**
     * Liga/desliga a zona. NUNCA exclui o registro: a desativação não toca
     * os quadros vigentes que a referenciam (histórico) — só bloqueia a
     * publicação de NOVOS rascunhos do Quadro 10 que a referenciem.
     */
    public function toggleActivation(Zona $zona): RedirectResponse
    {
        $zona->update(['ativo' => ! $zona->ativo]);

        $message = $zona->ativo
            ? 'Zona reativada — novas publicações do Quadro 10 podem referenciá-la.'
            : 'Zona desativada — novas publicações do Quadro 10 que a referenciem serão bloqueadas. Os quadros vigentes são preservados.';

        return back()->with('status', $message);
    }
}
