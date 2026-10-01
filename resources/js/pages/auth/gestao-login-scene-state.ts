/**
 * Máquina de estados e matemática da cena de alinhamento do login de
 * gestão. Módulo puro, sem DOM e sem React: é aqui que mora tudo que
 * define para onde cada elemento da cena vai em cada estado da sessão.
 * O loop de animação (gestao-login-scene.tsx) só persegue estes alvos.
 */

export type FaseLogin = 'idle' | 'validating' | 'denied' | 'authorized' | 'preparing' | 'docking';

export type EstadoLogico = FaseLogin | 'session' | 'secure' | 'ready';

export type TipoConta = { rotulo: string; selo: string; badge: string };

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
    fx_o: 2.4,
    grid_o: 1.6,
    grid_v: 1.4,
    light_o: 2,
    light_k: 1.6,
    zmap: 1.6,
    conv: 2.6,
    radar_o: 3,
};

export const TEMPOS = {
    /** Piso do beat de validação: abaixo disso a fase colapsa em rede rápida. */
    pisoValidacao: 1450,
    preparing: 900,
    docking: 2000,
    /**
     * Navegação em docking + 1150 ms (protótipo: open em T0+3150): a
     * transição de largura do palco (1.05 s) termina em 3050 e a cena já
     * esmaeceu — o swap de página acontece com a tela idêntica ao console.
     */
    navegacao: 3150,
    /** Início do fade da cena dentro do docking (termina na navegação). */
    desvanecerCena: 550,
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

/** Cores do canvas de efeitos, em componentes rgb literais (§4.3 do handoff). */
export const CANVAS_RGB = {
    branco: '210,225,255',
    azul: '96,152,240',
    vermelho: '232,98,88',
} as const;

export type Zona = {
    id: string;
    nome: string;
    leitura: string;
    /** Vértices do polígono em fração da largura/altura do palco. */
    p: ReadonlyArray<readonly [number, number]>;
};

/**
 * Zonas da malha cadastral estilizada sobre a cidade. Faixa superior em
 * y ∈ [.12, .30] e inferior em y ∈ [.66, .83] — a área da manchete fica
 * livre. Índices CA/TO são ilustrativos (dado do protótipo, não consulta).
 */
export const ZONAS: readonly Zona[] = [
    { id: 'ZCM-02', nome: 'Centralidade municipal', leitura: 'CA 3,0 · TO 70% · Viável', p: [[0.31, 0.15], [0.47, 0.13], [0.5, 0.27], [0.33, 0.29]] },
    { id: 'ZUS-04', nome: 'Uso sustentável', leitura: 'CA 2,0 · TO 60% · Viável', p: [[0.47, 0.13], [0.66, 0.155], [0.68, 0.28], [0.5, 0.27]] },
    { id: 'ZEIS-03', nome: 'Interesse social', leitura: 'CA 1,5 · Condicionado', p: [[0.66, 0.155], [0.82, 0.12], [0.86, 0.26], [0.68, 0.28]] },
    { id: 'ZPAM-01', nome: 'Proteção ambiental', leitura: 'Uso restrito · Inviável', p: [[0.82, 0.12], [0.97, 0.15], [0.96, 0.3], [0.86, 0.26]] },
    { id: 'ZPR-12', nome: 'Predominantemente residencial', leitura: 'CA 1,8 · TO 50% · Viável', p: [[0.07, 0.69], [0.25, 0.67], [0.27, 0.81], [0.09, 0.83]] },
    { id: 'ZCM-07', nome: 'Centralidade metropolitana', leitura: 'CA 4,0 · TO 75% · Viável', p: [[0.25, 0.67], [0.44, 0.7], [0.45, 0.82], [0.27, 0.81]] },
    { id: 'ZUS-09', nome: 'Uso sustentável', leitura: 'CA 2,0 · Condicionado', p: [[0.44, 0.7], [0.63, 0.66], [0.66, 0.8], [0.45, 0.82]] },
    { id: 'ZT-05', nome: 'Turística', leitura: 'CA 2,5 · TO 60% · Viável', p: [[0.63, 0.66], [0.8, 0.69], [0.83, 0.82], [0.66, 0.8]] },
    { id: 'ZEIS-11', nome: 'Interesse social', leitura: 'CA 1,2 · Viável', p: [[0.8, 0.69], [0.96, 0.66], [0.97, 0.81], [0.83, 0.82]] },
];

/** Ordem em que a varredura concede a etiqueta: alterna faixa inferior/superior. */
export const ORDEM_ZONAS: readonly number[] = [5, 1, 7, 2, 4, 0, 8, 3, 6];

/**
 * Alvos padrão das camadas vivas (§10.4 do handoff): malha, varredura,
 * luz, radar, vento das partículas e cidade mapeada. Aplicados depois dos
 * alvos dos anéis; cada estado sobrescreve só o que diverge do padrão.
 */
export const ALVOS_FX_PADRAO: Record<string, number> = {
    fx_o: 1,
    grid_o: 0.5,
    grid_v: 0.6,
    zone_b: 0.07,
    zmap: 0,
    conv: 0,
    sweep_o: 0.45,
    light_o: 0.42,
    light_k: 1,
    spin: 1,
    radar_o: 0,
    radar_v: 0,
    wind: 1,
    attract: 0,
};

export const ALVOS_FX: Partial<Record<EstadoLogico, Record<string, number>>> = {
    session: { grid_o: 0.62, grid_v: 0.35, zone_b: 0.045, sweep_o: 0.3, light_o: 0.58, spin: 0.6, wind: 0.6 },
    secure: { grid_o: 0.68, grid_v: 0.3, zone_b: 0.045, sweep_o: 0.26, light_o: 0.66, spin: 0.5, wind: 0.5 },
    ready: { grid_o: 0.74, grid_v: 0.25, zone_b: 0.045, sweep_o: 0.2, light_o: 0.78, spin: 0.4, wind: 0.4 },
    validating: { grid_o: 0.7, grid_v: 1.4, zone_b: 0.04, conv: 1, sweep_o: 0, light_o: 0.85, light_k: 0.8, spin: 3, radar_o: 1, radar_v: 320, wind: 0.3 },
    denied: { grid_o: 0.45, grid_v: 0.2, zone_b: 0.06, sweep_o: 0.3, light_o: 0.3, spin: 1.4 },
    authorized: { grid_o: 0.8, grid_v: 1.6, zone_b: 0.05, zmap: 1, sweep_o: 0, light_o: 1, light_k: 1.25, spin: 0.3, radar_o: 0.25, radar_v: 140, wind: 0.2 },
    preparing: { grid_o: 0.9, grid_v: 6, zone_b: 0.05, zmap: 0.75, sweep_o: 0, light_o: 1, light_k: 1.45, spin: 0.2, wind: 0, attract: 1 },
    docking: { fx_o: 0, grid_o: 0, grid_v: 2, zone_b: 0, sweep_o: 0, light_o: 0, light_k: 2, spin: 0, wind: 0, attract: 0.6 },
};

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
            return { rotulo: 'E-mail institucional', selo: 'E-mail institucional', badge: 'Institucional' };
        }

        if (/^[^@\s]+@[^@\s]+\.[^@\s]{2,}$/.test(texto)) {
            return { rotulo: 'Conta local', selo: 'Conta local', badge: 'Conta local' };
        }

        return null;
    }

    if (/^[a-z0-9._-]{3,}$/i.test(texto)) {
        return { rotulo: 'Rede corporativa (AD)', selo: 'Rede corporativa · AD', badge: 'Rede AD' };
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

    Object.assign(alvos, ALVOS_FX_PADRAO, ALVOS_FX[estado] ?? {});

    return alvos;
}

