# Phase 15: Relatórios e Indicadores - Research

**Researched:** 2026-06-15
**Domain:** Camada de leitura/análise gerencial (agregação SQL + exportação multiformato + dashboard com gráficos) sobre os dados reais das Fases 1–12
**Confidence:** HIGH (padrões de código internos verificados no repositório; APIs externas confirmadas em doc oficial/composer.json/npm)

## Summary

A Fase 15 é uma camada de **leitura** sobre dados que já existem. Quase nada aqui é "tecnologia nova de verdade": 9 dos 11 indicadores são `GROUP BY`/`count`/`avg` sobre tabelas já modeladas, e os três pilares de execução (export streaming, job de fila com download assinado, KPI gated por permissão) já têm implementações de referência no código — `AuditoriaController::export` (CSV streaming), `DecidirFluxoExpressoJob` (fila tries/timeout/backoff), `TvlDocumentController` (URL assinada + disco não-público) e `Gestao\DashboardController` (KPI real gated). O trabalho de planejamento é, sobretudo, **replicar esses padrões com disciplina** e **abstrair o contrato de exportação** (`ReportDefinition`/`ReportExporter` + drivers) para que a dívida transversal da HU-131 seja paga uma vez e herdada por todas as telas.

O terreno genuinamente novo se resume a duas dependências aprovadas e a três ajustes de domínio. **openspout** (XLSX por streaming, baixa memória) tem API estável e pequena (`Writer`/`Options`/`Row::fromValues`/`openToFile`); a única decisão real é a **versão** por causa do PHP (ver Standard Stack — recomendo `^4.0`). **Apache ECharts 6** entra como wrapper próprio client-only, espelhando exatamente o padrão SSR-safe do `MapaSection`/`MapImovel` da Fase 4 (`lazy` + flag `mounted` + skeleton), com import tree-shakeable por `echarts/core`. Os três ajustes de domínio são: (1) estender o `BusinessDeadlineCalculator` para dias úteis + feriados (HU-137) — atenção: HU-129 precisa de uma operação **nova** (duração decorrida entre dois instantes), não só do `dueAt` forward que já existe; (2) o **cadastro de feriados** (HU-137) como tabela auditada/parametrizável; (3) a **captura estruturada do motivo/gatilho de queda** (HU-145), aditiva, dentro de `FluxoExpressoService::encaminharAnalise`.

O risco dominante não é técnico, é de **fachada**: todo indicador degrada honesto quando falta dado (sem delta histórico, "zona indisponível → bairro", feriado não inventado, gatilho null quando o motor degradou). O teste CA-03 anti-fachada é o mais importante da fase, e a Validation Architecture abaixo dedica uma dimensão inteira a prová-lo.

**Primary recommendation:** Pagar a HU-131 primeiro como contrato único (`ReportDefinition` + `ReportExporter` + 3 drivers, sendo CSV a consolidação dos 2 streamings existentes e XLSX o único código novo via openspout `^4.0`); construir os serviços de relatório espelhando `ProcessoQueryService` (Builder + `when()` + agregação SQL, nunca loop PHP); adicionar **uma migration aditiva de índices** (`protocoled_at`, `viability_decisions.decided_at/flow/outcome`, `transitions(viability_request_id, created_at)`) porque as colunas de data/decisão dos relatórios **não estão indexadas hoje**; e tratar a notificação de "export pronto" como Notification standalone (database+mail), **não** pelo `NotificationDispatcher` (que é process-bound).

## Standard Stack

### Núcleo (já instalado — reusar)
| Lib | Versão | Papel na Fase 15 | Por que é o padrão |
|---|---|---|---|
| `laravel/framework` | v13 | Builder Eloquent, `Cache::remember`, `Storage`, `URL::temporarySignedRoute`, fila, scheduler | Stack travada do projeto |
| `barryvdh/laravel-dompdf` | ^3.1 | Driver PDF do export (HU-131 RN-010) | Já usado e validado no `TvlPdfService` |
| `inertiajs/inertia-laravel` + `@inertiajs/react` | v3 / ^3.3 | Páginas dos relatórios e dashboard (SSR ativo via `@inertiajs/vite`) | Stack travada |
| `spatie/laravel-activitylog` | ^5.0 | Trilha de auditoria de toda consulta/exportação (RN-002/RN-008) via `AuditService` | Espinha de auditoria do projeto |
| `spatie/laravel-permission` | ^8.0 | Gates `consultar-relatorios`, `relatorios.produtividade.nominal` | Padrão de permissão do projeto |
| `predis/predis` | ^3.5 | Cache (TTL do dashboard) e fila do job de export | Já instalado |

### Dependências NOVAS (aprovadas no CONTEXT — exigem `require`)

**`openspout/openspout` (composer)** — driver XLSX por streaming.

- **Última versão:** v5.7.2 (branch 5.x). **v4.x** mais recente também ativa.
- **DECISÃO DE VERSÃO (ponto de atenção crítico):**
  - O ambiente roda **PHP 8.5.4** (`php -v` confirmado), mas o `composer.json` do projeto declara `"php": "^8.3"`.
  - **openspout v5** exige `php: ~8.4.0 || ~8.5.0` → instalaria no runtime 8.5, mas **quebra a promessa `^8.3`** do projeto (composer resolve pela plataforma real, então `composer require openspout/openspout` puxaria a v5 e o projeto deixaria de suportar 8.3 de fato).
  - **openspout v4** exige `php: ~8.3.0 || ~8.4.0 || ~8.5.0` → **compatível com o `^8.3` declarado E com o runtime 8.5**.
  - **Recomendação: `composer require "openspout/openspout:^4.0"`** (mantém a coerência do `^8.3`). A API de escrita streaming (`Writer`/`Options`/`Row`/`Style`) é **idêntica** entre v4 e v5 — não há ganho funcional em forçar a v5 para este uso. Só subir para v5 se a equipe decidir **explicitamente** elevar o piso de PHP para `^8.4` (aí bumpar o `"php"` do `composer.json` no mesmo commit).
