---
phase: 05-motor-louos
plan: 08
subsystem: api
tags: [louos, sandbox, simulacao, rule-versions, four-eyes, inertia, react, parametrizacao, anti-fachada, sqlite, auditoria]

# Dependency graph
requires:
  - phase: 05-motor-louos
    plan: 03
    provides: "LouosEnquadramentoService.enquadrar + EnquadramentoInput.versoesOverride (modo versão específica/sandbox) + resolveVersion 3 modos"
  - phase: 05-motor-louos
    plan: 05
    provides: "consolidar() final (permitido/permitido_com_condicoes/nao_permitido/pendente) — contrato de comparação do sandbox"
  - phase: 05-motor-louos
    plan: 06
    provides: "RuleVersionService (openDraft/publish 4-olhos), LouosController/PublishLouosVersionRequest, permissão manter-louos, rotas gestao.louos.*"
  - phase: 06-classificacao-risco
    provides: "RuleVersion (status rascunho coexiste com vigente), FourEyesViolationException, AuditService"
provides:
  - "LouosSandboxSimulationService.simulate(domain, versaoRascunho, amostra?): reexecuta o MOTOR REAL por versão específica sobre cenários derivados dos dados vigentes e conta divergências (vigente × simulado), sem efeito colateral (RN-001)"
  - "LouosSandboxController (index/simulate/publish) + SimulateLouosRequest — simular e publicar rascunho por quatro olhos"
  - "Rotas gestao.louos.sandbox.index/simular/publicar (todas sob permission:manter-louos)"
  - "Página gestao/louos/sandbox (seleção de rascunho + amostra, resumo do impacto, tabela de divergências) + item de navegação"
affects: [05-09-verificacao, 07-consulta-previa, 14-medir-parametrizar]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Simulação sem efeito colateral via transação SEMPRE revertida (reexecutarSemPersistir): o motor real roda e audita, mas nada persiste — RN-001 sem tocar o motor"
    - "Cenários da amostra derivados dos dados reais já seedados (CNAEs do Quadro 7 vigente + zona fixa do Quadro 10 vigente), sem arquivo de cenário próprio"
    - "Publicação a partir do sandbox reusa RuleVersionService.publish (4-olhos), interceptando autor=publicador no controller (flash.error)"

key-files:
  created:
    - app/Services/Louos/LouosSandboxSimulationService.php
    - app/Http/Controllers/Gestao/LouosSandboxController.php
    - app/Http/Requests/Gestao/SimulateLouosRequest.php
    - resources/js/pages/gestao/louos/sandbox.tsx
    - tests/Feature/Louos/LouosSandboxTest.php
  modified:
    - routes/gestao.php
    - resources/js/layouts/gestao-layout.tsx

key-decisions:
  - "RN-001 garantido por transação sempre revertida (DB::beginTransaction → reprocessa → finally DB::rollBack), NÃO por flag no motor: respeita o escopo do plano (files_modified) e neutraliza QUALQUER escrita do motor durante a simulação (vigente intacta + zero decisão na trilha)"
  - "Cenários derivados dos CNAEs reais da versão vigente do Quadro 7 (área = ponto médio da faixa, ou min+1 sem teto) + uma zona fixa do Quadro 10 vigente (a com mais permissões 'permitido', desempate alfabético) — sem isso o consolidado seria sempre 'pendente' e não haveria o que comparar"
  - "Simulação registrada em auditoria como evento informativo 'louos'/'simulacao' (rulesVersion null) — NÃO é decisão; gravada fora da transação revertida"
  - "amostra_usada = tamanho de amostra resolvido (parâmetro/Settings, o teto); total = cenários efetivamente reprocessados (≤ amostra_usada) — ambos no resumo (RN-003)"
  - "Simular e publicar são manutenção: as 3 rotas vivem sob permission:manter-louos (analista consulta Quadros mas é 403 no sandbox)"
  - "POST simulacao re-renderiza a mesma página com o relatório (GET/POST compartilham o caminho 'simulacao' — refresh recai na consulta sem o relatório efêmero)"

patterns-established:
  - "Sandbox de parametrização: reexecutar o motor real por versão específica (versoesOverride) e comparar consolidado vigente × candidato, contando divergências e distribuição de→para"
  - "reexecutarSemPersistir(callable): roda dentro de transação sempre revertida para RN-001 (zero escrita persistida); registro informativo da operação gravado depois"

# Metrics
duration: 25min
completed: 2026-06-14
---

