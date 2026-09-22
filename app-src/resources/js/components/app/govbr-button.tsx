/**
 * Acesso via Login Único GOV.BR (HU-151), com a identidade do guia oficial
 * (azul institucional, marca "gov.br" em peso forte). Navegação de página
 * inteira (não XHR): o destino é o redirect OAuth para o provedor.
 *
 * As cores vêm dos tokens brand-* (escala alinhada ao azul gov.br em
 * resources/css/app.css) — sem hex hardcoded no componente.
 */
export default function GovBrButton() {
    return (
        <div className="space-y-5">
            <div className="flex items-center gap-3" aria-hidden="true">
                <span className="h-px flex-1 bg-gray-200 dark:bg-gray-800" />
                <span className="text-xs font-medium tracking-wide text-gray-400 uppercase dark:text-gray-500">
                    ou
                </span>
                <span className="h-px flex-1 bg-gray-200 dark:bg-gray-800" />
            </div>

            <a
                href="/portal/login/govbr"
                className="flex w-full items-center justify-center gap-1.5 rounded-lg bg-brand-500 px-4 py-3 text-sm font-medium text-white transition-colors hover:bg-brand-600 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-brand-500/40 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900"
            >
                Entrar com{' '}
                <span className="text-base font-extrabold tracking-tight">gov.br</span>
            </a>
        </div>
    );
}
