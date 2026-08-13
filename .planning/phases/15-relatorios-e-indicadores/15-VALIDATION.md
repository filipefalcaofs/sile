---
phase: 15
slug: relatorios-e-indicadores
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-06-15
---

# Phase 15 — Validation Strategy

> Contrato de validação por fase para amostragem de feedback durante a execução. Derivado da seção "Validation Architecture" de `15-RESEARCH.md`. **A Dimensão 1 (anti-fachada, CA-03) é prioritária.**

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit 12 (feature tests; factories) |
| **Config file** | `phpunit.xml` (SQLite em memória) |
| **Quick run command** | `php artisan test --compact --filter=Relatorios` |
| **Full suite command** | `composer test` (2 processos: `--exclude-group postgis` + `--group postgis`) |
| **Front/build check** | `vendor/bin/pint --dirty --format agent` · `npm run build` · `tsc --noEmit` |
| **Estimated runtime** | ~90–150 s (suíte completa nos 2 bancos) |

Baseline a confirmar fresco antes/depois: ~1315 SQLite + 29 `@group postgis`; 85 parâmetros / 27 permissões (somar os novos da fase).

---

## Sampling Rate

- **After every task commit:** `php artisan test --compact --filter=<TestRelevante>`
- **After every plan wave:** `composer test` (2 processos)
- **Before guardião-entrega / fechamento:** suíte completa verde + `pint` limpo + `npm run build` verde
- **Max feedback latency:** ~150 s

---

## Per-Task Verification Map

> Preenchido pelo planner/executor quando os PLAN.md existirem. Cada CA BDD das HUs vira ao menos um feature test.

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 15-XX-XX | XX | N | HU-XXX / CA-XX | feature | `php artisan test --compact --filter=...` | ❌ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Validation Dimensions (de 15-RESEARCH.md)

1. **Anti-fachada [PRIORITÁRIA — CA-03]:** indicador degrada honesto (KPI sem `delta` sem janela histórica; HU-124 zona→bairro com ressalva; HU-137 feriado nunca inventado; HU-145 `tipo_gatilho=null` quando o motor degradou); export reflete EXATAMENTE o conjunto filtrado (RN-005); export falho NÃO vira "pronto" (`failed()` auditado, sem link válido).
2. **Correção das agregações:** taxas (HU-127/128), tempo por etapa com desconto de fim de semana/feriado (HU-129), relatórios SAPS (Tempo de Emissão de TVL, Sedes de Escritório Virtual), taxa de resposta expressa (HU-145) — datasets factory conhecidos → valores exatos.
3. **Contrato de exportação (HU-131):** CSV/XLSX/PDF válidos (XLSX relido pelo Reader do openspout; PDF inicia com `%PDF` + rodapé "Total de registros: N" — CA-07/RN-010); limiar assíncrono (`Queue::fake()`); retrofit anti-regressão (Auditoria/Processos verdes).
4. **Auditoria e segurança (RN-002/007/008):** toda consulta e exportação auditadas; gate `consultar-relatorios` (403 auditado); `relatorios.produtividade.nominal` controla visão nominal (HU-130, analista vê só o próprio); LGPD (cpf_masked; download por URL assinada de disco não-público).
5. **Parametrização sem deploy (HU-014):** alterar `relatorios.export.assincrono_limiar_linhas`/`meta_taxa`/`janela_dias` via `Settings` muda comportamento; contagem de catálogo/permissões atualizada.
6. **SSR/Front:** `npm run build`/`tsc` verdes; wrapper de chart é client-only (não importa `echarts` no caminho do servidor); tema claro/escuro e resize (verificação visual).

---

## Wave 0 Requirements

- [ ] `tests/Feature/Relatorios/` — diretório de testes da fase
- [ ] Factories/estados auxiliares para datasets de indicadores (decisões/transições/quedas) — verificar reuso das factories existentes (`ViabilityRequestFactory`, `ViabilityDecisionFactory`, transições) antes de criar
- [ ] Infra de teste de export (helper para reabrir XLSX via Reader openspout; assert de `%PDF`)

*Instalação de dependências da fundação: `composer require "openspout/openspout:^4.0"` e `npm install echarts@^6.1`.*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Tema claro/escuro do gráfico acompanha o DS | HU-122 | Visual/DOM | Alternar dark mode no console e conferir o gráfico re-temar sem recriar |
| Responsividade dos gráficos (resize) | HU-122 | Visual | Redimensionar a janela/mobile e conferir `ResizeObserver` |
| Smoke navegável dos relatórios + export real | HU-122/131 | E2E navegável | Roteiro no SUMMARY de fechamento (gerar CSV/XLSX/PDF reais a partir de seeds) |

---

## Validation Sign-Off

- [ ] Todas as tasks têm verify `<automated>` ou dependência de Wave 0
- [ ] Continuidade de amostragem: sem 3 tasks consecutivas sem verify automatizado
- [ ] Wave 0 cobre todas as referências MISSING
- [ ] Sem flags de watch-mode
- [ ] Feedback latency < 150 s
- [ ] `nyquist_compliant: true` no frontmatter (ao fim do planejamento)

**Approval:** pending
