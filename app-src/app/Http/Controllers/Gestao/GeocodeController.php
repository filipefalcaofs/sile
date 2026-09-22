<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gestao\GeocodeRequest;
use App\Services\Geo\AddressNotFoundException;
use App\Services\Geo\Geocoder;
use App\Services\Geo\GeocoderException;
use App\Support\Audit\AuditService;
use App\Support\Settings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;

/**
 * HU-029 — geocodificação de endereço (endereço → coordenada) para posicionar o
 * imóvel no mapa do território. Toda saída (sucesso, não localizado,
 * indisponibilidade e bloqueio por toggle) é auditada (RN-002); com
 * features.geocoding desligado nenhum request HTTP sai (degradação comunicada —
 * a localização manual no mapa segue possível).
 */
class GeocodeController extends Controller
{
    public function __construct(
        private Geocoder $geocoder,
        private AuditService $audit,
    ) {}

    public function __invoke(GeocodeRequest $request): JsonResponse
    {
        $address = $request->validated('address');

        if (! Settings::enabled('geocoding')) {
            $this->audit->log(
                'territorio',
                'geocodificacao',
                'Geocodificação bloqueada: recurso desativado',
                ['endereco' => $address, 'motivo' => 'toggle-desativado'],
                result: 'bloqueado',
            );

            return response()->json([
                'message' => 'A geocodificação está desativada. Informe a localização manualmente no mapa.',
            ], 422);
        }

        try {
            $result = $this->geocoder->geocode($address);

            $this->audit->log(
                'territorio',
                'geocodificacao',
                'Geocodificação realizada',
                ['endereco' => $address, 'provider' => $this->provider()],
                result: 'sucesso',
            );

            return response()->json($result->toArray());
        } catch (AddressNotFoundException) {
            $this->audit->log(
                'territorio',
                'geocodificacao',
                'Geocodificação: endereço não localizado',
                ['endereco' => $address, 'motivo' => 'nao-encontrado'],
                result: 'falha',
            );

            return response()->json([
                'message' => 'Endereço não localizado. Ajuste o texto ou posicione o ponto no mapa.',
            ], 404);
        } catch (GeocoderException|ConnectionException) {
            $this->audit->log(
                'territorio',
                'geocodificacao',
                'Geocodificação: serviço indisponível',
                ['endereco' => $address, 'motivo' => 'indisponibilidade'],
                result: 'falha',
            );

            return response()->json([
                'message' => 'Serviço de geocodificação indisponível. Posicione o ponto manualmente no mapa.',
            ], 503);
        }
    }

    /**
     * Host do provider de geocodificação em uso (Nominatim ou self-host).
     */
    private function provider(): ?string
    {
        return parse_url(
            (string) Settings::get(
                'integrations.geocoding.base_url',
                config('sile.integrations.geocoding.base_url'),
            ),
            PHP_URL_HOST,
        );
    }
}