# Phase 5 Plan 08: Sandbox de Simulação de Parametrização da LOUOS (HU-143) Summary

**O gestor simula uma versão RASCUNHO de um Quadro contra cenários reais reexecutando o MOTOR REAL (`LouosEnquadramentoService` por versão específica) e vê quantos resultados mudariam — antes de publicar por quatro olhos. RN-001 absoluta: a simulação roda numa transação sempre revertida, sem alterar a vigente, sem publicar, sem auditar decisão e sem notificar.**

## Performance

- **Duration:** ~25 min
- **Started:** 2026-06-14T06:25:19Z
- **Completed:** 2026-06-14T06:50:32Z
- **Tasks:** 2 código (TDD RED→GREEN) + 1 checkpoint humano (visual)
- **Files:** 7 (5 criados, 2 modificados)

## Accomplishments

- **`LouosSandboxSimulationService::simulate(RuleDomain, versaoRascunho, ?amostra)`** reexecuta o motor real duas vezes por cenário — vigente (sem override) × candidato (`versoesOverride = [domínio => versaoRascunho]`, modo "versão específica" do 05-03) — e compara o veredito consolidado, devolvendo `{dominio, versao_rascunho, amostra_usada, total, mudariam, distribuicao, divergencias[]}`. Sem fachada: não imita resultado, roda o motor de verdade.
- **RN-001 (zero efeito colateral)** garantido por `reexecutarSemPersistir`: a reexecução roda dentro de `DB::beginTransaction()` … `finally DB::rollBack()`, de modo que NADA escrito pelo motor (inclusive a auditoria de decisão `enquadramento`) persiste. A vigente fica intacta; só o registro informativo `louos`/`simulacao` (sem `rulesVersion` de publicação) é gravado, depois, fora dessa transação. **O motor não foi tocado** (escopo do plano respeitado).
- **`LouosSandboxController`** (index/simulate/publish) + **`SimulateLouosRequest`** + rotas `gestao.louos.sandbox.*` sob `permission:manter-louos`. Publicar promove o rascunho por quatro olhos via `RuleVersionService::publish` (autor ≠ publicador), com o bloqueio comunicado por `flash.error`.
- **Página `gestao/louos/sandbox`** (React/Inertia): seleção de rascunho + tamanho de amostra, "Simular impacto", resumo (reprocessados/mudariam/amostra + distribuição de→para) e tabela de divergências (resultado vigente × simulado + motivo), "Publicar versão" com confirmação de quatro olhos. Item de navegação "Simulação de regras" gateado por `manter-louos`.
- **TDD estrito**: o teste-âncora e o 403/4-olhos falhavam antes (RED confirmado); 7 testes novos verdes.

## Task Commits

1. **Task 1: serviço de simulação (motor real, RN-001)** — `b77f544` (feat) — RED→GREEN
2. **Task 2: controller + request + rotas + página + nav (4-olhos)** — `b336c88` (feat) — RED→GREEN

## Contrato entregue (insumo do 05-09 e do EP14 medir→parametrizar→medir)

### `simulate()` e relatório
`simulate(RuleDomain $domain, string $versaoRascunho, ?int $amostra = null): array`. Amostra: `$amostra ?? (int) Settings::get('louos.sandbox.amostra_padrao', 50)` (RN-003/HU-014). Retorno:

```
{
  dominio: string,                 // RuleDomain->value (ex.: louos_quadro10)
  versao_rascunho: string,
  amostra_usada: int,              // teto resolvido (parâmetro/Settings)
  total: int,                      // cenários reprocessados (≤ amostra_usada)
  mudariam: int,                   // quantos divergiram (vigente × simulado)
  distribuicao: { "<de>→<para>": int },   // ex.: "permitido→nao_permitido": 1
  divergencias: [ { cenario: {cnae, cnae_formatado, area, zona}, resultado_vigente, resultado_simulado, motivo_simulado } ]
}
```

### Fonte dos cenários (escopo honesto, sem arquivo novo)
A amostra é DERIVADA dos dados reais já seedados:
- **Um cenário por CNAE distinto** da versão vigente do Quadro 7 (`LouosQuadro7Faixa` ordenado por `cnae_code`), com **área representativa** da faixa (ponto médio entre `area_min`/`area_max`, ou `area_min + 1` sem teto).
- **Zona fixa** retirada da versão vigente do Quadro 10 (a zona com mais permissões `permitido`, desempate alfabético) — montada num `TerritoryResult` sintético com a zona identificada, para o consolidado ir ALÉM de `pendente` e exercitar divergências reais.
- Sem Quadro 7 vigente ou sem zona no Quadro 10 vigente → amostra vazia (o consolidado seria sempre `pendente`).
- **Nota de escopo:** quando o EP08 (solicitações) trouxer processos reais, a mesma simulação passa a usá-los — muda a fonte da amostra, não a lógica. NÃO usa golden do 05-09 (wave posterior) nem arquivo de cenário próprio.

