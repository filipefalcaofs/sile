<?php

namespace App\Services\Renovacao;

use App\Enums\AnalysisCategory;
use App\Enums\ViabilityRequestOrigin;
use App\Enums\ViabilityRequestStatus;
use App\Models\ServiceDeclaration;
use App\Models\ServiceDeclarationAcceptance;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Models\ViabilityServiceType;
use App\Services\Solicitacao\ProtocolNumberGenerator;
use App\Services\Solicitacao\ViabilityRequestStateMachine;
use App\Support\Audit\AuditService;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Abre e protocola a renovação de um TVL provisório. Copia endereço e
 * atividades da origem (não são editáveis) e manda o processo para análise
 * semi-expressa, sem passar pelo deferimento automático.
 */
class RenovacaoProtocoloService
{
    public function __construct(
        private RenovacaoElegibilidadeService $elegibilidade,
        private ProtocolNumberGenerator $numeros,
        private ViabilityRequestStateMachine $stateMachine,
        private AuditService $audit,
    ) {}

    /**
     * @param  array{email?: string|null, phone?: string|null, mobile?: string|null}  $contato
     * @param  list<int>  $declaracaoIds
     */
    public function protocolar(ViabilityRequest $origem, User $ator, array $contato, array $declaracaoIds): ViabilityRequest
    {
        $avaliacao = $this->elegibilidade->avaliar($origem);

        if (! $avaliacao->elegivel) {
            throw new DomainException($avaliacao->motivo ?? 'Esta viabilidade não pode ser renovada.');
        }

        $tipo = ViabilityServiceType::query()->where('code', 'renovacao-tvl')->first();

        if ($tipo === null) {
            throw new DomainException('O tipo de serviço Renovação de TVL não está cadastrado.');
        }

        $obrigatorias = ServiceDeclaration::vigentesPara($tipo->id)
            ->where('required', true);

        $aceitas = collect($declaracaoIds)->map(fn (int|string $id): int => (int) $id);

        if ($obrigatorias->contains(fn (ServiceDeclaration $declaracao): bool => ! $aceitas->contains($declaracao->id))) {
            throw new DomainException('Marque todas as declarações de responsabilidade para protocolar a renovação.');
        }

        $renovacao = DB::transaction(function () use ($origem, $ator, $contato, $tipo, $obrigatorias): ViabilityRequest {
            $origem->loadMissing(['cnaes', 'company']);

            $renovacao = ViabilityRequest::query()->create([
                'origin' => ViabilityRequestOrigin::Portal,
                'service_type_id' => $tipo->id,
                'renews_viability_request_id' => $origem->id,
                'company_id' => $origem->company_id,
                'requester_user_id' => $ator->id,
                'created_by_user_id' => $ator->id,
                'used_area_m2' => $origem->used_area_m2,
                'property_registration' => $origem->property_registration,
                'address_street' => $origem->address_street,
                'address_number' => $origem->address_number,
                'address_complement' => $origem->address_complement,
                'address_neighborhood' => $origem->address_neighborhood,
                'address_zip' => $origem->address_zip,
                'address_reference' => $origem->address_reference,
                'property_polygon_geojson' => $origem->property_polygon_geojson,
            ]);

            $renovacao->refresh();

            foreach ($origem->cnaes as $cnae) {
                $renovacao->cnaes()->attach($cnae->id, [
                    'is_primary' => (bool) $cnae->pivot->is_primary,
                ]);
            }

            foreach ($obrigatorias as $declaracao) {
                ServiceDeclarationAcceptance::query()->create([
                    'service_declaration_id' => $declaracao->id,
                    'viability_request_id' => $renovacao->id,
                    'user_id' => $ator->id,
                    'version' => $declaracao->version,
                    'accepted_at' => now(),
                ]);
            }

            $this->atualizarContato($origem, $contato);

            $renovacao->forceFill([
                'protocol_number' => $this->numeros->generate(),
                'protocoled_at' => now(),
            ])->save();

            $this->stateMachine->transition(
                $renovacao,
                ViabilityRequestStatus::Protocolada,
                $ator,
                publicLabel: ViabilityRequestStatus::Protocolada->publicLabel(),
            );

            $this->stateMachine->transition(
                $renovacao,
                ViabilityRequestStatus::EmAnalise,
                $ator,
                reason: 'Renovação de TVL provisório encaminhada à análise semi-expressa.',
                publicLabel: ViabilityRequestStatus::EmAnalise->publicLabel(),
            );

            $renovacao->forceFill([
                'analysis_category' => AnalysisCategory::SemiExpresso,
            ])->save();

            $this->audit->log(
                logName: 'solicitacoes',
                event: 'renovacao_protocolada',
                description: "Renovação do TVL da solicitação #{$origem->id} protocolada como #{$renovacao->id}",
                properties: [
                    'origem_id' => $origem->id,
                    'renovacao_id' => $renovacao->id,
                    'protocol_number' => $renovacao->protocol_number,
                    'mobile' => $contato['mobile'] ?? null,
                ],
                subject: $renovacao,
            );

            return $renovacao;
        });

        return $renovacao->fresh();
    }

    /**
     * @param  array{email?: string|null, phone?: string|null, mobile?: string|null}  $contato
     */
    private function atualizarContato(ViabilityRequest $origem, array $contato): void
    {
        $empresa = $origem->company;

        if ($empresa === null) {
            return;
        }

        $dados = [];

        if (filled($contato['email'] ?? null)) {
            $dados['email'] = $contato['email'];
        }

        if (filled($contato['phone'] ?? null)) {
            $dados['phone'] = $contato['phone'];
        }

        if ($dados !== []) {
            $empresa->fill($dados)->save();
        }
    }
}
