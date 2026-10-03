import { describe, expect, it } from 'vitest';
import { centroDaGeometria } from './geometria';

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
});
