<?php

namespace App\Services\Geo;

use App\Support\Audit\AuditService;
use App\Support\Louos\Quadro11aClasseVia;
use App\Support\Settings;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Classe viária da LOUOS (Quadro 11A) por CODLOG, na camada oficial
 * `louos_classificacao_viaria:VM_L_CLASSIF_VIARIA` do GeoServer SEDUR.
 *
 * O casamento é por CODLOG, não espacial: a camada é de linhas e um
 * INTERSECTS de ponto sobre linha praticamente nunca casa. O CODLOG já vem
 * resolvido pela via mais próxima ou pelo logradouro tributário da inscrição.
 *
 * Sem fachada: toggle desligado, GeoServer fora, CODLOG sem linha ou classe
 * fora das 7 oficiais nunca viram classe inventada — o motor degrada.
 */
class GeoServerClassificacaoViariaClient
{
    /** @var array<string, ClassificacaoViariaHit> */
    private array $memo = [];

    public function __construct(private AuditService $audit) {}

    public function consultar(string $codLog): ClassificacaoViariaHit
    {
        $codLog = trim($codLog);

        return $this->memo[$codLog] ??= $this->consultarNoGeoServer($codLog);
    }

    private function consultarNoGeoServer(string $codLog): ClassificacaoViariaHit
    {
        if (! Settings::enabled('geoserver_via_classe')) {
            return new ClassificacaoViariaHit(
                status: ClassificacaoViariaHit::DESLIGADO,
                classe: null,
                classeBruta: null,
                motivo: 'Consulta da classificação viária no GeoServer SEDUR desligada.',
            );
        }

        if ($codLog === '') {
            return new ClassificacaoViariaHit(
                status: ClassificacaoViariaHit::INDISPONIVEL,
                classe: null,
                classeBruta: null,
                motivo: 'CODLOG ausente para consultar a classificação viária.',
            );
        }

        try {
            $response = Http::withHeaders([
                'User-Agent' => (string) config('viabiliza.integrations.geoserver.user_agent', 'Viabiliza-SEDUR-Salvador/1.0'),
            ])
                ->timeout((int) config('viabiliza.integrations.geoserver.timeout', 8))
                ->connectTimeout(3)
                ->acceptJson()
                ->get($this->url($codLog));
        } catch (Throwable $e) {
            return $this->auditar($codLog, new ClassificacaoViariaHit(
                status: ClassificacaoViariaHit::INDISPONIVEL,
                classe: null,
                classeBruta: null,
                motivo: 'GeoServer SEDUR indisponível: '.$e->getMessage(),
            ));
        }

        if (! $response->successful()) {
            return $this->auditar($codLog, new ClassificacaoViariaHit(
                status: ClassificacaoViariaHit::INDISPONIVEL,
                classe: null,
                classeBruta: null,
                motivo: 'GeoServer SEDUR respondeu HTTP '.$response->status().' na classificação viária.',
            ));
        }

        $payload = $response->json();

        if (! is_array($payload) || ($payload['type'] ?? null) !== 'FeatureCollection') {
            return $this->auditar($codLog, new ClassificacaoViariaHit(
                status: ClassificacaoViariaHit::INDISPONIVEL,
                classe: null,
                classeBruta: null,
                motivo: 'Resposta fora do contrato WFS na classificação viária.',
            ));
        }

        $features = $payload['features'] ?? [];

        if (! is_array($features) || $features === []) {
            return $this->auditar($codLog, $this->hierarquiaDoLogradouro($codLog));
        }

        $atributo = (string) config('viabiliza.integrations.geoserver.classificacao_viaria.atributo', 'CLASSIFICACAO_VIARIA');
        $properties = is_array($features[0]['properties'] ?? null) ? $features[0]['properties'] : [];
        $bruta = $properties[$atributo] ?? null;
        $bruta = is_string($bruta) && trim($bruta) !== '' ? trim($bruta) : null;
        $classe = Quadro11aClasseVia::oficializar($bruta);

        if ($classe === null) {
            return $this->auditar($codLog, new ClassificacaoViariaHit(
                status: ClassificacaoViariaHit::INDISPONIVEL,
                classe: null,
                classeBruta: $bruta,
                motivo: 'Classificação viária publicada fora das classes do Quadro 11A.',
            ));
        }

        return $this->auditar($codLog, new ClassificacaoViariaHit(
            status: ClassificacaoViariaHit::IDENTIFICADO,
            classe: $classe,
            classeBruta: $bruta,
            motivo: null,
        ));
    }

