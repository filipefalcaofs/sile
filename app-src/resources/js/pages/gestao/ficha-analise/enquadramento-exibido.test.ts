import { describe, expect, it } from 'vitest';
import { enquadramentoExibido, quadrosExibidos } from './enquadramento-exibido';

const motor = {
    codigo_louos: '1.2.3',
    codigo_tll: '2.02',
    grupo: 'nR1',
    subgrupo: 'nR1-08',
};

const opcaoEscritorio = {
    subgrupo: 'nR1-03',
    subgrupo_area: 'nR1-03',
    grupo_area: 'nR1',
};

describe('enquadramentoExibido', () => {
    it('mantém o subgrupo do motor enquanto o analista não troca a opção', () => {
        const item = {
            codigo_louos: '1.2.3',
            codigo_tll: '2.02',
            grupo_uso: 'nR1',
            subgrupo_uso: 'nR1-08',
            enquadramento_motor: motor,
        };

        expect(
            enquadramentoExibido(item, {
                subgrupo: 'nR1-08',
                subgrupo_area: 'nR1-08',
                grupo_area: 'nR1',
            }),
        ).toEqual({
            subgrupo: 'nR1-08',
            grupo: 'nR1',
            alterado: false,
            subgrupoMotor: 'nR1-08',
        });
    });

    it('troca o subgrupo quando o analista escolhe outra opção', () => {
        const item = {
            codigo_louos: '4.5.6',
            codigo_tll: '3.01',
            grupo_uso: 'nR1',
            subgrupo_uso: 'nR1-08',
            enquadramento_motor: motor,
        };

        expect(enquadramentoExibido(item, opcaoEscritorio)).toEqual({
            subgrupo: 'nR1-03',
            grupo: 'nR1',
            alterado: true,
            subgrupoMotor: 'nR1-08',
        });
    });

    it('troca também o grupo quando a área leva a outra faixa', () => {
        const item = {
            codigo_louos: '4.5.6',
            codigo_tll: null,
            grupo_uso: 'nR1',
            subgrupo_uso: 'nR1-08',
            enquadramento_motor: motor,
        };

        expect(
            enquadramentoExibido(item, {
                subgrupo: 'nR1-05',
                subgrupo_area: 'nR2-12',
                grupo_area: 'nR2',
            }),
        ).toMatchObject({
            subgrupo: 'nR2-12',
            grupo: 'nR2',
            alterado: true,
        });
    });

    it('deixa o subgrupo em aberto quando a nova opção não fecha a faixa', () => {
        const item = {
            codigo_louos: '4.5.6',
            codigo_tll: '3.01',
            grupo_uso: 'nR1',
            subgrupo_uso: 'nR1-08',
            enquadramento_motor: motor,
        };

        expect(
            enquadramentoExibido(item, {
                subgrupo: 'nR1-03',
                subgrupo_area: null,
                grupo_area: null,
            }),
        ).toMatchObject({
            subgrupo: null,
            grupo: null,
            alterado: true,
        });
    });

    it('volta ao subgrupo do motor quando o analista desfaz a troca', () => {
        const item = {
            codigo_louos: '1.2.3',
            codigo_tll: '2.02',
            grupo_uso: 'nR1',
            subgrupo_uso: 'nR1-08',
            enquadramento_motor: motor,
        };

        expect(enquadramentoExibido(item, opcaoEscritorio).subgrupo).toBe('nR1-08');
    });

    it('sem enquadramento do motor usa o da opção selecionada', () => {
        const item = {
            codigo_louos: '4.5.6',
            codigo_tll: '3.01',
            grupo_uso: null,
            subgrupo_uso: null,
        };

        expect(enquadramentoExibido(item, opcaoEscritorio)).toEqual({
            subgrupo: 'nR1-03',
            grupo: 'nR1',
            alterado: false,
            subgrupoMotor: null,
        });
    });
});

const quadroMotor = [{ key: 'quadro10', label: 'Quadro 10', estado: 'permitido', detalhe: 'NR1-08 na zona ZR-1 permitido.' }];
const quadroOpcao = [{ key: 'quadro10', label: 'Quadro 10', estado: 'nao_permitido', detalhe: 'NR2-12 na zona ZR-1 proibido.' }];

describe('quadrosExibidos', () => {
    it('sem troca mostra os quadros do motor', () => {
        expect(quadrosExibidos(false, quadroMotor, { quadros: quadroOpcao })).toEqual({ quadros: quadroMotor, recalculados: false });
    });

    it('com troca mostra os quadros recalculados para a opção escolhida', () => {
        expect(quadrosExibidos(true, quadroMotor, { quadros: quadroOpcao })).toEqual({ quadros: quadroOpcao, recalculados: true });
    });

    it('com troca e sem território gravado mantém os quadros do motor', () => {
        expect(quadrosExibidos(true, quadroMotor, { quadros: null })).toEqual({ quadros: quadroMotor, recalculados: false });
        expect(quadrosExibidos(true, quadroMotor, undefined)).toEqual({ quadros: quadroMotor, recalculados: false });
    });
});