export type GeometriaZona = {
    pontos: Array<[number, number]>;
    perimetro: number;
    cx: number;
    cy: number;
};

/**
 * Vértices das zonas em px do palco, com o deslocamento de paralaxe do
 * mouse, mais centroide e perímetro de cada polígono fechado (§11.1).
 */
export function geometriaZonas(largura: number, altura: number, ox: number, oy: number): GeometriaZona[] {
    return ZONAS.map((zona) => {
        const pontos = zona.p.map(([fx, fy]): [number, number] => [fx * largura + ox, fy * altura + oy]);
        let perimetro = 0;

        pontos.forEach((ponto, indice) => {
            const proximo = pontos[(indice + 1) % pontos.length];
            perimetro += Math.hypot(proximo[0] - ponto[0], proximo[1] - ponto[1]);
        });

        return {
            pontos,
            perimetro,
            cx: pontos.reduce((soma, ponto) => soma + ponto[0], 0) / pontos.length,
            cy: pontos.reduce((soma, ponto) => soma + ponto[1], 0) / pontos.length,
        };
    });
}

/** Raio da onda no instante `t` ∈ [0, 1]: ease-out cúbico até `max` (§11.5). */
export function raioOnda(t: number, max: number): number {
    return (1 - Math.pow(1 - t, 3)) * max;
}

