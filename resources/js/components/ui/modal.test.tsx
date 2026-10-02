// @vitest-environment happy-dom
import { act, useState, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it } from 'vitest';
import { Modal } from './modal';

function CampoParecer({ texto }: { texto: string }) {
    const [valor, setValor] = useState(texto);

    return (
        <Modal isOpen onClose={() => undefined}>
            <textarea id="parecer" value={valor} onChange={(evento) => setValor(evento.target.value)} />
        </Modal>
    );
}

describe('Modal', () => {
    let root: Root;
    let host: HTMLDivElement;

    globalThis.IS_REACT_ACT_ENVIRONMENT = true;

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

    it('mantém o foco no parecer quando o pai re-renderiza com outro onClose', () => {
        montar(<CampoParecer texto="" />);

        const parecer = document.getElementById('parecer') as HTMLTextAreaElement;
        act(() => {
            parecer.focus();
        });

        act(() => {
            const definirValor = Object.getOwnPropertyDescriptor(HTMLTextAreaElement.prototype, 'value')?.set;
            definirValor?.call(parecer, 'a');
            parecer.dispatchEvent(new Event('input', { bubbles: true }));
        });

        expect(document.activeElement).toBe(parecer);
    });

    it('chama o onClose mais recente ao pressionar Escape', () => {
        const geracoes: number[] = [];

        function Dialogo({ geracao }: { geracao: number }) {
            return (
                <Modal isOpen onClose={() => geracoes.push(geracao)}>
                    <textarea id="parecer" defaultValue="" />
                </Modal>
            );
        }

        montar(<Dialogo geracao={0} />);
        act(() => {
            root.render(<Dialogo geracao={1} />);
        });

        act(() => {
            document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
        });

        expect(geracoes).toEqual([1]);
    });

    it('separa a rolagem da centralização para o topo do diálogo continuar alcançável', () => {
        montar(
            <Modal isOpen onClose={() => undefined}>
                <p>Formulário longo</p>
            </Modal>,
        );

        const dialog = document.querySelector('[role="dialog"]') as HTMLElement;
        const scroller = document.querySelector('.modal') as HTMLElement;
        const centro = dialog.parentElement as HTMLElement;

        expect(scroller.className).toContain('overflow-y-auto');
        expect(scroller.className).not.toContain('items-center');
        expect(centro.className).toContain('min-h-full');
        expect(centro.className).toContain('items-center');
        expect(centro.className).toContain('pointer-events-none');
        expect(dialog.className).toContain('pointer-events-auto');
    });
});
