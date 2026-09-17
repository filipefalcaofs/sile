<?php

namespace App\Services\Realty;

use App\Support\Settings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Consulta real do Cadastro Imobiliário (SEFAZ via BFF da SEDUR).
 * Descarta NomeProprietario e históricos de propriedade (LGPD).
 */
class SedurSefazPropertyRegistryLookup implements PropertyRegistryLookup
{
    public function resolve(string $inscricao): PropertyRegistryResult
    {
        if (! Settings::enabled('cadastro_imobiliario')) {
            throw new PropertyRegistryUnavailableException($inscricao);
        }

        $digitos = preg_replace('/\D/', '', $inscricao) ?? '';

        if ($digitos === '') {
            throw new PropertyNotFoundException($inscricao);
        }

        $baseUrl = rtrim((string) Settings::get(
            'integrations.inscricao_imobiliaria.base_url',
            config('sile.integrations.inscricao_imobiliaria.base_url'),
        ), '/');

        $timeout = (int) config('sile.integrations.inscricao_imobiliaria.timeout', 12);
        $retries = (int) config('sile.integrations.inscricao_imobiliaria.retries', 3);
        $backoff = (int) config('sile.integrations.inscricao_imobiliaria.backoff_ms', 500);

        try {
            $response = Http::timeout($timeout)
                ->connectTimeout(3)
                ->retry(
                    $retries,
                    $backoff,
                    function (Throwable $exception): bool {
                        if ($exception instanceof RequestException) {
                            return $exception->response->serverError();
                        }

                        return true;
                    },
                )
                ->acceptJson()
                ->get("{$baseUrl}/{$digitos}");
        } catch (ConnectionException) {
            Log::warning('cadastro_imobiliario.indisponivel', [
                'inscricao' => $digitos,
                'http' => null,
                'codigo_retorno' => null,
            ]);

            throw new PropertyRegistryUnavailableException($inscricao);
        } catch (RequestException $exception) {
            $status = $exception->response->status();

            Log::warning('cadastro_imobiliario.consulta', [
                'inscricao' => $digitos,
                'http' => $status,
                'codigo_retorno' => null,
            ]);

            if ($status === 404) {
                throw new PropertyNotFoundException($digitos);
            }

            throw new PropertyRegistryUnavailableException($digitos);
        }

        $status = $response->status();
        $corpo = $response->json();
        $codigoRetorno = is_array($corpo) ? ($corpo['CodigoRetorno'] ?? null) : null;

        Log::info('cadastro_imobiliario.consulta', [
            'inscricao' => $digitos,
            'http' => $status,
            'codigo_retorno' => $codigoRetorno,
        ]);

        if ($status === 404) {
            throw new PropertyNotFoundException($digitos);
        }

        if (! $response->successful() || ! is_array($corpo)) {
            throw new PropertyRegistryUnavailableException($digitos);
        }

        if (($corpo['CodigoRetorno'] ?? null) !== '0') {
            throw new PropertyNotFoundException($digitos);
        }

        return $this->mapear($digitos, $corpo);
    }

