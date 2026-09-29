<?php

namespace App\Services\Realty;

/**
 * DTO imutável do resultado da resolução por inscrição imobiliária (inscrição
 * → coordenada do imóvel). toArray() expõe o contrato snake_case consumido
 * pelo serviço de consulta; raw guarda o payload bruto da base de lotes para
 * auditoria.
 *
 * ATENÇÃO (ordem de coordenadas): este DTO guarda latitude e longitude
 * NOMEADAS, sem ambiguidade. GeoJSON e ST_MakePoint usam a ordem
 * [longitude, latitude]; o Leaflet usa [latitude, longitude]. O consumidor é
 * responsável por respeitar a ordem do alvo ao montar ponto/geometria.
 */
final readonly class PropertyRegistryResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?float $latitude,
        public ?float $longitude,
        public string $inscricao,
        public ?string $source = null,
        public array $raw = [],
        public ?PropertyCadastroCampos $cadastro = null,
    ) {}

    /**
     * Contrato snake_case do resultado.
     *
     * @return array<string, mixed>
     */
    /**
     * Endereço para geocodificar quando o cadastro trouxe logradouro e número
     * e não trouxe coordenada. Sem os dois, não há o que buscar.
     */
    public function enderecoParaGeocode(): ?string
    {
        $endereco = trim((string) ($this->cadastro?->endereco ?? ''));
        $numero = trim((string) ($this->cadastro?->numero_porta ?? ''));

        if ($endereco === '' || $numero === '') {
            return null;
        }

        $bairro = trim((string) ($this->cadastro?->bairro ?? ''));
        $texto = $endereco;

        if ($bairro !== '') {
            $texto .= ', '.$bairro;
        }

        return $texto.', Salvador';
    }

    public function toArray(): array
    {
        return [
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'inscricao' => $this->inscricao,
            'source' => $this->source,
            'raw' => $this->raw,
            'cadastro' => $this->cadastro?->toArray(),
        ];
    }
}
