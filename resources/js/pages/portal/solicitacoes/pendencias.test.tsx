// @vitest-environment happy-dom
import { act } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it, vi } from 'vitest';
import Pendencias from './pendencias';

vi.mock('@inertiajs/react', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@inertiajs/react')>()),
    Head: () => null,
    usePage: () => ({ url: '/portal/solicitacoes/7/pendencias', props: { flash: {}, auth: { user: null } } }),
}));

const solicitacao = {
    id: 7,
    protocol_number: 'VIA-2026-000007',
    status: { value: 'em_pendencia', label: 'Em pendência', public_label: 'Convite — ação necessária do requerente' },
};

function pendencia(diasAteOPrazo: number) {
    return {
        id: 3,
        description: 'Envie o IPTU atualizado.',
        status: 'aberta',
        status_label: 'Aberta',
        due_at: new Date(Date.now() + diasAteOPrazo * 86_400_000).toISOString(),
        created_at: new Date().toISOString(),
    };
}

let root: Root | null = null;
let container: HTMLElement | null = null;

function renderizar(diasAteOPrazo: number): HTMLElement {
    container = document.createElement('div');
    document.body.appendChild(container);
    root = createRoot(container);
    act(() => {
        root!.render(<Pendencias solicitacao={solicitacao} pendencias={[pendencia(diasAteOPrazo)]} anexo_max_mb={10} />);
    });

    return container;
}

afterEach(() => {
    act(() => root?.unmount());
    container?.remove();
    root = null;
    container = null;
});

describe('Pendencias (resposta do convite)', () => {
    it('mostra o prazo restante e o limite do anexo', () => {
        const tela = renderizar(4);

        expect(tela.textContent).toContain('Faltam 4 dias');
        expect(tela.textContent).toContain('PDF, JPG ou PNG, até 10 MB.');
        expect(tela.textContent).toContain('0/5000');
    });

    it('avisa quando o prazo acabou', () => {
        expect(renderizar(-2).textContent).toContain('Prazo encerrado');
    });

    it('mostra o arquivo escolhido e permite remover', () => {
        const tela = renderizar(4);
        const entrada = tela.querySelector<HTMLInputElement>('input[type="file"]')!;
        const arquivo = new File(['conteudo'], 'iptu.pdf', { type: 'application/pdf' });

        act(() => {
            Object.defineProperty(entrada, 'files', { value: [arquivo], configurable: true });
            entrada.dispatchEvent(new Event('change', { bubbles: true }));
        });

        expect(tela.textContent).toContain('iptu.pdf');
        expect(tela.querySelector('input[type="file"]')).toBeNull();

        const remover = [...tela.querySelectorAll('button')].find((botao) => botao.textContent?.includes('Remover'))!;
        act(() => remover.click());

        expect(tela.textContent).not.toContain('iptu.pdf');
        expect(tela.querySelector('input[type="file"]')).not.toBeNull();
    });
});
