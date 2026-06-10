import type { ReactNode } from 'react';

interface LabelProps {
    htmlFor?: string;
    children: ReactNode;
    className?: string;
}

export default function Label({ htmlFor, children, className = '' }: LabelProps) {
    return (
        <label
            htmlFor={htmlFor}
            className={`mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400 ${className}`.trim()}
        >
            {children}
        </label>
    );
}
