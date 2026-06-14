---
phase: 05-motor-louos
plan: 09
subsystem: motor
tags: [louos, golden-cases, data-provider, comando, evidencia-real, anti-fachada, regressao, sqlite, fechamento-fase]

# Dependency graph
requires:
  - phase: 05-motor-louos
    plan: 05
    provides: "consolidar() final + buildFundamentacao() + vagas/restricoes — veredito permitido/permitido_com_condicoes/nao_permitido/pendente"
  - phase: 05-motor-louos
    plan: 02
    provides: "Quadros 7/10/11/11A como dados versionados (seeders LouosQuadro7/10/11) — Quadro 7 real (40 faixas/24 CNAEs)"
  - phase: 05-motor-louos
    plan: 08
    provides: "Sandbox de simulação (HU-143) com versoesOverride"
  - phase: 06-classificacao-risco
    provides: "Padrão golden #[DataProvider] (RiscoGoldenCaseTest) + comando de evidência (RiscoClassificarCommand)"
provides:
  - "LouosGoldenCaseTest (#[DataProvider]) — regressão de domínio do motor LOUOS sobre o seed real (critério 7 do ROADMAP)"
  - "4 fixtures golden: enquadramento por área (nR1), faixa superior (nR2), requer-zona=pendente (anti-fachada) e proibido na zona ZPAM (nao_permitido)"
  - "Comando louos:enquadrar {cnae} --area= [--zona=] [--restricao=*] — evidência real do motor de ponta a ponta"
  - "Fase 5 (Motor LOUOS) fechada e verificada com a suíte completa verde"
affects: [07-consulta-previa, 08-solicitacao]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Golden case do motor LOUOS = fixtures JSON (entrada→esperado) sobre o seed oficial via #[DataProvider], espelhando RiscoGoldenCaseTest — provider lê por __DIR__ antes do boot"
    - "Caso requer_zona/sem zona no fixture trava 'pendente' como regressão (degradação honesta anti-fachada); caso com zona explícita prova o caminho decidível"
    - "Comando de evidência espelha RiscoClassificarCommand (auto-descoberto): aplica o motor real e imprime parecer fundamentado + versões, sem fachada"
    - "Zona/restrição no comando são ENTRADA EXPLÍCITA do operador (hipótese de simulação) — sem --zona o parecer degrada para pendente; o sistema nunca inventa zona"

key-files:
  created:
    - tests/Fixtures/golden/louos/quadro7-enquadramento.json
    - tests/Fixtures/golden/louos/quadro7-faixa-superior.json
    - tests/Fixtures/golden/louos/requer-zona-pendente.json
    - tests/Fixtures/golden/louos/proibido-na-zona.json
    - tests/Feature/Louos/LouosGoldenCaseTest.php
    - app/Console/Commands/LouosEnquadrarCommand.php
    - tests/Feature/Louos/LouosEnquadrarCommandTest.php
  modified: []

key-decisions:
  - "Provider lê os fixtures por glob/__DIR__ (antes do boot da app), igual ao RiscoGoldenCaseTest — não usa base_path()"
  - "Harness do golden monta o TerritoryResult a partir do fixture: com `zona` → feição identificada (entrada explícita); sem zona/`requer_zona` → território nulo (Quadro 10 indisponível → pendente)"
  - "4º fixture (quadro7-faixa-superior) adicionado além dos 3 do plano para travar a transição de faixa do Quadro 7 real (nR1→nR2) — dentro do '3+' do critério de aceite"
  - "Comando exige --area (obrigatória, numérica, > 0); CNAE inválido (≠7 dígitos) ou área ausente/inválida → exit 1; CNAE válido sem regra no Quadro 7 → pendente, exit 0 (não é erro)"
  - "4º teste do comando (area ausente → exit 1) além dos 3 do plano, cobrindo o branch de validação de área"

patterns-established:
  - "Fechamento de fase de motor = golden cases (#[DataProvider] sobre seed real) + comando de evidência + verificação integral fresca"