- **Extensões PHP exigidas:** `ext-dom`, `ext-zip`, `ext-xmlreader`, `ext-libxml`, `ext-filter`, `ext-fileinfo` (todas padrão na imagem PHP do projeto; conferir no `Dockerfile` como item de checklist).
- **Memória:** < 3 MB mesmo em arquivos grandes (streaming real) — encaixa no `GerarExportacaoJob`.

**`echarts` (npm)** — gráficos do dashboard/série temporal.

- **Última versão:** 6.1.0 (npm `latest`, 2026-05). **Recomendação: `echarts@^6.1`**.
- Framework-agnóstico (não depende de versão do React) → compatível com React 19.2 do projeto.
- ECharts 6 traz **dark mode nativo** e **troca dinâmica de tema** (`chart.setTheme('dark'|'default')`) sem destruir a instância — relevante para console escuro × portal claro do DS TailAdmin.
- **Sem wrapper de terceiros.** O CONTEXT decidiu wrapper próprio em `resources/js/components/ui/chart/`. Import tree-shakeable por `echarts/core` (ver Code Examples) mantém o bundle em ~150 KB vs ~1 MB do pacote cheio. Não usar `echarts-for-react` (abandona o controle de tema/SSR e infla o bundle).

### Alternativas consideradas (e por que NÃO)
| Em vez de | Poderia usar | Trade-off / veredito |
|---|---|---|
| openspout | `maatwebsite/excel` (citado na HU-131 RN-009) | Carrega PhpSpreadsheet (memória alta, sem streaming real para datasets grandes). CONTEXT já descartou. |
| openspout | `phpoffice/phpspreadsheet` puro | Mesmo problema de memória; mais verboso. Descartado. |
| ECharts (wrapper próprio) | `echarts-for-react` | SSR/React 19 frágil, bundle cheio, perde padronização de tema do DS. Descartado pelo CONTEXT. |
| Materialized view / tabela-resumo | — | Desnecessário no volume de Salvador; `GROUP BY` + `Cache::remember(TTL=300s)` basta. Deferido no CONTEXT. |

**Instalação (executor, na wave de fundação):**
```bash
composer require "openspout/openspout:^4.0"
npm install echarts@^6.1
```

## Architecture Patterns

### Estrutura de diretórios alvo (espelha a organização existente)
```
app/Services/Relatorios/
├── ReportFilters.php                  # Value Object (período/setor/zona/CNAE/categoria/analista)
├── IndicadoresViabilidadeService.php  # HU-123/124/125/126/127/128
├── TempoAnaliseService.php            # HU-129 (reusa BusinessDeadlineCalculator estendido)
├── ProdutividadeAnalistaService.php   # HU-130
├── ExpressoQuedaService.php           # HU-145
└── Export/
    ├── ReportDefinition.php           # readonly: titulo, colunas, Builder, filtros, logName, event, personalData, arquivoBase
    ├── ReportFormatExporter.php       # interface (1 driver/formato)
    ├── CsvExporter.php                # consolida os 2 streamings CSV existentes
    ├── XlsxExporter.php               # openspout (código novo)
    ├── PdfExporter.php                # dompdf + Blade genérico (rodapé "Total de registros: N")
    └── ReportExporter.php             # orquestra sync (streaming) OU async (Job) acima do limiar

app/Jobs/GerarExportacaoJob.php        # espelha DecidirFluxoExpressoJob
app/Models/Holiday.php                 # HU-137 (cadastro auditado)
app/Services/Expresso/BusinessDeadlineCalculator.php  # ESTENDER (dias úteis + feriados)

resources/js/components/ui/chart/      # wrapper ECharts client-only (espelha MapaSection)
├── chart.tsx                          # <Chart option={...}/> client-only (lazy + mounted)
└── echarts-core.ts                    # echarts.use([...]) tree-shake central

resources/js/components/ui/data-table/export-menu.tsx  # dropdown CSV/XLSX/PDF (irmão de table-toolbar)
resources/js/pages/gestao/relatorios/  # páginas dos relatórios
```

### Pattern 1: Serviço de relatório route-free (espelhar `ProcessoQueryService`)
**O quê:** cada serviço expõe métodos que retornam dados agregados a partir de um `ReportFilters`, montando o Builder com `when()` (filtro só entra quando informado) e agregando em SQL com `groupBy` + `selectRaw`/`DB::raw`.
**Quando:** HU-122 a HU-130 e HU-145.
**Referência real:** `app/Services/Analise/ProcessoQueryService.php` (helpers `valor/inteiro/data/categoria`, `CATEGORIAS`, `GRUPOS_STATUS`, `whereLike(caseSensitive:false)`) e `ProcessoQueryService::visaoSetor` (exemplo de `GROUP BY` com `join` + `DB::raw('count(*) as total')` + `->map()` tipado).
**Regra de ouro:** agregação SEMPRE em SQL (`count`/`avg`/`sum`/`group by`), NUNCA loop PHP sobre coleção carregada.

