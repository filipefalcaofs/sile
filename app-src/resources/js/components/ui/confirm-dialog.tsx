import type { ReactNode } from 'react';
import { AlertIcon, InfoIcon } from '@/components/icons';
import Button from '@/components/ui/button';
import { Modal } from '@/components/ui/modal';

type ConfirmVariant = 'danger' | 'warning' | 'info';

interface ConfirmDialogProps {
    isOpen: boolean;
    onClose: () => void;
    onConfirm: () => void;
    title: string;
    description: ReactNode;
    confirmLabel?: string;
    cancelLabel?: string;
    variant?: ConfirmVariant;
    processing?: boolean;
}

const variantStyles: Record<ConfirmVariant, { iconWrapper: string; confirmClass: string }> = {
    danger: {
        iconWrapper: 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-500',
        confirmClass: 'bg-error-500 hover:bg-error-600',
    },
    warning: {
        iconWrapper: 'bg-warning-50 text-warning-600 dark:bg-warning-500/15 dark:text-orange-400',
        confirmClass: 'bg-warning-500 hover:bg-warning-600',
    },
    info: {
        iconWrapper: 'bg-brand-50 text-brand-500 dark:bg-brand-500/15 dark:text-brand-400',
        confirmClass: '',
    },
};

/**
 * Diálogo de confirmação para ações destrutivas ou de impacto
 * (substitui window.confirm com o visual do design system).
 */
export default function ConfirmDialog({
    isOpen,
    onClose,
    onConfirm,
    title,
    description,
    confirmLabel = 'Confirmar',
    cancelLabel = 'Cancelar',
    variant = 'danger',
    processing = false,
}: ConfirmDialogProps) {
    const styles = variantStyles[variant];

    return (
        <Modal isOpen={isOpen} onClose={onClose} className="max-w-[507px] p-6 lg:p-10" showCloseButton={false}>
            <div className="text-center">
                <span
                    className={`mx-auto flex h-15 w-15 items-center justify-center rounded-full ${styles.iconWrapper}`}
                >
                    {variant === 'info' ? (
                        <InfoIcon className="size-7 fill-current" />
                    ) : (
                        <AlertIcon className="size-7 fill-current" />
                    )}
                </span>
                <h4 className="mt-5 mb-2 text-title-sm font-semibold text-gray-800 dark:text-white/90">{title}</h4>
                <div className="text-theme-sm leading-6 text-gray-500 dark:text-gray-400">{description}</div>
                <div className="mt-7 flex w-full items-center justify-center gap-3">
                    <Button variant="outline" onClick={onClose} disabled={processing}>
                        {cancelLabel}
                    </Button>
                    <Button onClick={onConfirm} disabled={processing} className={styles.confirmClass}>
                        {processing ? 'Processando...' : confirmLabel}
                    </Button>
                </div>
            </div>
        </Modal>
    );
}
