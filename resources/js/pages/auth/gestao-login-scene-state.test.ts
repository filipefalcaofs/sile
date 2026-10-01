import { describe, expect, it } from 'vitest';
import {
    ESTADOS_SHELL,
    ORDEM_ZONAS,
    TEMPOS,
    ZONAS,
    alvosPorEstado,
    classificarIdentificador,
    coordenadas,
    corDoEstado,
    deriva,
    estadoLogico,
    geometriaZonas,
    intersecta,
    pontoNaPolilinha,
    raioOnda,
    retanguloEtiqueta,
} from './gestao-login-scene-state';
import type { TipoConta } from './gestao-login-scene-state';

const contaLocal: TipoConta = { rotulo: 'Conta local', selo: 'Conta local', badge: 'Conta local' };

function entrada(parcial: Partial<Parameters<typeof estadoLogico>[0]> = {}) {
    return {
        fase: 'idle' as const,
        identificador: '',
        temSenha: false,
        comFoco: false,
        tipoConta: null,
        ...parcial,
    };
}

describe('Classificação do identificador', () => {
    it('reconhece o e-mail institucional pelo domínio da prefeitura', () => {
        expect(classificarIdentificador('maria.souza@salvador.ba.gov.br')).toEqual({
            rotulo: 'E-mail institucional',
            selo: 'E-mail institucional',
            badge: 'Institucional',
        });
    });

    it('reconhece qualquer outro e-mail válido como conta local', () => {
        expect(classificarIdentificador('maria@exemplo.com')).toEqual({
            rotulo: 'Conta local',
            selo: 'Conta local',
            badge: 'Conta local',
        });
    });

    it('reconhece usuário sem arroba como rede corporativa', () => {
        expect(classificarIdentificador('msouza')).toEqual({
            rotulo: 'Rede corporativa (AD)',
            selo: 'Rede corporativa · AD',
            badge: 'Rede AD',
        });
    });

    it('não classifica campo vazio, usuário curto demais nem e-mail incompleto', () => {
        expect(classificarIdentificador('')).toBeNull();
        expect(classificarIdentificador('   ')).toBeNull();
        expect(classificarIdentificador('ab')).toBeNull();
        expect(classificarIdentificador('maria@')).toBeNull();
    });
});

describe('Estado lógico derivado', () => {
    it('em repouso, sem foco e sem nada digitado, é idle', () => {
        expect(estadoLogico(entrada())).toBe('idle');
    });

    it('foco em qualquer campo já abre a sessão', () => {
        expect(estadoLogico(entrada({ comFoco: true }))).toBe('session');
    });

    it('identificador digitado mantém a sessão mesmo sem foco', () => {
        expect(estadoLogico(entrada({ identificador: 'msouza' }))).toBe('session');
    });

    it('senha iniciada leva a secure mesmo sem identificador reconhecido', () => {
        expect(estadoLogico(entrada({ temSenha: true, identificador: 'ab' }))).toBe('secure');
    });

    it('identificador reconhecido mais senha leva a ready', () => {
        expect(
            estadoLogico(entrada({ identificador: 'msouza', temSenha: true, tipoConta: contaLocal })),
        ).toBe('ready');
    });

    it('a fase tem precedência sobre tudo que foi digitado', () => {
        expect(
            estadoLogico(
                entrada({ fase: 'validating', identificador: 'msouza', temSenha: true, tipoConta: contaLocal }),
            ),
        ).toBe('validating');
        expect(estadoLogico(entrada({ fase: 'denied' }))).toBe('denied');
        expect(estadoLogico(entrada({ fase: 'docking' }))).toBe('docking');
    });
});

