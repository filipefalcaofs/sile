import type { Polygon } from 'geojson';
import { describe, expect, it } from 'vitest';
import {
    type DadosMapaDecisao,
    formatarArea,
    formatarMetros,
    papelDaPorcao,
    rotuloDaLinha,
    rotuloDaPorcao,
    rotuloDaZonaDoLote,
    temCamadasDeContexto,
} from './mapa-decisao-dados';

const poligono: Polygon = { type: 'Polygon', coordinates: [[[0, 0], [1, 0], [1, 1], [0, 0]]] };

const base: DadosMapaDecisao = {
    lote: poligono,
    enderecamento: null,
    ponto: null,
    area_lote_m2: 1962,
    zona: { codigo: 'ZCMu 2', fonte: 'Maior porção do lote' },
    via: null,
    zonas: [],
    porcoes: [],
    linhas: [],
    vias: [],
    indisponiveis: [],
};

describe('formatação', () => {
    it('usa vírgula decimal e separador de milhar do pt-BR', () => {
        expect(formatarMetros(38.4)).toBe('38,4 m');
        expect(formatarArea(1228)).toBe('1.228 m²');
    });
});

describe('rotuloDaPorcao', () => {
    it('mostra zona, área e percentual da porção', () => {
        const porcao = { zona: 'ZCMu 2', area_m2: 1228, percentual: 62.6, da_decisao: true, geojson: poligono };

        expect(rotuloDaPorcao([porcao], porcao)).toBe('ZCMu 2: 1.228 m² (63%)');
    });
});

describe('papelDaPorcao', () => {
    const maior = { zona: 'ZCMu 2', area_m2: 1228, percentual: 63, da_decisao: true, geojson: poligono };
    const menor = { zona: 'ZPR 3', area_m2: 735, percentual: 37, da_decisao: false, geojson: poligono };

    it('separa a maior porção da menor quando o lote está em duas zonas', () => {
        expect(papelDaPorcao([maior, menor], maior)).toBe('maior');
        expect(papelDaPorcao([maior, menor], menor)).toBe('menor');
    });

    it('trata a porção única como única', () => {
        expect(papelDaPorcao([maior], maior)).toBe('unica');
    });
});

describe('rotuloDaZonaDoLote', () => {
    it('diz maior porção quando o lote tem mais de uma zona', () => {
        const porcao = { zona: 'ZCMu 2', area_m2: 1, percentual: 50, da_decisao: true, geojson: poligono };

        expect(rotuloDaZonaDoLote({ ...base, porcoes: [porcao, porcao] })).toBe('ZCMu 2 · maior porção do lote');
    });

    it('diferencia a zona do lote da zona do ponto', () => {
        expect(rotuloDaZonaDoLote({ ...base, zona: { codigo: 'ZPR 3', fonte: 'Maior porção do lote' } })).toBe('ZPR 3 · zona do lote');
        expect(rotuloDaZonaDoLote({ ...base, zona: { codigo: 'ZPR 3', fonte: 'Ponto da inscrição' } })).toBe('ZPR 3 · zona do ponto');
        expect(rotuloDaZonaDoLote({ ...base, zona: null })).toBeNull();
    });
});

describe('rotuloDaLinha', () => {
    const linha = { tipo: 'ZCLMu' as const, nome: 'Av. ACM', geojson: poligono };

    it('mede a linha dentro do lote quando passa por cima', () => {
        expect(rotuloDaLinha({ ...linha, passa_sobre_lote: true, dentro_m: 142.7, distancia_m: 0 })).toBe('Linha da ZCLMu dentro do lote: 142,7 m');
    });

    it('mede a distância até a linha quando passa em frente', () => {
        expect(rotuloDaLinha({ ...linha, passa_sobre_lote: false, dentro_m: 0, distancia_m: 38.4, ligacao: poligono })).toBe('38,4 m até a linha da ZCLMu');
    });

    it('não rotula quando não há medida', () => {
        expect(rotuloDaLinha(linha)).toBeNull();
    });
});

describe('temCamadasDeContexto', () => {
    it('exige lote ou ponto e ao menos uma camada em volta', () => {
        expect(temCamadasDeContexto(base)).toBe(false);
        expect(temCamadasDeContexto({ ...base, zonas: [{ codigo: 'ZPR 3', da_decisao: true, geojson: poligono }] })).toBe(true);
        expect(temCamadasDeContexto({ ...base, lote: null, zonas: [{ codigo: 'ZPR 3', da_decisao: true, geojson: poligono }] })).toBe(false);
    });
});
