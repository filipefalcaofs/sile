<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gestao\IdentifyTerritoryRequest;
use App\Http\Requests\Gestao\ValidateLocationRequest;
use App\Models\GeoLayer;
use App\Services\Geo\LocationValidationService;
use App\Services\Geo\TerritoryService;
use App\Support\Audit\AuditService;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Backend do mapa territorial da gestão (HU-030/HU-036/HU-037). A página lista
 * as camadas vigentes com seus status (zona/lote explícitos como pendentes de
 * fonte — HU-036) e o limiar de sobreposição parametrizado; os endpoints
 * identificar (TerritoryService) e validar-localizacao (LocationValidationService)
 * operam a lógica real e são auditados (RN-002). Tudo protegido por
 * consultar-territorio (gate no middleware permission:, PADRÃO CROSS-GUARD do
 * 04-03). A UI Leaflet completa é construída no 04-07 sobre este contrato.
 */
class TerritoryController extends Controller
{
    public function __construct(
        private TerritoryService $territory,
        private LocationValidationService $validation,
        private AuditService $audit,
    ) {}

    /**
     * Página de consulta territorial: camadas + status + limiar de sobreposição.
     */
    public function index(): Response
    {
        return Inertia::render('gestao/territorio/index', [
            'camadas' => GeoLayer::query()
                ->whereNull('valid_to')
                ->get()
                ->map(fn (GeoLayer $layer): array => [
                    'type' => $layer->type->value,
                    'type_label' => $layer->type->label(),
                    'status' => $layer->status->value,
                    'status_label' => $layer->status->label(),
                    'version' => $layer->version,
                    'feature_count' => $layer->feature_count,
                ]),
            'sobreposicaoMinima' => (int) Settings::get(
                'geo.validacao.sobreposicao_minima',
                config('sile.geo.validacao.sobreposicao_minima', 50),
            ),
            'geocodingEnabled' => Settings::enabled('geocoding'),
            'mapa' => ['centro' => ['lat' => -12.97, 'lng' => -38.5], 'zoom' => 13],
        ]);
    }

    /**
     * Identifica zona/via/lote/bairro/restrições por ponto (HU-030/HU-036). A
     * auditoria é feita dentro do TerritoryService (event identificacao); o
     * controller só repassa o resultado como JSON (contrato da UI 04-07).
     */
    public function identify(IdentifyTerritoryRequest $request): JsonResponse
    {
        $result = $this->territory->identify(
            (float) $request->validated('lat'),
            (float) $request->validated('lng'),
        );

        return response()->json($result->toArray());
    }

    /**
     * Valida a localização por sobreposição do polígono informado com o lote
     * oficial (HU-037 RN-004). Audita explicitamente o resultado e, quando há
     * alerta de baixa sobreposição, registra-o para o analista (RN-004). Hoje o
     * lote é pendente SEDUR e o resultado comunica "indisponível" (sem fachada).
     */
    public function validateLocation(ValidateLocationRequest $request): JsonResponse
    {
        $result = $this->validation->validate($request->validated('polygon'));

        $this->audit->log(
            'territorio',
            'validacao-localizacao',
            'Validação de localização por sobreposição com o lote',
            [
                'resultado' => $result->toArray(),
                'alerta' => $result->alerta,
            ],
            result: 'sucesso',
        );

        return response()->json($result->toArray());
    }
}
