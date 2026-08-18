---
phase: 07-consulta-previa-viabilidade
plan: 10
subsystem: testing
tags: [golden-cases, dataprovider, comando-evidencia, regressao-dominio, anti-fachada, degradacao-honesta, verificacao-integral, postgis, hu-054, hu-055, hu-056, hu-057, hu-058, hu-059, hu-060]

# Dependency graph
requires:
  - phase: 07-consulta-previa-viabilidade
    plan: 05
    provides: "ConsultaViabilidadeService (consultarPorEndereco/Cnae/Inscricao) + ConsultaViabilidadeResult — orquestrador real exercido pelos golden cases e pelo comando"
  - phase: 07-consulta-previa-viabilidade
    plan: 06
    provides: "Endpoints públicos + tradução de erros (404/503) reusada na ergonomia do comando"
  - phase: 07-consulta-previa-viabilidade
    plan: 08
    provides: "UI pública consulta.tsx + componente ResultadoViabilidade (alvo do smoke navegável)"
  - phase: 07-consulta-previa-viabilidade
    plan: 09
    provides: "UI do histórico autenticado historico.tsx (alvo do smoke navegável)"
  - phase: 05-motor-louos
    provides: "LouosGoldenCaseTest / LouosEnquadrarCommand — padrão #[DataProvider] e comando de evidência espelhados"
  - phase: 06-classificacao-de-risco
    provides: "RiscoGoldenCaseTest / RiscoClassificarCommand — padrão #[DataProvider] e comando de evidência espelhados"
provides:
  - "Comando viabilidade:consultar (CNAE/endereço/inscrição): evidência real de ponta a ponta do orquestrador, parecer fundamentado pt-BR"
  - "Golden cases da consulta (#[DataProvider]) sobre o orquestrador real + seed oficial — regressão de domínio da consulta (3 fixtures)"
  - "Fixtures-âncora anti-fachada: endereco-sem-zona-pendente (zona → pendente), inscricao-indisponivel (degrada sem inventar ponto), cnae-risco-real"
  - "Verificação integral fresca da Fase 7: suíte SQLite 657, grupo postgis 15 (executado), pint/typecheck/build, migrate:fresh --seed, evidência do comando, auditoria"
affects: [08-solicitacao-formal]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Golden cases da consulta espelham Louos/Risco: #[DataProvider] glob por __DIR__, harness seeda o oficial e roda o ConsultaViabilidadeService REAL, assertGolden por chave com mensagem que nomeia o caso"
    - "Chaves de esperado da consulta: veredito, veredito_motivo_contem (substring), risco_municipal_status, quadro7_status, tem_geocode (bool), tem_territorio (bool), avisos_contem (substring em algum aviso)"
    - "Comando de evidência fino: valida CNAE, escolhe consultarPorEndereco/Inscricao/Cnae pelo input, traduz exceção do geocoder em erro honesto (exit 1), renderiza o parecer; pendente NÃO é erro (exit 0)"

key-files:
  created:
    - app/Console/Commands/ConsultaViabilidadeCommand.php
    - tests/Feature/Viabilidade/ConsultaViabilidadeCommandTest.php
    - tests/Feature/Viabilidade/ConsultaViabilidadeGoldenCaseTest.php
    - tests/Fixtures/golden/viabilidade/endereco-sem-zona-pendente.json
    - tests/Fixtures/golden/viabilidade/cnae-risco-real.json
    - tests/Fixtures/golden/viabilidade/inscricao-indisponivel.json
  modified: []

key-decisions:
  - "Fixture endereco-sem-zona usa veredito_motivo_contem 'zoneamento' (não 'zona'): o motivo REAL do motor na via endereço/zona-indisponível é 'Base de zoneamento pendente SEDUR' — o golden bate contra o dado real (anti-fachada), não contra a suposição do plano"
  - "parseArea do comando distingue ausente (null, válido — área é opcional na consulta) de inválido (false, exit 1) — diferente de louos:enquadrar onde a área é obrigatória"
  - "Comando traduz AddressNotFoundException/GeocoderException em erro honesto (exit 1); pendente por zona NÃO é erro (exit 0) — degradação honesta espelhando o controller 07-06"
  - "Teste adicional do comando por endereço com Geocoder fake + FakeSpatialRepository (cobre a ramificação --endereco em CI sem Nominatim); a evidência real por Nominatim fica no comando manual"

patterns-established:
  - "Golden cases de orquestrador (compõe motores): o harness injeta fakes (Geocoder/SpatialRepository) só para a entrada por endereço; CNAE e inscrição rodam o caminho real (inscrição degrada pelo binding real)"

