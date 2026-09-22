import { describe, expect, it } from 'vitest';
import { paresLegiveis, valorLegivel } from './valor-legivel';

describe('valorLegivel', () => {
    it('não despeja JSON de objeto aninhado', () => {
        const municipal = {
            nivel: 'baixo',
            status: 'classificado',
            label: 'baixo',
            nivel_final: null,
            versao_regra: 'planilha-20-08-26',
            visa: 0,
            condicional: false,
            condicional_perguntas: [
                {
                    pergunta: 'Desde que já ocupe o imóvel?',
                    resposta: false,
                },
            ],
        };

        const texto = valorLegivel(municipal);

        expect(texto).not.toContain('{');
        expect(texto).not.toContain('"nivel"');
        expect(texto.toLowerCase()).toContain('baixo');
    });

    it('formata coordenada sem chaves de lat/lng', () => {
        expect(valorLegivel({ lat: -12.977141, lng: -38.510703 })).toBe('-12.977141, -38.510703');
    });

    it('resume encaminhamento pelo fluxo e motivo', () => {
        const texto = valorLegivel({
            fluxo: 'expresso',
            nivel: 'baixo',
            motivo: 'Nível baixo — elegível ao fluxo expresso',
            dimensao_decisiva: 'risco_tratamento',
            gatilhos: [],
        });

        expect(texto).not.toContain('{');
        expect(texto).toContain('Expresso');
        expect(texto).toContain('Nível baixo — elegível ao fluxo expresso');
    });
});

describe('paresLegiveis', () => {
    it('humaniza a entrada do snapshot sem JSON nem chaves cruas', () => {
        const pares = paresLegiveis({
            cnae: '6622300',
            cnae_formatado: '6622-3/00',
            area_m2: 30,
            ponto: { lat: -12.977141, lng: -38.510703 },
        });

        const mapa = Object.fromEntries(pares.map((par) => [par.label, par.value]));

        expect(mapa.CNAE).toBe('6622-3/00');
        expect(mapa['Área']).toBe('30 m²');
        expect(mapa.Ponto).toBe('-12.977141, -38.510703');
        expect(JSON.stringify(pares)).not.toContain('cnae_formatado');
        expect(JSON.stringify(pares)).not.toContain('"lat"');
    });

    it('humaniza dimensão decisiva e resume risco municipal', () => {
        const pares = paresLegiveis({
            dimensao_decisiva: 'risco_tratamento',
            municipal: {
                nivel: 'baixo',
                status: 'classificado',
                label: 'baixo',
            },
        });

        const mapa = Object.fromEntries(pares.map((par) => [par.label, par.value]));

        expect(mapa['Dimensão decisiva']).toBe('Risco (tratamento)');
        expect(mapa.Municipal.toLowerCase()).toContain('baixo');
        expect(pares.every((par) => !par.value.includes('{'))).toBe(true);
    });
});
