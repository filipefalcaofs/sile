<?php

namespace App\Console\Commands;

use App\Enums\GeoLayerStatus;
use App\Enums\GeoLayerType;
use App\Models\GeoLayer;
use App\Support\Audit\AuditService;
use Illuminate\Console\Command;

/**
 * Encerra a vigência de uma versão de camada sem apagar as feições (ex.: a
 * zona fictícia de demonstração que não pode valer em produção). Sem camada
 * local vigente, a dimensão passa a ser consultada no GeoServer SEDUR.
 */
class EncerrarCamadaGeoCommand extends Command
{
    protected $signature = 'geo:encerrar-camada
        {type : Tipo da camada (bairro, zona, via, lote, restricao)}
        {versao : Versão a encerrar}';

    protected $description = 'Encerra a vigência de uma versão de camada geográfica (sem apagar), auditado';

    public function handle(AuditService $audit): int
    {
        $type = GeoLayerType::tryFrom((string) $this->argument('type'));
        $versao = (string) $this->argument('versao');

        $camada = $type === null ? null : GeoLayer::query()
            ->where('type', $type)
            ->where('version', $versao)
            ->first();

        if ($camada === null) {
            $this->error("Camada {$this->argument('type')} {$versao} não encontrada.");

            return self::FAILURE;
        }

        $statusAnterior = $camada->status->value;

        $camada->forceFill([
            'status' => GeoLayerStatus::Substituida,
            'valid_to' => $camada->valid_to ?? now()->toDateString(),
        ])->save();

        $audit->log(
            logName: 'geo',
            event: 'camada-encerrada',
            description: "Camada {$type->value} {$versao} encerrada",
            properties: [
                'type' => $type->value,
                'version' => $versao,
                'status_anterior' => $statusAnterior,
            ],
            subject: $camada,
        );

        $this->info("Camada {$type->value} {$versao} encerrada.");

        return self::SUCCESS;
    }
}
