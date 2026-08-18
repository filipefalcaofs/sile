---
phase: 12-auditoria-e-compliance
plan: 12
subsystem: seeders
tags: [hu-149, hu-102, hu-099, abuse-detection, malha-fina, lgpd, personal-data, decision-trace, dev-seeder, idempotencia, anti-fachada, tdd, sqlite]

# Dependency graph
requires:
  - phase: 12-auditoria-e-compliance
    provides: "12-06/12-08 — AbuseDetectionService + 5 detectores (volume_cnpj entre eles) toggle-gated por features.deteccao_abuso, com encaminhamento à malha fina por sistema acima do limiar"
  - phase: 12-auditoria-e-compliance
    provides: "12-07 — marcação personal_data nos call sites reais (ProcessoController@show) consumida pelo painel LGPD (HU-102)"
  - phase: 12-auditoria-e-compliance
    provides: "12-04/12-05 — decision_trace aditivo + DecisionExplanationService (HU-099)"
  - phase: 10-analise-tecnica-sedur
    provides: "AnaliseTecnicaDecisionService (decide a partir da ficha finalizada) + AnalysisRecordService + MalhaFinaService"
provides:
  - "AuditoriaDevSeeder — dados de dev REAIS (fictícios na carga, lógica de verdade) de auditoria/abuso/LGPD/explicabilidade, encadeado no DatabaseSeeder (perfil dev), idempotente por marcadores próprios"
  - "Padrão de abuso DEDICADO (12 solicitações do mesmo CNPJ por requerente/empresa próprios) → 1 abuse_alert volume_cnpj ALTA aberto + encaminhamento à malha fina por sistema (caminho real)"
  - "Acesso a dado pessoal real (personal_data=true) pelo AuditService — base de medição do painel LGPD"
  - "1 decisão pela análise técnica (decision_trace navegável) + 1 decisão legada sem trace (sub-passo 'não registrado' da HU-099), SQLite-safe"
affects:
  - "12-12 (Task 2 — verificação integral + guardião-entrega): a suíte integral roda sobre este seed; o guardião avalia a entrega-funcional com evidência fresca"
  - "12-12 (checkpoint humano): o smoke navega a trilha/explicabilidade/LGPD/abuso alimentados por este seed"

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Seed de dev que roda o MOTOR real (detecção de abuso) habilitando o toggle só durante o seed e restaurando OFF — degradação honesta, sem fachada"
    - "Invalidação MANUAL do cache do Settings no seed (Cache::forget) porque o DatabaseSeeder roda sob WithoutModelEvents — o observer saved() do Parameter fica mudo"
    - "Padrão de abuso isolado em requerente/empresa DEDICADOS para não perturbar as contagens dos exemplos do cidadão (anti-regressão dos seeds anteriores)"
    - "Decisão pela ANÁLISE TÉCNICA a partir da ficha (territorial-agnóstica) como caminho SQLite-safe de explicabilidade — distinto do expresso, que exige PostGIS"
    - "Auth::setUser + forgetGuards para registrar o causer do acesso a dado pessoal sem efeitos de sessão/login no contexto do seed"

key-files:
  created:
    - "database/seeders/AuditoriaDevSeeder.php"
    - "tests/Feature/Seeders/AuditoriaDevSeederTest.php"
  modified:
    - "database/seeders/DatabaseSeeder.php"
    - "tests/Feature/Seeders/DatabaseSeederTest.php"

key-decisions:
  - "Padrão de abuso por VOLUME_CNPJ com 12 solicitações (> 2× o limite default 5) garante severidade ALTA e, com ela, o encaminhamento à malha fina — sem mexer nos parâmetros (mantém Parameter count 85)"
  - "Empresas/requerentes DEDICADOS (CNPJs 99.999.../88.888...; requerente.abuso@ e requerente.auditoria@) — jamais o cidadao@sile.dev; Company 3->5 atualizado no DatabaseSeederTest"
  - "Decisão dev via análise técnica (não o expresso) para ser SQLite-safe; ViabilityDecision 0->2 no DatabaseSeederTest (1 com trace + 1 legada sem trace)"
  - "Toggle restaurado ao valor ORIGINAL do parâmetro (não força OFF por cima de uma escolha do admin); no dev/teste o original é OFF — degradação honesta"
  - "Detecção roda sobre TODO o dataset (motor real): a empresa do cidadão (10 solicitações em pgsql / 7 em SQLite) fica em Média (sem malha fina) — só o padrão dedicado atinge ALTA, sem regredir o AnaliseSeedPostgisTest"

# Metrics
duration: ~50min (Task 1)
completed: 2026-06-15
---

# Phase 12 Plan 12: Fechamento — seed de dev real de auditoria/abuso/LGPD (Task 1) Summary

