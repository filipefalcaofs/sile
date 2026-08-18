---
phase: 05-motor-louos
plan: 04
subsystem: motor
tags: [louos, motor, quadro10, quadro11, quadro11a, degradacao, anti-fachada, tdd, sqlite]

# Dependency graph
requires:
  - phase: 05-motor-louos
    plan: 03
    provides: "LouosEnquadramentoService::enquadrar + helpers dimIdentificado/dimNaoEncontrado/dimIndisponivel + resolveVersion (3 modos) + placeholders honestos de quadro10/11/11a"
  - phase: 05-motor-louos
    plan: 02
    provides: "Quadros 10/11/11A modelados como versões vigentes (lei-9148-2016-quadro10/11/11a)"
  - phase: 04-georreferenciamento
    provides: "TerritoryResult (zona/via com status identificado|nao_encontrado|indisponivel) — a zona/via vêm daqui; indisponivel quando a base pende SEDUR"
provides:
  - "enquadrarQuadro10(quadro7, input): permissão por (zona × grupo de uso) na versão vigente, com degradação honesta sem zona"
  - "enquadrarCondicoesVia(domain, quadro7, input): Quadros 11 e 11A (condições pela via) compartilhando a lógica pelo domínio"
  - "Contrato final das dimensões territoriais (quadro10/quadro11/quadro11a) e a chave de propriedade de zona e de classe viária LOUOS"
  - "Degradação anti-fachada com teste nomeado nos dois caminhos (zona indisponível; via sem atributo viário LOUOS)"
affects: [05-05-motor, 05-09-golden-cases, 07-consulta-previa, 08-solicitacao]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Motor RECEBE o território (TerritoryResult), não o consulta — degrada sobre o dado recebido, espelhando TerritoryService::isBlocked"
    - "Degradação anti-fachada: status indisponivel SEM consultar a tabela nem inventar permissão/condição, força o consolidado a pendente (05-05)"
    - "Lookup com precedência subgrupo→grupo (Q10) e grupo→geral (Q11): regra específica vence, cai para a regra geral (espelha o seed com subgrupo/grupo vazio)"

key-files:
  created: []
  modified:
    - app/Services/Louos/LouosEnquadramentoService.php
    - tests/Feature/Louos/LouosEnquadramentoTerritorioTest.php

key-decisions:
  - "Ordem de checagem do Quadro 10: degradação da zona PRIMEIRO (anti-fachada central), depois a pré-condição do Quadro 7 — segue a ordem literal do plano"
  - "Discriminador anti-fachada nos testes: degradação retorna indisponivel; uma consulta frustrada retornaria nao_encontrado — asserir indisponivel prova que a tabela não foi consultada"
  - "Shape estável: todas as dimensões do Quadro 10 carregam permissao/condicionante_ref/base_legal (null ao degradar); todas as do Quadro 11/11A carregam classe_via/condicoes/base_legal (condicoes [] ao degradar)"
  - "Classe viária lida SOMENTE de propriedades['CLASSE_VIA_LOUOS'] — o atributo pende SEDUR; o motor degrada e nunca infere a classe a partir da geometria"
  - "Nome da zona: precedência territory.zona['nome'] → propriedades ZONA/zona/SIGLA_ZONA (atributo a confirmar no wiring do EP07)"
  - "Quadros 11 e 11A compartilham enquadrarCondicoesVia(domain, ...) — o domínio (LouosQuadro11/11a) distingue a versão e a tabela é a mesma"

patterns-established:
  - "Dimensão territorial degrada com versao_regra null (não consultou versão); só preenche a versão quando chega a consultar a tabela (nao_encontrado/identificado)"

# Metrics
duration: 16min
completed: 2026-06-14
---

# Phase 5 Plan 04: Motor dos Quadros 10 e 11/11A da LOUOS Summary

**O motor ganhou as dimensões territoriais: o Quadro 10 decide a permissão por (zona × grupo de uso) quando há zona e DEGRADA honestamente quando a zona é indisponível (base de zoneamento pendente SEDUR) — sem consultar a tabela nem inventar permissão; os Quadros 11/11A aplicam as condições pela via quando o atributo de classificação viária LOUOS existe e degradam enquanto ele pende. O motor RECEBE o território (TerritoryResult da Fase 4), não o consulta. O consolidado segue pendente (05-05 fecha a regra).**

## Performance

- **Duration:** ~16 min
- **Tasks:** 2 (cada uma TDD RED → GREEN → pint)
- **Files:** 2 modificados (service + teste do território, criado)
- **Commits:** f06029e (Quadro 10), 95c6e3c (Quadros 11/11A) + docs deste SUMMARY

## Contrato entregue (insumo direto de 05-05 e 05-09)

