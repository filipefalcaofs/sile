// @vitest-environment happy-dom
import { act, useState, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it } from 'vitest';
import MultiOptionSelect, { type MultiOptionSelectOption } from './multi-option-select';

const opcoes: MultiOptionSelectOption[] = [
    { value: '1', label: 'Setor de Licenciamento' },
    { value: '2', label: 'Setor de Uso do Solo' },
    { value: '3', label: 'Setor de Fiscalização' },
];

function Campo({ aoMudar }: { aoMudar: (valores: string[]) => void }) {
    const [valor, setValor] = useState<string[]>([]);

    return (
        <MultiOptionSelect
            id="setor"
            value={valor}
            onChange={(proximo) => {
                setValor(proximo);
                aoMudar(proximo);
            }}
            options={opcoes}
            placeholder="Todos"
        />
    );
}

describe('MultiOptionSelect', () => {
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

    function gatilho() {
        return document.getElementById('setor') as HTMLButtonElement;
    }

    function abrir() {
        act(() => {
            gatilho().click();
        });
    }

    function opcao(texto: string) {
        return Array.from(document.querySelectorAll<HTMLElement>('[role="option"]')).find((el) =>
            el.textContent?.includes(texto),
        ) as HTMLElement;
    }

    it('mostra a descrição das opções e marca várias, resumindo no campo', () => {
        const mudancas: string[][] = [];
        montar(<Campo aoMudar={(v) => mudancas.push(v)} />);

        expect(gatilho().textContent).toContain('Todos');

        abrir();
        expect(document.querySelectorAll('[role="option"]')).toHaveLength(3);

        act(() => {
            opcao('Licenciamento').click();
        });
        expect(gatilho().textContent).toContain('Setor de Licenciamento');

        act(() => {
            opcao('Fiscalização').click();
        });
        expect(gatilho().textContent).toContain('2 selecionados');
        expect(mudancas.at(-1)).toEqual(['1', '3']);
    });

    it('desmarca ao clicar de novo e limpa tudo', () => {
        const mudancas: string[][] = [];
        montar(<Campo aoMudar={(v) => mudancas.push(v)} />);
        abrir();

        act(() => {
            opcao('Uso do Solo').click();
        });
        act(() => {
            opcao('Uso do Solo').click();
        });
        expect(mudancas.at(-1)).toEqual([]);

        act(() => {
            opcao('Licenciamento').click();
        });
        const limpar = Array.from(document.querySelectorAll('button')).find((b) => b.textContent === 'Limpar') as HTMLButtonElement;
        act(() => {
            limpar.click();
        });
        expect(mudancas.at(-1)).toEqual([]);
        expect(gatilho().textContent).toContain('Todos');
    });
});
