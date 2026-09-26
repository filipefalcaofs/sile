<?php

namespace App\Services\Geo;

use App\Models\ViabilityRequest;
use App\Services\Decisao\DecisionTextCatalog;
use App\Services\Realty\LocalizacaoInscricaoService;
use App\Support\Audit\AuditService;

/**
 * HU-037 RN-005: o polígono desenhado e o ponto da inscrição no Cadastro
 * Imobiliário precisam cair na mesma zona. Zona diferente bloqueia a decisão
 * automática. Sem ponto ou sem zona identificada, a verificação não bloqueia
 * e fica registrada como não realizada.
 */
class LocalizacaoDivergenciaService
{
    public function __construct(
        private LocalizacaoInscricaoService $localizacao,
        private TerritoryService $territorio,
        private DecisionTextCatalog $textos,
        private AuditService $audit,
    ) {}

    public function avaliar(ViabilityRequest $request): ?string
    {
        if (blank($request->property_polygon_geojson)) {
            return null;
        }

        $localizacao = $this->localizacao->localizar($request->property_registration);

        if (! $localizacao->temPonto()) {
            $this->auditar($request, 'nao_verificada', 'Cadastro Imobiliário sem ponto ('.$localizacao->status.').');

            return null;
        }

        $pontoPoligono = $request->pontoDoImovel();
        $zonaPoligono = $pontoPoligono !== null ? $this->zona($pontoPoligono['lat'], $pontoPoligono['lng']) : null;
        $zonaInscricao = $this->zona((float) $localizacao->lat, (float) $localizacao->lng);

        if ($zonaPoligono === null || $zonaInscricao === null) {
            $this->auditar($request, 'nao_verificada', 'Zona não identificada para o polígono ou para a inscrição.');

            return null;
        }

        if ($zonaPoligono === $zonaInscricao) {
            $this->auditar($request, 'coincide', null, $zonaPoligono, $zonaInscricao);

            return null;
        }

        $motivo = $this->textos->render('localizacao.motivo.divergencia_zona', [
            ':zona_poligono' => $zonaPoligono,
            ':zona_inscricao' => $zonaInscricao,
        ]);

        $this->auditar($request, 'diverge', $motivo, $zonaPoligono, $zonaInscricao);

        return $motivo;
    }

    private function zona(float $lat, float $lng): ?string
    {
        $zona = $this->territorio->identify($lat, $lng)->zona;
        $nome = $zona['nome'] ?? null;

        if (($zona['status'] ?? null) !== 'identificado' || ! is_string($nome) || trim($nome) === '') {
            return null;
        }

        return trim($nome);
    }

    private function auditar(
        ViabilityRequest $request,
        string $resultado,
        ?string $motivo,
        ?string $zonaPoligono = null,
        ?string $zonaInscricao = null,
    ): void {
        $this->audit->log(
            logName: 'geo',
            event: 'localizacao-divergencia',
            description: "Verificação de localização (HU-037 RN-005) na solicitação #{$request->id}: {$resultado}",
            properties: [
                'viability_request_id' => $request->id,
                'resultado' => $resultado,
                'motivo' => $motivo,
                'zona_poligono' => $zonaPoligono,
                'zona_inscricao' => $zonaInscricao,
            ],
            subject: $request,
            result: $resultado,
        );
    }
}
