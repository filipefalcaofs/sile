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
    it('em análise mostra a decisão que o motor já marcou no CNAE', () => {
        const sintese = sinteseDaFicha(
            entrada({
                atividades: [
                    {
                        cnae: '6202300',
                        is_primary: true,
                        status_escolhido: 'deferida',
                        tendencia: 'permitido',
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

        expect(sintese.titulo).toBe('Deferida');
        expect(sintese.tom).toBe('deferida');
        expect(sintese.contexto).toContain('já marcada em cada atividade');
        expect(sintese.linhas).toEqual([
            {
                id: '6202300-quadro10',
                texto: 'Serviços na zona ZUE 04 permitido.',
                estado: 'permitido',
            },
        ]);
    });

    it('o CNAE indeferido prevalece e o motivo da fila vem depois da lei', () => {
        const sintese = sinteseDaFicha(
            entrada({
                motivosAnalise: ['Tipo de imóvel não reconhecido.'],
                atividades: [
                    {
                        cnae: '1413401',
                        cnae_formatado: '1413-4/01',
                        status_escolhido: 'indeferida',
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

        expect(sintese.titulo).toBe('Indeferida');
        expect(sintese.linhas.map((linha) => linha.estado)).toEqual(['nao_permitido', 'impedimento']);
        expect(sintese.linhas[0]?.texto).toBe('Uso vedado para o grupo na classe viária VC II.');
        expect(sintese.linhas[1]?.texto).toBe('Tipo de imóvel não reconhecido.');
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
                        status_escolhido: 'deferida',
                        codigo_louos: 'nR1-01',
                        fundamentacao: ['Lei nº 9.148/2016, art. 18'],
                    },
                ],
            }),
        );

        expect(sintese.titulo).toBe('Deferida');
        expect(sintese.contexto).toContain('já marcada em cada atividade');
        expect(sintese.base).toEqual(['Lei nº 9.148/2016, art. 18', 'Enquadramento nR1-01', 'Zona ZUE 04', 'Via VL']);
    });

    it('com CNAEs divergentes o indeferido prevalece e cada um aparece', () => {
        const sintese = sinteseDaFicha(
            entrada({
                atividades: [
                    { cnae: '6202300', cnae_formatado: '6202-3/00', status_escolhido: 'deferida' },
                    { cnae: '1413401', cnae_formatado: '1413-4/01', status_escolhido: 'indeferida' },
                ],
            }),
        );

        expect(sintese.titulo).toBe('Indeferida');
        expect(sintese.linhas.map((linha) => linha.texto)).toEqual(['6202-3/00: Deferida', '1413-4/01: Indeferida']);
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
