/**
 * Cores dos gráficos, alinhadas aos tokens do tema
 * (brand, success, warning, error e a escala gray do TailAdmin).
 * O canvas do ECharts não herda classe Tailwind; a fonte única fica aqui.
 */
export const chartPalette = {
    brand: '#1351b4',
    brandSoft: '#5992ed',
    success: '#12b76a',
    warning: '#f79009',
    error: '#f04438',
    gray300: '#d0d5dd',
    gray400: '#98a2b3',
    gray700: '#344054',
    gray800: '#1d2939',
    gray25: '#f2f4f7',
} as const;
