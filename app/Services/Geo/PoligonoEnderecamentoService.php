<?php

namespace App\Services\Geo;

use App\Models\ViabilityRequest;
use App\Support\Audit\AuditService;

/**
 * Grava no processo o polígono do endereçamento SEDUR, consultado pelo CODLOG
 * do logradouro tributário, pelo número e pelo complemento do endereço.
 * Ambíguo, ausente ou indisponível não grava nada e fica na auditoria.
 */
class PoligonoEnderecamentoService
{
    public function __construct(
        private GeoServerEnderecamentoClient $cliente,
        private AuditService $audit,
    ) {}

    public function aplicar(ViabilityRequest $request): bool
    {
        $codLog = trim((string) $request->cod_log);
        $numero = trim((string) $request->address_number);
        $complemento = $request->address_complement;

        if ($codLog === '' || $numero === '') {
            $this->auditar($request, 'sem_chave', $codLog, $numero, $complemento, new EnderecamentoHit('sem_chave', motivo: 'Processo sem CODLOG ou número métrico.'));

            return false;
        }

        $hit = $this->cliente->consultar($codLog, $numero, $complemento);

        $this->auditar($request, $hit->status, $codLog, $numero, $complemento, $hit);

        if (! $hit->encontrado()) {
            return false;
        }

        $request->forceFill([
            'address_polygon_geojson' => $hit->geometria,
            'address_polygon_consulted_at' => now(),
        ])->save();

        return true;
    }

    private function auditar(ViabilityRequest $request, string $status, string $codLog, string $numero, ?string $complemento, EnderecamentoHit $hit): void
    {
        $this->audit->log(
            logName: 'geo',
            event: 'enderecamento-poligono',
            description: "Polígono do endereçamento ({$status}) na solicitação #{$request->id}",
            properties: [
                'viability_request_id' => $request->id,
                'cod_log' => $codLog,
                'numero' => $numero,
                'complemento' => $complemento,
                'status' => $status,
                'candidatas' => $hit->candidatas,
                'motivo' => $hit->motivo,
            ],
            subject: $request,
            result: $status,
        );
    }
}