    private function auditar(string $codLog, ClassificacaoViariaHit $hit): ClassificacaoViariaHit
    {
        $this->audit->log(
            logName: 'territorio',
            event: 'classificacao-viaria',
            description: "Classificação viária do logradouro {$codLog}",
            properties: [
                'cod_log' => $codLog,
                'classe_bruta' => $hit->classeBruta,
                'classe' => $hit->classe,
            ],
            result: $hit->status,
            rulesVersion: 'lei-9148-2016-quadro11a',
        );

        return $hit;
    }

    /**
     * VIA LOCAL. A camada da LOUOS publica só as vias classificadas — 1.168
     * logradouros, medidos em 2026-10-03 — e a via local não está lá. A
     * hierarquia dela vem da camada de logradouros, atributo HIERARQUIA
     * (decisão 2 da ata SEDUR de 2026-10-02).
     *
     * Falha nessa segunda camada NÃO derruba a consulta: devolve
     * `nao_encontrado`, como antes de existir o fallback.
     */
    private function hierarquiaDoLogradouro(string $codLog): ClassificacaoViariaHit
    {
        $semClasse = new ClassificacaoViariaHit(
            status: ClassificacaoViariaHit::NAO_ENCONTRADO,
            classe: null,
            classeBruta: null,
            motivo: null,
        );

        try {
            $response = Http::withHeaders([
                'User-Agent' => (string) config('viabiliza.integrations.geoserver.user_agent', 'Viabiliza-SEDUR-Salvador/1.0'),
            ])
                ->timeout((int) config('viabiliza.integrations.geoserver.timeout', 8))
                ->connectTimeout(3)
                ->acceptJson()
                ->get($this->url($codLog, $this->typeNameLogradouro()));
        } catch (Throwable) {
            return $semClasse;
        }

        if (! $response->successful()) {
            return $semClasse;
        }

        $payload = $response->json();
        $features = is_array($payload) ? ($payload['features'] ?? []) : [];

        if (! is_array($features) || $features === []) {
            return $semClasse;
        }

        $atributo = (string) config('viabiliza.integrations.geoserver.logradouro.atributo_hierarquia', 'HIERARQUIA');
        $properties = is_array($features[0]['properties'] ?? null) ? $features[0]['properties'] : [];
        $bruta = $properties[$atributo] ?? null;
        $bruta = is_string($bruta) && trim($bruta) !== '' ? trim($bruta) : null;

        if ($bruta === null) {
            return $semClasse;
        }

        $classe = Quadro11aClasseVia::oficializar($bruta);

        if ($classe === null) {
            return new ClassificacaoViariaHit(
                status: ClassificacaoViariaHit::INDISPONIVEL,
                classe: null,
                classeBruta: $bruta,
                motivo: 'Hierarquia do logradouro fora das classes do Quadro 11A.',
            );
        }

        return new ClassificacaoViariaHit(
            status: ClassificacaoViariaHit::IDENTIFICADO,
            classe: $classe,
            classeBruta: $bruta,
            motivo: null,
        );
    }

    private function typeNameLogradouro(): string
    {
        return (string) config(
            'viabiliza.integrations.geoserver.logradouro.type_name',
            'logradouros:VM_LOGRADOURO',
        );
    }

    private function url(string $codLog, ?string $typeName = null): string
    {
        $base = rtrim((string) Settings::get(
            'integrations.geoserver.base_url',
            config('viabiliza.integrations.geoserver.base_url'),
        ), '/');

        $typeName ??= (string) config(
            'viabiliza.integrations.geoserver.classificacao_viaria.type_name',
            'louos_classificacao_viaria:VM_L_CLASSIF_VIARIA',
        );

        return $base.'/wfs?'.http_build_query([
            'service' => 'WFS',
            'version' => '1.1.0',
            'request' => 'GetFeature',
            'typeName' => $typeName,
            'outputFormat' => 'application/json',
            'srsName' => 'EPSG:4326',
            'maxFeatures' => 1,
            'CQL_FILTER' => sprintf('CODLOG=%s', $codLog),
        ]);
    }
}
