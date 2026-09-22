<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\LookupCnpjRequest;
use App\Services\Cnpj\CnpjLookup;
use App\Services\Cnpj\CnpjLookupException;
use App\Services\Cnpj\CnpjNotFoundException;
use App\Support\Audit\AuditService;
use App\Support\Settings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;

/**
 * HU-021 — consulta dos dados cadastrais por CNPJ para pré-preenchimento do
 * cadastro de empresa. Toda saída (sucesso, não encontrado, indisponibilidade
 * e bloqueio por toggle) é auditada (CA-02); com features.cnpj_lookup desligado
 * nenhum request HTTP sai (degradação comunicada, cadastro manual segue).
 */
class CnpjLookupController extends Controller
{
    public function __construct(
        private CnpjLookup $lookup,
        private AuditService $audit,
    ) {}

    public function __invoke(LookupCnpjRequest $request): JsonResponse
    {
        $cnpj = $request->validated('cnpj');

        if (! Settings::enabled('cnpj_lookup')) {
            $this->audit->log(
                'empresas',
                'consulta-cnpj',
                'Consulta de CNPJ bloqueada: recurso desativado',
                ['cnpj' => $cnpj, 'motivo' => 'toggle-desativado'],
                result: 'bloqueado',
            );

            return response()->json([
                'message' => 'A consulta automática de CNPJ está desativada. Preencha os dados manualmente.',
            ], 422);
        }

        try {
            $data = $this->lookup->lookup($cnpj);

            $this->audit->log(
                'empresas',
                'consulta-cnpj',
                'Consulta de CNPJ realizada',
                ['cnpj' => $cnpj, 'provider' => $this->provider()],
                result: 'sucesso',
            );

            return response()->json($data->toArray());
        } catch (CnpjNotFoundException) {
            $this->audit->log(
                'empresas',
                'consulta-cnpj',
                'Consulta de CNPJ: não encontrado',
                ['cnpj' => $cnpj, 'motivo' => 'nao-encontrado'],
                result: 'falha',
            );

            return response()->json([
                'message' => 'CNPJ não encontrado na base da Receita Federal.',
            ], 404);
        } catch (CnpjLookupException|ConnectionException) {
            $this->audit->log(
                'empresas',
                'consulta-cnpj',
                'Consulta de CNPJ: serviço indisponível',
                ['cnpj' => $cnpj, 'motivo' => 'indisponibilidade'],
                result: 'falha',
            );

            return response()->json([
                'message' => 'Serviço de consulta indisponível no momento. Preencha os dados manualmente.',
            ], 503);
        }
    }

    /**
     * Host do provider em uso (BrasilAPI ou minhareceita, conforme parâmetro).
     */
    private function provider(): ?string
    {
        return parse_url(
            (string) Settings::get(
                'integrations.cnpj_lookup.base_url',
                config('sile.integrations.cnpj_lookup.base_url'),
            ),
            PHP_URL_HOST,
        );
    }
}
