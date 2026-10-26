<?php

namespace App\Http\Controllers\Portal;

use App\Enums\AnalysisPendencyStatus;
use App\Http\Controllers\Controller;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Services\Legado\LegadoIndisponivelException;
use App\Services\Solicitacao\ConsultaProcessoLegado;
use App\Services\Solicitacao\ConsultaProcessoPorCnpj;
use App\Services\Solicitacao\LocalizadorProcesso;
use App\Services\Solicitacao\TimelineSolicitacao;
use App\Support\Audit\AuditService;
use App\Support\Representation\CurrentRepresentation;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Consulta de processo pelo número, para qualquer cidadão logado, no
 * Viabiliza e, sem resultado nele, no sistema anterior (Simplifica). Mostra o
 * que um extrato de andamento mostraria: números, empresa, situação e a
 * tramitação pública. Nada de CPF, contato, endereço, texto do convite ou
 * parecer. O botão de responder convite só vai para o requerente (ou o
 * solicitante declarado do REGIN) com convite aberto no prazo.
 */
class ConsultaProcessoController extends Controller
{
    public function __construct(
        private LocalizadorProcesso $localizador,
        private TimelineSolicitacao $timeline,
        private AuditService $audit,
        private ConsultaProcessoLegado $legado,
        private ConsultaProcessoPorCnpj $porCnpj,
    ) {}

    public function __invoke(Request $request): Response
    {
        $numero = $request->query('numero');
        $numero = is_string($numero) && trim($numero) !== '' ? trim($numero) : null;
        $cnpj = ConsultaProcessoPorCnpj::cnpj($numero);

        // CNPJ: lista os processos da empresa nos dois sistemas.
        if ($cnpj !== null) {
            $resultado = $this->porCnpj->listar($cnpj);
            $this->audit->log(
                'solicitacoes',
                'consulta-processo-cnpj',
                'Consulta dos processos de um CNPJ no portal',
                properties: ['cnpj' => $cnpj, 'itens' => count($resultado['lista'])],
            );

            return Inertia::render('portal/consulta/index', [
                'numero' => $numero,
                'encontrado' => $resultado['lista'] !== [],
                'legado_indisponivel' => $resultado['legado_indisponivel'],
                'processo' => null,
                'timeline' => null,
                'lista' => $resultado['lista'] === [] ? null : $resultado['lista'],
            ]);
        }

        $processo = $this->localizador->localizar($numero);

        if ($processo !== null) {
            $this->audit->log(
                'solicitacoes',
                'consulta-processo',
                "Consulta do processo #{$processo->id} pelo número no portal",
                properties: ['viability_request_id' => $processo->id, 'numero' => $this->localizador->normalizar($numero)],
                subject: $processo,
            );
        }

        $legado = null;
        $legadoIndisponivel = false;
        $normalizado = $this->localizador->normalizar($numero);

        // Sem processo no Viabiliza, o número pode ser do sistema anterior.
        if ($processo === null && $normalizado !== null && ConsultaProcessoLegado::ehNumeroDoLegado($normalizado)) {
            try {
                $legado = $this->legado->localizar($normalizado);
            } catch (LegadoIndisponivelException) {
                $legadoIndisponivel = true;
            }

            if ($legado !== null) {
                $this->audit->log(
                    'solicitacoes',
                    'consulta-processo-legado',
                    "Consulta do processo {$legado['processo']['protocol_number']} do sistema anterior no portal",
                    properties: ['numero' => $normalizado],
                );
            }
        }

        return Inertia::render('portal/consulta/index', [
            'numero' => $numero,
            'encontrado' => $processo !== null || $legado !== null,
            'legado_indisponivel' => $legadoIndisponivel,
            'processo' => $processo !== null ? $this->resumo($request, $processo) : $legado['processo'] ?? null,
            'timeline' => $processo !== null ? $this->timeline->build($processo, publico: true) : $legado['timeline'] ?? null,
            'lista' => null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function resumo(Request $request, ViabilityRequest $processo): array
    {
        $processo->loadMissing(['company', 'serviceType', 'decision']);

        $convite = $processo->pendencies()
            ->where('status', AnalysisPendencyStatus::Aberta)
            ->where(fn ($query) => $query->whereNull('due_at')->orWhere('due_at', '>', now()))
            ->orderBy('due_at')
            ->first();

        $efetivo = app(CurrentRepresentation::class)->effectiveUser($request->user());
        $podeResponder = $convite !== null && ViabilityRequest::query()
            ->whereKey($processo->id)
            ->respondivelPor($efetivo->id, $efetivo->cpf)
            ->exists();

        return [
            'origem' => 'viabiliza',
            'protocol_number' => $processo->protocol_number,
            'numero_viabilidade' => ViabilityDecision::semPrefixo($processo->decision?->tvl_product_number),
            'bap' => $processo->external_reference,
            'servico' => $processo->serviceType?->name,
            'empresa' => $processo->company === null ? null : [
                'razao_social' => $processo->company->legal_name,
                'cnpj' => $processo->company->formatted_cnpj,
            ],
            'protocolado_em' => $processo->protocoled_at?->toIso8601String(),
            'status' => [
                'value' => $processo->status->value,
                'public_label' => $processo->status->publicLabel(),
            ],
            'convite' => [
                'aberto' => $convite !== null,
                'prazo' => $convite?->due_at?->toIso8601String(),
            ],
            'pode_responder' => $podeResponder,
            'solicitacao_id' => $podeResponder ? $processo->id : null,
        ];
    }
}
