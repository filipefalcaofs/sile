<?php

namespace App\Services\Analise;

use App\Models\AnalysisRecord;
use App\Support\Settings;
use Illuminate\Support\Carbon;

/**
 * Monta o painel de precedentes da ficha de análise (HU-142): decisões
 * anteriores no mesmo imóvel (CA-01) e estatística do CNAE na zona (CA-02),
 * aplicando os parâmetros administráveis (RN-003), minimizando dados pessoais
 * (LGPD, RN-004) e degradando honesto quando não há zona ou histórico (CA-03).
 *
 * O SQL espacial/agregado fica TODO atrás do PrecedentRepository (fake nos testes
 * SQLite, PostGIS real em produção/@group postgis) — este serviço só orquestra:
 * lê o polígono/endereço da solicitação, a ZONA do engine_snapshot da ficha
 * (caminho EXATO por_cnae[i].consulta.territorio.zona, shape do
 * ResolvedViability::toSnapshot da Fase 9) e os parâmetros via Settings, e devolve
 * o payload {imovel, cnae_zona} consumido pelo endpoint da ficha (10-09).
 */
class PrecedentService
{
    public function __construct(private readonly PrecedentRepository $precedents) {}

    /**
     * @return array{imovel: list<array{viability_request_id: int, protocol_number: ?string, outcome: string, decided_at: ?string, service_type: ?string, analyst: ?string}>, cnae_zona: array<string, mixed>}
     */
    public function forRecord(AnalysisRecord $record): array
    {
        $request = $record->viabilityRequest;

        $maxItens = (int) Settings::get('analise.precedentes.max_itens', 10);
        $janelaMeses = (int) Settings::get('analise.precedentes.janela_meses', 12);

        $geojson = is_array($request?->property_polygon_geojson) ? $request->property_polygon_geojson : [];

        $imovel = $this->precedents->propertyPrecedents(
            $geojson,
            $maxItens,
            $request?->address_street,
            $request?->address_number,
        );

        return [
            'imovel' => $imovel,
            'cnae_zona' => $this->cnaeZoneSection($record, $janelaMeses),
        ];
    }

    /**
     * Estatística do CNAE na zona — só com zona identificada no engine_snapshot;
     * sem zona, degrada honesto (estatística "indisponível", nunca inventada).
     *
     * @return array<string, mixed>
     */
    private function cnaeZoneSection(AnalysisRecord $record, int $janelaMeses): array
    {
        $item = $this->primaryCnaeItem($record->engine_snapshot);
        $cnae = is_array($item) ? (string) ($item['cnae'] ?? '') : '';
        $zonaNome = $this->identifiedZoneName($item);

        if ($cnae === '' || $zonaNome === null) {
            return [
                'disponivel' => false,
                'motivo' => 'zona urbanística pendente',
            ];
        }

        $since = Carbon::now()->subMonths($janelaMeses);
        $stats = $this->precedents->cnaeZoneStats($cnae, $zonaNome, $since);

        return [
            'disponivel' => true,
            'cnae' => $cnae,
            'cnae_formatado' => is_array($item) ? ($item['cnae_formatado'] ?? null) : null,
            'zona' => $zonaNome,
            'janela_meses' => $janelaMeses,
            'deferidos' => (int) ($stats['deferidos'] ?? 0),
            'indeferidos' => (int) ($stats['indeferidos'] ?? 0),
            'total' => (int) ($stats['total'] ?? 0),
        ];
    }

    /**
     * Item do CNAE principal do engine_snapshot (fallback: o primeiro). null sem
     * snapshot do motor (FA-01 / ficha sem motor).
     *
     * @param  array<string, mixed>|null  $snapshot
     * @return array<string, mixed>|null
     */
    private function primaryCnaeItem(?array $snapshot): ?array
    {
        $porCnae = $snapshot['por_cnae'] ?? null;

        if (! is_array($porCnae) || $porCnae === []) {
            return null;
        }

        foreach ($porCnae as $item) {
            if (is_array($item) && ($item['is_primary'] ?? false) === true) {
                return $item;
            }
        }

        $first = $porCnae[0] ?? null;

        return is_array($first) ? $first : null;
    }

    /**
     * Nome da zona SÓ quando o status é 'identificado' (caminho EXATO
     * consulta.territorio.zona). Qualquer outro status (indisponivel/
     * nao_encontrado — zona pendente SEDUR) devolve null para degradar honesto.
     *
     * @param  array<string, mixed>|null  $item
     */
    private function identifiedZoneName(?array $item): ?string
    {
        $zona = $item['consulta']['territorio']['zona'] ?? null;

        if (! is_array($zona) || ($zona['status'] ?? null) !== 'identificado') {
            return null;
        }

        $nome = $zona['nome'] ?? null;

        return is_string($nome) && $nome !== '' ? $nome : null;
    }
}
