import { describe, expect, it } from 'vitest';
import { temEtapaIndisponivel, type EtapaMotor } from './etapas-motor';

const etapa = (situacao: EtapaMotor['situacao']): EtapaMotor => ({
    etapa: 'zona',
    rotulo: 'Zona urbanística',
    servico: 'geoserver_zona',
    situacao,
    situacao_rotulo: situacao,
    motivo: null,
    tentativas: 1,
    proxima_tentativa_em: null,
    esgotada: false,
});

describe('temEtapaIndisponivel', () => {
    it('só oferece reprocessar quando alguma etapa está indisponível', () => {
        expect(temEtapaIndisponivel([etapa('ok'), etapa('indisponivel')])).toBe(true);
        expect(temEtapaIndisponivel([etapa('ok'), etapa('definitiva')])).toBe(false);
        expect(temEtapaIndisponivel([])).toBe(false);
    });
});
