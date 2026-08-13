---
phase: 15-relatorios-e-indicadores
plan: 05
subsystem: relatorios-indicadores
tags: [hu-129, hu-131, tempo-por-etapa, tempo-util, saps, tvl, escritorio-virtual, export, rn-005, rn-006, anti-fachada]

# Dependency graph
requires:
  - phase: 15-relatorios-e-indicadores (15-04)
    provides: "BusinessDeadlineCalculator::businessDurationBetween (minutos úteis, desconta fim de semana/feriado)"
  - phase: 15-relatorios-e-indicadores (15-02)
    provides: "Contrato de exportação ReportSource/ReportDefinition + ReportFilters (bag serializável, acessores from/to/setorId/analistaId/bairro)"
  - phase: 08-solicitacao-de-viabilidade
    provides: "viability_request_transitions (timeline from/to/created_at) + viability_requests (protocoled_at/is_virtual_office) + viability_decisions (tvl_product_number/decided_at)"
  - phase: 10-analise-tecnica-sedur
    provides: "ProcessoResource (empresa/CNPJ formatado/protocoled_at — PII minimizada na origem)"
provides:
  - "TempoAnaliseService::tempoPorEtapa (tempo médio por etapa em minutos úteis — HU-129)"
  - "TempoAnaliseService::tempoEmissaoTvl (SAPS Tempo de Emissão de TVL em minutos úteis — RN-006)"
  - "TempoAnaliseService::sedesEscritorioVirtual (Builder do recorte is_virtual_office=true — SAPS RN-006)"
  - "TempoAnaliseService::minutosPorEtapa / minutosPorEtapaDoProcesso (helper puro de duração útil por etapa, reusado pelo export)"
  - "TempoAnaliseReportSource (export do detalhamento por processo, sem PII) + EscritorioVirtualReportSource (export do recorte)"
