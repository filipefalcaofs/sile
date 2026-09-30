import { Link, usePage } from '@inertiajs/react';
import { AnimatePresence, MotionConfig, motion } from 'motion/react';
import { createContext, useCallback, useContext, useRef } from 'react';
import type { ReactNode } from 'react';
import { collapse, fadeInUp, motionDuration, motionEase, revealDelay, stepSlide, surfaceEnter } from '@/lib/motion';

const MotionLink = motion.create(Link);

const RevealContext = createContext<(() => number) | null>(null);

/**
 * Zera a fila de entrada. A chave da página (em `PageTransition`)
 * remonta este escopo a cada troca de rota.
 */
export function RevealScope({ children }: { children: ReactNode }) {
    const counter = useRef(0);
    const next = useCallback(() => {
        const index = counter.current;
        counter.current += 1;

        return index;
    }, []);

    return <RevealContext.Provider value={next}>{children}</RevealContext.Provider>;
}

/** Reserva um atraso curto e estável para esta superfície, na ordem em que ela renderiza. */
export function useRevealDelay(): number {
    const next = useContext(RevealContext);
    const indexRef = useRef<number | null>(null);

    if (indexRef.current === null) {
        indexRef.current = next ? next() : 0;
    }

    return revealDelay(indexRef.current);
}

export function useRevealMotion() {
    const delay = useRevealDelay();

    return {
        initial: { opacity: 0, y: surfaceEnter.y },
        animate: {
            opacity: 1,
            y: 0,
            transition: { duration: surfaceEnter.duration, delay, ease: motionEase.out },
        },
    };
}

/**
 * Componentes de animação do design system, todos alimentados pelos
 * presets de `@/lib/motion`. Usar estes wrappers em vez de espalhar
 * `initial`/`animate` avulsos pelas telas.
 */

/**
 * Aplica `reducedMotion="user"`: quem ativa "reduzir movimento" no
 * sistema operacional recebe apenas fades, sem deslocamentos.
 */
export function MotionProvider({ children }: { children: ReactNode }) {
    return <MotionConfig reducedMotion="user">{children}</MotionConfig>;
}

/**
 * Troca de página. A chave é o componente Inertia (não a URL): a fila de
 * entrada recomeça só quando a tela muda. O título aparece na hora; as
 * superfícies entram em sequência, com no máximo 385 ms de atraso.
 */
export function PageTransition({ children }: { children: ReactNode }) {
    const { component } = usePage();

    return <RevealScope key={component}>{children}</RevealScope>;
}

/** Painel, cartão ou bloco de formulário. Entra na fila do `RevealScope` da página. */
export function Surface({
    children,
    className,
    component = 'div',
    id,
    role,
    'aria-labelledby': ariaLabelledBy,
}: {
    children: ReactNode;
    className?: string;
    component?: 'div' | 'section';
    id?: string;
    role?: string;
    'aria-labelledby'?: string;
}) {
    const motionProps = useRevealMotion();
    const Comp = component === 'section' ? motion.section : motion.div;

    return (
        <Comp id={id} role={role} aria-labelledby={ariaLabelledBy} className={className} {...motionProps}>
            {children}
        </Comp>
    );
}

/** Cartão que também é link. Mesma fila de `Surface`. */
export function RevealLink({
    href,
    className,
    children,
}: {
    href: string;
    className?: string;
    children: ReactNode;
}) {
    const motionProps = useRevealMotion();

    return (
        <MotionLink href={href} className={className} {...motionProps}>
            {children}
        </MotionLink>
    );
}

/**
 * Bloco que abre e fecha (erro de campo, seção, resultado).
 * `initial={false}` evita animar o que já estava na tela no primeiro paint.
 */
export function Presence({ open, children, className }: { open: boolean; children: ReactNode; className?: string }) {
    return (
        <AnimatePresence initial={false}>
            {open && (
                <motion.div
                    className={className}
                    initial={{ height: 0, opacity: 0 }}
                    animate={{
                        height: 'auto',
                        opacity: 1,
                        transition: { duration: motionDuration.base, ease: motionEase.out },
                    }}
                    exit={{ height: 0, opacity: 0, transition: { duration: motionDuration.fast, ease: motionEase.inOut } }}
                    style={{ overflow: 'hidden' }}
                >
                    {children}
                </motion.div>
            )}
        </AnimatePresence>
    );
}

/**
 * Entrada genérica de bloco (fade + subida de 8px). Para seções,
 * resultados e conteúdos que aparecem após uma ação.
 */
export function FadeIn({ children, className, delay = 0 }: { children: ReactNode; className?: string; delay?: number }) {
    return (
        <motion.div
            className={className}
            initial={fadeInUp.initial}
            animate={{
                ...fadeInUp.animate,
                transition: { ...fadeInUp.animate.transition, delay },
            }}
        >
            {children}
        </motion.div>
    );
}

/**
 * Expansão/recolhimento de seção com altura animada. O conteúdo sai do
 * DOM ao fechar (após a saída), como faria um `hidden` — mantendo a
 * semântica de acordeões e `aria-expanded` do gatilho.
 */
export function Collapse({ open, children, className }: { open: boolean; children: ReactNode; className?: string }) {
    return (
        <AnimatePresence initial={false}>
            {open && (
                <motion.div
                    className={`overflow-hidden ${className ?? ''}`}
                    initial={collapse.initial}
                    animate={collapse.animate}
                    exit={collapse.exit}
                >
                    {children}
                </motion.div>
            )}
        </AnimatePresence>
    );
}

/**
 * Troca de etapa (wizards e formulários em passos): a etapa que entra
 * desliza 16px desvanecendo. Sem animação de saída para não atrasar a
 * navegação entre passos.
 */
export function StepTransition({ stepKey, children }: { stepKey: string; children: ReactNode }) {
    return (
        <motion.div key={stepKey} initial={stepSlide.initial} animate={stepSlide.animate}>
            {children}
        </motion.div>
    );
}
