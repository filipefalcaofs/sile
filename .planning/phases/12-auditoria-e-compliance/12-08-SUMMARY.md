---
phase: 12-auditoria-e-compliance
plan: 08
subsystem: abuso
tags: [hu-149, abuse-detection, detectores-estruturais, poligono, inscricao, condicionante, idempotencia, tag-aditiva, tdd, sqlite]

# Dependency graph
requires:
  - phase: 12-auditoria-e-compliance
    provides: "12-06 — contrato AbuseDetector + DTOs AbuseFinding/DetectionWindow + AbuseDetectionService (toggle-gated, idempotente) + tag 'abuse.detectors'"
  - phase: 12-auditoria-e-compliance
    provides: "12-03 — ledger abuse_alerts (índice único parcial), enums AbuseSeverity/AbuseAlertStatus, parâmetros abuso.* + toggle features.deteccao_abuso (OFF)"
  - phase: 08-solicitacao-de-viabilidade
    provides: "ViabilityRequest (property_polygon_geojson, property_registration, CNAEs pivot) + simulation_snapshot (por_cnae.consulta.risco.sanitario.condicionantes_perguntas)"
provides:
  - "3 detectores ESTRUTURAIS determinísticos reais (poligono_repetido, inscricao_atividades_incompativeis, condicionante_evasao) sobre dado real, SEM IA"
  - "Conjunto mínimo de 5 detectores plugados na tag 'abuse.detectors' (2 de volume + 3 estruturais) — o AbuseDetectionService passa a iterar 5 sem outra mudança"
affects:
  - "12-09 (painel de alertas + efetividade): consome abuse_alerts por rule_key (poligono_repetido/inscricao_atividades_incompativeis/condicionante_evasao) e a evidence de cada um"
  - "12-12 (smoke/seed dev): pode semear os padrões estruturais reais para exercitar o pipeline"

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Detector estrutural sobre dado real com CHAVE NORMALIZADA em PHP (hash do anel de polígono canonicalizado) — portável SQLite/Postgres, sem PostGIS"
    - "Tag de container ADITIVA confirmada: 3 novos detectores entram na mesma tag 'abuse.detectors' sem tocar o AbuseDetectionService (12-06)"
    - "Proxy honesto e PROVISÓRIO onde a regra oficial é pendência SEDUR (matriz de incompatibilidade de CNAEs; definição de 'sempre evita'), documentado para calibração"
    - "Fingerprint estável por (rule_key, identidade) — polígono/inscrição/requerente; a janela decide SE emite, não o fingerprint (idempotência 12-03)"

key-files:
  created:
    - "app/Services/Abuso/Detectors/PoligonoRepetidoDetector.php"
    - "app/Services/Abuso/Detectors/InscricaoAtividadesIncompativeisDetector.php"
    - "app/Services/Abuso/Detectors/CondicionanteEvasaoDetector.php"
    - "tests/Feature/Abuso/AbuseDetectorsEstruturaisTest.php"
  modified:
    - "app/Providers/AppServiceProvider.php"

key-decisions:
  - "Equivalência de polígono por ANEL NORMALIZADO (arredondamento 6 casas + remoção do vértice de fechamento + rotação ao vértice mínimo + sentido/winding canônico): casa representações diferentes do MESMO anel sem banco. ST_Equals (igualdade espacial plena) é refinamento futuro registrado"
  - "ZERO parâmetro novo (12-03 é dona do ParameterSeeder): os mínimos são constantes determinísticas honestas (2 endereços / 2 CNAEs / 3 processos), documentadas como PROVISÓRIAS para a SEDUR parametrizar depois"
  - "Inscrição: proxy de 'atividades incompatíveis' = ≥2 CNAEs primários distintos simultâneos na mesma inscrição (a matriz oficial de incompatibilidade é pendência SEDUR); exclui canceladas (sem simultaneidade ativa)"
  - "Condicionante: lê o schema REAL do snapshot (condicionantes_perguntas[].resposta/acionou). 'Evasivo' = respondeu e NENHUMA acionou; 'honesto' = ao menos uma acionou; alerta só com ≥3 evasivos E zero honestos (sempre evita)"
  - "fix (bug Rule 1): o detector de inscrição agrupava CNAEs usando o código como CHAVE de array, e o PHP coage string numérica a int ('2896863' vira int, '0586860' fica string) — corrigido para agrupar por lista + array_unique, sem coerção (também blinda inscrições com zeros à esquerda)"

