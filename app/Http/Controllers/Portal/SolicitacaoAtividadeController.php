<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\UpdateSolicitacaoAtividadesRequest;
use App\Models\Cnae;
use App\Models\ViabilityRequest;
use App\Support\Audit\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Atividade principal (HU-064) e CNAEs complementares (HU-065) da solicitação.
 *
 * A escrita do pivot viability_request_cnaes é transacional e espelha o
 * CompanyCnaeService da Fase 3: um único sync com o conjunto EXATO (principal
 * is_primary=true + complementares is_primary=false) garante "exatamente um
 * principal" e a unicidade (request, cnae) na aplicação. Mudar a lista de
 * CNAEs invalida a simulação orientativa anterior (HU-063 RN-005) e é auditado
 * explicitamente (RN-002), coexistindo com o updated automático do HasAuditoria.
 */
class SolicitacaoAtividadeController extends Controller
{
    public function __construct(private AuditService $audit) {}

    public function update(UpdateSolicitacaoAtividadesRequest $request, ViabilityRequest $solicitacao): RedirectResponse
    {
        Gate::authorize('update', $solicitacao);

        $primaryId = (int) $request->validated('principal_cnae_id');
        $complementaresIds = array_values(array_map('intval', (array) $request->validated('complementares', [])));

        DB::transaction(function () use ($solicitacao, $primaryId, $complementaresIds) {
            $before = $solicitacao->cnaes()->get()->map(fn (Cnae $cnae) => [
                'code' => $cnae->code,
                'is_primary' => (bool) $cnae->pivot->is_primary,
            ])->all();

            // Conjunto exato: o principal entra primeiro (is_primary=true) e os
            // complementares como secundários — um único sync garante a invariante.
            $payload = [$primaryId => ['is_primary' => true]];

            foreach ($complementaresIds as $id) {
                $payload[$id] = ['is_primary' => false];
            }

            $solicitacao->cnaes()->sync($payload);

            // Mudou os CNAEs → a simulação anterior não vale mais (RN-005).
            $solicitacao->markSimulationStale();

            $after = [
                'principal' => Cnae::query()->whereKey($primaryId)->value('code'),
                'complementares' => Cnae::query()->whereIn('id', $complementaresIds)->orderBy('code')->pluck('code')->all(),
            ];

            $this->audit->log('solicitacoes', 'solicitacao-cnaes', 'Atividades da solicitação atualizadas', [
                'solicitacao_id' => $solicitacao->id,
                'antes' => $before,
                'depois' => $after,
            ], $solicitacao);
        });

        return back()->with('status', 'Atividades da solicitação atualizadas com sucesso.');
    }
}
