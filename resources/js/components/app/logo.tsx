interface LogoMarkProps {
    className?: string;
}

/**
 * Símbolo da marca Simplifica (nome técnico do sistema: SILE): pin de
 * localização com edifício — viabilidade locacional de atividades
 * econômicas. Versão vetorial do conceito em docs/marca/, herda a cor
 * via currentColor (usar text-brand-500).
 */
export function LogoMark({ className = 'size-8' }: LogoMarkProps) {
    return (
        <svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" className={className} aria-hidden="true">
            {/* As torres são recortes vazados (evenodd): contrastam sobre qualquer fundo */}
            <path
                fillRule="evenodd"
                clipRule="evenodd"
                d="M24 3C14.6 3 7 10.6 7 20c0 11.3 14.8 23.6 15.4 24.1a2.5 2.5 0 0 0 3.2 0C26.2 43.6 41 31.3 41 20c0-9.4-7.6-17-17-17ZM15.5 30V16.5l6.5-2v15.5h-6.5Zm9 0V10.5l8 2.5v17h-8Z"
                fill="currentColor"
            />
        </svg>
    );
}

interface LogoProps {
    subtitle?: string;
    markClassName?: string;
    textClassName?: string;
    subtitleClassName?: string;
    markColorClassName?: string;
}

/**
 * Logo horizontal: símbolo + wordmark "SIMPLIFICA" com subtítulo opcional.
 */
export default function Logo({
    subtitle,
    markClassName = 'size-9',
    textClassName = 'text-2xl font-semibold tracking-tight text-gray-900 dark:text-white',
    subtitleClassName = 'text-theme-xs text-gray-500 dark:text-gray-400',
    markColorClassName = 'text-brand-500',
}: LogoProps) {
    return (
        <span className="flex items-center gap-2.5">
            <span className={markColorClassName}>
                <LogoMark className={markClassName} />
            </span>
            <span className="flex flex-col leading-tight">
                <span className={textClassName}>SIMPLIFICA</span>
                {subtitle && <span className={subtitleClassName}>{subtitle}</span>}
            </span>
        </span>
    );
}
