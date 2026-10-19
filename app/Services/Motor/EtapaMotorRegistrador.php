<?php

namespace App\Services\Motor;

use App\Enums\EtapaMotor;
use App\Enums\SituacaoEtapa;
use App\Models\ViabilityRequest;
use App\Models\ViabilityRequestEtapa;
use App\Support\Audit\AuditService;
use Illuminate\Support\Str;

/**
 * Grava o resultado de uma etapa do motor. Falha transitória agenda a próxima
 * tentativa (15 min nas primeiras, 60 min depois) e esgota 24 h após a primeira
 * falha; qualquer outra situação limpa a agenda.
 */
class EtapaMotorRegistrador
{
    public function __construct(private AuditService $audit) {}

    public function registrar(
        ViabilityRequest $request,
        EtapaMotor $etapa,
        SituacaoEtapa $situacao,
        ?string $motivo = null,
    ): ViabilityRequestEtapa {
        $agora = now();

        $linha = ViabilityRequestEtapa::query()->firstOrNew([
            'viability_request_id' => $request->id,
            'etapa' => $etapa->value,
        ]);

        $linha->servico = $etapa->servico();
        $linha->situacao = $situacao;
        $linha->tentativas = ((int) $linha->tentativas) + 1;
        $linha->ultima_tentativa_em = $agora;

        if ($situacao === SituacaoEtapa::Indisponivel) {
            $linha->motivo = $this->limitar($motivo);
            $linha->primeira_falha_em ??= $agora;

            $proxima = $agora->copy()->addMinutes($this->intervaloEmMinutos($linha->tentativas));
            $fimDaJanela = $linha->primeira_falha_em->copy()->addHours((int) config('viabiliza.reprocessamento.janela_horas'));

            if ($proxima->greaterThan($fimDaJanela)) {
                $linha->esgotada_em = $agora;
                $linha->proxima_tentativa_em = null;
            } else {
                $linha->esgotada_em = null;
                $linha->proxima_tentativa_em = $proxima;
            }
        } else {
            $linha->motivo = $situacao === SituacaoEtapa::Ok ? null : $this->limitar($motivo);
            $linha->primeira_falha_em = null;
            $linha->proxima_tentativa_em = null;
            $linha->esgotada_em = null;
        }

        $linha->save();

        $this->audit->log(
            logName: 'motor',
            event: 'etapa',
            description: "Etapa {$etapa->value} da solicitação #{$request->id}: {$situacao->value}",
            properties: [
                'viability_request_id' => $request->id,
                'etapa' => $etapa->value,
                'servico' => $etapa->servico(),
                'situacao' => $situacao->value,
                'tentativas' => $linha->tentativas,
            ],
            subject: $request,
            result: $situacao->value,
        );

        return $linha;
    }

    private function limitar(?string $motivo): ?string
    {
        return $motivo !== null ? Str::limit($motivo, 500, '') : null;
    }

    private function intervaloEmMinutos(int $tentativas): int
    {
        return $tentativas <= (int) config('viabiliza.reprocessamento.tentativas_iniciais')
            ? (int) config('viabiliza.reprocessamento.intervalo_inicial_min')
            : (int) config('viabiliza.reprocessamento.intervalo_posterior_min');
    }
}
