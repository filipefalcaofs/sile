<?php

namespace App\Http\Controllers\Portal;

use App\Enums\IntencaoAtividade;
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
        $exclusoesIds = array_values(array_map('intval', (array) $request->validated('exclusoes', [])));

        // A intenção por CNAE (RN-AA-05b) só existe quando a solicitação É de
        // alteração de atividade econômica — primeiro estabelecimento e
        // renovação não declaram intenção por atividade (o pivot fica null).
        $isAlteracaoAtividade = $solicitacao->serviceType?->code === 'alteracao-atividade';

        $intencaoPara = function (int $id) use ($isAlteracaoAtividade, $exclusoesIds): ?string {
            if (! $isAlteracaoAtividade) {
                return null;
            }

            return in_array($id, $exclusoesIds, true)
                ? IntencaoAtividade::Excluir->value
                : IntencaoAtividade::Incluir->value;
        };

        DB::transaction(function () use ($request, $solicitacao, $primaryId, $complementaresIds, $intencaoPara) {
            $before = $solicitacao->cnaes()->get()->map(fn (Cnae $cnae) => [
                'code' => $cnae->code,
                'is_primary' => (bool) $cnae->pivot->is_primary,
                'intencao' => $cnae->pivot->intencao,
            ])->all();

            // Conjunto exato: o principal entra primeiro (is_primary=true) e os
            // complementares como secundários — um único sync garante a invariante.
            // A `intencao` precisa ir no MESMO payload do sync: ele substitui o
            // pivot inteiro, e uma chamada que só levasse is_primary apagaria a
            // intenção já gravada (armadilha conhecida deste projeto).
            $payload = [$primaryId => ['is_primary' => true, 'intencao' => $intencaoPara($primaryId)]];

            foreach ($complementaresIds as $id) {
                $payload[$id] = ['is_primary' => false, 'intencao' => $intencaoPara($id)];
            }

            $solicitacao->cnaes()->sync($payload);

            // Confirmação de perda da condição de sede (RN-AA-04): ausência da
            // chave no payload é "não perguntou desta vez", nunca "recusou" —
            // preserva o valor já gravado, o mesmo padrão já corrigido antes
            // neste projeto para outros campos de resposta do requerente.
            $solicitacao->confirma_perda_condicao_sede = $request->has('confirma_perda_condicao_sede')
                ? $request->boolean('confirma_perda_condicao_sede')
                : $solicitacao->confirma_perda_condicao_sede;

            // Pergunta vinculada (RN-EV-01), mesmo padrão de preservação:
            // ausência da chave é "este passo não perguntou desta vez", nunca
            // "respondeu não" — simétrico ao que SolicitacaoImovelController já
            // faz para o mesmo campo.
            $solicitacao->wants_virtual_office_hq = $request->has('wants_virtual_office_hq')
                ? $request->boolean('wants_virtual_office_hq')
                : $solicitacao->wants_virtual_office_hq;

            $solicitacao->save();

            // Mudou os CNAEs → a simulação anterior não vale mais (RN-005).
            $solicitacao->markSimulationStale();

            $after = [
                'principal' => Cnae::query()->whereKey($primaryId)->value('code'),
                'complementares' => Cnae::query()->whereIn('id', $complementaresIds)->orderBy('code')->pluck('code')->all(),
                // Simétrico ao `intencao` já presente em `$before` (achado M1
                // da revisão da Task 3): é a mudança de intenção que derruba
                // o vínculo das abrigadas, então ela precisa aparecer no
                // "depois" da auditoria, não só no "antes".
                'intencao' => $solicitacao->cnaes()->get()->mapWithKeys(fn (Cnae $cnae) => [
                    $cnae->code => $cnae->pivot->intencao,
                ])->all(),
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
