import { useEffect, useRef, useState } from 'react';
import type { RefObject } from 'react';
import { LogoMark } from '@/components/app/logo';
import {
    CANVAS_RGB,
    ESTADOS_FUNDIDOS,
    ESTADOS_SHELL,
    LEITURAS_ESPERA,
    ORDEM_ZONAS,
    TAXA_PADRAO,
    TAXAS,
    TEMPO_LEITURA,
    TEMPOS,
    TEXTOS_ESTADO,
    ZONAS,
    alvosPorEstado,
    coordenadas,
    corDoEstado,
    deriva,
    geometriaZonas,
    intersecta,
    pontoNaPolilinha,
    raioOnda,
    retanguloEtiqueta,
} from './gestao-login-scene-state';
import type { AlvosCena, EstadoLogico, FaseLogin, GeometriaZona, TipoConta } from './gestao-login-scene-state';
import { gestaoLoginMonoFont } from './gestao-login-theme';

const AZUL = 'oklch(64% 0.155 250)';
const VERMELHO = 'oklch(64% 0.19 25)';
const TILE = 'oklch(47% 0.135 252)';
const LARGURA_MINIMA_PALCO = 1101;
const DURACAO_PULSO = 750;
const DURACAO_ETIQUETA = 3600;
const MARGEM_ETIQUETA = 34;

export type PulsoDigitacao = { t0: number; hit?: boolean };

type Onda = { t0: number; x: number; y: number; max: number; rgb: string; s: number; dur: number };

type Particula = { x: number; y: number; z: number; ph: number };

export type GestaoLoginSceneProps = {
    estado: EstadoLogico;
    fase: FaseLogin;
    tipoConta: TipoConta | null;
    /** Camada da foto do palco: recebe blur, brilho, escala e respiração do loop. */
    photoRef: RefObject<HTMLDivElement | null>;
    /** Elemento do formulário onde a linha de conexão encosta. */
    alvoLinhaRef: RefObject<HTMLElement | null>;
    stageRef: RefObject<HTMLElement | null>;
    /** Camadas vivas renderizadas dentro do palco (BrandStage). */
    luzRef: RefObject<HTMLDivElement | null>;
    malhaWrapRef: RefObject<HTMLDivElement | null>;
    malhaRef: RefObject<HTMLDivElement | null>;
    fxRef: RefObject<HTMLCanvasElement | null>;
    etiquetaRef: RefObject<HTMLDivElement | null>;
    /** Fila de pulsos de digitação alimentada pelo formulário (throttle 80 ms). */
    pulsosRef: RefObject<PulsoDigitacao[]>;
};

const rotuloClasses = 'grid gap-1.5 text-[10px] leading-none font-medium tracking-[0.1em] whitespace-nowrap uppercase';