### Pattern 2: Contrato único de exportação (HU-131 — coração da fase)
**O quê:** `ReportDefinition` (readonly DTO) carrega o **MESMO Builder filtrado da tela** (RN-005, zero filtro duplicado) + metadados (título, colunas, logName/event de auditoria, `personalData`, base do nome do arquivo). `ReportExporter` compara `count()` com `relatorios.export.assincrono_limiar_linhas`: abaixo → streaming síncrono pelo driver; acima → despacha `GerarExportacaoJob` (RN-006). Cada driver implementa `ReportFormatExporter`.
**Quando:** toda exportação (relatórios novos + retrofit das telas antigas).
**Referência real:** CSV em `app/Http/Controllers/Gestao/AuditoriaController.php::export` e `ProcessoController::exportarCsv`; PDF em `app/Services/Analise/TvlPdfService.php`; async em `DecidirFluxoExpressoJob` + `TvlDocumentController` (download assinado).
**Integração na tela:** controller existente ganha um branch `?formato=` (~3 linhas) que monta o `ReportDefinition` e delega ao `ReportExporter` — exatamente como `AuditoriaController::index` faz `if formato==='csv' return $this->export(...)`.

### Pattern 3: Wrapper de gráfico client-only SSR-safe (espelhar `MapaSection`)
**O quê:** o ECharts acessa o DOM (`echarts.init`) e quebra no SSR do Inertia v3. Montar só no cliente com `lazy()` + dynamic import + flag `mounted` + skeleton de mesma altura — padrão **idêntico** ao `resources/js/components/geo/mapa-section.tsx` (Pitfall 9 documentado lá).
**Quando:** todo gráfico do dashboard/série temporal.
**Detalhes:** `echarts/core` + registro explícito (`echarts.use`) dos charts/components/renderers usados; `CanvasRenderer` (default, performático); `ResizeObserver` para responsividade; `chart.dispose()` no unmount; tema escuro/claro via `setTheme` (ECharts 6) sincronizado ao `dark` do Tailwind (a app usa classe `dark` no `<html>`, não `prefers-color-scheme` puro — ver Pitfall 5).

### Pattern 4: Job de export assíncrono (espelhar `DecidirFluxoExpressoJob`)
**O quê:** `GerarExportacaoJob` com `tries/timeout/backoff/onQueue` lidos de `config('sile.relatorios.job.*')`, carrega só os parâmetros (filtros serializáveis + classe da definition + id do usuário), gera o arquivo no disco não-público, dispara a Notification de "pronto" e implementa `failed()` que **audita a falha** (RN-002, nunca silenciosa).
**Download:** `URL::temporarySignedRoute` + `Storage::disk($disk)->download()` com middleware `signed` — cópia de `TvlDocumentController::downloadUrl/download`.

### Pattern 5: Seam de prazo HU-137 (estender `BusinessDeadlineCalculator`)
**O quê:** hoje o calculator só faz **forward** (`dueAt(from, hours)` = `addHours`) e `isOverdue`. HU-137/HU-129 exigem DUAS coisas:
1. `dueAt` passar a **pular fins de semana + feriados** (afeta SLA da fila, sem tocar call sites — o seam já está pronto, conforme docblock do arquivo).
2. **NOVA operação para HU-129:** duração **decorrida** em tempo útil entre dois instantes (ex.: `protocoled_at → decided_at` descontando fins de semana/feriados). Isso NÃO é `dueAt`; é um método novo (ex.: `businessDurationBetween(from, to): CarbonInterval|int`). É a causa-raiz da distorção do legado (19 dias reportados vs 42h reais): medir por etapa **com a regra de prazo correta**.
**Como:** injetar um `HolidayProvider` (interface) no calculator, lendo a tabela de feriados com cache; lista vazia = dias úteis sem feriados + **ressalva honesta visível** (nunca feriado inventado).

### Anti-patterns a evitar
- **Loop PHP para agregar** (ex.: `->get()->groupBy()->map(count)`): mata performance e contradiz o padrão. Use SQL.
- **Materialized view "preventiva":** complexidade sem necessidade no volume atual (deferido).
- **Reimplementar export por tela:** viola RN-009; tudo passa pelo `ReportExporter`.
- **Inventar delta histórico no KPI** ("+12%" sem janela persistida): o `KpiCard` já trata `delta` como opcional ("variação real, nunca inventada"); degradar sem delta.
- **Notificar export via `NotificationDispatcher`:** ele exige `ProcessNotification` ligado a um `viabilityRequestId` — export não é processo. Usar Notification standalone (ver Open Questions).

## Don't Hand-Roll

| Problema | Não construir | Usar | Por quê |
|---|---|---|---|
| Escrever XLSX | Gerador de OOXML/ZIP próprio | `openspout` `Writer` streaming | Formato OOXML é complexo; openspout faz em <3 MB |
| CSV streaming | Novo `fputcsv` por tela | `CsvExporter` consolidando o padrão de `AuditoriaController::export` | RN-009 (componente único); evita 2 cópias divergentes |
| PDF de relatório | HTML→PDF manual | `barryvdh/laravel-dompdf` (já no `TvlPdfService`) | Já validado, com disco não-público + URL assinada |
| Gráficos | SVG/Canvas próprio | `echarts/core` (wrapper próprio fino) | Decisão do usuário; ECharts cobre tudo (resize, tema, série temporal) |
| Download seguro de arquivo gerado | Rota pública para o disco | `URL::temporarySignedRoute` + `Storage::download` (padrão `TvlDocumentController`) | LGPD: arquivo pode conter PII; nunca URL pública |
| Job resiliente | `dispatch` cru | `GerarExportacaoJob` espelhando `DecidirFluxoExpressoJob` | tries/timeout/backoff/`failed()` auditado já provados |
| Contagem de dias úteis | Lógica ad-hoc por relatório | `BusinessDeadlineCalculator` estendido + `Holiday` | Centraliza a regra (HU-137) num único seam |
| Leitura de parâmetro | `config()`/`env()` direto | `App\Support\Settings::get(chave, fallback_config)` | Resolução banco→config→default, cache e fallback sem banco |
| Auditoria de consulta/export | `activity()` cru | `App\Support\Audit\AuditService::log(...)` | Assinatura única (result/rulesVersion/personalData) |