patterns-established:
  - "Conjunto mínimo de 5 detectores determinísticos (HU-149) cobrindo os padrões: volume (CNPJ, contador) + estruturais (polígono, inscrição, condicionante). EscritorioVirtualEncadeado fica para a 2ª onda"

# Metrics
duration: ~25min
completed: 2026-06-15
---

# Phase 12 Plan 08: Detectores estruturais de abuso (HU-149) — polígono, inscrição e evasão por condicionante Summary

**3 detectores ESTRUTURAIS determinísticos sobre dado REAL (SEM IA), espelhando os de volume da 12-06: `PoligonoRepetidoDetector` (mesmo polígono via hash do anel normalizado em endereços distintos — sem PostGIS), `InscricaoAtividadesIncompativeisDetector` (mesma inscrição com CNAEs primários distintos simultâneos — proxy provisório enquanto a matriz oficial é pendência SEDUR) e `CondicionanteEvasaoDetector` (requerente cujas respostas de condicionante sempre evitam a análise, lendo `resposta`/`acionou` do `simulation_snapshot`). Todos com fingerprint estável (idempotência), evidence honesta e janela respeitada — NUNCA punem nem transicionam status (RN-001). Acrescentados à tag aditiva `abuse.detectors`: o `AbuseDetectionService` passa a iterar os 5 sem outra mudança. Toggle OFF default.**

## Performance
- **Duration:** ~25 min
- **Completed:** 2026-06-15
- **Tasks:** 2 (TDD estrito RED→GREEN em cada)
- **Files:** 4 criados, 1 modificado
- **Par paralelo:** wave 3 com 12-07 no mesmo diretório — dono ÚNICO de `AppServiceProvider` na wave; staging seletivo por arquivo (nunca `git add -A`); pint só nos arquivos do plano

## Os 3 detectores estruturais (tag 'abuse.detectors')

| Detector | key | Agrupa por | Critério (limiar PROVISÓRIO) | subject | Severidade |
|---|---|---|---|---|---|
| `PoligonoRepetidoDetector` | `poligono_repetido` | hash do anel normalizado do `property_polygon_geojson` | ≥ **2 endereços distintos** com o mesmo polígono na janela | `null` (evidence carrega hash + endereços) | > 4 endereços → Alta, senão Média |
| `InscricaoAtividadesIncompativeisDetector` | `inscricao_atividades_incompativeis` | `property_registration` (exclui canceladas) | ≥ **2 CNAEs primários distintos** simultâneos na mesma inscrição | `null` (evidence carrega inscrição + CNAEs) | > 4 CNAEs → Alta, senão Média |
| `CondicionanteEvasaoDetector` | `condicionante_evasao` | `requester_user_id` | ≥ **3 processos evasivos** E **zero honestos** (sempre evita a análise) | `User` (requerente) | > 6 processos → Alta, senão Média |

- **`evidence`** (real, determinística):
  - polígono: `poligono_hash`, `enderecos` (lista), `enderecos_distintos`, `minimo`, `ids`.
  - inscrição: `inscricao`, `cnaes_primarios`, `cnaes_distintos`, `requerentes`, `minimo`, `ids`.
  - condicionante: `requester_user_id`, `processos`, `minimo`, `ids`.
- **`viabilityRequestId`** = processo mais recente do grupo (`max(ids)`) — dá à malha fina um processo concreto, como nos de volume.
- **`fingerprint`** estável por `hash('sha256', "{rule_key}|{identidade}")` (identidade = hash do polígono / inscrição / requester_user_id). A janela decide SE o finding é emitido (contagem dentro do período), não o fingerprint — idempotência diária da 12-03.

