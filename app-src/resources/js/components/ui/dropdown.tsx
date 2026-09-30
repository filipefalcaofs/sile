import { AnimatePresence, motion } from 'motion/react';
import { useEffect, useRef } from 'react';
import type { ReactNode } from 'react';
import { dropdownPop } from '@/lib/motion';

interface DropdownProps {
    isOpen: boolean;
    onClose: () => void;
    children: ReactNode;
    className?: string;
}

/**
 * Painel suspenso do TailAdmin. Fecha ao clicar fora, exceto em
 * elementos com a classe .dropdown-toggle (o botão que o abre).
 */
export function Dropdown({ isOpen, onClose, children, className = '' }: DropdownProps) {
    const dropdownRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const handleClickOutside = (event: MouseEvent) => {
            if (
                dropdownRef.current &&
                !dropdownRef.current.contains(event.target as Node) &&
                !(event.target as HTMLElement).closest('.dropdown-toggle')
            ) {
                onClose();
            }
        };

        document.addEventListener('mousedown', handleClickOutside);
        return () => {
            document.removeEventListener('mousedown', handleClickOutside);
        };
    }, [onClose]);

    // Sem early return quando fechado: o AnimatePresence precisa continuar
    // montado para executar a animação de saída do painel.
    return (
        <AnimatePresence>
            {isOpen && (
                <motion.div
                    ref={dropdownRef}
                    className={`absolute z-40 right-0 mt-2 origin-top-right rounded-xl border border-gray-200 bg-white shadow-theme-lg dark:border-gray-800 dark:bg-gray-dark ${className}`}
                    initial={dropdownPop.initial}
                    animate={dropdownPop.animate}
                    exit={dropdownPop.exit}
                >
                    {children}
                </motion.div>
            )}
        </AnimatePresence>
    );
}
