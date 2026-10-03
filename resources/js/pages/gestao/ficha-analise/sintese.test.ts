import { describe, expect, it } from 'vitest';
import { sinteseDaFicha, type EntradaSintese } from './sintese';

function entrada(parcial: Partial<EntradaSintese> = {}): EntradaSintese {
    return {
        statusProcesso: 'em_analise',
        rotuloProcesso: 'Em análise',
        motorDisponivel: true,
        motivosAnalise: [],
        atividades: [],
        zona: null,
        via: null,
        ...parcial,
    };
}

describe('síntese da ficha de análise', () => {
    it('não transforma a leitura do motor em sugestão de deferir', () => {
        const sintese = sinteseDaFicha(
            entrada({
                atividades: [
                    {
                        cnae: '6202300',
                        is_primary: true,
                        quadros: [
                            {
                                key: 'quadro10',
                                label: 'Quadro 10',
                                estado: 'permitido',
                                detalhe: 'Serviços na zona ZUE 04 permitido.',
                            },
                        ],
                    },
                ],
            }),
        );

        expect(sintese.titulo).toBe('Em análise');
        expect(sintese.contexto).toContain('Não é uma sugestão de deferir');
        expect(sintese.linhas).toEqual([
            {
                id: '6202300-quadro10',
                texto: 'Serviços na zona ZUE 04 permitido.',
                estado: 'permitido',
            },
        ]);
    });

    it('coloca o motivo da análise antes dos quadros e marca como impedimento', () => {
        const sintese = sinteseDaFicha(
            entrada({
                motivosAnalise: ['Tipo de imóvel não reconhecido.'],
                atividades: [
                    {
                        cnae: '1413401',
                        cnae_formatado: '1413-4/01',
                        quadros: [
                            {
                                key: 'quadro11a',
                                label: 'Quadro 11-A',
                                estado: 'nao_permitido',
                                detalhe: 'Uso vedado para o grupo na classe viária VC II.',
                            },
                        ],
                    },
                ],
            }),
        );

        expect(sintese.contexto).toContain('encaminhou à análise');
        expect(sintese.linhas.map((linha) => linha.estado)).toEqual(['impedimento', 'nao_permitido']);
        expect(sintese.linhas[1]?.texto).toBe('Uso vedado para o grupo na classe viária VC II.');
    });

    it('num desfecho já registrado não fala em sugestão e reúne a base gravada', () => {
        const sintese = sinteseDaFicha(
            entrada({
                statusProcesso: 'deferida',
                rotuloProcesso: 'Deferida',
                zona: 'ZUE 04',
                via: 'VL',
                atividades: [
                    {
                        cnae: '6202300',
                        is_primary: true,
                        codigo_louos: 'nR1-01',
                        fundamentacao: ['Lei nº 9.148/2016, art. 18'],
                    },
                ],
            }),
        );

        expect(sintese.titulo).toBe('Deferida');
        expect(sintese.contexto).toContain('Desfecho já registrado');
        expect(sintese.base).toEqual(['Lei nº 9.148/2016, art. 18', 'Enquadramento nR1-01', 'Zona ZUE 04', 'Via VL']);
    });

    it('sem motor e sem leitura diz que a análise é humana', () => {
        const sintese = sinteseDaFicha(entrada({ motorDisponivel: false }));

        expect(sintese.linhas).toEqual([
            {
                id: 'sem-motor',
                texto: 'O motor não pré-analisou este processo. A análise é humana.',
                estado: 'pendente',
            },
        ]);
        expect(sintese.base).toEqual([]);
    });
});
