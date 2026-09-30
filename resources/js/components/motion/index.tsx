import { usePage } from '@inertiajs/react';
import { AnimatePresence, MotionConfig, motion } from 'motion/react';
import type { ReactNode } from 'react';
import { collapse, fadeInUp, pageEnter, stepSlide } from '@/lib/motion';

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
 * Entrada de página na navegação Inertia. A chave é o nome do componente
 * da página (não a URL): remonta — e anima — só quando a página muda,
 * preservando estado local em recargas parciais da mesma página.
 */
export function PageTransition({ children }: { children: ReactNode }) {
    const { component } = usePage();

    return (
        <motion.div key={component} initial={pageEnter.initial} animate={pageEnter.animate}>
            {children}
        </motion.div>
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
