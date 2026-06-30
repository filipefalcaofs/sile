import { Form, Head, Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { KeyboardEvent } from 'react';
import { LogoMark } from '@/components/app/logo';
import { ArrowRightIcon, EyeCloseIcon, EyeIcon } from '@/components/icons';
import type { SharedProps } from '@/types';

/**
 * Login interno da retaguarda (Gestão SEDUR) — design "simplifica-console"
 * (2026-06-12): split em duas colunas. Palco de marca à esquerda com foto de
 * Salvador sob gradientes navy, mapa de zoneamento estilizado em hairlines
 * (referência à viabilidade locacional), headline display em Archivo 900 caps
 * e logos oficiais (PMS + SEDUR) no rodapé. Coluna de acesso à direita em
 * superfície elevada separada por hairline. Abaixo de 1100px o palco some e
 * só o formulário é exibido. Sem cadastro: contas internas são administradas
 * pela SEDUR (HU-012). Toda tentativa é registrada (RN-002).
 *
 * Tokens (brand-spec.md): bg oklch(15% .034 255) · surface oklch(19% .038 255)
 * fg oklch(96% .008 250) · muted oklch(67% .025 250) · faint oklch(50% .02 250)
 * accent oklch(64% .155 250) · accent-deep oklch(47% .135 252)
 */

const displayFont = "font-['Archivo',system-ui,sans-serif]";
const monoFont = "font-[ui-monospace,'SF_Mono',Menlo,monospace]";

const monoLabelClasses = `${monoFont} block text-[10.5px] leading-none font-medium tracking-[0.09em] text-[oklch(67%_0.025_250)] uppercase`;

const inputClasses =
    'w-full rounded-xl border border-white/8 bg-[oklch(14%_0.03_255)] p-4 text-[15px] font-medium text-[oklch(96%_0.008_250)] transition-all duration-150 placeholder:text-[oklch(50%_0.02_250)] hover:border-white/14 focus:border-[oklch(64%_0.155_250)] focus:bg-[oklch(13%_0.03_255)] focus:shadow-[0_0_0_4px_oklch(64%_0.155_250_/_0.18)] focus:ring-0 focus:outline-none aria-invalid:border-[oklch(64%_0.19_25)]';

const footStats = [
    { label: 'Auditoria', value: '100% rastreável', mono: false },
    { label: 'Protocolo', value: 'Digital e assinado', mono: false },
    { label: 'Base legal', value: 'Lei nº 9.184/2016', mono: true },
];

function ZoneMap() {
    return (
        <svg
            className="pointer-events-none absolute inset-0 z-0 size-full opacity-45"
            viewBox="0 0 1200 900"
            preserveAspectRatio="xMidYMid slice"
            aria-hidden="true"
        >
            <g fill="none" stroke="rgba(255,255,255,0.055)" strokeWidth="1">
                <path d="M-40 180 L220 140 L360 220 L520 170 L700 240 L880 190 L1080 260 L1260 210" />
                <path d="M-40 360 L180 330 L340 410 L560 350 L760 430 L960 370 L1240 440" />
                <path d="M-40 560 L240 520 L420 600 L640 540 L840 620 L1060 560 L1260 630" />
                <path d="M-40 760 L260 720 L460 790 L700 730 L920 800 L1240 750" />
                <path d="M160 -40 L200 240 L150 480 L210 720 L170 940" />
                <path d="M440 -40 L480 200 L430 440 L500 700 L450 940" />
                <path d="M740 -40 L700 220 L770 460 L710 700 L780 940" />
                <path d="M1020 -40 L1060 260 L1000 520 L1070 760 L1030 940" />
            </g>
            {/* zona destacada: único segundo uso do acento na tela */}
            <g>
                <path
                    d="M480 200 L700 240 L760 430 L560 350 L430 440 Z"
                    fill="oklch(64% 0.155 250 / 0.05)"
                    stroke="oklch(64% 0.155 250 / 0.32)"
                    strokeWidth="1.2"
                />
                <circle
                    className="motion-safe:animate-[sile-zone-pulse_3.2s_ease-in-out_infinite]"
                    cx="612"
                    cy="318"
                    r="5"
                    fill="oklch(64% 0.155 250 / 0.85)"
                />
                <circle cx="612" cy="318" r="14" fill="none" stroke="oklch(64% 0.155 250 / 0.25)" strokeWidth="1" />
                <text className="font-mono text-[11px] tracking-[0.08em] fill-white/16" x="630" y="306">
                    ZUS-04 · VIÁVEL
                </text>
            </g>
            <text className="font-mono text-[11px] tracking-[0.08em] fill-white/16" x="186" y="508">
                ZPR-12
            </text>
            <text className="font-mono text-[11px] tracking-[0.08em] fill-white/16" x="806" y="612">
                ZCM-07
            </text>
            <text className="font-mono text-[11px] tracking-[0.08em] fill-white/16" x="980" y="282">
                ZEIS-03
            </text>
        </svg>
    );
}

function BrandStage() {
    return (
        <section
            className="relative hidden flex-col overflow-hidden bg-[linear-gradient(180deg,oklch(12.5%_0.03_255_/_0.55)_0%,oklch(12.5%_0.03_255_/_0.2)_38%,oklch(12.5%_0.032_255_/_0.86)_100%),linear-gradient(100deg,oklch(12.5%_0.032_255_/_0.9)_0%,oklch(12.5%_0.03_255_/_0.42)_60%,oklch(12.5%_0.03_255_/_0.18)_100%),url('/images/salvador-hero.jpg')] bg-cover bg-center px-[clamp(28px,5vw,96px)] py-[clamp(28px,3.5vw,56px)] min-[1101px]:flex"
            aria-label="Apresentação do console"
        >
            <ZoneMap />

            <header className="relative z-1 flex items-center justify-between gap-4">
                <span className="flex items-center gap-3.5">
                    <span className="grid size-[46px] place-items-center rounded-xl border border-white/16 bg-[oklch(47%_0.135_252)]">
                        <LogoMark className="size-[23px] text-white" />
                    </span>
                    <span>
                        <span className={`${displayFont} block text-[17px] leading-[1.1] font-extrabold tracking-[0.06em]`}>
                            SIMPLIFICA
                        </span>
                        <span className={`${monoFont} block text-[10.5px] leading-normal font-medium tracking-[0.08em] text-[oklch(67%_0.025_250)] uppercase`}>
                            Gestão SEDUR · Salvador
                        </span>
                    </span>
                </span>
                <span
                    className={`${monoFont} inline-flex items-center gap-2 rounded-full border border-white/8 bg-black/18 px-4 py-2 text-[10.5px] leading-none font-medium tracking-[0.08em] text-[oklch(67%_0.025_250)] uppercase backdrop-blur-[4px]`}
                >
                    <span className="size-1.5 rounded-full bg-[oklch(70%_0.14_160)]" aria-hidden="true" />
                    Ambiente interno
                </span>
            </header>

            <div className="relative z-1 flex max-w-[720px] flex-1 flex-col justify-center py-12 motion-safe:animate-[sile-rise_0.6s_0.12s_cubic-bezier(0.2,0.7,0.2,1)_both]">
                <p
                    className={`${monoFont} mb-[30px] flex items-center gap-3 text-[11px] leading-none font-medium tracking-[0.1em] text-[oklch(67%_0.025_250)] uppercase before:h-px before:w-8 before:bg-[oklch(64%_0.155_250)] before:content-['']`}
                >
                    Gestão de licenciamento eletrônico
                </p>
                <h1
                    className={`${displayFont} text-[clamp(34px,3.2vw,54px)] leading-[1.04] font-extrabold tracking-[-0.02em] text-balance uppercase`}
                >
                    A cidade,
                    <br />
                    zona a zona
                </h1>
                <p className="mt-[22px] max-w-[52ch] text-[clamp(15px,1.05vw,17px)] leading-[1.65] font-normal text-[oklch(67%_0.025_250)]">
                    Análise de viabilidade locacional com fundamentação legal registrada em cada
                    decisão — LOUOS e PDDU citadas, protocolo assinado, trilha completa.
                </p>
            </div>

            <dl className="relative z-1 flex items-end justify-between gap-6 border-t border-white/8 pt-6 motion-safe:animate-[sile-rise_0.6s_0.22s_cubic-bezier(0.2,0.7,0.2,1)_both]">
                <div className="flex gap-[clamp(28px,3.5vw,64px)]">
                    {footStats.map((stat) => (
                        <div key={stat.label}>
                            <dt className={`${monoFont} mb-2 text-[10px] leading-none font-medium tracking-[0.1em] text-[oklch(50%_0.02_250)] uppercase`}>
                                {stat.label}
                            </dt>
                            <dd
                                className={
                                    stat.mono
                                        ? `${monoFont} text-sm leading-[1.3] font-semibold tabular-nums`
                                        : 'text-[14.5px] leading-[1.3] font-semibold'
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

export default function GestaoLogin({ status }: { status?: string }) {
    const { flash } = usePage<SharedProps>().props;
    const [showPassword, setShowPassword] = useState(false);
    const [capsLockOn, setCapsLockOn] = useState(false);

    const handlePasswordKeyUp = (event: KeyboardEvent<HTMLInputElement>) => {
        if (typeof event.getModifierState === 'function') {
            setCapsLockOn(event.getModifierState('CapsLock'));
        }
    };

    return (
        <main
            className="grid min-h-screen grid-cols-1 bg-[oklch(15%_0.034_255)] font-[-apple-system,BlinkMacSystemFont,'SF_Pro_Text','Segoe_UI',system-ui,sans-serif] text-[oklch(96%_0.008_250)] antialiased min-[1101px]:grid-cols-[minmax(0,1fr)_clamp(440px,34vw,580px)]"
        >
            <Head title="Gestão SEDUR — Acesso ao console">
                <link rel="preconnect" href="https://fonts.googleapis.com" />
                <link rel="preconnect" href="https://fonts.gstatic.com" crossOrigin="anonymous" />
                <link
                    href="https://fonts.googleapis.com/css2?family=Archivo:wght@500;600;700;800;900&display=swap"
                    rel="stylesheet"
                />
                <style>{`
                    @keyframes sile-rise { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: none; } }
                    @keyframes sile-zone-pulse { 0%, 100% { opacity: 0.25; } 50% { opacity: 0.9; } }
                `}</style>
            </Head>

            <BrandStage />

            <section
                className="flex min-h-screen flex-col bg-[oklch(19%_0.038_255)] px-[clamp(32px,4vw,72px)] py-[clamp(28px,3.5vw,56px)] min-[1101px]:min-h-0 min-[1101px]:border-l min-[1101px]:border-white/8 max-sm:px-[22px]"
                aria-labelledby="panel-title"
            >
                <div className="mx-auto flex w-full max-w-[400px] flex-1 flex-col justify-center motion-safe:animate-[sile-rise_0.6s_0.18s_cubic-bezier(0.2,0.7,0.2,1)_both]">
                    <p className={`${monoFont} mb-3.5 text-[10.5px] leading-none font-medium tracking-[0.1em] text-[oklch(64%_0.155_250)] uppercase`}>
                        Acesso restrito
                    </p>
                    <h2 id="panel-title" className={`${displayFont} text-[30px] leading-[1.1] font-extrabold tracking-[-0.02em]`}>
                        Entre no console
                    </h2>
                    <p className="mt-2.5 text-sm text-[oklch(67%_0.025_250)]">
                        Use seu e-mail institucional para acessar o ambiente de gestão.
                    </p>

                    {status && (
                        <p className="mt-6 rounded-xl border border-[oklch(70%_0.14_160_/_0.3)] bg-[oklch(70%_0.14_160_/_0.08)] px-4 py-3 text-sm text-[oklch(80%_0.12_160)]">
                            {status}
                        </p>
                    )}

                    {flash.error && (
                        <p className="mt-6 rounded-xl border border-[oklch(64%_0.19_25_/_0.35)] bg-[oklch(64%_0.19_25_/_0.1)] px-4 py-3 text-sm text-[oklch(80%_0.12_25)]">
                            {flash.error}
                        </p>
                    )}

                    <Form action="/gestao/login" method="post">
                        {({ errors, processing }) => (
                            <>
                                <div className="mt-[26px] grid gap-2.5">
                                    <label htmlFor="email" className={monoLabelClasses}>
                                        E-mail institucional
                                    </label>
                                    <input
                                        id="email"
                                        type="email"
                                        name="email"
                                        inputMode="email"
                                        autoComplete="username"
                                        autoFocus
                                        required
                                        placeholder="nome@salvador.ba.gov.br"
                                        aria-invalid={errors.email ? 'true' : undefined}
                                        className={inputClasses}
                                    />
                                    {errors.email && (
                                        <p className="text-[12.5px] text-[oklch(72%_0.16_25)]">{errors.email}</p>
                                    )}
                                </div>

                                <div className="mt-[26px] grid gap-2.5">
                                    <div className="flex items-baseline justify-between">
                                        <label htmlFor="password" className={monoLabelClasses}>
                                            Senha
                                        </label>
                                        <Link
                                            href="/portal/forgot-password"
                                            className="text-[12.5px] text-[oklch(67%_0.025_250)] underline underline-offset-[3px] hover:text-[oklch(96%_0.008_250)]"
                                        >
                                            Esqueceu?
                                        </Link>
                                    </div>
                                    <div className="relative">
                                        <input
                                            id="password"
                                            type={showPassword ? 'text' : 'password'}
                                            name="password"
                                            autoComplete="current-password"
                                            required
                                            onKeyUp={handlePasswordKeyUp}
                                            onBlur={() => setCapsLockOn(false)}
                                            aria-invalid={errors.password ? 'true' : undefined}
                                            className={`${inputClasses} pr-14`}
                                        />
                                        <button
                                            type="button"
                                            onClick={() => setShowPassword((current) => !current)}
                                            aria-label={showPassword ? 'Ocultar senha' : 'Mostrar senha'}
                                            aria-pressed={showPassword}
                                            className="absolute top-1/2 right-2 grid size-10 -translate-y-1/2 cursor-pointer place-items-center rounded-lg text-[oklch(67%_0.025_250)] transition-colors duration-150 hover:bg-white/5 hover:text-[oklch(96%_0.008_250)] focus-visible:bg-white/5 focus-visible:text-[oklch(96%_0.008_250)] focus-visible:outline-none"
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
                                    {errors.password && (
                                        <p className="text-[12.5px] text-[oklch(72%_0.16_25)]">{errors.password}</p>
                                    )}
                                </div>

                                <label className="mt-[22px] flex cursor-pointer items-center gap-2.5 text-sm text-[oklch(67%_0.025_250)] select-none">
                                    <input
                                        id="remember"
                                        name="remember"
                                        type="checkbox"
                                        defaultChecked
                                        className="size-4 cursor-pointer accent-[oklch(47%_0.135_252)]"
                                    />
                                    Manter conectado neste dispositivo
                                </label>

                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="group mt-7 inline-flex w-full cursor-pointer items-center justify-center gap-2.5 rounded-xl bg-[oklch(64%_0.155_250)] p-[17px] text-[15px] leading-none font-bold tracking-[0.01em] text-[oklch(13%_0.03_255)] transition-all duration-150 hover:brightness-108 hover:shadow-[0_8px_28px_oklch(64%_0.155_250_/_0.28)] focus-visible:shadow-[0_0_0_4px_oklch(64%_0.155_250_/_0.3)] focus-visible:outline-none active:translate-y-px active:shadow-none disabled:pointer-events-none disabled:opacity-75"
                                >
                                    {processing ? 'Verificando…' : 'Entrar no console'}
                                    {!processing && (
                                        <span
                                            className="transition-transform duration-150 group-hover:translate-x-[3px]"
                                            aria-hidden="true"
                                        >
                                            <ArrowRightIcon className="size-[18px]" />
                                        </span>
                                    )}
                                </button>
                            </>
                        )}
                    </Form>

                    <p className="mt-[30px] border-t border-white/8 pt-[22px] text-[12.5px] text-[oklch(50%_0.02_250)]">
                        Acesso exclusivo para servidores autorizados pela SEDUR. Atividades nesta
                        sessão são registradas para fins de auditoria.
                    </p>
                </div>

                <div
                    className={`${monoFont} mx-auto flex w-full max-w-[400px] flex-wrap justify-between gap-3 pt-6 text-[10px] leading-[1.7] font-medium tracking-[0.07em] text-[oklch(50%_0.02_250)] uppercase`}
                >
                    <span>SEDUR · SIMPLIFICA</span>
                    <span>salvador.ba.gov.br</span>
                </div>
            </section>
        </main>
    );
}
