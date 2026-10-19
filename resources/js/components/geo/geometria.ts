import type { GeoJsonObject, MultiPolygon, Polygon } from 'geojson';
import type { PathOptions } from 'leaflet';

/** Contorno do polígono do endereçamento SEDUR, distinto do desenho do requerente. */
export const ESTILO_ENDERECAMENTO: PathOptions = { color: '#f79009', weight: 2, dashArray: '6 4', fillOpacity: 0.08 };

/**
 * Centro aproximado (média dos vértices do anel exterior) para centralizar o
 * mini-mapa. Null quando a geometria não é Polygon/MultiPolygon.
 */
export function centroDaGeometria(geometria: GeoJsonObject | null | undefined): { lat: number; lng: number } | null {
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

/** Fonte do polígono do lote, com a legenda do contorno e a procedência das fontes complementares. */
export interface FontePoligono {
    value: string;
    legenda: string;
    atribuicao: string | null;
    oficial: boolean;
    procedencia: { source_id: string | null; release: string | null; obtido_em: string | null; area_m2: number | null } | null;
}

/** "Overture · id gers-9 · 19/10/2026 · 135,5 m²", a partir da legenda e da procedência. */
export function resumoDaProcedencia(fonte: FontePoligono): string | null {
    if (fonte.oficial || fonte.procedencia === null) {
        return null;
    }

    const nome = fonte.legenda.replace(/^Contorno tracejado: polígono (do |da )?/, '').replace(/\.$/, '');
    const { source_id, obtido_em, area_m2 } = fonte.procedencia;
    const partes = [
        `Fonte: ${nome.charAt(0).toUpperCase()}${nome.slice(1)}`,
        source_id ? `id ${source_id}` : null,
        obtido_em ? new Date(obtido_em).toLocaleDateString('pt-BR') : null,
        area_m2 !== null ? `${area_m2.toLocaleString('pt-BR')} m²` : null,
    ];

    return partes.filter((parte): parte is string => parte !== null).join(' · ');
}