## Polígono: equivalência por anel normalizado (sem PostGIS)

A chave do polígono é o `sha256` do anel exterior **canonicalizado**: coordenadas arredondadas a 6 casas, vértice de fechamento removido, anel rotacionado ao vértice lexicograficamente mínimo e sentido (winding) escolhido pelo menor entre a sequência e a sua reversa. Assim duas representações do MESMO anel (vértice inicial ou winding diferentes) geram o mesmo hash — roda igual em SQLite e Postgres.

**Refinamento futuro registrado:** a igualdade espacial PLENA (`ST_Equals` — tolerância numérica, projeção, sobreposição parcial, polígonos "quase iguais") fica para quando o caminho PostGIS estiver no detector; hoje a equivalência é por anel idêntico normalizado.

## Critérios PROVISÓRIOS a validar com a SEDUR

> HU-149 nasce com toggle OFF (`features.deteccao_abuso=0`) e os limiares são defaults honestos até a SEDUR calibrar com casos reais (HU-149 Observações: "começar com 3–5 regras e medir efetividade"). Nenhum parâmetro novo foi criado (12-03 é dona do ParameterSeeder); os mínimos são constantes a serem parametrizadas depois.

1. **Polígono repetido** — mínimo de **2 endereços distintos** e a equivalência por anel normalizado. SEDUR define o mínimo e se quer tolerância espacial (ST_Equals).
2. **Inscrição com atividades incompatíveis** — hoje é o **proxy de simultaneidade** (≥2 CNAEs primários distintos na mesma inscrição). A **matriz oficial de incompatibilidade entre CNAEs** (quais pares realmente não coexistem) é **pendência SEDUR**; quando entregue, troca o proxy pela regra real.
3. **Evasão por condicionante** — mínimo de **3 processos** e a definição de "sempre evita" (todas as respostas não acionadoras). **Pendência honesta:** a captura das respostas de condicionante na simulação ainda não está plugada (o `SolicitacaoViabilityResolver` consulta pelo ponto, sem respostas → `resposta=null` hoje). O detector já lê o **schema real** (`simulation_snapshot.por_cnae[].consulta.risco.sanitario.condicionantes_perguntas[].resposta/acionou`) e **não gera falso positivo** enquanto não há respostas — dispara quando o fluxo capturar as respostas (ou em seed/teste). Não é fachada: a lógica é real, só muda a carga.

## Consumo da tag (5 detectores)

`AppServiceProvider` faz `$this->app->tag([VolumeCnpjDetector, VolumeContadorDetector, PoligonoRepetidoDetector, InscricaoAtividadesIncompativeisDetector, CondicionanteEvasaoDetector], 'abuse.detectors')` e o `AbuseDetectionService` (12-06) consome `$app->tagged('abuse.detectors')` — agora itera os **5** sem nenhuma alteração no serviço. O `EscritorioVirtualEncadeadoDetector` permanece FORA (2ª onda — depende de marcação estruturada; HU-139 é texto livre).

## Mapa CA → teste (HU-149)

| HU / RN | Teste | Status |
|---|---|---|
| Polígono repetido em endereços distintos (+ equivalência por ordem de vértices, janela, fingerprint) | `AbuseDetectorsEstruturaisTest` | verde |
| Inscrição com CNAEs primários distintos (+ ignora canceladas/nula, janela, fingerprint) | `AbuseDetectorsEstruturaisTest` | verde |
| Evasão por condicionante: sempre evita (+ resposta honesta descaracteriza, abaixo do mínimo, ignora sem resposta, janela, fingerprint) | `AbuseDetectorsEstruturaisTest` | verde |
| Pipeline itera os 5 detectores (tag resolve 5; execução idempotente) | `AbuseDetectorsEstruturaisTest` | verde |
| ANTI-REGRESSÃO 12-06 — serviço/comando com a tag de 5 | `AbuseDetectionServiceTest` (9 testes) | verde |
| RN-001 — só ALERTA, sem punição/transição | toda a suíte de abuso | verde |

