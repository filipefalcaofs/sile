# Fase 15: Relatórios e Indicadores (EP15) - Pesquisa

**Researched:** 2026-06-15
**Domain:** Camada de leitura/análise gerencial (agregação SQL, exportação multiformato, dashboards) sobre dados reais já registrados nas Fases 1–12
**Confidence:** HIGH (stack e padrões verificados no código real e na doc oficial; pontos LOW sinalizados)

## Summary

A Fase 15 é a única fase do roadmap **autonomamente executável** (sem dependência externa): consome dados que as Fases 8–12 já gravam. O CONTEXT já fixou o "o quê" e o "como" macro (serviços route-free espelhando `ProcessoQueryService`; export único `ReportDefinition`/`ReportExporter` + drivers; gráficos Apache ECharts com wrapper próprio SSR-safe; openspout para XLSX; HU-137 feriados incluída; captura estruturada de queda HU-145). Esta pesquisa responde **como executar bem** cada uma dessas decisões, com a API exata das duas dependências novas e os padrões reais do código a reusar.

Achados principais que mudam o plano:
1. **openspout v5.7.x exige PHP 8.4/8.5** — o runtime é 8.5.4 (OK), mas o `composer.json` declara `php: ^8.3`. A instalação resolve no ambiente atual; é preciso bumpar o constraint para `^8.4` (honestidade) ou aceitar que a fase eleva o piso efetivo. A API de escrita é **path-based** (`openToFile`), não stream — isso molda o contrato dos drivers.
2. **`echarts-for-react` 3.0.6 tem bug ABERTO de crash com Vite 8 + React 19** (CJS interop, issue #619, 2026-03) — exatamente a stack do projeto. **Recomendação forte: wrapper próprio sobre `echarts/core` v6.1.x** (sem `echarts-for-react`), espelhando o wrapper hand-rolled do Leaflet. Menos dependência, sem o crash conhecido, alinhado ao CONTEXT.
3. **Índices ausentes para os recortes por período/decisão**: `viability_requests.protocoled_at`, `viability_decisions.decided_at`, `viability_decisions.decided_by_user_id` e `viability_request_transitions(viability_request_id, created_at)` **NÃO têm índice**. O CONTEXT afirma que `protocoled_at`/`decided_at` estão indexados — está incorreto. Migration aditiva de índices é pré-requisito de performance (HU-082 RN-008 reforça "sistema lento" como dor do legado).
4. **Risco (HU-126) e CNAE (HU-125) não são colunas do processo**: derivam de `viability_request_cnaes` (N CNAEs por processo) → join com `risk_classifications` (versão vigente). Decisão de contagem (CNAE principal vs todos; versão vigente vs da decisão) precisa ser fixada — recomendação abaixo.
5. **HU-129 precisa de uma capacidade NOVA no `BusinessDeadlineCalculator`**: hoje ele só calcula prazo PARA FRENTE (`dueAt(from, hours)`); o tempo POR ETAPA mede tempo DECORRIDO entre dois timestamps reais em dias úteis. São duas mudanças adjacentes no mesmo seam (pular fins de semana/feriados no `dueAt` E adicionar `elapsedBusiness(from, to)`).

**Primary recommendation:** Executar nas waves do CONTEXT; tratar os 5 achados acima como tarefas explícitas (migration de índices na W1; capacidade `elapsedBusiness` + feriados na W2/HU-137; wrapper ECharts próprio na W5; contrato de driver path-based na W1/export base). O teste anti-fachada CA-03 ("não inventa número quando falta dado") é o de maior prioridade.

## Standard Stack

### Dependências novas (APROVADAS no CONTEXT — exigem require)

| Lib | Versão | Propósito | Por que é o padrão |
|-----|--------|-----------|--------------------|
| `openspout/openspout` | **v5.7.2** (5.x) | Driver XLSX do export por streaming | Fork mantido do box/spout; escrita row-by-row com **memória < 3MB** independente do volume; MIT; encaixe natural no Job assíncrono. Preferido a `maatwebsite/excel` (que carrega PhpSpreadsheet inteiro em memória) e ao próprio PhpSpreadsheet. |
| `echarts` | **v6.1.0** | Gráficos do dashboard (HU-122) e série temporal (HU-145) | Lib de chart única do projeto (decisão do usuário); import tree-shakeable via `echarts/core`; canvas/SVG renderers; ESM nativo (compatível com Vite 8). |

**NÃO instalar** `echarts-for-react` (ver Pitfall 2). **NÃO instalar** `maatwebsite/excel` nem `phpoffice/phpspreadsheet`.

### Instalação

```bash
composer require openspout/openspout
npm install echarts
```

**Atenção composer (HIGH):** openspout v5 requer `php >=8.4`. Runtime atual = PHP 8.5.4 → resolve. Mas `composer.json` declara `"php": "^8.3"`. Ações: (a) bumpar para `"php": "^8.4"` no `composer.json` (recomendado — reflete a realidade), ou (b) se 8.3 for requisito de contrato, fixar `openspout/openspout:^4` (que ainda suporta 8.2+) — porém v4 tem API de fábrica diferente (`WriterEntityFactory`). Como STATE confirma PHP 8.5 em dev/CI/Docker, seguir (a).

### Dependências já instaladas a REUSAR (sem novo require)

| Lib | Uso na Fase 15 |
|-----|----------------|
| `barryvdh/laravel-dompdf` ^3.1 | Driver PDF do export (já usado em `TvlPdfService`) |
| `predis/predis` ^3.5 | `Cache::remember` do dashboard (TTL curto) e `Cache::lock` se preciso |
| `@inertiajs/react` ^3.3 + `@inertiajs/vite` ^3.3 | SSR ligado via plugin Vite — wrapper de chart precisa ser client-only |
| `leaflet`/`react-leaflet` ^5 | **Referência de padrão** SSR-safe (não usar para chart) |

## Architecture Patterns

### Estrutura de diretórios (a criar)

```
app/Services/Relatorios/
├── ReportFilters.php                 # Value Object compartilhado (período/setor/zona/CNAE/categoria/analista)
├── IndicadoresViabilidadeService.php # HU-123/124/125/126/127/128
├── TempoAnaliseService.php           # HU-129 (reusa BusinessDeadlineCalculator estendido)
├── ProdutividadeAnalistaService.php  # HU-130
├── ExpressoQuedaService.php          # HU-145 (ranking de motivos + taxa expressa + drill-down)
└── Export/
    ├── ReportDefinition.php          # readonly: titulo, colunas, Builder filtrado, filtrosAplicados, logName, event, personalData, arquivoBase
    ├── ReportFormatExporter.php       # interface (1 driver por formato)
    ├── ReportExporter.php             # orquestra sync vs assíncrono (count vs limiar)
    ├── CsvExporter.php
    ├── XlsxExporter.php               # openspout
    └── PdfExporter.php                # dompdf + Blade genérico

app/Jobs/GerarExportacaoJob.php        # padrão DecidirFluxoExpressoJob (tries/timeout/backoff/fila)
app/Http/Controllers/Gestao/RelatorioController.php
resources/js/components/ui/chart/      # wrapper ECharts SSR-safe + chart-section (mount client-only)
resources/js/components/ui/data-table/export-menu.tsx
```

### Pattern 1: Serviço de agregação route-free (espelhar `ProcessoQueryService`)

O padrão canônico já existe e deve ser copiado: Builder reutilizável com `when()` por filtro, `whereLike(..., caseSensitive: false)` (case-insensitive em PostgreSQL E SQLite), helpers `valor/inteiro/data/categoria` normalizando a query string. Para relatórios, em vez de paginar, agrega com `count`/`avg`/`groupBy` **em SQL** (nunca loop PHP).

```php
// IndicadoresViabilidadeService — distribuição por status no período (HU-123)
// Fonte: ProcessoQueryService::filtered() já monta o Builder filtrado;
// o relatório reusa o MESMO Builder e só troca a projeção (RN-005 do export).
public function porStatus(ReportFilters $filtros): array
{
    return $this->processos->filtered($filtros->toArray())
        ->reorder() // remove o orderByDesc('id') do filtered antes do groupBy
        ->groupBy('status')
        ->selectRaw('status, count(*) as total')
        ->pluck('total', 'status')
        ->all();
}
```

Pontos de atenção reais:
- `ProcessoQueryService::filtered()` termina com `->orderByDesc('id')`. Antes de `groupBy`, chamar `->reorder()` (senão o `id` entra no GROUP BY e quebra no PostgreSQL com "must appear in GROUP BY").
- Agregação SQL nunca em PHP (CONTEXT). `count`/`avg`/`groupBy` sobre colunas indexadas; ver migration de índices abaixo.
- Cache do dashboard: `Cache::remember("relatorios.dashboard.{$hashFiltros}", config('sile.relatorios.cache_ttl_segundos', 300), fn () => ...)`. Números sempre reais; cache é só TTL técnico curto.

### Pattern 2: Contrato de exportação único (HU-131) — API path-based por causa do openspout

**Decisão de design recomendada (CONTEXT deixa o shape à discrição do planner):** como `openspout` escreve para um **caminho de arquivo** (não para um stream), unificar os 3 drivers atrás de um contrato baseado em path para o caminho assíncrono/disco, e preservar o streaming nativo apenas onde a anti-regressão exige (os 2 CSVs existentes).

```php
interface ReportFormatExporter
{
    /** Escreve o relatório COMPLETO num caminho local (usado pelo Job e pelo download sync de XLSX/PDF). */
    public function writeToPath(ReportDefinition $definition, string $absolutePath): void;

    public function extension(): string;       // 'csv' | 'xlsx' | 'pdf'
    public function mimeType(): string;
}
```

`ReportExporter::export(ReportDefinition $def, string $formato)`:
1. Audita SEMPRE (RN-008): usuário/tela/filtros/formato/volume via `AuditService::log(..., personalData: $def->personalData)`.
2. `if ($def->builder->toBase()->getCountForPagination() > $limiar)` → despacha `GerarExportacaoJob` (assíncrono) → grava em disco não-público → notifica via `NotificationDispatcher` → download por `URL::temporarySignedRoute` (espelha `TvlDocumentController::downloadUrl`).
3. Senão (sync): grava em arquivo temporário e devolve `response()->download($tmp, $nome)->deleteFileAfterSend()`.

**Anti-regressão dos 2 CSVs existentes (W4):** `AuditoriaController::export` e `ProcessoController::exportarCsv` hoje usam `response()->streamDownload(fputcsv...)` (StreamedResponse). Ler os testes atuais ANTES de migrar e preservar o **contrato observável** (filename, colunas, conteúdo, auditoria `personalData`). Se os testes afirmam `StreamedResponse`/`streamedContent()`, manter o caminho sync do CSV como streaming nativo (o `CsvExporter` pode expor também um `streamTo(php://output)`), e usar `writeToPath` só no caminho assíncrono. XLSX é "primariamente assíncrono" no CONTEXT — não precisa de streaming sync.

### Pattern 3: openspout XLSX por streaming (memória baixa) dentro do Job

API verificada (openspout v5.7, Context7):

```php
use OpenSpout\Writer\XLSX\Writer;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Style\Style;

final class XlsxExporter implements ReportFormatExporter
{
    public function writeToPath(ReportDefinition $def, string $absolutePath): void
    {
        // Opcional: tempFolder controlável; inline strings = mais rápido.
        $writer = new Writer(new Options(SHOULD_USE_INLINE_STRINGS: true));
        $writer->openToFile($absolutePath);

        // Cabeçalho (estilo opcional)
        $header = new Style(fontBold: true);
        $writer->addRow(Row::fromValuesWithStyles(
            $def->colunas,
            array_fill_keys(array_keys($def->colunas), $header),
        ));

        // STREAMING: chunk no Builder filtrado — uma "página" por vez na memória.
        $def->builder->chunk(self::CHUNK, function ($linhas) use ($writer, $def): void {
            foreach ($linhas as $modelo) {
                $writer->addRow(Row::fromValues($def->mapRow($modelo)));
            }
        });

        // RN-010: XLSX NÃO recebe linha de total no corpo (preserva integridade tabular);
        // total/data/filtros vão em metadados (Properties) ou aba própria, se exigido.
        $writer->close();
    }
}
```

Notas:
- `Row::fromValues([...])` auto-detecta tipo; datas como `DateTimeImmutable` viram célula de data; `null` = célula vazia.
- Para escrever no disco do Storage: openspout precisa de um path real. Como o disco do export é `local` (não-público, igual ao TVL), usar `$path = Storage::disk($disk)->path($relativo)` e passar a `openToFile`. Para discos remotos (S3 futuro), escrever em `tempnam()` e depois `Storage::disk($disk)->put($relativo, fopen($tmp,'r'))`.
- **Não usar `openToBrowser()` dentro de `response()->streamDownload`** — o `openToBrowser` seta seus próprios headers (Content-Type/Disposition) e colide com os do Laravel. Para XLSX sync, escrever em arquivo temporário e `response()->download(...)->deleteFileAfterSend()`.

### Pattern 4: Wrapper ECharts próprio, SSR-safe, tree-shakeable (espelhar `mapa-section.tsx`)

O projeto já resolve "lib que toca `window`/DOM no import" com `lazy()` + guarda `mounted` + `Suspense` (ver `resources/js/components/geo/mapa-section.tsx`). Replicar para ECharts:

```tsx
// resources/js/components/ui/chart/echarts-core.ts — registro tree-shakeable único
import * as echarts from 'echarts/core';
import { BarChart, LineChart, PieChart } from 'echarts/charts';
import {
    GridComponent, TooltipComponent, LegendComponent,
    TitleComponent, DatasetComponent,
} from 'echarts/components';
import { CanvasRenderer } from 'echarts/renderers';

echarts.use([
    BarChart, LineChart, PieChart,
    GridComponent, TooltipComponent, LegendComponent, TitleComponent, DatasetComponent,
    CanvasRenderer,
]);

export { echarts };
```

```tsx
// resources/js/components/ui/chart/echart.tsx — wrapper imperativo (init/setOption/resize/dispose)
import { useEffect, useRef } from 'react';
import type { EChartsOption } from 'echarts';
import { echarts } from './echarts-core';

export function EChart({ option, theme = 'light', className }: { option: EChartsOption; theme?: 'light' | 'dark'; className?: string }) {
    const ref = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!ref.current) return;
        // tema só é aplicável no init → dispose + re-init quando o tema muda
        const chart = echarts.init(ref.current, theme === 'dark' ? 'dark' : undefined, { renderer: 'canvas' });
        chart.setOption({ backgroundColor: 'transparent', ...option });

        const ro = new ResizeObserver(() => chart.resize());
        ro.observe(ref.current);

        return () => { ro.disconnect(); chart.dispose(); };
    }, [theme]); // re-init no tema

    useEffect(() => {
        const inst = ref.current && echarts.getInstanceByDom(ref.current);
        inst?.setOption(option, { notMerge: true }); // dados mudam sem re-init
    }, [option]);

    return <div ref={ref} className={className} style={{ width: '100%', height: '100%' }} />;
}
```

```tsx
// resources/js/components/ui/chart/chart-section.tsx — mount client-only (idêntico ao MapaSection)
import { lazy, Suspense, useEffect, useState } from 'react';
const EChart = lazy(() => import('./echart').then((m) => ({ default: m.EChart })));

export function ChartSection(props: { option: unknown; theme?: 'light' | 'dark'; className?: string }) {
    const [mounted, setMounted] = useState(false);
    useEffect(() => setMounted(true), []);
    if (!mounted) return <div className="h-72 animate-pulse rounded-2xl bg-gray-100 dark:bg-white/[0.03]" />;
    return <Suspense fallback={<div className="h-72 animate-pulse rounded-2xl bg-gray-100 dark:bg-white/[0.03]" />}><EChart {...(props as never)} /></Suspense>;
}
```

Tema claro/escuro do DS TailAdmin (console escuro × portal claro): derivar `theme` do estado de dark-mode da app (classe `dark` no `<html>` — checar como o gestao-layout alterna) e passar ao wrapper. ECharts aplica tema só no `init` → o wrapper re-inicializa no `useEffect([theme])`. `backgroundColor: 'transparent'` para herdar o card. Responsividade via `ResizeObserver` → `chart.resize()`.

### Pattern 5: HU-129 — tempo por etapa + feriados (HU-137)

`viability_request_transitions` é a fonte (from/to/created_at). A duração de uma etapa = diff entre transições consecutivas do MESMO processo. Etapas (CONTEXT):
- **preenchimento**: `viability_requests.created_at` → `protocoled_at`
- **espera/encaminhamento**: transição `protocolada` → `em_analise` (ou decisão direta = expresso)
- **análise**: `em_analise` → decisão, descontando intervalos `em_pendencia`
- **pendência**: somatório `em_pendencia` → `em_analise`

**Implementação recomendada:** carregar as transições do conjunto filtrado ordenadas por `(viability_request_id, created_at)` e computar as durações por processo. O cálculo de duração consecutiva é inerentemente sequencial — não viola "nunca loop PHP" (que vale para CONTAGEM/AGREGAÇÃO); aqui o loop monta as durações e a MÉDIA final é agregada. Para o volume de Salvador, OK. Alternativa PostgreSQL (`LAG() OVER (PARTITION BY request ORDER BY created_at)`) é mais rápida mas SQLite (suíte) exige cuidado de portabilidade → preferir o cálculo em PHP sobre o conjunto carregado, ou window function com fallback.

**Capacidade NOVA no `BusinessDeadlineCalculator` (seam HU-137):** hoje a classe só tem `dueAt(from, hours)` (prazo PARA FRENTE) e `isOverdue()`. HU-129 precisa do tempo **decorrido** entre dois timestamps em dias úteis. São DUAS mudanças no mesmo seam:
1. `dueAt()` passa a pular fins de semana + feriados (intenção original de HU-137 para o prazo BAP/HU-134 e SLA/HU-144 — sem tocar call sites, que só dependem de `dueAt`/`isOverdue`).
2. Adicionar `elapsedBusiness(from, to): float` (segundos/horas úteis entre dois instantes reais) para HU-129.

```php
// BusinessDeadlineCalculator estendido — consulta o cadastro de feriados (HU-137)
public function __construct(private readonly HolidayCalendar $holidays) {}

public function dueAt(DateTimeInterface $from, int $hours): Carbon
{
    $cursor = Carbon::instance($from);
    $remaining = $hours;
    while ($remaining > 0) {
        $cursor->addHour();
        if (! $this->isBusinessInstant($cursor)) { continue; } // pula fim de semana/feriado
        $remaining--;
    }
    return $cursor;
}

private function isBusinessInstant(Carbon $i): bool
{
    return ! $i->isWeekend() && ! $this->holidays->isHoliday($i->toDateString());
}
```

Onde `HolidayCalendar` lê a tabela `holidays` (HU-137) com cache. **Degradação honesta:** sem lista oficial de feriados (pendência SEDUR), o cadastro existe vazio → conta só dias úteis sem feriados, **com ressalva visível na UI** ("feriados municipais pendentes de cadastro"). Nunca feriado inventado.

**HU-137 — modelagem do cadastro de feriados:**
- Tabela `holidays`: `id`, `date` (date, unique ou unique+scope), `name`, `scope` (nacional/municipal/estadual), `recurring` (bool — ex.: 25/12 todo ano), `active`, timestamps. Versionado/auditado (RN-002) — reusar `AuditService` nos CRUDs; histórico via activity_log (padrão dos outros cadastros).
- CRUD administrável em `routes/gestao.php` atrás de `manter-parametros` (reuso) ou permissão própria `manter-feriados` (decisão abaixo). UI nasce com `<ExportMenu>` (HU-131 RN-004 lista "feriados" explicitamente).
- Modelo simples (cadastro versionado/auditado parametrizável); não confundir com os "dados versionados" pesados (rule_versions) — feriado é um cadastro CRUD comum auditado.

**Relatórios SAPS (RN-006):**
- **Tempo de Emissão de TVL** = `protocoled_at` → `viability_decisions.decided_at` onde `tvl_product_number IS NOT NULL`, em dias úteis (`elapsedBusiness`).
- **Sedes de Escritório Virtual** = recorte com `is_virtual_office = true`.

### Pattern 6: HU-145 — captura estruturada da queda (aditivo, anti-regressão Fases 9/10)

Hoje a queda grava 3 categorias grossas em `transitions.reason` + auditoria; o gatilho específico (`TipoGatilho`) fica aninhado em `analysis_records.engine_snapshot` (cobertura parcial). A captura estruturada acontece em `FluxoExpressoService::encaminharAnalise()` (ver arquivo), DENTRO da transação já existente — puramente **aditiva**.

Fonte do gatilho: `$resolved->por_cnae[i]` traz `cnae`, `cnae_formatado`, `is_primary`, `tendencia`, `fluxo` (`expresso`|`analise`) e `consulta`/`consulta_array` (o `ConsultaViabilidadeResult`). Um CNAE "caiu" quando `fluxo === 'analise'`. O `TipoGatilho` (enquadramento_ausente/zeis_especial/dados_do_processo) é produzido pelo motor de risco e exposto no resultado da consulta — o executor deve localizá-lo em `consulta_array` (seção de risco) ou no `RiscoResult`/`ConsultaViabilidadeResult` para gravá-lo estruturado.

```php
// Nova tabela: request_fall_reasons (ou expresso_fallbacks)
Schema::create('request_fall_reasons', function (Blueprint $table) {
    $table->id();
    $table->foreignId('viability_request_id')->index()->constrained()->cascadeOnDelete();
    $table->string('cnae');                 // por CNAE (RN-001)
    $table->string('tipo_gatilho')->nullable(); // TipoGatilho->value; null quando a queda é locacional/pendente, não de risco
    $table->string('dimensao');             // 'risco' | 'territorio' | 'processo'
    $table->string('motivo');               // categoria estruturada (nunca texto livre — RN-001)
    $table->timestamps();
    $table->index(['tipo_gatilho']);
    $table->index(['created_at']);
});
```

Gravação em `encaminharAnalise`, no mesmo `DB::transaction`, iterando `$resolved->por_cnae` onde `fluxo==='analise'`. A auditoria e a transição atuais permanecem (rede anti-regressão). `ExpressoQuedaService` (HU-145) então lê esta tabela para o ranking de motivos + CNAEs que mais caem, e cruza `analysis_divergences` (analista×motor) para o drill-down; a **taxa de resposta expressa** = decisões `flow='expresso'` ÷ elegíveis no período (série temporal), com meta/janela parametrizáveis.

**Anti-regressão:** rodar a suíte das Fases 9/10 ANTES e DEPOIS; o write é additivo e idempotente por natureza (uma queda gera N linhas, uma por CNAE caído). Spy do motor não muda (o resolver já roda; só lemos o resultado em memória).

### Anti-Patterns a evitar

- **Materialized view / tabela-resumo de indicadores**: deferido no CONTEXT — GROUP BY + cache TTL curto basta no volume de Salvador. Não construir.
- **Mega-service de relatórios**: um serviço por domínio (espelhar a granularidade de `ProcessoQueryService`/`AnalysisSlaService`).
- **`openToBrowser` dentro de `streamDownload`**: colisão de headers (ver Pattern 3).
- **`echarts-for-react`**: bug aberto com Vite 8 + React 19 (Pitfall 2).
- **Delta "+X%" inventado nos KPIs**: sem série histórica persistida, comparativos degradam honesto (sem delta) — precedente da Fase 2.4 (`DashboardController` "sem delta inventado").
- **Risco/CNAE como coluna do processo**: não existe; derivar por join (Pattern do HU-125/126 abaixo).

## Don't Hand-Roll

| Problema | Não construir | Usar | Por quê |
|----------|---------------|------|---------|
| Escrever XLSX | Gerador de XML OOXML próprio | `openspout` | OOXML é complexo; openspout faz streaming < 3MB |
| Renderizar gráfico | SVG/Canvas próprio | `echarts/core` (wrapper fino) | Decisão do usuário; echarts cobre bar/line/pie/série temporal |
| PDF de relatório | Montar PDF na mão | `dompdf` (já instalado) + Blade | Padrão já validado no `TvlPdfService` |
| Filtros da query | Reescrever when/whereLike | `ProcessoQueryService` + `ReportFilters` | Filtros do SAPS já testados; reuso garante consistência tela↔export |
| Job de fila resiliente | Job do zero | Padrão `DecidirFluxoExpressoJob` | tries/timeout/backoff/fila parametrizados + `failed()` auditado |
| Download seguro | URL pública / token caseiro | `URL::temporarySignedRoute` + disco não-público | Padrão `TvlDocumentController` (CA-02/LGPD) |
| Notificar export pronto | E-mail solto | `NotificationDispatcher` (EP11) | Multicanal + ledger honesto + toggles |
| Auditoria | activity() cru | `AuditService::log(...)` | RN-002 com result/rulesVersion/personalData |
| Cálculo de prazo útil | Lib de calendário externa | estender `BusinessDeadlineCalculator` | Seam já preparado; feriados via HU-137 |
| Parâmetros/feature toggle | config hardcoded | `Settings::get` + `ParameterSeeder` + `config/sile.php` | HU-014: efeito sem deploy, fallback sem banco |

## Common Pitfalls

### Pitfall 1: openspout exige PHP 8.4+ (composer.json declara ^8.3) — HIGH
**O que dá errado:** `composer require openspout/openspout` num ambiente 8.3 falha; em 8.5 resolve mas o `composer.json` fica mentindo (`^8.3`).
**Como evitar:** bumpar `"php": "^8.4"` no `composer.json` (runtime real = 8.5.4). Verificar `composer require` retorna v5.7.x e `composer test` segue verde.
**Sinal de alerta:** "requires php >=8.4 but your php version (8.3.x) does not satisfy".

### Pitfall 2: `echarts-for-react` quebra com Vite 8 + React 19 — HIGH
**O que dá errado:** issue #619 (aberta 2026-03): `EChartsReactCore` crasha com "Element type is invalid... got: object" em Vite 8 + React 19 (CJS interop). É exatamente a stack do projeto (`vite ^8`, `react ^19`).
**Como evitar:** não instalar `echarts-for-react`; usar wrapper próprio sobre `echarts/core` (Pattern 4). Bônus: controle total de tema/resize/dispose e bundle menor.
**Sinal de alerta:** crash de runtime no primeiro render do chart, ou aviso de peer dep no `npm install`.

### Pitfall 3: índices ausentes para período/decisão/transições — HIGH
**O que dá errado:** recortes por `protocoled_at` (HU-123), `decided_at`/`decided_by_user_id` (HU-127/128/130) e o tempo por etapa lendo `transitions` por processo fazem full scan. O CONTEXT afirma erroneamente que `protocoled_at`/`decided_at` já estão indexados.
**Como evitar:** migration ADITIVA na W1: índices em `viability_requests.protocoled_at`, `viability_decisions(decided_at)`, `viability_decisions(decided_by_user_id)`, `viability_decisions(flow, outcome)` e **composto** `viability_request_transitions(viability_request_id, created_at)`. Repetir o cuidado driver-aware (PostgreSQL/SQLite).
**Sinal de alerta:** relatórios lentos no seed grande; `EXPLAIN` com Seq Scan.

### Pitfall 4: GROUP BY com ordenação herdada do `filtered()` — MEDIUM
**O que dá errado:** `ProcessoQueryService::filtered()` aplica `->orderByDesc('id')`. Em PostgreSQL, `GROUP BY status` com `ORDER BY id` exige `id` no GROUP BY → erro.
**Como evitar:** `->reorder()` antes de `groupBy`/`selectRaw` nos serviços de relatório.

### Pitfall 5: contagem por CNAE/risco multiplica processos — MEDIUM
**O que dá errado:** um processo tem N CNAEs (`viability_request_cnaes`) → contar "por CNAE"/"por risco" via join conta o processo N vezes (números > total real → parece "inventado").
**Como evitar:** decidir e documentar o critério: contagem por **CNAE principal** (`is_primary=true`) para "volume de processos", e contagem por **ocorrência de CNAE** (todas) só quando a métrica for "atividades", deixando claro o denominador. Risco vem de `risk_classifications` (versão vigente via `RuleVersion::vigente(RuleDomain::RiscoMunicipal)`), por `cnae_code`. CNAE sem classificação → "não classificado" (degradação honesta, nunca dropar a linha).

### Pitfall 6: SSR do Inertia v3 quebra com ECharts no import — MEDIUM
**O que dá errado:** `echarts.init` toca DOM/canvas; se o componente renderizar no servidor, o SSR quebra (mesmo Pitfall 9 do Leaflet).
**Como evitar:** `chart-section.tsx` com `lazy()` + guarda `mounted` + `Suspense` (Pattern 4). Nunca importar `echarts` no topo de uma página renderizada por `Inertia::render` sem o wrapper client-only.

### Pitfall 7: suíte em 2 processos (SQLite + @group postgis) — MEDIUM
**O que dá errado:** lição da Fase 10 — `composer test` roda 2 processos (`--exclude-group postgis` e `--group postgis`). Agregações com SQL específico de PostgreSQL (window functions, `to_char` para mês) podem passar num e falhar no outro.
**Como evitar:** preferir agregação portável (Eloquent/`selectRaw` compatível) ou cobrir o ramo PostgreSQL com `@group postgis`. Para "agrupar por mês", evitar `DATE_FORMAT`/`to_char` divergentes — agrupar por data e formatar em PHP, ou usar `whereBetween` por janela.

### Pitfall 8: re-seed de parâmetros e contagem dos testes — MEDIUM
**O que dá errado:** `ParameterSeederTest` afirma `assertSame(85, ...)` + lista de grupos; `RolesAndPermissionsSeederTest` afirma `assertSame(27, Permission::count())` e `assertSame(4, Role::count())`. Adicionar parâmetros/permissões sem atualizar esses testes quebra a suíte.
**Como evitar:** dono único na W1 atualiza catálogo + contagens (85→85+N, 27→27+N) + grupos. `value` nunca entra no update do upsert (preserva o que o admin gravou).

### Pitfall 9: marcador/asset de chart no bundle — LOW
**O que dá errado:** menos provável que o Leaflet, mas temas/ícones de echarts importados por caminho podem não resolver no Vite.
**Como evitar:** usar só `echarts/core` + módulos ESM; tema via objeto/`registerTheme`, não import de arquivo de tema legado.

## Code Examples

### HU-127/128 — taxa de deferimento/indeferimento (real, sem inventar)

```php
// IndicadoresViabilidadeService
public function taxas(ReportFilters $f): array
{
    $base = $this->decisionsNoPeriodo($f); // Builder sobre viability_decisions join requests p/ filtros
    $total = (clone $base)->count();
    if ($total === 0) {
        return ['deferimento' => null, 'indeferimento' => null, 'total' => 0]; // CA-03: sem dado, sem número
    }
    $deferidas = (clone $base)->where('outcome', DecisionOutcome::Deferida->value)->count();
    $indeferidas = (clone $base)->where('outcome', DecisionOutcome::Indeferida->value)->count();
    return [
        'deferimento' => round($deferidas / $total * 100, 1),
        'indeferimento' => round($indeferidas / $total * 100, 1),
        'total' => $total,
    ];
}
```

### HU-145 — taxa de resposta expressa (série temporal real)

```php
// ExpressoQuedaService
// elegíveis = protocoladas que foram resolvidas (deferida/indeferida/em_analise) no período
// expressas = viability_decisions.flow='expresso' (decided_by_user_id IS NULL)
$expressas = ViabilityDecision::query()
    ->where('flow', 'expresso')
    ->whereBetween('decided_at', [$f->de(), $f->ate()])
    ->count();
// taxa = expressas / elegíveis; meta via Settings::get('relatorios.expresso.meta_taxa')
```

### Download assinado do export assíncrono (espelha TvlDocumentController)

```php
return URL::temporarySignedRoute(
    'gestao.relatorios.download',
    now()->addMinutes((int) config('sile.relatorios.export.download_ttl_minutos', 10)),
    ['export' => $exportId],
);
```

### `<ExportMenu>` no frontend (irmão de `table-toolbar`/`per-page-select`)

```tsx
// monta dropdown CSV/XLSX/PDF apontando para {url do index}?formato={fmt}&...paramsAtuais (do useServerTable)
// Qualquer tela com useServerTable+DataTable ganha export adicionando <ExportMenu> + branch ?formato= (~3 linhas) no controller.
```

## State of the Art

| Abordagem antiga | Abordagem atual | Impacto |
|------------------|-----------------|---------|
| `box/spout` (arquivado) | `openspout/openspout` v5 | Fork mantido; API `new Writer()` + `Row::fromValues()` (sem `WriterEntityFactory` da v3/v4) |
| `maatwebsite/excel` (RN-009 da HU sugere) | `openspout` direto | Memória constante; CONTEXT sobrescreve a sugestão da HU |
| echarts bundle cheio (`import echarts`) | `echarts/core` + `echarts.use([...])` | Bundle só com o que usa (~150KB vs ~1MB) |
| `echarts-for-react` | wrapper próprio sobre `echarts/core` | Evita bug Vite 8 + React 19; controle total |
| Inertia SSR via Node server | `@inertiajs/vite` (SSR no dev Vite) | Componentes que tocam DOM precisam de mount client-only |

**Desatualizado/evitar:** `box/spout`; `WriterEntityFactory::createXLSXWriter()` (API v3/v4 — não existe na v5); `echarts-for-react` na stack atual; `next-transpile-modules`/`transpilePackages` (são específicos de Next.js — irrelevantes no Vite, que consome o ESM do echarts direto).

## Validation Architecture (Nyquist)

Cada CA BDD das 11 HUs vira feature test PHPUnit (`tests/Feature/Relatorios/`). Estratégia por dimensão:

### Dimensão 1 — Anti-fachada (PRIORITÁRIA, CA-03 do CONTEXT)
- **Indicador degrada honesto sem dado**: dado banco vazio (ou recorte sem registros), `taxas()`/`porStatus()`/série temporal retornam `null`/"indisponível", **nunca 0% apresentado como real nem número inventado**. Teste: asserta `null`/flag de indisponível, não um valor fabricado.
- **Número sempre real sobre o banco**: teste com N processos conhecidos (factory) afirma que o indicador bate exatamente com a contagem real (ex.: 3 deferidas / 5 decididas → 60.0).
- **Feriados sem lista oficial**: `BusinessDeadlineCalculator` com `holidays` vazio conta dias úteis sem feriados E expõe a ressalva (flag/aviso) — teste afirma a ressalva visível, nunca um feriado assumido.
- **Delta de KPI sem série histórica**: dashboard não emite "+X%" — teste afirma ausência de delta inventado (precedente Fase 2.4).

### Dimensão 2 — Export reflete o conjunto filtrado (RN-005 / CA-05)
- Mesma listagem com filtros A vs B → arquivos exportados contêm **exatamente** as linhas do filtro (contagem e ordenação). Teste compara linhas do export com `filtered()->get()`.
- **Retrofit anti-regressão**: testes atuais de `AuditoriaController::export` e `ProcessoController::exportarCsv` permanecem verdes após migrar para o componente único (mesmas colunas/filename/conteúdo/`personalData`).
- **3 formatos**: CSV/XLSX/PDF do MESMO `ReportDefinition` produzem os mesmos dados (paridade). XLSX: ler de volta com o Reader do openspout e afirmar linhas. PDF: afirmar `%PDF` no início + "Total de registros: N" no fim (CA-07/RN-010). CSV: afirmar cabeçalho + linhas.

### Dimensão 3 — Assíncrono acima do limiar (RN-006 / CA-06)
- `Queue::fake()`: abaixo do limiar → resposta sync (download), `assertNothingPushed`. Acima → `GerarExportacaoJob` despachado, tela não trava. Teste do `handle()` do Job: grava no disco não-público (`Storage::fake`) + notifica (`Notification::fake`/ledger) + URL assinada.
- `failed()` do Job audita a falha (RN-002), nunca silenciosa.

### Dimensão 4 — Auditoria/meta-auditoria (RN-002/RN-008)
- Toda consulta de relatório e toda exportação geram linha em `activity_log` com usuário/tela/filtros/formato/volume e `personalData` quando há PII (espelha `AuditoriaController`). Teste: spy/contagem de `AuditService` ou assert na `activity_log`.

### Dimensão 5 — Permissão (RN-003 / CA-04)
- Sem `consultar-relatorios` → 403 auditado (ponto único `bootstrap/app.php`). Visão nominal (HU-130) só com `relatorios.produtividade.nominal`; analista vê só o próprio recorte. Teste por perfil (admin/gestor/analista/cidadão).

### Dimensão 6 — Tempo por etapa correto (HU-129 RN-004/RN-005)
- Cenário sintético com datas conhecidas atravessando fim de semana/feriado: `elapsedBusiness` desconta sábado/domingo/feriado. Teste afirma o número de horas úteis exato (prova que a distorção "+48h fim de semana / +24h feriado" do legado some).
- Tempo por etapa: processo com transições conhecidas → durações por etapa batem com o diff esperado; intervalos `em_pendencia` descontados da análise.

### Dimensão 7 — HU-145 captura sem regressão
- Encaminhamento à análise grava N linhas em `request_fall_reasons` (uma por CNAE caído) com `tipo_gatilho`/`dimensao`/`motivo` estruturados (nunca texto livre). Suíte das Fases 9/10 permanece verde (rede anti-regressão). Spy do motor inalterado.
- Drill-down: motivo → lista de processos (via `ProcessoQueryService::filtered`) → divergências (`analysis_divergences`).

### Mecânica de suíte
- `composer test` = 2 processos (SQLite ~1315 + 29 @group postgis). Cobrir SQL PostgreSQL-específico com `@group postgis` quando necessário; preferir agregação portável.
- Seeds dev (W6): `RelatoriosDevSeeder` produz dados pelo FLUXO REAL (driver-aware, roda em SQLite), idempotente; comando de evidência (ex.: `relatorios:exportar`) gera um arquivo real; pruning de retenção no scheduler (`routes/console.php`, padrão Fase 3.1).

## Open Questions / Pontos de decisão remanescentes

1. **Critério de contagem CNAE/risco (HU-125/126)** — por CNAE principal (`is_primary`) ou todos os CNAEs? Risco da versão vigente ou da decisão? *Recomendação:* principal + versão vigente, "não classificado" honesto. **Confirmar com analista-negocio/SEDUR.**
2. **Permissão de feriados (HU-137)** — reusar `manter-parametros` ou criar `manter-feriados`? *Recomendação:* `manter-feriados` própria (cadastro distinto), some à contagem 27→28+. Decisão do planner.
3. **`relatorios.tempo.etapas` (json)** — declarar explicitamente quais transições compõem cada etapa só se a SEDUR exigir customização; default no código. **Pendência SEDUR** (degradação: default honesto).
4. **Layout/colunas oficiais dos relatórios SAPS** (Tempo de Emissão de TVL, Sedes de Escritório Virtual) — **pendência SEDUR**; entregar com colunas razoáveis derivadas dos campos reais, ajustáveis.
5. **Metas/janela da taxa expressa (HU-145 RN-004)** — parâmetro "meta não definida" até a SEDUR fixar (degrada honesto, não bloqueia).
6. **Política de produtividade nominal (HU-130)** — quem vê ranking nominal; default conservador (anonimizado) registrado, **confirmação SEDUR/DPO**.
7. **Contrato exato do `ReportFormatExporter`** (path-based unificado vs híbrido stream/path) — recomendação no Pattern 2; decisão final do planner ao ler os testes atuais dos 2 CSVs.
8. **LGPD por coluna sensível na exportação (RN-007)** — quais colunas exigem permissão específica → **DPO**. Default: `cpf_masked` salvo permissão de PII (precedente Fase 12).

## Key files to touch (mapa para o planner)

**Reusar/estender (não recriar):**
- `app/Services/Analise/ProcessoQueryService.php` — Builder filtrado + `CATEGORIAS`/`GRUPOS_STATUS`; base do `ReportFilters` e do drill-down HU-145.
- `app/Services/Expresso/BusinessDeadlineCalculator.php` — estender (dias úteis/feriados + `elapsedBusiness`).
- `app/Services/Analise/AnalysisSlaService.php` — referência de cálculo puro parametrizado.
- `app/Services/Analise/TvlPdfService.php` — padrão dompdf + disco não-público + auditoria.
- `app/Http/Controllers/Gestao/TvlDocumentController.php` — padrão `temporarySignedRoute` + streaming de disco não-público.
- `app/Http/Controllers/Gestao/AuditoriaController.php` + `Gestao/ProcessoController.php` — os 2 CSVs a consolidar (anti-regressão).
- `app/Http/Controllers/Gestao/DashboardController.php` — estender com KPIs reais (sem delta inventado).
- `app/Jobs/DecidirFluxoExpressoJob.php` — padrão de Job resiliente (tries/timeout/backoff/`failed()` auditado).
- `app/Services/Comunicacao/NotificationDispatcher.php` — notificar export pronto (EP11).
- `app/Support/Audit/AuditService.php` — `log(...)` com result/rulesVersion/personalData.
- `app/Support/Settings.php` + `config/sile.php` + `database/seeders/ParameterSeeder.php` — parâmetros HU-014.
- `app/Services/Expresso/FluxoExpressoService.php` (`encaminharAnalise`) — captura estruturada HU-145.
- `app/Services/Solicitacao/ResolvedViability.php` + `app/Enums/TipoGatilho.php` + `app/Enums/Fluxo.php` — fonte do gatilho da queda.
- `resources/js/components/geo/{map-imovel,mapa-section}.tsx` — padrão SSR-safe client-only (modelo do wrapper ECharts).
- `resources/js/components/ui/data-table/{use-server-table,table-toolbar,per-page-select}.tsx` + `data-table.tsx` — base do `<ExportMenu>`.
- `resources/js/components/ui/kpi-card.tsx`, `resources/js/layouts/gestao-layout.tsx`, `resources/js/components/app/command-search.tsx` — dashboard/nav/Cmd+K.
- `database/seeders/RolesAndPermissionsSeeder.php` + `routes/gestao.php` (dono único) + `routes/console.php` (pruning).

**Criar:** ver "Estrutura de diretórios" (Architecture Patterns) + migrations (índices aditivos; `request_fall_reasons`; `holidays`).

## Technical risks (resumo priorizado)

| Risco | Severidade | Mitigação |
|-------|-----------|-----------|
| openspout exige PHP 8.4 vs `composer.json ^8.3` | HIGH | bumpar para `^8.4`; verificar `composer test` verde |
| `echarts-for-react` crash Vite 8 + React 19 | HIGH | wrapper próprio sobre `echarts/core` |
| Índices ausentes (período/decisão/transições) | HIGH | migration aditiva driver-aware na W1 |
| Refactor dos 2 CSVs quebra contrato/testes | MEDIUM | ler testes antes; preservar contrato observável; CSV sync mantém streaming |
| HU-145 capture regride Fases 9/10 | MEDIUM | aditivo na transação existente; suíte 9/10 antes/depois |
| SQL PostgreSQL-específico falha em SQLite | MEDIUM | agregação portável ou ramo `@group postgis` |
| GROUP BY + orderBy herdado | MEDIUM | `->reorder()` antes de agregar |
| Dupla contagem CNAE/risco | MEDIUM | contar por CNAE principal; denominador explícito |
| Contagens de seeder-tests (85/27) | MEDIUM | dono único atualiza na W1 |

## Sources

### Primary (HIGH)
- Código do repositório SILE (Fases 1–12): serviços, controllers, migrations, seeders, componentes React citados acima — fonte de verdade dos padrões reais.
- `.planning/phases/15-relatorios-e-indicadores/15-CONTEXT.md` — decisões fixadas.
- `.planning/STATE.md` — baseline (85 parâmetros / 27 permissões; suíte 1315 SQLite + 29 postgis; flows `expresso`/`analise_tecnica`).
- HUs EP15: `docs/SILE_HUs_Completas_MD/EP15-Relatórios-e-Indicadores/HU-122..131.md`, `HU-145`; HU-137 em `EP02-Administracao`.
- OpenSpout via Context7 (openspout/openspout, atualizado ~2 semanas): API v5 (`new Writer()`, `openToFile`, `Row::fromValues`, `Cell`, `Options`, `openToBrowser`, requisito PHP 8.4/8.5, memória <3MB).
- npm `echarts` 6.1.0 (publicado 2026-05-19); `echarts-for-react` 3.0.6 peer deps + issue #619 (Vite 8 + React 19 crash, aberta 2026-03-19).
- `hustcc/echarts-for-react` README — padrão de import tree-shakeable `echarts/core` + `echarts.use([...])`.

### Secondary (MEDIUM)
- Packagist openspout (v5.7.2, 2026-05-29) — confirmação de versão estável.
- Discussões SSR de canvas charts (Next.js) — confirmam o modo de falha (DOM/canvas no import) que o mount client-only resolve; transpilePackages é específico de Next, não se aplica ao Vite.

### Verificações locais executadas
- `php -v` → PHP 8.5.4; `composer.json` → `"php": "^8.3"`; `git check-ignore .planning` → TRACKED.
- Schemas confirmados por leitura das migrations (índices presentes/ausentes).
- `RolesAndPermissionsSeederTest` (27 permissões / 4 roles) e `ParameterSeederTest` (85 parâmetros) confirmados.

## Metadata

**Confidence breakdown:**
- Standard stack (openspout/echarts versões+API): HIGH — doc oficial + Context7 + npm.
- Padrões a reusar (services/export/charts/audit/job): HIGH — lidos no código real.
- Modelo de dados/índices: HIGH — migrations lidas diretamente.
- HU-145 fonte do gatilho (`consulta_array`): MEDIUM — shape conhecido; o ponto exato do `TipoGatilho` no resultado da consulta o executor confirma ao implementar.
- Pendências SEDUR/DPO (layout SAPS, metas, produtividade, LGPD por coluna): LOW — externas, degradam honesto.

**Research date:** 2026-06-15
**Valid until:** ~2026-07-15 (stack estável; revalidar versões de echarts/openspout se a fase começar depois)
