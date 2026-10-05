<?php

namespace App\Services\Relatorios;

use App\Models\AnalysisPendency;
use App\Models\ViabilityRequest;
use App\Services\Expresso\BusinessDeadlineCalculator;
use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * Tempo de viabilidade de um processo na régua federal.
 *
 * A REDESIM publica o tempo de viabilidade de endereço como "quantidade de
 * horas úteis entre o início e o fim da análise, não considerando fins de
 * semana ou feriados", e retira do cálculo os períodos em que o processo
 * aguarda providência do cidadão. Este serviço transcreve essa definição para
 * o VIABILIZA: sem ela, comparar o nosso tempo com o ranking nacional seria
 * comparar grandezas diferentes.
 *
 * Um dia útil vale 24 horas — a régua desconta fim de semana e feriado, não
 * expediente comercial.
 */
class TempoViabilidadeCalculator
{
    public function __construct(
        private readonly BusinessDeadlineCalculator $prazos,
    ) {}

    /**
     * Horas úteis entre o protocolo e a decisão, descontado o tempo com o
     * cidadão. Processo sem protocolo ou sem decisão não tem tempo apurável —
     * devolve null em vez de zero, porque ausência de medida não é medida zero.
     */
    public function horasUteis(ViabilityRequest $request): ?float
    {
        $inicio = $request->protocoled_at;
        $fim = $request->decision?->decided_at;

        if ($inicio === null || $fim === null) {
            return null;
        }

        $minutos = $this->prazos->businessDurationBetween($inicio, $fim)
            - $this->minutosComOCidadao($request, $inicio, $fim);

        return round(max(0, $minutos) / 60, 2);
    }

    /**
     * Minutos úteis em que o processo esteve com o cidadão. Intervalos de
     * pendência sobrepostos são fundidos antes da medição, para que o mesmo
     * período não seja descontado duas vezes.
     */
    private function minutosComOCidadao(
        ViabilityRequest $request,
        DateTimeInterface $inicio,
        DateTimeInterface $fim,
    ): int {
        $intervalos = $this->intervalosDePendencia($request, $inicio, $fim);

        $minutos = 0;

        foreach ($this->fundirSobrepostos($intervalos) as [$de, $ate]) {
            $minutos += $this->prazos->businessDurationBetween($de, $ate);
        }

        return $minutos;
    }

    /**
     * Intervalos de pendência recortados à janela do processo. A pendência
     * ainda aberta na decisão desconta até a decisão.
     *
     * @return list<array{0: Carbon, 1: Carbon}>
     */
    private function intervalosDePendencia(
        ViabilityRequest $request,
        DateTimeInterface $inicio,
        DateTimeInterface $fim,
    ): array {
        $janelaInicio = Carbon::instance($inicio);
        $janelaFim = Carbon::instance($fim);

        $intervalos = [];

        foreach ($request->pendencies as $pendencia) {
            $abertura = $pendencia->created_at;

            if ($abertura === null) {
                continue;
            }

            $de = Carbon::instance($abertura)->max($janelaInicio);
            $ate = $this->fimDaPendencia($pendencia, $janelaFim)->min($janelaFim);

            if ($ate->greaterThan($de)) {
                $intervalos[] = [$de, $ate];
            }
        }

        return $intervalos;
    }

    /**
     * Pendência respondida fecha na resposta; cancelada, no cancelamento;
     * ainda aberta, no fim da janela.
     */
    private function fimDaPendencia(AnalysisPendency $pendencia, Carbon $janelaFim): Carbon
    {
        $fim = $pendencia->responded_at ?? $pendencia->cancelled_at;

        return $fim === null ? $janelaFim->copy() : Carbon::instance($fim);
    }

    /**
     * @param  list<array{0: Carbon, 1: Carbon}>  $intervalos
     * @return list<array{0: Carbon, 1: Carbon}>
     */
    private function fundirSobrepostos(array $intervalos): array
    {
        usort($intervalos, static fn (array $a, array $b): int => $a[0] <=> $b[0]);

        $fundidos = [];

        foreach ($intervalos as [$de, $ate]) {
            $ultimo = array_key_last($fundidos);

            if ($ultimo !== null && $de->lessThanOrEqualTo($fundidos[$ultimo][1])) {
                $fundidos[$ultimo][1] = $fundidos[$ultimo][1]->max($ate);

                continue;
            }

            $fundidos[] = [$de, $ate];
        }

        return $fundidos;
    }
}