**Key insight:** quase todo "problema novo" desta fase tem um **gêmeo já implementado** no repositório. O valor do plano é mapear cada tarefa ao seu gêmeo e reusar o padrão — não reinventar.

## Common Pitfalls

### Pitfall 1: Colunas de data/decisão dos relatórios NÃO estão indexadas
**O que acontece:** filtros por período (HU-123) e agregações de taxa/tempo varrem a tabela inteira.
**Causa-raiz (verificada nas migrations):** `viability_requests.protocoled_at` **não tem índice**; `viability_decisions` só indexa `viability_request_id` (unique) e `tvl_product_number` (unique) — **`decided_at`, `flow`, `outcome`, `decided_by_user_id` sem índice**; `viability_request_transitions` só tem o FK (sem índice em `created_at`/`to_status`). O CONTEXT afirma "colunas já indexadas: protocoled_at, decided_at" — isso está **incorreto** à luz das migrations.
**Como evitar:** uma **migration aditiva** na wave de fundação adicionando índices em `protocoled_at`, `viability_decisions.decided_at`, `(flow, outcome)`, `decided_by_user_id`, e `viability_request_transitions (viability_request_id, created_at)`. Aditiva, sem tocar colunas existentes (regra Laravel: alterar coluna exige repetir todos os atributos).

### Pitfall 2: PostgreSQL é case-sensitive; SQLite não
**O que acontece:** filtro de texto (nome/CNAE/bairro) funciona no teste SQLite e falha em produção pgsql.
**Como evitar:** usar `whereLike(..., caseSensitive: false)` (já é o padrão do `ProcessoQueryService`). Nunca `where('col','like',...)` cru.

### Pitfall 3: HU-129 mede duração, o seam atual só faz prazo forward
**O que acontece:** tenta-se reusar `dueAt` para medir tempo decorrido e o número sai errado (ou conta calendário, recriando a distorção do legado).
**Como evitar:** adicionar método de **duração entre dois instantes em tempo útil** ao calculator (item explícito no plano). Tempo por etapa = diff entre transições consecutivas (`viability_request_transitions.created_at`) processado por essa nova operação.

### Pitfall 4: openspout `openToFile` precisa de caminho de filesystem real
**O que acontece:** tentar escrever XLSX direto em `php://output` como no CSV, ou em disco remoto (S3) sem caminho local.
**Como evitar:** no **síncrono**, usar `openToBrowser($filename)` (streama ao cliente). No **assíncrono** (Job), `openToFile()` num caminho local do disco (`Storage::disk($disk)->path($rel)` para disco local) e então registrar o arquivo; se o disco-alvo não for local, escrever em `tempnam()` e fazer `Storage::put` depois. XLSX usa arquivos temporários (não é stream puro como CSV).

### Pitfall 5: Tema do ECharts preso ao SO em vez do toggle do DS
**O que acontece:** o gráfico usa `prefers-color-scheme` e ignora o tema do TailAdmin (classe `dark` no `<html>`).
**Como evitar:** o wrapper lê o tema atual da app (classe `dark` / contexto de tema) e chama `chart.setTheme()` quando muda (observar via `MutationObserver` na classe do `<html>` ou pelo mesmo mecanismo de tema já usado no projeto). Confirmar como o dark mode é alternado nos componentes existentes (`dark:` Tailwind) antes de fixar a fonte da verdade do tema.

### Pitfall 6: Job/export "de fachada"
**O que acontece:** o job grava um arquivo vazio/parcial e notifica "pronto", ou a notificação some.
**Como evitar:** `failed()` audita a falha (RN-002); o arquivo só é registrado/baixável após `close()` bem-sucedido; teste que prova que export estourado/erro NÃO gera link válido.

### Pitfall 7: Retrofit dos CSVs quebrando testes existentes
**O que acontece:** ao migrar `AuditoriaController::export`/`ProcessoController::exportarCsv` para o `CsvExporter`, mudam colunas/nome de arquivo/`personalData` e os testes atuais quebram.
**Como evitar:** os testes atuais são a **rede anti-regressão** — preservar colunas, nome do arquivo e a marca `personalData`/HU-101. Migrar 1 controller por vez, rodando o teste do controller a cada passo.

### Pitfall 8: Suíte com 2 bancos (SQLite + @group postgis)
**O que acontece:** rodar só `php artisan test` e achar que está verde, mas o grupo postgis quebrou.
**Como evitar:** rodar `composer test` (executa os 2 processos: `--exclude-group postgis` e depois `--group postgis`). Baseline declarado: ~1315 SQLite + 29 postgis — **verificar fresco** antes/depois.

## Code Examples

### `XlsxExporter` com openspout (streaming, baixa memória)
```php
<?php

namespace App\Services\Relatorios\Export;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;

final class XlsxExporter implements ReportFormatExporter
{
    /** Síncrono: streama direto ao navegador (datasets abaixo do limiar). */
    public function stream(ReportDefinition $definition): void
    {
        $options = new Options();
        $options->SHOULD_USE_INLINE_STRINGS = true; // default; rápido e baixa memória

        $writer = new Writer($options);
        $writer->openToBrowser($definition->fileName('xlsx'));

        $header = Style::default();
        $header->setFontBold();
        $writer->addRow(Row::fromValues($definition->columnLabels(), $header));

        // cursor()/lazy() do Builder filtrado da tela (RN-005): nunca all() em memória
        foreach ($definition->builder()->cursor() as $model) {
            $writer->addRow(Row::fromValues($definition->mapRow($model)));
        }

        $writer->close();
    }

    /** Assíncrono: grava no disco (caminho local) para download assinado posterior. */
    public function writeTo(ReportDefinition $definition, string $absolutePath): void
    {
        $writer = new Writer();
        $writer->openToFile($absolutePath);
        $writer->addRow(Row::fromValues($definition->columnLabels()));

        foreach ($definition->builder()->cursor() as $model) {
            $writer->addRow(Row::fromValues($definition->mapRow($model)));
        }

        $writer->close();
    }
}
```
Fonte: doc oficial openspout 4.x (`docs/documentation.md`) — `Writer`/`Options`/`Row::fromValues`/`Style::setFontBold`/`openToFile`/`openToBrowser`.

