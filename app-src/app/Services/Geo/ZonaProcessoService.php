<?php

namespace App\Services\Geo;

use App\Enums\ZonaFonte;
use App\Models\ViabilityRequest;
use App\Services\Decisao\DecisionTextCatalog;
use App\Support\Audit\AuditService;

/**
 * Zona do processo pela maior porção do polígono do endereçamento (art. 18,
 * § 2º, da Lei 9.148/2016). Sem polígono, ou com o cálculo indisponível, a
 * zona continua a do ponto e `zona_fonte` diz qual regra decidiu. Empate não
 * grava zona vencedora: o fluxo expresso encaminha à análise.
 */
class ZonaProcessoService
{
    public function __construct(
        private ZonaMaiorPorcaoResolver $maiorPorcao,
        private DecisionTextCatalog $textos,
        private AuditService $audit,
    ) {}

    public function resolver(ViabilityRequest $request): ?ZonaMaiorPorcao
    {
        $poligono = $request->address_polygon_geojson;

        return is_array($poligono) && $poligono !== [] ? $this->maiorPorcao->resolver($poligono) : null;
    }

    public function materializar(ViabilityRequest $request): ?ZonaMaiorPorcao
    {
        $resultado = $this->resolver($request);

        if ($resultado === null) {
            $this->marcarPonto($request);

            return null;
        }

        $this->auditar($request, $resultado);

        if ($resultado->identificado()) {
            $request->forceFill([
                'zona_codigo' => $resultado->codigo,
                'zona_fonte' => ZonaFonte::EnderecamentoMaiorPorcao,
            ])->save();

            return $resultado;
        }

        if ($request->zona_fonte !== ZonaFonte::EnderecamentoMaiorPorcao) {
            $this->marcarPonto($request);
        }

        return $resultado;
    }

    public function motivoEmpate(ZonaMaiorPorcao $resultado): string
    {
        return $this->textos->render('zona.motivo.empate_maior_porcao', [
            ':zona_a' => $resultado->empate[0] ?? '—',
            ':zona_b' => $resultado->empate[1] ?? '—',
        ]);
    }

    private function marcarPonto(ViabilityRequest $request): void
    {
        if ($request->zona_codigo !== null && $request->zona_fonte !== ZonaFonte::Ponto) {
            $request->forceFill(['zona_fonte' => ZonaFonte::Ponto])->save();
        }
    }

    private function auditar(ViabilityRequest $request, ZonaMaiorPorcao $resultado): void
    {
        $this->audit->log(
            logName: 'geo',
            event: 'zona-maior-porcao',
            description: "Zona pela maior porção ({$resultado->status}) na solicitação #{$request->id}",
            properties: [
                'viability_request_id' => $request->id,
                'status' => $resultado->status,
                'zona_vencedora' => $resultado->codigo,
                'porcoes' => $resultado->porcoes,
                'empate' => $resultado->empate,
                'camadas' => $resultado->camadas,
                'area_lote_m2' => $resultado->areaLoteM2,
                'motivo' => $resultado->motivo,
            ],
            subject: $request,
            result: $resultado->status,
            rulesVersion: 'lei-9148-2016-art18',
        );
    }
}
