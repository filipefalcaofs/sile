import { useEffect, useRef, useState } from 'react';
import type { RefObject } from 'react';
import { LogoMark } from '@/components/app/logo';
import {
    ESTADOS_FUNDIDOS,
    ESTADOS_SHELL,
    LEITURAS_ESPERA,
    PONTOS_ANALISE,
    TAXA_PADRAO,
    TAXAS,
    TEMPO_LEITURA,
    TEXTOS_ESTADO,
    alvosPorEstado,
    corDoEstado,
    deriva,
} from './gestao-login-scene-state';
import type { AlvosCena, EstadoLogico, FaseLogin, TipoConta } from './gestao-login-scene-state';
import { gestaoLoginMonoFont } from './gestao-login-theme';

const AZUL = 'oklch(64% 0.155 250)';
const VERMELHO = 'oklch(64% 0.19 25)';
const TILE = 'oklch(47% 0.135 252)';
const LARGURA_MINIMA_PALCO = 1101;

export type GestaoLoginSceneProps = {
    estado: EstadoLogico;
    fase: FaseLogin;
    tipoConta: TipoConta | null;
    /** Camada da foto do palco: recebe blur, brilho e escala do loop. */
    photoRef: RefObject<HTMLDivElement | null>;
    /** Elemento do formulário onde a linha de conexão encosta. */
    alvoLinhaRef: RefObject<HTMLElement | null>;
    stageRef: RefObject<HTMLElement | null>;
};

const rotuloClasses = 'grid gap-1.5 text-[10px] leading-none font-medium tracking-[0.1em] whitespace-nowrap uppercase';

