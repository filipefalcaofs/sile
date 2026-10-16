// @vitest-environment happy-dom
import { act } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { ResultadoViabilidade, type ResultadoConsulta } from './resultado-viabilidade';

vi.mock('@inertiajs/react', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@inertiajs/react')>()),
    usePage: () => ({ props: { vocabulario: { risco_sanitario: {} } } }),
}));

const AVISO = 'Esta consulta é uma simulação informativa: não cria processo e não vincula a decisão oficial.';

type Estado = 'conclusiva' | 'incompleta' | 'inconclusiva';

function resultado(estado: Estado | null, extra: Partial<ResultadoConsulta> = {}): ResultadoConsulta {
    return {
        entrada: { tipo: 'endereco', cnae: '4712100' },
        geocode: null,
        territorio: null,
        enquadramento: {
            enquadramento: { status: 'identificado', grupo: 'nR1', subgrupo: null },
            quadro10: {},
            quadro11a: {},
            consolidado: { resultado: 'permitido' },
            versoes: {},
        },
        risco: {
            municipal: { status: 'classificado', nivel: 'baixo_a', nivel_label: 'Baixo A' },
            sanitario: { status: 'nao_classificado', nivel_original: null, nivel_final: null, reclassificado: false },
            encaminhamento: { fluxo: 'expresso', dimensao_decisiva: 'municipal', motivo: 'Expresso' },
            fundamentacao: [],
            versoes: {},
        },
        restricoes: null,
        veredito_locacional: { resultado: 'permitido', label: 'Permitido', motivo: null },
        fundamentacao: [],
        avisos: [],
        versoes: {},
        aviso_simulacao: AVISO,
        ...(estado === null
            ? {}
            : {
                  confiabilidade: {
                      estado,
                      label: { conclusiva: 'Resultado conclusivo', incompleta: 'Consulta incompleta', inconclusiva: 'Não foi possível concluir' }[estado],
                      pendencias:
                          estado === 'incompleta'
                              ? [
                                    { campo: 'area', rotulo: 'Informe a área da atividade (m²).' },
                                    { campo: 'pergunta', rotulo: 'Pergunta 11: A atividade será desenvolvida no local?' },
                                ]
                              : [],
                      motivo: estado === 'inconclusiva' ? 'Zona urbanística não identificada neste ponto.' : null,
                  },
              }),
        ...extra,
    };
}

let root: Root | null = null;
let container: HTMLElement | null = null;

function renderizar(result: ResultadoConsulta): HTMLElement {
    container = document.createElement('div');
    document.body.appendChild(container);
    root = createRoot(container);
    act(() => {
        root!.render(<ResultadoViabilidade result={result} />);
    });

    return container;
}

afterEach(() => {
    act(() => root?.unmount());
    container?.remove();
    root = null;
    container = null;
});

describe('ResultadoViabilidade — confiabilidade', () => {
    it('conclusiva exibe o veredito e o aviso fixo de simulação', () => {
        const el = renderizar(resultado('conclusiva'));

        expect(el.querySelector('[aria-label="Veredito locacional"]')).not.toBeNull();
        expect(el.textContent).toContain('Permitido');
        expect(el.textContent).toContain('Resultado conclusivo');
        expect(el.textContent).toContain(AVISO);
    });

    it('incompleta lista as pendências e não exibe veredito', () => {
        const el = renderizar(resultado('incompleta', { veredito_locacional: null }));

        expect(el.querySelector('[aria-label="Veredito locacional"]')).toBeNull();
        expect(el.textContent).toContain('Consulta incompleta');
        expect(el.textContent).toContain('Informe a área da atividade (m²).');
        expect(el.textContent).toContain('Pergunta 11: A atividade será desenvolvida no local?');
        expect(el.textContent).toContain(AVISO);
    });

    it('inconclusiva mostra o motivo, sem veredito e sem orientar próximo passo', () => {
        const el = renderizar(resultado('inconclusiva', { veredito_locacional: null }));

        expect(el.querySelector('[aria-label="Veredito locacional"]')).toBeNull();
        expect(el.textContent).toContain('Não foi possível concluir');
        expect(el.textContent).toContain('Zona urbanística não identificada neste ponto.');
        expect(el.textContent).not.toContain('JUCEB');
        expect(el.querySelector('a')).toBeNull();
        expect(el.textContent).toContain(AVISO);
    });

    it('snapshot antigo do histórico, sem confiabilidade, continua exibindo o veredito', () => {
        const el = renderizar(resultado(null, { aviso_simulacao: undefined }));

        expect(el.querySelector('[aria-label="Veredito locacional"]')).not.toBeNull();
        expect(el.textContent).not.toContain('Consulta incompleta');
    });
});
