import { useEffect, useRef } from 'react';
import { createPortal } from 'react-dom';
import type { ReactNode } from 'react';
import { CloseIcon } from '@/components/icons';

interface ModalProps {
    isOpen: boolean;
    onClose: () => void;
    className?: string;
    children: ReactNode;
    showCloseButton?: boolean;
    isFullscreen?: boolean;
}

const FOCUSABLE_SELECTOR =
    'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

/**
 * Diálogo modal acessível: renderiza via portal (imune a containing
 * blocks criados por transform/filter em ancestrais), move o foco para
 * dentro ao abrir, prende Tab/Shift+Tab no conteúdo e devolve o foco ao
 * elemento de origem ao fechar (WAI-ARIA dialog pattern).
 */
export function Modal({
    isOpen,
    onClose,
    children,
    className,
    showCloseButton = true,
    isFullscreen = false,
}: ModalProps) {
    const modalRef = useRef<HTMLDivElement>(null);
    const previousFocusRef = useRef<HTMLElement | null>(null);

    useEffect(() => {
        if (!isOpen) {
            return;
        }

        previousFocusRef.current = document.activeElement as HTMLElement | null;

        const modal = modalRef.current;
        const firstFocusable = modal?.querySelector<HTMLElement>(FOCUSABLE_SELECTOR);
        (firstFocusable ?? modal)?.focus({ preventScroll: true });

        const handleKeydown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                onClose();
                return;
            }

            if (event.key !== 'Tab' || !modalRef.current) {
                return;
            }

            const focusables = Array.from(
                modalRef.current.querySelectorAll<HTMLElement>(FOCUSABLE_SELECTOR),
            );
            if (focusables.length === 0) {
                event.preventDefault();
                modalRef.current.focus({ preventScroll: true });
                return;
            }

            const first = focusables[0];
            const last = focusables[focusables.length - 1];
            const active = document.activeElement;

            if (event.shiftKey && (active === first || active === modalRef.current)) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && active === last) {
                event.preventDefault();
                first.focus();
            }
        };

        document.addEventListener('keydown', handleKeydown);

        return () => {
            document.removeEventListener('keydown', handleKeydown);
            previousFocusRef.current?.focus({ preventScroll: true });
        };
    }, [isOpen, onClose]);

    useEffect(() => {
        if (isOpen) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = 'unset';
        }

        return () => {
            document.body.style.overflow = 'unset';
        };
    }, [isOpen]);

    if (!isOpen) {
        return null;
    }

    const contentClasses = isFullscreen ? 'w-full h-full' : 'relative w-full rounded-3xl bg-white dark:bg-gray-900';

    return createPortal(
        <div className="fixed inset-0 flex items-center justify-center overflow-y-auto modal z-99999">
            {!isFullscreen && (
                <div
                    className="fixed inset-0 h-full w-full bg-gray-400/50 backdrop-blur-[32px]"
                    aria-hidden="true"
                    onClick={onClose}
                ></div>
            )}
            <div
                ref={modalRef}
                role="dialog"
                aria-modal="true"
                tabIndex={-1}
                className={`${contentClasses} ${className ?? ''} focus:outline-hidden`}
                onClick={(e) => e.stopPropagation()}
            >
                {showCloseButton && (
                    <button
                        type="button"
                        onClick={onClose}
                        aria-label="Fechar"
                        className="absolute right-3 top-3 z-999 flex h-9.5 w-9.5 items-center justify-center rounded-full bg-gray-100 text-gray-400 transition-colors hover:bg-gray-200 hover:text-gray-700 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-brand-500 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white sm:right-6 sm:top-6 sm:h-11 sm:w-11"
                    >
                        <CloseIcon />
                    </button>
                )}
                <div>{children}</div>
            </div>
        </div>,
        document.body,
    );
}
