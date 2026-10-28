import { describe, expect, it } from 'vitest';

import { duracaoNaTramitacao } from './duracao-tramitacao';

const agora = new Date('2026-10-28T12:00:00-03:00').getTime();

describe('selo de tempo na tramitação', () => {
    it('etapa encerrada mostra o tempo até a próxima situação', () => {
        expect(duracaoNaTramitacao('protocolada', '2026-10-27T18:50:00-03:00', '2026-10-27T18:50:10-03:00', agora)).toBe('0 min');
    });

    it.each(['deferida', 'indeferida', 'cancelada', 'desativado', 'cassado', 'revogado'])(
        'situação final %s sem próxima etapa não mostra tempo',
        (situacao) => {
            expect(duracaoNaTramitacao(situacao, '2026-10-27T18:50:00-03:00', null, agora)).toBeNull();
        },
    );

    it('situação atual em andamento mostra há quanto tempo está nela', () => {
        expect(duracaoNaTramitacao('em_analise', '2026-10-27T18:50:00-03:00', null, agora)).toBe('há 17 h');
    });

    it('situação final que foi reaberta mostra o tempo que durou', () => {
        expect(duracaoNaTramitacao('deferida', '2026-10-27T18:50:00-03:00', '2026-10-27T20:50:00-03:00', agora)).toBe('2 h');
    });

    it('tempos longos saem em dias', () => {
        expect(duracaoNaTramitacao('em_pendencia', '2026-10-20T12:00:00-03:00', null, agora)).toBe('há 8 dias');
    });
});
