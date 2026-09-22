<?php

namespace App\Services\Abuso\Detectors;

use App\Enums\AbuseSeverity;
use App\Models\ViabilityRequest;
use App\Services\Abuso\AbuseFinding;
use App\Services\Abuso\Contracts\AbuseDetector;
use App\Services\Abuso\DetectionWindow;

/**
 * HU-149 (estrutural): o MESMO polígono de imóvel declarado em ENDEREÇOS
 * DISTINTOS na janela — sinal de geometria "reaproveitada" para forçar um local
 * conveniente. Determinístico sobre dado real, SEM IA: agrupa viability_requests
 * por uma CHAVE NORMALIZADA do geojson (hash do anel canonicalizado — roda em
 * SQLite, sem PostGIS) e emite um finding quando o polígono aparece em
 * `MINIMO_ENDERECOS`+ endereços diferentes. fingerprint estável por (regra,
 * polígono) → idempotência da 12-03. Apenas ALERTA, nunca pune (RN-001).
 *
 * A igualdade espacial PLENA (ST_Equals — tolerância, projeção, sobreposição
 * parcial) é refinamento futuro: aqui a equivalência é por anel normalizado
 * (arredondamento + rotação + sentido canônicos), que casa representações
 * diferentes do MESMO anel sem depender do banco.
 */
class PoligonoRepetidoDetector implements AbuseDetector
{
    /** Mínimo de endereços distintos com o mesmo polígono para alertar (provisório — SEDUR calibra). */
    private const MINIMO_ENDERECOS = 2;

    public function key(): string
    {
        return 'poligono_repetido';
    }

    /**
     * @return iterable<AbuseFinding>
     */
    public function detect(DetectionWindow $window): iterable
    {
        $solicitacoes = ViabilityRequest::query()
            ->whereNotNull('property_polygon_geojson')
            ->whereBetween('created_at', [$window->start, $window->end])
            ->orderBy('id')
            ->get(['id', 'property_polygon_geojson', 'address_street', 'address_number', 'address_neighborhood', 'address_zip']);

        /** @var array<string, array{ids: list<int>, enderecos: array<string, true>}> $grupos */
        $grupos = [];

        foreach ($solicitacoes as $solicitacao) {
            $chave = $this->normalizedPolygonKey($solicitacao->property_polygon_geojson);

            if ($chave === null) {
                continue;
            }

            $grupos[$chave]['ids'][] = (int) $solicitacao->id;
            $grupos[$chave]['enderecos'][$this->addressKey($solicitacao)] = true;
        }

        foreach ($grupos as $chave => $grupo) {
            $enderecos = array_keys($grupo['enderecos']);
            $enderecosDistintos = count($enderecos);

            if ($enderecosDistintos < self::MINIMO_ENDERECOS) {
                continue;
            }

            $ids = $grupo['ids'];

            yield new AbuseFinding(
                ruleKey: $this->key(),
                severity: $enderecosDistintos > self::MINIMO_ENDERECOS * 2 ? AbuseSeverity::Alta : AbuseSeverity::Media,
                fingerprint: hash('sha256', $this->key().'|'.$chave),
                evidence: [
                    'poligono_hash' => $chave,
                    'enderecos' => $enderecos,
                    'enderecos_distintos' => $enderecosDistintos,
                    'minimo' => self::MINIMO_ENDERECOS,
                    'ids' => $ids,
                ],
                viabilityRequestId: $ids === [] ? null : max($ids),
                subject: null,
                windowStart: $window->start,
                windowEnd: $window->end,
            );
        }
    }

    /**
     * Chave estável do polígono: arredonda as coordenadas, remove o vértice de
     * fechamento e canonicaliza rotação e sentido do anel — assim duas
     * representações do MESMO anel (vértice inicial ou winding diferentes) geram
     * o mesmo hash. Sem anel válido → null (a solicitação não entra no grupo).
     *
     * @param  array<string, mixed>|null  $geojson
     */
    private function normalizedPolygonKey(?array $geojson): ?string
    {
        /** @var array<int, array<int, float|int>>|null $ring */
        $ring = $geojson['coordinates'][0] ?? null;

        if (! is_array($ring) || count($ring) < 3) {
            return null;
        }

        $pontos = array_map(
            static fn (array $ponto): array => [round((float) $ponto[0], 6), round((float) $ponto[1], 6)],
            $ring,
        );

        if (count($pontos) > 1 && $pontos[0] === $pontos[count($pontos) - 1]) {
            array_pop($pontos);
        }

        if (count($pontos) < 3) {
            return null;
        }

        return hash('sha256', (string) json_encode($this->canonicalRing($pontos)));
    }

    /**
     * Anel canônico: o menor entre a sequência (rotacionada ao vértice mínimo) e
     * a sua reversa (idem), neutralizando vértice inicial e sentido do winding.
     *
     * @param  list<array<int, float>>  $pontos
     * @return list<array<int, float>>
     */
    private function canonicalRing(array $pontos): array
    {
        $frente = $this->rotateToMin($pontos);
        $reverso = $this->rotateToMin(array_reverse($pontos));

        return json_encode($frente) <= json_encode($reverso) ? $frente : $reverso;
    }

    /**
     * Rotaciona o anel para começar no vértice lexicograficamente mínimo.
     *
     * @param  list<array<int, float>>  $pontos
     * @return list<array<int, float>>
     */
    private function rotateToMin(array $pontos): array
    {
        $indiceMin = 0;

        foreach ($pontos as $indice => $ponto) {
            if ($ponto < $pontos[$indiceMin]) {
                $indiceMin = $indice;
            }
        }

        return array_merge(array_slice($pontos, $indiceMin), array_slice($pontos, 0, $indiceMin));
    }

    /**
     * Assinatura normalizada do endereço (logradouro/número/bairro/CEP) para
     * contar endereços DISTINTOS — variação de caixa/espaços não cria diferença.
     */
    private function addressKey(ViabilityRequest $solicitacao): string
    {
        $partes = array_map(
            static fn (?string $valor): string => mb_strtolower(trim((string) $valor)),
            [
                $solicitacao->address_street,
                $solicitacao->address_number,
                $solicitacao->address_neighborhood,
                $solicitacao->address_zip,
            ],
        );

        return implode('|', $partes);
    }
}
