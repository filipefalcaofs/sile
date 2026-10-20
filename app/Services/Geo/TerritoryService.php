<?php

namespace App\Services\Geo;

use App\Enums\GeoLayerStatus;
use App\Enums\GeoLayerType;
use App\Models\GeoLayer;
use App\Support\Audit\AuditService;
use App\Support\Settings;
use Carbon\CarbonInterface;

/**
 * Identificação territorial por ponto (HU-030 a HU-034): dado lat/lng, devolve
 * bairro (ST_Contains), via mais próxima (ST_DWithin/ST_Distance) e restrições
 * incidentes (ST_Intersects) a partir das camadas REAIS carregadas; e devolve
 * zona/lote como indisponível quando a base é `pendente_fonte` — degradação
 * comunicada, sem valor falso (sem fachada).
 *
 * A lógica é genérica e dirigida pelo DADO: uma camada inexistente ou
 * `pendente_fonte` é indisponível e NÃO dispara consulta espacial; quando a
 * SEDUR entregar a base de zoneamento/lotes (camada vigente com feições), a
 * MESMA lógica passa a identificá-la — a carga muda, a lógica não. A versão de
 * cada camada consultada é registrada (RN-004) e a identificação é auditada
 * (RN-002).
 */
class TerritoryService
{
    public function __construct(
        private SpatialRepository $spatial,
        private AuditService $audit,
        private ?GeoServerWfsZonaClient $geoserver = null,
        private ?GeoServerClassificacaoViariaClient $classificacaoViaria = null,
    ) {}