/**
 * Ponto a uma distância `d` do início de uma polilinha, medida ao longo
 * dos segmentos. Usado pelo pulso de digitação ao viajar a linha (§11.9).
 */
export function pontoNaPolilinha(pontos: ReadonlyArray<readonly [number, number]>, distancia: number): [number, number] {
    if (pontos.length === 0) {
        return [0, 0];
    }

    let restante = Math.max(0, distancia);

    for (let indice = 1; indice < pontos.length; indice++) {
        const [x0, y0] = pontos[indice - 1];
        const [x1, y1] = pontos[indice];
        const comprimento = Math.hypot(x1 - x0, y1 - y0);
        const ultimo = indice === pontos.length - 1;

        if (restante <= comprimento || ultimo) {
            const u = comprimento > 0 ? Math.min(1, restante / comprimento) : 0;

            return [x0 + (x1 - x0) * u, y0 + (y1 - y0) * u];
        }

        restante -= comprimento;
    }

    const [fx, fy] = pontos[pontos.length - 1];

    return [fx, fy];
}

export type RetanguloEtiqueta = { x: number; y: number; w: number; h: number; direita: boolean };

/**
 * Retângulo estimado da etiqueta de zona, crescendo para a esquerda quando
 * o centroide passa de 74% da largura do palco (§12.4).
 */
export function retanguloEtiqueta(centro: { cx: number; cy: number }, indice: number, largura: number): RetanguloEtiqueta {
    const zona = ZONAS[indice];
    const w = Math.max(zona.id.length + zona.nome.length + 3, zona.leitura.length) * 7.4 + 24;
    const direita = centro.cx > largura * 0.74;

    return { x: direita ? centro.cx - 12 - w : centro.cx + 12, y: centro.cy - 14, w, h: 36, direita };
}

/**
 * Interseção com margem entre o retângulo da etiqueta e a caixa de um
 * elemento já renderizado (regra anti-sobreposição, §12.4).
 */
export function intersecta(
    etiqueta: { x: number; y: number; w: number; h: number },
    caixa: { left: number; right: number; top: number; bottom: number },
    margem: number,
): boolean {
    return !(
        etiqueta.x + etiqueta.w + margem < caixa.left ||
        etiqueta.x - margem > caixa.right ||
        etiqueta.y + etiqueta.h + margem < caixa.top ||
        etiqueta.y - margem > caixa.bottom
    );
}

/**
 * Coordenadas vivas do anel B: os segundos derivam da posição do anel e
 * travam em 14.0″S · 07.0″W quando os anéis centralizam (§10.9).
 */
export function coordenadas(bx: number, by: number): string {
    const f = (valor: number) => (((valor % 60) + 60) % 60).toFixed(1).padStart(4, '0');

    return `12°58′${f(14 + by * 0.05)}″S · 38°30′${f(7 + bx * 0.05)}″W`;
}
