---
phase: 08-solicitacao-de-viabilidade
plan: 16
subsystem: testing
tags: [solicitacao-viabilidade, seeds, golden-case, smoke, comando-evidencia, anti-fachada, verificacao-integral, hu-061, hu-067, hu-068, hu-070, hu-141, hu-148]

# Dependency graph
requires:
  - phase: 08-solicitacao-de-viabilidade
    plan: "08"
    provides: "DocumentRequirementResolver (códigos-base foto-fachada/termo-concessao referenciados por code)"
  - phase: 08-solicitacao-de-viabilidade
    plan: "10"
    provides: "ProtocolarSolicitacaoService (número único + transição + bloqueio documental)"
  - phase: 08-solicitacao-de-viabilidade
    plan: "12"
    provides: "CancelarSolicitacaoService (estados canceláveis parametrizáveis)"
  - phase: 08-solicitacao-de-viabilidade
    plan: "09"
    provides: "SimulacaoSolicitacaoService (snapshot por CNAE, veredito propagado)"
  - phase: 07-consulta-previa-viabilidade
    plan: "(comando)"
    provides: "ConsultaViabilidadeCommand + ConsultaViabilidadeGoldenCaseTest (padrão de evidência/golden a espelhar)"
provides:
  - "database/seeders/ViabilityServiceTypeSeeder.php — catálogo mínimo de tipos de serviço (códigos estáveis)"
  - "database/seeders/DocumentRequirementSeeder.php — requisitos-base (foto-fachada, termo-concessao, contrato-locacao)"
  - "database/seeders/SolicitacaoDevSeeder.php — solicitações de exemplo via serviços reais (rascunho/protocolada/cancelada/contingência)"
  - "app/Console/Commands/SolicitacaoProtocolarCommand.php — evidência real do protocolo (solicitacao:protocolar)"
  - "tests/Feature/Solicitacao/SolicitacaoGoldenCaseTest.php + fixtures — regressão de domínio do fluxo"
  - "tests/Feature/Solicitacao/SolicitacaoSmokeTest.php — jornada de ponta a ponta pelo portal"
affects: [09-fluxo-expresso, 10-analise-tecnica, 13-integracoes]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Seeds dev com lógica real: solicitações de exemplo passam pelos serviços reais (Protocolar/Cancelar), dados fictícios com número/timeline/auditoria genuínos; idempotência por marcador estável em address_reference"
    - "Catálogos-base seedados por código estável (firstOrCreate por code preserva ajustes do admin) e referenciados pelo resolver via constante (DocumentRequirementResolver::CODE_FACHADA)"
    - "Golden cases do fluxo (#[DataProvider] + fixtures JSON em tests/Fixtures/golden/solicitacao) executando os serviços reais sobre o seed oficial — espelha Fases 5/6/7"
    - "Smoke de integração dirigindo a jornada inteira pelos ENDPOINTS reais (criar→imóvel→atividades→documento→simular→protocolar→consultar→cancelar) com território injetado por fake (bairro, zona pendente)"

key-files:
  created:
    - database/seeders/ViabilityServiceTypeSeeder.php
    - database/seeders/DocumentRequirementSeeder.php
    - database/seeders/SolicitacaoDevSeeder.php
    - app/Console/Commands/SolicitacaoProtocolarCommand.php
    - tests/Feature/Solicitacao/SolicitacaoProtocolarCommandTest.php
    - tests/Feature/Solicitacao/SolicitacaoGoldenCaseTest.php
    - tests/Feature/Solicitacao/SolicitacaoSmokeTest.php
    - tests/Fixtures/golden/solicitacao/rascunho-completo-protocola.json
    - tests/Fixtures/golden/solicitacao/rascunho-sem-fachada-bloqueia.json
    - tests/Fixtures/golden/solicitacao/protocolada-cancelavel.json
    - tests/Fixtures/golden/solicitacao/simulacao-sem-zona-pendente.json
  modified:
    - database/seeders/DatabaseSeeder.php
    - tests/Feature/Seeders/DatabaseSeederTest.php