### Wrapper ECharts client-only + tree-shake (espelha `MapaSection`)
```ts
// resources/js/components/ui/chart/echarts-core.ts — registro central (tree-shaking)
import * as echarts from 'echarts/core';
import { BarChart, LineChart, PieChart } from 'echarts/charts';
import { GridComponent, TooltipComponent, LegendComponent, TitleComponent } from 'echarts/components';
import { CanvasRenderer } from 'echarts/renderers';

echarts.use([BarChart, LineChart, PieChart, GridComponent, TooltipComponent, LegendComponent, TitleComponent, CanvasRenderer]);

export { echarts };
```
```tsx
// resources/js/components/ui/chart/chart-impl.tsx — inicialização (só no cliente)
import { useEffect, useRef } from 'react';
import type { EChartsOption } from 'echarts';
import { echarts } from './echarts-core';

export function ChartImpl({ option, theme }: { option: EChartsOption; theme: 'dark' | 'light' }) {
    const el = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!el.current) return;
        const chart = echarts.init(el.current, theme === 'dark' ? 'dark' : undefined);
        chart.setOption(option);
        const ro = new ResizeObserver(() => chart.resize());
        ro.observe(el.current);
        return () => { ro.disconnect(); chart.dispose(); };
    }, [option, theme]);

    return <div ref={el} className="h-80 w-full" />;
}
```
```tsx
// resources/js/components/ui/chart/chart.tsx — guarda SSR (Pitfall 9 do MapaSection)
import { lazy, Suspense, useEffect, useState } from 'react';
import type { EChartsOption } from 'echarts';

const ChartImpl = lazy(() => import('./chart-impl').then((m) => ({ default: m.ChartImpl })));

export function Chart(props: { option: EChartsOption; theme: 'dark' | 'light' }) {
    const [mounted, setMounted] = useState(false);
    useEffect(() => setMounted(true), []);
    if (!mounted) return <div className="h-80 w-full animate-pulse rounded-2xl bg-gray-100 dark:bg-white/[0.03]" />;
    return (
        <Suspense fallback={<div className="h-80 w-full animate-pulse rounded-2xl bg-gray-100 dark:bg-white/[0.03]" />}>
            <ChartImpl {...props} />
        </Suspense>
    );
}
```
Fonte: padrão verificado em `resources/js/components/geo/mapa-section.tsx` + doc ECharts 6 (tree-shake `echarts/core` + `echarts.use`).

### Tempo por etapa via transições consecutivas (HU-129) — agregação SQL
```php
// Esboço: durações por etapa derivadas de viability_request_transitions.
// A duração de cada etapa = diff entre transições consecutivas; a média
// por etapa é calculada em SQL, NUNCA em loop PHP. O desconto de fins de
// semana/feriados (HU-137) entra via BusinessDeadlineCalculator::businessDurationBetween.
$stageTimes = ViabilityRequestTransition::query()
    ->selectRaw('from_status, to_status, AVG(...) as media_segundos') // janela/lag por request
    ->whereBetween('created_at', [$filters->from(), $filters->to()])
    ->groupBy('from_status', 'to_status')
    ->get();
```
Nota: em PostgreSQL, usar `LAG(created_at) OVER (PARTITION BY viability_request_id ORDER BY created_at)` para o instante da transição anterior; manter um caminho portável para a suíte SQLite (a Fase 10 já lida com a divisão SQLite/postgis — espelhar). O desconto de tempo útil é aplicado no serviço após obter os pares (from→to, instantes), não na média bruta de calendário.

### Captura estruturada da queda (HU-145) — aditivo em `FluxoExpressoService::encaminharAnalise`
```php
// DENTRO da DB::transaction existente de encaminharAnalise(), APÓS o audit->log.
// Aditivo: nova tabela, nenhuma coluna/comportamento existente alterado.
// Fonte por CNAE: $resolved->por_cnae[*]['consulta_array']['risco']['encaminhamento']
//   -> gatilhos_acionados[0]['codigo'] (TipoGatilho), dimensao_decisiva, motivo.
// $resolved === null (toggle off) OU consolidado pendente: grava 1 linha de
// nível-processo com motivo=$reason e cnae/tipo_gatilho NULL (honesto, sem inventar gatilho).
if ($resolved !== null) {
    foreach ($resolved->por_cnae as $item) {
        if (($item['fluxo'] ?? null) !== Fluxo::Analise->value) {
            continue; // só os CNAEs que efetivamente caíram
        }
        $enc = $item['consulta_array']['risco']['encaminhamento'] ?? [];
        ExpressoQueda::create([
            'viability_request_id' => $request->id,
            'cnae' => $item['cnae'],
            'tipo_gatilho' => $enc['gatilhos_acionados'][0]['codigo'] ?? null,
            'dimensao' => $enc['dimensao_decisiva'] ?? null,
            'motivo' => $enc['motivo'] ?? $reason,
        ]);
    }
} else {
    ExpressoQueda::create([
        'viability_request_id' => $request->id,
        'cnae' => null, 'tipo_gatilho' => null, 'dimensao' => null, 'motivo' => $reason,
    ]);
}
```
Fonte: `app/Services/Expresso/FluxoExpressoService.php` (método `encaminharAnalise`, `$resolved` disponível) + `app/Services/Risco/RiscoClassificationService.php` (shape de `encaminhamento`: `fluxo/dimensao_decisiva/motivo/gatilhos_acionados[]`) + `app/Enums/TipoGatilho.php`.

