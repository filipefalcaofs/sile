// @vitest-environment happy-dom
import { act, useState, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it } from 'vitest';
import OptionSelect, { type OptionSelectOption } from './option-select';

const denominacao =
    '07.01.03 — Comércio de artigos de vestuário, calçados, bolsas, artigos de viagem e acessórios, artigos de cama, mesa, banho, utilidades domésticas, tecidos e fios têxteis';

const opcoes: OptionSelectOption[] = [
    { value: '07.01.03|', label: denominacao, meta: 'TLL 1.01' },
    {
        value: '07.12.13|1.01',
        label: '07.12.13 — Escritório (inclusive virtual), sede de empresa, grupo de salas, centro empresarial',
        meta: 'TLL 1.01',
    },
];

function Campo({ inicial = '' }: { inicial?: string }) {
    const [valor, setValor] = useState(inicial);

    return (
        <OptionSelect
            id="enquadramento"
            value={valor}
            onChange={setValor}
            placeholder="Selecione o enquadramento…"
            options={opcoes}
        />
    );
}

describe('OptionSelect', () => {
    let root: Root;
    let host: HTMLDivElement;

    (globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }).IS_REACT_ACT_ENVIRONMENT = true;

    afterEach(() => {
        act(() => {
            root.unmount();
        });
        host.remove();
    });

    function montar(ui: ReactNode) {
        host = document.createElement('div');
        document.body.appendChild(host);
        root = createRoot(host);
        act(() => {
            root.render(ui);
        });
    }

    function abrir() {
        const gatilho = document.getElementById('enquadramento') as HTMLButtonElement;
        act(() => {
            gatilho.click();
        });
    }

    it('mostra o texto completo da opção, sem cortar a lista', () => {
        montar(<Campo />);
        abrir();

        const lista = document.querySelector('[role="listbox"]');
        expect(lista?.textContent).toContain(denominacao);

        const opcao = document.querySelector('[role="option"]');
        expect(opcao?.textContent).toContain(denominacao);
        expect(opcao?.className ?? '').not.toMatch(/truncate|line-clamp|text-ellipsis/);
        expect(opcao?.querySelector('.whitespace-normal')).not.toBeNull();
    });

    it('devolve o valor da opção escolhida', () => {
        montar(<Campo />);
        abrir();

        const opcoesLista = document.querySelectorAll('[role="option"]');
        act(() => {
            (opcoesLista[1] as HTMLElement).click();
        });

        const gatilho = document.getElementById('enquadramento') as HTMLButtonElement;
        const rotulo = gatilho.querySelector('span');
        expect(gatilho.textContent).toContain(
            'Escritório (inclusive virtual), sede de empresa, grupo de salas, centro empresarial',
        );
        expect(rotulo?.className ?? '').not.toMatch(/truncate|line-clamp|text-ellipsis/);
        expect(rotulo?.className).toContain('whitespace-normal');
        expect(document.querySelector('[role="listbox"]')).toBeNull();
    });

    it('fecha com Escape e devolve o foco ao campo', () => {
        montar(<Campo />);
        abrir();

        act(() => {
            document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
        });

        expect(document.querySelector('[role="listbox"]')).toBeNull();
        expect(document.activeElement).toBe(document.getElementById('enquadramento'));
    });

    it('filtra as opções pelo texto digitado na busca', () => {
        montar(<Campo />);
        abrir();

        const busca = document.querySelector('[role="listbox"] input') as HTMLInputElement;
        expect(busca).not.toBeNull();

        act(() => {
            const definir = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value')?.set;
            definir?.call(busca, 'escritório');
            busca.dispatchEvent(new Event('input', { bubbles: true }));
        });

        const opcoesLista = document.querySelectorAll('[role="option"]');
        expect(opcoesLista).toHaveLength(1);
        expect(opcoesLista[0]?.textContent).toContain('Escritório');
    });
});
