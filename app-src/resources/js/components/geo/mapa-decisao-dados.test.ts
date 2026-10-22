import type { Polygon } from 'geojson';
import { describe, expect, it } from 'vitest';
import {
    type DadosMapaDecisao,
    explicacaoDaDecisao,
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

describe('explicacaoDaDecisao', () => {
    const linha = { tipo: 'ZCLMu' as const, nome: 'Av. ACM', geojson: poligono };
    const porcao = (zona: string, area_m2: number, percentual: number, da_decisao: boolean) => ({ zona, area_m2, percentual, da_decisao, geojson: poligono });

    it('explica a maior porção, a linha mais próxima e a via com a classe', () => {
        const texto = explicacaoDaDecisao({
            ...base,
            porcoes: [porcao('ZCMu 2', 1228, 62.6, true), porcao('ZPR 3', 735, 37.4, false)],
            linhas: [
                { ...linha, passa_sobre_lote: false, dentro_m: 0, distancia_m: 759, ligacao: poligono },
                { ...linha, tipo: 'ZCLMe', passa_sobre_lote: false, dentro_m: 0, distancia_m: 38.4, ligacao: poligono },
            ],
            via: { cod_log: '2631', nome: 'Av. ACM', classe: 'ARTERIAL I' },
            vias: [{ cod_log: '2631', nome: 'Av. ACM', classe: 'ARTERIAL I', da_decisao: true, geojson: poligono }],
        });

        expect(texto).toEqual([
            'Zona ZCMu 2: maior porção do lote (63%, 1.228 m²). ZPR 3 ocupa 37% (735 m²).',
            'Nenhuma linha de ZCLMu ou ZCLMe passa por cima do lote. A mais próxima é a da ZCLMe, a 38,4 m.',
            'Via: Av. ACM (CODLOG 2631), classe ARTERIAL I.',
        ]);
    });

    it('diz quando o lote está todo numa zona e a linha passa por cima', () => {
        const texto = explicacaoDaDecisao({
            ...base,
            zona: { codigo: 'ZPR 3', fonte: 'Maior porção do lote' },
            porcoes: [porcao('ZPR 3', 3179, 100, true)],
            linhas: [{ ...linha, passa_sobre_lote: true, dentro_m: 142.7, distancia_m: 0, ligacao: null }],
        });

        expect(texto[0]).toBe('Zona ZPR 3: o lote está inteiro nela (3.179 m²).');
        expect(texto[1]).toBe('A linha da ZCLMu passa por cima do lote: 142,7 m dentro dele.');
    });

    it('cobre zona pelo ponto, nenhuma linha no entorno e via fora do recorte', () => {
        const texto = explicacaoDaDecisao({
            ...base,
            lote: null,
            zona: { codigo: 'ZPR 3', fonte: 'Ponto da inscrição' },
            via: { cod_log: '9', nome: null, classe: null },
        });

        expect(texto).toEqual([
            'Zona ZPR 3: zona onde está o ponto da inscrição, porque o processo não tem o desenho do lote.',
            'Nenhuma linha de ZCLMu ou ZCLMe no entorno do lote.',
            'Via: CODLOG 9, sem classe viária publicada. A via não aparece no recorte do mapa.',
        ]);
    });

    it('diz quando a zona não foi identificada e não há via', () => {
        expect(explicacaoDaDecisao({ ...base, zona: null })).toEqual([
            'Zona não identificada no processo.',
            'Nenhuma linha de ZCLMu ou ZCLMe no entorno do lote.',
        ]);
    });
});
