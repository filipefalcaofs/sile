import { useEffect, useRef } from 'react';
import type { EChartsType } from 'echarts/core';
import { echarts } from './echarts-core';
import type { EChartsOption } from './echarts-core';

export interface ChartImplProps {
    /** Configuração do gráfico (restrita aos módulos de echarts-core). */
    option: EChartsOption;
    /** Classe do contêiner — define a altura (mesma do skeleton). */
    className?: string;
}

/**
 * Lê o tema vigente do Design System: a app alterna o modo escuro pela classe
 * `dark` no <html> (ThemeProvider), nunca por `prefers-color-scheme` — Pitfall 5.
 */
function isDarkMode(): boolean {
    return typeof document !== 'undefined' && document.documentElement.classList.contains('dark');
}

/**
 * Inicialização do ECharts que toca o DOM (`echarts.init`) — montada apenas no
 * cliente via `Chart` (lazy + mounted). Mantém uma única instância: observa o
 * resize do contêiner e a troca de tema do DS, reaplicando tema/opção sem
 * recriar o gráfico; faz `dispose` no unmount.
 */
export function ChartImpl({ option, className }: ChartImplProps) {
    const containerRef = useRef<HTMLDivElement>(null);
    const chartRef = useRef<EChartsType | null>(null);
    const optionRef = useRef(option);
    optionRef.current = option;

    useEffect(() => {
        const container = containerRef.current;
        if (!container) {
            return;
        }

        const chart = echarts.init(container, isDarkMode() ? 'dark' : null);
        chart.setOption(optionRef.current);
        chartRef.current = chart;

        const resizeObserver = new ResizeObserver(() => chart.resize());
        resizeObserver.observe(container);

        // Acompanha a classe `dark` do <html>: troca o tema na instância viva
        // (setTheme do ECharts 6) e reaplica a opção, sem recriar o gráfico.
        const themeObserver = new MutationObserver(() => {
            chart.setTheme(isDarkMode() ? 'dark' : 'default');
            chart.setOption(optionRef.current);
        });
        themeObserver.observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['class'],
        });

        return () => {
            resizeObserver.disconnect();
            themeObserver.disconnect();
            chart.dispose();
            chartRef.current = null;
        };
    }, []);

    useEffect(() => {
        chartRef.current?.setOption(option, true);
    }, [option]);

    return <div ref={containerRef} className={className ?? 'h-80 w-full'} />;
}

export default ChartImpl;