key-decisions:
  - "Seeds dev usam os SERVIÇOS REAIS (ProtocolarSolicitacaoService/CancelarSolicitacaoService) para gerar número/timeline/auditoria genuínos — dados fictícios, lógica real (entrega-funcional); a simulação NÃO roda no seed para manter o seed SQLite-safe (territory exige PostGIS), o que não afeta o protocolo (que não depende de geo)."
  - "Idempotência das solicitações de exemplo por marcador estável em address_reference (não há chave natural); re-seed reusa o existente sem gerar número novo nem duplicar."
  - "Tabela cnae_document_requirement permanece VAZIA — planilha oficial pendente SEDUR (degradação honesta; o resolver valida os obrigatórios-base mesmo sem a carga por CNAE)."
  - "Catálogo de parâmetros mantido em 49 (08-12): NENHUM parâmetro novo nesta fase de fechamento."
  - "ViabilityRequest::create() não hidrata o default de banco do status (null em memória) → uso ViabilityRequest::factory()->draft() (com FKs explícitos para não criar empresa/usuário extra) no seeder e nos golden/smoke."
  - "Comando solicitacao:protocolar usa o created_by (ou requerente) como ator; bloqueios (doc/dados/transição) saem com exit 1 e aviso — sucesso com exit 0 imprime número/transição."

patterns-established:
  - "Comando de evidência de fluxo (solicitacao:protocolar) espelhando viabilidade:consultar/louos:enquadrar — molde para os comandos de evidência das Fases 9/10"
  - "Golden + smoke de fluxo de processo: golden ancora casos entrada→esperado por DataProvider; smoke encadeia a jornada inteira pelos endpoints reais — molde para fechar fases de processo"

# Metrics
duration: ~50 min (com leitura de contexto e debug do status em seed)
completed: 2026-06-14
---

# Phase 8 Plan 16: Fechamento da Fase — seeds dev, comando de evidência, golden/smoke e verificação integral Summary