### Dimensão `quadro10` — `{status, permissao, condicionante_ref, base_legal, motivo, versao_regra}`
- **Identificado:** `permissao` ∈ {`permitido`, `permitido_condicionado`, `proibido`} (value de `Quadro10Permissao`), `condicionante_ref` (ex.: `CU-01`), `base_legal`, `motivo` null, `versao_regra`.
- **Indisponível (degradação — anti-fachada):** sem `input.territory` OU `zona['status'] !== 'identificado'` → `permissao/condicionante_ref/base_legal` null, `motivo` = motivo da zona ou `'Permissão por zona pendente da base oficial (SEDUR)'`, `versao_regra` null. **NÃO consulta `louos_quadro10_permissoes`.**
- **Indisponível (pré-condição):** Quadro 7 não identificado → `motivo` `'Sem enquadramento (Quadro 7) não há permissão a verificar'`, `versao_regra` null.
- **Não encontrado:** versão existe, sem linha para (zona, grupo) → `motivo` `'Combinação zona × grupo de uso sem regra no Quadro 10 vigente'`, `versao_regra` = versão.

### Dimensões `quadro11` / `quadro11a` — `{status, classe_via, condicoes, base_legal, motivo, versao_regra}`
- **Identificado:** `classe_via` (do atributo), `condicoes` (array livre — recuos, vagas, estudo de tráfego), `base_legal`, `motivo` null, `versao_regra`.
- **Indisponível (degradação):** sem território OU `via['status'] !== 'identificado'` → `classe_via` null, `condicoes` `[]`, `motivo` = motivo da via ou `'Condições pela via dependem da classificação viária LOUOS (pendente SEDUR)'`, `versao_regra` null.
- **Indisponível (sem atributo — anti-fachada, caso atual):** via identificada SEM `CLASSE_VIA_LOUOS` → `motivo` `'Via identificada, porém sem o atributo de classificação viária LOUOS (pendente SEDUR)'`, `condicoes` `[]`, `versao_regra` null. **NUNCA infere a classe da geometria.**
- **Não encontrado:** classe presente, sem linha → `classe_via` setado, `condicoes` `[]`, `motivo` `'Classe viária × grupo de uso sem regra no quadro de via vigente'`, `versao_regra` = versão.

### Chave de propriedade do território (contrato do wiring no EP07)
- **Zona (Quadro 10):** precedência `territory.zona['nome']` → `propriedades['ZONA' | 'zona' | 'SIGLA_ZONA']`. Atributo exato a confirmar com a base oficial da SEDUR; sem ele a busca degrada para `nao_encontrado`, nunca inventa zona.
- **Classe viária (Quadros 11/11A):** SOMENTE `via.propriedades['CLASSE_VIA_LOUOS']`. Atributo pendente SEDUR (a Fase 4 entregou a geometria viária, não a classificação LOUOS).

### Resolução de versão e busca
- `resolveVersion(domain, input)` reusado do 05-03 (3 modos: vigente / na data / versão específica do sandbox HU-143), aplicado por domínio (`LouosQuadro10`, `LouosQuadro11`, `LouosQuadro11a`).
- **Quadro 10:** busca por (rule_version_id, zona, grupo_uso = `quadro7['grupo']`); prefere a regra do subgrupo específico, caindo para a regra geral do grupo (subgrupo vazio/nulo).
- **Quadro 11/11A:** busca por (rule_version_id, classe_via); prefere o grupo de uso específico, caindo para a regra geral da classe (grupo vazio/nulo).
- **Auditoria (RN-002/RN-004):** `enquadrar()` audita uma vez; `versoes` mantém a `versao_regra` consultada por quadro. A dimensão degradada carrega `versao_regra` null (não chegou a consultar a versão).

### Consolidado (inalterado — escopo do 05-05)
- `consolidar()` permanece `pendente` fixo, recebendo as 4 dimensões. Com zona/via degradadas (caso atual), o veredito honesto continua `pendente`. **05-05 reescreve** a regra final (proibido → nao_permitido, condicionado → permitido com condicionantes, etc.).

## Evidência (verificação fresca)

- `php artisan test --compact --filter=LouosEnquadramentoTerritorioTest` → **9 passed** (36 assertions).
- `php artisan test --compact --filter=Louos` → **55 passed** (354 assertions).
- `php artisan test --compact --exclude-group postgis` → **574 passed** (2.937 assertions), zero falhas. Cadeia: 563 baseline → 565 (05-07 paralelo, +2 testes de UI) → **574 (05-04, +9)**.
- `vendor/bin/pint --dirty --format agent` → passed em cada task.
- Critérios de aceite (grep) confirmados: `enquadrarQuadro10`, `enquadrarCondicoesVia`, `LouosQuadro10Permissao::`, `CLASSE_VIA_LOUOS`, `STATUS_INDISPONIVEL`.

