// @vitest-environment happy-dom
import { act, useState } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it } from 'vitest';
import IsoDateInput from './iso-date-input';

(globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }).IS_REACT_ACT_ENVIRONMENT = true;

describe('IsoDateInput', () => {
    let root: Root;
    let host: HTMLDivElement;
    let valorAtual = '';

    afterEach(() => {
        act(() => {
            root.unmount();
        });
        host.remove();
    });

    function Campo({ inicial }: { inicial: string }) {
        const [valor, setValor] = useState(inicial);
        valorAtual = valor;

        return <IsoDateInput value={valor} onChange={setValor} />;
    }

    function montar(inicial: string) {
        host = document.createElement('div');
        document.body.appendChild(host);
        root = createRoot(host);
        act(() => {
            root.render(<Campo inicial={inicial} />);
        });
    }

    function abrirCalendario() {
        act(() => {
            host.querySelector<HTMLButtonElement>('button[aria-label="Abrir calendário"]')!.click();
        });

        return document.querySelector<HTMLElement>('[role="dialog"][aria-label="Escolher data"]');
    }

    it('abre o calendário no mês da data informada e grava o dia escolhido em ISO', () => {
        montar('2026-03-10');

        const painel = abrirCalendario();
        expect(painel).not.toBeNull();
        expect(painel!.textContent).toContain('mar');

        const dia15 = Array.from(painel!.querySelectorAll<HTMLButtonElement>('.rdp-day_button')).find(
            (botao) => botao.textContent === '15' && !botao.closest('.rdp-outside'),
        );

        act(() => {
            dia15!.click();
        });

        expect(valorAtual).toBe('2026-03-15');
        expect(host.querySelector('input')!.value).toBe('15/03/2026');
        expect(document.querySelector('[aria-label="Escolher data"]')).toBeNull();
    });

    it('limpa a data pelo calendário', () => {
        montar('2026-03-10');

        const painel = abrirCalendario();
        const limpar = Array.from(painel!.querySelectorAll('button')).find((botao) => botao.textContent === 'Limpar');

        act(() => {
            limpar!.click();
        });

        expect(valorAtual).toBe('');
        expect(host.querySelector('input')!.value).toBe('');
    });

    it('continua aceitando a data digitada', () => {
        montar('');

        const input = host.querySelector('input')!;
        const setter = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value')!.set!;

        act(() => {
            setter.call(input, '01022026');
            input.dispatchEvent(new Event('input', { bubbles: true }));
        });

        expect(valorAtual).toBe('2026-02-01');
    });
});
