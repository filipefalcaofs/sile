import type { Feature, GeoJsonObject, MultiPolygon, Polygon } from 'geojson';
import type { PathOptions } from 'leaflet';

/** Contorno do polígono do endereçamento SEDUR, distinto do desenho do requerente. */
export const ESTILO_ENDERECAMENTO: PathOptions = { color: '#f79009', weight: 2, dashArray: '6 4', fillOpacity: 0.08 };

/**
 * Centro aproximado (média dos vértices do anel exterior) para centralizar o
 * mini-mapa. Aceita a geometria solta ou dentro de uma Feature. Null quando
 * não é Polygon/MultiPolygon.
 */
export function centroDaGeometria(entrada: GeoJsonObject | null | undefined): { lat: number; lng: number } | null {
    const geometria: GeoJsonObject | null | undefined = entrada?.type === 'Feature' ? (entrada as Feature).geometry : entrada;
    let anel: number[][] | undefined;

    if (geometria?.type === 'Polygon') {
        anel = (geometria as Polygon).coordinates[0];
    } else if (geometria?.type === 'MultiPolygon') {
        anel = (geometria as MultiPolygon).coordinates[0]?.[0];
    }

    if (!anel || anel.length === 0) {
        return null;
    }

    const primeiro = anel[0];
    const ultimo = anel[anel.length - 1];
    const vertices = anel.length > 1 && primeiro[0] === ultimo[0] && primeiro[1] === ultimo[1] ? anel.slice(0, -1) : anel;
    const soma = vertices.reduce((acc, [lng, lat]) => ({ lat: acc.lat + lat, lng: acc.lng + lng }), { lat: 0, lng: 0 });

    return { lat: soma.lat / vertices.length, lng: soma.lng / vertices.length };
}

/**
 * Chave curta e estável do conteúdo da geometria (hash djb2 do JSON): força o
 * react-leaflet a redesenhar o contorno quando as coordenadas mudam.
 */
export function chaveDaGeometria(geometria: GeoJsonObject): string {
    const texto = JSON.stringify(geometria);
    let hash = 5381;

    for (let i = 0; i < texto.length; i++) {
        hash = ((hash << 5) + hash + texto.charCodeAt(i)) | 0;
    }

    return (hash >>> 0).toString(16);
}