# Metrics
duration: ~15 min
completed: 2026-06-14
---

# Phase 7 Plan 10: Golden Cases + Comando de Evidência + Verificação Integral Summary

**A Fase 7 (Consulta Prévia de Viabilidade) fechada com regressão de domínio + evidência real + verificação integral fresca: o comando `viabilidade:consultar` (CNAE/endereço/inscrição) imprime o parecer fundamentado de ponta a ponta do orquestrador REAL, e os golden cases (#[DataProvider]) exercem o `ConsultaViabilidadeService` sobre o seed oficial provando a degradação honesta — `endereco-sem-zona-pendente` trava o veredito `pendente` (motivo cita zoneamento, nunca permitido/não permitido sem a base de zona) e `inscricao-indisponivel` prova que a inscrição degrada com aviso sem inventar ponto (geocode/território nulos). Verificação fresca: suíte SQLite 657/657, grupo postgis 15/15 (EXECUTADO, não skip), pint/typecheck/build verdes, `migrate:fresh --seed` no Postgres sem erros, evidência real do comando (CNAE → Baixo Risco B + Fluxo expresso + Quadro 7 nR1, veredito pendente; endereço → Nominatim real + território identificado + zona pendente → veredito pendente) e auditoria das consultas (RN-002). Smoke navegável é o único item remanescente (checkpoint humano).**

## Performance

- **Duration:** ~15 min
- **Completed:** 2026-06-14
- **Tasks:** 2 auto (TDD estrito RED → GREEN → pint) + 1 checkpoint (verificação integral)
- **Files:** 6 criados, 0 modificados — ZERO dependência nova

## Accomplishments

- **Comando `viabilidade:consultar`** (auto-descoberto): evidência real do orquestrador para as 3 entradas, parecer fundamentado pt-BR (RISCO / ENQUADRAMENTO / VEREDITO LOCACIONAL / FUNDAMENTAÇÃO / AVISOS / VERSÕES).
- **Golden cases da consulta** (#[DataProvider]): 3 fixtures entrada→esperado batendo contra o orquestrador real + seed oficial — regressão de domínio (precedente Fases 5/6).
- **Casos-âncora anti-fachada:** sem zona → `pendente` (motivo cita zoneamento); inscrição indisponível → degrada com aviso, sem geocode/território (nunca inventa ponto).
- **Verificação integral fresca** da Fase 7 com evidência (abaixo), incluindo a linha que prova que o grupo postgis EXECUTOU (não pulou).

## Task Commits

Cada task auto foi commitada atomicamente (TDD: RED → GREEN → pint):

1. **Task 1: comando viabilidade:consultar (evidência real)** — `bc5fc01` (feat)
2. **Task 2: golden cases da consulta + 3 fixtures** — `613c6f9` (test)

**Plan metadata:** este SUMMARY (`docs(07-10)`).

## Verificação integral — EVIDÊNCIA FRESCA (output lido por inteiro)

Regra de verificação antes de completar: cada comando rodado fresco e o output inteiro lido antes de afirmar.

### 1. Pint (`vendor/bin/pint --dirty --format agent`)
```
{"tool":"pint","result":"passed"}
```

### 2. Suíte SQLite (`php artisan test --compact --exclude-group postgis`)
```
{"tool":"phpunit","result":"passed","tests":657,"passed":657,"assertions":3337,"duration_ms":24187}
```
**657/657 verde** = 650 baseline (07-09) + 7 novos (4 do comando + 3 golden cases). **Zero regressão.**

### 3. Grupo postgis (território real) — EXECUTADO, não skip
```
Banco sile_testing já existe.
Extensão postgis garantida em sile_testing.
{"tool":"phpunit","result":"passed","tests":15,"passed":15,"assertions":85,"duration_ms":2843}
```
**15/15 verde** — a linha `"tests":15,"passed":15,"assertions":85,"duration_ms":2843` prova que os testes @group postgis RODARAM contra o PostGIS real (não foram marcados como skipped).

### 4. Frontend (`npm run typecheck` / `npm run build`)
```
> tsc --noEmit        (sem erros TS)

public/build/assets/consulta-CYutg_an.js   9.21 kB │ gzip: 3.31 kB
public/build/assets/map-imovel-sxaahK9J.js 154.86 kB │ gzip: 45.48 kB
public/build/assets/app-DGUvNu7C.js        321.63 kB │ gzip: 101.37 kB
✓ built in 613ms
```

### 5. Banco Postgres dev (`php artisan migrate:fresh --seed`)
Todos os seeders concluídos sem erro (RolesAndPermissions, LegalTerm, Parameter, Cnae, RiscoMunicipal, RiscoSanitario, RiskTrigger, LouosQuadro7/10/11, DevAdmin, Company, GeoLayer) — todos `DONE`, exit 0.

### 6. Evidência real do comando `viabilidade:consultar`

**6a. CNAE de baixo risco → Fluxo expresso + Quadro 7 (veredito pendente honesto):**
`php artisan viabilidade:consultar 4712-1/00 --area=120`
```
Consulta prévia de viabilidade
Tipo de entrada: CNAE
CNAE 4712-1/00 — Comércio varejista de mercadorias em geral, com predominância de produtos alimentícios - minimercados, mercearias e armazéns
Área pretendida: 120 m²

RISCO
 Risco municipal (Decreto 32.636/2020): Baixo Risco B
 Risco sanitário (VISA): Baixo Risco
 Encaminhamento: Fluxo expresso — Nível baixo_b (municipal) elegível ao fluxo expresso

ENQUADRAMENTO POR ÁREA (Quadro 7)
 Grupo de uso: nR1 / Subgrupo: nR1-01
 Faixa de área: 0 a 350 m²

VEREDITO LOCACIONAL
 Resultado: Pendente de análise técnica
 Motivo: Permissão por zona pendente da base oficial (SEDUR)

FUNDAMENTAÇÃO LEGAL
 - Lei nº 9.148/2016 (LOUOS) — Quadro 7
 - Permissão por zona pendente da base oficial (SEDUR)
 - Decreto Municipal nº 32.636/2020
 - Classificação de risco sanitário (Vigilância Sanitária)

AVISOS
 - Consulta por CNAE não avalia o local: o veredito locacional depende do endereço/zona. Para a viabilidade locacional, consulte por endereço.

Versões de regras aplicadas:
 territorio: — (não consultado)
 louos: quadro7=lei-9148-2016-quadro7 | quadro10=— | quadro11=— | quadro11a=—
 risco: municipal=decreto-32636-2020 | sanitario=visa-unificada-2026-04-30
```

**6b. Endereço real (Nominatim) → território identificado + zona pendente → veredito pendente:**
`php artisan viabilidade:consultar 4712-1/00 --endereco="Praça da Sé, Salvador" --area=120`
```
Consulta prévia de viabilidade
Tipo de entrada: Endereço
CNAE 4712-1/00 — Comércio varejista de mercadorias em geral, com predominância de produtos alimentícios - minimercados, mercearias e armazéns
Endereço: Praça da Sé, Salvador
Área pretendida: 120 m²

RISCO
 Risco municipal (Decreto 32.636/2020): Baixo Risco B
 Risco sanitário (VISA): Baixo Risco
 Encaminhamento: Fluxo expresso — Nível baixo_b (municipal) elegível ao fluxo expresso

ENQUADRAMENTO POR ÁREA (Quadro 7)
 Grupo de uso: nR1 / Subgrupo: nR1-01
 Faixa de área: 0 a 350 m²

VEREDITO LOCACIONAL
 Resultado: Pendente de análise técnica
 Motivo: Base de zoneamento pendente SEDUR

FUNDAMENTAÇÃO LEGAL
 - Lei nº 9.148/2016 (LOUOS) — Quadro 7
 - Base de zoneamento pendente SEDUR
 - Decreto Municipal nº 32.636/2020
 - Classificação de risco sanitário (Vigilância Sanitária)

AVISOS
 - Veredito locacional pendente: zona urbanística pendente da base oficial (SEDUR).

Versões de regras aplicadas:
 territorio: bairro=geosalvador-bairros-dec38776-2024 | via=geosalvador-logradouros-centro | zona=pendente-sedur | lote=pendente-sedur | restricoes=geosalvador-pddu2016-zeis
 louos: quadro7=lei-9148-2016-quadro7 | quadro10=— | quadro11=— | quadro11a=—
 risco: municipal=decreto-32636-2020 | sanitario=visa-unificada-2026-04-30
```
O território foi identificado de verdade (bairro/via/restrições reais do GeoSalvador), `zona=pendente-sedur` e o veredito é `Pendente de análise técnica` — NUNCA permitido/não permitido sem o dado de zona.

### 7. Auditoria das consultas (RN-002)
`php artisan tinker --execute '...Activity::where("log_name","viabilidade")->count()...'`
```
viabilidade audit count: 2
ultimo event/result: consulta/sucesso
tipo/veredito: endereco/pendente
```
As 2 consultas reais (CNAE + endereço) foram auditadas — count > 0, último registro `consulta/sucesso`, `endereco/pendente`.

### Golden cases (`php artisan test --compact --filter=ConsultaViabilidadeGoldenCaseTest`)
```
{"tool":"phpunit","result":"passed","tests":3,"passed":3,"assertions":15}
```
3 casos verdes batendo contra o seed oficial.

## ENTREGUE vs BLOQUEADO (insumo para o orquestrador atualizar STATE/ROADMAP)

### ENTREGUE — Fase 7 completa, lógica real de ponta a ponta
- **Consulta por endereço (HU-054):** geocodifica real (Nominatim) → identifica território → enquadra (LOUOS) → classifica risco → parecer fundamentado. Provado por evidência real (6b) + postgis.
- **Consulta por CNAE (HU-056):** risco real (Decreto 32.636/2020 + VISA) + Quadro 7 por área, sem local. Provado por evidência real (6a) + golden `cnae-risco-real`.
- **Consulta por inscrição (HU-055):** degrada honesto (base de lotes pendente SEDUR) com aviso, sem inventar ponto. Provado por golden `inscricao-indisponivel`.
- **Simulações (HU-057 enquadramento / HU-058 risco / HU-059 restrições):** views do mesmo `ConsultaViabilidadeResult` (UI 07-08).
- **Histórico do próprio usuário (HU-060):** snapshot imutável escopado ao dono, autenticado (07-07/09).
- **Infra da consulta:** endpoints públicos com throttle + toggle + auditoria RN-002 (07-06); UI pública (mapa + resultado honesto) e UI do histórico; comando de evidência; golden cases de regressão.

### BLOQUEADO / pendente SEDUR (degrada honesto, registrado — Fase 13)
- **Veredito locacional permitido/não permitido (Quadro 10 — zona urbanística):** depende da base oficial de zona (SIGIS/CA 2000), pendente SEDUR. Enquanto isso, o veredito degrada para `pendente` com motivo "Base de zoneamento pendente SEDUR" — NUNCA permitido/não permitido sem o dado real. Quando a base chegar, muda a carga, não a lógica.
- **Resolução por inscrição imobiliária (lote / Cadastro Multifinalitário):** contrato `PropertyRegistryLookup` pronto; o binding real é `UnavailablePropertyRegistryLookup` (degrada). A Fase 13 (HU-106) troca SÓ o binding pela base oficial, sem tocar call sites.

## Files Created

- `app/Console/Commands/ConsultaViabilidadeCommand.php` — comando `viabilidade:consultar` (CNAE/endereço/inscrição), parecer fundamentado, tradução de erros do geocoder, pendente não-erro.
- `tests/Feature/Viabilidade/ConsultaViabilidadeCommandTest.php` — 4 testes (CNAE real, CNAE inválido, inscrição degrada com aviso, endereço com geocoder fake).
- `tests/Feature/Viabilidade/ConsultaViabilidadeGoldenCaseTest.php` — harness #[DataProvider] sobre o orquestrador real + assertGolden por chave.
- `tests/Fixtures/golden/viabilidade/endereco-sem-zona-pendente.json` — endereço geocodifica mas sem zona → pendente (motivo cita zoneamento).
- `tests/Fixtures/golden/viabilidade/cnae-risco-real.json` — risco + Quadro 7 reais sem avaliar o local.
- `tests/Fixtures/golden/viabilidade/inscricao-indisponivel.json` — inscrição degrada sem inventar ponto.

## Decisions Made

- **Golden bate contra o dado real, não contra a suposição do plano:** o plano pediu `veredito_motivo_contem 'zona'`, mas o motivo REAL na via endereço/zona-indisponível é "Base de zoneamento pendente SEDUR" — ajustei o esperado para `'zoneamento'` (substring fiel e mais específica). Anti-fachada: o golden encoda o comportamento real verificado.
- **`parseArea` distingue ausente de inválido:** na consulta a área é OPCIONAL (a via CNAE roda sem área); ausência → `null` (válido), valor não numérico/≤0 → erro (exit 1). Diferente de `louos:enquadrar` (área obrigatória).
- **Pendente NÃO é erro (exit 0):** o comando só sai com erro (exit 1) em CNAE inválido, endereço não localizado ou geocodificação indisponível — degradação honesta espelhando o controller 07-06.

## Deviations from Plan

Sem bug de produção, correção crítica ou mudança arquitetural no escopo do plano. Ajustes para honrar o dado real e reforçar a cobertura (mesmos `files_modified` do 07-10, zero scope creep, zero dependência nova):

**1. [Realidade do motor] Fixture endereco-sem-zona: `veredito_motivo_contem` `'zona'` → `'zoneamento'`**
- **Found during:** Task 2 (RED → ajuste do fixture).
- **Issue:** o plano supôs o motivo contendo "zona"; o motivo REAL do motor na via endereço (território presente, zona indisponível) é "Base de zoneamento pendente SEDUR" — contém "zoneamento", não "zona".
- **Fix:** ajustei o esperado da fixture para `'zoneamento'` (o golden deve bater contra o dado real). O aviso da mesma fixture (`avisos_contem 'zona'`) bate contra "zona urbanística pendente…" e foi mantido.
- **Files:** `tests/Fixtures/golden/viabilidade/endereco-sem-zona-pendente.json`.

**2. [Aditivo] 4º teste do comando — caminho `--endereco` com Geocoder fake**
- **Motivo:** o plano nomeou 3 testes (CNAE/inválido/inscrição) mas previu "Geocoder fake bind p/ --endereco". Adicionei `test_comando_por_endereco_geocodifica_e_fica_pendente_sem_zona` (fake Geocoder + FakeSpatialRepository sem zona) para cobrir a ramificação `--endereco` em CI sem chamar Nominatim — a evidência real por Nominatim fica no comando manual (6b).
- **Files:** `tests/Feature/Viabilidade/ConsultaViabilidadeCommandTest.php`.

**Total:** 0 correções de produção; 1 ajuste de fixture (fidelidade ao dado real) + 1 teste aditivo. Sem scope creep.

## Issues Encountered

- **Cache Redis stale na evidência por endereço (resolvido — operacional, não regressão):** a primeira execução de `viabilidade:consultar --endereco` lançou `TypeError: NominatimGeocoder::geocode() ... __PHP_Incomplete_Class returned`. Causa raiz (debugging sistemático): o cache é Redis e o `NominatimGeocoder` cacheia o objeto `GeocodeResult`; havia uma entrada serializada por uma versão anterior da classe, deixada pelo `composer run dev` em execução desde 11/06 (`migrate:fresh` não limpa o Redis). `php artisan cache:clear` resolveu e a evidência real passou a rodar (6b). **Não é regressão do 07-10** (o comando só chama o serviço existente) nem do orquestrador; é higiene de cache do ambiente de longa duração.
  - **Nota de robustez para o orquestrador (Fase 4/13):** cachear DTOs de domínio (`GeocodeResult`, e o `CnpjData` do [03-02]) em Redis os torna sensíveis a `__PHP_Incomplete_Class` quando a classe muda entre deploys. Mitigação operacional já hoje: `php artisan cache:clear` no deploy. Endurecimento opcional (fora do escopo 07-10, exige TDD próprio em Fase 4): cachear o array bruto e reconstruir o DTO fora do closure. Registrado para decisão — não silenciado.

## Smoke navegável — checkpoint humano remanescente (Task 3, gate blocking)

A verificação integral automatizável está COMPLETA e verde (acima). Resta o **smoke navegável** (inspeção em navegador), que é inerentemente humano e não foi executado por este agente:

- **Anônimo:** abrir `/portal/viabilidade`, consultar por endereço de Salvador → mapa com o ponto; risco + enquadramento reais; veredito "Pendente de análise técnica" com motivo de zona (NUNCA Permitido/Não permitido). Testar CNAE e inscrição (aviso de indisponível). Conferir mobile (375px) e dark mode.
- **Autenticado:** logar no portal, refazer uma consulta, abrir `/portal/viabilidade/historico` → a consulta aparece; abrir o snapshot; confirmar que outro usuário não vê.

Servidor `composer dev` disponível. A evidência automatizada (suíte completa com motores reais via 07-06/07/08/09 + evidência do comando de ponta a ponta) prova o fluxo no nível de framework; a confirmação visual (mapa renderizado, dark mode, mobile, veredito honesto na tela) é o passo final do checkpoint.

## Next Phase Readiness

- **Fase 8 (Solicitação formal):** a consulta prévia entrega o parecer real (risco + enquadramento + veredito propagado) que a solicitação formal consome; o orquestrador (`ConsultaViabilidadeService`) permanece puro/reutilizável (HU-141).
- **Bloqueios herdados (não introduzidos aqui):** veredito permitido/não permitido depende da base de zona (SIGIS/CA 2000, Fase 13); resolução por inscrição depende da base de lotes (contrato 07-02, Fase 13 troca só o binding). Ambos degradam honestamente — provado pelos golden cases e pela evidência real.
- **Robustez de cache de DTOs (Fase 4/13):** ver Issues Encountered — endurecimento opcional do cache do geocoder/CNPJ.

---
*Phase: 07-consulta-previa-viabilidade*
*Completed: 2026-06-14*
