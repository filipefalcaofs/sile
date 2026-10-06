import { describe, expect, it } from 'vitest';
import { comAreaEmbarqueInformada, vereditoEstacionamento } from './area-embarque';

describe('área de embarque e desembarque na ficha', () => {
    it('preenche o lado do requerente com o Sim declarado na recepção', () => {
        expect(comAreaEmbarqueInformada({}, 'Sim')).toEqual({
            area_embarque_requerente: 'sim',
        });
    });

    it('preenche o lado do requerente com o Não declarado na recepção', () => {
        expect(comAreaEmbarqueInformada({}, 'Não')).toEqual({
            area_embarque_requerente: 'nao',
        });
    });

    it('mantém o valor já gravado na ficha', () => {
        expect(
            comAreaEmbarqueInformada({ area_embarque_requerente: 'nao' }, 'Sim'),
        ).toEqual({ area_embarque_requerente: 'nao' });
    });

    it('deixa em branco quando a recepção não informou', () => {
        expect(comAreaEmbarqueInformada({ vagas_requeridas: 2 }, null)).toEqual({
            vagas_requeridas: 2,
        });
    });
});

describe('veredito de estacionamento', () => {
    it('permanece o das vagas enquanto a norma da área não foi informada', () => {
        expect(
            vereditoEstacionamento({
                vagasRequeridas: 2,
                vagasExigidas: 1,
                embarqueRequerente: 'nao',
                embarqueExigida: null,
            }),
        ).toBe(true);
    });

    it('reprova quando a norma exige a área e o requerente informou que não tem', () => {
        expect(
            vereditoEstacionamento({
                vagasRequeridas: 2,
                vagasExigidas: 1,
                embarqueRequerente: 'nao',
                embarqueExigida: 'sim',
            }),
        ).toBe(false);
    });

    it('não reprova quando a área não se aplica', () => {
        expect(
            vereditoEstacionamento({
                vagasRequeridas: 2,
                vagasExigidas: 1,
                embarqueRequerente: 'nao',
                embarqueExigida: 'na',
            }),
        ).toBe(true);
    });
});