## Task Commits

1. **Task 1: PoligonoRepetido + InscricaoAtividadesIncompativeis** — `fc9572c` (feat, TDD)
2. **Task 2: CondicionanteEvasao + registro dos 3 na tag + fix de coerção** — `713e3ca` (feat, TDD)

## Deviations from Plan

- **[Rule 1 — Bug] Coerção de chave numérica no detector de inscrição.** A primeira versão agrupava os CNAEs usando o código como CHAVE de array; o PHP coage string numérica canônica a int (ex.: `'2896863'` vira int, `'0586860'` fica string), o que o teste com dados aleatórios da factory expôs (array misto). Corrigido para agrupar por prefixo de string + lista com `array_unique` (também blinda inscrições com zeros à esquerda). Encontrado na Task 2; corrigido junto e coberto pelo teste de inscrição (agora determinístico). Demais: plano executado como escrito.

## Issues Encountered

- A randomicidade dos códigos de CNAE da factory revelou o bug de coerção acima — TDD pegou antes do GREEN da Task 2. Sem outros sustos.

## Evidência de verificação (output real, fresco)

- RED Task 1: `AbuseDetectorsEstruturaisTest` → **12 errors** (classes `PoligonoRepetidoDetector`/`InscricaoAtividadesIncompativeisDetector` ausentes — motivo certo).
- GREEN Task 1: `AbuseDetectorsEstruturaisTest` → **12 passed, 26 assertions**.
- RED Task 2: `AbuseDetectorsEstruturaisTest` → **7 errors** (`CondicionanteEvasaoDetector` ausente) + **3 failures** (tag com 2; pipeline criados=0; inscrição coerção) — motivos certos.
- GREEN Task 2: `"AbuseDetectorsEstruturaisTest|AbuseDetectionServiceTest"` → **29 passed, 92 assertions** (12-06 sem regressão).
- Testes de abuso (3 arquivos): `"AbuseDetectorsEstruturaisTest|AbuseDetectionServiceTest|AbuseDetectorsTest"` → **35 passed, 114 assertions**.
- `vendor/bin/pint --test` nos arquivos do plano → **passed**. `ReadLints` limpo.
- Suíte completa: `php artisan test --compact --exclude-group=postgis` → **1293 passed, 6627 assertions** (sem regressão; baseline 12-06 era 1253).
- `php artisan abuso:detectar` (dev real) → `Detecção de abuso desligada (features.deteccao_abuso=0).` exit 0 (no-op honesto; o container resolve o serviço com os 5 detectores tagueados sem erro).
- grep: `implements AbuseDetector` nos **5** detectores; os 3 estruturais na tag (`AppServiceProvider` linhas 106-108).

## Next Phase Readiness

- **12-09** (painel + efetividade): `abuse_alerts` agora recebe também `rule_key` ∈ {`poligono_repetido`, `inscricao_atividades_incompativeis`, `condicionante_evasao`}; a efetividade (RN-005) = confirmados ÷ gerados por rule_key. A `evidence` de cada um já traz os campos para a UI (hash/endereços, inscrição/CNAEs, requerente/processos).
- **12-12** (smoke/seed dev): pode semear os padrões reais (mesmo polígono em 2 endereços; mesma inscrição com 2 CNAEs; requerente com 3 snapshots evasivos) para exercitar o pipeline ON.
- **2ª onda:** `EscritorioVirtualEncadeadoDetector` (HU-139 é texto livre → depende de marcação estruturada de "escritório virtual encadeado").
- **Pendências SEDUR (registradas):** matriz de incompatibilidade de CNAEs; calibração dos mínimos (2/2/3) e da definição de "sempre evita"; captura das respostas de condicionante no fluxo de simulação (hoje `resposta=null`); refinamento espacial ST_Equals para o polígono.

---
*Phase: 12-auditoria-e-compliance*
*Completed: 2026-06-15*