## Anti-fachada (RED confirmado nos dois caminhos de degradação)

- **`test_zona_indisponivel_degrada_quadro10_sem_consultar_tabela` (CENTRAL):** com Quadro 7 identificado E uma permissão no Quadro 10 que CASARIA, a zona `indisponivel` → `quadro10` `indisponivel`, `permissao` null. RED original falhou com o motivo do placeholder (`'Dimensão ainda não avaliada...'`); como a degradação retorna `indisponivel` (e uma consulta com zona nula retornaria `nao_encontrado`), o status prova que a tabela NÃO foi consultada.
- **`test_via_sem_atributo_louos_degrada_quadro11`:** via identificada SEM `CLASSE_VIA_LOUOS`, mesmo com condição cadastrada que casaria → `quadro11` `indisponivel`, `condicoes` `[]`. RED original falhou (`condicoes` null no placeholder); a classe nunca é inferida da geometria.

## Testes (9, TDD)

**Task 1 — Quadro 10 (degradação sem zona):**
1. `test_zona_indisponivel_degrada_quadro10_sem_consultar_tabela` — âncora anti-fachada.
2. `test_territorio_nulo_degrada_quadro10` — território nulo → indisponivel (motivo default).
3. `test_zona_identificada_retorna_permissao_permitido` — zona + grupo com regra → identificado/permitido.
4. `test_zona_identificada_retorna_proibido` — RN-005: combinação proibida → identificado/proibido.
5. `test_zona_identificada_sem_regra_retorna_nao_encontrado` — versão existe, sem linha → nao_encontrado.
6. `test_sem_enquadramento_quadro7_nao_avalia_quadro10` — Quadro 7 nao_encontrado → indisponivel (pré-condição).

**Task 2 — Quadros 11/11A (condições pela via):**
7. `test_via_sem_atributo_louos_degrada_quadro11` — âncora anti-fachada do atributo pendente.
8. `test_via_indisponivel_degrada_quadro11_e_11a` — via indisponivel → ambos indisponivel.
9. `test_classe_via_presente_retorna_condicoes_quadro11_e_11a` — cenário futuro: classe presente → identificado com condicoes nos dois quadros.

## Deviations from Plan

### Decisões de shape (não-deviation)
- **base_legal em todas as dimensões:** o plano lista `base_legal` no caso identificado; mantive a chave (null ao degradar) em todos os casos do Quadro 10 e 11/11A para shape estável (consumido por 05-05 e UI). Sem isso o consolidado teria de checar a existência da chave por status.
- **`SIGLA_ZONA` como chave extra de zona:** além de `nome`/`ZONA`/`zona` citadas no plano, incluí `SIGLA_ZONA` (comum em camadas GIS) como fallback — o atributo final é a confirmar com a SEDUR (documentado acima). Nenhuma invenção de dado: ausente → degrada.

### Caso de borda coberto por consistência (não-deviation)
- **Versão de via inexistente → `nao_encontrado`** (`'Quadro de condições pela via sem versão vigente'`), espelhando o tratamento do Quadro 7 do 05-03. Não acontece com os seeds atuais (sempre publicam versão), mas evita decisão ambígua.

**Total deviations:** nenhuma de código; plano executado como escrito.

## Issues Encountered

- **Execução paralela no mesmo working directory (05-07 UI):** o executor do 05-07 commitou `5876f41`, `09ab871` e `5ff7ac8` (tela + teste de componente Inertia dos Quadros) interleaved com os meus `f06029e`/`95c6e3c`. Mitigação: **commit atômico com `git add` por caminho explícito** (apenas os 2 arquivos do 05-04 em cada commit), nunca `-A`/`.`. A suíte fresca do HEAD combinado está verde (574/574); os +2 testes de UI do 05-07 explicam a diferença sobre o baseline (563 + 2 + 9 = 574).

## Next Plan Readiness (05-05)

- **05-05** reescreve `consolidar()` (que já recebe as 4 dimensões com o shape final acima) com o veredito fundamentado: Quadro 7 identificado + Quadro 10 `proibido` → `nao_permitido` (RN-005); `permitido_condicionado` → permitido com a condicionante (`condicionante_ref`); Quadro 10/11/11A `indisponivel` → `pendente` (degradação preservada — sem zona/atributo real, segue pendente até a base da SEDUR chegar). As condições do Quadro 11/11A (`condicoes`) entram como condicionantes do parecer quando identificadas.
- **05-09** (golden cases): casos `requer_zona` devem esperar `pendente` enquanto a zona é indisponível; os caminhos identificado/proibido/condicionado já têm a lógica do Quadro 10 montável com TerritoryResult sintético.

---
*Phase: 05-motor-louos*
*Completed: 2026-06-14*
