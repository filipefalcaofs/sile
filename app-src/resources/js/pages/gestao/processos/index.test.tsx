// @vitest-environment happy-dom
import { act, type ComponentProps } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const visitas: { url: string; params: Record<string, string | number> }[] = [];

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ children }: { children: React.ReactNode }) => <a>{children}</a>,
    router: {
        get: (url: string, params: Record<string, string | number>) => {
            visitas.push({ url, params });
        },
        post: vi.fn(),
    },
    usePage: () => ({ url: '/gestao/processos', props: { auth: { permissions: [] } } }),
}));

vi.mock('@/components/analise/processos-simplifica', () => ({ default: () => null }));

import ConsultaProcessos from './index';

type Props = ComponentProps<typeof ConsultaProcessos>;

const base: Props = {
    erroPeriodo: null,
    processos: { data: [], links: [], from: null, to: null, total: 0 },
    filtros: {
        grupo: '', status: '', protocolo: '', bap: '', produto_tvl: '', servico: '', setor: '', analista: '', categoria: '',
        inscricao: '', nome: '', cnpj: '', cep: '', logradouro: '', bairro: '', data_de: '', data_ate: '',
        analysis_status: '', busca: '', fluxo: '', cnae: '', complemento: '',
        per_page: 15, ordem: 'recentes', fonte: 'viabiliza',
    },
    abas: { todos: 0, em_analise: 0, distribuir: 0, pendencia: 0, concluido: 0 },
    perPageOptions: [10, 15, 25, 50],
    statusOptions: [],
    categoriaOptions: [],
    analysisStatusOptions: [],
    analistaOptions: [],
};

describe('Consulta de processos: filtros por fonte', () => {
    let root: Root;
    let host: HTMLDivElement;

    (globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }).IS_REACT_ACT_ENVIRONMENT = true;

    beforeEach(() => {
        visitas.length = 0;
        host = document.createElement('div');
        document.body.appendChild(host);
        root = createRoot(host);
    });

    afterEach(() => {
        act(() => root.unmount());
        host.remove();
    });

    function renderizar(props: Props) {
        act(() => root.render(<ConsultaProcessos {...props} />));
    }

    function digitar(id: string, valor: string) {
        const campo = host.querySelector<HTMLInputElement>(`#${id}`)!;
        const setter = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value')!.set!;

        act(() => {
            setter.call(campo, valor);
            campo.dispatchEvent(new Event('input', { bubbles: true }));
        });
    }

    function escolherFonte(titulo: string) {
        const botao = Array.from(host.querySelectorAll<HTMLButtonElement>('[role="radio"]')).find((b) => b.textContent?.includes(titulo))!;

        act(() => botao.click());
    }

    it('não leva o filtro de uma fonte para a outra e devolve o que estava preenchido ao voltar', () => {
        renderizar(base);
        digitar('filtro-bap', 'BAP2601551384');

        escolherFonte('Simplifica');

        expect(visitas.at(-1)?.params).toMatchObject({ fonte: 'simplifica' });
        expect(visitas.at(-1)?.params).not.toHaveProperty('bap');
        expect(host.querySelector<HTMLInputElement>('#filtro-bap')!.value).toBe('');

        renderizar({ ...base, filtros: { ...base.filtros, fonte: 'simplifica' } });
        digitar('filtro-nome', 'Padaria');

        escolherFonte('Viabiliza');

        expect(visitas.at(-1)?.params).toMatchObject({ bap: 'BAP2601551384' });
        expect(visitas.at(-1)?.params).not.toHaveProperty('nome');
        expect(visitas.at(-1)?.params).not.toHaveProperty('fonte');
        expect(host.querySelector<HTMLInputElement>('#filtro-bap')!.value).toBe('BAP2601551384');

        renderizar(base);
        escolherFonte('Simplifica');

        expect(visitas.at(-1)?.params).toMatchObject({ fonte: 'simplifica', nome: 'Padaria' });
        expect(visitas.at(-1)?.params).not.toHaveProperty('bap');
    });
});
