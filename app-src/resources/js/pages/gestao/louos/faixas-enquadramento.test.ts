import { describe, expect, it } from 'vitest';
import { apresentacaoClassificacao, apresentacaoRisco, faixasDaLinha, textoFaixa2, textoFaixa3 } from './faixas-enquadramento';

const escritorio = {
    subcategoria: 'nR1-12',
    ate_m2: '1250.00',
    enquadramento2: 'nR2-12',
    ate_m2_2: '1250.01',
    enquadramento3: null,
    acima_m2: null,
};

const tresFaixas = {
    subcategoria: 'nR1-08',
    ate_m2: '500.00',
    enquadramento2: 'nR2-08',
    ate_m2_2: '5000.00',
    enquadramento3: 'nR3-08',
    acima_m2: '5000.01',
};

describe('faixas do enquadramento', () => {
    it('não coloca só o código da subcategoria na faixa 2', () => {
        expect(textoFaixa2(escritorio)).toBe('nR2-12 acima de 1.250 m²');
        expect(textoFaixa2(escritorio)).not.toBe('nR2-12');
    });

    it('mostra o teto da faixa do meio e o piso da última quando há três bandas', () => {
        expect(textoFaixa2(tresFaixas)).toBe('nR2-08 até 5.000 m²');
        expect(textoFaixa3(tresFaixas)).toBe('nR3-08 acima de 5.000 m²');
    });

    it('omite a faixa quando a banda não existe', () => {
        expect(textoFaixa2({ ...escritorio, enquadramento2: null, ate_m2_2: null })).toBe('—');
        expect(textoFaixa3(escritorio)).toBe('—');
    });

    it('empilha as bandas com o corte ao lado do uso', () => {
        expect(faixasDaLinha(escritorio)).toEqual([
            { codigo: 'nR1-12', limite: 'até 1.250 m²' },
            { codigo: 'nR2-12', limite: 'acima de 1.250 m²' },
        ]);
        expect(faixasDaLinha(tresFaixas)).toEqual([
            { codigo: 'nR1-08', limite: 'até 500 m²' },
            { codigo: 'nR2-08', limite: 'até 5.000 m²' },
            { codigo: 'nR3-08', limite: 'acima de 5.000 m²' },
        ]);
    });

    it('marca uso sem corte como qualquer área', () => {
        expect(
            faixasDaLinha({
                subcategoria: 'nRa-02',
                ate_m2: null,
                enquadramento2: null,
                ate_m2_2: null,
                enquadramento3: null,
                acima_m2: null,
            }),
        ).toEqual([{ codigo: 'nRa-02', limite: null }]);
    });
});

describe('selos da linha', () => {
    it('distingue risco e classificação sem o texto cru da planilha', () => {
        expect(apresentacaoRisco('BAIXO RISCO')).toEqual({ rotulo: 'Baixo', cor: 'success' });
        expect(apresentacaoRisco('A SER DEFINIDO PELA CNLU').rotulo).toBe('A definir pela CNLU');
        expect(apresentacaoClassificacao('VALIDAÇÃO')).toEqual({ rotulo: 'Validação', cor: 'info' });
        expect(apresentacaoClassificacao('EXPRESSO')?.cor).toBe('success');
        expect(apresentacaoClassificacao('SEMI EXPRESSO')?.rotulo).toBe('Semi expresso');
    });
});
