<?php

namespace App\Services\Realty;

use App\Enums\PropertyLocationSource;
use App\Services\Geo\AddressNotFoundException;
use App\Services\Geo\Geocoder;
use App\Services\Geo\GeocodeResult;
use App\Services\Geo\GeocoderException;

/**
 * Consulta a localização da inscrição no Cadastro Imobiliário uma vez por
 * execução (requisição ou job): protocolo, CODLOG tributário, regra territorial
 * e a checagem de divergência reaproveitam o mesmo resultado.
 *
 * Sem coordenada no cadastro, e com logradouro e número, o ponto sai do
 * Nominatim. Falha ou endereço fora de Salvador mantém o status sem coordenada.
 */
class LocalizacaoInscricaoService
{
    /** @var array<string, LocalizacaoInscricao> */
    private array $memo = [];

    public function __construct(
        private PropertyRegistryLookup $cadastro,
        private Geocoder $geocoder,
    ) {}

    public function localizar(?string $inscricao): LocalizacaoInscricao
    {
        $digitos = preg_replace('/\D/', '', (string) $inscricao) ?? '';

        if ($digitos === '') {
            return new LocalizacaoInscricao(LocalizacaoInscricao::SEM_INSCRICAO);
        }

        return $this->memo[$digitos] ??= $this->consultar($digitos);
    }

    private function consultar(string $digitos): LocalizacaoInscricao
    {
        try {
            $resultado = $this->cadastro->resolve($digitos);
        } catch (PropertyNotFoundException) {
            return new LocalizacaoInscricao(LocalizacaoInscricao::NAO_ENCONTRADA);
        } catch (PropertyRegistryUnavailableException) {
            return new LocalizacaoInscricao(LocalizacaoInscricao::INDISPONIVEL);
        }

        $codigo = $resultado->raw['CodigoLogradouroTributario'] ?? null;
        $codLog = is_string($codigo) || is_numeric($codigo)
            ? ltrim(trim((string) $codigo), '0')
            : '';
        $nome = $resultado->raw['LogradouroTributario'] ?? $resultado->cadastro?->logradouro_tributario;
        $nome = is_string($nome) ? trim($nome) : '';

        $situacao = $resultado->cadastro?->situacao_cadastral;

        $temPonto = $resultado->latitude !== null && $resultado->longitude !== null;

        if ($temPonto) {
            return new LocalizacaoInscricao(
                status: LocalizacaoInscricao::LOCALIZADA,
                lat: $resultado->latitude,
                lng: $resultado->longitude,
                codLogTributario: $codLog !== '' ? $codLog : null,
                logradouroTributario: $nome !== '' ? $nome : null,
                origemPonto: PropertyLocationSource::CadastroImobiliario,
                situacaoCadastral: $situacao,
            );
        }

        $geocode = $this->geocodificar($resultado);

        if ($geocode !== null) {
            return new LocalizacaoInscricao(
                status: LocalizacaoInscricao::LOCALIZADA,
                lat: $geocode->latitude,
                lng: $geocode->longitude,
                codLogTributario: $codLog !== '' ? $codLog : null,
                logradouroTributario: $nome !== '' ? $nome : null,
                origemPonto: PropertyLocationSource::Geocodificacao,
                geocode: $geocode,
                situacaoCadastral: $situacao,
            );
        }

        return new LocalizacaoInscricao(
            status: LocalizacaoInscricao::SEM_COORDENADA,
            codLogTributario: $codLog !== '' ? $codLog : null,
            logradouroTributario: $nome !== '' ? $nome : null,
            situacaoCadastral: $situacao,
        );
    }

    private function geocodificar(PropertyRegistryResult $resultado): ?GeocodeResult
    {
        $endereco = $resultado->enderecoParaGeocode();

        if ($endereco === null) {
            return null;
        }

        try {
            $geocode = $this->geocoder->geocode($endereco);
        } catch (AddressNotFoundException|GeocoderException) {
            return null;
        }

        if (! $geocode->estaEmSalvador()) {
            return null;
        }

        return $geocode;
    }
}
