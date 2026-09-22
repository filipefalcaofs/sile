import * as echarts from 'echarts/core';
import { BarChart, LineChart, PieChart } from 'echarts/charts';
import type { BarSeriesOption, LineSeriesOption, PieSeriesOption } from 'echarts/charts';
import {
    DatasetComponent,
    GridComponent,
    LegendComponent,
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
// (~150 KB vs ~1 MB do pacote cheio). Importar este módulo registra também o
// tema "dark" embutido (echarts/core → lib/core/echarts.js), usado pelo wrapper
// para acompanhar a classe `dark` do <html> sem recriar a instância.
echarts.use([
    BarChart,
    LineChart,
    PieChart,
    GridComponent,
    TooltipComponent,
    LegendComponent,
    TitleComponent,
    DatasetComponent,
    CanvasRenderer,
]);

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
