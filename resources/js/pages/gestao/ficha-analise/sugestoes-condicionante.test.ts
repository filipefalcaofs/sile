import { describe, expect, it } from 'vitest';

import { sugerirCondicionantes } from './sugestoes-condicionante';

const texto = (id: number, content: string, category = 'condicionante') => ({ id, category, content });

describe('sugestões de condicionante a partir da biblioteca', () => {
    it('menos de 3 letras não sugere', () => {
        expect(sugerirCondicionantes([texto(1, 'Desde que seja escritório')], 'de', [])).toEqual([]);
    });

    it('ignora acento e maiúscula', () => {
        const textos = [texto(1, 'Desde que a Área utilizada não ultrapasse 1.250m²'), texto(2, 'Desde que seja escritório da empresa')];

        expect(sugerirCondicionantes(textos, 'area util', []).map((s) => s.id)).toEqual([1]);
    });

    it('condicionantes vêm antes dos demais', () => {
        const textos = [texto(1, 'Deferido para escritório da empresa.', 'deferimento'), texto(2, 'Desde que seja escritório da empresa')];

        expect(sugerirCondicionantes(textos, 'escritorio', [])[0].id).toBe(2);
    });

    it('não repete o que já foi adicionado', () => {
        const textos = [texto(1, 'Desde que seja escritório da empresa'), texto(2, 'Desde que o escritório tenha recepção')];

        expect(sugerirCondicionantes(textos, 'escritorio', ['  Desde que seja escritório da empresa ']).map((s) => s.id)).toEqual([2]);
    });

    it('respeita o limite', () => {
        const textos = Array.from({ length: 7 }, (_, i) => texto(i + 1, `Desde que cumpra a exigência ${i + 1}`));

        expect(sugerirCondicionantes(textos, 'exigencia', [])).toHaveLength(5);
    });

    it('reconhece a categoria condicionante em qualquer grafia', () => {
        const textos = [texto(1, 'Deferido para escritório da empresa.', 'deferimento'), texto(2, 'Desde que seja escritório', 'Condicionantes')];

        expect(sugerirCondicionantes(textos, 'escritorio', [])[0].id).toBe(2);
    });
});
