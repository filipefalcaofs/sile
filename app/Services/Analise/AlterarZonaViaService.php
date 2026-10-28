<?php

namespace App\Services\Analise;

use App\Models\User;
use App\Models\ViabilityRequest;
use App\Support\Audit\AuditService;
use Illuminate\Support\Facades\DB;

/**
 * Alteração manual da zona e da via do imóvel na ficha de análise: grava a
 * escolha do analista, recalcula a ficha em edição e registra quem alterou e o
 * antes e o depois no log e na Tramitação.
 */
class AlterarZonaViaService
{
    public function __construct(
        private PreAnaliseService $preAnalise,
        private AnalysisRecordService $fichas,
        private AuditService $audit,
    ) {}

    /**
     * Zona e via que valem hoje para o processo: a escolha manual ou a calculada.
     *
     * @return array{zona: ?string, via: ?string}
     */
    public function atuais(ViabilityRequest $processo): array
    {
        return [
            'zona' => $this->texto($processo->zona_manual) ?? $this->texto($processo->zona_codigo),
            'via' => $this->texto($processo->via_manual) ?? $this->texto($processo->camadas_incidentes['via'] ?? null),
        ];
    }

    public function alterar(ViabilityRequest $processo, ?string $zona, ?string $via, User $ator): void
    {
        $antes = $this->atuais($processo);
        $depois = ['zona' => $this->texto($zona) ?? $antes['zona'], 'via' => $this->texto($via) ?? $antes['via']];

        DB::transaction(function () use ($processo, $antes, $depois, $ator): void {
            $processo->forceFill(['zona_manual' => $depois['zona'], 'via_manual' => $depois['via']])->save();

            $this->audit->log(
                logName: 'analise',
                event: 'zona-via-alterada',
                description: "Zona e via do processo #{$processo->id} alteradas na ficha de análise",
                properties: [
                    'viability_request_id' => $processo->id,
                    'protocol_number' => $processo->protocol_number,
                    'antes' => $antes,
                    'depois' => $depois,
                    'ator_id' => $ator->id,
                    'ator_nome' => $ator->name,
                    'tela' => 'ficha-analise',
                ],
                subject: $processo,
            );
        });

        $ficha = $this->fichas->current($processo, $ator);
        $this->preAnalise->recalcularFicha($processo, $ficha);
    }

    private function texto(mixed $valor): ?string
    {
        $valor = trim((string) $valor);

        return $valor === '' ? null : $valor;
    }
}
