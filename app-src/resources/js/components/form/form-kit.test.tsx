// @vitest-environment happy-dom
import { act, useState, type ReactNode } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, describe, expect, it } from 'vitest';
import CnpjInput from './cnpj-input';
import CurrencyInput from './currency-input';
import Input from './input';
import Label from './label';
import OptionSelect from './option-select';
import PasswordField from './password-field';
import SearchableSelect from './searchable-select';
import Select from './select';
import Switch from './switch';
import Textarea from './textarea';
import UfSelect from './uf-select';

(globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }).IS_REACT_ACT_ENVIRONMENT = true;

describe('kit de formulário', () => {
    let root: Root;
    let host: HTMLDivElement;

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

    it('liga o erro do input ao campo para o leitor de tela', () => {
        montar(<Input id="nome" error hint="Informe o nome" defaultValue="" />);

        const campo = document.getElementById('nome') as HTMLInputElement;
        const hintId = campo.getAttribute('aria-describedby');

        expect(campo.getAttribute('aria-invalid')).toBe('true');
        expect(hintId).toBeTruthy();
        expect(document.getElementById(hintId ?? '')?.textContent).toBe('Informe o nome');
        expect(document.getElementById(hintId ?? '')?.getAttribute('role')).toBe('alert');
    });

    it('anuncia campo obrigatório além do asterisco visual', () => {
        montar(
            <Label htmlFor="nome" required>
                Nome
            </Label>,
        );

        expect(host.querySelector('[aria-hidden="true"]')?.textContent).toBe('*');
        expect(host.querySelector('.sr-only')?.textContent).toContain('obrigatório');
    });

    it('mostra erro no select e no textarea', () => {
        montar(
            <>
                <Select id="uf" value="" onChange={() => undefined} options={[{ value: 'BA', label: 'Bahia' }]} error hint="Selecione a UF" />
                <Textarea id="parecer" value="" onChange={() => undefined} error hint="Informe o parecer" />
            </>,
        );

        expect(document.getElementById('uf')?.getAttribute('aria-invalid')).toBe('true');
        expect(document.getElementById('parecer')?.getAttribute('aria-invalid')).toBe('true');
        expect(host.textContent).toContain('Selecione a UF');
        expect(host.textContent).toContain('Informe o parecer');
    });

    it('filtra a lista pesquisável pelo texto digitado', () => {
        function Campo() {
            const [valor, setValor] = useState('');

            return (
                <SearchableSelect
                    id="analista"
                    value={valor}
                    onChange={setValor}
                    options={[
                        { value: '1', label: 'Ana Lima' },
                        { value: '2', label: 'Bruno Costa' },
                    ]}
                />
            );
        }

        montar(<Campo />);
        act(() => {
            (document.getElementById('analista') as HTMLButtonElement).click();
        });

        const busca = document.querySelector('[role="listbox"] input') as HTMLInputElement;
        act(() => {
            const definir = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value')?.set;
            definir?.call(busca, 'bru');
            busca.dispatchEvent(new Event('input', { bubbles: true }));
        });

        const opcoes = document.querySelectorAll('[role="option"]');
        expect(opcoes).toHaveLength(1);
        expect(opcoes[0]?.textContent).toContain('Bruno Costa');
    });

    it('formata CNPJ alfanumérico e lista as UFs', () => {
        function Campos() {
            const [cnpj, setCnpj] = useState('');
            const [uf, setUf] = useState('');

            return (
                <>
                    <CnpjInput id="cnpj" value={cnpj} onChange={setCnpj} />
                    <UfSelect id="uf" value={uf} onChange={setUf} />
                </>
            );
        }

        montar(<Campos />);
        const cnpj = document.getElementById('cnpj') as HTMLInputElement;
        act(() => {
            const definir = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value')?.set;
            definir?.call(cnpj, '12abc345000181');
            cnpj.dispatchEvent(new Event('input', { bubbles: true }));
            cnpj.dispatchEvent(new Event('change', { bubbles: true }));
        });

        expect((document.getElementById('cnpj') as HTMLInputElement).value).toBe('12.ABC.345/0001-81');
        expect(document.getElementById('uf')?.textContent).toContain('Bahia');
        expect(document.querySelectorAll('#uf option').length).toBeGreaterThan(26);
    });

    it('grava moeda em decimal e mostra o valor em reais', () => {
        let gravado = '';

        function Campo() {
            const [valor, setValor] = useState('1111.78');

            return (
                <CurrencyInput
                    id="valor"
                    name="valor"
                    value={valor}
                    onChange={(proximo) => {
                        gravado = proximo;
                        setValor(proximo);
                    }}
                />
            );
        }

        montar(<Campo />);
        expect((document.getElementById('valor') as HTMLInputElement).value).toBe('1.111,78');
        expect((host.querySelector('input[name="valor"]') as HTMLInputElement).value).toBe('1111.78');

        const visivel = document.getElementById('valor') as HTMLInputElement;
        act(() => {
            const definir = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value')?.set;
            definir?.call(visivel, '10');
            visivel.dispatchEvent(new Event('input', { bubbles: true }));
            visivel.dispatchEvent(new Event('change', { bubbles: true }));
        });

        expect(gravado).toBe('0.10');
    });

    it('alterna a visibilidade da senha e marca o campo como obrigatório', () => {
        montar(<PasswordField id="senha" name="password" label="Senha" autoComplete="new-password" />);

        expect(host.querySelector('.sr-only')?.textContent).toContain('obrigatório');
        const botao = host.querySelector('button') as HTMLButtonElement;
        act(() => {
            botao.click();
        });
        expect((document.getElementById('senha') as HTMLInputElement).type).toBe('text');
        expect(botao.getAttribute('aria-label')).toBe('Ocultar senha');
    });

    it('respeita o switch controlado', () => {
        montar(<Switch label="Escritório virtual" checked={false} onChange={() => undefined} />);

        expect(host.querySelector('[role="switch"]')?.getAttribute('aria-checked')).toBe('false');
    });

    it('mostra o texto completo da opção mesmo com erro', () => {
        montar(
            <OptionSelect
                id="empresa"
                value="1"
                onChange={() => undefined}
                error
                hint="Selecione a empresa"
                options={[{ value: '1', label: 'Padaria do Centro — 11.222.333/0001-81' }]}
            />,
        );

        expect(document.getElementById('empresa')?.textContent).toContain('Padaria do Centro — 11.222.333/0001-81');
        expect(document.getElementById('empresa')?.getAttribute('aria-invalid')).toBe('true');
    });
});
