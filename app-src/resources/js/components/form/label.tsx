import type { ReactNode } from 'react';

interface LabelProps {
    htmlFor?: string;
    children: ReactNode;
    className?: string;
    required?: boolean;
}

export default function Label({ htmlFor, children, className = '', required = false }: LabelProps) {
    return (
        <label
            htmlFor={htmlFor}
            className={`mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400 ${className}`.trim()}
        >
            {children}
            {required && (
                <span className="ml-0.5 text-error-500" aria-hidden="true">
                    *
                </span>
            )}
        </label>
    );
}
