import { describe, expect, it } from 'vitest';
import { calcularPreenchimento, camposVazios, type CamposContados } from './preenchimento';

function entrada(parcial: Partial<CamposContados> = {}, extras: { quantidadeAnexos?: number; poligonoValidado?: boolean } = {}) {
    return calcularPreenchimento({
        dados: { ...camposVazios(), ...parcial },
        quantidadeAnexos: extras.quantidadeAnexos ?? 0,
        poligonoValidado: extras.poligonoValidado ?? false,
    });
}

function secao(resumo: ReturnType<typeof entrada>, id: string) {
    const encontrada = resumo.secoes.find((item) => item.id === id);

    if (!encontrada) {
        throw new Error(`Seção ${id} ausente`);
    }

    return encontrada;
}

describe('preenchimento da ficha de vistoria', () => {
    it('começa vazia e não trata o parecer curto como pronto', () => {
        const resumo = entrada({ parecer: 'ok' });

        expect(secao(resumo, 'localizacao')).toMatchObject({ preenchidos: 0, total: 3, situacao: 'vazia' });
        expect(secao(resumo, 'imovel').total).toBe(17);
        expect(secao(resumo, 'entorno').total).toBe(18);
        expect(secao(resumo, 'condicoes').total).toBe(5);
        expect(secao(resumo, 'documentacao')).toMatchObject({ preenchidos: 0, total: 2 });
        expect(secao(resumo, 'conclusao')).toMatchObject({ preenchidos: 0, total: 4, situacao: 'vazia' });
        expect(resumo.parecerPronto).toBe(false);
        expect(resumo.percentual).toBe(0);
    });

    it('conta o parecer a partir de 3 caracteres e o polígono validado', () => {
        const resumo = entrada({ parecer: 'Área murada.' }, { poligonoValidado: true });

        expect(resumo.parecerPronto).toBe(true);
        expect(secao(resumo, 'conclusao').preenchidos).toBe(1);
        expect(secao(resumo, 'conclusao').situacao).toBe('parcial');
        expect(secao(resumo, 'localizacao')).toMatchObject({ preenchidos: 1, situacao: 'parcial' });
    });

    it('só inclui a origem do ruído quando há ruído', () => {
        const semRuido = entrada({ sons_ruidos: '0', sons_ruidos_origem: 'gerador' });
        const comRuido = entrada({ sons_ruidos: '1', sons_ruidos_origem: 'gerador' });

        expect(secao(semRuido, 'condicoes').total).toBe(5);
        expect(secao(semRuido, 'condicoes').preenchidos).toBe(1);
        expect(secao(comRuido, 'condicoes')).toMatchObject({ preenchidos: 2, total: 6 });
    });

    it('marca a seção como completa quando todos os itens dela estão preenchidos', () => {
        const resumo = entrada({}, { quantidadeAnexos: 2, poligonoValidado: false });

        expect(secao(resumo, 'documentacao')).toMatchObject({
            preenchidos: 1,
            total: 2,
            situacao: 'parcial',
        });

        const completa = entrada({ observacoes: 'Fotos no anexo.' }, { quantidadeAnexos: 1 });

        expect(secao(completa, 'documentacao')).toMatchObject({
            preenchidos: 2,
            total: 2,
            situacao: 'completa',
        });
    });
});
