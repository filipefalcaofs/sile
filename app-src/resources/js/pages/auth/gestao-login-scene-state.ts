/**
 * Máquina de estados e matemática da cena de alinhamento do login de
 * gestão. Módulo puro, sem DOM e sem React: é aqui que mora tudo que
 * define para onde cada elemento da cena vai em cada estado da sessão.
 * O loop de animação (gestao-login-scene.tsx) só persegue estes alvos.
 */

export type FaseLogin = 'idle' | 'validating' | 'denied' | 'authorized' | 'preparing' | 'docking';

export type EstadoLogico = FaseLogin | 'session' | 'secure' | 'ready';

export type TipoConta = { rotulo: string; selo: string };

export type AlvosCena = Record<string, number>;

/** Fases em que o palco já virou sidebar: sem paralaxe, centro congelado. */
export const ESTADOS_SHELL: FaseLogin[] = ['docking'];

/** Estados em que os três anéis estão sobrepostos e os rótulos somem. */
export const ESTADOS_FUNDIDOS: EstadoLogico[] = ['validating', 'authorized', 'preparing', 'docking'];

export const TAXA_PADRAO = 3.2;

/**
 * Velocidade de perseguição de cada valor animado. Quanto maior, mais
 * rápido o valor alcança o alvo. Os que não aparecem aqui usam TAXA_PADRAO.
 */
export const TAXAS: Record<string, number> = {
    sec_rot: 2.2,
    disc_x: 3.4,
    disc_y: 3.4,
    disc_k: 3.4,
    ax_y: 9,
    ph_b: 2,
    drift: 1.5,
    ax_len: 3.6,
};

export const TEMPOS = {
    /** Piso do beat de validação: abaixo disso a fase colapsa em rede rápida. */
    pisoValidacao: 1450,
    preparing: 900,
    docking: 2000,
    navegacao: 2900,
    denied: 1100,
    debounceIdentificador: 520,
} as const;

export const TEXTOS_ESTADO: Record<EstadoLogico, { numero: string; texto: string }> = {
    idle: { numero: '01', texto: 'Central em espera' },
    session: { numero: '02', texto: 'Operador iniciou uma sessão' },
    secure: { numero: '02', texto: 'Camada de autorização em preparo' },
    ready: { numero: '02', texto: 'Pronto para iniciar a análise' },
    validating: { numero: '03', texto: 'Validando credencial' },
    denied: { numero: '03', texto: 'Credencial não confere' },
    authorized: { numero: '04', texto: 'Operador autorizado' },
    preparing: { numero: '05', texto: 'Preparando análise de viabilidade' },
    docking: { numero: '06', texto: 'Central de viabilidade aberta' },
};

/** Leituras que a central alterna enquanto espera, a cada ~4 s. */
export const LEITURAS_ESPERA = [
    'Central em espera',
    'Varredura territorial ativa',
    'Motor de regras em sincronia',
] as const;

export const TEMPO_LEITURA = 4000;

/**
 * Posições fixas dos pontos de análise sobre a cidade, em fração da largura
 * e da altura do palco. Oito consultas territoriais sendo processadas.
 */
export const PONTOS_ANALISE: ReadonlyArray<readonly [number, number]> = [
    [0.18, 0.32],
    [0.31, 0.58],
    [0.44, 0.24],
    [0.52, 0.71],
    [0.63, 0.42],
    [0.72, 0.62],
    [0.81, 0.3],
    [0.88, 0.55],
];

export function corDoEstado(estado: EstadoLogico): string {
    if (estado === 'denied') {
        return 'oklch(78% 0.13 25)';
    }

    if (estado === 'authorized' || estado === 'preparing') {
        return 'oklch(82% 0.1 250)';
    }

    if (estado === 'idle') {
        return 'rgba(255,255,255,0.6)';
    }

    return 'rgba(255,255,255,0.88)';
}

export function classificarIdentificador(valor: string): TipoConta | null {
    const texto = (valor || '').trim();

    if (texto === '') {
        return null;
    }

    if (texto.includes('@')) {
        if (/@salvador\.ba\.gov\.br$/i.test(texto)) {
            return { rotulo: 'E-mail institucional', selo: 'E-mail institucional' };
        }

        if (/^[^@\s]+@[^@\s]+\.[^@\s]{2,}$/.test(texto)) {
            return { rotulo: 'Conta local', selo: 'Conta local' };
        }

        return null;
    }

    if (/^[a-z0-9._-]{3,}$/i.test(texto)) {
        return { rotulo: 'Rede corporativa (AD)', selo: 'Rede corporativa · AD' };
    }

    return null;
}

export function estadoLogico(entrada: {
    fase: FaseLogin;
    identificador: string;
    temSenha: boolean;
    comFoco: boolean;
    tipoConta: TipoConta | null;
}): EstadoLogico {
    if (entrada.fase !== 'idle') {
        return entrada.fase;
    }

    if (entrada.tipoConta && entrada.temSenha && entrada.identificador !== '') {
        return 'ready';
    }

    if (entrada.temSenha) {
        return 'secure';
    }

    if (entrada.comFoco || entrada.identificador !== '') {
        return 'session';
    }

    return 'idle';
}

/**
 * Vida dos anéis no estado de espera. `fator` vem do alvo do estado: 1 em
 * idle, 0 quando a cena precisa ficar imóvel.
 */
export function deriva(indice: number, tempoMs: number, fator: number): { dx: number; dy: number } {
    const dx = Math.sin(tempoMs * 0.00031 * (1 + indice * 0.27) + indice * 2.1) * 26 * fator;
    const dy = Math.cos(tempoMs * 0.00027 * (1 + indice * 0.19) + indice * 1.3) * 20 * fator;

    return { dx, dy };
}