export default function GestaoLoginScene({ estado, fase, tipoConta, photoRef, alvoLinhaRef, stageRef }: GestaoLoginSceneProps) {
    const [amplo, setAmplo] = useState(() => typeof window !== 'undefined' && window.innerWidth >= LARGURA_MINIMA_PALCO);

    const scene = useRef<HTMLDivElement>(null);
    const anelA = useRef<HTMLDivElement>(null);
    const anelB = useRef<HTMLDivElement>(null);
    const anelC = useRef<HTMLDivElement>(null);
    const arcoWrap = useRef<HTMLDivElement>(null);
    const arco = useRef<SVGCircleElement>(null);
    const disco = useRef<HTMLDivElement>(null);
    const linha = useRef<SVGPathElement>(null);
    const noInicial = useRef<SVGCircleElement>(null);
    const noFinal = useRef<SVGCircleElement>(null);
    const varredura = useRef<HTMLDivElement>(null);
    const pontos = useRef<Array<HTMLDivElement | null>>([]);
    const focoLuz = useRef<HTMLDivElement>(null);

    // Valores lidos pelo loop sem provocar re-render.
    const estadoRef = useRef(estado);
    const faseRef = useRef(fase);
    estadoRef.current = estado;
    faseRef.current = fase;

    const centro = useRef({ x: 0, y: 0 });
    const atual = useRef<AlvosCena | null>(null);
    const rotacaoBase = useRef(0);
    const mouse = useRef({ x: 0, y: 0 });
    const mouseSuave = useRef({ x: 0, y: 0 });
    const blurAnterior = useRef<number[]>([-1, -1, -1]);
    const fotoAnterior = useRef('');
    const [leitura, setLeitura] = useState(0);

    useEffect(() => {
        const onResize = () => setAmplo(window.innerWidth >= LARGURA_MINIMA_PALCO);
        window.addEventListener('resize', onResize);

        return () => window.removeEventListener('resize', onResize);
    }, []);

    // Em espera, a linha de estado alterna entre as leituras da central.
    useEffect(() => {
        if (estado !== 'idle') {
            return;
        }

        const intervalo = setInterval(() => setLeitura((atual) => (atual + 1) % LEITURAS_ESPERA.length), TEMPO_LEITURA);

        return () => clearInterval(intervalo);
    }, [estado]);

    // Cada entrada em validating ou denied soma uma volta ao arco. A base
    // acumulada evita que a próxima volta recue o ângulo já percorrido.
    useEffect(() => {
        if (fase === 'validating' || fase === 'denied') {
            rotacaoBase.current = Math.round((atual.current?.sec_rot ?? 0) / 360) * 360;
        }
    }, [fase]);

    useEffect(() => {
        if (!amplo) {
            return;
        }

        const reduzido = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        const medir = () => {
            const palco = stageRef.current;

            if (!palco || ESTADOS_SHELL.includes(faseRef.current)) {
                return;
            }

            centro.current = {
                x: palco.getBoundingClientRect().width * 0.7,
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
                cur[chave] += (alvos[chave] - cur[chave]) * (1 - Math.exp(-taxa * dt));
            }

            const noShell = ESTADOS_SHELL.includes(faseRef.current);

            // O mouse entra suavizado: sem isso, entrar ou sair do palco salta
            // a paralaxe de 0 para o valor atual e os anéis dão um tranco.
            mouseSuave.current.x += (mouse.current.x - mouseSuave.current.x) * (1 - Math.exp(-6 * dt));
            mouseSuave.current.y += (mouse.current.y - mouseSuave.current.y) * (1 - Math.exp(-6 * dt));

            const mx = noShell || reduzido ? 0 : mouseSuave.current.x;
            const my = noShell || reduzido ? 0 : mouseSuave.current.y;

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
                // O disco está fora do palco, em coordenadas de viewport. O
                // alvo `disc_x`/`disc_y` é relativo ao centro da cena; aqui
                // vira posição absoluta na tela.
                const discoX = base.x + cur.disc_x - 125;
                const discoY = base.y + cur.disc_y - 125;

                disco.current.style.transform = `translate3d(${discoX.toFixed(2)}px, ${discoY.toFixed(2)}px, 0) scale(${Math.max(0, cur.disc_s * cur.disc_k).toFixed(4)})`;
                disco.current.style.opacity = Math.max(0, Math.min(1, cur.disc_o)).toFixed(3);
            }

            if (photoRef.current) {
                const blur = reduzido ? 0 : Math.max(0, cur.ph_b);
                const assinatura = blur.toFixed(2) + cur.ph_br.toFixed(3);

                if (assinatura !== fotoAnterior.current) {
                    photoRef.current.style.filter = `blur(${blur.toFixed(2)}px) brightness(${cur.ph_br.toFixed(3)})`;
                    fotoAnterior.current = assinatura;
                }

                photoRef.current.style.transform = `translate(${(mx * -8).toFixed(1)}px, ${(my * -6).toFixed(1)}px) scale(${cur.ph_s.toFixed(4)})`;
            }

            const palcoAtual = stageRef.current;

            // Varredura: em espera percorre o palco; com a sessão aberta,
            // converge para a região da cena. Sem movimento reduzido.
            if (varredura.current && palcoAtual) {
                const largura = palcoAtual.getBoundingClientRect().width;
                const emEspera = estadoRef.current === 'idle';
                const progresso = emEspera ? (agora % 9000) / 9000 : 0.5 + Math.sin(agora * 0.0009) * 0.08;
                const alvoX = emEspera ? progresso * largura : base.x + Math.sin(agora * 0.0009) * 40;

                varredura.current.style.transform = `translateX(${alvoX.toFixed(1)}px)`;
                varredura.current.style.opacity = reduzido || noShell ? '0' : emEspera ? '0.5' : '0.35';
            }

            // Pontos de análise: acendem em sequência sobre a cidade; com a
            // sessão aberta mudam para o azul de autorização.
            if (palcoAtual) {
                const caixaPalco = palcoAtual.getBoundingClientRect();
                const sessaoAberta = estadoRef.current !== 'idle';

                PONTOS_ANALISE.forEach(([fx, fy], indice) => {
                    const el = pontos.current[indice];

                    if (!el) {
                        return;
                    }

                    const pulso = reduzido ? 0.4 : 0.5 + Math.sin(agora * 0.0011 + indice * 1.7) * 0.5;
                    const opacidade = noShell ? 0 : sessaoAberta ? 0.25 + pulso * 0.5 : 0.12 + pulso * 0.3;

                    el.style.transform = `translate(${(fx * caixaPalco.width).toFixed(1)}px, ${(fy * caixaPalco.height).toFixed(1)}px)`;
                    el.style.opacity = opacidade.toFixed(3);
                    el.style.background = sessaoAberta ? AZUL : 'rgba(255,255,255,0.7)';
                });
            }

            // Foco de luz na cidade: cresce na autorização e some no docking.
            if (focoLuz.current) {
                const autorizando = estadoRef.current === 'authorized' || estadoRef.current === 'preparing';
                const alvo = noShell || reduzido ? 0 : autorizando ? 0.55 : estadoRef.current === 'validating' ? 0.3 : 0;
                const atualLuz = Number(focoLuz.current.dataset.o ?? '0');
                const proximo = atualLuz + (alvo - atualLuz) * (1 - Math.exp(-3 * dt));

                focoLuz.current.dataset.o = proximo.toFixed(4);
                focoLuz.current.style.opacity = proximo.toFixed(3);
            }

            if (palcoAtual && linha.current) {
                const direita = palcoAtual.getBoundingClientRect().right;
                const x0 = base.x + 152;
                const y0 = base.y;
                const x2 = direita - 40;
                const meio = x0 + (x2 - x0) * 0.42;
                const alcanca = x2 > x0 + 20;

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

            quadro = requestAnimationFrame(frame);
        };

        quadro = requestAnimationFrame(frame);

        return () => {
            cancelAnimationFrame(quadro);
            window.removeEventListener('resize', medir);
            palco?.removeEventListener('mousemove', onMove);
            palco?.removeEventListener('mouseleave', onLeave);
        };
    }, [amplo, alvoLinhaRef, photoRef, stageRef]);

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
        <div aria-hidden="true" className="pointer-events-none absolute inset-0 z-6">
            {/* Abertura: o disco que vira o logo da sidebar. Fica fora do
                palco para viajar até (38, 50) enquanto o palco contrai. */}
            <div
                ref={disco}
                className="absolute grid size-[250px] scale-0 place-items-center rounded-full transition-[background-color,box-shadow] duration-600"
                style={{
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

            {/* Varredura territorial e pontos de análise, sobre a cidade */}
            <div
                ref={varredura}
                className="absolute top-0 bottom-0 w-px opacity-0"
                style={{
                    background: 'linear-gradient(180deg, transparent, oklch(64% 0.155 250 / 0.5) 30%, oklch(64% 0.155 250 / 0.5) 70%, transparent)',
                    boxShadow: '0 0 24px 4px oklch(64% 0.155 250 / 0.18)',
                }}
            />
            {PONTOS_ANALISE.map((_, indice) => (
                <div
                    key={indice}
                    ref={(el) => {
                        pontos.current[indice] = el;
                    }}
                    className="absolute size-[5px] rounded-full opacity-0"
                    style={{ background: 'rgba(255,255,255,0.7)', boxShadow: '0 0 8px 2px rgba(255,255,255,0.25)' }}
                />
            ))}

            <svg className="absolute inset-0 size-full overflow-visible">
                <path ref={linha} d="M0 0" fill="none" stroke="oklch(64% 0.155 250 / 0.85)" strokeWidth="1" style={{ opacity: 0 }} />
                <circle ref={noInicial} cx="0" cy="0" r="2.5" fill={AZUL} style={{ opacity: 0 }} />
                <circle ref={noFinal} cx="0" cy="0" r="4" fill={TILE} stroke="oklch(80% 0.09 250)" strokeWidth="1" style={{ opacity: 0 }} />
            </svg>

            <div ref={scene} className="absolute top-0 left-0 size-0">
                {/* Foco de luz na cidade, centrado no ponto da cena */}
                <div
                    ref={focoLuz}
                    className="absolute -top-[400px] -left-[400px] size-[800px] rounded-full opacity-0"
                    style={{ background: 'radial-gradient(circle, oklch(64% 0.155 250 / 0.35) 0%, transparent 60%)' }}
                />

                {/* Linha de estado e linha do operador, acima da cena */}
                <div className="absolute -top-[268px] -left-[220px] grid w-[440px] justify-items-center gap-2.5">
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

                {/* Anel A — atividade empresarial */}
                <div ref={anelA} className="absolute -top-[125px] -left-[125px] size-[250px] will-change-[transform,opacity,filter]">
                    <div className="absolute inset-0 rounded-full border border-white/40" />
                    <div className="absolute inset-0 rounded-full bg-[repeating-conic-gradient(rgba(255,255,255,0.6)_0deg_0.6deg,transparent_0.6deg_10deg)] [mask:radial-gradient(circle,transparent_114px,#000_115px,#000_124px,transparent_125px)]" />
                    <div
                        className={`${gestaoLoginMonoFont} ${rotuloClasses} absolute top-1.5 right-[calc(100%-26px)] text-right transition-opacity duration-500`}
                        style={{ opacity: fundido ? 0 : 1 }}
                    >
                        <span className="text-white/72">Atividade empresarial</span>
                        <span className="text-white/42">CNAE</span>
                    </div>
                </div>

                {/* Anel B — localização */}
                <div ref={anelB} className="absolute -top-[125px] -left-[125px] size-[250px] will-change-[transform,opacity,filter]">
                    <div className="absolute inset-0 rounded-full border border-white/40" />
                    <div className="absolute top-1/2 -right-[26px] -left-[26px] h-px bg-white/30" />
                    <div className="absolute -top-[26px] -bottom-[26px] left-1/2 w-px bg-white/30" />
                    <div className="absolute top-1/2 left-1/2 -mt-1.5 -ml-1.5 size-3 rounded-full border border-white/70" />
                    <div
                        className={`${gestaoLoginMonoFont} ${rotuloClasses} absolute top-7 left-[calc(100%-18px)] transition-opacity duration-500`}
                        style={{ opacity: fundido ? 0 : 1 }}
                    >
                        <span className="text-white/72">Localização</span>
                        <span className="text-white/42">12°58′S · 38°30′W</span>
                    </div>
                </div>

                {/* Anel C — regras urbanísticas */}
                <div ref={anelC} className="absolute -top-[125px] -left-[125px] size-[250px] will-change-[transform,opacity,filter]">
                    <div className="absolute inset-0 rounded-full border border-white/40" />
                    <div className="absolute inset-4 rounded-full border border-dashed border-white/30" />
                    <div
                        className={`${gestaoLoginMonoFont} ${rotuloClasses} absolute top-[calc(100%+12px)] left-1/2 -translate-x-1/2 text-center transition-opacity duration-500`}
                        style={{ opacity: fundido ? 0 : 1 }}
                    >
                        <span className="text-white/72">Regras urbanísticas · LOUOS</span>
                        <span className="text-white/42">Lei nº 9.148/2016</span>
                    </div>
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
