<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gestao\StoreGeoServerLayerRequest;
use App\Http\Requests\Gestao\UpdateGeoServerLayerRequest;
use App\Models\GeoServerLayer;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Manutenção do catálogo de camadas WFS do GeoServer SEDUR (parametrização
 * HU-014, item 3.2): dado administrável, não config de deploy — uma zona nova
 * da LOUOS entra por cadastro e passa a ser consultada pelo
 * GeoServerWfsZonaClient na próxima identificação (a linha criada NÃO é
 * fachada: o cliente consome qualquer linha ativa de verdade). O par
 * workspace+type_name é único e imutável na edição (padrão código/CNAE); a
 * desativação preserva o histórico (toggle, nunca exclui). Auditoria
 * automática via HasAuditoria no model (RN-002).
 */
class GeoServerLayerController extends Controller
{
    /**
     * Lista completa, sem paginação: o catálogo é pequeno (20 camadas oficiais
     * no seed) e a ordem de consulta é o dado relevante da tela.
     */
    public function index(): Response
    {
        $camadas = GeoServerLayer::query()
            ->orderBy('ordem')
            ->orderBy('id')
            ->get()
            ->map(fn (GeoServerLayer $camada) => [
                'id' => $camada->id,
                'workspace' => $camada->workspace,
                'type_name' => $camada->type_name,
                'nome_completo' => $camada->nomeCompleto(),
                'label' => $camada->label,
                'ordem' => $camada->ordem,
                'ativo' => $camada->ativo,
            ]);

        return Inertia::render('gestao/territorio/geoserver', [
            'camadas' => $camadas,
        ]);
    }

    public function store(StoreGeoServerLayerRequest $request): RedirectResponse
    {
        GeoServerLayer::create($request->validated());

        return back()->with('status', 'Camada do GeoServer cadastrada com sucesso.');
    }

    /**
     * Atualiza rótulo, ordem e situação. O par workspace+type_name é imutável
     * (não consta no UpdateRequest, logo o valor enviado é descartado —
     * padrão código/CNAE).
     */
    public function update(UpdateGeoServerLayerRequest $request, GeoServerLayer $geoServerLayer): RedirectResponse
    {
        $geoServerLayer->update($request->validated());

        return back()->with('status', 'Camada do GeoServer atualizada com sucesso.');
    }

    /**
     * Liga/desliga a camada. NUNCA exclui o registro: a desativação apenas a
     * retira do escopo ativos() que o cliente WFS consulta — o histórico e a
     * trilha de auditoria ficam preservados.
     */
    public function toggleActivation(GeoServerLayer $geoServerLayer): RedirectResponse
    {
        $geoServerLayer->update(['ativo' => ! $geoServerLayer->ativo]);

        $message = $geoServerLayer->ativo
            ? 'Camada reativada — volta a ser consultada na identificação da zona.'
            : 'Camada desativada — deixa de ser consultada na identificação da zona.';

        return back()->with('status', $message);
    }
}
