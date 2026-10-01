import { Head, Link, router, useHttp, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import type { FormEvent, KeyboardEvent, RefObject } from 'react';
import { AppVersion } from '@/components/app/app-version';
import { LogoMark } from '@/components/app/logo';
import { ArrowRightIcon, CheckCircleIcon, EyeCloseIcon, EyeIcon, MoonIcon, SunIcon } from '@/components/icons';
import { ThemeProvider, useTheme } from '@/contexts/theme-context';
import type { SharedProps } from '@/types';
import { sequenciaDeEntrada } from './gestao-login-docking';
import GestaoLoginScene from './gestao-login-scene';
import type { PulsoDigitacao } from './gestao-login-scene';
import { TEMPOS, classificarIdentificador, estadoLogico } from './gestao-login-scene-state';
import type { FaseLogin, TipoConta } from './gestao-login-scene-state';
import {
    gestaoLoginBadgeClasses,
    gestaoLoginBrandForeground,
    gestaoLoginBrandStageClasses,
    gestaoLoginDisplayFont,
    gestaoLoginLabelFocoClasses,
    gestaoLoginStageGradientClasses,
    gestaoLoginStagePhotoClasses,
    gestaoLoginStageVeilClasses,
    gestaoLoginDividerClasses,
    gestaoLoginFaintClasses,
    gestaoLoginInputClasses,
    gestaoLoginLabelClasses,
    gestaoLoginLinkClasses,
    gestaoLoginMonoFont,
    gestaoLoginMutedClasses,
    gestaoLoginPanelClasses,
    gestaoLoginShellClasses,
    gestaoLoginSubmitClasses,
    gestaoLoginThemeToggleClasses,
} from './gestao-login-theme';

/**
 * Login interno da retaguarda (Gestão SEDUR) — design do console Viabiliza
 * (2026-06-12): split em duas colunas. Palco de marca à esquerda com foto de
 * Salvador sob gradientes navy, mapa de zoneamento estilizado em hairlines
 * (referência à viabilidade locacional), headline display em Archivo 900 caps
 * com mensagem institucional — decisões que constroem a cidade — e logos
 * oficiais (PMS + SEDUR) no rodapé. Coluna de acesso à direita em
 * superfície elevada separada por hairline. Abaixo de 1100px o palco some e
 * só o formulário é exibido. Sem cadastro: contas internas são administradas
 * pela SEDUR (HU-012). Toda tentativa é registrada (RN-002).
 *
 * O palco institucional permanece escuro. A coluna do formulário segue o
 * tema do console (ThemeProvider): claro em gray-50/white e escuro nos
 * tokens oklch do brand-spec (bg 15% · surface 19% · fg 96%).
 */

const displayFont = gestaoLoginDisplayFont;
const monoFont = gestaoLoginMonoFont;

const footStats = [
    { label: 'Em tempo real', value: 'Viabilidade com análise imediata', mono: false },
    { label: '100% auditável', value: 'Rastreabilidade e histórico de cada decisão', mono: false },
    { label: 'Segurança jurídica', value: 'Conformidade com a LOUOS — Lei nº 9.148/2016', mono: false },
];

function BrandStage({
    docking,
    stageRef,
    photoRef,
    luzRef,
    malhaWrapRef,
    malhaRef,
    fxRef,
    etiquetaRef,
    selo,
    emRepouso,
}: {
    docking: boolean;
    stageRef: RefObject<HTMLElement | null>;
    photoRef: RefObject<HTMLDivElement | null>;
    luzRef: RefObject<HTMLDivElement | null>;
    malhaWrapRef: RefObject<HTMLDivElement | null>;
    malhaRef: RefObject<HTMLDivElement | null>;
    fxRef: RefObject<HTMLCanvasElement | null>;
    etiquetaRef: RefObject<HTMLDivElement | null>;
    selo: TipoConta | null;
    emRepouso: boolean;
}) {
    const marca = docking ? 'true' : 'false';
    const saidaDoPalco = docking
        ? 'translate-y-[-10px] opacity-0 transition-all duration-400'
        : 'translate-y-0 opacity-100 transition-all duration-400 delay-[0.9s]';

    return (
        <section ref={stageRef} className={gestaoLoginBrandStageClasses} data-docking={marca} aria-label="Apresentação do console">
            <div
                ref={photoRef}
                className={gestaoLoginStagePhotoClasses}
                style={{ filter: 'blur(5px) brightness(0.74)', transform: 'scale(1.02)' }}
                aria-hidden="true"
            />
            <div className={gestaoLoginStageGradientClasses} aria-hidden="true" />

            {/* Luz dinâmica da central: o translate posiciona o centro do círculo (§5.3) */}
            <div
                ref={luzRef}
                aria-hidden="true"
                className="pointer-events-none absolute -top-[450px] -left-[450px] size-[900px] rounded-full opacity-0 mix-blend-screen will-change-[transform,opacity]"
                style={{ background: 'radial-gradient(circle, oklch(64% 0.155 250 / 0.2) 0%, oklch(64% 0.155 250 / 0.07) 34%, transparent 64%)' }}
            />

            {/* Malha cadastral em perspectiva (§5.4) */}
            <div
                ref={malhaWrapRef}
                aria-hidden="true"
                className="pointer-events-none absolute -right-[15%] bottom-0 -left-[15%] h-[46%] opacity-0"
                style={{
                    perspective: '620px',
                    perspectiveOrigin: '50% 0%',
                    WebkitMaskImage: 'linear-gradient(180deg, transparent 0%, #000 42%, #000 74%, transparent 100%)',
                    maskImage: 'linear-gradient(180deg, transparent 0%, #000 42%, #000 74%, transparent 100%)',
                }}
            >
                <div
                    ref={malhaRef}
                    className="absolute top-0 right-0 left-0 h-[260%] origin-[50%_0%]"
                    style={{
                        transform: 'rotateX(66deg)',
                        backgroundImage:
                            'linear-gradient(oklch(72% 0.12 250 / 0.2) 1px, transparent 1px), linear-gradient(90deg, oklch(72% 0.12 250 / 0.2) 1px, transparent 1px)',
                        backgroundSize: '64px 64px',
                    }}
                />
            </div>

            {/* Canvas de efeitos: varredura, zonas, ondas, convergência e partículas (§5.5) */}
            <canvas ref={fxRef} aria-hidden="true" className="pointer-events-none absolute top-0 left-0 size-full opacity-0" />

            {/* Etiqueta da zona em análise (§5.6) — o loop escreve texto, posição e opacidade */}
            <div
                ref={etiquetaRef}
                aria-hidden="true"
                className={`${monoFont} pointer-events-none absolute top-0 left-0 z-1 grid gap-1.5 border-l py-2 pr-3 pl-2.5 text-[10px] leading-none font-medium tracking-[0.1em] whitespace-nowrap uppercase opacity-0 transition-opacity duration-600`}
                style={{
                    borderLeftColor: 'oklch(64% 0.155 250 / 0.8)',
                    background: 'linear-gradient(90deg, oklch(12.5% 0.03 255 / 0.6), oklch(12.5% 0.03 255 / 0))',
                }}
            >
                <span className="text-white/86" />
                <span className="text-[oklch(80%_0.09_250)]" />
            </div>

            <div className={gestaoLoginStageVeilClasses} data-docking={marca} aria-hidden="true" />

            <header
                className={`relative z-1 flex shrink-0 items-center justify-between gap-4 transition-opacity duration-400 ${docking ? 'opacity-0' : 'opacity-100 delay-[0.9s]'}`}
            >
                <span className="flex items-center gap-3.5">
                    <span className="grid size-[46px] place-items-center rounded-xl border border-white/16 bg-[oklch(47%_0.135_252)]">
                        <LogoMark className="size-[23px] text-white" />
                    </span>
                    <span>
                        <span className={`${displayFont} ${gestaoLoginBrandForeground} block text-[17px] leading-[1.1] font-extrabold tracking-[0.06em]`}>
                            VIABILIZA
                        </span>
                        <span className={`${monoFont} block text-[10.5px] leading-normal font-medium tracking-[0.08em] text-[oklch(67%_0.025_250)] uppercase`}>
                            A viabilidade certa, no lugar certo
                        </span>
                    </span>
                </span>
                <span
                    className={`${monoFont} inline-flex items-center gap-2 rounded-full border bg-black/18 px-4 py-2 text-[10.5px] leading-none font-medium tracking-[0.08em] text-[oklch(67%_0.025_250)] uppercase backdrop-blur-[4px] transition-colors duration-500`}
                    style={{ borderColor: emRepouso ? 'rgba(255,255,255,0.08)' : 'oklch(64% 0.155 250 / 0.35)' }}
                >
                    <span className="size-1.5 rounded-full bg-[oklch(70%_0.14_160)]" aria-hidden="true" />
                    Ambiente interno
                    <span
                        className="inline-block overflow-hidden whitespace-nowrap text-[oklch(80%_0.09_250)] transition-all duration-700 ease-[cubic-bezier(0.2,0.7,0.2,1)]"
                        style={{ maxWidth: selo && !docking ? '240px' : '0px', opacity: selo && !docking ? 1 : 0 }}
                    >
                        {selo ? ` · ${selo.selo}` : ''}
                    </span>
                </span>
            </header>

            {/* A animação de entrada (sile-rise, fill both) fica num filho
                interno: no wrapper ela travaria a opacity e a transição de
                saída do docking nunca apareceria. */}
            <div className={`relative z-1 flex min-h-0 max-w-[720px] flex-1 flex-col justify-center overflow-hidden py-[clamp(16px,4vh,48px)] ${saidaDoPalco}`}>
                <div className="motion-safe:animate-[sile-rise_0.6s_0.12s_cubic-bezier(0.2,0.7,0.2,1)_both]">
                    <p
                        className={`${monoFont} mb-[30px] flex items-center gap-3 text-[11px] leading-none font-medium tracking-[0.1em] text-[oklch(67%_0.025_250)] uppercase before:h-px before:w-8 before:bg-[oklch(64%_0.155_250)] before:content-['']`}
                    >
                        Sistema de viabilidade locacional
                    </p>
                    <h1
                        className={`${displayFont} ${gestaoLoginBrandForeground} text-[clamp(34px,3.2vw,54px)] leading-[1.04] font-extrabold tracking-[-0.02em] text-balance uppercase`}
                    >
                        Decisões que
                        <br />
                        constroem a cidade.
                    </h1>
                    <p className="mt-[22px] max-w-[42ch] text-[clamp(15px,1.05vw,17px)] leading-[1.65] font-normal text-[oklch(67%_0.025_250)]">
                        Uma plataforma inteligente para transformar regras urbanísticas em decisões mais
                        claras, seguras e fundamentadas.
                    </p>
                </div>
            </div>

            <dl className={`relative z-1 flex shrink-0 items-end justify-between gap-6 overflow-hidden border-t border-white/8 pt-[clamp(16px,2.5vh,24px)] ${saidaDoPalco}`}>
                <div className="flex gap-[clamp(28px,3.5vw,64px)]">
                    {footStats.map((stat) => (
                        <div key={stat.label}>
                            <dt className={`${monoFont} mb-2 text-[10px] leading-none font-medium tracking-[0.1em] text-[oklch(50%_0.02_250)] uppercase`}>
                                {stat.label}
                            </dt>
                            <dd
                                className={
                                    stat.mono
                                        ? `${monoFont} ${gestaoLoginBrandForeground} text-sm leading-[1.3] font-semibold tabular-nums`
                                        : `${gestaoLoginBrandForeground} max-w-[18ch] text-[13px] leading-[1.35] font-semibold text-pretty`
                                }
                            >
                                {stat.value}
                            </dd>
                        </div>
                    ))}
                </div>
                <div className="flex items-center gap-[22px]">
                    <img
                        className="block h-11 w-auto opacity-92"
                        src="/images/logo_prefeitura.png"
                        alt="Prefeitura de Salvador"
                    />
                    <span className="h-9 w-px bg-white/18" aria-hidden="true" />
                    <img
                        className="block h-[30px] w-auto opacity-92"
                        src="/images/01JW9M9BYJ76Y0M06Z1XHJDKH8.png"
                        alt="SEDUR — Secretaria de Desenvolvimento Urbano"
                    />
                </div>
            </dl>
        </section>
    );
}

function ThemeToggleButton() {
    const { toggleTheme } = useTheme();

    return (
        <button
            type="button"
            onClick={toggleTheme}
            aria-label="Alternar tema"
            className={gestaoLoginThemeToggleClasses}
        >
            <SunIcon className="hidden dark:block" />
            <MoonIcon className="dark:hidden" />
        </button>
    );
}

function GestaoLoginContent({ status }: { status?: string }) {
    const { flash } = usePage<SharedProps>().props;
    const [showPassword, setShowPassword] = useState(false);
    const [capsLockOn, setCapsLockOn] = useState(false);
    const [fase, setFase] = useState<FaseLogin>('idle');
    const [tipoConta, setTipoConta] = useState<TipoConta | null>(null);
    const [campoComFoco, setCampoComFoco] = useState<'ident' | 'senha' | null>(null);
    const [erroLogin, setErroLogin] = useState<string | null>(null);
    const [erroIdentVazio, setErroIdentVazio] = useState(false);
    const [erroSenhaVazia, setErroSenhaVazia] = useState(false);
    const [shakeX, setShakeX] = useState(0);

    const stageRef = useRef<HTMLElement>(null);
    const photoRef = useRef<HTMLDivElement>(null);
    const luzRef = useRef<HTMLDivElement>(null);
    const malhaWrapRef = useRef<HTMLDivElement>(null);
    const malhaRef = useRef<HTMLDivElement>(null);
    const fxRef = useRef<HTMLCanvasElement>(null);
    const etiquetaRef = useRef<HTMLDivElement>(null);
    const identRef = useRef<HTMLInputElement>(null);
    const senhaRef = useRef<HTMLInputElement>(null);
    const botaoRef = useRef<HTMLButtonElement>(null);
    const debounce = useRef<ReturnType<typeof setTimeout>>(undefined);
    const cancelarSequencia = useRef<() => void>(() => {});
    const pulsosRef = useRef<PulsoDigitacao[]>([]);
    const ultimoPulso = useRef(0);

    const form = useHttp<{ identifier: string; password: string; remember: boolean }, { destino: string }>({
        identifier: '',
        password: '',
        remember: true,
    });

    const estado = estadoLogico({
        fase,
        identificador: form.data.identifier.trim(),
        temSenha: form.data.password !== '',
        comFoco: campoComFoco !== null,
        tipoConta,
    });

    const alvoLinhaRef = (
        campoComFoco === 'senha'
            ? senhaRef
            : campoComFoco === 'ident'
              ? identRef
              : estado === 'ready'
                ? botaoRef
                : form.data.password !== ''
                  ? senhaRef
                  : identRef
    ) as RefObject<HTMLElement | null>;

    useEffect(
        () => () => {
            clearTimeout(debounce.current);
            cancelarSequencia.current();
        },
        [],
    );

    const sacudir = () => {
        [10, -8, 6, -4, 2, 0].forEach((deslocamento, indice) => {
            setTimeout(() => setShakeX(deslocamento), indice * 70);
        });
    };

    // Cada tecla empurra um pulso para a cena (throttle de 80 ms, §12.2).
    const registrarPulso = () => {
        const agora = performance.now();

        if (agora - ultimoPulso.current > 80) {
            pulsosRef.current.push({ t0: agora });
            ultimoPulso.current = agora;
        }
    };

    const aoDigitarIdentificador = (valor: string) => {
        form.setData('identifier', valor);
        setErroLogin(null);
        setErroIdentVazio(false);
        registrarPulso();
        clearTimeout(debounce.current);
        debounce.current = setTimeout(
            () => setTipoConta(classificarIdentificador(valor)),
            TEMPOS.debounceIdentificador,
        );
    };

    const enviar = (evento: FormEvent) => {
        evento.preventDefault();

        if (fase !== 'idle') {
            return;
        }

        if (!form.data.identifier.trim() || !form.data.password) {
            setErroIdentVazio(!form.data.identifier.trim());
            setErroSenhaVazia(!form.data.password);
            sacudir();

            return;
        }

        clearTimeout(debounce.current);
        setErroLogin(null);
        setFase('validating');
        setTipoConta(
            classificarIdentificador(form.data.identifier) ?? { rotulo: 'Conta local', selo: 'Conta local', badge: 'Conta local' },
        );

        const inicio = performance.now();
        const reduzido = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        const comPiso = (acao: () => void) => {
            const restante = Math.max(0, TEMPOS.pisoValidacao - (performance.now() - inicio));
            setTimeout(acao, reduzido ? 0 : restante);
        };

        form.post('/gestao/login', {
            headers: { 'X-Viabiliza-Login-Async': '1' },
            onSuccess: (resposta) => {
                comPiso(() => {
                    cancelarSequencia.current = sequenciaDeEntrada({
                        destino: resposta.destino,
                        reduzido,
                        aoMudarFase: setFase,
                        navegar: (destino) => router.visit(destino, { preserveScroll: false }),
                    });
                });
            },
            onError: (erros) => {
                comPiso(() => {
                    setFase('denied');
                    form.setData('password', '');
                    sacudir();
                    setTimeout(() => {
                        setFase('idle');
                        setErroLogin(
                            (erros.identifier as string | undefined) ??
                                'Credenciais não conferem. A tentativa foi registrada para fins de auditoria.',
                        );
                    }, TEMPOS.denied);
                });
            },
        });
    };

    const rotuloBotao = useMemo(() => {
        if (fase === 'validating') {
            return 'Validando credencial…';
        }

        if (fase === 'denied') {
            return 'Credencial não confere';
        }

        if (fase === 'authorized') {
            return 'Operador autorizado';
        }

        if (fase !== 'idle') {
            return 'Abrindo a central…';
        }

        if (erroLogin) {
            return 'Tentar novamente';
        }

        return 'Entrar no console';
    }, [fase, erroLogin]);

    const handlePasswordKeyUp = (event: KeyboardEvent<HTMLInputElement>) => {
        if (typeof event.getModifierState === 'function') {
            setCapsLockOn(event.getModifierState('CapsLock'));
        }
    };

    const noDocking = fase === 'docking';

    return (
        <main className={gestaoLoginShellClasses}>
            <Head title="Gestão SEDUR — Acesso ao console">
                <link rel="preconnect" href="https://fonts.googleapis.com" />
                <link rel="preconnect" href="https://fonts.gstatic.com" crossOrigin="anonymous" />
                <link
                    href="https://fonts.googleapis.com/css2?family=Archivo:wght@500;600;700;800;900&family=Outfit:wght@400;500;600&display=swap"
                    rel="stylesheet"
                />
                <style>{`
                    @keyframes sile-rise { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: none; } }
                    @keyframes sile-sheen { from { transform: translateX(-110%); } to { transform: translateX(110%); } }
                    @keyframes sile-fill { from { transform: scaleX(0); } to { transform: scaleX(1); } }
                `}</style>
            </Head>

            <BrandStage
                docking={noDocking}
                stageRef={stageRef}
                photoRef={photoRef}
                luzRef={luzRef}
                malhaWrapRef={malhaWrapRef}
                malhaRef={malhaRef}
                fxRef={fxRef}
                etiquetaRef={etiquetaRef}
                selo={tipoConta}
                emRepouso={estado === 'idle'}
            />

            <section className={gestaoLoginPanelClasses} data-docking={noDocking ? 'true' : 'false'} aria-labelledby="panel-title">
                <div
                    className="flex min-h-0 flex-1 flex-col transition-all duration-350"
                    style={{
                        opacity: noDocking ? 0 : 1,
                        transform: noDocking ? 'translateY(-8px)' : 'none',
                        pointerEvents: noDocking ? 'none' : 'auto',
                    }}
                >
                <div className="flex shrink-0 justify-end">
                    <ThemeToggleButton />
                </div>
                <div className="mx-auto flex min-h-0 w-full max-w-[400px] flex-1 flex-col justify-center overflow-hidden motion-safe:animate-[sile-rise_0.6s_0.18s_cubic-bezier(0.2,0.7,0.2,1)_both]">
                    <p className={`${monoFont} mb-3.5 text-[10.5px] leading-none font-medium tracking-[0.1em] text-brand-500 uppercase dark:text-[oklch(64%_0.155_250)]`}>
                        Acesso restrito
                    </p>
                    <h2 id="panel-title" className={`${displayFont} text-[clamp(24px,5vw,30px)] leading-[1.1] font-extrabold tracking-[-0.02em]`}>
                        Entre no console
                    </h2>
                    <p className={`mt-2 max-[740px]:text-[13px] ${gestaoLoginMutedClasses}`}>
                        Use o usuário da rede corporativa ou o e-mail da sua conta local para acessar o ambiente de gestão.
                    </p>

                    {status && (
                        <p className="mt-6 rounded-xl border border-success-500/30 bg-success-50 px-4 py-3 text-sm text-success-600 dark:border-[oklch(70%_0.14_160_/_0.3)] dark:bg-[oklch(70%_0.14_160_/_0.08)] dark:text-[oklch(80%_0.12_160)]">
                            {status}
                        </p>
                    )}

                    {(erroLogin ?? flash.error) && (
                        <p className="mt-6 rounded-xl border border-error-500/35 bg-error-50 px-4 py-3 text-sm text-error-600 motion-safe:animate-[sile-rise_0.35s_ease_both] dark:border-[oklch(64%_0.19_25_/_0.35)] dark:bg-[oklch(64%_0.19_25_/_0.1)] dark:text-[oklch(80%_0.12_25)]">
                            {erroLogin ?? flash.error}
                        </p>
                    )}

                    <form onSubmit={enviar} noValidate style={{ transform: `translateX(${shakeX}px)`, transition: 'transform 0.07s ease' }}>
                                <div className="mt-5 grid gap-2 max-[740px]:mt-4 max-[740px]:gap-1.5">
                                    <label
                                        htmlFor="identifier"
                                        className={`${gestaoLoginLabelClasses} transition-colors duration-250 ${campoComFoco === 'ident' ? gestaoLoginLabelFocoClasses : ''}`}
                                    >
                                        E-mail institucional ou usuário AD
                                    </label>
                                    <div className="relative">
                                        {/* Selo do tipo de conta reconhecido (§6.3) */}
                                        <span
                                            aria-hidden="true"
                                            className={gestaoLoginBadgeClasses}
                                            style={{
                                                opacity: tipoConta && !noDocking ? 1 : 0,
                                                transform: `translateY(-50%) translateX(${tipoConta ? 0 : 6}px)`,
                                                transition: 'opacity 0.35s ease, transform 0.5s cubic-bezier(0.2,0.7,0.2,1)',
                                            }}
                                        >
                                            <span className="size-[5px] rounded-full bg-current" />
                                            <span>{tipoConta?.badge ?? ''}</span>
                                        </span>
                                        <input
                                            ref={identRef}
                                            id="identifier"
                                            type="text"
                                            name="identifier"
                                            autoComplete="username"
                                            autoFocus
                                            required
                                            placeholder="nome@salvador.ba.gov.br ou seuusuarioad"
                                            aria-describedby="identifier-help"
                                            aria-invalid={erroLogin || erroIdentVazio ? 'true' : undefined}
                                            className={gestaoLoginInputClasses}
                                            style={{ paddingRight: tipoConta ? '128px' : undefined }}
                                            value={form.data.identifier}
                                            onChange={(evento) => aoDigitarIdentificador(evento.target.value)}
                                            onFocus={() => setCampoComFoco('ident')}
                                            onBlur={() => {
                                                setCampoComFoco(null);
                                                clearTimeout(debounce.current);
                                                setTipoConta(classificarIdentificador(form.data.identifier));
                                            }}
                                        />
                                    </div>
                                    <p id="identifier-help" className={`text-[12.5px] max-[740px]:text-xs ${gestaoLoginMutedClasses}`}>
                                        Use o usuário da rede corporativa (ex.: seuusuarioad) ou o e-mail da conta local.
                                    </p>
                                    {erroIdentVazio && (
                                        <p className="text-[12.5px] text-error-600 dark:text-[oklch(80%_0.12_25)]">
                                            Informe o e-mail institucional ou o usuário AD.
                                        </p>
                                    )}
                                </div>

                                <div className="mt-5 grid gap-2 max-[740px]:mt-4 max-[740px]:gap-1.5">
                                    <div className="flex items-baseline justify-between">
                                        <label
                                            htmlFor="password"
                                            className={`${gestaoLoginLabelClasses} transition-colors duration-250 ${campoComFoco === 'senha' ? gestaoLoginLabelFocoClasses : ''}`}
                                        >
                                            Senha da rede corporativa ou da conta local
                                        </label>
                                        <Link href="/gestao/forgot-password" className={gestaoLoginLinkClasses}>
                                            Esqueceu?
                                        </Link>
                                    </div>
                                    <div className="relative">
                                        <input
                                            ref={senhaRef}
                                            id="password"
                                            type={showPassword ? 'text' : 'password'}
                                            name="password"
                                            autoComplete="current-password"
                                            required
                                            onKeyUp={handlePasswordKeyUp}
                                            aria-invalid={erroLogin || erroSenhaVazia ? 'true' : undefined}
                                            className={`${gestaoLoginInputClasses} pr-14`}
                                            value={form.data.password}
                                            onChange={(evento) => {
                                                form.setData('password', evento.target.value);
                                                setErroLogin(null);
                                                setErroSenhaVazia(false);
                                                registrarPulso();
                                            }}
                                            onFocus={() => setCampoComFoco('senha')}
                                            onBlur={() => {
                                                setCampoComFoco(null);
                                                setCapsLockOn(false);
                                            }}
                                        />
                                        <button
                                            type="button"
                                            onClick={() => setShowPassword((current) => !current)}
                                            aria-label={showPassword ? 'Ocultar senha' : 'Mostrar senha'}
                                            aria-pressed={showPassword}
                                            className="absolute top-1/2 right-2 grid size-10 -translate-y-1/2 cursor-pointer place-items-center rounded-lg text-gray-500 transition-colors duration-150 hover:bg-gray-100 hover:text-gray-800 focus-visible:bg-gray-100 focus-visible:text-gray-800 focus-visible:outline-none dark:text-[oklch(67%_0.025_250)] dark:hover:bg-white/5 dark:hover:text-[oklch(96%_0.008_250)] dark:focus-visible:bg-white/5 dark:focus-visible:text-[oklch(96%_0.008_250)]"
                                        >
                                            {showPassword ? (
                                                <EyeIcon className="size-[18px]" />
                                            ) : (
                                                <EyeCloseIcon className="size-[18px]" />
                                            )}
                                        </button>
                                    </div>
                                    {capsLockOn && (
                                        <p className="text-xs text-[oklch(78%_0.14_85)]">Caps Lock está ativado.</p>
                                    )}
                                    {erroSenhaVazia && (
                                        <p className="text-[12.5px] text-error-600 dark:text-[oklch(80%_0.12_25)]">Informe a senha.</p>
                                    )}
                                </div>

                                <label className={`mt-4 flex cursor-pointer items-center gap-2.5 max-[740px]:mt-3 max-[740px]:text-[13px] ${gestaoLoginMutedClasses} select-none`}>
                                    <input
                                        id="remember"
                                        name="remember"
                                        type="checkbox"
                                        checked={form.data.remember}
                                        onChange={(evento) => form.setData('remember', evento.target.checked)}
                                        className="size-4 cursor-pointer accent-brand-600 dark:accent-[oklch(47%_0.135_252)]"
                                    />
                                    Manter conectado neste dispositivo
                                </label>

                                <button
                                    ref={botaoRef}
                                    type="submit"
                                    disabled={fase !== 'idle'}
                                    className={gestaoLoginSubmitClasses}
                                >
                                    {/* Brilho único quando tudo está pronto (§14.3) */}
                                    {estado === 'ready' && (
                                        <span
                                            key="brilho"
                                            aria-hidden="true"
                                            className="pointer-events-none absolute inset-0 motion-safe:animate-[sile-sheen_1.2s_0.15s_cubic-bezier(0.2,0.7,0.2,1)_both]"
                                            style={{ background: 'linear-gradient(100deg, transparent 30%, rgba(255,255,255,0.26) 50%, transparent 70%)' }}
                                        />
                                    )}
                                    {/* Barra de progresso do piso de validação (§14.3) */}
                                    {fase === 'validating' && (
                                        <span
                                            key="barra"
                                            aria-hidden="true"
                                            className="absolute right-0 bottom-0 left-0 h-0.5 origin-[0_50%] bg-white/70 motion-safe:animate-[sile-fill_1.45s_cubic-bezier(0.4,0,0.2,1)_both]"
                                        />
                                    )}
                                    {rotuloBotao}
                                    {fase === 'idle' && (
                                        <span className="transition-transform duration-150 group-hover:translate-x-[3px]" aria-hidden="true">
                                            <ArrowRightIcon className="size-[18px]" />
                                        </span>
                                    )}
                                    {(fase === 'authorized' || fase === 'preparing' || fase === 'docking') && (
                                        <CheckCircleIcon className="size-[18px]" aria-hidden="true" />
                                    )}
                                </button>
                    </form>

                    <p className={`mt-5 border-t ${gestaoLoginDividerClasses} pt-4 text-[12.5px] max-[740px]:mt-4 max-[740px]:pt-3 max-[740px]:text-xs ${gestaoLoginFaintClasses}`}>
                        Acesso exclusivo para servidores autorizados pela SEDUR. Atividades nesta
                        sessão são registradas para fins de auditoria.
                    </p>
                </div>

                <div
                    className={`${monoFont} mx-auto flex w-full max-w-[400px] shrink-0 flex-wrap justify-between gap-3 pt-4 text-[10px] leading-[1.7] font-medium tracking-[0.07em] uppercase max-[740px]:pt-3 ${gestaoLoginFaintClasses}`}
                >
                    <span>SEDUR · VIABILIZA</span>
                    <AppVersion className="normal-case tracking-[0.04em]" />
                </div>
                </div>
            </section>

            {/* Cena em coordenadas de viewport, irmã das colunas: o disco
                precisa viajar até a sidebar sem ser cortado pelo overflow do
                palco (mesma estrutura do protótipo, z-index 6 sobre o main). */}
            <GestaoLoginScene
                estado={estado}
                fase={fase}
                tipoConta={tipoConta}
                photoRef={photoRef}
                alvoLinhaRef={alvoLinhaRef}
                stageRef={stageRef}
                luzRef={luzRef}
                malhaWrapRef={malhaWrapRef}
                malhaRef={malhaRef}
                fxRef={fxRef}
                etiquetaRef={etiquetaRef}
                pulsosRef={pulsosRef}
            />
        </main>
    );
}

export default function GestaoLogin({ status }: { status?: string }) {
    return (
        <ThemeProvider>
            <GestaoLoginContent status={status} />
        </ThemeProvider>
    );
}