**`AuditoriaDevSeeder`: o ambiente de desenvolvimento ganha auditoria, explicabilidade, LGPD e detecção de abuso NAVEGÁVEIS com dados fictícios mas produzidos pela LÓGICA real (entrega-funcional). Semeia um padrão de abuso dedicado e roda o `AbuseDetectionService` REAL (habilita `features.deteccao_abuso` só durante o seed → `abuse_alerts` + 1 encaminhamento à malha fina por sistema; restaura OFF), registra um acesso a dado pessoal pelo `AuditService` real (painel LGPD) e leva um processo à decisão pela análise técnica (com `decision_trace`) além de uma decisão legada sem trace — tudo por requerentes/empresas dedicados, idempotente, sem perturbar os exemplos do cidadão.**

> Status do plano: **Task 1 concluída** (este seed). Task 2 (verificação integral + veredito do guardião-entrega) e o checkpoint humano do smoke ficam para o orquestrador — seções reservadas abaixo.

## O que o seed produz (dev navegável, fluxo real)

| Domínio | O que nasce | Como (lógica real) |
|---|---|---|
| Abuso (HU-149) | 1 `abuse_alert` `volume_cnpj` **ALTA aberto** + `fine_mesh_referral` por sistema | 12 solicitações do MESMO CNPJ dedicado (> 2× o limite 5) → `AbuseDetectionService::detectar()` com o toggle ligado só no seed |
| LGPD (HU-102) | ≥1 `activity` com `personal_data=true` | `AuditService::log` no mesmo call site do `ProcessoController@show` (gestor como causer) |
| Explicabilidade (HU-099) | 1 `ViabilityDecision` `analise_tecnica` com `decision_trace` | processo dedicado: protocolo → em_analise (máquina de estados) → ficha finalizada → `AnaliseTecnicaDecisionService::decide` |
| Explicabilidade (HU-099) | 1 `ViabilityDecision` **legada sem `decision_trace`** | factory (sub-passo "não registrado nesta decisão") |
| Degradação honesta | `features.deteccao_abuso` volta a **OFF** após o seed | toggle restaurado ao valor original + `Cache::forget` manual (DatabaseSeeder roda sob `WithoutModelEvents`) |

Anti-fachada / anti-regressão:
- Entidades DEDICADAS (CNPJs e e-mails próprios) — **nunca** o `cidadao@sile.dev`; as contagens dos exemplos das fases 8/9/10/11 ficam intactas.
- A detecção roda sobre TODO o dataset (motor de verdade): a empresa do cidadão fica em **Média** (sem malha fina) — só o padrão dedicado atinge **Alta**, então o `AnaliseSeedPostgisTest` (deferido com 1 malha fina) não regride.
- Roda em **SQLite e pgsql** (detecção e decisão da análise técnica são territoriais-agnósticas), diferente do expresso/análise que exigem PostGIS.

## Evidência de testes (números reais, frescos)

- **RED** (antes do seeder): `AuditoriaDevSeederTest` → 3 errors (`Class "Database\Seeders\AuditoriaDevSeeder" not found`) + 1 failure (sem `personal_data`) — motivo certo.
- **GREEN** `AuditoriaDevSeederTest` → **5 passed, 27 assertions** (abuso real + malha fina; personal_data; toggle OFF restaurado; decisão com trace + legada sem trace; idempotência).
- Anti-regressão `AuditoriaDevSeederTest|DatabaseSeederTest` → **13 passed, 139 assertions** (inclui **85 parâmetros / 27 permissões**; Company 3→5; ViabilityDecision 0→2).
- **`composer test` (2 processos), suíte INTEGRAL fresca e verde:**
  - SQLite (`--exclude-group postgis`): **1313 passed, 6747 assertions**.
  - PostGIS (`--group postgis`): **29 passed, 184 assertions**.
  - Sem regressão (baseline anterior 1308 SQLite + 29 postgis; cresceu com os 5 testes novos do seed).
- `vendor/bin/pint --dirty --format agent` → passed; `ReadLints` limpo.

## Task Commits

1. **Task 1 (teste, TDD RED):** `5e09938` — `test(12-12): adiciona AuditoriaDevSeederTest (abuso/LGPD/decisão reais)`
2. **Task 1 (implementação, GREEN + anti-regressão):** `e1e4d16` — `feat(12-12): AuditoriaDevSeeder produz auditoria/abuso/LGPD reais no dev`

## Desvios do plano

Nenhum desvio de escopo. Ajustes de implementação (não desvios), documentados:
- **Invalidação manual do cache do Settings no seed.** O `DatabaseSeeder` roda sob `WithoutModelEvents`, então o observer `saved()` do `Parameter` (que faz o `Cache::forget`) fica mudo — sem o `Cache::forget` explícito no `detectarAbusoReal`, o toggle ficaria cacheado ON após o seed. Pego pelo TDD (teste do toggle OFF falhou pelo motivo certo) e corrigido.
- **CPF do requerente de auditoria.** O primeiro CPF escolhido colidia com o `admin@sile.dev` (DevAdminSeeder); trocado por outro CPF válido e não utilizado. Pego pelo TDD (unique constraint) e corrigido.

