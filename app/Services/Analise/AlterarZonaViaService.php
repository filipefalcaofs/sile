<?php

namespace App\Services\Analise;

use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Solicitacao\SolicitacaoViabilityResolver;
use App\Support\Audit\AuditService;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Alteração manual da zona e da via do imóvel na ficha de análise. A prévia
 * mostra o que o motor decidiria com os novos valores, sem gravar; a alteração
 * grava a escolha, recalcula a ficha em edição e registra justificativa, quem
 * alterou e o antes e o depois no log e na Tramitação.
 */
class AlterarZonaViaService
{
    public function __construct(
        private SolicitacaoViabilityResolver $resolver,
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

    /**
     * @return array{antes: array{zona: ?string, via: ?string}, depois: array{zona: ?string, via: ?string}, motor_disponivel: bool, mudancas: list<array<string, mixed>>}
     */
    public function previa(ViabilityRequest $processo, ?string $zona, ?string $via): array
    {
        $antes = $this->atuais($processo);
        $depois = ['zona' => $this->texto($zona) ?? $antes['zona'], 'via' => $this->texto($via) ?? $antes['via']];

        try {
            $atual = $this->resolver->resolve($processo);
            $novo = $this->resolver->resolveComTerritorioManual($processo, $depois['zona'], $depois['via']);
        } catch (Throwable) {
            return ['antes' => $antes, 'depois' => $depois, 'motor_disponivel' => false, 'mudancas' => []];
        }

        $anteriores = collect($atual->por_cnae)->keyBy('cnae');

        $mudancas = collect($novo->por_cnae)
            ->map(function (array $item) use ($anteriores): array {
                $antigo = $anteriores->get($item['cnae']);

                return [
                    'cnae' => $item['cnae_formatado'],
                    'antes' => $antigo['tendencia_label'] ?? null,
                    'depois' => $item['tendencia_label'],
                    'motivo' => $item['veredito_motivo'] ?? null,
                    'mudou' => ($antigo['tendencia'] ?? null) !== $item['tendencia'],
                ];
            })
            ->values()
            ->all();

        return ['antes' => $antes, 'depois' => $depois, 'motor_disponivel' => true, 'mudancas' => $mudancas];
    }

    public function alterar(ViabilityRequest $processo, ?string $zona, ?string $via, string $justificativa, User $ator): void
    {
        $antes = $this->atuais($processo);
        $depois = ['zona' => $this->texto($zona) ?? $antes['zona'], 'via' => $this->texto($via) ?? $antes['via']];

        DB::transaction(function () use ($processo, $antes, $depois, $justificativa, $ator): void {
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
                    'justificativa' => $justificativa,
                    'ator_id' => $ator->id,
                    'ator_nome' => $ator->name,
                    'tela' => 'ficha-analise',
                ],
                subject: $processo,
            );
        });

        $ficha = $this->fichas->current($processo);
        $this->preAnalise->recalcularFicha($processo, $ficha);
    }

    private function texto(mixed $valor): ?string
    {
        $valor = trim((string) $valor);

        return $valor === '' ? null : $valor;
    }
}