export default function GestaoLoginScene({
    estado,
    fase,
    tipoConta,
    photoRef,
    alvoLinhaRef,
    stageRef,
    luzRef,
    malhaWrapRef,
    malhaRef,
    fxRef,
    etiquetaRef,
    pulsosRef,
}: GestaoLoginSceneProps) {
    const [amplo, setAmplo] = useState(() => typeof window !== 'undefined' && window.innerWidth >= LARGURA_MINIMA_PALCO);

    const scene = useRef<HTMLDivElement>(null);
    const anelA = useRef<HTMLDivElement>(null);
    const anelB = useRef<HTMLDivElement>(null);
    const anelC = useRef<HTMLDivElement>(null);
    const reguaA = useRef<HTMLDivElement>(null);
    const tracejadoC = useRef<HTMLDivElement>(null);
    const coord = useRef<HTMLSpanElement>(null);
    const radar = useRef<HTMLDivElement>(null);
    const arcoWrap = useRef<HTMLDivElement>(null);
    const arco = useRef<SVGCircleElement>(null);
    const disco = useRef<HTMLDivElement>(null);
    const linha = useRef<SVGPathElement>(null);
    const noInicial = useRef<SVGCircleElement>(null);
    const noFinal = useRef<SVGCircleElement>(null);
    const rotuloA = useRef<HTMLDivElement>(null);
    const rotuloB = useRef<HTMLDivElement>(null);
    const rotuloC = useRef<HTMLDivElement>(null);
    const caixaStatus = useRef<HTMLDivElement>(null);

    // Valores lidos pelo loop sem provocar re-render.
    const estadoRef = useRef(estado);
    const faseRef = useRef(fase);
    const tipoContaRef = useRef(tipoConta);
    estadoRef.current = estado;
    faseRef.current = fase;
    tipoContaRef.current = tipoConta;

    const centro = useRef({ x: 0, y: 0 });
    const atual = useRef<AlvosCena | null>(null);
    const rotacaoBase = useRef(0);
    const faseAnterior = useRef<FaseLogin>('idle');
    const mouse = useRef({ x: 0, y: 0 });
    const mouseSuave = useRef({ x: 0, y: 0 });
    const blurAnterior = useRef<number[]>([-1, -1, -1]);
    const fotoAnterior = useRef('');
    const [leitura, setLeitura] = useState(0);

    // Estado das camadas vivas, fora do React: o loop escreve direto no DOM.
    const estadoAnterior = useRef<EstadoLogico>('idle');
    const identificadoAntes = useRef(false);
    const giro = useRef(0);
    const anguloRadar = useRef(0);
    const deslocamentoMalha = useRef(0);
    const posicaoAnelB = useRef<[number, number]>([0, 0]);
    const momentoCoordenada = useRef(0);
    const textoCoordenada = useRef('');
    const posicaoLuz = useRef<[number, number] | null>(null);
    const varredura = useRef<number | null>(null);
    const varreduraBruta = useRef<number | null>(null);
    const ondas = useRef<Onda[]>([]);
    const particulas = useRef<Particula[] | null>(null);
    const zonasGeometria = useRef<GeometriaZona[] | null>(null);
    const flashZona = useRef<number[]>(ZONAS.map(() => 0));
    const flashPonto = useRef<number[]>(ZONAS.map(() => 0));
    const analiseZona = useRef<number[]>(ZONAS.map(() => 0));
    const progressoZona = useRef<number[]>(ZONAS.map(() => 0));
    const corZona = useRef<string[]>(ZONAS.map(() => CANVAS_RGB.azul));
    const ordemAtual = useRef(0);
    const indiceEtiqueta = useRef<number | null>(null);
    const etiquetaLigada = useRef(false);
    const temporizadorEtiqueta = useRef<ReturnType<typeof setTimeout>>(undefined);
    const pontosLinha = useRef<Array<[number, number]> | null>(null);
    const linhaVisivel = useRef(false);

    useEffect(() => {
        const onResize = () => setAmplo(window.innerWidth >= LARGURA_MINIMA_PALCO);
        window.addEventListener('resize', onResize);

        return () => window.removeEventListener('resize', onResize);
    }, []);

    // Intervalo global de leituras, iniciado no mount: o índice só avança
    // quando a central está em espera no momento do tick (não reinicia).
    useEffect(() => {
        const intervalo = setInterval(() => {
            if (estadoRef.current === 'idle') {
                setLeitura((indice) => (indice + 1) % LEITURAS_ESPERA.length);
            }
        }, TEMPO_LEITURA);

        return () => clearInterval(intervalo);
    }, []);

    // rotBase: arredonda no submit (entrada em validating) e soma uma volta
    // ao voltar de denied para idle, como no protótipo.
    useEffect(() => {
        if (fase === 'validating') {
            rotacaoBase.current = Math.round((atual.current?.sec_rot ?? 0) / 360) * 360;
        }

        if (faseAnterior.current === 'denied' && fase === 'idle') {
            rotacaoBase.current += 360;
        }

        faseAnterior.current = fase;
    }, [fase]);

    useEffect(() => () => clearTimeout(temporizadorEtiqueta.current), []);

    // No fim do docking, a linha de estado esmaece (0,6 s) e termina
    // invisível exatamente na navegação — o disco fica: ele é o logo da
    // sidebar que o console assume sem piscar.
    const [cenaSumindo, setCenaSumindo] = useState(false);

    useEffect(() => {
        if (fase !== 'docking') {
            return;
        }

        const temporizador = setTimeout(() => setCenaSumindo(true), TEMPOS.desvanecerCena);

        return () => clearTimeout(temporizador);
    }, [fase]);

    useEffect(() => {
        if (!amplo) {
            return;
        }

        const reduzido = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        const medir = () => {
            if (ESTADOS_SHELL.includes(faseRef.current)) {
                return;
            }

            // Centro da cena pela fórmula do protótipo (§3.3): não depende da
            // largura medida do palco, que muda durante a animação de docking.
            const larguraJanela = window.innerWidth;
            const larguraPainel = Math.min(580, Math.max(440, larguraJanela * 0.34));

            centro.current = {
                x: (larguraJanela - larguraPainel) * 0.7,
                y: window.innerHeight * 0.47,
            };
        };

        const onMove = (evento: MouseEvent) => {
            const palco = stageRef.current;

            if (!palco || reduzido) {
                return;
            }

            const caixa = palco.getBoundingClientRect();
            mouse.current = {
                x: (evento.clientX - caixa.left) / caixa.width - 0.5,
                y: (evento.clientY - caixa.top) / caixa.height - 0.5,
            };
        };

        const onLeave = () => {
            mouse.current = { x: 0, y: 0 };
        };

        medir();
        window.addEventListener('resize', medir);
        const palco = stageRef.current;
        palco?.addEventListener('mousemove', onMove);
        palco?.addEventListener('mouseleave', onLeave);

        arco.current?.setAttribute('pathLength', '100');
        linha.current?.setAttribute('pathLength', '1');

        let anterior = performance.now();
        let quadro = 0;

        const alturaAlvoLinha = () => {
            const alvo = alvoLinhaRef.current;

            if (!alvo) {
                return window.innerHeight / 2;
            }

            const caixa = alvo.getBoundingClientRect();

            return caixa.top + caixa.height / 2;
        };

        const dispararOnda = (max: number, rgb: string, s: number, dur: number) => {
            ondas.current.push({ t0: performance.now(), x: centro.current.x, y: centro.current.y, max, rgb, s, dur });
        };

        const ativarEtiqueta = (indice: number) => {
            indiceEtiqueta.current = indice;
            etiquetaLigada.current = true;

            const el = etiquetaRef.current;

            if (el) {
                const zona = ZONAS[indice];
                const [linha1, linha2] = Array.from(el.children) as HTMLElement[];

                if (linha1 && linha2) {
                    linha1.textContent = `${zona.id} · ${zona.nome}`;
                    linha2.textContent = zona.leitura;
                }
            }

            clearTimeout(temporizadorEtiqueta.current);
            temporizadorEtiqueta.current = setTimeout(() => {
                etiquetaLigada.current = false;
            }, DURACAO_ETIQUETA);
        };

        const etiquetaCabe = (zona: GeometriaZona, indice: number, larguraPalco: number) => {
            const retangulo = retanguloEtiqueta(zona, indice, larguraPalco);

            return [rotuloA, rotuloB, rotuloC, caixaStatus].every((ref) => {
                const el = ref.current;

                if (!el) {
                    return true;
                }

                return !intersecta(retangulo, el.getBoundingClientRect(), MARGEM_ETIQUETA);
            });
        };

        // Canvas de efeitos (§11): varredura → ondas → zonas → pontos →
        // convergência → pulsos → partículas, nesta ordem de desenho.
        const desenharCanvas = (
            agora: number,
            dt: number,
            cur: AlvosCena,
            mx: number,
            my: number,
            estadoAtual: EstadoLogico,
            larguraPalco: number,
            alturaPalco: number,
        ) => {
            const canvas = fxRef.current;
            const base = centro.current;

            if (!canvas) {
                return;
            }

            const alfa = Math.max(0, Math.min(1, cur.fx_o));
            canvas.style.opacity = alfa.toFixed(3);

            if (alfa < 0.01 || larguraPalco < 200) {
                varredura.current = null;

                return;
            }

            const dpr = Math.min(2, window.devicePixelRatio || 1);
            const larguraInterna = Math.round(larguraPalco * dpr);
            const alturaInterna = Math.round(alturaPalco * dpr);

            if (canvas.width !== larguraInterna || canvas.height !== alturaInterna) {
                canvas.width = larguraInterna;
                canvas.height = alturaInterna;
            }

            const g = canvas.getContext('2d');

            if (!g) {
                return;
            }

            g.setTransform(dpr, 0, 0, dpr, 0, 0);
            g.clearRect(0, 0, larguraPalco, alturaPalco);
            g.lineWidth = 1;

            const emEspera = estadoAtual === 'idle';
            const TAU = Math.PI * 2;
            const zs = geometriaZonas(larguraPalco, alturaPalco, -mx * 14, -my * 10);
            zonasGeometria.current = zs;

            const tracarZona = (pontos: Array<[number, number]>) => {
                g.beginPath();
                pontos.forEach(([x, y], indice) => (indice ? g.lineTo(x, y) : g.moveTo(x, y)));
                g.closePath();
            };

            // Varredura territorial: em espera cruza o palco (período 11 s);
            // ativada, oscila em torno da cena.
            const periodo = 11000;
            const bruta = emEspera && !reduzido ? ((agora % periodo) / periodo) * (larguraPalco + 160) - 80 : base.x + Math.sin(agora * 0.0009) * 40;
            const varreduraAnterior = varredura.current == null ? bruta : varredura.current;

            if (varredura.current == null || (emEspera && bruta < (varreduraBruta.current ?? bruta) - 10)) {
                varredura.current = bruta;
            } else {
                varredura.current += (bruta - varredura.current) * (1 - Math.exp(-(emEspera ? 12 : 2.5) * dt));
            }

            varreduraBruta.current = bruta;
            const posicaoVarredura = varredura.current;

            if (emEspera && !reduzido) {
                zs.forEach((zona, indice) => {
                    if (varreduraAnterior < zona.cx && posicaoVarredura >= zona.cx) {
                        flashPonto.current[indice] = 1;
                        flashZona.current[indice] = Math.max(flashZona.current[indice], 0.4);
                        corZona.current[indice] = CANVAS_RGB.azul;

                        if (indice === ORDEM_ZONAS[ordemAtual.current % ORDEM_ZONAS.length]) {
                            ordemAtual.current++;

                            if (etiquetaCabe(zona, indice, larguraPalco)) {
                                ativarEtiqueta(indice);
                            }
                        }
                    }
                });
            }

            const opacidadeVarredura = cur.sweep_o;

            if (opacidadeVarredura > 0.01) {
                const faixa = g.createLinearGradient(posicaoVarredura - 110, 0, posicaoVarredura, 0);
                faixa.addColorStop(0, 'rgba(96,152,240,0)');
                faixa.addColorStop(1, `rgba(96,152,240,${(0.07 * opacidadeVarredura).toFixed(3)})`);
                g.fillStyle = faixa;
                g.fillRect(posicaoVarredura - 110, 0, 110, alturaPalco);

                const linhaVarredura = g.createLinearGradient(0, 0, 0, alturaPalco);
                linhaVarredura.addColorStop(0, 'rgba(130,175,245,0)');
                linhaVarredura.addColorStop(0.3, `rgba(130,175,245,${(0.6 * opacidadeVarredura).toFixed(3)})`);
                linhaVarredura.addColorStop(0.7, `rgba(130,175,245,${(0.6 * opacidadeVarredura).toFixed(3)})`);
                linhaVarredura.addColorStop(1, 'rgba(130,175,245,0)');
                g.fillStyle = linhaVarredura;
                g.fillRect(posicaoVarredura, 0, 1, alturaPalco);
            }

            // Ondas: cada mudança de estado atravessa a cidade e acende as
            // zonas por onde o raio passa.
            const momento = performance.now();
            ondas.current = ondas.current.filter((onda) => momento - onda.t0 < onda.dur);
            ondas.current.forEach((onda) => {
                const t = Math.max(0, (momento - onda.t0) / onda.dur);
                const raio = raioOnda(t, onda.max);

                g.beginPath();
                g.arc(onda.x, onda.y, raio, 0, TAU);
                g.strokeStyle = `rgba(${onda.rgb},${((1 - t) * 0.34 * onda.s).toFixed(3)})`;
                g.stroke();

                zs.forEach((zona, indice) => {
                    if (Math.abs(Math.hypot(zona.cx - onda.x, zona.cy - onda.y) - raio) < 50) {
                        flashZona.current[indice] = Math.max(flashZona.current[indice], onda.s * (1 - t * 0.5));
                        flashPonto.current[indice] = Math.max(flashPonto.current[indice], onda.s);
                        corZona.current[indice] = onda.rgb;
                    }
                });
            });

            // Zonas: contorno base, flash, cidade mapeada e zona em análise.
            const decaimentoZona = Math.exp(-1.8 * dt);
            const decaimentoPonto = Math.exp(-2.4 * dt);
            const perseguicaoAnalise = 1 - Math.exp(-3 * dt);

            zs.forEach((zona, indice) => {
                flashZona.current[indice] *= decaimentoZona;
                flashPonto.current[indice] *= decaimentoPonto;

                const ativa = etiquetaLigada.current && indiceEtiqueta.current === indice && emEspera;
                analiseZona.current[indice] += ((ativa ? 1 : 0) - analiseZona.current[indice]) * perseguicaoAnalise;
                progressoZona.current[indice] = ativa
                    ? Math.min(1, progressoZona.current[indice] + dt * 1.1)
                    : analiseZona.current[indice] < 0.02
                      ? 0
                      : progressoZona.current[indice];

                tracarZona(zona.pontos);
                g.setLineDash([]);
                g.lineWidth = 1;
                g.strokeStyle = `rgba(255,255,255,${cur.zone_b.toFixed(3)})`;
                g.stroke();

                if (flashZona.current[indice] > 0.02) {
                    g.strokeStyle = `rgba(${corZona.current[indice]},${(flashZona.current[indice] * 0.5).toFixed(3)})`;
                    g.stroke();
                }

                if (cur.zmap > 0.02) {
                    g.fillStyle = `rgba(96,152,240,${(cur.zmap * 0.05).toFixed(3)})`;
                    g.fill();
                    g.strokeStyle = `rgba(120,170,245,${(cur.zmap * 0.3).toFixed(3)})`;
                    g.stroke();
                }

                const analise = analiseZona.current[indice];

                if (analise > 0.01) {
                    const progresso = progressoZona.current[indice];
                    g.fillStyle = `rgba(96,152,240,${(analise * 0.08 * progresso).toFixed(3)})`;
                    g.fill();
                    g.setLineDash([zona.perimetro * progresso, zona.perimetro]);
                    g.lineWidth = 1.25;
                    g.strokeStyle = `rgba(130,178,248,${(analise * 0.85).toFixed(3)})`;
                    g.stroke();
                    g.setLineDash([]);
                    g.lineWidth = 1;
                    g.fillStyle = `rgba(170,200,250,${analise.toFixed(3)})`;
                    zona.pontos.forEach(([x, y], vertice) => {
                        if (progresso * zona.pontos.length > vertice) {
                            g.fillRect(x - 2, y - 2, 4, 4);
                        }
                    });
                }
            });

            // Pontos de análise nos centroides.
            zs.forEach((zona, indice) => {
                const pulso = reduzido ? 0.4 : 0.5 + Math.sin(agora * 0.0011 + indice * 1.7) * 0.5;
                const flash = flashPonto.current[indice];
                const corPonto = emEspera ? '235,242,255' : '120,170,245';

                g.beginPath();
                g.arc(zona.cx, zona.cy, 2 + flash * 1.4, 0, TAU);
                g.fillStyle = `rgba(${corPonto},${Math.min(1, 0.18 + pulso * 0.22 + flash * 0.6).toFixed(3)})`;
                g.fill();

                if (flash > 0.03) {
                    g.beginPath();
                    g.arc(zona.cx, zona.cy, 4 + (1 - flash) * 22, 0, TAU);
                    g.strokeStyle = `rgba(${corPonto},${(flash * 0.5).toFixed(3)})`;
                    g.stroke();
                }
            });

            // Validação: cada consulta territorial converge para a central.
            if (cur.conv > 0.02) {
                g.setLineDash([2, 7]);
                g.lineDashOffset = -agora * 0.03;
                g.strokeStyle = `rgba(120,170,245,${(cur.conv * 0.26).toFixed(3)})`;
                zs.forEach((zona) => {
                    g.beginPath();
                    g.moveTo(zona.cx, zona.cy);
                    g.lineTo(base.x, base.y);
                    g.stroke();
                });
                g.setLineDash([]);
                g.lineDashOffset = 0;

                zs.forEach((zona, indice) => {
                    const t = (agora * 0.0007 + indice * 0.137) % 1;
                    const avanco = t * t;

                    g.beginPath();
                    g.arc(zona.cx + (base.x - zona.cx) * avanco, zona.cy + (base.y - zona.cy) * avanco, 1.6, 0, TAU);
                    g.fillStyle = `rgba(175,208,255,${(cur.conv * (1 - t) * 0.9).toFixed(3)})`;
                    g.fill();
                });
            }

            // Cada tecla vira um pulso que viaja do campo até a central.
            const trajeto = pontosLinha.current;
            const pulsos = pulsosRef.current;

            if (trajeto && linhaVisivel.current && cur.ax_o > 0.3 && pulsos) {
                const vivos = pulsos.filter((pulso) => momento - pulso.t0 < DURACAO_PULSO);
                pulsos.length = 0;
                pulsos.push(...vivos);

                let comprimentoTotal = 0;

                for (let indice = 1; indice < trajeto.length; indice++) {
                    comprimentoTotal += Math.hypot(trajeto[indice][0] - trajeto[indice - 1][0], trajeto[indice][1] - trajeto[indice - 1][1]);
                }

                vivos.forEach((pulso) => {
                    const t = Math.max(0, (momento - pulso.t0) / DURACAO_PULSO);
                    const avanco = 1 - Math.pow(1 - t, 2);
                    const [x, y] = pontoNaPolilinha(trajeto, (1 - avanco) * comprimentoTotal);

                    g.beginPath();
                    g.arc(x, y, 6, 0, TAU);
                    g.fillStyle = 'rgba(96,152,240,0.16)';
                    g.fill();
                    g.beginPath();
                    g.arc(x, y, 2.2, 0, TAU);
                    g.fillStyle = `rgba(185,212,255,${(0.95 - t * 0.3).toFixed(3)})`;
                    g.fill();

                    if (t > 0.92 && !pulso.hit) {
                        pulso.hit = true;
                        dispararOnda(80, CANVAS_RGB.azul, 0.45, 650);
                    }
                });
            } else if (pulsos) {
                pulsos.length = 0;
            }

            // Partículas: poeira de luz em três profundidades.
            if (!particulas.current) {
                particulas.current = Array.from({ length: 42 }, () => ({
                    x: Math.random() * larguraPalco,
                    y: Math.random() * alturaPalco,
                    z: 0.25 + Math.random() * 0.75,
                    ph: Math.random() * TAU,
                }));
            }

            particulas.current.forEach((particula) => {
                if (!reduzido) {
                    particula.y -= (3 + particula.z * 7) * dt * cur.wind;
                    particula.x += Math.sin(agora * 0.00021 + particula.ph) * 4 * dt * cur.wind;

                    if (cur.attract > 0.01) {
                        particula.x += (base.x - particula.x) * dt * 1.4 * cur.attract;
                        particula.y += (base.y - particula.y) * dt * 1.4 * cur.attract;
                    }
                }

                if (particula.y < -10) {
                    particula.y = alturaPalco + 10;
                    particula.x = Math.random() * larguraPalco;
                }

                const x = particula.x - mx * particula.z * 24;
                const y = particula.y - my * particula.z * 16;
                const raio = 0.5 + particula.z * 0.9;

                g.fillStyle = `rgba(200,220,255,${((0.05 + particula.z * 0.16) * (0.65 + 0.35 * Math.sin(agora * 0.0013 + particula.ph * 3))).toFixed(3)})`;
                g.fillRect(x - raio, y - raio, raio * 2, raio * 2);
            });
        };

        const frame = (agora: number) => {
            const dt = Math.min(0.05, (agora - anterior) / 1000);
            anterior = agora;

            if (!ESTADOS_SHELL.includes(faseRef.current)) {
                medir();
            }

            const base = centro.current;
            const alvos = alvosPorEstado(estadoRef.current, base, rotacaoBase.current, alturaAlvoLinha());

            if (!atual.current) {
                atual.current = { ...alvos };
            }

            const cur = atual.current;

            for (const chave in alvos) {
                const taxa = TAXAS[chave] ?? TAXA_PADRAO;

                if (cur[chave] === undefined) {
                    cur[chave] = alvos[chave];
                }

                cur[chave] += (alvos[chave] - cur[chave]) * (1 - Math.exp(-taxa * dt));
            }

            const noShell = ESTADOS_SHELL.includes(faseRef.current);

            // Paralaxe suavizada (§10.6): o alvo do mouse zera em shell e em
            // movimento reduzido, e a posição persegue com taxa 5.
            const alvoMouseX = noShell || reduzido ? 0 : mouse.current.x;
            const alvoMouseY = noShell || reduzido ? 0 : mouse.current.y;
            const perseguicaoMouse = 1 - Math.exp(-5 * dt);
            mouseSuave.current.x += (alvoMouseX - mouseSuave.current.x) * perseguicaoMouse;
            mouseSuave.current.y += (alvoMouseY - mouseSuave.current.y) * perseguicaoMouse;

            const mx = mouseSuave.current.x;
            const my = mouseSuave.current.y;

            if (scene.current) {
                scene.current.style.transform = `translate(${base.x.toFixed(1)}px, ${base.y.toFixed(1)}px)`;
            }

            [anelA, anelB, anelC].forEach((ref, indice) => {
                const el = ref.current;

                if (!el) {
                    return;
                }

                const chave = ['a', 'b', 'c'][indice];
                const { dx, dy } = reduzido ? { dx: 0, dy: 0 } : deriva(indice, agora, cur.drift);
                const x = cur[`${chave}_x`] + dx - mx * (10 + indice * 9);
                const y = cur[`${chave}_y`] + dy - my * (8 + indice * 7);

                el.style.transform = `translate3d(${x.toFixed(2)}px, ${y.toFixed(2)}px, 0) scale(${(cur[`${chave}_s`] * cur.rs).toFixed(4)})`;
                el.style.opacity = Math.max(0, cur[`${chave}_o`]).toFixed(3);

                if (indice === 1) {
                    posicaoAnelB.current = [x, y];
                }

                const blur = reduzido ? 0 : Math.max(0, cur[`${chave}_b`]);

                if (Math.abs(blur - blurAnterior.current[indice]) > 0.04) {
                    el.style.filter = blur > 0.05 ? `blur(${blur.toFixed(2)}px)` : 'none';
                    blurAnterior.current[indice] = blur;
                }
            });

            if (arcoWrap.current) {
                arcoWrap.current.style.transform = `rotate(${cur.sec_rot.toFixed(2)}deg) scale(${cur.rs.toFixed(4)})`;
                arcoWrap.current.style.opacity = Math.max(0, cur.sec_o).toFixed(3);
            }

            if (arco.current) {
                const preenchido = Math.max(0, Math.min(100, cur.sec_c));
                arco.current.setAttribute('stroke-dasharray', `${preenchido.toFixed(2)} ${(100 - preenchido + 0.01).toFixed(2)}`);
                arco.current.style.stroke = cur.sec_red > 0.5 ? VERMELHO : AZUL;
            }

            if (disco.current) {
                disco.current.style.transform = `translate3d(${cur.disc_x.toFixed(2)}px, ${cur.disc_y.toFixed(2)}px, 0) scale(${Math.max(0, cur.disc_s * cur.disc_k).toFixed(4)})`;
                disco.current.style.opacity = Math.max(0, Math.min(1, cur.disc_o)).toFixed(3);
            }

            if (photoRef.current) {
                const blur = reduzido ? 0 : Math.max(0, cur.ph_b);
                const assinatura = blur.toFixed(2) + cur.ph_br.toFixed(3);

                if (assinatura !== fotoAnterior.current) {
                    photoRef.current.style.filter = `blur(${blur.toFixed(2)}px) brightness(${cur.ph_br.toFixed(3)})`;
                    fotoAnterior.current = assinatura;
                }

                // Respiração lenta da foto (§10.5), desligada em reduced-motion.
                const respira = reduzido ? 0 : 1;
                const fotoX = mx * -8 + Math.sin(agora * 0.00007) * 6 * respira;
                const fotoY = my * -6 + Math.cos(agora * 0.00006) * 4 * respira;
                photoRef.current.style.transform = `translate(${fotoX.toFixed(1)}px, ${fotoY.toFixed(1)}px) scale(${cur.ph_s.toFixed(4)})`;
            }

            const palcoAtual = stageRef.current;

            if (palcoAtual && linha.current) {
                const direita = palcoAtual.getBoundingClientRect().right;
                const x0 = base.x + 152;
                const y0 = base.y;
                const x2 = direita - 40;
                const meio = x0 + (x2 - x0) * 0.42;
                const alcanca = x2 > x0 + 20;

                pontosLinha.current = [
                    [x0, y0],
                    [meio, y0],
                    [x2, cur.ax_y],
                    [direita, cur.ax_y],
                ];
                linhaVisivel.current = alcanca;

                linha.current.setAttribute(
                    'd',
                    `M ${x0.toFixed(1)} ${y0.toFixed(1)} L ${meio.toFixed(1)} ${y0.toFixed(1)} L ${x2.toFixed(1)} ${cur.ax_y.toFixed(1)} L ${direita.toFixed(1)} ${cur.ax_y.toFixed(1)}`,
                );

                const comprimento = Math.max(0, Math.min(1, cur.ax_len));
                linha.current.setAttribute('stroke-dasharray', `${comprimento.toFixed(4)} 1`);

                const opacidade = alcanca ? Math.max(0, cur.ax_o) : 0;
                linha.current.style.opacity = opacidade.toFixed(3);

                if (noInicial.current) {
                    noInicial.current.setAttribute('cx', x0.toFixed(1));
                    noInicial.current.setAttribute('cy', y0.toFixed(1));
                    noInicial.current.style.opacity = (opacidade * Math.min(1, comprimento * 4)).toFixed(3);
                }

                if (noFinal.current) {
                    noFinal.current.setAttribute('cx', direita.toFixed(1));
                    noFinal.current.setAttribute('cy', cur.ax_y.toFixed(1));
                    noFinal.current.style.opacity = (opacidade * Math.max(0, (comprimento - 0.9) * 10)).toFixed(3);
                }
            }

            // Camadas vivas (§10.4–§12) — ondas por transição, giros, malha,
            // canvas, luz e etiqueta.
            if (palcoAtual) {
                const larguraPalco = palcoAtual.clientWidth;
                const alturaPalco = palcoAtual.clientHeight;
                const estadoAtual = estadoRef.current;

                if (estadoAtual !== estadoAnterior.current) {
                    if (estadoAnterior.current === 'idle' && ['session', 'secure', 'ready'].includes(estadoAtual)) {
                        dispararOnda(larguraPalco * 1.15, CANVAS_RGB.branco, 0.6, 2000);
                    }

                    if (estadoAtual === 'authorized') {
                        dispararOnda(larguraPalco * 1.5, CANVAS_RGB.azul, 1, 2400);
                    }

                    if (estadoAtual === 'denied') {
                        dispararOnda(320, CANVAS_RGB.vermelho, 0.8, 1000);
                    }

                    estadoAnterior.current = estadoAtual;
                }

                const identificado = tipoContaRef.current !== null;

                if (identificado && !identificadoAntes.current && !ESTADOS_FUNDIDOS.includes(estadoAtual)) {
                    dispararOnda(340, CANVAS_RGB.azul, 0.55, 1100);
                }

                identificadoAntes.current = identificado;

                // Giros: régua do anel A, tracejado do anel C e radar.
                giro.current += dt * cur.spin * 5;
                anguloRadar.current += dt * cur.radar_v;

                if (reguaA.current) {
                    reguaA.current.style.transform = `rotate(${giro.current.toFixed(2)}deg)`;
                }

                if (tracejadoC.current) {
                    tracejadoC.current.style.transform = `rotate(${(-giro.current * 0.7).toFixed(2)}deg)`;
                }

                if (radar.current) {
                    radar.current.style.transform = `rotate(${anguloRadar.current.toFixed(1)}deg) scale(${cur.rs.toFixed(3)})`;
                    radar.current.style.opacity = Math.max(0, cur.radar_o).toFixed(3);
                }

                // Coordenadas vivas do anel B, a cada 140 ms.
                if (coord.current && agora - momentoCoordenada.current > 140) {
                    momentoCoordenada.current = agora;
                    const texto = coordenadas(posicaoAnelB.current[0], posicaoAnelB.current[1]);

                    if (texto !== textoCoordenada.current) {
                        coord.current.textContent = texto;
                        textoCoordenada.current = texto;
                    }
                }

                // Malha cadastral em perspectiva.
                if (malhaRef.current && malhaWrapRef.current) {
                    deslocamentoMalha.current = (deslocamentoMalha.current + dt * cur.grid_v * 14) % 64;
                    malhaRef.current.style.backgroundPosition = `0px ${deslocamentoMalha.current.toFixed(2)}px`;
                    malhaWrapRef.current.style.opacity = Math.max(0, cur.grid_o).toFixed(3);
                    malhaWrapRef.current.style.transform = `translateX(${(-mx * 5).toFixed(1)}px)`;
                }

                desenharCanvas(agora, dt, cur, mx, my, estadoAtual, larguraPalco, alturaPalco);

                // Luz dinâmica: em espera migra para a zona com etiqueta ativa;
                // com a sessão aberta, acompanha a cena. O mouse arrasta a luz.
                if (luzRef.current) {
                    const zonaAtiva =
                        etiquetaLigada.current && indiceEtiqueta.current != null && estadoAtual === 'idle' && zonasGeometria.current
                            ? zonasGeometria.current[indiceEtiqueta.current]
                            : null;
                    const alvoLuzX = (estadoAtual === 'idle' ? (zonaAtiva ? zonaAtiva.cx : larguraPalco * 0.66) : base.x) + mx * larguraPalco * 0.18;
                    const alvoLuzY = (estadoAtual === 'idle' ? (zonaAtiva ? zonaAtiva.cy : alturaPalco * 0.44) : base.y) + my * alturaPalco * 0.18;

                    if (!posicaoLuz.current) {
                        posicaoLuz.current = [alvoLuzX, alvoLuzY];
                    }

                    const perseguicaoLuz = 1 - Math.exp(-1.6 * dt);
                    posicaoLuz.current[0] += (alvoLuzX - posicaoLuz.current[0]) * perseguicaoLuz;
                    posicaoLuz.current[1] += (alvoLuzY - posicaoLuz.current[1]) * perseguicaoLuz;

                    luzRef.current.style.transform = `translate(${posicaoLuz.current[0].toFixed(1)}px, ${posicaoLuz.current[1].toFixed(1)}px) scale(${cur.light_k.toFixed(3)})`;
                    luzRef.current.style.opacity = Math.max(0, Math.min(1, cur.light_o)).toFixed(3);
                }

                // Etiqueta de zona: opacidade e posição seguem a zona ativa.
                if (etiquetaRef.current) {
                    etiquetaRef.current.style.opacity = etiquetaLigada.current && estadoAtual === 'idle' ? '1' : '0';

                    if (zonasGeometria.current && indiceEtiqueta.current != null) {
                        const zona = zonasGeometria.current[indiceEtiqueta.current];
                        const direita = zona.cx > larguraPalco * 0.74;

                        etiquetaRef.current.style.transform = `translate(${(zona.cx + (direita ? -12 : 12)).toFixed(1)}px, ${(zona.cy - 14).toFixed(1)}px)${direita ? ' translateX(-100%)' : ''}`;
                    }
                }
            }

            quadro = requestAnimationFrame(frame);
        };

        quadro = requestAnimationFrame(frame);

        return () => {
            cancelAnimationFrame(quadro);
            window.removeEventListener('resize', medir);
            palco?.removeEventListener('mousemove', onMove);
            palco?.removeEventListener('mouseleave', onLeave);
        };
    }, [amplo, alvoLinhaRef, etiquetaRef, fxRef, luzRef, malhaRef, malhaWrapRef, photoRef, pulsosRef, stageRef]);

    if (!amplo) {
        return null;
    }

    const fundido = ESTADOS_FUNDIDOS.includes(estado);
    const noShell = ESTADOS_SHELL.includes(fase);
    const { numero, texto } = TEXTOS_ESTADO[estado];
    const textoExibido = estado === 'idle' ? LEITURAS_ESPERA[leitura] : texto;
    const cor = corDoEstado(estado);
    const mostraOperador = tipoConta !== null && ['session', 'secure', 'ready', 'validating', 'authorized'].includes(estado);

    return (
        <div
            aria-hidden="true"
            className="pointer-events-none absolute inset-0 z-6 transition-opacity duration-600"
            style={noShell ? undefined : { clipPath: 'inset(0 clamp(440px, 34vw, 580px) 0 0)' }}
        >
            <svg className="absolute inset-0 size-full overflow-visible">
                <path ref={linha} d="M0 0" fill="none" stroke="oklch(64% 0.155 250 / 0.85)" strokeWidth="1" style={{ opacity: 0 }} />
                <circle ref={noInicial} cx="0" cy="0" r="2.5" fill={AZUL} style={{ opacity: 0 }} />
                <circle ref={noFinal} cx="0" cy="0" r="4" fill={TILE} stroke="oklch(80% 0.09 250)" strokeWidth="1" style={{ opacity: 0 }} />
            </svg>

            <div ref={scene} className="absolute top-0 left-0 size-0">
                {/* Linha de estado e linha do operador, acima da cena */}
                <div
                    ref={caixaStatus}
                    className={`absolute -top-[268px] -left-[220px] grid w-[440px] justify-items-center gap-2.5 transition-opacity duration-600 ${cenaSumindo ? 'opacity-0' : 'opacity-100'}`}
                >
                    <div
                        key={estado + textoExibido}
                        className={`${gestaoLoginMonoFont} flex items-center gap-2.5 text-[10.5px] font-medium tracking-[0.1em] whitespace-nowrap uppercase motion-safe:animate-[sile-rise_0.55s_cubic-bezier(0.2,0.7,0.2,1)_both]`}
                        style={{ color: cor }}
                    >
                        <span className="tabular-nums text-[oklch(50%_0.02_250)]">{numero} / 06</span>
                        <span className="h-px w-[18px] opacity-70" style={{ background: cor }} />
                        <span>{textoExibido}</span>
                    </div>
                    <span
                        className={`${gestaoLoginMonoFont} text-[10px] tracking-[0.08em] text-[oklch(67%_0.025_250)] uppercase transition-all duration-500`}
                        style={{ opacity: mostraOperador ? 1 : 0, transform: mostraOperador ? 'none' : 'translateY(-4px)' }}
                    >
                        {tipoConta ? `Operador identificado · ${tipoConta.rotulo}` : ''}
                    </span>
                </div>

                {/* Radar da validação (§7.2) */}
                <div
                    ref={radar}
                    className="absolute -top-[158px] -left-[158px] size-[316px] rounded-full opacity-0"
                    style={{
                        background: 'conic-gradient(from 0deg, transparent 0deg, oklch(64% 0.155 250 / 0.24) 70deg, transparent 70.5deg)',
                        WebkitMaskImage: 'radial-gradient(circle, transparent 38px, #000 39px)',
                        maskImage: 'radial-gradient(circle, transparent 38px, #000 39px)',
                    }}
                />

                {/* Anel de autorização */}
                <div ref={arcoWrap} className="absolute -top-[165px] -left-[165px] size-[330px] opacity-0">
                    <svg viewBox="0 0 330 330" className="absolute inset-0 size-full overflow-visible">
                        <circle cx="165" cy="165" r="160" fill="none" stroke="rgba(255,255,255,0.08)" strokeWidth="1" />
                        <circle
                            ref={arco}
                            cx="165"
                            cy="165"
                            r="160"
                            fill="none"
                            strokeWidth="1.5"
                            strokeLinecap="round"
                            strokeDasharray="0 100"
                            transform="rotate(-90 165 165)"
                            style={{ stroke: AZUL, transition: 'stroke 0.3s' }}
                        />
                    </svg>
                </div>

                {/* Anel A — atividade empresarial (a régua de ticks gira) */}
                <div ref={anelA} className="absolute -top-[125px] -left-[125px] size-[250px] will-change-[transform,opacity,filter]">
                    <div className="absolute inset-0 rounded-full border border-white/40" />
                    <div
                        ref={reguaA}
                        className="absolute inset-0 rounded-full bg-[repeating-conic-gradient(rgba(255,255,255,0.6)_0deg_0.6deg,transparent_0.6deg_10deg)] [mask:radial-gradient(circle,transparent_114px,#000_115px,#000_124px,transparent_125px)]"
                    />
                    <div
                        ref={rotuloA}
                        className={`${gestaoLoginMonoFont} ${rotuloClasses} absolute top-1.5 right-[calc(100%-26px)] text-right transition-opacity duration-500`}
                        style={{ opacity: fundido ? 0 : 1 }}
                    >
                        <span className="text-white/72">Atividade empresarial</span>
                        <span className="text-white/42">CNAE</span>
                    </div>
                </div>

                {/* Anel B — localização, com coordenadas vivas */}
                <div ref={anelB} className="absolute -top-[125px] -left-[125px] size-[250px] will-change-[transform,opacity,filter]">
                    <div className="absolute inset-0 rounded-full border border-white/40" />
                    <div className="absolute top-1/2 -right-[26px] -left-[26px] h-px bg-white/30" />
                    <div className="absolute -top-[26px] -bottom-[26px] left-1/2 w-px bg-white/30" />
                    <div className="absolute top-1/2 left-1/2 -mt-1.5 -ml-1.5 size-3 rounded-full border border-white/70" />
                    <div
                        ref={rotuloB}
                        className={`${gestaoLoginMonoFont} ${rotuloClasses} absolute top-7 left-[calc(100%-18px)] transition-opacity duration-500`}
                        style={{ opacity: fundido ? 0 : 1 }}
                    >
                        <span className="text-white/72">Localização</span>
                        <span ref={coord} className="text-white/42 tabular-nums">
                            12°58′14.0″S · 38°30′07.0″W
                        </span>
                    </div>
                </div>

                {/* Anel C — regras urbanísticas (o tracejado gira ao contrário) */}
                <div ref={anelC} className="absolute -top-[125px] -left-[125px] size-[250px] will-change-[transform,opacity,filter]">
                    <div className="absolute inset-0 rounded-full border border-white/40" />
                    <div ref={tracejadoC} className="absolute inset-4 rounded-full border border-dashed border-white/30" />
                    <div
                        ref={rotuloC}
                        className={`${gestaoLoginMonoFont} ${rotuloClasses} absolute top-[calc(100%+12px)] left-1/2 -translate-x-1/2 text-center transition-opacity duration-500`}
                        style={{ opacity: fundido ? 0 : 1 }}
                    >
                        <span className="text-white/72">Regras urbanísticas · LOUOS</span>
                        <span className="text-white/42">Lei nº 9.148/2016</span>
                    </div>
                </div>

                {/* Abertura: o disco que vira o logo da sidebar em (38, 50) */}
                <div
                    ref={disco}
                    className="absolute -top-[125px] -left-[125px] grid size-[250px] place-items-center rounded-full transition-[background-color,box-shadow] duration-600"
                    style={{
                        // Escala inicial via transform inline: o loop sobrescreve
                        // style.transform; a classe scale-0 do Tailwind v4 viraria a
                        // propriedade CSS `scale`, que multiplicaria tudo por zero.
                        transform: 'scale(0)',
                        background: noShell ? 'transparent' : TILE,
                        boxShadow:
                            estado === 'authorized' || estado === 'preparing'
                                ? '0 0 0 1px rgba(255,255,255,0.18), 0 0 90px oklch(64% 0.155 250 / 0.5)'
                                : '0 0 0 0 transparent',
                    }}
                >
                    {/* LogoMark só aceita className: a cor vai no wrapper, por currentColor. */}
                    <span className="flex transition-colors duration-600" style={{ color: noShell ? '#5992ed' : '#ffffff' }}>
                        <LogoMark className="size-[120px]" />
                    </span>
                </div>

                {/* Fórmula do produto */}
                <div
                    className={`${gestaoLoginMonoFont} absolute top-[190px] -left-[240px] flex w-[480px] items-center justify-center gap-2.5 text-[11px] tracking-[0.1em] text-white/78 uppercase transition-all duration-500`}
                    style={{
                        opacity: estado === 'preparing' ? 1 : 0,
                        transform: estado === 'preparing' ? 'none' : estado === 'docking' ? 'translateY(-10px)' : 'translateY(10px)',
                    }}
                >
                    <span>Atividade</span>
                    <span style={{ color: AZUL }}>+</span>
                    <span>Localização</span>
                    <span style={{ color: AZUL }}>+</span>
                    <span>LOUOS</span>
                    <span style={{ color: AZUL }}>→</span>
                    <span className="font-semibold text-white">Viabilidade</span>
                </div>
            </div>
        </div>
    );
}