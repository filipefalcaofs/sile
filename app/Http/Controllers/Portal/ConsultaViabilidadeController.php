<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\ConsultaViabilidadeEnderecoRequest;
use App\Services\Geo\AddressNotFoundException;
use App\Services\Geo\GeocoderException;
use App\Services\Viabilidade\ConsultaViabilidadeService;
use App\Support\Audit\AuditService;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Consulta prévia de viabilidade PÚBLICA (HU-054/055/056): a página e os 3
 * endpoints JSON (endereço/CNAE/inscrição) são acessíveis ao cidadão ANÔNIMO,
 * com throttle parametrizado (07-01) e auditoria RN-002. O controller só
 * orquestra via ConsultaViabilidadeService (07-05) — a auditoria de sucesso é
 * interna ao serviço; aqui registramos o bloqueio por toggle (HU-014) e
 * traduzimos as exceções honestas do geocoder (endereço não localizado / serviço
 * indisponível NUNCA viram resultado falso — sem fachada).
 */
class ConsultaViabilidadeController extends Controller
{
    public function __construct(
        private ConsultaViabilidadeService $service,
        private AuditService $audit,
    ) {}

    /**
     * Página pública da consulta (a UI completa é construída no 07-08; aqui só o
     * backend da rota). `consultaEnabled` comunica o toggle à interface.
     */
    public function index(): Response
    {
        return Inertia::render('portal/viabilidade/consulta', [
            'consultaEnabled' => Settings::enabled('consulta_viabilidade'),
        ]);
    }

    /**
     * Consulta por ENDEREÇO (HU-054): geocodifica → território → motores. Traduz
     * as exceções do geocoder em mensagens honestas — nunca resultado falso.
     */
    public function endereco(ConsultaViabilidadeEnderecoRequest $request): JsonResponse
    {
        if ($bloqueio = $this->guardToggle()) {
            return $bloqueio;
        }

        try {
            $result = $this->service->consultarPorEndereco(
                $request->validated('endereco'),
                $request->validated('cnae'),
                $request->validated('area') !== null ? (float) $request->validated('area') : null,
            );
        } catch (AddressNotFoundException) {
            return response()->json([
                'message' => 'Endereço não localizado. Revise o endereço ou posicione a consulta por CNAE.',
            ], 404);
        } catch (GeocoderException) {
            return response()->json([
                'message' => 'Serviço de geocodificação indisponível no momento. Tente novamente em instantes.',
            ], 503);
        }

        return response()->json($result->toArray());
    }

    /**
     * Guarda do toggle features.consulta_viabilidade (HU-014): desligado, bloqueia
     * ANTES de orquestrar, audita o bloqueio (degradação comunicada, nunca falha
     * silenciosa) e devolve 422. Ligado, devolve null e o fluxo segue.
     */
    private function guardToggle(): ?JsonResponse
    {
        if (Settings::enabled('consulta_viabilidade')) {
            return null;
        }

        $this->audit->log(
            'viabilidade',
            'consulta',
            'Consulta de viabilidade bloqueada: recurso desativado',
            ['motivo' => 'toggle-desativado'],
            result: 'bloqueado',
        );

        return response()->json([
            'message' => 'A consulta de viabilidade está temporariamente desativada. Tente novamente mais tarde.',
        ], 422);
    }
}
