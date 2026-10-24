<?php

namespace App\Services\Realty;

use App\Enums\PropertyLocationSource;
use App\Services\Geo\AddressNotFoundException;
use App\Services\Geo\Geocoder;
use App\Services\Geo\GeocodeResult;
use App\Services\Geo\GeocoderException;
use App\Services\Geo\ValidadorPontoInscricao;

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
        private ValidadorPontoInscricao $validador,
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

        [$codLogLocalizacao, $logradouroLocalizacao] = $this->logradouroDaLocalizacao($resultado->raw);

        $situacao = $resultado->cadastro?->situacao_cadastral;
        $uso = $resultado->raw['Utilizacao'] ?? null;
        $utilizacao = is_string($uso) && trim($uso) !== '' ? trim($uso) : null;

        $temPonto = $resultado->latitude !== null && $resultado->longitude !== null;

        if ($temPonto) {
            $ponto = $this->validador->validar(
                $resultado->latitude,
                $resultado->longitude,
                $codLogLocalizacao ?? ($codLog !== '' ? $codLog : null),
                $this->texto($resultado->cadastro?->numero_porta),
                $resultado->enderecoParaGeocode(),
                $digitos,
            );

            return new LocalizacaoInscricao(
                status: LocalizacaoInscricao::LOCALIZADA,
                lat: $ponto->lat,
                lng: $ponto->lng,
                codLogTributario: $codLog !== '' ? $codLog : null,
                logradouroTributario: $nome !== '' ? $nome : null,
                origemPonto: $ponto->origem,
                situacaoCadastral: $situacao,
                utilizacao: $utilizacao,
                codLogLocalizacao: $codLogLocalizacao,
                logradouroLocalizacao: $logradouroLocalizacao,
                confirmacaoPonto: $ponto->confirmacao,
                pontoSefazLat: $resultado->latitude,
                pontoSefazLng: $resultado->longitude,
                distanciaPontoM: $ponto->distanciaOriginalM,
                distanciaFinalPontoM: $ponto->distanciaFinalM,
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
                utilizacao: $utilizacao,
                codLogLocalizacao: $codLogLocalizacao,
                logradouroLocalizacao: $logradouroLocalizacao,
            );
        }

        return new LocalizacaoInscricao(
            status: LocalizacaoInscricao::SEM_COORDENADA,
            codLogTributario: $codLog !== '' ? $codLog : null,
            logradouroTributario: $nome !== '' ? $nome : null,
            situacaoCadastral: $situacao,
            utilizacao: $utilizacao,
            codLogLocalizacao: $codLogLocalizacao,
            logradouroLocalizacao: $logradouroLocalizacao,
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

    /**
     * Código e nome do logradouro do endereço de Localização da inscrição.
     *
     * @param  array<string, mixed>  $raw
     * @return array{0: ?string, 1: ?string}
     */
    private function logradouroDaLocalizacao(array $raw): array
    {
        $lista = $raw['ListaEndereco']['RetornoEndereco'] ?? [];

        if (! is_array($lista)) {
            return [null, null];
        }

        if (! array_is_list($lista)) {
            $lista = [$lista];
        }

        foreach ($lista as $endereco) {
            if (! is_array($endereco) || ($endereco['TipoEndereco'] ?? null) !== 'Localização') {
                continue;
            }

            $codigo = $endereco['CodigoLogradouro'] ?? null;
            $codLog = is_string($codigo) || is_numeric($codigo) ? ltrim(trim((string) $codigo), '0') : '';

            if ($codLog === '') {
                return [null, null];
            }

            $nome = $endereco['Logradouro'] ?? $endereco['DescricaoLogradouro'] ?? null;
            $nome = is_string($nome) ? trim($nome) : '';

            return [$codLog, $nome !== '' ? $nome : null];
        }

        return [null, null];
    }

    private function texto(?string $valor): ?string
    {
        $valor = trim((string) $valor);

        return $valor !== '' ? $valor : null;
    }
}
