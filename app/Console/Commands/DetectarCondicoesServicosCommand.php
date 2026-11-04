<?php

namespace App\Console\Commands;

use App\Models\ServicoNomenclatura;
use App\Models\ViabilityRequestEvento;
use App\Services\Sefaz\LegadoViabilidadeFonte;
use App\Services\Servicos\CondicaoServicoDetector;
use Illuminate\Console\Command;

/**
 * Preenche a condição (Inclusão/Exclusão, Revisão de Endereço/Alteração de
 * Espaço) dos eventos gravados antes de o De x Para ter linhas condicionais.
 * A alteração de atividade sem processo anterior consulta o Simplifica, que
 * aceita 60 requisições por minuto: quando ele não responde, espera e tenta de
 * novo; se continuar sem resposta, o evento fica sem condição e conta como erro.
 */
class DetectarCondicoesServicosCommand extends Command
{
    protected $signature = 'servicos:detectar-condicoes
        {--so-endereco : Só os eventos de alteração de endereço, que não consultam o Simplifica}
        {--espera=60 : Segundos de espera quando o Simplifica não responde}
        {--tentativas=3 : Tentativas por evento quando o Simplifica não responde}';

    protected $description = 'Detecta a condição do De x Para nos eventos da JUCEB já gravados';

    public function handle(CondicaoServicoDetector $detector, LegadoViabilidadeFonte $legado): int
    {
        $soEndereco = (bool) $this->option('so-endereco');
        $espera = max(0, (int) $this->option('espera'));
        $tentativas = max(1, (int) $this->option('tentativas'));
        $preenchidos = 0;
        $semResposta = 0;

        ViabilityRequestEvento::query()
            ->whereNull('condicao')
            ->with('viabilityRequest')
            ->chunkById(500, function ($eventos) use ($detector, $legado, $soEndereco, $espera, $tentativas, &$preenchidos, &$semResposta): void {
                foreach ($eventos as $evento) {
                    if ($evento->viabilityRequest === null) {
                        continue;
                    }

                    if ($soEndereco && ! str_starts_with(ServicoNomenclatura::chaveDe((string) $evento->nome), 'alteracao de endereco')) {
                        continue;
                    }

                    for ($tentativa = 1; $tentativa <= $tentativas; $tentativa++) {
                        $legado->esquecerFalha();
                        $condicao = $detector->detectar($evento->viabilityRequest, $evento->nome);

                        if (! $legado->falhou()) {
                            break;
                        }

                        if ($tentativa < $tentativas && $espera > 0) {
                            $this->warn("Simplifica sem resposta; aguardando {$espera}s");
                            sleep($espera);
                        }
                    }

                    if ($legado->falhou()) {
                        $semResposta++;

                        continue;
                    }

                    if ($condicao !== null) {
                        $evento->update(['condicao' => $condicao->value]);
                        $preenchidos++;
                    }
                }
            });

        $this->info("Eventos com condição preenchida: {$preenchidos}. Simplifica sem resposta: {$semResposta}.");

        return $semResposta > 0 ? self::FAILURE : self::SUCCESS;
    }
}
