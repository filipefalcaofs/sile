// @vitest-environment happy-dom
import { act } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import ConclusaoAnaliseForm from './conclusao-analise-form';

describe('ConclusaoAnaliseForm — copiar parecer', () => {
    let root: Root;
    let host: HTMLDivElement;
    const escrever = vi.fn().mockResolvedValue(undefined);

    (globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }).IS_REACT_ACT_ENVIRONMENT = true;

    beforeEach(() => {
        escrever.mockClear();
        Object.defineProperty(navigator, 'clipboard', { value: { writeText: escrever }, configurable: true });
    });

    afterEach(() => {
        act(() => {
            root.unmount();
        });
        host.remove();
    });

    function montar(propriedades: { parecer?: string; parecerDaFicha?: string | null }) {
        host = document.createElement('div');
        document.body.appendChild(host);
        root = createRoot(host);
        act(() => {
            root.render(
                <ConclusaoAnaliseForm
                    resultadoId="resultado"
                    resultado=""
                    onResultado={() => undefined}
                    opcoes={[{ value: 'deferido', label: 'Deferido' }]}
                    onParecer={() => undefined}
                    onConfirm={() => undefined}
                    {...propriedades}
                />,
            );
        });
    }

    function botaoCopiar(): HTMLButtonElement {
        const botao = Array.from(host.querySelectorAll('button')).find((item) => item.textContent?.includes('Copiar parecer'));

        if (!botao) {
            throw new Error('botão Copiar parecer não encontrado');
        }

        return botao as HTMLButtonElement;
    }

    it('copia o parecer registrado na ficha de análise', async () => {
        montar({ parecer: '', parecerDaFicha: 'Parecer técnico da ficha.' });

        await act(async () => {
            botaoCopiar().click();
        });

        expect(escrever).toHaveBeenCalledWith('Parecer técnico da ficha.');
    });

    it('sem parecer na ficha, copia o texto digitado na conclusão', async () => {
        montar({ parecer: 'Texto digitado agora.', parecerDaFicha: null });

        await act(async () => {
            botaoCopiar().click();
        });

        expect(escrever).toHaveBeenCalledWith('Texto digitado agora.');
    });

    it('fica desabilitado quando não há nada para copiar', () => {
        montar({ parecer: '', parecerDaFicha: '   ' });

        expect(botaoCopiar().disabled).toBe(true);
    });
});

describe('ConclusaoAnaliseForm — tipo do deferimento e validade', () => {
    let root: Root;
    let host: HTMLDivElement;

    (globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }).IS_REACT_ACT_ENVIRONMENT = true;

    afterEach(() => {
        act(() => {
            root.unmount();
        });
        host.remove();
    });

    function montar(propriedades: { resultado: string; tipo?: string; validadeMeses?: string; semTipo?: boolean }) {
        host = document.createElement('div');
        document.body.appendChild(host);
        root = createRoot(host);
        act(() => {
            root.render(
                <ConclusaoAnaliseForm
                    resultadoId="resultado"
                    resultado={propriedades.resultado}
                    onResultado={() => undefined}
                    opcoes={[
                        { value: 'deferido', label: 'Deferido' },
                        { value: 'indeferido', label: 'Indeferido' },
                    ]}
                    tipo={propriedades.tipo ?? ''}
                    onTipo={propriedades.semTipo ? undefined : () => undefined}
                    validadeMeses={propriedades.validadeMeses ?? ''}
                    onValidadeMeses={() => undefined}
                    onConfirm={() => undefined}
                />,
            );
        });
    }

    function botaoConcluir(): HTMLButtonElement {
        const botao = Array.from(host.querySelectorAll('button')).find((item) => item.textContent?.includes('Concluir análise'));

        if (!botao) {
            throw new Error('botão Concluir análise não encontrado');
        }

        return botao as HTMLButtonElement;
    }

    it('mostra Tipo só quando o resultado é deferido', () => {
        montar({ resultado: 'indeferido' });
        expect(host.querySelector('#tipo-deferimento')).toBeNull();
        act(() => root.unmount());
        host.remove();

        montar({ resultado: 'deferido' });
        expect(host.querySelector('#tipo-deferimento')).not.toBeNull();
    });

    it('mostra Data da Validade só para provisório e pré-operacional', () => {
        montar({ resultado: 'deferido', tipo: 'definitiva' });
        expect(host.querySelector('#validade-deferimento')).toBeNull();
        act(() => root.unmount());
        host.remove();

        montar({ resultado: 'deferido', tipo: 'provisoria' });
        expect(host.querySelector('#validade-deferimento')).not.toBeNull();
    });

    it('desabilita concluir sem a validade obrigatória', () => {
        montar({ resultado: 'deferido', tipo: 'provisoria', validadeMeses: '' });

        expect(botaoConcluir().disabled).toBe(true);
    });

    it('sem onTipo não mostra os campos (renovação)', () => {
        montar({ resultado: 'deferido', semTipo: true });

        expect(host.querySelector('#tipo-deferimento')).toBeNull();
        expect(botaoConcluir().disabled).toBe(false);
    });
});