    /**
     * Identifica as dimensões territoriais do ponto. Use a camada vigente por
     * padrão, ou a vigente em `$date` para reproduzir uma decisão (RN-004).
     */
    public function identify(float $lat, float $lng, ?CarbonInterface $date = null): TerritoryResult
    {
        // Raio (metros) da "via mais próxima": constante técnica de 04-02 em
        // config/viabiliza.php (não-catálogo); o default é só rede de segurança.
        $maxMeters = (int) config('viabiliza.geo.via_max_metros', 50);

        $result = new TerritoryResult(
            bairro: $this->identifyContaining(GeoLayerType::Bairro, $lng, $lat, $date),
            via: $this->identifyNearest(GeoLayerType::Via, $lng, $lat, $maxMeters, $date),
            zona: $this->identifyContaining(GeoLayerType::Zona, $lng, $lat, $date),
            lote: $this->identifyContaining(GeoLayerType::Lote, $lng, $lat, $date),
            restricoes: $this->identifyIntersecting(GeoLayerType::Restricao, $lng, $lat, $date),
        );

        $this->audit->log(
            logName: 'territorio',
            event: 'identificacao',
            description: 'Identificação territorial por ponto',
            properties: [
                'lat' => $lat,
                'lng' => $lng,
                'versoes_por_camada' => $result->versoes(),
                'resumo_status' => $result->resumoStatus(),
            ],
            result: 'sucesso',
        );

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function identifyContaining(GeoLayerType $type, float $lng, float $lat, ?CarbonInterface $date): array
    {
        $layer = $this->resolveLayer($type, $date);

        if ($this->isBlocked($layer)) {
            if ($type === GeoLayerType::Zona) {
                $remoto = $this->identifyZonaFromGeoServer($lat, $lng);

                if ($remoto !== null) {
                    return $remoto;
                }
            }

            return $this->dimIndisponivel($this->motivoFor($type), $layer?->version);
        }

        $feature = $this->spatial->containingFeature($layer, $lng, $lat);

        if ($feature === null && $type === GeoLayerType::Zona) {
            $remoto = $this->identifyZonaFromGeoServer($lat, $lng);

            if ($remoto !== null && ($remoto['status'] ?? null) === 'identificado') {
                return $remoto;
            }
        }

        return $feature === null
            ? $this->dimNaoEncontrado($layer->version)
            : $this->dimIdentificado($layer->version, $feature['properties']);
    }

    /**
     * @return array<string, mixed>
     */
    private function identifyNearest(GeoLayerType $type, float $lng, float $lat, int $maxMeters, ?CarbonInterface $date): array
    {
        $layer = $this->resolveLayer($type, $date);

        if ($this->isBlocked($layer)) {
            return $this->dimIndisponivel($this->motivoFor($type), $layer?->version) + ['distancia_m' => null];
        }

        $feature = $this->spatial->nearestFeature($layer, $lng, $lat, $maxMeters);

        if ($feature === null) {
            return $this->dimNaoEncontrado($layer->version) + ['distancia_m' => null];
        }

        $dim = $this->dimIdentificado($layer->version, $feature['properties'], ['distancia_m' => $feature['distancia_m']]);

        return $type === GeoLayerType::Via ? $this->comClasseViaria($dim) : $dim;
    }

    /**
     * Acrescenta a classe viária da LOUOS (Quadro 11A) às propriedades da via,
     * consultada por CODLOG na camada oficial. Sem classe, a via carrega o
     * motivo e o atributo não é criado — o enquadramento degrada em vez de
     * receber uma classe inventada.
     *
     * @param  array<string, mixed>  $dim
     * @return array<string, mixed>
     */
    private function comClasseViaria(array $dim): array
    {
        if ($this->classificacaoViaria === null) {
            return $dim + ['classe_via_motivo' => 'Consulta da classificação viária não configurada.'];
        }

        $codLog = $dim['propriedades']['CODLOG'] ?? null;
        $codLog = is_string($codLog) || is_numeric($codLog) ? trim((string) $codLog) : '';

        if ($codLog === '') {
            return $dim + ['classe_via_motivo' => 'Via identificada sem CODLOG.'];
        }

        $hit = $this->classificacaoViaria->consultar($codLog);

        if ($hit->classe === null) {
            return $dim + ['classe_via_motivo' => $hit->motivo ?? 'Classificação viária não encontrada.'];
        }

        $dim['propriedades']['CLASSE_VIA_LOUOS'] = $hit->classe;

        return $dim + ['classe_via_motivo' => null];
    }

    /**
     * @return array<string, mixed>
     */
    private function identifyIntersecting(GeoLayerType $type, float $lng, float $lat, ?CarbonInterface $date): array
    {
        $layer = $this->resolveLayer($type, $date);

        if ($this->isBlocked($layer)) {
            return [
                'status' => 'indisponivel',
                'itens' => [],
                'motivo' => $this->motivoFor($type),
                'versao_camada' => $layer?->version,
            ];
        }

        $itens = array_map(
            fn (array $feature): array => [
                'nome' => $this->nomeFromProperties($feature['properties']),
                'propriedades' => $feature['properties'],
            ],
            $this->spatial->intersectingFeatures($layer, $lng, $lat),
        );

        return [
            'status' => $itens === [] ? 'nao_encontrado' : 'identificado',
            'itens' => $itens,
            'motivo' => null,
            'versao_camada' => $layer->version,
        ];
    }

    private function resolveLayer(GeoLayerType $type, ?CarbonInterface $date): ?GeoLayer
    {
        if ($date !== null) {
            return GeoLayer::naData($type, $date)->first();
        }

        return GeoLayer::vigente($type)->first();
    }

    /**
     * Bloqueada = sem camada ou camada `pendente_fonte` (zona/lote sem base
     * oficial). NUNCA dispara consulta espacial nesse estado (sem fachada).
     */
    private function isBlocked(?GeoLayer $layer): bool
    {
        return $layer === null || $layer->status === GeoLayerStatus::PendenteFonte;
    }

    private function motivoFor(GeoLayerType $type): string
    {
        return match ($type) {
            GeoLayerType::Zona => 'Base de zoneamento pendente SEDUR',
            GeoLayerType::Lote => 'Base de lotes pendente SEDUR',
            GeoLayerType::Via => 'Base de logradouros pendente: GeoServer SEDUR (logradouro:VM_LOGRADOURO)',
            default => 'Camada de '.$type->label().' indisponível',
        };
    }

    /**
     * Nome legível derivado das propriedades da feição (chaves usuais das
     * camadas oficiais); null quando ausente — `propriedades` carrega o resto.
     *
     * @param  array<string, mixed>  $properties
     */
    private function nomeFromProperties(array $properties): ?string
    {
        foreach (['NOME_BAIRRO', 'NOME_LOGRADOURO', 'NOME_VIA', 'SUBZONA', 'ZONA', 'SIGLA_ZONA', 'NOME', 'nome'] as $key) {
            $value = $properties[$key] ?? null;

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $properties
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function dimIdentificado(?string $versao, array $properties, array $extra = []): array
    {
        return [
            'status' => 'identificado',
            'nome' => $this->nomeFromProperties($properties),
            'propriedades' => $properties,
            'motivo' => null,
            'versao_camada' => $versao,
        ] + $extra;
    }

    /**
     * @return array<string, mixed>
     */
    private function dimNaoEncontrado(?string $versao): array
    {
        return [
            'status' => 'nao_encontrado',
            'nome' => null,
            'propriedades' => null,
            'motivo' => null,
            'versao_camada' => $versao,
        ];
    }

    /**
     * O CODLOG da inscrição imobiliária é o logradouro do processo. A via mais
     * próxima pode não existir na camada local; sem esse código o Quadro 10
     * (observações) e o Quadro 11A não enxergam a via oficial.
     */
    public function comCodLogOficial(TerritoryResult $territorio, ?string $codLog): TerritoryResult
    {
        $codLog = trim((string) $codLog);

        if ($codLog === '') {
            return $territorio;
        }

        $propriedades = is_array($territorio->via['propriedades'] ?? null) ? $territorio->via['propriedades'] : [];
        $atual = $propriedades['CODLOG'] ?? null;
        $atual = is_string($atual) || is_numeric($atual) ? trim((string) $atual) : '';

        if (($territorio->via['status'] ?? null) === 'identificado' && $atual === $codLog) {
            return $territorio;
        }

        $via = $this->comClasseViaria([
            'status' => 'identificado',
            'nome' => $territorio->via['nome'] ?? null,
            'propriedades' => [...$propriedades, 'CODLOG' => $codLog],
            'motivo' => null,
            'versao_camada' => 'inscricao-imobiliaria',
            'distancia_m' => $territorio->via['distancia_m'] ?? null,
        ]);

        return new TerritoryResult(
            bairro: $territorio->bairro,
            via: $via,
            zona: $territorio->zona,
            lote: $territorio->lote,
            restricoes: $territorio->restricoes,
        );
    }

    /**
     * Zona oficial no GeoServer quando a camada local está pendente.
     * Null = toggle off / sem cliente — cai no motivo de base pendente.
     *
     * @return array<string, mixed>|null
     */
    private function identifyZonaFromGeoServer(float $lat, float $lng): ?array
    {
        if ($this->geoserver === null || ! Settings::enabled('geoserver_zona')) {
            return null;
        }

        $hit = $this->geoserver->identificar($lat, $lng);

        if ($hit->status === 'identificado' && $hit->codigo !== null) {
            $properties = [
                ...(is_array($hit->properties) ? $hit->properties : []),
                'ZONA' => $hit->codigo,
                'fonte' => 'geoserver-wfs',
                'typeName' => $hit->typeName,
            ];

            $dim = $this->dimIdentificado('geoserver-wfs', $properties);
            $dim['nome'] = $hit->codigo;

            return $dim;
        }

        if ($hit->status === 'nao_encontrado') {
            return $this->dimNaoEncontrado('geoserver-wfs');
        }

        return $this->dimIndisponivel(
            (string) ($hit->motivo ?? 'GeoServer SEDUR indisponível'),
            'geoserver-wfs',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function dimIndisponivel(string $motivo, ?string $versao): array
    {
        return [
            'status' => 'indisponivel',
            'nome' => null,
            'propriedades' => null,
            'motivo' => $motivo,
            'versao_camada' => $versao,
        ];
    }
}
