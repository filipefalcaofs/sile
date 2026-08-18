import { useEffect, useState } from 'react';

/**
 * Barra de acessibilidade no padrão gov.br / eMAG (Modelo de
 * Acessibilidade em Governo Eletrônico). Oferece os recursos exigidos
 * pela Lei Brasileira de Inclusão (13.146/2015) e Decreto 5.296/2004:
 *
 * - Atalhos de navegação por teclado ("Ir para o conteúdo/menu/rodapé"),
 *   com accesskeys 1/2/3, visíveis apenas ao receberem foco.
 * - Ajuste do tamanho da fonte (A- / A / A+).
 * - Alto contraste.
 *
 * As preferências são persistidas em localStorage e reaplicadas a cada
 * carga. O widget de Libras (VLibras) é injetado globalmente via blade
 * (resources/views/app.blade.php) por ser um script externo do governo.
 */

const SKIP_LINKS = [
    { href: '#conteudo', label: 'Ir para o conteúdo', accessKey: '1' },
    { href: '#menu', label: 'Ir para o menu', accessKey: '2' },
    { href: '#rodape', label: 'Ir para o rodapé', accessKey: '3' },
];

const FONT_STEPS = ['100%', '112.5%', '125%'];
const MAX_FONT_STEP = FONT_STEPS.length - 1;

const skipLinkClasses =
    'sr-only rounded-md bg-brand-600 px-3 py-1.5 text-sm font-medium text-white focus:not-sr-only focus:relative focus:z-[100] focus:inline-flex focus:outline-hidden focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-brand-700';

const controlClasses =
    'inline-flex items-center justify-center rounded px-2 py-1 font-medium text-gray-600 transition hover:bg-gray-200 hover:text-gray-900 focus:outline-hidden focus:ring-2 focus:ring-brand-500 dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white';

export default function AccessibilityBar() {
    const [fontStep, setFontStep] = useState(0);
    const [contrast, setContrast] = useState(false);

    useEffect(() => {
        const savedFont = Number(localStorage.getItem('acc-font'));
        const savedContrast = localStorage.getItem('acc-contrast') === '1';
        if (Number.isInteger(savedFont) && savedFont >= 0 && savedFont <= MAX_FONT_STEP) {
            setFontStep(savedFont);
        }
        setContrast(savedContrast);
    }, []);

    useEffect(() => {
        document.documentElement.style.fontSize = FONT_STEPS[fontStep] ?? '100%';
        localStorage.setItem('acc-font', String(fontStep));
    }, [fontStep]);

    useEffect(() => {
        document.documentElement.classList.toggle('acc-contrast', contrast);
        localStorage.setItem('acc-contrast', contrast ? '1' : '0');
    }, [contrast]);

    return (
        <div className="border-b border-gray-200 bg-gray-100 dark:border-gray-800 dark:bg-gray-950">
            <div className="mx-auto flex w-full max-w-(--breakpoint-2xl) flex-wrap items-center gap-x-3 gap-y-2 px-4 py-1.5 sm:px-6">
                <nav aria-label="Atalhos de acessibilidade" className="flex flex-wrap items-center gap-2">
                    {SKIP_LINKS.map((link) => (
                        <a key={link.href} href={link.href} accessKey={link.accessKey} className={skipLinkClasses}>
                            {link.label} [{link.accessKey}]
                        </a>
                    ))}
                </nav>

                <div className="ml-auto flex items-center gap-1 text-sm" role="group" aria-label="Recursos de acessibilidade">
                    <button
                        type="button"
                        onClick={() => setFontStep((step) => Math.max(0, step - 1))}
                        disabled={fontStep === 0}
                        className={`${controlClasses} disabled:cursor-not-allowed disabled:opacity-40`}
                        aria-label="Diminuir tamanho da fonte"
                    >
                        A<span aria-hidden="true">−</span>
                    </button>
                    <button
                        type="button"
                        onClick={() => setFontStep(0)}
                        className={controlClasses}
                        aria-label="Restaurar tamanho padrão da fonte"
                    >
                        A
                    </button>
                    <button
                        type="button"
                        onClick={() => setFontStep((step) => Math.min(MAX_FONT_STEP, step + 1))}
                        disabled={fontStep === MAX_FONT_STEP}
                        className={`${controlClasses} disabled:cursor-not-allowed disabled:opacity-40`}
                        aria-label="Aumentar tamanho da fonte"
                    >
                        A<span aria-hidden="true">+</span>
                    </button>
                    <button
                        type="button"
                        onClick={() => setContrast((current) => !current)}
                        aria-pressed={contrast}
                        className={controlClasses}
                    >
                        Alto contraste
                    </button>
                </div>
            </div>
        </div>
    );
}
