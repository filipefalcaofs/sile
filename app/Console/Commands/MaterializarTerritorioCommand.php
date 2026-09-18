<?php

namespace App\Console\Commands;

use App\Models\ViabilityRequest;
use App\Services\Geo\TerritorioProcessoService;
use App\Support\Audit\AuditService;
use Illuminate\Console\Command;

/**
 * Backfill do território materializado (Onda GIS): percorre os processos com
 * polígono e sem zona/bairro materializados e grava a zona oficial (GeoServer
 * WFS) e o bairro oficial (camada GeoSalvador) pela identificação territorial.
 * Idempotente e conservador por construção (o serviço nunca sobrescreve dado
 * bom com degradação). O resultado é auditado (RN-002).
 */
class MaterializarTerritorioCommand extends Command
{
    protected $signature = 'geo:materializar-territorio';

    protected $description = 'Materializa zona_codigo/bairro_oficial nos processos com polígono (backfill da Onda GIS)';

    public function handle(TerritorioProcessoService $territorio, AuditService $audit): int
    {
        $elegiveis = ViabilityRequest::query()
            ->whereNotNull('property_polygon_geojson')
            ->where(fn ($q) => $q->whereNull('zona_codigo')->orWhereNull('bairro_oficial'))
            ->count();

        $this->info("Processos elegíveis: {$elegiveis}");

        $materializados = 0;

        ViabilityRequest::query()
            ->whereNotNull('property_polygon_geojson')
            ->where(fn ($q) => $q->whereNull('zona_codigo')->orWhereNull('bairro_oficial'))
            ->chunkById(100, function ($processos) use ($territorio, &$materializados): void {
                foreach ($processos as $processo) {
                    if ($territorio->materializar($processo)) {
                        $materializados++;
                    }
                }
            });

        $this->info("Materializados: {$materializados}");

        $audit->log(
            logName: 'territorio',
            event: 'materializacao-backfill',
            description: 'Backfill do território materializado (zona/bairro oficiais)',
            properties: ['elegiveis' => $elegiveis, 'materializados' => $materializados],
            result: 'sucesso',
        );

        return self::SUCCESS;
    }
}