**A Fase 8 fechou com a verificação integral FRESCA verde e a degradação honesta travada por testes: seeds de desenvolvimento que preparam o ambiente com dados fictícios mas lógica REAL (tipos de serviço, requisitos-base por código e solicitações de exemplo protocoladas/canceladas pelos serviços reais), um comando `solicitacao:protocolar` que dá evidência de ponta a ponta (número/transição reais; bloqueio honesto com exit 1), golden cases (#[DataProvider]) e um smoke que encadeia a jornada inteira pelo portal — provando que faltar a foto da fachada BLOQUEIA o protocolo e que, sem zona oficial, a simulação fica PENDENTE (nunca permitido/não permitido inventado). Verificação fresca: `vendor/bin/pint --test` limpo, `npx tsc --noEmit` e `npm run build` verdes, suíte 848/848 (829 SQLite + 19 @group postgis com o container healthy). O smoke NAVEGÁVEL permanece como checkpoint humano (não abro o browser); o ambiente de dev foi migrado e seedado para o teste manual.**

## Performance

- **Duration:** ~50 min (inclui leitura de contexto e debug do status não-hidratado em seed)
- **Completed:** 2026-06-14
- **Tasks:** 2 automatizadas (seeds; comando + golden/smoke) + 1 checkpoint humano (verificação integral + smoke navegável)
- **Files:** 11 criados + 2 modificados — ZERO dependência nova

## Accomplishments

- **Seeds dev (lógica real, dados fictícios)**: `ViabilityServiceTypeSeeder` (4 tipos com códigos estáveis), `DocumentRequirementSeeder` (foto-fachada + termo-concessao + contrato-locacao opcional; pivot por-CNAE VAZIO — pendente SEDUR), `SolicitacaoDevSeeder` (rascunho instruído, protocolada, cancelada e contingência do cidadão `cidadao@sile.dev` via serviços reais). Registrados no `DatabaseSeeder` (catálogos antes do admin; `SolicitacaoDevSeeder` por último).
- **Comando de evidência** `solicitacao:protocolar {solicitacao}`: protocola pelo serviço real e imprime empresa/tipo/atividade, número gerado e transição; bloqueio honesto (documento/dados/transição) sai com exit 1.
- **Golden + smoke** travam a regressão do fluxo e a degradação honesta (bloqueio documental; pendente sem zona).
- **Verificação integral fresca** verde (pint/test/typecheck/build) — números abaixo.

## Task Commits

TDD estrito (RED→GREEN com evidência fresca):

1. **Task 1: seeds dev + DatabaseSeeder + DatabaseSeederTest** — `12cf547` (feat) — RED: catálogo/exemplos ausentes (0≠4) → GREEN: `DatabaseSeederTest` 6/6 (79 asserções).
2. **Task 2: comando + golden + smoke** — `f49800c` (test) — RED: comando inexistente → GREEN: comando 5/5, golden 4/4, smoke 3/3 (80 asserções no filtro).
3. **Task 3 (este metadado):** `docs(08-16)` — SUMMARY + STATE + ROADMAP no fechamento.

## Files Created/Modified

- `database/seeders/ViabilityServiceTypeSeeder.php` — catálogo mínimo de tipos de serviço (idempotente por code).
- `database/seeders/DocumentRequirementSeeder.php` — requisitos-base por código (idempotente por code).
- `database/seeders/SolicitacaoDevSeeder.php` — 4 solicitações de exemplo via serviços reais (idempotente por marcador).
- `database/seeders/DatabaseSeeder.php` — registra os três seeders.
- `app/Console/Commands/SolicitacaoProtocolarCommand.php` — comando de evidência.
- `tests/Feature/Solicitacao/SolicitacaoProtocolarCommandTest.php` — 5 testes do comando.
- `tests/Feature/Solicitacao/SolicitacaoGoldenCaseTest.php` + 4 fixtures — golden do fluxo.
- `tests/Feature/Solicitacao/SolicitacaoSmokeTest.php` — smoke de ponta a ponta.
- `tests/Feature/Seeders/DatabaseSeederTest.php` — cobre catálogos, exemplos e idempotência.

## Decisions Made

- **Seeds com serviços reais, sem simulação no seed**: protocolar/cancelar passam pelos serviços reais (número/timeline/auditoria genuínos); a simulação (que exige PostGIS para território) fica fora do seed para mantê-lo SQLite-safe — não afeta o protocolo.
- **Idempotência por marcador em `address_reference`** (sem chave natural): re-seed reusa o existente, sem novo número nem duplicação.
- **`factory()->draft()` em vez de `create()` cru** nos seeds/golden/smoke: o `create()` cru não hidrata o default de banco do `status` (fica `null` em memória e quebra os guards); a factory inicializa o status, e os FKs explícitos evitam criar empresa/usuário/tipo extras (preserva as contagens do seed).
- **`cnae_document_requirement` VAZIO** e **catálogo de parâmetros em 49** (sem alteração) — pendências/decisões honestas registradas.

## Deviations from Plan

- **Teste dedicado do comando (`SolicitacaoProtocolarCommandTest`) adicionado** além dos arquivos listados no plano: o TDD do comando exige um teste falhando primeiro; o plano cobria o comando só por `--help` + golden/smoke. Sem isto não haveria RED para o comando. Sem impacto de escopo (apenas adiciona cobertura).
- **Smoke dirigido por HTTP (endpoints reais)** em vez de chamadas diretas de serviço: o plano admite "seeds reais + factories"; driblar a jornada pelos endpoints prova a integração (rotas+policies+serviços) de verdade e é o "navegável" mais honesto em teste automatizado.

## Issues Encountered

- **`status` nulo em memória após `ViabilityRequest::create()`**: o default `rascunho` é do banco e não é hidratado no `create()` cru — o `CancelarSolicitacaoService` lia `$request->status->value` em `null`. Causa-raiz confirmada por trace (debugging sistemático); corrigido usando `factory()->draft()` (hidrata o status) com FKs explícitos.

## Verificação integral (EVIDÊNCIA FRESCA)

- **`vendor/bin/pint --test --format agent`** → `passed` (repo inteiro sem pendências).
- **`npx tsc --noEmit`** → sem erros.
- **`npm run build`** → built ok (`wizard` 28,6 kB; `map-imovel` 154,9 kB lazy).
- **Suíte SQLite** (`php artisan test --compact --exclude-group postgis`) → **829 testes, 829 passaram** (4305 asserções).
- **Grupo postgis** (`POSTGIS_TESTS_REQUIRED=true php artisan test --compact --group=postgis`, container `sile-pgsql` healthy) → **19 testes, 19 passaram** (106 asserções).
- **Suíte completa** (`POSTGIS_TESTS_REQUIRED=true php artisan test --compact`) → **848 testes, 848 passaram** (4411 asserções). Baseline da fase (08-13) era 835 → **+13** (1 seeder + 5 comando + 4 golden + 3 smoke). Zero regressão.

### Evidência do comando (dev pgsql real, após `php artisan migrate` + `db:seed`)

```
$ php artisan solicitacao:protocolar 1
Protocolo de solicitação de viabilidade
Solicitação #1
Empresa: MAGAZINE LUIZA S/A
Tipo de serviço: Viabilidade — primeiro estabelecimento
Atividade principal: 4712-1/00 — Comércio varejista de mercadorias em geral, com predominância de produtos alimentícios - minimercados, mercearias e armazéns

Resultado: PROTOCOLADA
Número de protocolo: VIA-2026-000004
Transição: rascunho → protocolada
Data do protocolo: 14/06/2026 15:10
Simulação pré-protocolo: não executada (orientativa, não bloqueia o protocolo).
EXIT=0
```

### Auditoria (dev pgsql real, read-only)

```
solicitacoes_audit=13   # > 0 (transições/protocolos auditados — RN-002)
transicoes=5            # exemplos protocolados/cancelados + o comando de evidência
exemplos_cidadao=4      # rascunho + protocolada + cancelada + contingência
```

## Smoke navegável (CHECKPOINT HUMANO — PENDENTE)

Não executado por mim (sem abrir o browser). O ambiente de dev foi preparado (`php artisan migrate` aplicou as tabelas da Fase 8 no banco `sile`; `php artisan db:seed` populou catálogos + exemplos). Roteiro sugerido com `composer dev` (http://localhost:8000):

1. **Cidadão** (`cidadao@sile.dev` / `password`): logar no portal → `/portal/solicitacoes` → "Nova solicitação" (tipo + empresa) → percorrer o wizard com um endereço de Salvador (mapa demarca o polígono; simulação mostra a tendência por CNAE — pendente sem zona COM motivo; faltar a fachada bloqueia o protocolo com aviso). Anexar a fachada e protocolar → número gerado. Abrir a consulta do protocolo (timeline + status amigável + prazo com ressalva). Gerar o link público e abrir em aba anônima (sem login) → status + timeline sem dados sensíveis. Cancelar uma solicitação. Conferir mobile (375px) e dark mode.
2. **Operador** (`admin@sile.dev` / `password`, console `/gestao`): registrar uma solicitação em **contingência** (origem auditada) e conferir o mesmo fluxo; iniciar um **atendimento presencial** por CPF e protocolar em nome do cidadão (banner "Atendendo: …"; auditoria com os dois CPFs).

Responder **"aprovado"** (anexando prints) ou descrever divergências para correção antes de fechar a fase.

## Pendências SEDUR / bloqueios externos da Fase 8 (registrados, NÃO simulados)

- **HU-071/072 (DAM/SEFAZ)** — BLOQUEADO → Fase 13 (DAM é da SEFAZ; SILE consulta/exibe; sem contrato agora). **Success criteria 4 do ROADMAP permanece bloqueado** (não simulado).
- **Origem `regin`** — BLOQUEADA → Fase 13 (enum extensível pronto; `contingencia` HU-148 é o caminho real hoje).
- **HU-139 (edifício comercial/complemento)** — ADIADO pendente SEDUR (complemento é texto livre por ora).
- **Lista oficial de tipos de serviço (HU-061)** — seed mínimo; carga oficial SEDUR substitui sem deploy (CRUD).
- **Requisitos documentais por CNAE** — `cnae_document_requirement` VAZIO até a planilha oficial SEDUR (resolver degrada honesto com os obrigatórios-base).
- **Prazo estimado real (HU-069 RN-005)** — depende da medição HU-129/Fase 15; parâmetro com ressalva até lá.
- **Zona urbanística (Quadro 10)** — pendente SEDUR (veredito/simulação ficam pendentes com motivo; herdado das Fases 4/5/7).
- **Procedimento de ciência presencial no balcão (HU-150)** e **regra fina de duplicidade/cancelamento (HU-070)** — aguardando definição SEDUR (defaults honestos parametrizados).

## Next Phase Readiness

- **Fase 9 (Fluxo Expresso)** depende das Fases 5, 6 e 8: a solicitação protocolada (número + primeiro evento de domínio `SolicitacaoProtocolada`) e o snapshot da simulação são os insumos; os ganchos de estado (em_analise/deferida/indeferida) já existem no enum/máquina (só ADICIONAR transições + listeners).
- **Critérios 1, 2, 3, 5 e 6 do ROADMAP** validados com evidência fresca; **critério 4 (DAM)** permanece BLOQUEADO → Fase 13 (registrado, não simulado).
- **Gate restante:** aprovação do smoke navegável (checkpoint humano acima).

---
*Phase: 08-solicitacao-de-viabilidade*
*Completed: 2026-06-14*
