// @vitest-environment happy-dom
import { act } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import Consulta from './consulta';

const enviado: { payload: Record<string, unknown> | null; url: string | null } = { payload: null, url: null };
let transformado: (() => Record<string, unknown>) | null = null;
let respostaDoServidor: unknown = null;

vi.mock('@inertiajs/react', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@inertiajs/react')>()),
    usePage: () => ({ props: { auth: { user: null }, vocabulario: { risco_sanitario: {} } } }),
    Head: () => null,
    useHttp: () => ({
        processing: false,
        transform: (fn: () => Record<string, unknown>) => {
            transformado = fn;
        },
        post: (url: string, opcoes?: { onSuccess?: (resposta: unknown) => void }) => {
            enviado.url = url;
            enviado.payload = transformado ? transformado() : null;
            if (respostaDoServidor !== null) {
                opcoes?.onSuccess?.(respostaDoServidor);
            }
        },
    }),
}));

vi.mock('@/components/geo/mapa-section', () => ({ MapaSection: () => null }));

const props = {
    consultaEnabled: true,
    tiposImovel: [{ value: 'Galpão', label: 'Galpão' }],
    categoriasEmpresa: [
        { value: 'orgao_publico', label: 'Órgão público' },
        { value: 'outra', label: 'Outra' },
    ],
};

let root: Root | null = null;
let container: HTMLElement | null = null;

function renderizar(): HTMLElement {
    container = document.createElement('div');
    document.body.appendChild(container);
    root = createRoot(container);
    act(() => {
        root!.render(<Consulta {...props} />);
    });

    return container;
}

function digitar(el: HTMLInputElement | HTMLSelectElement, valor: string) {
    const proto = el instanceof HTMLSelectElement ? HTMLSelectElement.prototype : HTMLInputElement.prototype;
    act(() => {
        Object.getOwnPropertyDescriptor(proto, 'value')!.set!.call(el, valor);
        el.dispatchEvent(new Event(el instanceof HTMLSelectElement ? 'change' : 'input', { bubbles: true }));
    });
}

function campo<T extends HTMLElement>(el: HTMLElement, id: string): T {
    return el.querySelector<T>(`#${id}`)!;
}

function aba(el: HTMLElement, rotulo: string): HTMLButtonElement {
    return Array.from(el.querySelectorAll<HTMLButtonElement>('[role="tab"]')).find((b) => b.textContent?.includes(rotulo))!;
}

function botao(el: HTMLElement): HTMLButtonElement {
    return Array.from(el.querySelectorAll<HTMLButtonElement>('button[type="submit"]')).find((b) =>
        b.textContent?.includes('Consultar viabilidade'),
    )!;
}

beforeEach(() => {
    enviado.payload = null;
    enviado.url = null;
    transformado = null;
    respostaDoServidor = null;
});

afterEach(() => {
    act(() => root?.unmount());
    container?.remove();
    root = null;
    container = null;
});

describe('Consulta — formulário', () => {
    it('exige a área nas abas Endereço e Inscrição', () => {
        const el = renderizar();

        digitar(campo(el, 'endereco'), 'Praça da Sé, Salvador');
        digitar(campo(el, 'cnae'), '4712-1/00');
        expect(botao(el).disabled).toBe(true);

        digitar(campo(el, 'area'), '120');
        expect(botao(el).disabled).toBe(false);

        digitar(campo(el, 'area'), '0');
        expect(botao(el).disabled).toBe(true);

        act(() => aba(el, 'Inscrição').click());
        digitar(campo(el, 'inscricao'), '123456');
        digitar(campo(el, 'area'), '');
        expect(botao(el).disabled).toBe(true);
        digitar(campo(el, 'area'), '50');
        expect(botao(el).disabled).toBe(false);
    });

    it('mantém a área opcional na aba CNAE e rotula como sem análise do local', () => {
        const el = renderizar();

        act(() => aba(el, 'CNAE').click());
        digitar(campo(el, 'cnae'), '4712-1/00');

        expect(botao(el).disabled).toBe(false);
        expect(el.textContent).toContain('sem análise do local');
    });

    it('envia tipo de imóvel, categoria e área do bloco Refinar consulta', () => {
        const el = renderizar();

        expect(el.textContent).toContain('Refinar consulta');
        digitar(campo(el, 'endereco'), 'Praça da Sé, Salvador');
        digitar(campo(el, 'cnae'), '4712-1/00');
        digitar(campo(el, 'area'), '120');
        digitar(campo<HTMLSelectElement>(el, 'tipo_imovel'), 'Galpão');
        digitar(campo<HTMLSelectElement>(el, 'categoria_empresa'), 'outra');

        act(() => botao(el).click());

        expect(enviado.url).toBe('/portal/viabilidade/endereco');
        expect(enviado.payload).toMatchObject({
            endereco: 'Praça da Sé, Salvador',
            cnae: '4712-1/00',
            area: 120,
            tipo_imovel: 'Galpão',
            categoria_empresa: 'outra',
        });
    });

    it('não envia tipo de imóvel nem categoria quando não foram escolhidos', () => {
        const el = renderizar();

        digitar(campo(el, 'endereco'), 'Praça da Sé, Salvador');
        digitar(campo(el, 'cnae'), '4712-1/00');
        digitar(campo(el, 'area'), '120');
        act(() => botao(el).click());

        expect(enviado.payload).not.toHaveProperty('tipo_imovel');
        expect(enviado.payload).not.toHaveProperty('categoria_empresa');
        expect(enviado.payload).not.toHaveProperty('vagas');
    });

    it('ao trocar de aba, limpa o resultado e as pendências da consulta anterior', () => {
        respostaDoServidor = {
            entrada: { tipo: 'endereco', cnae: '4712100' },
            geocode: null,
            territorio: null,
            enquadramento: { enquadramento: { status: 'nao_encontrado', grupo: null, subgrupo: null }, quadro10: {}, quadro11a: {}, consolidado: { resultado: 'pendente' }, versoes: {} },
            risco: {
                municipal: { status: 'nao_classificado', nivel: null, nivel_label: null },
                sanitario: { status: 'nao_classificado', nivel_original: null, nivel_final: null, reclassificado: false },
                encaminhamento: { fluxo: 'analise', dimensao_decisiva: 'municipal', motivo: '' },
                fundamentacao: [],
                versoes: {},
            },
            restricoes: null,
            veredito_locacional: null,
            confiabilidade: {
                estado: 'incompleta',
                label: 'Consulta incompleta',
                pendencias: [{ campo: 'tipo_imovel', rotulo: 'Informe o tipo do imóvel.' }],
                motivo: null,
            },
            fundamentacao: [],
            avisos: [],
            versoes: {},
            perguntas: [],
        };
        const el = renderizar();

        digitar(campo(el, 'endereco'), 'Praça da Sé, Salvador');
        digitar(campo(el, 'cnae'), '4712-1/00');
        digitar(campo(el, 'area'), '120');
        act(() => botao(el).click());
        expect(el.textContent).toContain('Consulta incompleta');

        act(() => aba(el, 'CNAE').click());

        expect(el.textContent).not.toContain('Consulta incompleta');
        expect(el.textContent).not.toContain('Informe o tipo do imóvel.');
    });
});
