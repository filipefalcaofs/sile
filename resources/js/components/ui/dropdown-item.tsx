import { Link } from '@inertiajs/react';
import type { MouseEvent, ReactNode } from 'react';

interface DropdownItemProps {
    tag?: 'a' | 'button';
    href?: string;
    onClick?: () => void;
    onItemClick?: () => void;
    baseClassName?: string;
    className?: string;
    children: ReactNode;
}

/**
 * Item de Dropdown do TailAdmin adaptado: navegação interna usa o
 * <Link> do Inertia (prop href) em vez do react-router.
 */
export function DropdownItem({
    tag = 'button',
    href,
    onClick,
    onItemClick,
    baseClassName = 'block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900',
    className = '',
    children,
}: DropdownItemProps) {
    const combinedClasses = `${baseClassName} ${className}`.trim();

    const handleClick = (event: MouseEvent) => {
        if (tag === 'button') {
            event.preventDefault();
        }
        if (onClick) {
            onClick();
        }
        if (onItemClick) {
            onItemClick();
        }
    };

    if (tag === 'a' && href) {
        return (
            <Link href={href} className={combinedClasses} onClick={handleClick}>
                {children}
            </Link>
        );
    }

    return (
        <button type="button" onClick={handleClick} className={combinedClasses}>
            {children}
        </button>
    );
}
