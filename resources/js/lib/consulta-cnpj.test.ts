import { describe, expect, it } from 'vitest';
import { mensagemBuscaCnpj } from './consulta-cnpj';

describe('mensagemBuscaCnpj', () => {
    it('mostra que o CNPJ é inválido sem orientar preenchimento manual', () => {
        expect(mensagemBuscaCnpj({ cnpj: 'O campo cnpj não é um CNPJ válido.' })).toBe(
            'O campo cnpj não é um CNPJ válido.',
        );
        expect(mensagemBuscaCnpj({ errors: { cnpj: ['O CNPJ informado é inválido.'] } })).toBe(
            'O CNPJ informado é inválido.',
        );
    });

    it('orienta o preenchimento manual quando a consulta está indisponível', () => {
        expect(mensagemBuscaCnpj(null)).toBe('Não foi possível consultar o CNPJ. Preencha os dados manualmente.');
        expect(mensagemBuscaCnpj({ message: 'Serviço indisponível' })).toBe(
            'Não foi possível consultar o CNPJ. Preencha os dados manualmente.',
        );
    });
});