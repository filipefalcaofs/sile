import { lazy, Suspense, useEffect, useState } from 'react';
import type { ChartImplProps } from './chart-impl';

// Guarda SSR: o ECharts acessa o DOM (`echarts.init`) e quebra o SSR do Inertia
// v3. O import dinâmico (lazy) só resolve no cliente e a flag `mounted` impede
// que o gráfico renderize no servidor ou na primeira passada — mesmo padrão do
// `MapaSection` (Pitfall 9). O import de echarts-core nunca entra no caminho do
// servidor: só é carregado por este chunk assíncrono.
const ChartImpl = lazy(() =>
    import('./chart-impl').then((module) => ({ default: module.ChartImpl })),
);

function ChartSkeleton({ className }: { className?: string }) {
    return (
        <div
            className={`${className ?? 'h-80 w-full'} animate-pulse rounded-2xl bg-gray-100 dark:bg-white/[0.03]`}
        />
    );
}

export type ChartProps = ChartImplProps;

/**
 * Wrapper de gráfico ECharts client-only e SSR-safe. O tema (claro/escuro) é
 * resolvido internamente pela classe `dark` do <html>; basta passar `option`.
 * Exibe um skeleton de mesma altura enquanto monta, evitando salto de layout.
 */
export function Chart({ option, className }: ChartProps) {
    const [mounted, setMounted] = useState(false);

    useEffect(() => {
        setMounted(true);
    }, []);

    if (!mounted) {
        return <ChartSkeleton className={className} />;
    }

    return (
        <Suspense fallback={<ChartSkeleton className={className} />}>
            <ChartImpl option={option} className={className} />
        </Suspense>
    );
}

export default Chart;