affects: [15-09-dashboard-http, relatorios-saps]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Tempo por etapa = pareamento de transições consecutivas em PHP + businessDurationBetween (desconto de tempo útil NÃO é SQL portável SQLite/pgsql — 15-RESEARCH Open Q #3)"
    - "Varredura escopada por período em SQL + cursor() (memória baixa); só a duração útil de cada par roda em PHP"
    - "Etapa classificada pelo status DEIXADO em cada transição (from_status); reentradas (em_analise após pendência) acumulam na mesma etapa, isolando a pendência da análise"
    - "Helper puro minutosPorEtapa compartilhado entre a agregação (serviço) e o detalhamento por processo (export)"

key-files:
  created:
    - app/Services/Relatorios/TempoAnaliseService.php
    - app/Services/Relatorios/Export/Sources/TempoAnaliseReportSource.php
    - app/Services/Relatorios/Export/Sources/EscritorioVirtualReportSource.php
    - tests/Feature/Relatorios/TempoAnaliseServiceTest.php
    - tests/Feature/Relatorios/TempoAnaliseReportSourceTest.php
  modified: []

key-decisions:
  - "Tempo útil calculado em PHP por par de transições (não LAG SQL): o desconto de fim de semana/feriado não tem expressão portável SQLite+pgsql (Open Q #3); a varredura é SQL escopada + cursor, evitando o anti-pattern de loop-para-contar"
  - "Etapa pelo status DEIXADO (from_status): rascunho→preenchimento, protocolada→espera, aguardando_bap→espera, em_analise→analise, em_pendencia→pendencia; reentradas somam na mesma etapa (isola pendência da análise — HU-129)"
  - "ESPERA agrega protocolada (encaminhamento) E aguardando_bap (Junta/BAP): o objetivo da HU-129 é tornar VISÍVEL o tempo de espera que não é trabalho da SEDUR"
  - "Média/amostras POR PROCESSO que passou pela etapa (não por intervalo): casa com o detalhamento por processo do export e dá amostras honestas"
  - "tempoEmissaoTvl só conta decisões com tvl_product_number IS NOT NULL e decided_at no período; sem registros ⇒ media_minutos null (CA-03), nunca 0 disfarçado"
  - "Sources via app(TempoAnaliseService) reusam o MESMO recorte do serviço (RN-005); TempoAnaliseReportSource personalData=false (só protocolo+tempos), EscritorioVirtualReportSource personalData=true (empresa/CNPJ da ProcessoResource)"

# Metrics
duration: ~75 min
completed: 2026-06-15
---

# Phase 15 Plan 05: Tempo por Etapa (HU-129) + Relatórios SAPS de Tempo Summary

**`TempoAnaliseService` mede o tempo POR ETAPA da timeline em tempo ÚTIL (descontando fim de semana/feriado via `businessDurationBetween` de 15-04) a partir de `viability_request_transitions`, mais os relatórios SAPS Tempo de Emissão de TVL e Sedes de Escritório Virtual — todos exportáveis pelo contrato único (RN-005) e com degradação honesta (sem dados ⇒ média null, CA-03).**

## Performance

- **Duration:** ~75 min
- **Tasks:** 2 (1 commit feat por task)
- **Files created:** 5 (1 serviço + 2 sources + 2 testes)

## Accomplishments

- **Tempo por etapa (HU-129):** `tempoPorEtapa(ReportFilters): array` varre as transições dos processos protocolados no período (SQL escopado + `cursor()`), pareia as consecutivas por processo e soma a duração ÚTIL de cada etapa. Mede 4 etapas — **preenchimento, espera, análise, pendência** — em minutos úteis. É a correção da distorção do legado (conta calendário e mistura etapas): aqui o tempo de espera que não é trabalho da SEDUR fica visível e a pendência é isolada da análise.
- **SAPS Tempo de Emissão de TVL (RN-006):** `tempoEmissaoTvl(ReportFilters): array` mede `protocoled_at → decided_at` em tempo útil das decisões COM número de produto TVL decididas no período; sem registros ⇒ `media_minutos = null`.
- **SAPS Sedes de Escritório Virtual (RN-006):** `sedesEscritorioVirtual(ReportFilters): Builder` recorta `is_virtual_office = true` + filtros comuns, reusado pelo source de export.
- **Export pelo contrato único (RN-005):** `TempoAnaliseReportSource` (detalhamento por processo — minutos úteis por etapa + total, **sem PII**) e `EscritorioVirtualReportSource` (recorte com empresa/CNPJ minimizado pela `ProcessoResource`). Ambos herdam CSV/XLSX/PDF do `ReportExporter`.
- **Anti-fachada provado:** a pendência que cruza sábado/domingo rende 3840 min úteis (não 6720 de calendário) — o desconto está testado; período vazio devolve média null e amostras 0.

## API entregue

```php
// Tempo médio por etapa em minutos ÚTEIS (HU-129). Sem amostras ⇒ media_minutos null.
TempoAnaliseService::tempoPorEtapa(ReportFilters $f): array
//  => ['etapas' => [ ['etapa' => 'preenchimento'|'espera'|'analise'|'pendencia',
//                      'media_minutos' => int|null, 'amostras' => int], ... ]]

// SAPS Tempo de Emissão de TVL em minutos ÚTEIS (RN-006). Sem registros ⇒ media null.
TempoAnaliseService::tempoEmissaoTvl(ReportFilters $f): array
//  => ['media_minutos' => int|null, 'amostras' => int]

// SAPS Sedes de Escritório Virtual — Builder do recorte (RN-006/RN-005).
TempoAnaliseService::sedesEscritorioVirtual(ReportFilters $f): \Illuminate\Database\Eloquent\Builder

// Helper puro de duração útil por etapa (reusado pelo export).
TempoAnaliseService::minutosPorEtapa(\DateTimeInterface $criadoEm, array $transicoes): array // ['etapa' => minutos>=0] só as ocorridas
TempoAnaliseService::minutosPorEtapaDoProcesso(ViabilityRequest $request): array
```

### Classificação exata da etapa por par (from→to)

A etapa é o status **DEIXADO** em cada transição (o intervalo medido é o tempo que o processo passou naquele status, do instante anterior até a transição):

| from_status → to_status (exemplo) | status deixado | etapa | marco inicial do intervalo |
|---|---|---|---|
| rascunho → protocolada | rascunho | **preenchimento** | `viability_requests.created_at` |
| protocolada → em_analise | protocolada | **espera** | transição anterior |
| aguardando_bap → em_analise | aguardando_bap | **espera** (Junta/BAP) | transição anterior |
| em_analise → em_pendencia / deferida / indeferida | em_analise | **analise** | transição anterior |
| em_pendencia → em_analise | em_pendencia | **pendencia** | transição anterior |

Reentradas (em_analise depois de pendência) **somam** na mesma etapa → a pendência fica isolada do tempo de análise. Cada par é convertido em minutos úteis por `BusinessDeadlineCalculator::businessDurationBetween`.

### ReportSources (event / arquivoBase)

| Source | event | arquivoBase | personalData | colunas |
|---|---|---|---|---|
| `TempoAnaliseReportSource` | `exporta-tempo-analise` | `tempo-por-etapa` | `false` | protocolo, preenchimento_min, espera_min, analise_min, pendencia_min, total_min |
| `EscritorioVirtualReportSource` | `exporta-escritorio-virtual` | `sedes-escritorio-virtual` | `true` | protocolo, empresa, cnpj, bairro, protocolado_em, decidido_em, resultado |

## Task Commits

1. **Task 1: TempoAnaliseService (tempoPorEtapa + tempoEmissaoTvl) + TempoAnaliseServiceTest** — `cd3e3ed` (feat)
2. **Task 2: sedesEscritorioVirtual + TempoAnaliseReportSource + EscritorioVirtualReportSource + TempoAnaliseReportSourceTest** — `2d0bb03` (feat)

_Cada task seguiu TDD (RED confirmado pelo motivo certo antes da implementação); teste e implementação ficam no MESMO commit por seguir a lista de arquivos de cada task do plano._

## Files Created

- `app/Services/Relatorios/TempoAnaliseService.php` — tempo por etapa + SAPS (TVL/escritório virtual) + helper puro de duração útil
- `app/Services/Relatorios/Export/Sources/TempoAnaliseReportSource.php` — export do detalhamento por processo (sem PII)
- `app/Services/Relatorios/Export/Sources/EscritorioVirtualReportSource.php` — export do recorte is_virtual_office=true
- `tests/Feature/Relatorios/TempoAnaliseServiceTest.php` — fim de semana (3840 vs 6720), TVL com ruído, período vazio (CA-03)
- `tests/Feature/Relatorios/TempoAnaliseReportSourceTest.php` — RN-005 (só o período), minutos úteis por etapa no mapRow, recorte is_virtual_office

## Decisions Made

- **Duração útil em PHP, não em SQL (LAG):** o desconto de fim de semana/feriado não é portável SQLite+pgsql (Open Q #3 do RESEARCH). A varredura é SQL escopada por período + `cursor()` (memória baixa); o pareamento e o `businessDurationBetween` rodam em PHP — não é "loop para contar o que o SQL faria" (a contagem/recorte segue em SQL).
- **Etapa pelo status DEIXADO (from_status):** simplifica o pareamento e isola naturalmente a pendência da análise quando há reentradas. A ESPERA agrega `protocolada` e `aguardando_bap` para honrar o objetivo da HU-129 (tornar visível a espera que não é trabalho da SEDUR — ex.: Junta/BAP).
- **Média/amostras por processo (não por intervalo):** casa com o detalhamento por processo do export e dá uma amostra honesta (um processo conta uma vez por etapa que percorreu, somando reentradas).
- **`minutosPorEtapa` público e puro:** o MESMO helper alimenta a agregação do serviço e o `mapRow` do export, eliminando lógica duplicada de pareamento.
- **PII minimizada:** o detalhamento de tempo não expõe requerente (`personalData=false`, só protocolo + tempos); o relatório de escritório virtual usa a `ProcessoResource` (CNPJ já formatado) e marca `personalData=true`.

## Deviations from Plan

### Refinamentos (auto-aplicados, dentro do escopo)

**1. [Refinamento] ESPERA inclui `aguardando_bap` além de `protocolada`**
- O texto literal da Task 1 cita "ESPERA/ENCAMINHAMENTO = protocolada→em_analise", mas o `<objective>` exige que "o tempo de espera (ex.: BAP) que não é trabalho da SEDUR fique visível". Mapeei `aguardando_bap → espera` para honrar o objetivo sem criar uma 5ª etapa (as 4 do must_have permanecem). Sem impacto nos testes (a espera continua medida; o BAP, quando existir, soma na mesma etapa).

**2. [Refinamento] PREENCHIMENTO derivado da 1ª transição (rascunho→protocolada) com marco em `created_at`**
- O plano cita "PREENCHIMENTO = created_at→protocoled_at". Implementei via a primeira transição (from=rascunho) com o marco inicial = `viability_requests.created_at` — idêntico quando a transição de protocolo casa com `protocoled_at` (o caso real da máquina de estados), mas unificado no MESMO pareamento das demais etapas (uma fonte só, sem caminho especial).

### Coordenação com trabalho paralelo (sem mudança de escopo)

**3. [Coordenação] Staging seletivo + Pint por caminhos + sem migrate global**
- **Contexto:** o working tree tem trabalho NÃO commitado do usuário (Fase 14 IA/e-mail: `AiConfiguration`/`EmailServer`/migrations; + sidebar/vitest/`RolesAndPermissionsSeeder`/`routes/gestao.php`/`bootstrap/providers.php`).
- **Fix:** cada commit recebeu APENAS os arquivos do 15-05 (`git add` por caminho); Pint rodado com CAMINHOS EXPLÍCITOS (não `--dirty`, que reformataria os PHP do usuário); `migrate` global NÃO rodado (dispararia as migrations pendentes da Fase 14) — a evidência é via RefreshDatabase/SQLite. Nenhum arquivo do usuário foi tocado/staged/commitado.

**Total deviations:** 2 refinamentos + 1 coordenação. **Impacto no plano:** nenhum (escopo intacto; entrega mais fiel ao objetivo da HU-129 e preservação do trabalho paralelo).

## Verification (evidência fresca)

- `php artisan test --compact --filter=TempoAnalise` → **7/7, 39 asserções** (TempoAnaliseServiceTest 3 + TempoAnaliseReportSourceTest 4).
- `php artisan test --compact --filter=Relatorios` → **70/70, 302 asserções** (zero regressão na suíte de relatórios).
- Greps de aceite: `class TempoAnaliseService` + `businessDurationBetween`; `function tempoPorEtapa` + `function tempoEmissaoTvl`; `implements ReportSource` nos dois sources; `function sedesEscritorioVirtual` — todos presentes.
- `vendor/bin/pint --format agent` nos 5 arquivos → `passed` (sem pendências).
- Provas anti-fachada/correção: pendência que cruza sáb/dom = 3840 min úteis (não 6720 de calendário); TVL ignora decisão sem `tvl_product_number` e fora do período; período vazio ⇒ `media_minutos=null`/`amostras=0`; RN-005 (o source de tempo traz só o período, o de escritório virtual só `is_virtual_office=true`).

## Next Phase Readiness

- **15-09 (HTTP/dashboard + download):** instanciar `TempoAnaliseReportSource`/`EscritorioVirtualReportSource` via `app()` e delegar ao `ReportExporter` (CSV/XLSX/PDF); expor `tempoPorEtapa`/`tempoEmissaoTvl` como indicadores na tela; auditar a consulta. A rota de download `gestao.relatorios.exportacoes.download` é herdada do 15-02 (pendência da fase).
- **Pendências registradas (sem fachada):**
  - Calendário MUNICIPAL oficial de feriados (Salvador) — pendência SEDUR (herdada do 15-04); até lá o tempo útil desconta só fins de semana + nacionais fixos, com a ressalva honesta do `hasOfficialCalendar()`.
  - Layout/colunas oficiais dos relatórios SAPS — pendência SEDUR; as colunas atuais espelham o conteúdo essencial (protocolo/tempos e protocolo/empresa/decisão).

---
*Phase: 15-relatorios-e-indicadores*
*Completed: 2026-06-15*
