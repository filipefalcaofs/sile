<?php

namespace App\Console\Commands;

use App\Services\Geo\ClassificacaoViariaHit;
use App\Services\Geo\GeoServerClassificacaoViariaClient;
use Illuminate\Console\Command;

/**
 * Consulta a classe viária da LOUOS de um CODLOG no GeoServer SEDUR.
 * Evidência de homologação da integração real (Quadro 11A).
 */
class LouosClasseViaCommand extends Command
{
    protected $signature = 'louos:classe-via {codlog : Código do logradouro (CODLOG)}';

    protected $description = 'Mostra a classe viária da LOUOS de um logradouro, consultada no GeoServer SEDUR';

    public function handle(GeoServerClassificacaoViariaClient $client): int
    {
        $codLog = (string) $this->argument('codlog');
        $hit = $client->consultar($codLog);

        $this->line("CODLOG: {$codLog}");
        $this->line('Status: '.$hit->status);
        $this->line('Classe publicada: '.($hit->classeBruta ?? '—'));
        $this->line('Classe da lei: '.($hit->classe ?? '—'));

        if ($hit->status === ClassificacaoViariaHit::IDENTIFICADO) {
            return self::SUCCESS;
        }

        $this->error($hit->motivo ?? 'Classificação viária não identificada.');

        return self::FAILURE;
    }
}
