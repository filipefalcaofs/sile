// @vitest-environment happy-dom
import { act } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import ConclusaoAnaliseForm, { TIPO_DEFERIMENTO_OPCOES } from './conclusao-analise-form';

describe('ConclusaoAnaliseForm — importar parecer', () => {
    let root: Root;
    let host: HTMLDivElement;

    (globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }).IS_REACT_ACT_ENVIRONMENT = true;

    afterEach(() => {
        act(() => {
            root.unmount();
        });
        host.remove();
    });

    function montar(propriedades: { parecer?: string; parecerDaFicha?: string | null; onParecer?: (valor: string) => void }) {
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
                    onParecer={propriedades.onParecer ?? (() => undefined)}
                    onConfirm={() => undefined}
                    {...propriedades}
                />,
            );
        });
    }

    function botaoImportar(): HTMLButtonElement {
        const botao = Array.from(host.querySelectorAll('button')).find((item) => item.textContent?.includes('Importar parecer'));

        if (!botao) {
            throw new Error('botão Importar parecer não encontrado');
        }

        return botao as HTMLButtonElement;
    }

    it('importa o parecer registrado na ficha de análise para o campo', () => {
        const aoMudar = vi.fn();
        montar({ parecer: '', parecerDaFicha: 'Parecer técnico da ficha.', onParecer: aoMudar });

        act(() => {
            botaoImportar().click();
        });

        expect(aoMudar).toHaveBeenCalledWith('Parecer técnico da ficha.');
    });

    it('confirma a importação no próprio botão', () => {
        montar({ parecer: '', parecerDaFicha: 'Parecer técnico da ficha.' });

        act(() => {
            botaoImportar().click();
        });

        expect(host.textContent).toContain('Importado!');
    });

    it('fica desabilitado quando a ficha não tem parecer', () => {
        montar({ parecer: 'Texto digitado agora.', parecerDaFicha: '   ' });

        expect(botaoImportar().disabled).toBe(true);
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

    function montar(propriedades: { resultado: string; tipo?: string; validadeMeses?: string; validadeData?: string; semTipo?: boolean }) {
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
                    validadeData={propriedades.validadeData ?? ''}
                    onValidadeData={() => undefined}
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

    it('chama o tipo sem validade de Definitivo', () => {
        expect(TIPO_DEFERIMENTO_OPCOES.find((opcao) => opcao.value === 'definitiva')?.label).toBe('Definitivo');
    });

    it('Personalizado pede a data e só libera concluir com ela preenchida', () => {
        montar({ resultado: 'deferido', tipo: 'provisoria', validadeMeses: 'personalizado', validadeData: '' });
        expect(host.querySelector('#validade-data-deferimento')).not.toBeNull();
        expect(botaoConcluir().disabled).toBe(true);
        act(() => root.unmount());
        host.remove();

        montar({ resultado: 'deferido', tipo: 'provisoria', validadeMeses: 'personalizado', validadeData: '2027-03-15' });
        expect(botaoConcluir().disabled).toBe(false);
    });

    it('sem Personalizado a data não aparece', () => {
        montar({ resultado: 'deferido', tipo: 'provisoria', validadeMeses: '12' });

        expect(host.querySelector('#validade-data-deferimento')).toBeNull();
    });

    it('sem onTipo não mostra os campos (renovação)', () => {
        montar({ resultado: 'deferido', semTipo: true });

        expect(host.querySelector('#tipo-deferimento')).toBeNull();
        expect(botaoConcluir().disabled).toBe(false);
    });
});