describe('Alvos por estado', () => {
    const base = { x: 700, y: 400 };

    it('em idle os três anéis ficam espalhados, desfocados e com o arco apagado', () => {
        const alvos = alvosPorEstado('idle', base, 0, 500);

        expect(alvos.a_x).toBe(-130);
        expect(alvos.a_y).toBe(-72);
        expect(alvos.c_b).toBe(3.2);
        expect(alvos.sec_c).toBe(0);
        expect(alvos.sec_o).toBe(0);
        expect(alvos.ax_o).toBe(0);
        expect(alvos.drift).toBe(1);
    });

    it('em ready os anéis quase se tocam e o arco vai a 86', () => {
        const alvos = alvosPorEstado('ready', base, 0, 500);

        expect(alvos.a_x).toBe(-16);
        expect(alvos.sec_c).toBe(86);
        expect(alvos.drift).toBe(0.12);
        expect(alvos.ax_len).toBe(1);
    });

    it('em validating os três anéis vão ao centro e o arco fecha girando 360 graus', () => {
        const alvos = alvosPorEstado('validating', base, 720, 500);

        expect(alvos.a_x).toBe(0);
        expect(alvos.b_x).toBe(0);
        expect(alvos.c_x).toBe(0);
        expect(alvos.sec_c).toBe(100);
        expect(alvos.sec_rot).toBe(1080);
        expect(alvos.ax_len).toBe(0);
    });

    it('em denied o arco fica vermelho e os anéis se afastam', () => {
        const alvos = alvosPorEstado('denied', base, 0, 500);

        expect(alvos.sec_red).toBe(1);
        expect(alvos.sec_c).toBe(16);
        expect(alvos.a_x).toBe(-165);
        expect(alvos.drift).toBe(1.3);
    });

    it('em authorized o disco aparece e a foto perde o desfoque', () => {
        const alvos = alvosPorEstado('authorized', base, 0, 500);

        expect(alvos.disc_s).toBe(1);
        expect(alvos.ph_b).toBe(0);
        expect(alvos.sec_o).toBe(0.55);
    });

    it('em docking o disco viaja até o logo da sidebar e encolhe para 36px', () => {
        const alvos = alvosPorEstado('docking', base, 0, 500);

        expect(alvos.disc_x).toBe(38 - base.x);
        expect(alvos.disc_y).toBe(50 - base.y);
        expect(alvos.disc_k).toBe(0.3);
        expect(alvos.rs).toBe(3.4);
        expect(alvos.a_o).toBe(0);
        expect(alvos.drift).toBe(0);
    });

    it('em idle as camadas vivas usam os valores padrão de FX', () => {
        const alvos = alvosPorEstado('idle', base, 0, 500);

        expect(alvos.fx_o).toBe(1);
        expect(alvos.grid_o).toBe(0.5);
        expect(alvos.sweep_o).toBe(0.45);
        expect(alvos.light_o).toBe(0.42);
        expect(alvos.spin).toBe(1);
        expect(alvos.radar_o).toBe(0);
        expect(alvos.wind).toBe(1);
        expect(alvos.attract).toBe(0);
    });

    it('em validating o radar liga a 320 graus por segundo e a convergência ativa', () => {
        const alvos = alvosPorEstado('validating', base, 0, 500);

        expect(alvos.radar_o).toBe(1);
        expect(alvos.radar_v).toBe(320);
        expect(alvos.conv).toBe(1);
        expect(alvos.sweep_o).toBe(0);
        expect(alvos.spin).toBe(3);
    });

    it('em preparing a malha acelera e as partículas são atraídas', () => {
        const alvos = alvosPorEstado('preparing', base, 0, 500);

        expect(alvos.grid_v).toBe(6);
        expect(alvos.attract).toBe(1);
        expect(alvos.zmap).toBe(0.75);
        expect(alvos.light_k).toBe(1.45);
        expect(alvos.wind).toBe(0);
    });

    it('em docking as camadas vivas apagam', () => {
        const alvos = alvosPorEstado('docking', base, 0, 500);

        expect(alvos.fx_o).toBe(0);
        expect(alvos.grid_o).toBe(0);
        expect(alvos.light_o).toBe(0);
        expect(alvos.light_k).toBe(2);
        expect(alvos.attract).toBe(0.6);
    });
});

describe('Geometria das zonas', () => {
    it('mantém as nove zonas fora da faixa da manchete (y entre 0.30 e 0.66)', () => {
        expect(ZONAS).toHaveLength(9);

        ZONAS.forEach((zona) => {
            zona.p.forEach(([, fy]) => {
                expect(fy <= 0.3 || fy >= 0.66).toBe(true);
            });
        });
    });

    it('a ordem de ativação percorre as nove zonas sem repetir', () => {
        expect([...ORDEM_ZONAS].sort((a, b) => a - b)).toEqual([0, 1, 2, 3, 4, 5, 6, 7, 8]);
    });

    it('calcula vértices em px, centroide e perímetro com a paralaxe aplicada', () => {
        const zonas = geometriaZonas(1000, 500, -14, 10);
        const primeira = zonas[0];

        expect(zonas).toHaveLength(9);
        expect(primeira.pontos[0]).toEqual([0.31 * 1000 - 14, 0.15 * 500 + 10]);
        expect(primeira.cx).toBeCloseTo((310 + 470 + 500 + 330) / 4 - 14, 6);
        expect(primeira.cy).toBeCloseTo((75 + 65 + 135 + 145) / 4 + 10, 6);

        const esperado = primeira.pontos.reduce((soma, ponto, indice) => {
            const proximo = primeira.pontos[(indice + 1) % primeira.pontos.length];

            return soma + Math.hypot(proximo[0] - ponto[0], proximo[1] - ponto[1]);
        }, 0);

        expect(primeira.perimetro).toBeCloseTo(esperado, 6);
    });
});

