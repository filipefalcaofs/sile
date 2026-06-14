<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Cnae;
use App\Models\ViabilityRequest;
use App\Services\Solicitacao\TimelineSolicitacao;
use App\Support\Audit\AuditService;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Consulta AUTENTICADA do protocolo (HU-069): o dono/representado acompanha o
 * andamento com a timeline em linguagem simples (publicLabel), o prazo estimado
 * com ressalva honesta e os dados do processo. Gera também o link PÚBLICO
 * assinado de acompanhamento (TTL parametrizável, Task 3). Toda consulta é
 * auditada (RN-002). Autorização pela ViabilityRequestPolicy::view (dono/
 * representado, qualquer status — CA-04 para terceiro 403 auditado).
 */
class ConsultaProtocoloController extends Controller
{
    public function __construct(
        private TimelineSolicitacao $timeline,
        private AuditService $audit,
    ) {}

    public function show(Request $request, ViabilityRequest $solicitacao): Response
    {
        Gate::authorize('view', $solicitacao);

        $solicitacao->load(['company', 'serviceType', 'cnaes']);

        $this->audit->log(
            'solicitacoes',
            'consulta-protocolo',
            "Consulta autenticada do protocolo da solicitação #{$solicitacao->id}",
            properties: [
                'viability_request_id' => $solicitacao->id,
                'protocol_number' => $solicitacao->protocol_number,
            ],
            subject: $solicitacao,
        );

        return Inertia::render('portal/solicitacoes/protocolo', [
            'solicitacao' => $this->payload($solicitacao),
            'timeline' => $this->timeline->build($solicitacao, publico: false),
            'publicLink' => $this->publicLink($solicitacao),
        ]);
    }

    /**
     * Link PÚBLICO assinado de acompanhamento (HU-069): TTL parametrizável
     * (solicitacao.consulta_publica.assinatura_ttl_dias), efeito sem deploy. Só
     * faz sentido para solicitações já protocoladas (têm número rastreável); em
     * rascunho ainda não há protocolo a compartilhar.
     */
    private function publicLink(ViabilityRequest $solicitacao): ?string
    {
        if ($solicitacao->protocol_number === null) {
            return null;
        }

        $dias = (int) Settings::get(
            'solicitacao.consulta_publica.assinatura_ttl_dias',
            config('sile.solicitacao.consulta_publica.assinatura_ttl_dias', 30),
        );

        return URL::temporarySignedRoute(
            'portal.protocolo.publico',
            now()->addDays($dias),
            ['solicitacao' => $solicitacao->id],
        );
    }

    /**
     * Dados do processo para o dono (visão completa — é a própria solicitação).
     *
     * @return array<string, mixed>
     */
    private function payload(ViabilityRequest $solicitacao): array
    {
        return [
            'id' => $solicitacao->id,
            'protocol_number' => $solicitacao->protocol_number,
            'status' => [
                'value' => $solicitacao->status->value,
                'label' => $solicitacao->status->label(),
                'public_label' => $solicitacao->status->publicLabel(),
            ],
            'protocoled_at' => $solicitacao->protocoled_at?->toIso8601String(),
            'created_at' => $solicitacao->created_at?->toIso8601String(),
            'service_type' => $solicitacao->serviceType?->name,
            'company' => $solicitacao->company ? [
                'legal_name' => $solicitacao->company->legal_name,
                'formatted_cnpj' => $solicitacao->company->formatted_cnpj,
            ] : null,
            'used_area_m2' => $solicitacao->used_area_m2,
            'address' => [
                'street' => $solicitacao->address_street,
                'number' => $solicitacao->address_number,
                'neighborhood' => $solicitacao->address_neighborhood,
                'reference' => $solicitacao->address_reference,
            ],
            'cnaes' => $solicitacao->cnaes
                ->sortByDesc(fn (Cnae $cnae) => (bool) $cnae->pivot->is_primary)
                ->values()
                ->map(fn (Cnae $cnae) => [
                    'formatted_code' => $cnae->formatted_code,
                    'description' => $cnae->description,
                    'is_primary' => (bool) $cnae->pivot->is_primary,
                ])
                ->all(),
        ];
    }
}
