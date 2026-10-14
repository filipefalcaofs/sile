<?php

namespace App\Services\Geo;

use App\Enums\GeoLayerStatus;
use App\Enums\GeoLayerType;
use App\Enums\RuleDomain;
use App\Enums\TerritorialRuleType;
use App\Enums\ZonaFonte;
use App\Models\GeoLayer;
use App\Models\RuleVersion;
use App\Models\TerritorialRule;
use App\Models\ViabilityRequest;
use App\Services\Realty\LocalizacaoInscricaoService;

/**
 * Inventário das camadas geográficas do imóvel: em cada camada, qual elemento
 * contém ou margeia o ponto do imóvel. Logradouro tributário (SEFAZ), todos os
 * logradouros num raio curto, bairro, zona e as poligonais das regras
 * territoriais ativas (ABM com o trecho, ZEM, ZPAM, ZUE, loteamentos TAC).
 * Com polígono do endereçamento, a zona é a da maior porção do lote, com as
 * porções por zona.
 * Camada que não respondeu fica como indisponível, nunca omitida.
 */
class CamadasIncidentesService
{
    /**
     * Atributos que nomeiam o elemento da feição, na ordem de preferência.
     */
    private const ATRIBUTOS_ELEMENTO = [
        'TRECHOS' => 'Trecho %s',
        'PARCELAMENTO' => '%s',
        'SUBZONA' => '%s',
        'ZONA' => '%s',
        'NOME' => '%s',
        'DESCRICAO' => '%s',
    ];

    public function __construct(
        private TerritoryService $territorio,
        private SpatialRepository $espacial,
        private GeoServerWfsCamadaClient $wfs,
        private LocalizacaoInscricaoService $localizacao,
        private ZonaProcessoService $zonaProcesso,
        private GeoServerClassificacaoViariaClient $classificacaoViaria,
    ) {}

    /**
     * Identifica e grava o inventário no processo.
     *
     * @return array<string, mixed>
     */
    public function materializar(ViabilityRequest $request): array
    {
        $inventario = $this->identificar($request);

        $request->forceFill(['camadas_incidentes' => $inventario])->save();

        return $inventario;
    }

    /**
     * @return array<string, mixed>
     */
    public function identificar(ViabilityRequest $request): array
    {
        $ponto = $request->pontoDoImovel();
        $localizacao = $this->localizacao->localizar($request->property_registration);

        $inventario = [
            'identificado_em' => now()->toIso8601String(),
            'ponto' => $ponto === null ? null : [
                'lat' => $ponto['lat'],
                'lng' => $ponto['lng'],
                'origem' => $request->property_polygon_geojson ? 'poligono' : $request->property_location_source?->value,
            ],
            'logradouro_tributario' => $localizacao->codLogTributario === null ? null : [
                'cod_log' => $localizacao->codLogTributario,
                'nome' => $this->nomeLogradouro($localizacao->logradouroTributario),
            ],
            'logradouros_proximos' => [],
            'bairro' => null,
            'zona' => null,
            'via' => $this->classeViaria($request->cod_log ?: $localizacao->codLogTributario),
            'camadas' => [],
        ];

        if ($ponto === null) {
            $inventario['zona'] = $this->zona($request, null);

            return $inventario;
        }

        $territorio = $this->territorio->identify($ponto['lat'], $ponto['lng']);

        $inventario['logradouros_proximos'] = $this->logradourosProximos($ponto['lat'], $ponto['lng']);
        $inventario['bairro'] = $this->dimensao($territorio->bairro);
        $inventario['zona'] = $this->zona($request, $territorio->zona);
        $inventario['camadas'] = $this->camadasPoligonais($ponto['lat'], $ponto['lng']);

        return $inventario;
    }

    /**
     * Classe viária (Quadro 11A) do logradouro do processo, a mesma que o
     * enquadramento usa. Null quando o CODLOG falta ou a classe não existe.
     */
    private function classeViaria(?string $codLog): ?string
    {
        $codLog = trim((string) $codLog);

        return $codLog === '' ? null : $this->classificacaoViaria->consultar($codLog)->classe;
    }