describe('Ondas e pulsos', () => {
    it('o raio da onda segue o ease-out cúbico', () => {
        expect(raioOnda(0, 400)).toBe(0);
        expect(raioOnda(1, 400)).toBe(400);
        expect(raioOnda(0.5, 400)).toBeCloseTo((1 - 0.125) * 400, 10);
    });

    it('o pulso percorre a polilinha segmento a segmento', () => {
        const pontos: Array<[number, number]> = [
            [0, 0],
            [10, 0],
            [10, 20],
        ];

        expect(pontoNaPolilinha(pontos, 0)).toEqual([0, 0]);
        expect(pontoNaPolilinha(pontos, 5)).toEqual([5, 0]);
        expect(pontoNaPolilinha(pontos, 15)).toEqual([10, 5]);
        expect(pontoNaPolilinha(pontos, 999)).toEqual([10, 20]);
    });
});

describe('Etiqueta de zona', () => {
    it('estima a largura pelo texto e cresce para a esquerda após 74% do palco', () => {
        const esquerda = retanguloEtiqueta({ cx: 300, cy: 200 }, 0, 1000);
        const zona = ZONAS[0];
        const larguraEsperada = Math.max(zona.id.length + zona.nome.length + 3, zona.leitura.length) * 7.4 + 24;

        expect(esquerda.direita).toBe(false);
        expect(esquerda.x).toBe(312);
        expect(esquerda.y).toBe(186);
        expect(esquerda.w).toBeCloseTo(larguraEsperada, 6);
        expect(esquerda.h).toBe(36);

        const direita = retanguloEtiqueta({ cx: 900, cy: 200 }, 0, 1000);

        expect(direita.direita).toBe(true);
        expect(direita.x).toBeCloseTo(900 - 12 - larguraEsperada, 6);
    });

    it('detecta interseção considerando a margem de segurança', () => {
        const etiqueta = { x: 100, y: 100, w: 50, h: 36 };
        const caixa = { left: 180, right: 260, top: 100, bottom: 130 };

        expect(intersecta(etiqueta, caixa, 34)).toBe(true);
        expect(intersecta(etiqueta, caixa, 10)).toBe(false);
    });
});

describe('Coordenadas vivas', () => {
    it('trava no alvo quando o anel B está centralizado', () => {
        expect(coordenadas(0, 0)).toBe('12°58′14.0″S · 38°30′07.0″W');
    });

    it('deriva com a posição do anel B e normaliza em 60 segundos', () => {
        expect(coordenadas(20, -40)).toBe('12°58′12.0″S · 38°30′08.0″W');
        expect(coordenadas(-1200, 0)).toBe('12°58′14.0″S · 38°30′07.0″W');
    });
});

describe('Deriva dos anéis', () => {
    it('zera quando o fator do estado é zero', () => {
        const { dx, dy } = deriva(0, 12345, 0);

        expect(dx).toBeCloseTo(0, 10);
        expect(dy).toBeCloseTo(0, 10);
    });

    it('segue a fórmula senoidal documentada', () => {
        const t = 10000;
        const { dx, dy } = deriva(1, t, 1);

        expect(dx).toBeCloseTo(Math.sin(t * 0.00031 * 1.27 + 2.1) * 26, 10);
        expect(dy).toBeCloseTo(Math.cos(t * 0.00027 * 1.19 + 1.3) * 20, 10);
    });

    it('escala linearmente com o fator', () => {
        const cheio = deriva(2, 7000, 1);
        const meio = deriva(2, 7000, 0.5);

        expect(meio.dx).toBeCloseTo(cheio.dx / 2, 10);
        expect(meio.dy).toBeCloseTo(cheio.dy / 2, 10);
    });
});

describe('Tempos e cores', () => {
    it('a sequência de sucesso soma 2900 ms até a navegação', () => {
        expect(TEMPOS.preparing).toBe(900);
        expect(TEMPOS.docking).toBe(2000);
        expect(TEMPOS.navegacao).toBe(2900);
    });

    it('a recusa dura 1100 ms e o reconhecimento espera 520 ms', () => {
        expect(TEMPOS.denied).toBe(1100);
        expect(TEMPOS.debounceIdentificador).toBe(520);
    });

    it('docking é um estado de shell, ready não é', () => {
        expect(ESTADOS_SHELL).toContain('docking');
        expect(ESTADOS_SHELL).not.toContain('validating');
    });

    it('a recusa pinta o texto de vermelho e a autorização de azul claro', () => {
        expect(corDoEstado('denied')).toBe('oklch(78% 0.13 25)');
        expect(corDoEstado('authorized')).toBe('oklch(82% 0.1 250)');
        expect(corDoEstado('idle')).toBe('rgba(255,255,255,0.6)');
        expect(corDoEstado('ready')).toBe('rgba(255,255,255,0.88)');
    });
});
