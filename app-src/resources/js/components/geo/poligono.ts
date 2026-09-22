/**
 * Helpers puros do polígono da vistoria — SEM import de Leaflet, para poderem
 * ser usados pela página (SSR-safe). O componente de mapa (`map-poligono.tsx`)
 * importa daqui; quem só precisa converter/calcular não puxa o Leaflet.
 */

export interface GeoJsonPolygon {
    type: 'Polygon';
    coordinates: number[][][];
}

/** Vértice no padrão Leaflet [lat, lng]. */
export type Vertice = [number, number];

/**
 * Converte o anel GeoJSON [lng, lat] (fechado) em vértices Leaflet
 * [lat, lng] sem a duplicata de fechamento.
 */
export function geojsonParaVertices(poligono: GeoJsonPolygon | null): Vertice[] {
    const anel = poligono?.coordinates?.[0];

    if (!Array.isArray(anel)) {
        return [];
    }

    const vertices = anel.map((posicao) => [Number(posicao[1]), Number(posicao[0])] as Vertice);

    // Remove o fechamento duplicado (primeira == última posição).
    if (vertices.length > 1) {
        const primeira = vertices[0];
        const ultima = vertices[vertices.length - 1];

        if (primeira[0] === ultima[0] && primeira[1] === ultima[1]) {
            vertices.pop();
        }
    }

    return vertices;
}

/**
 * Converte os vértices [lat, lng] em GeoJSON Polygon com anel FECHADO
 * (primeira posição repetida ao final) — o formato persistido no backend.
 */
export function verticesParaGeoJson(vertices: Vertice[]): GeoJsonPolygon | null {
    if (vertices.length < 3) {
        return null;
    }

    const anel = vertices.map(([lat, lng]) => [lng, lat]);
    anel.push([vertices[0][1], vertices[0][0]]);

    return { type: 'Polygon', coordinates: [anel] };
}

/**
 * Área aproximada (shoelace planar, graus → metros no entorno do polígono) —
 * mesma fórmula do PropertyGeometryWriter para SQLite. Prévia honesta na tela;
 * a área oficial gravada na validação vem do backend (ST_Area no pgsql).
 */
export function areaPoligonoM2(vertices: Vertice[]): number | null {
    if (vertices.length < 3) {
        return null;
    }

    const pontos = [...vertices, vertices[0]];
    const latMedia = pontos.reduce((soma, [lat]) => soma + lat, 0) / pontos.length;
    const metrosPorGrauLat = 111_320.0;
    const metrosPorGrauLng = 111_320.0 * Math.cos((latMedia * Math.PI) / 180);

    let soma = 0;

    for (let i = 0; i < pontos.length - 1; i++) {
        const x1 = pontos[i][1] * metrosPorGrauLng;
        const y1 = pontos[i][0] * metrosPorGrauLat;
        const x2 = pontos[i + 1][1] * metrosPorGrauLng;
        const y2 = pontos[i + 1][0] * metrosPorGrauLat;

        soma += x1 * y2 - x2 * y1;
    }

    return Math.round((Math.abs(soma) / 2) * 100) / 100;
}