# Metrics
duration: 18min
completed: 2026-06-14
---

# Phase 5 Plan 09: Fechamento e Verificação do Motor LOUOS Summary

**A Fase 5 fecha com a proteção de regressão de domínio (golden cases #[DataProvider] sobre o seed real dos Quadros da LOUOS) e a evidência real de ponta a ponta (comando `louos:enquadrar`). Os casos `requer_zona`/sem zona travam o veredito `pendente` como regressão — a degradação honesta sem a base de zona (pendente SEDUR) é anti-fachada e está bloqueada contra mudança silenciosa; o caso com zona explícita prova o caminho decidível (proibido → nao_permitido). Suíte completa verde: 600 SQLite + 15 PostGIS.**

## Performance

- **Tasks:** 2 + checkpoint de verificação integral
- **Commits:** `73c0a47` (golden cases), `04aa870` (comando louos:enquadrar) + docs deste SUMMARY
- **Files:** 7 criados (4 fixtures, 2 testes, 1 comando)

## Entregáveis (com evidência)

### Task 1 — Golden cases do motor LOUOS (`73c0a47`)

`tests/Feature/Louos/LouosGoldenCaseTest.php` espelha o `RiscoGoldenCaseTest`: o setUp seeda os Quadros oficiais (`LouosQuadro7Seeder` + `LouosQuadro10Seeder` + `LouosQuadro11Seeder`), o provider `goldenCases()` lê `tests/Fixtures/golden/louos/*.json` por `__DIR__` (antes do boot), executa o motor REAL (`LouosEnquadramentoService::enquadrar`) e asserta cada chave de `esperado` com mensagem que nomeia o golden case divergente.

| Fixture | Entrada | O que trava |
|---|---|---|
| `quadro7-enquadramento.json` | 4712-1/00, área 350, sem zona | Quadro 7 REAL: nR1/nR1-01 (faixa âncora SAPS ≤350 m²); consolidado `pendente` (sem zona) |
| `quadro7-faixa-superior.json` | 4712-1/00, área 400, sem zona | Transição de faixa do Quadro 7: nR2/nR2-01 (>350 m²); `pendente` |
| `requer-zona-pendente.json` | 7020-4/00, área 500, `requer_zona: true` | **CASO CHAVE anti-fachada**: Quadro 7 identificado (nR1), Quadro 10 `indisponivel`, resultado `pendente` — degradação honesta como regressão |
| `proibido-na-zona.json` | 1091-1/02, área 200, zona ZPAM | Caminho decidível: nR3 × ZPAM = `proibido` no Quadro 10 → `nao_permitido` (RN-005) |

### Task 2 — Comando `louos:enquadrar` (`04aa870`)

`app/Console/Commands/LouosEnquadrarCommand.php` (auto-descoberto), espelhando `RiscoClassificarCommand`. Assinatura:

```
louos:enquadrar {cnae} {--area=} {--zona=} {--restricao=*}
```

`handle(LouosEnquadramentoService $service)`: normaliza o CNAE (7 dígitos), valida `--area` (obrigatória, numérica, > 0), monta o `EnquadramentoInput` (zona/restrição como ENTRADA EXPLÍCITA do operador; sem `--zona` o território é nulo → Quadro 10 degrada → parecer pendente) e imprime o parecer: enquadramento (Quadro 7 grupo/subgrupo/faixa), permissão (Quadro 10 ou indisponível), condições pela via (Quadros 11/11A), parecer consolidado (label de `ResultadoViabilidade`), condicionantes, fundamentação legal e versões de regras aplicadas.

## Evidência (verificação integral fresca)

### Suíte de testes

```
# SQLite (--exclude-group postgis)
{"tool":"phpunit","result":"passed","tests":600,"passed":600,"assertions":3050,"duration_ms":19826}

# PostGIS (executou de verdade, não skip)
php artisan geo:preparar-banco-de-testes → "Extensão postgis garantida em sile_testing."
{"tool":"phpunit","result":"passed","tests":15,"passed":15,"assertions":85,"duration_ms":3194}

# Domínio LOUOS (motor + mantenedores + sandbox + golden + comando)
{"tool":"phpunit","result":"passed","tests":81,"passed":81,"assertions":467,"duration_ms":1939}

# Golden cases isolados
{"tool":"phpunit","result":"passed","tests":4,"passed":4,"assertions":19,"duration_ms":279}

# Comando isolado
{"tool":"phpunit","result":"passed","tests":4,"passed":4,"assertions":10,"duration_ms":336}
```

Cadeia coerente: **592 baseline (até 05-08) → 600 SQLite** (+8: 4 golden + 4 comando), todos aditivos. PostGIS **15/15** executados (banco `sile_testing` com extensão postgis garantida — prova de execução real, não skip).

### Pint, typecheck e build

```
vendor/bin/pint --dirty --format agent → {"tool":"pint","result":"passed"}
npm run typecheck → tsc --noEmit (sem erros)
npm run build → ✓ built in 578ms (inclui assets/louos-*.js, assets/sandbox-*.js)
```

### Banco dev (migrate:fresh --seed) — Postgres

`php artisan migrate:fresh --seed` rodou sem erros; todos os seeders DONE, incluindo `LouosQuadro7Seeder`/`LouosQuadro10Seeder`/`LouosQuadro11Seeder`. Carga confirmada:

```
Quadro7 faixas: 40
Quadro7 CNAEs distintos: 24
Quadro10 permissoes: 18
Quadro11/11A condicoes via: 8
Vigente louos_quadro7:   lei-9148-2016-quadro7
Vigente louos_quadro10:  lei-9148-2016-quadro10
Vigente louos_quadro11:  lei-9148-2016-quadro11
Vigente louos_quadro11a: lei-9148-2016-quadro11a
```

### Evidência real do motor — `php artisan louos:enquadrar` (sobre o seed oficial)

**Cenário 1 — sem zona (4712-1/00, área 350): enquadra REAL, mas degrada para pendente**

```
Enquadramento LOUOS — CNAE 4712-1/00
Denominação: Comércio varejista de mercadorias em geral... - minimercados, mercearias e armazéns
Área pretendida: 350 m²

Enquadramento por área (Quadro 7):
  Grupo de uso: nR1 / Subgrupo: nR1-01
  Faixa de área: 0 a 350 m²
  Versão de regras: lei-9148-2016-quadro7

Permissão na zona (Quadro 10):
  Indisponível — não decidida.
  Motivo: Permissão por zona pendente da base oficial (SEDUR)
  Versão de regras: —

Parecer consolidado:
  Resultado: Pendente de análise técnica
  Motivo: Permissão por zona pendente da base oficial (SEDUR)
  Condicionantes: nenhuma

Fundamentação legal:
  - Lei nº 9.148/2016 (LOUOS) — Quadro 7
  - Permissão por zona pendente da base oficial (SEDUR)
```

A fundamentação **não cita o Quadro 10** (regra não aplicada — honesto). O enquadramento por área é real; a permissão por zona é a parte bloqueada.

**Cenário 2 — zona proibida (1091-1/02, área 200, --zona=ZPAM): não permitido**

```
Enquadramento por área (Quadro 7):
  Grupo de uso: nR3 / Subgrupo: nR3-10
Permissão na zona (Quadro 10):
  Permissão: Proibido
  Versão de regras: lei-9148-2016-quadro10
Parecer consolidado:
  Resultado: Não permitido
  Motivo: Atividade proibida na zona pelo Quadro 10
```

**Cenário 3 — zona permitida (4712-1/00, área 350, --zona=ZPR-1): permitido**

```
Permissão na zona (Quadro 10):
  Permissão: Permitido
Parecer consolidado:
  Resultado: Permitido
  Motivo: Atividade permitida na zona, sem condicionantes incidentes
  Condicionantes:
    - [vagas] Exigência de vagas não parametrizada para o grupo
```

Os cenários 2 e 3 usam a zona como **entrada explícita do operador** (hipótese — a base oficial de zona segue pendente): provam que, quando há zona, o motor decide de verdade pela regra versionada (proibido/permitido), enquanto sem zona degrada honestamente para pendente.

## ENTREGUE vs BLOQUEADO (marco de conclusão da Fase 5)

### ENTREGUE — funcional, real, de ponta a ponta

- **Quadro 7 REAL** (HU-038): 40 faixas em 24 CNAEs derivadas da Lei 9.148/2016 + modelo TVL/SAPS; enquadra por CNAE (carregado) + área (entrada) de verdade.
- **Motor `LouosEnquadramentoService`** (HU-038 a HU-046): `enquadrar()` + `consolidar()` + `buildFundamentacao()` + vagas parametrizadas (HU-042) + restrições ZEIS (HU-043), com auditoria RN-002 (`louos`/`enquadramento` com versões).
- **Mantenedores dos Quadros** (HU-015 a HU-018): CRUD sobre rascunhos + publicação 4 olhos (rule_versions herdado da Fase 6).
- **Sandbox de simulação** (HU-143): `versoesOverride` simula versão rascunho sem afetar a vigente.
- **Golden cases** (#[DataProvider]) sobre o seed real — critério 7 do ROADMAP; **comando `louos:enquadrar`** — evidência real.
- **Degradação honesta** sem zona: consolidado `pendente`, fundamentação sem citar regra não aplicada — anti-fachada travada como regressão.

### BLOQUEADO / pendente SEDUR (registrar no STATE/ROADMAP — orquestrador)

- **Quadro 10 sobre zona REAL** (HU-039): a base de zoneamento da LOUOS (SIGIS/CA 2000) não tem fonte vetorial pública — sem ela o Quadro 10 degrada para `indisponivel` → parecer `pendente`. Estrutura/seed modelados existem; quando a base chegar, muda a carga, não a lógica.
- **Atributo viário LOUOS** (HU-040/041, Quadros 11/11A): a geometria viária existe (Fase 4, 800 features), mas o atributo `CLASSE_VIA_LOUOS` pende confirmação SEDUR — o motor não infere a classe a partir da geometria (degrada).
- **Correspondência "Quadro 11" ↔ 11B**: a confirmar com a SEDUR.
- **Planilhas oficiais dos Quadros** (7/10/11/11A): substituem os seeds derivados da Lei quando a SEDUR entregar — o motor processa o dado real em qualquer caso.

## Deviations from Plan

### Enriquecimentos (dentro do "3+" do critério de aceite)

- **4º fixture golden** (`quadro7-faixa-superior.json`): além dos 3 fixtures do plano, trava a transição de faixa do Quadro 7 real (nR1→nR2 em 350 m²) — a parte entregável/REAL do motor merece regressão na transição de faixa. O acceptance pede "3+".
- **4º teste do comando** (`test_area_ausente_falha`): além dos 3 do plano, cobre o branch de validação de área obrigatória (exit 1).

**Total deviations:** nenhuma de comportamento; plano executado como escrito, com 2 enriquecimentos de cobertura aditivos. Sem fachada.

## Next Phase Readiness

- **Fase 5 (Motor LOUOS) entregue e verificada.** O parecer fundamentado (`{resultado, fundamentacao[], condicionantes[], motivo}` + versões) é o contrato estável consumido pela Fase 7 (Consulta Prévia) e Fase 8 (Solicitação).
- **Próxima acionável:** Fase 7 (Consulta Prévia), que consome o motor LOUOS (Fase 5), o território (Fase 4) e o risco (Fase 6).
- **Bloqueio herdado:** zona urbanística e atributo viário LOUOS seguem pendentes SEDUR — o motor degrada honestamente para `pendente`; a Fase 7 deve comunicar a pendência ao usuário, nunca simular permissão.

---
*Phase: 05-motor-louos*
*Completed: 2026-06-14*
