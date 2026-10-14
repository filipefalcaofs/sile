<?php

namespace App\Services\Motor;

use App\Enums\EtapaMotor;
use App\Enums\SituacaoEtapa;
use App\Jobs\DecidirFluxoExpressoJob;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Expresso\FluxoExpressoService;
use App\Services\Geo\PoligonoEnderecamentoService;
use App\Services\Geo\TerritorioProcessoService;
use App\Services\Realty\LocalizacaoInscricao;
use App\Services\Solicitacao\ProtocolarSolicitacaoService;
use App\Support\Audit\AuditService;

/**
 * Refaz as etapas do motor que falharam por serviço indisponível, na ordem, e
 * reavalia o veredito. Para na primeira etapa que continua indisponível: sem a
 * localização pela inscrição as demais não têm o que consultar.
 */
class ReprocessarEtapasService
{
    public function __construct(
        private TerritorioProcessoService $territorio,
        private ProtocolarSolicitacaoService $protocolo,
        private FluxoExpressoService $expresso,
        private AuditService $audit,
        private PoligonoEnderecamentoService $poligono,
    ) {}

    public function reprocessar(ViabilityRequest $request, ?User $actor = null): ReprocessoResultado
    {
        $pendentes = $request->etapas()
            ->where('situacao', SituacaoEtapa::Indisponivel->value)
            ->get()
            ->sortBy(fn ($etapa) => $etapa->etapa->ordem());

        // Processo anterior ao registro de etapas: nunca gravou nenhuma. Reprocessar
        // por pedido explícito refaz o passe completo e já deixa as etapas gravadas.
        $anteriorAoRegistro = $pendentes->isEmpty()
            && $request->etapas()->doesntExist()
            && $this->expresso->podeReavaliar($request);

        if ($pendentes->isEmpty() && ! $anteriorAoRegistro) {
            return new ReprocessoResultado(false, null, $request->status->value, $this->semEtapaParaReprocessar($request));
        }

        $paradaEm = null;

        $precisaLocalizar = $anteriorAoRegistro
            ? $request->pontoDoImovel() === null && filled($request->property_registration)
            : $pendentes->contains(fn ($etapa) => $etapa->etapa === EtapaMotor::LocalizacaoInscricao);

        if ($precisaLocalizar
            && $this->territorio->localizarPelaInscricao($request) === LocalizacaoInscricao::INDISPONIVEL) {
            $paradaEm = EtapaMotor::LocalizacaoInscricao;
        }

        $decisaoAgendada = false;

        if ($paradaEm === null) {
            $this->protocolo->completarTerritorio($request);

            // Sem polígono e com ponto, a decisão espera a busca do lote pelo
            // ponto (consulta lenta): vai para a fila em vez de decidir agora
            // pelo ponto. Com polígono, segue síncrona como sempre.
            if ($this->poligono->precisaConsultarPorPonto($request) && $this->expresso->podeReavaliar($request)) {
                DecidirFluxoExpressoJob::dispatch($request->id, reavaliacao: true, atorId: $actor?->id);
                $decisaoAgendada = true;
            } else {
                $this->expresso->reavaliar($request, $actor);
            }
        }

        $request->refresh();

        $mensagem = match (true) {
            $paradaEm !== null => "Serviço de {$paradaEm->rotulo()} continua indisponível. Nova tentativa agendada.",
            $decisaoAgendada => 'Etapas reprocessadas. O polígono do lote está sendo buscado pelo ponto; o veredito sai em seguida.',
            default => 'Etapas reprocessadas e veredito reavaliado.',
        };

        $this->audit->log(
            logName: 'motor',
            event: 'reprocesso',
            description: "Reprocesso das etapas da solicitação #{$request->id}",
            properties: [
                'viability_request_id' => $request->id,
                'ator_id' => $actor?->id,
                'parada_em' => $paradaEm?->value,
                'status_do_processo' => $request->status->value,
                'decisao_agendada' => $decisaoAgendada,
            ],
            subject: $request,
            result: $paradaEm === null ? 'sucesso' : 'parcial',
        );

        return new ReprocessoResultado(true, $paradaEm, $request->status->value, $mensagem);
    }

    private function semEtapaParaReprocessar(ViabilityRequest $request): string
    {
        if ($request->etapas()->doesntExist() && ! $this->expresso->podeReavaliar($request)) {
            return 'O processo já foi decidido ou a análise já foi assumida: não há o que reprocessar.';
        }

        return 'Nenhuma etapa indisponível a reprocessar.';
    }
}
