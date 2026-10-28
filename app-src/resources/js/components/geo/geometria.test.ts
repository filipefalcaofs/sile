import { describe, expect, it } from 'vitest';
import type { GeoJsonObject } from 'geojson';
import { centroDaGeometria, chaveDaGeometria } from './geometria';

const anel = [
    [-38.5, -12.98],
    [-38.498, -12.98],
    [-38.498, -12.978],
    [-38.5, -12.978],
    [-38.5, -12.98],
];

describe('centroDaGeometria', () => {
    it('calcula o centro do anel exterior de um Polygon sem repetir o vértice de fechamento', () => {
        const centro = centroDaGeometria({ type: 'Polygon', coordinates: [anel] } as never);

        expect(centro?.lat).toBeCloseTo(-12.979, 6);
        expect(centro?.lng).toBeCloseTo(-38.499, 6);
    });

    it('usa o primeiro polígono de um MultiPolygon', () => {
        const centro = centroDaGeometria({ type: 'MultiPolygon', coordinates: [[anel]] } as never);

        expect(centro?.lat).toBeCloseTo(-12.979, 6);
        expect(centro?.lng).toBeCloseTo(-38.499, 6);
    });

    it('devolve null sem geometria', () => {
        expect(centroDaGeometria(null)).toBeNull();
    });

    it('devolve null quando as coordenadas não são numéricas ou estão fora de faixa', () => {
        const nulas = { type: 'Polygon', coordinates: [[[null, null], [null, null], [null, null]]] } as never;
        const texto = { type: 'Polygon', coordinates: [[['x', 'y'], ['x', 'y'], ['x', 'y']]] } as never;
        const fora = { type: 'Polygon', coordinates: [[[500, 500], [501, 500], [501, 501], [500, 500]]] } as never;

        expect(centroDaGeometria(nulas)).toBeNull();
        expect(centroDaGeometria(texto)).toBeNull();
        expect(centroDaGeometria(fora)).toBeNull();
    });

    it('centro de Feature com Polygon usa a geometria', () => {
        const feature = {
            type: 'Feature',
            properties: {},
            geometry: { type: 'Polygon', coordinates: [[[0, 0], [2, 0], [2, 2], [0, 2], [0, 0]]] },
        } as GeoJsonObject;

        expect(centroDaGeometria(feature)).toEqual({ lat: 1, lng: 1 });
    });
});

describe('chaveDaGeometria', () => {
    it('muda quando as coordenadas mudam e é estável para o mesmo conteúdo', () => {
        const a = { type: 'Polygon', coordinates: [anel] } as GeoJsonObject;
        const b = { type: 'Polygon', coordinates: [[...anel.slice(0, -1), [-38.499, -12.98], anel[0]]] } as GeoJsonObject;

        expect(chaveDaGeometria(a)).not.toBe(chaveDaGeometria(b));
        expect(chaveDaGeometria(a)).toBe(chaveDaGeometria(structuredClone(a)));
    });
});
