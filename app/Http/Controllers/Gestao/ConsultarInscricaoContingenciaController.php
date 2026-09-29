<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gestao\ConsultarInscricaoContingenciaRequest;
use App\Services\Geo\AddressNotFoundException;
use App\Services\Geo\Geocoder;
use App\Services\Geo\GeocoderException;
use App\Services\Realty\PropertyCadastroCampos;
use App\Services\Realty\PropertyNotFoundException;
use App\Services\Realty\PropertyRegistryLookup;
use App\Services\Realty\PropertyRegistryResult;
use App\Services\Realty\PropertyRegistryUnavailableException;
use App\Support\Audit\AuditService;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;

/**
 * Pré-preenche o imóvel da contingência com o Cadastro Imobiliário (SEFAZ via
 * SEDUR). Devolve só endereço e coordenada — nunca o payload bruto, que pode
 * trazer dado de proprietário. Sem coordenada no cadastro, o ponto sai do
 * geocoder quando o endereço está em Salvador.
 */
class ConsultarInscricaoContingenciaController extends Controller
{
    public function __construct(
        private PropertyRegistryLookup $lookup,
        private Geocoder $geocoder,
        private AuditService $audit,
    ) {}

    public function __invoke(ConsultarInscricaoContingenciaRequest $request): JsonResponse
    {
        $inscricao = $request->validated('inscricao');

        if (! Settings::enabled('cadastro_imobiliario')) {
            $this->audit->log(
                'contingencia',
                'consulta-inscricao',
                'Consulta de inscrição bloqueada: recurso desativado',
                ['inscricao' => $inscricao, 'motivo' => 'toggle-desativado'],
                result: 'bloqueado',
            );

            return response()->json([
                'message' => 'A consulta do Cadastro Imobiliário está desativada. Preencha o endereço manualmente.',
                'errors' => [
                    'inscricao' => ['A consulta do Cadastro Imobiliário está desativada. Preencha o endereço manualmente.'],
                ],
            ], 422);
        }

        try {
            $resultado = $this->lookup->resolve($inscricao);
        } catch (PropertyNotFoundException) {
            $this->audit->log(
                'contingencia',
                'consulta-inscricao',
                'Consulta de inscrição: não encontrada',
                ['inscricao' => $inscricao, 'motivo' => 'nao-encontrado'],
                result: 'falha',
            );

            return response()->json([
                'message' => 'Inscrição não encontrada no Cadastro Imobiliário.',
            ], 404);
        } catch (PropertyRegistryUnavailableException) {
            $this->audit->log(
                'contingencia',
                'consulta-inscricao',
                'Consulta de inscrição: cadastro indisponível',
                ['inscricao' => $inscricao, 'motivo' => 'indisponibilidade'],
                result: 'falha',
            );

            return response()->json([
                'message' => 'Cadastro Imobiliário temporariamente indisponível. Preencha o endereço manualmente.',
            ], 503);
        }

        [$latitude, $longitude] = $this->coordenadas($resultado);

        $this->audit->log(
            'contingencia',
            'consulta-inscricao',
            'Consulta de inscrição realizada',
            [
                'inscricao' => $inscricao,
                'source' => $resultado->source,
                'tem_coordenada' => $latitude !== null && $longitude !== null,
            ],
            result: 'sucesso',
        );

        return response()->json([
            ...$this->endereco($resultado->cadastro),
            'latitude' => $latitude,
            'longitude' => $longitude,
        ]);
    }

    /**
     * @return array{0: ?float, 1: ?float}
     */
    private function coordenadas(PropertyRegistryResult $resultado): array
    {
        if ($resultado->latitude !== null && $resultado->longitude !== null) {
            return [$resultado->latitude, $resultado->longitude];
        }

        $endereco = $resultado->enderecoParaGeocode();

        if ($endereco === null) {
            return [null, null];
        }

        try {
            $geocode = $this->geocoder->geocode($endereco);
        } catch (AddressNotFoundException|GeocoderException) {
            return [null, null];
        }

        if (! $geocode->estaEmSalvador()) {
            return [null, null];
        }

        return [$geocode->latitude, $geocode->longitude];
    }

    /**
     * O cadastro guarda o logradouro já concatenado com número e complemento
     * (`Rua, 10 — Sala 2`). A tela separa os três campos.
     *
     * @return array{
     *     address_street: ?string,
     *     address_number: ?string,
     *     address_complement: ?string,
     *     address_neighborhood: ?string,
     *     address_zip: ?string
     * }
     */
    private function endereco(?PropertyCadastroCampos $cadastro): array
    {
        $logradouro = trim((string) ($cadastro?->endereco ?? ''));
        $numero = $cadastro?->numero_porta;
        $complemento = null;

        if (str_contains($logradouro, ' — ')) {
            [$logradouro, $complemento] = explode(' — ', $logradouro, 2);
            $complemento = trim($complemento);
            $complemento = $complemento === '' ? null : $complemento;
        }

        if ($numero !== null && $numero !== '' && str_ends_with($logradouro, ', '.$numero)) {
            $logradouro = substr($logradouro, 0, -strlen(', '.$numero));
        }

        $logradouro = trim($logradouro);
        $cep = preg_replace('/\D/', '', (string) ($cadastro?->cep ?? '')) ?? '';

        return [
            'address_street' => $logradouro === '' ? null : $logradouro,
            'address_number' => $numero !== null && $numero !== '' ? $numero : null,
            'address_complement' => $complemento,
            'address_neighborhood' => $cadastro?->bairro,
            'address_zip' => $cep === '' ? null : $cep,
        ];
    }
}