## Bloqueios SEDUR/DPO (registrados, nunca simulados)

Permanecem como pendências externas (sem fachada): calibração dos limiares/janela de fraude e do papel auditor; políticas de retenção/eliminação/anonimização LGPD (DPO); `EscritorioVirtualEncadeadoDetector` (2ª onda); export pleno XLSX/PDF (HU-131/Fase 15). A detecção nasce desligada (toggle OFF) até a SEDUR validar.

---

## Verificação integral + guardião-entrega (Task 2 — CONCLUÍDA)

**Verificação integral fresca (orquestrador), 2026-06-15:**
- `vendor/bin/pint --dirty --format agent` → **passed** (limpo; só `.cursor/` não-versionado).
- `composer test` (2 processos): SQLite **1313 passed / 6747 assertions** + PostGIS **29 passed / 184 assertions** — sem regressão.
- Árvore limpa; trabalho da fase commitado (11 planos + seed do 12-12).

**VEREDITO DO GUARDIÃO-ENTREGA: APROVADO** (evidência fresca executada pelo próprio guardião):
- `composer test` → 1313 SQLite (6747) + 29 postgis (184); filtros dirigidos aos invariantes → **68 passed / 396 assertions**; `npm run build` ok (chunks abuso/auditoria/lgpd/explicabilidade); 85 parâmetros / 27 permissões confirmados em teste.
- Conformidade: [ok] entrega funcional anti-fachada · [ok] TDD (CAs cobertos e verdes) · [ok] auditoria RN-002 · [ok] parametrização HU-014 · [ok] convenções/consistência.
- Invariantes anti-fachada confirmados em CÓDIGO + TESTE: abuso nunca pune (toggle OFF default, `abuso:detectar` no-op, confirmar/descartar só mexe no alerta, malha fina ortogonal sem mudar status — RN-001); explicabilidade é projeção pura (spy do motor = 0 chamadas — RN-005); LGPD marca `personal_data` só em call sites reais e o painel é minimizado; `decision_trace`/`AuditService::log(personalData)` aditivos (Fases 9/10 verdes, inclusive @group postgis).
- Bloqueios externos legítimos REGISTRADOS (não reprovam): export pleno XLSX/PDF → HU-131/Fase 15; limiares de fraude/papel auditor → SEDUR; matriz de CNAEs incompatíveis e captura de respostas de condicionante → SEDUR; `EscritorioVirtualEncadeadoDetector` → 2ª onda; retenção/eliminação/anonimização LGPD → DPO.

**Achado FORA do escopo do EP12 (não reprova a Fase 12 — pendência da Fase 10):**
- `CaixaSetorController@index` (HU-080/081, Fase 10) fazia `Inertia::render('gestao/caixa-setor/index')`, mas **não existia** `resources/js/pages/gestao/caixa-setor/index.tsx` → o SSR registrava `Page not found: gestao/caixa-setor/index`. O backend (listar/distribuir/assumir) estava completo, real e testado (`CaixaSetorTest`), mas o teste Inertia assere só o NOME do componente — não pegava a tela ausente. Era uma fachada de runtime (rota alcançável sem página); o docblock do controller dizia "a tela é construída em 10-16", que não foi entregue.
- **CORRIGIDO nesta sessão (commits `1c7c7df`, `6e1c7de`):** criada a tela `gestao/caixa-setor/index.tsx` (lista server-driven + assumir + distribuir em modal com justificativa de seleção obrigatória) e o `CaixaSetorController@index` passou a expor `analistas` (do setor) e `podeDistribuir` (TDD — 2 testes novos no `CaixaSetorTest`, 9/9). Verificação fresca: typecheck + build (chunk `caixa-setor`) verdes, `composer test` = 1315 SQLite + 29 postgis. Fachada removida de ponta a ponta.

## Checkpoint humano do smoke (DISPENSADO pelo usuário)

O usuário optou por dispensar o smoke navegável manual e considerar a fase aprovada com base na verificação automatizada + veredito do guardião-entrega (sessão autônoma). A cobertura que sustenta a decisão: suíte integral fresca verde (`composer test` 1315+29), filtros dirigidos aos invariantes anti-fachada (68/68), `npm run build`/`typecheck` verdes com os chunks das telas novas, e o guardião APROVADO inspecionando código + teste. O roteiro de smoke do plano permanece válido para validação humana futura no ambiente dev (`migrate:fresh --seed` + `composer dev`).

---
*Phase: 12-auditoria-e-compliance*
*Task 1 concluída: 2026-06-15*