    /**
     * @param  array<string, mixed>  $corpo
     */
    private function mapear(string $inscricao, array $corpo): PropertyRegistryResult
    {
        $utm = CoordenadaUtm24s::paraWgs84(
            $this->texto($corpo['CoordenadaX'] ?? null),
            $this->texto($corpo['CoordenadaY'] ?? null),
        );

        $localizacao = $this->enderecoDoImovel($corpo);
        $numero = $this->numeroPorta($localizacao['NumeroPorta'] ?? null);
        $logradouro = $this->texto($localizacao['Logradouro'] ?? null)
            ?? $this->texto($localizacao['DescricaoLogradouro'] ?? null);
        $complemento = $this->juntarEdificioComplemento($localizacao);

        $endereco = $logradouro;
        if ($endereco !== null && $numero !== null) {
            $endereco .= ', '.$numero;
        }
        if ($endereco !== null && $complemento !== null) {
            $endereco .= ' — '.$complemento;
        }

        $codigoLogradouro = $this->texto($corpo['CodigoLogradouroTributario'] ?? null);
        $logradouroTributario = $this->texto($corpo['LogradouroTributario'] ?? null);
        $logradouroTributarioCompleto = match (true) {
            $codigoLogradouro !== null && $logradouroTributario !== null => "{$codigoLogradouro} - {$logradouroTributario}",
            default => $logradouroTributario ?? $codigoLogradouro,
        };

        $cadastro = new PropertyCadastroCampos(
            inscricao: $inscricao,
            endereco: $endereco,
            numero_metrico: $this->texto($localizacao['NumeroMetrico'] ?? null),
            loteamento: $this->texto($localizacao['Loteamento'] ?? null),
            quadra: $this->texto($localizacao['Quadra'] ?? null),
            lote: $this->texto($localizacao['Lote'] ?? null),
            conjunto_edificio: $this->texto($localizacao['Edificio'] ?? $localizacao['Conjunto'] ?? null),
            bloco: $this->texto($localizacao['Bloco'] ?? null),
            sub_unidade: $this->texto($localizacao['SubUnidade'] ?? null),
            numero_sub_unidade: $this->texto($localizacao['NumeroSubUnidade'] ?? null),
            bairro: $this->texto($localizacao['Bairro'] ?? null),
            cep: $this->formatarCep($localizacao['CEP'] ?? null),
            area_construida_m2: $this->texto($corpo['AreaConstruida'] ?? null),
            tipo_imovel: null,
            data_lancamento: $this->dataBr($corpo['DataEmissaoHabiteSe'] ?? null),
            situacao_cadastral: $this->texto($corpo['Status'] ?? null),
            contribuinte: null,
            cpf_cnpj: null,
            numero_porta: $numero,
            area_terreno_m2: $this->texto($corpo['AreaTerreno'] ?? null),
            valor_venal_iptu: $this->texto($corpo['Vupt'] ?? null),
            logradouro_tributario: $logradouroTributarioCompleto,
            situacao_fiscal: null,
            data_emissao_certidao: $this->dataBr($corpo['DataProcessamento'] ?? null),
        );

        return new PropertyRegistryResult(
            latitude: $utm['latitude'] ?? null,
            longitude: $utm['longitude'] ?? null,
            inscricao: $inscricao,
            source: 'SEFAZ via SEDUR',
            raw: $this->sanitizar($corpo),
            cadastro: $cadastro,
        );
    }

    /**
     * @param  array<string, mixed>  $corpo
     * @return array<string, mixed>
     */
    private function enderecoDoImovel(array $corpo): array
    {
        $lista = $corpo['ListaEndereco']['RetornoEndereco'] ?? [];

        if (! is_array($lista) || $lista === []) {
            return [];
        }

        if (! array_is_list($lista)) {
            $lista = [$lista];
        }

        $validos = array_values(array_filter(
            $lista,
            function (mixed $item): bool {
                if (! is_array($item)) {
                    return false;
                }

                return $this->texto($item['Logradouro'] ?? null) !== null
                    || $this->texto($item['DescricaoLogradouro'] ?? null) !== null;
            },
        ));

        foreach ($validos as $endereco) {
            if (($endereco['TipoEndereco'] ?? null) === 'Localização') {
                return $endereco;
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $corpo
     * @return array<string, mixed>
     */
    private function sanitizar(array $corpo): array
    {
        unset(
            $corpo['NomeProprietario'],
            $corpo['ListaOutrosProprietarios'],
            $corpo['ListaHistoricoPropriedade'],
        );

        return $corpo;
    }

    /**
     * @param  array<string, mixed>  $endereco
     */
    private function juntarEdificioComplemento(array $endereco): ?string
    {
        $edificio = $this->texto($endereco['Edificio'] ?? null);
        $complemento = $this->texto($endereco['Complemento'] ?? null);

        if ($edificio !== null && $complemento !== null) {
            return "{$edificio} - {$complemento}";
        }

        return $edificio ?? $complemento;
    }

    private function numeroPorta(mixed $valor): ?string
    {
        $texto = $this->texto($valor);

        if ($texto === null) {
            return null;
        }

        $semZeros = ltrim($texto, '0');

        return $semZeros === '' ? null : $semZeros;
    }

    private function formatarCep(mixed $valor): ?string
    {
        $digitos = preg_replace('/\D/', '', (string) ($this->texto($valor) ?? '')) ?? '';

        if (strlen($digitos) !== 8) {
            return $this->texto($valor);
        }

        return substr($digitos, 0, 5).'-'.substr($digitos, 5);
    }

    private function dataBr(mixed $valor): ?string
    {
        $texto = $this->texto($valor);

        if ($texto === null) {
            return null;
        }

        $timestamp = strtotime($texto);

        if ($timestamp === false) {
            return $texto;
        }

        return date('d/m/Y H:i:s', $timestamp);
    }

    private function texto(mixed $valor): ?string
    {
        if (is_array($valor) || is_object($valor) || $valor === null) {
            return null;
        }

        $texto = trim((string) $valor);

        return $texto === '' ? null : $texto;
    }
}
