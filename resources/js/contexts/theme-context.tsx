import { createContext, useContext, useEffect, useRef, useState } from 'react';
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
    const usuarioTrocou = useRef(false);

    useEffect(() => {
        const savedTheme = localStorage.getItem('theme') as Theme | null;

        setTheme(savedTheme === 'dark' ? 'dark' : 'light');
        setIsInitialized(true);
    }, []);

    useEffect(() => {
        if (!isInitialized) {
            return;
        }

        localStorage.setItem('theme', theme);

        if (usuarioTrocou.current) {
            usuarioTrocou.current = false;
            localStorage.setItem('acc-contrast', '0');
            document.documentElement.classList.remove('acc-contrast');
            window.dispatchEvent(new CustomEvent('viabiliza:theme-usuario'));
        }

        const contraste = localStorage.getItem('acc-contrast') === '1';

        if (theme === 'dark' && !contraste) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    }, [theme, isInitialized]);

    const toggleTheme = () => {
        usuarioTrocou.current = true;
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
