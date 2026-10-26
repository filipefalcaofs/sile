<?php

namespace App\Console\Commands;

use App\Enums\ViabilityRequestOrigin;
use App\Events\ResultadoEmitido;
use App\Listeners\ComunicarResultadoRegin;
use App\Models\Activity;
use App\Models\IntegrationCall;
use App\Models\ViabilityRequest;
use App\Services\Regin\ReginIntegrationSettings;
use Illuminate\Console\Command;

/**
 * Reenvia à JUCEB o parecer dos processos REGIN que já têm decisão e ainda não
 * tiveram o recebimento confirmado. Usa o mesmo listener do envio original
 * (ComunicarResultadoRegin), então a auditoria `integracoes`/`regin-parecer`
 * continua a única fonte: `sucesso` = RECEBIDO_SUCESSO, `bloqueado` = não
 * confirmou. Quem já tem `sucesso` nunca é reenviado.
 *
 * Dois modos reenviam também quem já tem `sucesso`, para corrigir parecer aceito
 * com dado errado (ex.: CODIGO_AREA=1 antes do ajuste para 70):
 *  - `--forcar` com `--protocolo=`: reenvia exatamente esses protocolos;
 *  - `--area-errada`: reenvia todo parecer cujo último envio aceito levou área
 *    diferente da configurada. Depois do reenvio o protocolo sai da lista.
 *
 * Para quando a JUCEB recusa em sequência (ex.: 403 de autorização): continuar
 * só repetiria a recusa em centenas de processos.
 */
class ReginReenviarPareceresCommand extends Command
{
    protected $signature = 'regin:reenviar-pareceres
        {--protocolo=* : Só estes protocolos (BAP…); sem a opção, todos os pendentes}
        {--exceto=* : Protocolos que não entram nesta passada}
        {--forcar : Com --protocolo, reenvia mesmo quem já teve o recebimento confirmado}
        {--area-errada : Reenvia os já confirmados cujo último envio levou CODIGO_AREA diferente da configurada}
        {--limite= : Máximo de processos nesta passada}
        {--parar-apos=3 : Interrompe após esta quantidade de falhas seguidas}
        {--dry-run : Lista o que seria reenviado, sem enviar}';

    protected $description = 'Reenvia à JUCEB o parecer dos processos REGIN com decisão e sem recebimento confirmado';

    public function handle(ComunicarResultadoRegin $listener, ReginIntegrationSettings $settings): int
    {
        $protocolos = array_filter((array) $this->option('protocolo'));
        $forcar = (bool) $this->option('forcar');
        $areaErrada = (bool) $this->option('area-errada');

        if ($forcar && $protocolos === []) {
            $this->error('--forcar exige --protocolo=BAP…; para corrigir a área em lote use --area-errada.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $pararApos = max(1, (int) $this->option('parar-apos'));

        $jaEntregues = Activity::query()
            ->where('log_name', 'integracoes')
            ->where('event', 'regin-parecer')
            ->where('result', 'sucesso')
            ->where('subject_type', (new ViabilityRequest)->getMorphClass())
            ->pluck('subject_id')
            ->all();

        $query = ViabilityRequest::query()
            ->where('origin', ViabilityRequestOrigin::Regin)
            ->whereNotNull('external_reference')
            ->whereHas('decision')
            ->with('decision')
            ->orderBy('id');

        if ($areaErrada) {
            $comAreaErrada = $this->protocolosComAreaDiferente($settings->codigoArea());

            if ($protocolos !== []) {
                $comAreaErrada = array_values(array_intersect($comAreaErrada, array_map('strtoupper', $protocolos)));
            }

            $query->whereIn('external_reference', $comAreaErrada);
        } else {
            if (! $forcar) {
                $query->whereNotIn('id', $jaEntregues);
            }

            if ($protocolos !== []) {
                $query->whereIn('external_reference', $protocolos);
            }
        }

        $exceto = array_filter((array) $this->option('exceto'));

        if ($exceto !== []) {
            $query->whereNotIn('external_reference', $exceto);
        }

        if ($this->option('limite') !== null && (int) $this->option('limite') > 0) {
            $query->limit((int) $this->option('limite'));
        }

        $pendentes = $query->get();
        $enviados = 0;
        $falhas = 0;
        $seguidas = 0;

        foreach ($pendentes as $processo) {
            $protocolo = (string) $processo->external_reference;

            if ($dryRun) {
                $this->line("{$protocolo}: seria reenviado ({$processo->decision->outcome->value})");

                continue;
            }

            $listener->handle(new ResultadoEmitido($processo, $processo->decision));

            $resultado = Activity::query()
                ->where('log_name', 'integracoes')
                ->where('event', 'regin-parecer')
                ->where('subject_type', $processo->getMorphClass())
                ->where('subject_id', $processo->getKey())
                ->latest('id')
                ->value('result');

            if ($resultado === 'sucesso') {
                $this->line("{$protocolo}: enviado e confirmado pela JUCEB");
                $enviados++;
                $seguidas = 0;

                continue;
            }

            $this->line("{$protocolo}: JUCEB não confirmou o recebimento");
            $falhas++;
            $seguidas++;

            if ($seguidas >= $pararApos) {
                $this->warn("Interrompido após {$seguidas} falhas seguidas. Resolva o acesso à JUCEB e rode de novo; quem já foi confirmado não é reenviado.");

                break;
            }
        }

        $this->info(($dryRun ? '[dry-run] ' : '')."pendentes {$pendentes->count()}, confirmados {$enviados}, não confirmados {$falhas}");

        return $falhas > 0 && ! $dryRun ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Protocolos cujo envio mais recente aceito pela JUCEB (codFuncao 110) levou
     * um CODIGO_AREA diferente do configurado.
     *
     * @return list<string>
     */
    private function protocolosComAreaDiferente(int $areaCorreta): array
    {
        $areas = [];

        IntegrationCall::query()
            ->where('direction', 'saida')
            ->where('integration', 'regin')
            ->where('url', 'like', '%/recebe%')
            ->where('outcome', 'sucesso')
            ->orderBy('id')
            ->chunkById(500, function ($lote) use (&$areas): void {
                foreach ($lote as $chamada) {
                    $corpo = json_decode((string) $chamada->request_body, true);

                    if (! is_array($corpo) || (int) ($corpo['codFuncao'] ?? 0) !== 110) {
                        continue;
                    }

                    $protocolo = strtoupper(trim((string) ($corpo['protocolo'] ?? '')));

                    if ($protocolo !== '') {
                        $areas[$protocolo] = (string) ($corpo['dadosProcesso']['ANALISES']['AREA'][0]['CODIGO_AREA'] ?? 'ausente');
                    }
                }
            });

        return array_keys(array_filter($areas, fn (string $area): bool => $area !== (string) $areaCorreta));
    }
}