    /**
     * @return list<array{cod_log: ?string, nome: ?string, distancia_m: float}>
     */
    private function logradourosProximos(float $lat, float $lng): array
    {
        $camada = GeoLayer::vigente(GeoLayerType::Via)->first();

        if ($camada === null || $camada->status === GeoLayerStatus::PendenteFonte) {
            return [];
        }

        $raio = (int) config('sile.geo.logradouros_proximos_metros', 30);

        return array_map(fn (array $feicao): array => [
            'cod_log' => isset($feicao['properties']['CODLOG']) ? (string) $feicao['properties']['CODLOG'] : null,
            'nome' => $feicao['properties']['NOME_LOGRADOURO'] ?? $feicao['properties']['NOME_VIA'] ?? null,
            'distancia_m' => round($feicao['distancia_m'], 1),
        ], $this->espacial->featuresWithin($camada, $lng, $lat, $raio));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function camadasPoligonais(float $lat, float $lng): array
    {
        $versao = RuleVersion::vigente(RuleDomain::Territorial)->first();

        if ($versao === null) {
            return [];
        }

        return TerritorialRule::query()
            ->where('rule_version_id', $versao->getKey())
            ->where('ativo', true)
            ->whereIn('tipo', [TerritorialRuleType::PoligonalAnalise, TerritorialRuleType::PoligonalAllowlistExpresso])
            ->whereNotNull('camada')
            ->orderBy('id')
            ->get()
            ->map(fn (TerritorialRule $regra): array => $this->camadaDaRegra($regra, $lat, $lng))
            ->all();
    }

    /**
     * Consulta a camada sem o filtro da regra para saber em qual elemento o
     * ponto está (ex.: trecho 3 da ABM) e, dentro dela, com o filtro para
     * saber se a regra alcança o imóvel (trechos 5 e 6).
     *
     * @return array<string, mixed>
     */
    private function camadaDaRegra(TerritorialRule $regra, float $lat, float $lng): array
    {
        $base = [
            'regra' => $regra->codigo,
            'titulo' => $regra->titulo,
            'camada' => $regra->camada,
        ];

        $hit = $this->wfs->consultar((string) $regra->camada, $lat, $lng);

        if ($hit->indisponivel()) {
            return $base + ['status' => GeoServerCamadaHit::INDISPONIVEL, 'elemento' => null, 'atributos' => [], 'incide' => null];
        }

        if (! $hit->dentro()) {
            return $base + ['status' => GeoServerCamadaHit::FORA, 'elemento' => null, 'atributos' => [], 'incide' => false];
        }

        $atributos = $this->atributosEscalares($hit->properties ?? []);
        $incide = true;

        if (is_string($regra->camada_filtro) && trim($regra->camada_filtro) !== '') {
            $filtrado = $this->wfs->consultar((string) $regra->camada, $lat, $lng, $regra->camada_filtro);
            $incide = $filtrado->indisponivel() ? null : $filtrado->dentro();
        }

        return $base + [
            'status' => GeoServerCamadaHit::DENTRO,
            'elemento' => $this->elemento($atributos),
            'atributos' => $atributos,
            'incide' => $incide,
        ];
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function elemento(array $atributos): ?string
    {
        foreach (self::ATRIBUTOS_ELEMENTO as $chave => $formato) {
            $valor = $atributos[$chave] ?? null;

            if ($valor !== null && trim((string) $valor) !== '') {
                return sprintf($formato, trim((string) $valor));
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $propriedades
     * @return array<string, string|int|float|bool>
     */
    private function atributosEscalares(array $propriedades): array
    {
        return array_slice(array_filter($propriedades, 'is_scalar'), 0, 15, true);
    }

    /**
     * Zona do inventário: a da maior porção do endereçamento quando identificada,
     * senão a do ponto, com as porções e o motivo da maior porção quando houve
     * polígono para calcular.
     *
     * @param  array<string, mixed>|null  $zonaPonto
     * @return array{status: string, nome: ?string, fonte: ?string, porcoes: list<array{zona: string, area_m2: float, percentual: float}>, maior_porcao: array{status: string, motivo: ?string, empate: list<string>}|null}|null
     */
    private function zona(ViabilityRequest $request, ?array $zonaPonto): ?array
    {
        $porArea = $this->zonaProcesso->resolver($request);

        if ($porArea?->identificado()) {
            return [
                'status' => 'identificado',
                'nome' => $porArea->codigo,
                'fonte' => ZonaFonte::EnderecamentoMaiorPorcao->value,
                'porcoes' => $porArea->porcoes,
                'maior_porcao' => ['status' => $porArea->status, 'motivo' => null, 'empate' => []],
            ];
        }

        if ($zonaPonto === null && $porArea === null) {
            return null;
        }

        $ponto = $zonaPonto === null ? ['status' => 'indisponivel', 'nome' => null] : $this->dimensao($zonaPonto);

        return $ponto + [
            'fonte' => $ponto['status'] === 'identificado' ? ZonaFonte::Ponto->value : null,
            'porcoes' => $porArea === null ? [] : $porArea->porcoes,
            'maior_porcao' => $porArea === null ? null : [
                'status' => $porArea->status,
                'motivo' => $porArea->motivo,
                'empate' => $porArea->empate,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $dimensao
     * @return array{status: string, nome: ?string}
     */
    private function dimensao(array $dimensao): array
    {
        return [
            'status' => (string) ($dimensao['status'] ?? 'indisponivel'),
            'nome' => is_string($dimensao['nome'] ?? null) ? $dimensao['nome'] : null,
        ];
    }

    /**
     * O cadastro devolve "212 - Avenida Lafayete Coutinho"; o inventário guarda
     * o nome sem o código repetido.
     */
    private function nomeLogradouro(?string $logradouro): ?string
    {
        if ($logradouro === null) {
            return null;
        }

        return trim((string) preg_replace('/^\d+\s*-\s*/', '', $logradouro));
    }
}
