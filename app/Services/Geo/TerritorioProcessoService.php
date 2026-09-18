<?php

namespace App\Services\Geo;

use App\Models\ViabilityRequest;

/**
 * Materialização do território no processo (Onda GIS): com o acesso full ao
 * GIS/SEDUR validado em produção, a zona oficial (GeoServer WFS) e o bairro
 * oficial (camada GeoSalvador versionada) deixam de ser consultados ao vivo e
 * descartados — são GRAVADOS em viability_requests (zona_codigo /
 * bairro_oficial) na identificação do imóvel e como rede de segurança no
 * protocolo. Write-once e conservador: só escreve quando a dimensão foi
 * IDENTIFICADA; indisponível/não encontrado NUNCA sobrescreve um valor já
 * materializado (a degradação não apaga dado bom). Sem polígono, não há ponto
 * — nada a materializar.
 */
class TerritorioProcessoService
{
    public function __construct(private readonly TerritoryService $territory) {}

    /**
     * Materializa zona/bairro no processo. Reusa um TerritoryResult já
     * computado quando o chamador o tem (a tela do imóvel já identificou —
     * evita a segunda consulta WFS); senão identifica pelo centroide do
     * polígono. Retorna true quando gravou algo.
     */
    public function materializar(ViabilityRequest $request, ?TerritoryResult $territorio = null): bool
    {
        if ($territorio === null) {
            $ponto = $this->centroid($request->property_polygon_geojson);

            if ($ponto === null) {
                return false;
            }

            $territorio = $this->territory->identify($ponto['lat'], $ponto['lng']);
        }

        $mudou = false;

        $zona = $this->valorIdentificado($territorio->zona);
        if ($zona !== null && $request->zona_codigo !== $zona) {
            $request->zona_codigo = $zona;
            $mudou = true;
        }

        $bairro = $this->valorIdentificado($territorio->bairro);
        if ($bairro !== null && $request->bairro_oficial !== $bairro) {
            $request->bairro_oficial = $bairro;
            $mudou = true;
        }

        if ($mudou) {
            // Colunas fora do fillable (escritas pelo sistema, nunca pelo
            // cidadão) — mesmo padrão das colunas de análise.
            $request->forceFill([
                'zona_codigo' => $request->zona_codigo,
                'bairro_oficial' => $request->bairro_oficial,
            ])->save();
        }

        return $mudou;
    }

    /**
     * Nome/código da dimensão SÓ quando identificado — indisponível ou não
     * encontrado devolvem null (nunca sobrescrevem o materializado).
     *
     * @param  array<string, mixed>  $dim
     */
    private function valorIdentificado(array $dim): ?string
    {
        if (($dim['status'] ?? null) !== 'identificado') {
            return null;
        }

        $nome = $dim['nome'] ?? null;

        return is_string($nome) && trim($nome) !== '' ? trim($nome) : null;
    }

    /**
     * Centroide (lat, lng) pela média dos vértices únicos do anel exterior —
     * mesma aproximação da SolicitacaoViabilityResolver. null sem polígono
     * confiável (nunca uma coordenada inventada).
     *
     * @param  array<string, mixed>|null  $geojson
     * @return array{lat: float, lng: float}|null
     */
    private function centroid(?array $geojson): ?array
    {
        $anel = $geojson['coordinates'][0] ?? null;

        if (! is_array($anel) || count($anel) < 3) {
            return null;
        }

        $vertices = [];
        foreach ($anel as $vertice) {
            if (is_array($vertice) && count($vertice) >= 2 && is_numeric($vertice[0]) && is_numeric($vertice[1])) {
                $vertices[(string) $vertice[0].','.(string) $vertice[1]] = [(float) $vertice[0], (float) $vertice[1]];
            }
        }

        if ($vertices === []) {
            return null;
        }

        $lng = array_sum(array_column($vertices, 0)) / count($vertices);
        $lat = array_sum(array_column($vertices, 1)) / count($vertices);

        return ['lat' => $lat, 'lng' => $lng];
    }
}
