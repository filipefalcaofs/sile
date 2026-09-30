import type { Transition, Variants } from 'motion/react';

/**
 * Camada central de animação do Viabiliza (motion/react).
 *
 * Toda animação do sistema sai daqui: durações curtas (teto de 350ms),
 * deslocamentos pequenos (até 16px) e uma única curva de saída suave.
 * Componentes consomem estes presets em vez de inventar valores próprios,
 * o que mantém a fluidez consistente entre telas. `MotionProvider`
 * (components/motion) aplica `reducedMotion="user"`, então quem ativa
 * "reduzir movimento" no sistema operacional recebe só fades.
 */

export const motionDuration = {
    fast: 0.15,
    base: 0.22,
    slow: 0.32,
} as const;

export const motionEase = {
    out: [0.16, 1, 0.3, 1],
    inOut: [0.4, 0, 0.2, 1],
} as const satisfies Record<string, [number, number, number, number]>;

export const springSoft = {
    type: 'spring',
    stiffness: 420,
    damping: 34,
} as const satisfies Transition;

/** Fade puro — feedbacks, alertas e estados vazios. */
export const fadeIn = {
    initial: { opacity: 0 },
    animate: { opacity: 1, transition: { duration: motionDuration.base, ease: motionEase.out } },
    exit: { opacity: 0, transition: { duration: motionDuration.fast, ease: motionEase.inOut } },
} as const satisfies Variants;

/** Entrada padrão de conteúdo: sobe 8px desvanecendo. */
export const fadeInUp = {
    initial: { opacity: 0, y: 8 },
    animate: { opacity: 1, y: 0, transition: { duration: motionDuration.base, ease: motionEase.out } },
    exit: { opacity: 0, y: 4, transition: { duration: motionDuration.fast, ease: motionEase.inOut } },
} as const satisfies Variants;

/** Entrada de página ao navegar (Inertia) — quase imperceptível. */
export const pageEnter = {
    initial: { opacity: 0, y: 10 },
    animate: { opacity: 1, y: 0, transition: { duration: motionDuration.base, ease: motionEase.out } },
} as const satisfies Variants;

/** Modais e diálogos: escala 96% → 100% com leve subida. */
export const modalPop = {
    initial: { opacity: 0, scale: 0.96, y: 10 },
    animate: { opacity: 1, scale: 1, y: 0, transition: { duration: motionDuration.base, ease: motionEase.out } },
    exit: { opacity: 0, scale: 0.97, y: 6, transition: { duration: motionDuration.fast, ease: motionEase.inOut } },
} as const satisfies Variants;

/** Fundo escuro de modais e da sidebar móvel. */
export const backdropFade = {
    initial: { opacity: 0 },
    animate: { opacity: 1, transition: { duration: motionDuration.base, ease: motionEase.out } },
    exit: { opacity: 0, transition: { duration: motionDuration.fast, ease: motionEase.inOut } },
} as const satisfies Variants;

/** Menus suspensos: nascem do ponto de origem (canto superior direito). */
export const dropdownPop = {
    initial: { opacity: 0, scale: 0.96, y: -4 },
    animate: { opacity: 1, scale: 1, y: 0, transition: { duration: motionDuration.fast, ease: motionEase.out } },
    exit: { opacity: 0, scale: 0.97, y: -4, transition: { duration: motionDuration.fast, ease: motionEase.inOut } },
} as const satisfies Variants;

/** Troca de etapa em wizards: desliza 16px na horizontal. */
export const stepSlide = {
    initial: { opacity: 0, x: 16 },
    animate: { opacity: 1, x: 0, transition: { duration: motionDuration.base, ease: motionEase.out } },
} as const satisfies Variants;

/** Contêiner de lista com escalonamento curto entre os filhos. */
export const staggerList = {
    initial: {},
    animate: { transition: { staggerChildren: 0.03, delayChildren: 0.02 } },
} as const satisfies Variants;

/** Item de lista escalonada — sempre filho de `staggerList`. */
export const staggerItem = {
    initial: { opacity: 0, y: 6 },
    animate: { opacity: 1, y: 0, transition: { duration: motionDuration.fast, ease: motionEase.out } },
} as const satisfies Variants;

/** Linhas de tabela: só fade — transform em <tr> desalinha bordas e divisores. */
export const tableRowFade = {
    initial: { opacity: 0 },
    animate: { opacity: 1, transition: { duration: motionDuration.fast, ease: motionEase.out } },
} as const satisfies Variants;

/** Expansão/recolhimento de seções (altura animada). */
export const collapse = {
    initial: { height: 0, opacity: 0 },
    animate: { height: 'auto', opacity: 1, transition: { duration: motionDuration.base, ease: motionEase.out } },
    exit: { height: 0, opacity: 0, transition: { duration: motionDuration.fast, ease: motionEase.inOut } },
} as const satisfies Variants;