type PosicaoAnel = [x: number, y: number, escala: number, blur: number, opacidade: number];

const SESSION: PosicaoAnel[] = [
    [-72, -40, 0.95, 1.2, 0.72],
    [68, -22, 1, 0, 0.84],
    [-4, 70, 0.9, 1.6, 0.64],
];

const CENTRO: PosicaoAnel[] = [
    [0, 0, 1, 0, 0.95],
    [0, 0, 1, 0, 0.95],
    [0, 0, 1, 0, 0.95],
];

function aplicarAneis(alvos: AlvosCena, posicoes: PosicaoAnel[]): void {
    ['a', 'b', 'c'].forEach((chave, indice) => {
        const [x, y, escala, blur, opacidade] = posicoes[indice];
        alvos[`${chave}_x`] = x;
        alvos[`${chave}_y`] = y;
        alvos[`${chave}_s`] = escala;
        alvos[`${chave}_b`] = blur;
        alvos[`${chave}_o`] = opacidade;
    });
}

/**
 * Alvos de todos os valores animados no estado informado.
 *
 * @param base - Centro da cena na viewport, em px.
 * @param rotacaoBase - Ângulo já acumulado pelo arco, para somar mais uma volta sem recuar.
 * @param alvoYLinha - Altura na viewport onde a linha de conexão encosta no painel.
 */
export function alvosPorEstado(
    estado: EstadoLogico,
    base: { x: number; y: number },
    rotacaoBase: number,
    alvoYLinha: number,
): AlvosCena {
    const alvos: AlvosCena = {
        drift: 1,
        rs: 1,
        sec_c: 0,
        sec_o: 0,
        sec_rot: rotacaoBase,
        sec_red: 0,
        disc_s: 0,
        disc_x: 0,
        disc_y: 0,
        disc_k: 1,
        disc_o: 1,
        ph_b: 5,
        ph_s: 1.02,
        ph_br: 0.74,
        ax_o: 0,
        ax_len: 0,
        ax_y: alvoYLinha,
    };

    switch (estado) {
        case 'idle':
            aplicarAneis(alvos, [
                [-130, -72, 0.9, 2.4, 0.46],
                [124, -36, 1, 0.6, 0.56],
                [-8, 120, 0.82, 3.2, 0.42],
            ]);
            break;

        case 'session':
            aplicarAneis(alvos, SESSION);
            Object.assign(alvos, { drift: 0.5, ph_b: 3.2, ph_br: 0.82, ax_o: 1, ax_len: 1 });
            break;

        case 'secure':
            aplicarAneis(
                alvos,
                SESSION.map(([x, y, escala, blur, opacidade]): PosicaoAnel => [
                    x * 0.72,
                    y * 0.72,
                    escala,
                    blur * 0.5,
                    opacidade + 0.08,
                ]),
            );
            Object.assign(alvos, { drift: 0.32, sec_o: 1, sec_c: 58, ph_b: 2.4, ph_br: 0.85, ax_o: 1, ax_len: 1 });
            break;

        case 'ready':
            aplicarAneis(alvos, [
                [-16, -9, 0.99, 0, 0.92],
                [16, -5, 1, 0, 0.92],
                [-2, 16, 0.98, 0, 0.9],
            ]);
            Object.assign(alvos, { drift: 0.12, sec_o: 1, sec_c: 86, ph_b: 1.4, ph_br: 0.9, ax_o: 1, ax_len: 1 });
            break;

        case 'validating':
            aplicarAneis(alvos, CENTRO);
            Object.assign(alvos, {
                drift: 0,
                sec_o: 1,
                sec_c: 100,
                sec_rot: rotacaoBase + 360,
                ph_b: 0.6,
                ph_br: 0.95,
                ax_o: 1,
                ax_len: 0,
            });
            break;

        case 'denied':
            aplicarAneis(alvos, [
                [-165, -96, 0.88, 2.2, 0.5],
                [162, -56, 1, 1, 0.56],
                [-20, 165, 0.84, 2.6, 0.46],
            ]);
            Object.assign(alvos, {
                drift: 1.3,
                sec_o: 1,
                sec_c: 16,
                sec_red: 1,
                sec_rot: rotacaoBase + 360,
                ph_b: 4,
                ph_br: 0.78,
            });
            break;

        case 'authorized':
            aplicarAneis(alvos, CENTRO);
            Object.assign(alvos, {
                drift: 0,
                sec_o: 0.55,
                sec_c: 100,
                sec_rot: rotacaoBase + 360,
                disc_s: 1,
                ph_b: 0,
                ph_br: 1.02,
            });
            break;

        case 'preparing':
            aplicarAneis(alvos, CENTRO);
            Object.assign(alvos, {
                drift: 0,
                sec_o: 0.4,
                sec_c: 100,
                sec_rot: rotacaoBase + 360,
                disc_s: 1,
                ph_b: 0,
                ph_br: 1.04,
                ph_s: 1.05,
            });
            break;

        default:
            // docking: os anéis atravessam a lente enquanto o disco vira o
            // logo da sidebar do console, em (38, 50) na viewport.
            aplicarAneis(alvos, [
                [0, 0, 1, 4, 0],
                [0, 0, 1, 4, 0],
                [0, 0, 1, 4, 0],
            ]);
            Object.assign(alvos, {
                drift: 0,
                rs: 3.4,
                sec_o: 0,
                sec_c: 100,
                sec_rot: rotacaoBase + 360,
                disc_s: 1,
                disc_x: 38 - base.x,
                disc_y: 50 - base.y,
                disc_k: 0.3,
                disc_o: 1,
                ph_b: 7,
                ph_s: 1.12,
                ph_br: 0.9,
            });
    }

    return alvos;
}
