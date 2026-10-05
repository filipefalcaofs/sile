import type { ReactNode } from 'react';

interface FieldErrorProps {
    id?: string;
    message?: ReactNode;
    children?: ReactNode;
}

/** Erro de campo anunciado ao leitor de tela, no mesmo visual do hint do Input. */
export default function FieldError({ id, message, children }: FieldErrorProps) {
    const conteudo = children ?? message;

    if (conteudo === null || conteudo === undefined || conteudo === false || conteudo === '') {
        return null;
    }

    return (
        <p id={id} role="alert" className="mt-1.5 text-theme-xs text-error-500">
            {conteudo}
        </p>
    );
}
