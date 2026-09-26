import { createContext, useContext, useEffect, useState } from 'react';
import type { ReactNode } from 'react';

type Theme = 'light' | 'dark';

type ThemeContextType = {
    theme: Theme;
    toggleTheme: () => void;
};

const ThemeContext = createContext<ThemeContextType | undefined>(undefined);

/**
 * Controla o tema claro/escuro: aplica a classe .dark no <html> e
 * persiste a escolha em localStorage ("theme"), como no TailAdmin.
 */
export function ThemeProvider({ children }: { children: ReactNode }) {
    const [theme, setTheme] = useState<Theme>('light');
    const [isInitialized, setIsInitialized] = useState(false);

    useEffect(() => {
        const contraste = localStorage.getItem('acc-contrast') === '1';
        const savedTheme = localStorage.getItem('theme') as Theme | null;

        setTheme(contraste ? 'light' : (savedTheme ?? 'light'));
        setIsInitialized(true);
    }, []);

    useEffect(() => {
        const aoContraste = (evento: Event) => {
            if ((evento as CustomEvent<boolean>).detail) {
                setTheme('light');
            }
        };

        window.addEventListener('sile:acc-contrast', aoContraste);

        return () => window.removeEventListener('sile:acc-contrast', aoContraste);
    }, []);

    useEffect(() => {
        if (isInitialized) {
            localStorage.setItem('theme', theme);
            if (theme === 'dark') {
                document.documentElement.classList.add('dark');
                document.documentElement.classList.remove('acc-contrast');
                localStorage.setItem('acc-contrast', '0');
                window.dispatchEvent(new CustomEvent('sile:theme', { detail: 'dark' }));
            } else {
                document.documentElement.classList.remove('dark');
            }
        }
    }, [theme, isInitialized]);

    const toggleTheme = () => {
        setTheme((prevTheme) => (prevTheme === 'light' ? 'dark' : 'light'));
    };

    return <ThemeContext.Provider value={{ theme, toggleTheme }}>{children}</ThemeContext.Provider>;
}

export function useTheme(): ThemeContextType {
    const context = useContext(ThemeContext);
    if (context === undefined) {
        throw new Error('useTheme deve ser usado dentro de um ThemeProvider');
    }
    return context;
}
