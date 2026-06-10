import { useEffect, useRef } from 'react';
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

export function Modal({
    isOpen,
    onClose,
    children,
    className,
    showCloseButton = true,
    isFullscreen = false,
}: ModalProps) {
    const modalRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const handleEscape = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                onClose();
            }
        };

        if (isOpen) {
            document.addEventListener('keydown', handleEscape);
        }

        return () => {
            document.removeEventListener('keydown', handleEscape);
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

    return (
        <div className="fixed inset-0 flex items-center justify-center overflow-y-auto modal z-99999">
            {!isFullscreen && (
                <div className="fixed inset-0 h-full w-full bg-gray-400/50 backdrop-blur-[32px]" onClick={onClose}></div>
            )}
            <div ref={modalRef} className={`${contentClasses} ${className ?? ''}`} onClick={(e) => e.stopPropagation()}>
                {showCloseButton && (
                    <button
                        type="button"
                        onClick={onClose}
                        aria-label="Fechar"
                        className="absolute right-3 top-3 z-999 flex h-9.5 w-9.5 items-center justify-center rounded-full bg-gray-100 text-gray-400 transition-colors hover:bg-gray-200 hover:text-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white sm:right-6 sm:top-6 sm:h-11 sm:w-11"
                    >
                        <CloseIcon />
                    </button>
                )}
                <div>{children}</div>
            </div>
        </div>
    );
}