### Rotas (nomes finais) — todas sob `permission:manter-louos`
- `GET /gestao/louos/simulacao` → `gestao.louos.sandbox.index`
- `POST /gestao/louos/simulacao` → `gestao.louos.sandbox.simular` (re-renderiza a página com `simulacao`)
- `PUT /gestao/louos/simulacao/publicar` → `gestao.louos.sandbox.publicar`

### Quatro olhos (RN-005)
A publicação promove o rascunho via `RuleVersionService::publish($draft, $publisherId)`. O controller intercepta `draft.created_by === publisherId` antes e comunica por `flash.error` (degradação controlada, espelha o `LouosController`); o `RuleVersionService` é a defesa de domínio (lança `FourEyesViolationException`).

### Decisão de auditoria
A simulação **não é auditada como decisão**: as auditorias `enquadramento` emitidas pelo motor durante a reexecução são revertidas com a transação; persiste apenas um evento informativo `louos`/`simulacao` (`rulesVersion` null), com o resumo do impacto. A publicação mantém a auditoria `regras`/`publicacao-versao` do `RuleVersionService` (RN-002).

## Files Created/Modified
- `app/Services/Louos/LouosSandboxSimulationService.php` — simulação (motor real, RN-001, cenários derivados, divergências)
- `app/Http/Controllers/Gestao/LouosSandboxController.php` — index/simulate/publish
- `app/Http/Requests/Gestao/SimulateLouosRequest.php` — quadro/versao_rascunho(exists rascunho)/amostra + `dominio()`
- `resources/js/pages/gestao/louos/sandbox.tsx` — UI do sandbox (resumo + divergências + publicação 4-olhos)
- `tests/Feature/Louos/LouosSandboxTest.php` — 7 testes (serviço + rota)
- `routes/gestao.php` — 3 rotas sandbox sob `manter-louos`
- `resources/js/layouts/gestao-layout.tsx` — item de navegação "Simulação de regras"

## Decisions Made
- **RN-001 por transação revertida, não por flag no motor:** mantém o `LouosEnquadramentoService` intacto (escopo `files_modified` do plano) e garante zero escrita persistida — a vigente fica intacta e nenhuma decisão de sandbox contamina a trilha. O relatório é computado em memória e sobrevive ao rollback.
- **Zona fixa = zona com mais `permitido`** (desempate alfabético): maximiza cenários além de `pendente`, dando o que comparar — determinístico e auto-contido.
- **`amostra_usada` (teto) distinto de `total` (reprocessados):** ambos no resumo (RN-003); o teto prova a parametrização (HU-014), o total prova o que rodou.
- **Simular/publicar = manutenção** (`manter-louos`): o analista consulta os Quadros (05-06) mas é 403 no sandbox.

## Deviations from Plan

None - plan executed exactly as written (somente os arquivos de `files_modified` foram tocados; o motor não foi modificado).

## Issues Encountered
- **`assertInertia(->component(...))` exige o arquivo da página em disco:** o teste de rota da simulação só ficou verde após criar `sandbox.tsx` (esperado — a verificação de componente do Inertia confere o arquivo). Os testes de 403 e de 4-olhos passam sem a página (403 curto-circuita; publish redireciona).

## Next Phase Readiness
- **05-09 (verificação/golden):** o sandbox reexecuta o motor real por versão específica e RN-001 (vigente intacta) está travado por teste — base para a verificação anti-fachada. O golden do 05-09 NÃO foi tocado.
- **Criação de rascunho (integração):** o sandbox simula/publica rascunhos EXISTENTES (status rascunho), que coexistem com a vigente (`RuleVersionService::openDraft`). A criação de rascunho "candidato" pela UI (um "salvar como rascunho" no mantenedor 05-06) fica como evolução natural — fora do escopo declarado do 05-08; o ciclo simular→publicar está completo e testado sobre rascunhos reais.
- **EP14 (ampliar o expresso, medir→parametrizar→medir):** a simulação de impacto é o mecanismo de segurança para mudanças de regra.

---
*Phase: 05-motor-louos*
*Completed: 2026-06-14*