### Entrada do catálogo de parâmetro (HU-014) — shape exato
```php
// database/seeders/ParameterSeeder.php :: catalog()
'relatorios.export.assincrono_limiar_linhas' => [
    'group' => 'relatorios',
    'type' => 'integer',
    'default_value' => '5000',
    'validation_rules' => ['required', 'integer', 'min:100', 'max:1000000'],
    'description' => 'Acima deste número de linhas a exportação roda em segundo plano',
],
```
Fonte: `database/seeders/ParameterSeeder.php::catalog()` (chave => group/type/default_value(string)/validation_rules(array)/description; `sensitive` para credenciais). Espelhar a chave em `config/sile.php` no bloco `relatorios` (fallback sem banco). Constantes técnicas (`max_linhas`, `chunk`, `pdf.paper`, `disk`, `cache_ttl_segundos`, `job.*`) ficam SÓ em `config/sile.php`, fora do catálogo (precedente [02-02]).

## State of the Art

| Abordagem antiga | Abordagem atual | Quando mudou | Impacto |
|---|---|---|---|
| `box/spout` | `openspout/openspout` (fork mantido) | box/spout abandonado | Usar openspout; namespaces `OpenSpout\` |
| openspout v3 `WriterEntityFactory` | v4/v5 `new Writer(new Options())` + `Row::fromValues` | v4 (breaking) | A doc/exemplos v3 (factory) NÃO valem; usar a API de objeto |
| ECharts: trocar tema = `dispose()`+`init()` | ECharts 6 `setTheme()` dinâmico | ECharts 6 (2025/26) | Troca claro/escuro sem recriar a instância |
| `echarts-for-react` | `echarts/core` + wrapper próprio | tendência atual p/ tree-shake/SSR | Bundle ~150 KB vs ~1 MB; controle de SSR/tema |
| Inertia SSR com servidor Node separado | SSR automático via `@inertiajs/vite` (v3) | Inertia v3 | Confirma necessidade de componentes client-only (ECharts) |

**Obsoleto/evitar:**
- Exemplos openspout com `WriterEntityFactory::createXLSXWriter()` (v3) e `setShouldUseInlineStrings()` (era box/spout): na v4+ é `new Writer($options)` e `$options->SHOULD_USE_INLINE_STRINGS`.
- `maatwebsite/excel` (citado na HU-131 RN-009 como referência): substituído pela decisão openspout no CONTEXT.

## Key Files To Touch (mapa para o planner)

**Criar:**
- `app/Services/Relatorios/{ReportFilters, IndicadoresViabilidadeService, TempoAnaliseService, ProdutividadeAnalistaService, ExpressoQuedaService}.php`
- `app/Services/Relatorios/Export/{ReportDefinition, ReportFormatExporter, CsvExporter, XlsxExporter, PdfExporter, ReportExporter}.php`
- `app/Jobs/GerarExportacaoJob.php`
- `app/Models/Holiday.php` + migration `create_holidays_table` + `HolidayController` (CRUD) + seeder
- `app/Models/ExpressoQueda.php` (ou nome equivalente) + migration `create_*_drop_reasons_table`
- migration aditiva de índices (Pitfall 1)
- `app/Http/Controllers/Gestao/RelatorioController.php` + Notification `ExportacaoPronta` (database+mail)
- Blade genérico de relatório PDF (`resources/views/relatorios/*.blade.php`)
- Front: `resources/js/components/ui/chart/*`, `resources/js/components/ui/data-table/export-menu.tsx`, `resources/js/pages/gestao/relatorios/*`

**Estender (aditivo, anti-regressão):**
- `app/Services/Expresso/BusinessDeadlineCalculator.php` (dias úteis + feriados + duração entre instantes)
- `app/Services/Expresso/FluxoExpressoService.php::encaminharAnalise` (captura HU-145)
- `app/Http/Controllers/Gestao/DashboardController.php` (KPIs do EP15)
- `database/seeders/ParameterSeeder.php` + `config/sile.php` (bloco `relatorios.*`)
- `database/seeders/RolesAndPermissionsSeeder.php` (permissões `consultar-relatorios`, `relatorios.produtividade.nominal`)
- `routes/gestao.php` (rotas dos relatórios + download assinado; **rota estática antes do wildcard**, dono único por wave)
- `routes/console.php` (pruning de retenção dos exports, padrão Fase 3.1)
- `resources/js/components/ui/data-table/use-server-table.ts` (expor os params atuais para o `ExportMenu` montar `?formato=`)

**Retrofit (sem novas rotas, preservando testes):**
- `AuditoriaController::export` e `ProcessoController::exportarCsv` → `CsvExporter`
- listagens Fases 1–2 (CNAEs/usuários/perfis/parâmetros/acessos), 1 controller por plano

## Risks & Decision Points (remanescentes)

1. **Versão do openspout vs PHP `^8.3`** (ALTO) — recomendo `^4.0` (mantém `^8.3`); só `^5.0` se a equipe bumpar o piso PHP. Decisão do planner/usuário no início.
2. **Notificação de export pronto** (MÉDIO) — `NotificationDispatcher` é process-bound (`ProcessNotification.viabilityRequestId()`). Para export, usar Notification standalone via canais `database` (surge no `NotificationCenterController`/tabela `notifications`) + `mail`. NÃO forçar o dispatcher. (CONTEXT diz "via EP11" de forma frouxa — reusar a INFRA de notificação, não o roteador de processo.)
3. **Índices ausentes** (MÉDIO) — migration aditiva obrigatória (Pitfall 1); o CONTEXT subestima isso.
4. **HU-129 duração ≠ dueAt** (MÉDIO) — método novo de duração útil no calculator.
5. **Window function portável** (MÉDIO) — `LAG()` no pgsql; caminho alternativo para SQLite na suíte (espelhar a divisão SQLite/postgis da Fase 10).
6. **Fonte do tema do gráfico** (BAIXO) — confirmar como o dark mode é alternado (classe `dark` no `<html>`) para sincronizar o `setTheme`.
7. **Tabela de queda: nome/colunas e nullability** (BAIXO) — `cnae`/`tipo_gatilho`/`dimensao` nullable (linha de nível-processo quando degradado). Discrição do planner.

### Pendências SEDUR/DPO (escalam, NÃO bloqueiam — degradam honesto)
Lista oficial de feriados municipais (HU-137); zona urbanística oficial (HU-124 → bairro até GIS/Fase 13); metas/janela da taxa expressa (HU-145 RN-004); política de produtividade nominal (HU-130); layout/colunas oficiais dos relatórios SAPS; colunas sensíveis na exportação (RN-007 → DPO).

## Validation Architecture

Estratégia de testes/validação por dimensão (Nyquist). Stack: **PHPUnit** feature tests em `tests/Feature/Relatorios/`, factories, suíte de 2 bancos (`composer test`). Cada CA BDD das HUs → ao menos um feature test. **A Dimensão 1 (anti-fachada) é prioritária — CA-03.**

### Dimensão 1 — Anti-fachada (número sempre real; degradação honesta) [PRIORITÁRIA]
- **Indicador degrada sem inventar:** sem janela histórica, o KPI vem **sem delta** (assert: payload não traz `delta`/`+X%`); dado ausente vira "indisponível/pendente", nunca 0 disfarçado de real.
- **HU-124 zona → bairro:** com zona indisponível, o recorte cai em bairro com ressalva visível (assert do rótulo de degradação).
- **HU-137 feriado não inventado:** lista vazia ⇒ contagem = dias úteis sem feriados + ressalva; nenhum feriado fabricado (assert: cálculo bate com dias úteis puros e a flag de ressalva está presente).
- **HU-145 gatilho honesto:** queda com `$resolved === null` (toggle off) grava motivo de nível-processo com `tipo_gatilho = null` (assert: NÃO há gatilho inventado); queda por gatilho real grava o `TipoGatilho` correto.
- **Export reflete o conjunto filtrado (RN-005):** o arquivo exportado contém **exatamente** as linhas do Builder filtrado (assert: contagem e conteúdo do export == query filtrada; aplicar filtro e provar que linhas fora dele não aparecem).
- **Export falho não vira "pronto":** job que estoura/erra chama `failed()` auditado e NÃO disponibiliza link válido (assert: ausência de arquivo baixável + linha de auditoria `result=falha`).

### Dimensão 2 — Correção das agregações (números certos)
- Taxas de deferimento/indeferimento (HU-127/128) com dataset factory conhecido → valor esperado exato.
- Tempo por etapa (HU-129) com transições factory cravadas → média por etapa esperada; **caso fim de semana/feriado** prova o desconto (anti-distorção do legado).
- Relatórios SAPS (RN-006): Tempo de Emissão de TVL (`protocoled_at→decided_at` com `tvl_product_number` não nulo) e Sedes de Escritório Virtual (`is_virtual_office=true`) com recortes período/setor/analista/categoria.
- Taxa de resposta expressa (HU-145 RN-002): `flow='expresso' & decided_by_user_id IS NULL ÷ elegíveis` com dataset conhecido.

### Dimensão 3 — Conformidade do contrato de exportação (HU-131)
- Os 3 formatos (CSV/XLSX/PDF) geram conteúdo válido: CSV com cabeçalho + linhas; XLSX legível (reabrir com o `Reader` do openspout no teste e conferir linhas); PDF começa com `%PDF` e o rodapé exibe "Total de registros: N" coerente + data/hora + filtros (CA-07/RN-010).
- Limiar assíncrono (CA-06/RN-006): abaixo → resposta de streaming síncrona; acima → `GerarExportacaoJob` despachado (assert com `Queue::fake()`), arquivo no disco não-público, link por `temporarySignedRoute`.
- Retrofit anti-regressão: testes existentes de `AuditoriaController::export` e `ProcessoController::exportarCsv` continuam verdes após migrar ao `CsvExporter` (mesmas colunas/arquivo/`personalData`).

### Dimensão 4 — Auditoria e segurança (RN-002/RN-007/RN-008)
- Toda **consulta** de relatório e toda **exportação** geram linha de auditoria (usuário/tela/filtros/formato/volume) — espelha a meta-auditoria de `AuditoriaController` (`personalData` quando expõe PII).
- Gate de permissão: sem `consultar-relatorios` → 403 auditado (ponto único `bootstrap/app.php`); `relatorios.produtividade.nominal` controla visão nominal (HU-130); analista vê só o próprio recorte.
- LGPD: export minimiza PII (ex.: `cpf_masked`) salvo permissão específica; download só por URL assinada do disco não-público.

### Dimensão 5 — Parametrização sem deploy (HU-014)
- Mudar `relatorios.export.assincrono_limiar_linhas`/`meta_taxa`/`janela_dias` via `Settings` altera o comportamento (assert: limiar novo muda o caminho sync/async; meta nova muda o cálculo da HU-145) — efeito sem deploy, com histórico auditado.
- Teste de contagem do catálogo (`ParameterSeeder`) e de permissões (`RolesAndPermissionsSeeder`) atualizado para o novo total (baseline declarado 85 parâmetros / 27 permissões — **verificar fresco** e somar os novos).

### Dimensão 6 — SSR/Front (gráficos não quebram o SSR)
- Smoke do build (`npm run build` / `tsc --noEmit`) com o wrapper de chart; o componente não importa `echarts` no caminho do servidor (só client-only via `lazy`+`mounted`).
- Verificação manual/visual: tema claro/escuro do gráfico acompanha o DS; resize responsivo.

**Comando de evidência (fresco, completo):** `composer test` (2 processos: SQLite + `@group postgis`) + `vendor/bin/pint --dirty --format agent` + `npm run build`. Recomendado um comando artisan de evidência ponta-a-ponta (ex.: `relatorios:exportar`) que gera um arquivo real de cada formato a partir de seeds (golden/smoke), provando a lógica real de export end-to-end.

## Open Questions

1. **Notificação de export pronto via qual mecanismo?**
   - O que sabemos: existe tabela `notifications` (canal database, criada 2026-06-15), `NotificationCenterController` e rotas `gestao.notificacoes.*`; o `NotificationDispatcher` é process-bound.
   - Lacuna: o CONTEXT diz "via EP11 (NotificationDispatcher)", mas o contrato não serve a export não-processual.
   - Recomendação: Notification standalone `ExportacaoPronta` (canais `database`+`mail`), surgindo no `NotificationCenterController`. Reusar a infra, não o roteador de processo.

2. **Fonte da verdade do tema do gráfico (dark/light).**
   - O que sabemos: DS TailAdmin usa classe `dark` (Tailwind `dark:`).
   - Lacuna: não há ainda um theme context React confirmado para gráficos.
   - Recomendação: o wrapper lê `document.documentElement.classList.contains('dark')` e observa mudanças (`MutationObserver`) ou consome o mesmo mecanismo de tema já em uso; confirmar no `app-shell`/layout antes de cravar.

3. **Window function (`LAG`) na suíte SQLite (HU-129).**
   - O que sabemos: SQLite moderno suporta window functions, mas a Fase 10 já separou caminhos SQLite/postgis.
   - Recomendação: preferir cálculo de duração no serviço (PHP) a partir das transições ordenadas quando o caminho portável for mais simples e testável; usar SQL window só onde o ganho justifica, com teste nos 2 bancos.

4. **Disco do export.**
   - Recomendação: `relatorios.export.disk` = disco **não-público** (mesma guarda anti-`public` do `TvlPdfService`), com pruning de retenção (`relatorios.export.retencao_dias`) no scheduler.

## Sources

### Primárias (HIGH)
- Repositório (verificado): `app/Services/Analise/ProcessoQueryService.php`, `AnalysisSlaService.php`, `TvlPdfService.php`; `app/Services/Expresso/{BusinessDeadlineCalculator,FluxoExpressoService}.php`; `app/Services/Risco/RiscoClassificationService.php`; `app/Services/Comunicacao/NotificationDispatcher.php`; `app/Support/{Settings,Audit/AuditService}.php`; `app/Jobs/DecidirFluxoExpressoJob.php`; `app/Http/Controllers/Gestao/{DashboardController,AuditoriaController,TvlDocumentController}.php`; `app/Enums/TipoGatilho.php`; `app/Services/Solicitacao/ResolvedViability.php`; `routes/gestao.php`; `config/sile.php`; `database/seeders/ParameterSeeder.php`; migrations de `viability_requests`/`viability_decisions`/`viability_request_transitions`/`analysis_records`/`analysis_divergences`/`notifications`; front `resources/js/components/geo/{map-imovel,mapa-section}.tsx`, `ui/data-table/{use-server-table,table-toolbar,per-page-select}.tsx`, `ui/kpi-card.tsx`; `composer.json`, `package.json`, `vite.config.js`, `resources/js/app.tsx`.
- openspout: `composer.json` das branches 4.x e 5.x (raw GitHub) — constraint PHP; doc oficial `docs/documentation.md` 4.x (API Writer/Options/Row/Style).
- Versões: `npm view echarts version` → 6.1.0; Packagist openspout → v5.7.2; `php -v` → 8.5.4.
- HUs: `docs/SILE_HUs_Completas_MD/EP15-Relatórios-e-Indicadores/HU-122..131,145.md`; `EP02/HU-137`.

### Secundárias (MEDIUM)
- WebSearch openspout (laravel-news, nidup.io) — confirmação de streaming/baixa memória.
- WebSearch/handbook ECharts 6 (apache.org) — dark mode dinâmico, `setTheme`, tree-shake `echarts/core`.

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — versões e constraints confirmados em composer.json/npm/packagist; APIs em doc oficial.
- Architecture (reuso de padrões internos): HIGH — todos os arquivos-gêmeos lidos no repositório.
- Pitfalls (índices, duração ≠ dueAt, SSR do chart): HIGH — verificados nas migrations e no código do calculator/MapaSection.
- HU-145 capture shape: MEDIUM-HIGH — fonte do gatilho confirmada no `RiscoClassificationService`; nome exato da tabela/colunas fica a critério do planner.
- Notificação de export: MEDIUM — infra (tabela `notifications` + controller) confirmada; o mecanismo exato é decisão de design.

**Research date:** 2026-06-15
**Valid until:** ~2026-07-15 (openspout/echarts são estáveis; reavaliar se mudar o piso de PHP ou a major do ECharts)
