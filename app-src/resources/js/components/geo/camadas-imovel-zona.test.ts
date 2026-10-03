import { describe, expect, it } from 'vitest';
import { textoZona } from './camadas-imovel-zona';

describe('textoZona', () => {
    it('mostra a fonte e o percentual de cada porção quando há mais de uma', () => {
        expect(
            textoZona({
                status: 'identificado',
                nome: 'ZPR 3',
                fonte: 'enderecamento_maior_porcao',
                porcoes: [
                    { zona: 'ZPR 3', area_m2: 780, percentual: 78 },
                    { zona: 'ZEIS', area_m2: 220, percentual: 22 },
                ],
            }),
        ).toBe('ZPR 3 — maior porção do lote (ZPR 3 78,0% · ZEIS 22,0%)');
    });

    it('omite os percentuais com uma porção só', () => {
        expect(
            textoZona({
                status: 'identificado',
                nome: 'ZPR 3',
                fonte: 'enderecamento_maior_porcao',
                porcoes: [{ zona: 'ZPR 3', area_m2: 1000, percentual: 100 }],
            }),
        ).toBe('ZPR 3 — maior porção do lote');
    });

    it('marca a zona do ponto da inscrição', () => {
        expect(textoZona({ status: 'identificado', nome: 'ZEIS', fonte: 'ponto', porcoes: [] })).toBe('ZEIS — ponto da inscrição');
    });

    it('mantém o texto antigo sem fonte e nos estados degradados', () => {
        expect(textoZona({ status: 'identificado', nome: 'ZCMe 1/01' })).toBe('ZCMe 1/01');
        expect(textoZona({ status: 'indisponivel', nome: null })).toBe('Camada indisponível');
        expect(textoZona({ status: 'nao_encontrado', nome: null })).toBe('Não identificado');
        expect(textoZona(null)).toBe('—');
    });
});
