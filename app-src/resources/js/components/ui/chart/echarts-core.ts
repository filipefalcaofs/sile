import * as echarts from 'echarts/core';
import { BarChart, LineChart, PieChart } from 'echarts/charts';
import type { BarSeriesOption, LineSeriesOption, PieSeriesOption } from 'echarts/charts';
import {
    DatasetComponent,
    GridComponent,
    LegendComponent,
    MarkLineComponent,
    MarkPointComponent,
    TitleComponent,
    TooltipComponent,
} from 'echarts/components';
import type {
    DatasetComponentOption,
    GridComponentOption,
    LegendComponentOption,
    TitleComponentOption,
    TooltipComponentOption,
} from 'echarts/components';
import type { ComposeOption } from 'echarts/core';
import { CanvasRenderer } from 'echarts/renderers';

// Registro central tree-shakeable (echarts/core + echarts.use): só os charts,
// componentes e o renderer Canvas usados pelos relatórios entram no bundle
// (~150 KB vs ~1 MB do pacote cheio). O tema escuro daqui é transparente: o
// tema "dark" embutido do ECharts pinta um fundo próprio e destaca o gráfico
// do cartão.
echarts.use([
    BarChart,
    LineChart,
    PieChart,
    GridComponent,
    TooltipComponent,
    LegendComponent,
    MarkLineComponent,
    MarkPointComponent,
    TitleComponent,
    DatasetComponent,
    CanvasRenderer,
]);

/** Nome do tema escuro alinhado ao cartão (fundo transparente, texto e grades do DS). */
export const CHART_DARK_THEME = 'sile-dark';

echarts.registerTheme(CHART_DARK_THEME, {
    darkMode: true,
    backgroundColor: 'transparent',
    textStyle: { color: '#98a2b3' },
    legend: { textStyle: { color: '#d0d5dd' } },
    tooltip: {
        backgroundColor: '#1d2939',
        borderColor: '#344054',
        textStyle: { color: '#f2f4f7' },
    },
    categoryAxis: {
        axisLine: { lineStyle: { color: '#344054' } },
        axisTick: { show: false },
        axisLabel: { color: '#98a2b3' },
        splitLine: { lineStyle: { color: 'rgba(152,162,179,0.16)' } },
    },
    valueAxis: {
        axisLine: { show: false },
        axisLabel: { color: '#98a2b3' },
        splitLine: { lineStyle: { color: 'rgba(152,162,179,0.16)' } },
    },
});

/** Tipo de configuração restrito aos módulos registrados acima. */
export type EChartsOption = ComposeOption<
    | BarSeriesOption
    | LineSeriesOption
    | PieSeriesOption
    | GridComponentOption
    | TooltipComponentOption
    | LegendComponentOption
    | TitleComponentOption
    | DatasetComponentOption
>;

export { echarts };
